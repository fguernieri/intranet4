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

$pedido = $_GET['pedido'] ?? '';
$filial = $_GET['filial'] ?? '';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
$format = strtolower($_GET['format'] ?? ''); // csv | json | print

$items = [];
$pedido_meta = null;
$autoprint = isset($_GET['autoprint']) && $_GET['autoprint'];
if (defined('SUPABASE_URL') && defined('SUPABASE_KEY') && defined('SUPABASE_ORDERS_TABLE')) {
  $base = rtrim(SUPABASE_URL, '/');
  if ($pedido !== '') {
    // exact pedido lookup
    $url = "{$base}/rest/v1/" . SUPABASE_ORDERS_TABLE . "?numero_pedido=eq." . urlencode($pedido) . "&order=data,produto";
  } else {
    // build a filter for filial and date range
    $filters = [];
    if ($filial !== '') {
      $filters[] = 'filial=eq.' . urlencode($filial);
    }
    // parse dates and build gte/lte on 'data' column (assuming data stored as timestamp)
    if ($start !== '') {
      // start of day
      $s = $start . 'T00:00:00';
      $filters[] = 'data=gte.' . urlencode($s);
    }
    if ($end !== '') {
      // end of day
      $e = $end . 'T23:59:59';
      $filters[] = 'data=lte.' . urlencode($e);
    }
    $filterStr = '';
    if (!empty($filters)) $filterStr = '?' . implode('&', $filters) . '&order=data,produto';
    else $filterStr = '?order=data,produto';
    $url = "{$base}/rest/v1/" . SUPABASE_ORDERS_TABLE . $filterStr;
  }

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
  if (!$err && $code >= 200 && $code < 300) {
    $rows = json_decode($resp, true) ?: [];
    foreach ($rows as $r) {
      $items[] = $r;
      if ($pedido_meta === null) {
        $pedido_meta = [
          'numero_pedido' => $r['numero_pedido'] ?? $pedido,
          'data' => $r['data'] ?? null,
          'usuario' => $r['usuario'] ?? null,
          'filial' => $r['filial'] ?? $filial,
          'setor' => $r['setor'] ?? ''
        ];
      }
    }
  }
}

// If export format requested, output directly
if ($format === 'csv') {
  // send CSV download
  if ($pedido !== '') {
    $filename = 'pedido_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $pedido) . '.csv';
  } else {
    $label = ($filial !== '') ? preg_replace('/[^A-Za-z0-9_-]/', '_', $filial) : 'all_filiais';
    $range = ($start || $end) ? ($start . '_' . $end) : 'all_dates';
    $filename = sprintf('pedidos_%s_%s.csv', $label, $range);
  }
  // Use semicolon-separated CSV with UTF-8 BOM so Excel opens columns correctly on Windows
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  $out = fopen('php://output', 'w');
  // write UTF-8 BOM
  fwrite($out, "\xEF\xBB\xBF");
  // header row in requested Excel order for CSV exports
  fputcsv($out, ['PRODUTO','CATEGORIA','UND','QTDE','OBS','SETOR','FILIAL','USUARIO','NUMERO_PEDIDO','DATA'], ';');
  foreach ($items as $it) {
    fputcsv($out, [
      $it['produto'] ?? '',
      $it['categoria'] ?? '',
      $it['und'] ?? '',
      $it['qtde'] ?? '',
      $it['observacao'] ?? '',
      $it['setor'] ?? '',
      $it['filial'] ?? '',
      $it['usuario'] ?? '',
      $it['numero_pedido'] ?? '',
      $it['data'] ?? ''
    ], ';');
  }
  fclose($out);
    exit;
}

if ($format === 'json') {
  header('Content-Type: application/json; charset=utf-8');
  // ensure meta includes setor when available
  if ($pedido_meta && !isset($pedido_meta['setor'])) $pedido_meta['setor'] = '';
  echo json_encode(['meta' => $pedido_meta, 'items' => $items], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($format === 'xlsx') {
  // prefer PhpSpreadsheet if available
  $autoload = __DIR__ . '/../../vendor/autoload.php';
  if (file_exists($autoload)) {
    require_once $autoload;
    try {
      $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
      $sheet = $spreadsheet->getActiveSheet();
      // For Excel the requested column order is: PRODUTO - CATEGORIA - UND - QTDE - OBS - SETOR - FILIAL - USUARIO - PEDIDO - DATA
      $headers = ['PRODUTO','CATEGORIA','UND','QTDE','OBS','SETOR','FILIAL','USUARIO','NUMERO_PEDIDO','DATA'];
      $sheet->fromArray($headers, null, 'A1');
      $row = 2;
      foreach ($items as $it) {
        $sheet->fromArray([
          $it['produto'] ?? '',
          $it['categoria'] ?? '',
          $it['und'] ?? '',
          $it['qtde'] ?? '',
          $it['observacao'] ?? '',
          $it['setor'] ?? '',
          $it['filial'] ?? '',
          $it['usuario'] ?? '',
          $it['numero_pedido'] ?? '',
          $it['data'] ?? ''
        ], null, 'A' . $row);
        $row++;
      }
      // output
      if ($pedido !== '') {
        $filename = 'pedido_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $pedido) . '.xlsx';
      } else {
        $label = ($filial !== '') ? preg_replace('/[^A-Za-z0-9_-]/', '_', $filial) : 'all_filiais';
        $range = ($start || $end) ? ($start . '_' . $end) : 'all_dates';
        $filename = sprintf('pedidos_%s_%s.xlsx', $label, $range);
      }
      header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      header('Content-Disposition: attachment; filename="' . $filename . '"');
      $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
      $writer->save('php://output');
      exit;
    } catch (Exception $e) {
      // fallback to CSV on error
    }
  }
  // fallback: generate CSV if PhpSpreadsheet not available
  if ($pedido !== '') {
    $filename = 'pedido_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $pedido) . '.csv';
  } else {
    $label = ($filial !== '') ? preg_replace('/[^A-Za-z0-9_-]/', '_', $filial) : 'all_filiais';
    $range = ($start || $end) ? ($start . '_' . $end) : 'all_dates';
    $filename = sprintf('pedidos_%s_%s.csv', $label, $range);
  }
  // fallback CSV with semicolon delimiter and BOM (match Excel ordering)
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  $out = fopen('php://output', 'w');
  fwrite($out, "\xEF\xBB\xBF");
  // columns for Excel fallback CSV: PRODUTO - CATEGORIA - UND - QTDE - OBS - SETOR - FILIAL - USUARIO - PEDIDO - DATA
  fputcsv($out, ['PRODUTO','CATEGORIA','UND','QTDE','OBS','SETOR','FILIAL','USUARIO','NUMERO_PEDIDO','DATA'], ';');
  foreach ($items as $it) {
    fputcsv($out, [
      $it['produto'] ?? '',
      $it['categoria'] ?? '',
      $it['und'] ?? '',
      $it['qtde'] ?? '',
      $it['observacao'] ?? '',
      $it['setor'] ?? '',
      $it['filial'] ?? '',
      $it['usuario'] ?? '',
      $it['numero_pedido'] ?? '',
      $it['data'] ?? ''
    ], ';');
  }
  fclose($out);
  exit;
}

// server-side PDF generation (if requested). Falls back to autoprint view when Dompdf not available.
if ($format === 'pdf') {
  $autoload = __DIR__ . '/../../vendor/autoload.php';
  if (file_exists($autoload)) {
    require_once $autoload;
    if (class_exists('\Dompdf\Dompdf')) {
      // build simple printable HTML for the PDF
      $title = $pedido ? 'Pedido ' . htmlspecialchars($pedido) : 'Pedidos exportados';
      $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans, Arial, Helvetica, sans-serif;font-size:12px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #444;padding:6px;text-align:left}th{background:#eee}</style></head><body>';
      $html .= '<h2>' . $title . '</h2>';
      if ($pedido_meta) {
        $html .= '<p><strong>Pedido:</strong> ' . htmlspecialchars($pedido_meta['numero_pedido']) . '<br>';
        $html .= '<strong>Data:</strong> ' . htmlspecialchars($pedido_meta['data']) . ' &nbsp; <strong>Usuário:</strong> ' . htmlspecialchars($pedido_meta['usuario']) . '<br>';
        $html .= '<strong>Filial:</strong> ' . htmlspecialchars($pedido_meta['filial']) . ' &nbsp; <strong>Setor:</strong> ' . htmlspecialchars($pedido_meta['setor'] ?? '') . '</p>';
      }
      $html .= '<table><thead><tr><th>Data</th><th>Produto</th><th>Categoria</th><th>Unidade</th><th>Qtde</th><th>Obs</th><th>Usuário</th><th>Setor</th></tr></thead><tbody>';
      foreach ($items as $it) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($it['data'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($it['produto'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($it['categoria'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($it['und'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($it['qtde'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($it['observacao'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($it['usuario'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($it['setor'] ?? '') . '</td>';
        $html .= '</tr>';
      }
      $html .= '</tbody></table></body></html>';

      try {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $filename = $pedido ? ('pedido_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $pedido) . '.pdf') : 'pedidos.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dompdf->output();
        exit;
      } catch (Exception $e) {
        // fall through to autoprint fallback below
      }
    }
  }
  // fallback: open printable view which will trigger browser print dialog
  $params = [];
  if ($pedido !== '') $params[] = 'pedido=' . urlencode($pedido);
  if ($filial !== '') $params[] = 'filial=' . urlencode($filial);
  $params[] = 'autoprint=1';
  $loc = 'export.php' . ($params ? ('?' . implode('&', $params)) : '');
  header('Location: ' . $loc);
  exit;
}

// printable HTML default
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Exportar Pedido <?= htmlspecialchars($pedido) ?></title>
  <link href="/assets/css/style.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100">
  <?php if (!$autoprint) require_once __DIR__ . '/../../sidebar.php'; ?>
  <main class="p-6 max-w-4xl mx-auto">
    <header class="mb-4">
      <h1 class="text-2xl font-bold"><?php echo $pedido ? 'Exportar Pedido' : 'Exportar Pedidos por Filtro'; ?></h1>
      <?php if ($pedido_meta): ?>
        <p class="text-sm text-gray-300">Pedido: <strong><?= htmlspecialchars($pedido_meta['numero_pedido']) ?></strong></p>
        <p class="text-sm text-gray-300">Data: <strong><?= htmlspecialchars($pedido_meta['data']) ?></strong></p>
        <p class="text-sm text-gray-300">Usuário: <strong><?= htmlspecialchars($pedido_meta['usuario']) ?></strong></p>
        <p class="text-sm text-gray-300">Filial: <strong><?= htmlspecialchars($pedido_meta['filial']) ?></strong></p>
        <p class="text-sm text-gray-300">Setor: <strong><?= htmlspecialchars($pedido_meta['setor'] ?? '') ?></strong></p>
      <?php else: ?>
        <?php if ($pedido === ''): ?>
          <p class="text-sm text-gray-300">Filial: <strong><?= htmlspecialchars($filial ?: 'Todas') ?></strong></p>
          <p class="text-sm text-gray-300">Período: <strong><?= htmlspecialchars($start ?: '...') ?> — <?= htmlspecialchars($end ?: '...') ?></strong></p>
        <?php else: ?>
          <div class="bg-yellow-700 text-black p-3 rounded">Não foi possível recuperar os detalhes do pedido.</div>
        <?php endif; ?>
      <?php endif; ?>
    </header>

    <div class="mb-4">
      <a class="inline-block bg-green-600 px-4 py-2 rounded mr-2" href="export.php?pedido=<?= urlencode($pedido) ?>&format=csv">Download CSV</a>
      <a class="inline-block bg-blue-600 px-4 py-2 rounded mr-2" href="export.php?pedido=<?= urlencode($pedido) ?>&format=json">Download JSON</a>
  <a class="inline-block bg-indigo-600 px-4 py-2 rounded mr-2" href="export.php?pedido=<?= urlencode($pedido) ?>&autoprint=1" target="_blank">Baixar PDF</a>
  <a class="inline-block bg-teal-600 px-4 py-2 rounded mr-2" href="export.php?pedido=<?= urlencode($pedido) ?>&format=xlsx">Download XLSX</a>
      <button onclick="window.print()" class="inline-block bg-gray-700 px-4 py-2 rounded">Imprimir</button>
      <a href="order.php?filial=<?= urlencode($pedido_meta['filial'] ?? '') ?>" class="ml-2 text-sm text-gray-400">Voltar</a>
    </div>

    <?php if (!empty($items)): ?>
      <div class="bg-gray-800 p-4 rounded">
        <table class="w-full text-sm">
          <thead class="text-left text-yellow-400"><tr><th>Data</th><th>Produto</th><th>Unidade</th><th>Qtde</th><th>Obs</th><th>Usuario</th><th>Setor</th></tr></thead>
          <tbody>
            <?php foreach ($items as $it): ?>
              <tr class="border-b border-gray-700"><td class="py-2"><?= htmlspecialchars($it['data'] ?? '') ?></td><td><?= htmlspecialchars($it['produto'] ?? '') ?></td><td><?= htmlspecialchars($it['und'] ?? '') ?></td><td><?= htmlspecialchars($it['qtde'] ?? '') ?></td><td><?= htmlspecialchars($it['observacao'] ?? '') ?></td><td><?= htmlspecialchars($it['usuario'] ?? '') ?></td><td><?= htmlspecialchars($it['setor'] ?? '') ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="bg-yellow-700 text-black p-3 rounded">Nenhum item encontrado para este pedido.</div>
    <?php endif; ?>

  </main>
  <?php if ($autoprint): ?>
  <script>
    // auto-print for PDF export then close after short delay
    window.addEventListener('load', function(){
      setTimeout(function(){ window.print(); }, 200);
      // optional: close window after print (may be blocked by some browsers)
      window.onafterprint = function(){ setTimeout(function(){ window.close(); }, 200); };
    });
  </script>
  <?php endif; ?>
</body>
</html>