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
              <option value="pdf">PDF</option>
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
          <button id="export-btn" type="submit" class="bg-green-600 px-4 py-2 rounded">Exportar</button>
          <button id="preview-btn" type="button" class="bg-yellow-500 px-4 py-2 rounded text-sm">Pré-visualizar</button>
          <button id="pdf-only-btn" type="button" class="bg-indigo-600 px-4 py-2 rounded text-sm">Baixar PDF</button>
          <a href="receipt.php" class="bg-gray-700 px-4 py-2 rounded text-sm">Ver recibos</a>
        </div>
      </form>

      <script>
        // open server-side PDF export in new tab to trigger download
        (function(){
          const form = document.querySelector('form[action="export.php"]');
          const formatEl = form.querySelector('[name="format"]');
          form.addEventListener('submit', function(e){
            if (formatEl && formatEl.value === 'pdf') {
              e.preventDefault();
              // build query string from form inputs
              const params = new URLSearchParams(new FormData(form));
              const url = form.action + '?' + params.toString();
              window.open(url, '_blank');
            }
          });

          // direct PDF button (exports using current form values as PDF)
          const pdfBtn = document.getElementById('pdf-only-btn');
          if (pdfBtn) {
            pdfBtn.addEventListener('click', function(){
              const params = new URLSearchParams(new FormData(form));
              // force format=pdf
              params.set('format', 'pdf');
              const url = form.action + '?' + params.toString();
              window.open(url, '_blank');
            });
          }

          // preview inline: fetch printable HTML and inject into the page
          // helper: escape HTML to avoid XSS and ensure strings render safely
          function escapeHtml(input) {
            if (input === null || input === undefined) return '';
            return String(input)
              .replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;')
              .replace(/'/g, '&#039;');
          }
          // helper: format date-only (DD/MM/YYYY) from ISO or Date string
          function formatDate(input) {
            if (!input) return '';
            try {
              // if contains T -> ISO timestamp
              if (typeof input === 'string' && input.indexOf('T') !== -1) {
                const d = input.split('T')[0]; // YYYY-MM-DD
                const parts = d.split('-');
                if (parts.length === 3) return parts[2] + '/' + parts[1] + '/' + parts[0];
              }
              const dt = new Date(input);
              if (!isNaN(dt.getTime())) {
                return dt.toLocaleDateString();
              }
            } catch (e) {}
            // fallback: strip time if present
            return String(input).replace(/T.*$/, '').split(' ')[0];
          }
          const previewBtn = document.getElementById('preview-btn');
          // create inline preview container
          const previewContainer = document.createElement('div');
          previewContainer.id = 'inline-preview';
          previewContainer.className = 'mt-6 bg-gray-800 p-4 rounded hidden';
          previewContainer.innerHTML = `
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
              <div style="color:#f6c90e;font-weight:600;font-size:13px">Pré-visualização do Pedido</div>
            </div>
            <div id="inline-preview-content" class="bg-white text-black p-3 overflow-auto" style="max-height:60vh;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.15;border-radius:4px"></div>
          `;
          // insert after the form
          const formEl = document.querySelector('form[action="export.php"]');
          formEl.parentNode.insertBefore(previewContainer, formEl.nextSibling);

          if (previewBtn) {
            previewBtn.addEventListener('click', async function(){
              const params = new URLSearchParams(new FormData(form));
              // request JSON which is easier to render reliably
              params.set('format', 'json');
              const url = form.action + '?' + params.toString();
              const contentEl = document.getElementById('inline-preview-content');
              const container = document.getElementById('inline-preview');
              try {
                const res = await fetch(url, { credentials: 'same-origin' });
                if (!res.ok) throw new Error('Falha ao buscar preview');
                const data = await res.json();
                const items = data.items || [];
                if (!items.length) {
                  contentEl.innerHTML = '<div class="p-3 bg-yellow-300 text-black rounded">Nenhum pedido encontrado para o período informado.</div>';
                  container.classList.remove('hidden');
                  return;
                }
                // if multiple pedidos, render a full table with columns; otherwise use receipt-style
                const meta = data.meta || {};
                // determine unique pedidos
                const pedidosSet = new Set(items.map(i=>i.numero_pedido || ''));
                let html = '';
                // get filial from meta or from form values (fallback)
                const formValues = Object.fromEntries(new FormData(form).entries());
                const filialTop = meta.filial || formValues.filial || '';
                if (pedidosSet.size > 1) {
                  // render full table with Pedido, Data, Produto, Categoria, Und, Qtde, Obs, Usuário, Filial, Setor
                  html += '<div style="max-width:1100px;margin:0 auto;color:#000;font-family:Arial,Helvetica,sans-serif;font-size:10px">';
                  html += '<h2 style="text-align:center;font-size:13px;margin:4px 0">LISTA DE PEDIDOS</h2>';
                  if (filialTop) html += '<div style="text-align:center;font-size:11px;color:#333;margin-bottom:6px"><strong>Filial:</strong> ' + escapeHtml(filialTop) + '</div>';
                  // compact table with fixed layout so long texts wrap nicely
                  html += '<table class="pdf-table" style="width:100%;border-collapse:collapse;font-size:10px;table-layout:fixed">';
                  html += '<thead><tr>';
                  html += '<th style="text-align:left;border-bottom:1px solid #e6e6e6;padding:4px;width:10%">Data</th>';
                  html += '<th style="text-align:left;border-bottom:1px solid #e6e6e6;padding:4px;width:40%">Produto</th>';
                  html += '<th style="text-align:left;border-bottom:1px solid #e6e6e6;padding:4px;width:12%">Categoria</th>';
                  html += '<th style="text-align:right;border-bottom:1px solid #e6e6e6;padding:4px;width:8%">Qtde</th>';
                  html += '<th style="text-align:left;border-bottom:1px solid #e6e6e6;padding:4px;width:6%">Und</th>';
                  html += '<th style="text-align:left;border-bottom:1px solid #e6e6e6;padding:4px;width:16%">Obs</th>';
                  html += '<th style="text-align:left;border-bottom:1px solid #e6e6e6;padding:4px;width:4%">Usuário</th>';
                  html += '<th style="text-align:left;border-bottom:1px solid #e6e6e6;padding:4px;width:4%">Setor</th>';
                  html += '</tr></thead><tbody>';
                  for (const it of items) {
                    html += '<tr>';
                    html += '<td style="padding:4px;border-bottom:1px dotted #e9e9e9">' + escapeHtml(formatDate(it.data || '')) + '</td>';
                    html += '<td class="produto" style="padding:4px;border-bottom:1px dotted #e9e9e9;word-break:break-word;overflow-wrap:anywhere;white-space:normal">' + escapeHtml(it.produto || '') + '</td>';
                    html += '<td style="padding:4px;border-bottom:1px dotted #e9e9e9">' + escapeHtml(it.categoria || '') + '</td>';
                    html += '<td style="padding:4px;border-bottom:1px dotted #e9e9e9;text-align:right">' + escapeHtml(String(it.qtde ?? '')) + '</td>';
                    html += '<td style="padding:4px;border-bottom:1px dotted #e9e9e9">' + escapeHtml(it.und || '') + '</td>';
                    html += '<td style="padding:4px;border-bottom:1px dotted #e9e9e9;word-break:break-word;overflow-wrap:anywhere;white-space:normal">' + escapeHtml(it.observacao || '') + '</td>';
                    html += '<td style="padding:4px;border-bottom:1px dotted #e9e9e9">' + escapeHtml(it.usuario || '') + '</td>';
                    html += '<td style="padding:4px;border-bottom:1px dotted #e9e9e9">' + escapeHtml(it.setor || '') + '</td>';
                    html += '</tr>';
                  }
                  html += '</tbody></table>';
                  html += '<div style="margin-top:8px;font-size:10px;color:#333">Total linhas: ' + escapeHtml(String(items.length)) + '</div>';
                  html += '</div>';
                } else {
                  // nice receipt-style layout for single pedido
                  html += '<div style="max-width:900px;margin:0 auto;color:#000;font-family:Arial,Helvetica,sans-serif;font-size:11px">';
                  html += '<div style="text-align:center;padding:6px 0;border-bottom:1px solid #e6e6e6;margin-bottom:8px">';
                  html += '<h2 style="margin:0;font-size:14px">RECIBO / ORDEM</h2>';
                  html += '<div style="font-size:11px;color:#444">' + escapeHtml(window.location.hostname || '') + '</div>';
                  html += '</div>';
                  // show filial at top (left) and other meta on right
                  html += '<div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:8px">';
                  html += '<div>' + (filialTop ? ('<strong>Filial:</strong> ' + escapeHtml(filialTop) + '<br>') : '') + '<strong>Data:</strong> ' + escapeHtml(formatDate(meta.data || (items[0] && items[0].data) || '')) + '</div>';
                  html += '<div style="text-align:right"><strong>Usuário:</strong> ' + escapeHtml(meta.usuario || '') + '<br>';
                  html += '<strong>Setor:</strong> ' + escapeHtml(meta.setor || '') + '</div>';
                  html += '</div>';
                  html += '<table style="width:100%;border-collapse:collapse;font-size:11px">';
                  html += '<thead><tr><th style="text-align:left;border-bottom:1px solid #e6e6e6;padding:6px">Produto</th><th style="text-align:right;border-bottom:1px solid #e6e6e6;padding:6px;width:60px">Qtd</th><th style="text-align:left;border-bottom:1px solid #e6e6e6;padding:6px;width:60px">Und</th></tr></thead><tbody>';
                  for (const it of items) {
                    html += '<tr>';
                    html += '<td style="padding:6px;border-bottom:1px dotted #e9e9e9">' + escapeHtml(it.produto || '') + (it.categoria ? (' <small style="color:#666">(' + escapeHtml(it.categoria) + ')</small>') : '') + (it.observacao ? ('<div style="color:#444;font-size:11px;margin-top:4px">' + escapeHtml(it.observacao) + '</div>') : '') + '</td>';
                    html += '<td style="padding:6px;border-bottom:1px dotted #e9e9e9;text-align:right">' + escapeHtml(String(it.qtde ?? '')) + '</td>';
                    html += '<td style="padding:6px;border-bottom:1px dotted #e9e9e9">' + escapeHtml(it.und || '') + '</td>';
                    html += '</tr>';
                  }
                  html += '</tbody></table>';
                  html += '<div style="margin-top:8px;font-size:11px;color:#333">Total itens: ' + escapeHtml(String(items.length)) + '</div>';
                  html += '<div style="margin-top:12px;font-size:10px;color:#666;border-top:1px solid #f1f1f1;padding-top:8px">Gerado por sistema — ' + escapeHtml((new Date()).toLocaleDateString()) + '</div>';
                  html += '</div>';
                }
                contentEl.innerHTML = html;
                container.classList.remove('hidden');
              } catch (err) {
                alert('Erro ao carregar preview: ' + (err.message || err));
              }
            });
          }
        })();
      </script>

      <div class="mt-6 text-sm text-gray-400">
        <p>Dica: o número do pedido aparece na tela de recibo após enviar um pedido. Use o formato mostrado no recibo para localizar corretamente.</p>
      </div>
    </div>
  </main>
</body>
</html>