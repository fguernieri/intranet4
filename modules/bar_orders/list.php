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

$orders = [];
$error = null;

if (defined('SUPABASE_URL') && defined('SUPABASE_KEY') && defined('SUPABASE_ORDERS_TABLE')) {
    $base = rtrim(SUPABASE_URL, '/');
    // fetch recent rows (items) and group by numero_pedido client-side
    $url = "{$base}/rest/v1/" . SUPABASE_ORDERS_TABLE . "?select=numero_pedido,data,usuario,filial,setor,qtde,produto&order=data.desc&limit=1000";
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
    if ($err) {
        $error = "Erro ao conectar ao Supabase: " . $err;
    } elseif ($code < 200 || $code >= 300) {
        $error = "Supabase retornou HTTP $code";
    } else {
        $rows = json_decode($resp, true) ?: [];
        // group rows by numero_pedido
        $groups = [];
        foreach ($rows as $r) {
            $num = $r['numero_pedido'] ?? '';
            if ($num === '') continue;
            if (!isset($groups[$num])) {
                $groups[$num] = [
                    'numero_pedido' => $num,
                    'data' => $r['data'] ?? null,
                    'usuario' => $r['usuario'] ?? null,
                    'filial' => $r['filial'] ?? null,
                    'setor' => $r['setor'] ?? null,
                    'items' => 0,
                    'total_qtde' => 0.0
                ];
            }
            $groups[$num]['items'] += 1;
            $groups[$num]['total_qtde'] += isset($r['qtde']) ? (float)$r['qtde'] : 0;
            // keep the earliest data if null
            if (empty($groups[$num]['data']) && !empty($r['data'])) {
                $groups[$num]['data'] = $r['data'];
            }
        }
        // convert to list sorted by data desc (already ordered by rows but ensure ordering)
        $orders = array_values($groups);
        usort($orders, function($a, $b){
            $ta = strtotime($a['data'] ?? '1970-01-01');
            $tb = strtotime($b['data'] ?? '1970-01-01');
            return $tb <=> $ta;
        });
    }
} else {
    $error = 'Supabase não configurado. Verifique modules/bar_orders/config.php';
}

?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Lista de Pedidos</title>
  <link href="/assets/css/style.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 flex min-h-screen">
  <main class="flex-1 p-6">
    <div class="max-w-7xl mx-auto w-full">
      <header class="mb-6 flex items-center justify-between">
        <div>
          <a href="index.php" class="text-sm text-gray-400">&larr; Voltar</a>
          <h1 class="text-2xl font-bold">Pedidos</h1>
          <p class="text-gray-400 text-sm">Usuário: <?= htmlspecialchars($usuario) ?></p>
        </div>
        <div class="space-x-2">
          <a href="export_form.php" class="inline-block bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-500">Exportar</a>
          <a href="list.php" class="inline-block bg-gray-700 text-white px-3 py-2 rounded">Atualizar</a>
        </div>
      </header>

      <?php if ($error): ?>
        <div class="mb-4 px-4 py-3 bg-red-700 text-white rounded"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="mb-4 flex items-center gap-3">
        <input id="filter" type="text" placeholder="Filtrar por pedido, filial, usuário ou setor" class="w-full p-2 bg-gray-800 rounded text-sm" />
        <select id="filter-filial" class="p-2 bg-gray-800 rounded text-sm">
          <option value="">Todas as filiais</option>
        </select>
      </div>

      <div class="bg-gray-800 p-4 rounded">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm" id="orders-table">
            <thead class="text-left text-yellow-400"><tr>
              <th class="p-2">Pedido</th>
              <th class="p-2">Data</th>
              <th class="p-2">Usuário</th>
              <th class="p-2">Filial</th>
              <th class="p-2">Setor</th>
              <th class="p-2">Itens</th>
              <th class="p-2">Total Qtde</th>
              <th class="p-2">Ações</th>
            </tr></thead>
            <tbody>
              <?php if (empty($orders)): ?>
                <tr><td colspan="8" class="p-4 text-center text-gray-300">Nenhum pedido encontrado.</td></tr>
              <?php else: foreach ($orders as $o): ?>
                <tr class="order-row border-b border-gray-700" data-filial="<?= htmlspecialchars($o['filial'] ?? '') ?>">
                  <td class="p-2"><strong><?= htmlspecialchars($o['numero_pedido']) ?></strong></td>
                  <td class="p-2"><?= htmlspecialchars($o['data'] ?? '') ?></td>
                  <td class="p-2"><?= htmlspecialchars($o['usuario'] ?? '') ?></td>
                  <td class="p-2"><?= htmlspecialchars($o['filial'] ?? '') ?></td>
                  <td class="p-2"><?= htmlspecialchars($o['setor'] ?? '') ?></td>
                  <td class="p-2 text-right"><?= intval($o['items']) ?></td>
                  <td class="p-2 text-right"><?= htmlspecialchars(number_format($o['total_qtde'], 2, ',', '.')) ?></td>
                  <td class="p-2">
                    <a class="inline-block bg-indigo-600 px-3 py-1 rounded text-xs" href="receipt.php?pedido=<?= urlencode($o['numero_pedido']) ?>">Ver</a>
                    <a class="inline-block bg-teal-600 px-3 py-1 rounded text-xs ml-2" href="export.php?pedido=<?= urlencode($o['numero_pedido']) ?>&format=pdf" target="_blank">PDF</a>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>

  <script>
    // simple client-side filter
    const filterInput = document.getElementById('filter');
    const filterFilial = document.getElementById('filter-filial');
    const rows = Array.from(document.querySelectorAll('.order-row'));

    // populate filial select options from rows
    const filials = new Set();
    rows.forEach(r => { const f = r.dataset.filial || ''; if (f) filials.add(f); });
    const sorted = Array.from(filials).sort();
    sorted.forEach(f => {
      const opt = document.createElement('option'); opt.value = f; opt.textContent = f; filterFilial.appendChild(opt);
    });

    function applyFilter(){
      const q = (filterInput.value || '').toLowerCase();
      const filial = (filterFilial.value || '').toLowerCase();
      rows.forEach(r => {
        const txt = (r.innerText || '').toLowerCase();
        const matchesText = q === '' || txt.indexOf(q) !== -1;
        const matchesFilial = filial === '' || (r.dataset.filial || '').toLowerCase() === filial;
        r.style.display = (matchesText && matchesFilial) ? '' : 'none';
      });
    }

    filterInput.addEventListener('input', applyFilter);
    filterFilial.addEventListener('change', applyFilter);
  </script>
</body>
</html>
