<?php
include __DIR__ . '/../../auth.php';
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../sidebar.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Análise de Vendas - Cozinha</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col sm:flex-row">
<main class="flex-1 p-4 sm:p-10 pt-20 sm:pt-10">
  <h1 class="text-2xl font-bold mb-4">Análise de Vendas - Cozinha</h1>

  <!-- Upload Excel -->
  <div class="card1 mb-6">
    <form id="form-upload" class="flex flex-col sm:flex-row gap-3 items-start sm:items-end" enctype="multipart/form-data">
      <div>
        <label class="block text-sm mb-1">Importar Excel de Vendas</label>
        <input type="file" name="arquivo" accept=".xlsx,.xls,.csv" class="bg-gray-800 border border-gray-700 rounded px-3 py-2" required>
      </div>
      <button type="submit" class="btn-acao-verde">Enviar e Atualizar</button>
      <span id="upload-status" class="text-sm opacity-80"></span>
    </form>
  </div>

  <!-- Filtros -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
    <div>
      <label class="block text-sm mb-1">De</label>
      <input type="date" id="f-de" class="bg-gray-800 border border-gray-700 rounded px-3 py-2 w-full">
    </div>
    <div>
      <label class="block text-sm mb-1">Até</label>
      <input type="date" id="f-ate" class="bg-gray-800 border border-gray-700 rounded px-3 py-2 w-full">
    </div>
    <div class="flex items-end">
      <button id="btn-aplicar" class="btn-acao-azul w-full">Aplicar Período</button>
    </div>
  </div>

  <!-- Gráfico Faturamento Diário -->
  <div class="mb-6">
    <h2 class="text-xl font-semibold mb-2">Faturamento Diário</h2>
    <div id="chart-faturamento" class="card1 no-hover"></div>
  </div>

  <!-- Quantidade por prato (multi-seleção) -->
  <div class="mb-6">
    <div class="flex flex-col sm:flex-row sm:items-end gap-3 mb-2">
      <div class="flex-1">
        <label class="block text-sm mb-1">Selecionar pratos</label>
        <select id="sel-pratos" multiple size="6" class="w-full bg-gray-800 border border-gray-700 rounded p-2"></select>
      </div>
      <div class="whitespace-nowrap">
        <button id="btn-plotar" class="btn-acao">Plotar Selecionados</button>
      </div>
    </div>
    <h2 class="text-xl font-semibold mb-2">Quantidade Vendida por Dia</h2>
    <div id="chart-quantidades" class="card1 no-hover"></div>
  </div>

  <!-- Matriz de Cardápio (dispersão/bubble) -->
  <div class="mb-10">
    <h2 class="text-xl font-semibold mb-2">Matriz de Cardápio (CMV x Quantidade)</h2>
    <p class="text-sm opacity-80 mb-2">Eixo X: CMV (%) | Eixo Y: Quantidade total no período | Tamanho: Preço médio</p>
    <div id="chart-matriz" class="card1 no-hover"></div>
  </div>
</main>

<script>
  const qs = (s)=>document.querySelector(s);
  const api = (params)=>fetch('vendas_data.php' + params, {cache:'no-cache'}).then(r=>r.json());

  function pad(v){return v.toString().padStart(2,'0')}
  function today(){const d=new Date();return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`}
  function daysAgo(n){const d=new Date(Date.now()-n*86400000);return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`}

  // Defaults
  qs('#f-de').value = daysAgo(30);
  qs('#f-ate').value = today();

  let chartFat, chartQtd, chartMatriz;

  async function carregarOverview(){
    const de = qs('#f-de').value, ate = qs('#f-ate').value;
    const data = await api(`?action=overview&de=${de}&ate=${ate}`);

    // Faturamento diário (linha)
    const fatOpts = {
      chart: { type:'line', height: 320, foreColor:'#f3f4f6' },
      series: data.faturamento.series,
      xaxis: { categories: data.faturamento.categories, type:'category', labels:{ rotate:-45 } },
      stroke: { width: 2 },
      colors: ['#34d399'],
      yaxis: { labels: { formatter:(v)=>`R$ ${v.toFixed(0)}` } },
      tooltip: { y: { formatter:(v)=>`R$ ${v.toFixed(2)}` } }
    };
    if (chartFat) chartFat.updateOptions(fatOpts); else { chartFat = new ApexCharts(qs('#chart-faturamento'), fatOpts); chartFat.render(); }

    // Popular pratos list
    const sel = qs('#sel-pratos');
    sel.innerHTML = data.pratos.map(p=>`<option value="${p.codigo}">${p.produto || p.codigo} — ${Number(p.qtd).toFixed(0)}</option>`).join('');
  }

  async function plotarSelecionados(){
    const de = qs('#f-de').value, ate = qs('#f-ate').value;
    const sel = Array.from(qs('#sel-pratos').selectedOptions).map(o=>o.value);
    if (!sel.length) return;
    const d = await api(`?action=series_pratos&de=${de}&ate=${ate}&codes=${encodeURIComponent(sel.join(','))}`);
    const opts = {
      chart: { type:'line', height: 360, foreColor:'#f3f4f6' },
      series: d.series,
      xaxis: { categories: d.categories, labels:{ rotate:-45 } },
      stroke: { width: 2 },
      legend: { position:'top' }
    };
    if (chartQtd) chartQtd.updateOptions(opts); else { chartQtd = new ApexCharts(qs('#chart-quantidades'), opts); chartQtd.render(); }
  }

  async function carregarMatriz(){
    const de = qs('#f-de').value, ate = qs('#f-ate').value;
    const d = await api(`?action=matriz&de=${de}&ate=${ate}`);
    const series = d.points.map(p=>({ name: p.name, data: [{ x: p.x, y: p.y, z: p.z }] }));
    const opts = {
      chart: { type:'bubble', height: 380, foreColor:'#f3f4f6' },
      series: series,
      dataLabels: { enabled:false },
      xaxis: { title:{ text:'CMV (%)' } },
      yaxis: { title:{ text:'Quantidade' } },
      legend: { show:false },
      colors: ['#ffbc3c','#ffe970','#ffd256','#ffff8a','#34d399','#60a5fa','#f472b6','#c084fc']
    };
    if (chartMatriz) chartMatriz.updateOptions(opts); else { chartMatriz = new ApexCharts(qs('#chart-matriz'), opts); chartMatriz.render(); }
  }

  // Eventos
  qs('#btn-aplicar').addEventListener('click', async (e)=>{ e.preventDefault(); await carregarOverview(); await carregarMatriz(); });
  qs('#btn-plotar').addEventListener('click', async (e)=>{ e.preventDefault(); await plotarSelecionados(); });

  // Upload Excel
  qs('#form-upload').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const el = qs('#upload-status'); el.textContent = 'Enviando...';
    const fd = new FormData(e.target);
    try{
      const resp = await fetch('vendas_upload.php', { method:'POST', body: fd });
      const j = await resp.json();
      if (j.ok) {
        el.textContent = `Importado: ${j.linhas_processadas} linhas.`;
        await carregarOverview();
        await carregarMatriz();
      } else { el.textContent = 'Falha: ' + (j.erro||''); }
    }catch(err){ el.textContent = 'Erro ao enviar arquivo.'; }
  });

  // Inicial
  (async ()=>{ await carregarOverview(); await carregarMatriz(); })();
</script>
</body>
</html>

