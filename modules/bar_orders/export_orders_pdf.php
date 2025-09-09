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
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

if (!defined('SUPABASE_URL') || !defined('SUPABASE_KEY') || !defined('SUPABASE_ORDERS_TABLE')) {
    echo "Configuração do Supabase ausente"; exit;
}

$base = rtrim(SUPABASE_URL, '/');
// build filter
$filters = [];
if ($filial !== '') $filters[] = 'filial=eq.' . urlencode($filial);
if ($start !== '') $filters[] = 'data=gte.' . urlencode($start . 'T00:00:00');
if ($end !== '') $filters[] = 'data=lte.' . urlencode($end . 'T23:59:59');
$filterStr = '';
if (!empty($filters)) $filterStr = '?' . implode('&', $filters) . '&order=numero_pedido,data,produto';
else $filterStr = '?order=numero_pedido,data,produto';

$url = "$base/rest/v1/" . SUPABASE_ORDERS_TABLE . $filterStr;
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
if ($err || $code < 200 || $code >= 300) { echo "Falha ao buscar itens: " . ($err ?: $resp); exit; }
$items = json_decode($resp, true) ?: [];

// fetch metas for pedidos in this batch
$pedidos = array_values(array_unique(array_filter(array_map(function($i){ return $i['numero_pedido'] ?? ''; }, $items))));
$metas = [];
if (count($pedidos)) {
  $in = implode(',', array_map('urlencode', $pedidos));
  $metaUrl = "$base/rest/v1/order_item_meta?numero_pedido=in.($in)&select=*";
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
      $metas[$r['numero_pedido'] . '||' . $r['produto']] = $r;
    }
  }
}

// build nice printable HTML grouped by fornecedor (supplier)
$company = defined('APP_NAME') ? htmlspecialchars(APP_NAME) : 'Pedidos';
$download_label = ($filial !== '') ? preg_replace('/[^A-Za-z0-9_-]/','_', $filial) : 'filtrados';
$title = 'Conferência de Entrega - ' . $download_label . ' - ' . date('Y-m-d H:i');

$html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
$html .= '<style>body{font-family: Arial, Helvetica, sans-serif; color:#000; margin:20px;} h1{font-size:18px;margin:0 0 6px 0} h2{font-size:15px;margin:8px 0} .meta{font-size:12px;color:#444;margin-bottom:12px} table{width:100%;border-collapse:collapse;margin-top:8px;margin-bottom:18px} th,td{border:1px solid #333;padding:6px;text-align:left;font-size:13px} th{background:#f6f6f6} .center{text-align:center} .block{page-break-inside:avoid;margin-bottom:18px} .small{font-size:12px;color:#444}</style>';
$html .= '</head><body>';
$html .= '<h1>' . $company . ' — Conferência de Entrega</h1>';
$html .= '<div class="meta">Filial: ' . htmlspecialchars($filial ?: 'Todas') . ' · Período: ' . htmlspecialchars($start ?: '...') . ' — ' . htmlspecialchars($end ?: '...') . ' · Gerado por: ' . htmlspecialchars($_SESSION['usuario_nome'] ?? '') . ' em ' . date('Y-m-d H:i') . '</div>';

// group items by fornecedor (from metas). fallback to 'Sem Fornecedor'
$groups = [];
foreach ($items as $it) {
  $key = ($it['numero_pedido'] ?? '') . '||' . ($it['produto'] ?? '');
  $m = $metas[$key] ?? [];
  $forn = trim((string)($m['fornecedor'] ?? '')) ?: 'Sem Fornecedor';
  $data_entrega = $m['data_entrega'] ?? '';
  if (!$data_entrega) {
    $data_entrega = date('Y-m-d');
  }
  $groups[$forn][] = [
    'pedido' => $it['numero_pedido'] ?? '',
    'data' => $it['data'] ?? '',
    'produto' => $it['produto'] ?? '',
    'qtde' => $it['qtde'] ?? '',
    'data_entrega' => $data_entrega,
    'obs' => $m['obs_comprador'] ?? ''
  ];
}

// sort suppliers alphabetically
ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

$totalItems = 0;
foreach ($groups as $forn => $rows) {
  $html .= '<div class="block">';
  $html .= '<h2>Fornecedor: ' . htmlspecialchars($forn) . ' (Itens: ' . count($rows) . ')</h2>';
  // Textos informativos por filial
  $filial_texts = [
    'BAR DA FABRICA' => 'BAR DA FABRICA BASTARDS Comendador Lustosa 69<br>Taproom: 39.866.345/0001-68<br>RECEBIMENTOS: 8:30 as 11:30 e 13:30 as 18hrs',
    'WE ARE BASTARDS' => 'WE ARE BASTARDS PUB <br>Iguaçu 2296<br>WAB 30.307.697/000109 (BAR)<br>BAW 30.042.812/0001-60 (COZ)<br>RECEBIMENTOS: 10 as 11:30 e 13:30 as 18hrs',
    'CROSS' => 'CROSSROADS<br>Iguaçu 2310<br>CROSS 01.906.570/0001-08<br>RECEBIMENTOS: 10 as 11:30 e 13:30 as 18hrs',
    'BAR DO MEIO' => 'BAR DO MEIO <br>Iguaçu 2304<br>BDM 54.382.100/0001-59<br>RECEBIMENTOS: 10 as 11:30 e 13:30 as 18hrs',
  ];
  if (isset($filial_texts[$filial])) {
    $html .= '<div class="meta" style="margin-bottom:8px;font-size:13px;color:#222">' . $filial_texts[$filial] . '</div>';
  }
    $html .= '<table><thead><tr>'
      . '<th style="width:18%">Pedido</th>'
      . '<th style="width:32%">Produto</th>'
      . '<th class="center" style="width:10%">Qtde</th>'
      . '<th style="width:20%">Data Entrega</th>'
      . '<th style="width:20%">Obs Comprador</th>'
      . '</tr></thead><tbody>';
  foreach ($rows as $r) {
      $html .= '<tr>'
        . '<td style="width:18%">' . htmlspecialchars($r['pedido']) . '</td>'
        . '<td style="width:32%">' . htmlspecialchars($r['produto']) . '</td>'
        . '<td class="center" style="width:10%">' . htmlspecialchars($r['qtde']) . '</td>'
        . '<td style="width:20%">' . htmlspecialchars($r['data_entrega']) . '</td>'
        . '<td style="width:20%">' . htmlspecialchars($r['obs']) . '</td>'
        . '</tr>';
    $totalItems++;
  }
  $html .= '</tbody></table>';
  // signature / nota block for this supplier (conferente signs; supplier does not sign)
  $html .= '<div style="margin-top:8px;">';
  $html .= '<table style="width:100%;border:none;margin-top:6px"><tr>';
  $html .= '<td style="border:none;padding:6px;vertical-align:top">Conferente:<div style="border-top:1px solid #333;width:60%;margin-top:30px"></div></td>';
  $html .= '<td style="border:none;padding:6px;vertical-align:top">Número da Nota:<div style="border-top:1px solid #333;width:60%;margin-top:30px"></div></td>';
  $html .= '<td style="border:none;padding:6px;vertical-align:top">Data:<div style="border-top:1px solid #333;width:50%;margin-top:30px"></div></td>';
  $html .= '</tr></table>';
  $html .= '</div>';
  $html .= '</div>';
}

$html .= '<p class="small">Total de itens (todas filiais/pedidos): ' . $totalItems . '</p>';
$html .= '<script>window.print && setTimeout(()=>{window.print();},200);</script>';
$html .= '</body></html>';

// try Dompdf
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoload)) {
  require_once $autoload;
  if (class_exists('\Dompdf\Dompdf')) {
    try {
      $dompdf = new \Dompdf\Dompdf();
      $dompdf->loadHtml($html);
      $dompdf->setPaper('A4','portrait');
      $dompdf->render();
      $filename = $download_label . '_' . date('Ymd_Hi') . '.pdf';
      header('Content-Type: application/pdf');
      header('Content-Disposition: attachment; filename="' . $filename . '"');
      echo $dompdf->output();
      exit;
    } catch (Exception $e) {
      // fall back to HTML
    }
  }
}

echo $html;
