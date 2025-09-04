<?php
require_once __DIR__ . '/../../sidebar.php';
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
if ($pedido === '') {
    die('Pedido inválido');
}

$items = [];
$pedido_meta = null;
if (defined('SUPABASE_URL') && defined('SUPABASE_KEY') && defined('SUPABASE_ORDERS_TABLE')) {
    $base = rtrim(SUPABASE_URL, '/');
    $url = "{$base}/rest/v1/" . SUPABASE_ORDERS_TABLE . "?numero_pedido=eq." . urlencode($pedido) . "&order=data,produto";
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
        // group by numero_pedido; choose the first row for metadata
        foreach ($rows as $r) {
            $items[] = $r;
            if ($pedido_meta === null) {
                $pedido_meta = [
                    'numero_pedido' => $r['numero_pedido'] ?? $pedido,
                    'data' => $r['data'] ?? null,
                    'usuario' => $r['usuario'] ?? null,
                    'filial' => $r['filial'] ?? null
                ];
            }
        }
    }
}

?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Recibo do Pedido <?= htmlspecialchars($pedido) ?></title>
  <link href="/assets/css/style.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 flex min-h-screen">
  <main class="flex-1 p-6">
    <div class="max-w-4xl mx-auto w-full">
      <header class="mb-6">
        <a href="order.php?filial=<?= urlencode($pedido_meta['filial'] ?? '') ?>" class="text-sm text-gray-400">&larr; Voltar</a>
        <h1 class="text-2xl font-bold">Recibo do Pedido</h1>
        <?php if ($pedido_meta): ?>
          <p class="text-sm text-gray-300">Pedido: <strong><?= htmlspecialchars($pedido_meta['numero_pedido']) ?></strong></p>
          <p class="text-sm text-gray-300">Data: <strong><?= htmlspecialchars($pedido_meta['data']) ?></strong></p>
          <p class="text-sm text-gray-300">Usuario: <strong><?= htmlspecialchars($pedido_meta['usuario']) ?></strong></p>
          <p class="text-sm text-gray-300">Filial: <strong><?= htmlspecialchars($pedido_meta['filial']) ?></strong></p>
          <p class="text-sm text-gray-300">Setor: <strong><?= htmlspecialchars($pedido_meta['setor'] ?? '') ?></strong></p>
        <?php else: ?>
          <div class="bg-yellow-700 text-black p-3 rounded">Não foi possível recuperar os detalhes do pedido.</div>
        <?php endif; ?>
      </header>

      <?php if (!empty($items)): ?>
        <div class="bg-gray-800 p-4 rounded">
          <div class="mb-4">
            <a class="inline-block bg-indigo-600 px-4 py-2 rounded mr-2" href="export.php?pedido=<?= urlencode($pedido) ?>&format=pdf" target="_blank">Baixar PDF</a>
            <a class="inline-block bg-teal-600 px-4 py-2 rounded mr-2" href="export.php?pedido=<?= urlencode($pedido) ?>&format=xlsx">Download XLSX</a>
            <button onclick="window.print()" class="inline-block bg-gray-700 px-4 py-2 rounded">Imprimir</button>
          </div>
          <table class="w-full text-sm">
            <thead class="text-left text-yellow-400"><tr><th>Produto</th><th>Unidade</th><th>Qtde</th><th>Obs</th></tr></thead>
            <tbody>
              <?php foreach ($items as $it): ?>
                <tr class="border-b border-gray-700"><td class="py-2"><?= htmlspecialchars($it['produto'] ?? '') ?></td><td><?= htmlspecialchars($it['und'] ?? '') ?></td><td><?= htmlspecialchars($it['qtde'] ?? '') ?></td><td><?= htmlspecialchars($it['observacao'] ?? '') ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="bg-yellow-700 text-black p-3 rounded">Nenhum item encontrado para este pedido.</div>
      <?php endif; ?>

    </div>
  </main>
</body>
</html>