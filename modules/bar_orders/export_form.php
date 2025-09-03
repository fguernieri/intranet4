<?php
require_once __DIR__ . '/../../sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id'])) {
  header('Location: /login.php');
  exit;
}

// load config (prefer user-created config.php)
if (file_exists(__DIR__ . '/config.php')) {
  require_once __DIR__ . '/config.php';
} else {
  require_once __DIR__ . '/config.example.php';
}

// fetch filial list from insumos so user can pick a filial
$filiais = [];
if (defined('SUPABASE_URL') && defined('SUPABASE_KEY')) {
  $base = rtrim(SUPABASE_URL, '/');
  // try distinct first (may not be available on all PostgREST setups)
  $url_distinct = "{$base}/rest/v1/insumos?select=distinct(filial)&order=filial";
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
      if (!empty($r['filial'])) $filiais[] = $r['filial'];
    }
    $filiais = array_values(array_unique($filiais));
  } else {
    // fallback to non-distinct and dedupe client-side
    $url = "{$base}/rest/v1/insumos?select=filial&order=filial";
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
        if (!empty($r['filial'])) $filiais[] = $r['filial'];
      }
      $filiais = array_values(array_unique($filiais));
    }
  }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Exportar Pedido</title>
  <link href="/assets/css/style.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 flex min-h-screen">
  <main class="flex-1 p-6">
    <div class="max-w-3xl mx-auto w-full">
      <header class="mb-6">
        <a href="index.php" class="text-sm text-gray-400">&larr; Voltar</a>
        <h1 class="text-2xl font-bold">Exportar Pedido</h1>
        <p class="text-gray-400 text-sm">Informe o número do pedido e escolha o formato de exportação.</p>
      </header>

      <form method="get" action="export.php" class="bg-gray-800 p-4 rounded">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
          <div>
            <label class="block text-sm mb-1">Filial</label>
            <select name="filial" class="w-full p-2 bg-gray-900 rounded text-sm">
              <option value="">-- Todas as filiais --</option>
              <?php if (empty($filiais)): ?>
                <option disabled>Não foram encontradas filiais (verifique config)</option>
              <?php else: ?>
                <?php foreach ($filiais as $f): ?>
                  <option value="<?= htmlspecialchars($f, ENT_QUOTES) ?>"><?= htmlspecialchars($f) ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>

          <div>
            <label class="block text-sm mb-1">Formato</label>
            <select name="format" class="w-full p-2 bg-gray-900 rounded text-sm">
              <option value="csv">CSV (planilha)</option>
              <option value="json">JSON</option>
              <option value="print">Imprimir</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
          <div>
            <label class="block text-sm mb-1">Data início</label>
            <input type="date" name="start" class="w-full p-2 bg-gray-900 rounded text-sm">
          </div>
          <div>
            <label class="block text-sm mb-1">Data fim</label>
            <input type="date" name="end" class="w-full p-2 bg-gray-900 rounded text-sm">
          </div>
        </div>

        <div class="mb-4">
          <label class="block text-sm mb-1">Número do Pedido (opcional)</label>
          <input name="pedido" type="text" class="w-full p-2 bg-gray-900 rounded text-sm" placeholder="Ex: 20250902123045">
        </div>

        <div class="flex gap-2">
          <button type="submit" class="bg-green-600 px-4 py-2 rounded">Exportar</button>
          <a href="receipt.php" class="bg-gray-700 px-4 py-2 rounded text-sm">Ver recibos</a>
        </div>
      </form>

      <div class="mt-6 text-sm text-gray-400">
        <p>Dica: o número do pedido aparece na tela de recibo após enviar um pedido. Use o formato mostrado no recibo para localizar corretamente.</p>
      </div>
    </div>
  </main>
</body>
</html>
