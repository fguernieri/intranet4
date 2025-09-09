<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

// load config
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    require_once __DIR__ . '/config.example.php';
}


$filial = $_GET['filial'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

if (!$filial || !$data_inicio || !$data_fim) {
  echo "Filtros obrigatórios não informados (filial, data_inicio, data_fim)"; exit;
}

if (!defined('SUPABASE_URL') || !defined('SUPABASE_KEY') || !defined('SUPABASE_ORDERS_TABLE')) {
    echo "Configuração do Supabase ausente"; exit;
}

$base = rtrim(SUPABASE_URL, '/');
$url = "$base/rest/v1/" . SUPABASE_ORDERS_TABLE .
  "?filial=eq." . urlencode($filial) .
  "&data_pedido=gte." . urlencode($data_inicio) .
  "&data_pedido=lte." . urlencode($data_fim) .
  "&order=numero_pedido,produto";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'apikey: ' . SUPABASE_KEY,
  'Authorization: Bearer ' . SUPABASE_KEY,
  'Content-Type: application/json'
]);
$resp = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($err || $code < 200 || $code >= 300) {
  echo "Falha ao buscar pedido: " . ($err ?: $resp);
  exit;
}

$items = json_decode($resp, true) ?: [];

// Agrupar pedidos para buscar metas de todos
$pedidos = array_unique(array_column($items, 'numero_pedido'));


// fetch metas para todos os pedidos
$meta = [];
if ($pedidos) {
  $pedido_filter = implode(',', array_map('urlencode', $pedidos));
  $metaUrl = "$base/rest/v1/order_item_meta?numero_pedido=in.($pedido_filter)&select=*";
  $ch2 = curl_init($metaUrl);
  curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'apikey: ' . SUPABASE_KEY,
    'Authorization: Bearer ' . SUPABASE_KEY,
    'Content-Type: application/json'
  ]);
  $resp2 = curl_exec($ch2);
  $err2 = curl_error($ch2);
  $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
  curl_close($ch2);
  if (!$err2 && $code2 >= 200 && $code2 < 300) {
    $rows = json_decode($resp2, true) ?: [];
    foreach ($rows as $r) {
      $meta[$r['numero_pedido'] . '|' . $r['produto']] = $r;
    }
  }
}

// build printable HTML

$company = htmlspecialchars(constant('APP_NAME') ?? 'Pedidos');
$title = 'Conferência de Entrega - Pedidos';

$html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
$html .= '<style>body{font-family: Arial, Helvetica, sans-serif; color:#000; margin:24px;} .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px} h1{font-size:18px;margin:0} table{width:100%;border-collapse:collapse;margin-top:12px} th,td{border:1px solid #333;padding:8px;text-align:left;font-size:14px} th{background:#f2f2f2} .small{font-size:12px;color:#444} .center{text-align:center}</style>';
$html .= '</head><body>';
$html .= '<div class="header"><div><h1>' . $company . '</h1><div class="small">Conferência de Entrega</div></div><div class="small">Filial: ' . htmlspecialchars($filial) . ' | Período: ' . htmlspecialchars($data_inicio) . ' a ' . htmlspecialchars($data_fim) . '</div></div>';
$html .= '<table><thead><tr><th>Pedido</th><th>Produto</th><th class="center">Qtde</th><th>Fornec.</th><th>Data Entrega</th><th>Obs Comprador</th></tr></thead><tbody>';

foreach ($items as $it) {
  $pedido_num = htmlspecialchars($it['numero_pedido'] ?? '');
  $produto = htmlspecialchars($it['produto'] ?? '');
  $qt = htmlspecialchars($it['qtde'] ?? '');
  $m = $meta[$pedido_num . '|' . $it['produto']] ?? [];
  $forn = htmlspecialchars($m['fornecedor'] ?? '');
  $de = htmlspecialchars($m['data_entrega'] ?? '');
  $obs = htmlspecialchars($m['obs_comprador'] ?? '');
  $html .= "<tr><td>$pedido_num</td><td>$produto</td><td class=\"center\">$qt</td><td>$forn</td><td>$de</td><td>$obs</td></tr>";
}

$html .= '</tbody></table>';
$html .= '<p class="small">Gerado por: ' . htmlspecialchars($_SESSION['usuario_nome'] ?? '') . ' em ' . date('Y-m-d H:i') . '</p>';
$html .= '<script>window.print && setTimeout(()=>{window.print();},200);</script>';
$html .= '</body></html>';

// try to use Dompdf if available, otherwise output HTML
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoload)) {
  require_once $autoload;
  if (class_exists('\Dompdf\Dompdf')) {
    try {
      $dompdf = new \Dompdf\Dompdf();
      $dompdf->loadHtml($html);
      $dompdf->setPaper('A4','portrait');
      $dompdf->render();
  $filename = 'pedidos_' . preg_replace('/[^A-Za-z0-9_-]/','_', $filial) . '_' . $data_inicio . '_' . $data_fim . '.pdf';
      header('Content-Type: application/pdf');
      header('Content-Disposition: attachment; filename="' . $filename . '"');
      echo $dompdf->output();
      exit;
    } catch (Exception $e) {
      // fall through to HTML
    }
  }
}

// fallback: serve printable HTML that will trigger print dialog
echo $html;
