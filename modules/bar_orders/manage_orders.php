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

// default filters from GET
$filial = $_GET['filial'] ?? '';
$start = $_GET['start'] ?? date('Y-m-d');
$end = $_GET['end'] ?? date('Y-m-d');

// fetch filial list from insumos so user can pick a filial
$filiais = [];
if (defined('SUPABASE_URL') && defined('SUPABASE_KEY')) {
  $base = rtrim(SUPABASE_URL, '/');
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
    // fallback non-distinct
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
      foreach ($rows as $r) { if (!empty($r['filial'])) $filiais[] = $r['filial']; }
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
  <title>Gerenciar Pedidos</title>
  <link href="/assets/css/style.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 flex min-h-screen">
  <main class="flex-1 p-6">
    <div class="max-w-7xl mx-auto w-full">
      <header class="mb-6 flex items-center justify-between">
        <div>
          <a href="index.php" class="text-sm text-gray-400">&larr; Voltar</a>
          <h1 class="text-2xl font-bold">Gerenciar Pedidos</h1>
          <p class="text-gray-400 text-sm">Usuário: <?= htmlspecialchars($usuario) ?></p>
        </div>
      </header>

      <form id="filter-form" class="mb-4 bg-gray-800 p-4 rounded grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div>
          <label class="block text-sm mb-1">Filial</label>
          <select name="filial" class="w-full p-2 bg-gray-900 rounded text-sm">
            <option value="">-- Todas as filiais --</option>
            <?php foreach ($filiais as $f): ?>
              <option value="<?= htmlspecialchars($f, ENT_QUOTES) ?>" <?= $filial === $f ? 'selected' : '' ?>><?= htmlspecialchars($f) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Data início</label>
          <input type="date" name="start" value="<?= htmlspecialchars($start) ?>" class="w-full p-2 bg-gray-900 rounded text-sm">
        </div>
        <div>
          <label class="block text-sm mb-1">Data fim</label>
          <input type="date" name="end" value="<?= htmlspecialchars($end) ?>" class="w-full p-2 bg-gray-900 rounded text-sm">
        </div>
        <div class="flex items-end">
          <button id="apply-filter" type="button" class="bg-blue-600 px-3 py-2 rounded">Aplicar filtro</button>
        </div>
      </form>

      <div id="items-container" class="bg-gray-800 p-4 rounded">
        <div class="mb-3 flex justify-between items-center">
          <div class="text-sm text-gray-300">Resultados para: <span id="filter-summary"></span></div>
          <div>
            <button id="save-all" class="bg-yellow-500 px-3 py-2 rounded">Salvar todas alterações</button>
            <button id="export-filtered" class="bg-indigo-600 ml-2 px-3 py-2 rounded">Exportar PDF (filtrados)</button>
          </div>
        </div>
        <div class="overflow-auto">
          <table id="items-table" class="min-w-full text-sm">
            <thead class="text-left text-yellow-400"><tr><th class="p-2">Pedido</th><th class="p-2">Produto</th><th class="p-2">Qtde</th><th class="p-2">Obs</th><th class="p-2">Fornec.</th><th class="p-2">Data Entrega</th><th class="p-2">Status</th><th class="p-2">Obs Comprador</th><th class="p-2">Ações</th></tr></thead>
            <tbody id="items-tbody"><tr><td colspan="9" class="p-4 text-center text-gray-300">Aplique um filtro para carregar itens.</td></tr></tbody>
          </table>
        </div>
      </div>

    </div>
  </main>

  <script>
    const applyBtn = document.getElementById('apply-filter');
    const tableBody = document.getElementById('items-tbody');
    const filterSummary = document.getElementById('filter-summary');
    const saveAll = document.getElementById('save-all');

    function renderRows(items){
      if (!items || !items.length) {
        tableBody.innerHTML = '<tr><td colspan="9" class="p-4 text-center text-gray-300">Nenhum item encontrado para o filtro.</td></tr>';
        return;
      }
      tableBody.innerHTML = '';
        items.forEach(it => {
        const tr = document.createElement('tr');
        tr.className = 'border-b border-gray-700';
        const pedido = it.numero_pedido || '';
        const produto = it.produto || '';
        const qt = it.qtde || '';
        const meta = it.meta || {}; // may contain fornecedor/status/obs_comprador
        tr.innerHTML = `
          <td class="p-2">${escapeHtml(pedido)}</td>
          <td class="p-2">${escapeHtml(produto)}</td>
          <td class="p-2">${escapeHtml(qt)}</td>
          <td class="p-2">${escapeHtml(it.observacao||'')}</td>
          <td class="p-2"><input class="w-full p-1 bg-gray-900 rounded text-sm meta-input" data-field="fornecedor" value="${escapeHtml(meta.fornecedor||'')}"></td>
          <td class="p-2"><input type="date" class="w-full p-1 bg-gray-900 rounded text-sm meta-input" data-field="data_entrega" value="${escapeHtml(meta.data_entrega||'') || ''}"></td>
          <td class="p-2"><select class="w-full p-1 bg-gray-900 rounded text-sm meta-input" data-field="status"><option value="">--</option><option value="PENDENTE">PENDENTE</option><option value="NEGOCIACAO">NEGOCIACAO</option><option value="FECHADO">FECHADO</option></select></td>
          <td class="p-2"><input class="w-full p-1 bg-gray-900 rounded text-sm meta-input" data-field="obs_comprador" value="${escapeHtml(meta.obs_comprador||'')}"></td>
          <td class="p-2">
            <button class="save-row bg-green-600 px-2 py-1 rounded text-xs">Salvar</button>
          </td>
        `;
        // set selected status
        const sel = tr.querySelector('select[data-field="status"]');
        if (sel && meta.status) sel.value = meta.status;

  // attach save handler
  tr.querySelector('.save-row').addEventListener('click', ()=> saveRow(pedido, produto, tr));

        tableBody.appendChild(tr);
      });
    }

    function escapeHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    async function loadItems(){
      const form = document.getElementById('filter-form');
      const params = new URLSearchParams(new FormData(form));
      // request JSON via export.php
      params.set('format','json');
  const url = 'export.php?' + params.toString();
  // show a human-friendly summary instead of raw query string
  const filialVal = (form.elements['filial'] && form.elements['filial'].value) || '';
  const startVal = (form.elements['start'] && form.elements['start'].value) || '';
  const endVal = (form.elements['end'] && form.elements['end'].value) || '';
  const filialLabel = filialVal ? filialVal.replace(/\+/g, ' ') : 'Todas as filiais';
  filterSummary.innerText = `Filial: ${filialLabel} · Período: ${startVal || '...'} — ${endVal || '...'}`;
      try {
          const res = await fetch(url, { credentials: 'same-origin' });
          const text = await res.text();
          if (!res.ok) {
            // show server message (JSON or text)
            let msg = text;
            try { const j = JSON.parse(text); if (j && j.error) msg = j.error; } catch(e){}
            throw new Error(msg || 'Falha ao buscar itens');
          }
          const data = text ? JSON.parse(text) : { items: [] };
        const items = data.items || [];
        // items are flat rows; we will attach meta if available via a separate call
        // transform to include possible meta from new endpoint
        // For now, call /rest/v1/order_item_meta?numero_pedido=in.(list) to fetch metas
        const pedidos = Array.from(new Set(items.map(i=>i.numero_pedido).filter(Boolean)));
        if (pedidos.length) {
          const metaUrl = 'get_order_meta.php?pedidos=' + encodeURIComponent(pedidos.join(','));
          const ch = await fetch(metaUrl, { credentials: 'same-origin' });
          let metas = [];
          if (ch.ok) {
            const j = await ch.json(); metas = j.items || [];
          }
          // build map: numero_pedido|produto -> meta
          const metaMap = {};
          metas.forEach(m => { metaMap[m.numero_pedido + '||' + m.produto] = m; });
          // attach meta to items
          items.forEach(it => {
            const key = (it.numero_pedido || '') + '||' + (it.produto || '');
            it.meta = metaMap[key] || {};
          });
        }
        renderRows(items);
      } catch (err) {
        tableBody.innerHTML = `<tr><td colspan="9" class="p-4 text-center text-red-500">Erro: ${escapeHtml(err.message||err)}</td></tr>`;
      }
    }

    async function saveRow(numero_pedido, produto, tr){
      // build payload from inputs in the tr
      const inputs = tr.querySelectorAll('.meta-input');
      const item = { produto };
      inputs.forEach(inp => { item[inp.dataset.field] = inp.value; });
      const payload = { numero_pedido, items: [item] };
      try {
        const res = await fetch('save_order_meta.php', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        let j = null;
        const text = await res.text();
        try { j = JSON.parse(text); } catch(e) { /* not JSON */ }
        if (res.ok) {
          tr.classList.add('bg-gray-800');
          alert('Salvo com sucesso');
        } else {
          const msg = j && j.error ? j.error : text;
          alert('Erro ao salvar: ' + msg);
        }
      } catch (e) {
        alert('Erro ao salvar: ' + e.message);
      }
    }

    async function saveAllRows(){
      const rows = Array.from(tableBody.querySelectorAll('tr'));
      const groups = {};
      for (const r of rows) {
        const pedido = r.children[0] && r.children[0].innerText.trim();
        const produto = r.children[1] && r.children[1].innerText.trim();
        if (!pedido || !produto) continue;
        const inputs = r.querySelectorAll('.meta-input');
        const item = { produto };
        inputs.forEach(inp => { item[inp.dataset.field] = inp.value; });
        if (!groups[pedido]) groups[pedido] = [];
        groups[pedido].push(item);
      }
      // send each pedido as a batch
      for (const pedido in groups) {
        try {
          const payload = { numero_pedido: pedido, items: groups[pedido] };
          const res = await fetch('save_order_meta.php', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
          const text = await res.text();
          let j = null; try { j = JSON.parse(text); } catch(e) {}
          if (!res.ok) { const msg = j && j.error ? j.error : text; alert('Erro ao salvar pedido ' + pedido + ': ' + msg); }
        } catch(e) { alert('Erro ao salvar: ' + e.message); }
      }
      alert('Operação concluída');
    }

    applyBtn.addEventListener('click', loadItems);
    saveAll.addEventListener('click', saveAllRows);
    document.getElementById('export-filtered').addEventListener('click', ()=>{
      const form = document.getElementById('filter-form');
      const params = new URLSearchParams(new FormData(form));
      const url = 'export_orders_pdf.php?' + params.toString();
      window.open(url, '_blank');
    });
  </script>
</body>
</html>