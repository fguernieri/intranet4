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

$usuario = $_SESSION['usuario_nome'] ?? '';

// simple list of bars
// Try to load distinct filial values from Supabase 'insumos' table (lowercase column names)
$bars = [];
if (defined('SUPABASE_URL') && defined('SUPABASE_KEY')) {
  $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/insumos?select=filial&order=filial';
  // Try distinct syntax first (some PostgREST setups support distinct(column))
  $url_distinct = rtrim(SUPABASE_URL, '/') . '/rest/v1/insumos?select=distinct(filial)&order=filial';
  $ch = curl_init($url_distinct);
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
      if (!empty($r['filial'])) $bars[] = $r['filial'];
    }
    $bars = array_values(array_unique($bars));
  } else {
    // fallback: try non-distinct and dedupe client-side
    $ch2 = curl_init($url);
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
        if (!empty($r['filial'])) $bars[] = $r['filial'];
      }
      $bars = array_values(array_unique($bars));
    }
  }
}

// If still empty, provide a sensible fallback so UI doesn't break
if (empty($bars)) {
  $bars = ['WE ARE BASTARDS', 'BAR DA FABRICA', 'CROSS', 'BAR DO MEIO'];
}

?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Módulo - Pedido de compra (Orders)</title>
  <link href="/assets/css/style.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 flex min-h-screen">
  <!-- sidebar.php is expected to render the sidebar on the left -->
  <main class="flex-1 p-6">
    <div class="max-w-7xl mx-auto w-full">
      <header class="mb-6">
        <h1 class="text-2xl font-bold">Módulo - Pedido de compra (Orders)</h1>
        <p class="text-gray-400 text-sm">Usuário: <?= htmlspecialchars($usuario) ?></p>
        <div class="mt-3 space-x-2">
          <a href="manage_orders.php" class="inline-block bg-yellow-500 text-black px-3 py-2 rounded hover:bg-yellow-400">Gerenciador de pedidos</a>
          <a href="export_form.php" class="inline-block bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-500">Exportar pedido</a>
          <a href="list.php" class="inline-block bg-green-600 text-white px-3 py-2 rounded hover:bg-green-500">Ver recibos</a>
        </div>
      </header>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <?php foreach ($bars as $bar): ?>
          <a href="orders.php?filial=<?= urlencode($bar) ?>" class="block p-4 bg-gray-800 rounded hover:bg-gray-700">
            <h2 class="font-semibold text-lg text-yellow-400"><?= htmlspecialchars($bar) ?></h2>
            <p class="text-sm text-gray-300">Fazer pedido para esta filial</p>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </main>
</body>
</html>