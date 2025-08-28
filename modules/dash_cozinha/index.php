<?php
include __DIR__ . '/../../auth.php';
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/db_dw.php';
include __DIR__ . '/../../sidebar.php';


?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard da Cozinha</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col sm:flex-row">
<main class="flex-1 p-4 sm:p-10 pt-20 sm:pt-10">
  <h1 class="text-2xl font-bold mb-4">Dashboard da Cozinha</h1>

  <!-- Disp Cozinhas -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 mb-4">
    <a href="disp_bdf_almoco.php"       class="btn-acao">Disp BDF Almoço</a>
    <a href="disp_bdf_almoco_fds.php"   class="btn-acao">Disp BDF Almoço FDS</a>
    <a href="disp_bdf_noite.php"        class="btn-acao">Disp BDF Noite</a>
    <a href="disp_wab.php"              class="btn-acao">Disp WAB</a>
    <a href="inventario_cozinha.php"   class="btn-acao-verde">Inventário Cozinha</a>
    <a href="telegram_disp_config.php"  class="btn-acao-azul">Telegram</a>
  </div>

  <!-- KPIs -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">
    <div class="card1 text-center"><p>Total de Pratos</p><p id="kpi-total">--</p></div>
    <div class="card1 text-center"><p>Custo Médio</p><p id="kpi-custo">--</p></div>
    <div class="card1 text-center"><p>Preço Médio</p><p id="kpi-preco">--</p></div>
    <div class="card1 text-center"><p>CMV Médio</p><p id="kpi-cmv">--</p></div>
    <div class="card1 text-center"><p>Margem Média (%)</p><p id="kpi-margem">--</p></div>
  </div>
  
  <!-- Gráficos CMV e Grupo -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
    <div>
      <h2 class="text-xl font-semibold mb-2">CMV por Prato</h2>
      <div id="chart-cmv" class="card1 no-hover"></div>
    </div>
    <div>
      <h2 class="text-xl font-semibold mb-2">Distribuição por Grupo</h2>
      <div id="chart-grupo" class="card1 no-hover"></div>
    </div>
  </div>

  <!-- Gráficos Disponibilidade -->
  <div class="grid grid-cols-1 sm:grid-cols-3 mt-6 gap-4">
    <div>
      <h2 class="text-xl font-semibold mb-2">Disp. Tempo Real</h2>
      <div id="chart-availability" class="card1 no-hover p-4"></div>
    </div>
    <div>
      <h2 class="text-xl font-semibold mb-2">Disp. Últimos 7 Dias</h2>
      <div id="chart-availability-7d" class="card1 no-hover p-4"></div>
    </div>
    <div>
      <h2 class="text-xl font-semibold mb-2">Disp. Últimos 30 Dias</h2>
      <div id="chart-availability-30d" class="card1 no-hover p-4"></div>
    </div>
  </div>

  <!-- Tabela Detalhamento -->
  <div class="mt-6">
    <div class="flex items-center justify-between mb-2">
      <h2 class="text-xl font-semibold">Detalhamento</h2>
      <input
        type="text"
        id="filtro-tabela"
        placeholder="🔍 Filtrar pratos, grupos, custos..."
        class="w-full sm:w-1/2 px-3 py-2 rounded bg-gray-800 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-yellow-400"
      >
      <button
        id="toggle-detalhamento"
        class="btn-acao px-2 py-1 text-sm"
        type="button"
      >Ocultar</button>
    </div>
    <div
      id="detalhamento-container"
      class="overflow-x-auto">      
      <table id="tabela-sortable" class="min-w-full text-xs text-left">
        <thead>
          <tr class="bg-yellow-600 text-white text-sm">
            <th class="p-2 cursor-pointer" onclick="sortTable(0)">Prato</th>
            <th class="p-2 cursor-pointer" onclick="sortTable(1)">Grupo</th>
            <th class="p-2 cursor-pointer" onclick="sortTable(2)">Custo</th>
            <th class="p-2 cursor-pointer" onclick="sortTable(3)">Preço</th>
            <th class="p-2 cursor-pointer" onclick="sortTable(4)">CMV&nbsp;(%)</th>
            <th class="p-2 cursor-pointer" onclick="sortTable(5)">Margem&nbsp;(R$)</th>
            <th class="p-2 cursor-pointer" onclick="sortTable(6)">Margem&nbsp;(%)</th>
          </tr>
        </thead>
        <tbody id="tabela-pratos" data-sort-dir="asc"></tbody>
      </table>
    </div>
  </div>
  
  <!-- Matriz Engenharia de Cardápio -->
  <div class="mt-8">
    <div class="flex items-center justify-between mb-2">
      <h2 class="text-xl font-semibold">Engenharia de Cardápio</h2>
      <div class="flex flex-wrap items-center gap-2">
        <label class="text-sm text-gray-300">Filial
          <select id="mat-filial" class="bg-gray-800 border border-gray-700 rounded px-2 py-1">
            <option value="1">1 - BDF</option>
            <option value="2">2 - WAB</option>
          </select>
        </label>
        <label class="text-sm text-gray-300">Início
          <input type="date" id="mat-inicio" class="bg-gray-800 border border-gray-700 rounded px-2 py-1" />
        </label>
        <label class="text-sm text-gray-300">Fim
          <input type="date" id="mat-fim" class="bg-gray-800 border border-gray-700 rounded px-2 py-1" />
        </label>
        <button id="btn-gerar-matriz" class="btn-acao">Gerar matriz</button>
      </div>
    </div>

    <div id="matriz-container" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div class="card1 no-hover">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <h3 class="font-semibold text-green-400 mb-2">Estrelas</h3>
            <ul id="q-estrelas" class="text-sm space-y-1"></ul>
          </div>
          <div>
            <h3 class="font-semibold text-amber-400 mb-2">Burros de Carga</h3>
            <ul id="q-burros" class="text-sm space-y-1"></ul>
          </div>
          <div>
            <h3 class="font-semibold text-teal-400 mb-2">Quebra-Cabeça</h3>
            <ul id="q-puzzles" class="text-sm space-y-1"></ul>
          </div>
          <div>
            <h3 class="font-semibold text-red-400 mb-2">Cachorros</h3>
            <ul id="q-cachorros" class="text-sm space-y-1"></ul>
          </div>
        </div>
      </div>

      <div class="card1 no-hover overflow-x-auto">
        <table id="table-matriz" class="min-w-full text-xs text-left" data-sort-dir-m="asc">
          <thead>
            <tr class="bg-yellow-600 text-white text-sm">
              <th class="p-2 cursor-pointer" onclick="sortMatriz(0)">Prato</th>
              <th class="p-2 cursor-pointer" onclick="sortMatriz(1)">Vendas</th>
              <th class="p-2 cursor-pointer" onclick="sortMatriz(2)">Margem (R$)</th>
              <th class="p-2 cursor-pointer" onclick="sortMatriz(3)">Margem (%)</th>
              <th class="p-2 cursor-pointer" onclick="sortMatriz(4)">Categoria</th>
            </tr>
          </thead>
          <tbody id="tbody-matriz"></tbody>
        </table>
      </div>
    </div>
  </div>

</main>

<script>
  async function fetchDashData() {
    const resp = await fetch('dash_data.php', { cache: 'no-cache' });
    if (!resp.ok) throw new Error('Erro ao buscar dados');
    return resp.json();
  }

  document.addEventListener('DOMContentLoaded', async () => {
    const data = await fetchDashData();

    // KPIs
    document.getElementById('kpi-total').textContent  = data.kpis.total ?? '--';
    document.getElementById('kpi-custo').textContent  = `R$ ${(data.kpis.custo ?? 0).toFixed(2)}`;
    document.getElementById('kpi-preco').textContent  = `R$ ${(data.kpis.preco ?? 0).toFixed(2)}`;
    document.getElementById('kpi-cmv').textContent    = `${(data.kpis.cmv ?? 0).toFixed(1)}%`;
    document.getElementById('kpi-margem').textContent = `${(100 - (data.kpis.cmv ?? 0)).toFixed(1)}%`;

    // Gráficos CMV e Grupo
    new ApexCharts(document.querySelector('#chart-cmv'),   data.chartCmv).render();
    new ApexCharts(document.querySelector('#chart-grupo'), data.chartGrupo).render();

    // Tabela
    const tbody = document.getElementById('tabela-pratos');
    tbody.innerHTML = data.tabela.map(p => {
      const margemR = p.preco - p.custo;
      const margemP = margemR / p.preco * 100;
      return `
        <tr class="border-b border-gray-700 hover:bg-gray-800">
          <td class="p-2">${p.nome}</td>
          <td class="p-2">${p.grupo}</td>
          <td class="p-2">R$ ${p.custo.toFixed(2)}</td>
          <td class="p-2">R$ ${p.preco.toFixed(2)}</td>
          <td class="p-2">${p.cmv.toFixed(1)}%</td>
          <td class="p-2">R$ ${margemR.toFixed(2)}</td>
          <td class="p-2">${margemP.toFixed(1)}%</td>
        </tr>`;
    }).join('');

    // Função genérica para chart de disponibilidade
    function renderDispChart(selector, obj) {
      const labels = Object.keys(obj);
      const series = Object.values(obj);
      if (!labels.length) return;
      const opts = {
        chart:    { type: 'radialBar', height: 350 },
        plotOptions: {
          radialBar: {
            startAngle: 0, endAngle: 270,
            track: { background: '#333', strokeWidth:'100%', margin:5,
              dropShadow:{ enabled:true, top:2, left:0, blur:4, opacity:0.15 } },
            dataLabels:{ name:{show:true}, value:{show:true},
            },
            barLabels: {
              enabled:         true,
              useSeriesColors: false, 
              offsetX:         -8,
              formatter:       (name, opts) =>
                `${name}: ${opts.w.globals.series[opts.seriesIndex].toFixed(1)}%`,
            }
          }
        },
        stroke:{ lineCap:'round' },
        labels: labels,
        series: series,
        colors: ['#ffbc3c','#ffd256','#ffe970','#ffff8a']
      };
      new ApexCharts(document.querySelector(selector), opts).render();
    }

    // Disp tempo real, 7d e 30d
    renderDispChart('#chart-availability',    data.availability);
    renderDispChart('#chart-availability-7d', data.availability7d);
    renderDispChart('#chart-availability-30d',data.availability30d);
  });

  // --- Matriz Engenharia de Cardápio ---
  function norm(s){
    return (s||'').toLowerCase()
      .normalize('NFD').replace(/\p{Diacritic}+/gu,'')
      .replace(/\s+/g,' ').trim();
  }

  async function gerarMatriz(){
    // Reaproveita a tabela carregada pelo dashboard (custos/preços)
    const respDash = await fetch('dash_data.php', { cache:'no-cache' });
    const dash = await respDash.json();
    const pratos = dash.tabela || [];

    // Datas padrão: mês atual
    const hoje = new Date();
    const y = hoje.getFullYear();
    const m = String(hoje.getMonth()+1).padStart(2,'0');
    const d1 = `${y}-${m}-01`;
    const d2 = `${y}-${m}-${String(new Date(y, hoje.getMonth()+1, 0).getDate()).padStart(2,'0')}`;

    const filial = document.getElementById('mat-filial').value || '1';
    const inicio = document.getElementById('mat-inicio').value || d1;
    const fim    = document.getElementById('mat-fim').value    || d2;

    const resV = await fetch(`sales_cc870.php?inicio=${inicio}&fim=${fim}&filial=${filial}`);
    const sv = await resV.json();
    const vendas = (sv && sv.vendas) ? sv.vendas : [];

    // Mapa de vendas por nome (normalizado)
    const mapVend = new Map();
    vendas.forEach(v => {
      const key = v.codigo ? `c:${v.codigo}` : `n:${norm(v.nome)}`;
      mapVend.set(key, (mapVend.get(key)||0) + Number(v.quantidade||0));
    });

    // Normaliza pratos e busca suas vendas (por nome)
    const itens = pratos.map(p => {
      const margemR = (p.preco||0) - (p.custo||0);
      const margemP = (p.preco||0) > 0 ? (margemR/(p.preco||1))*100 : 0;
      const keyByName = `n:${norm(p.nome)}`;
      const qtde = Number(mapVend.get(keyByName) || 0);
      return {
        nome: p.nome, grupo: p.grupo,
        custo: Number(p.custo||0), preco: Number(p.preco||0),
        margemR, margemP, qtde
      };
    });

    const totalQtde = itens.reduce((s,i)=>s+i.qtde,0);
    const avgQtde   = itens.length ? totalQtde / itens.length : 0;
    const avgMargem = itens.length ? itens.reduce((s,i)=>s+i.margemR,0) / itens.length : 0;

    const cats = { estrelas:[], burros:[], puzzles:[], cachorros:[] };
    const tbody = document.getElementById('tbody-matriz');
    tbody.innerHTML = '';

    itens.forEach(i => {
      const pop  = i.qtde >= avgQtde;
      const mar  = i.margemR >= avgMargem;
      let cat = 'Cachorros';
      if (pop && mar) cat = 'Estrelas';
      else if (pop && !mar) cat = 'Burros de Carga';
      else if (!pop && mar) cat = 'Quebra-Cabeça';

      const li = `${i.nome} · R$ ${i.margemR.toFixed(2)} · ${i.qtde.toFixed(0)} vendas`;
      if (cat==='Estrelas') cats.estrelas.push(li);
      else if (cat==='Burros de Carga') cats.burros.push(li);
      else if (cat==='Quebra-Cabeça') cats.puzzles.push(li);
      else cats.cachorros.push(li);

      const tr = document.createElement('tr');
      tr.className = 'border-b border-gray-700 hover:bg-gray-800';
      tr.innerHTML = `
        <td class="p-2">${i.nome}</td>
        <td class="p-2">${i.qtde.toFixed(0)}</td>
        <td class="p-2">R$ ${i.margemR.toFixed(2)}</td>
        <td class="p-2">${i.margemP.toFixed(1)}%</td>
        <td class="p-2">${cat}</td>
      `;
      tbody.appendChild(tr);
    });

    function renderList(id, arr){
      const el = document.getElementById(id);
      el.innerHTML = arr.length ? arr.slice(0,20).map(x=>`<li class=\"list-disc ml-5\">${x}</li>`).join('') : '<li class="ml-1 text-gray-400">Sem dados</li>';
    }
    renderList('q-estrelas', cats.estrelas);
    renderList('q-burros',   cats.burros);
    renderList('q-puzzles',  cats.puzzles);
    renderList('q-cachorros',cats.cachorros);
  }

  document.getElementById('btn-gerar-matriz').addEventListener('click', gerarMatriz);

  // Sort table
  function sortTable(col) {
    const table = document.getElementById('tabela-sortable');
    let dir = table.tBodies[0].getAttribute('data-sort-dir') === 'asc' ? 'asc' : 'desc';
    let switching = true;
    while (switching) {
      switching = false;
      const rows = table.rows;
      for (let i = 1; i < rows.length - 1; i++) {
        const x = rows[i].cells[col].textContent.replace(/[R$%,]/g,'').trim();
        const y = rows[i+1].cells[col].textContent.replace(/[R$%,]/g,'').trim();
        const a = isNaN(x) ? x.toLowerCase() : parseFloat(x);
        const b = isNaN(y) ? y.toLowerCase() : parseFloat(y);
        if ((dir==='asc' && a>b) || (dir==='desc' && a<b)) {
          rows[i].parentNode.insertBefore(rows[i+1], rows[i]);
          switching = true;
          break;
        }
      }
      if (!switching) dir = dir==='asc' ? 'desc' : 'asc';
    }
    table.tBodies[0].setAttribute('data-sort-dir', dir);
  }
  
  // Filtro em tempo real da tabela
document.getElementById('filtro-tabela').addEventListener('input', function () {
  const termo = this.value.toLowerCase();
  const linhas = document.querySelectorAll('#tabela-pratos tr');

  linhas.forEach(linha => {
    const textoLinha = linha.textContent.toLowerCase();
    linha.style.display = textoLinha.includes(termo) ? '' : 'none';
  });
});
  
  // Oculta a tabela
  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('toggle-detalhamento');
    const container = document.getElementById('detalhamento-container');

    btn.addEventListener('click', () => {
      const hidden = container.classList.toggle('hidden');
      btn.textContent = hidden ? 'Mostrar' : 'Ocultar';
    });
  });
  
    // Função de ordenação para a matriz
  function sortMatriz(col) {
    const table = document.getElementById('table-matriz');
    let dir = table.getAttribute('data-sort-dir-m') === 'asc' ? 'desc' : 'asc';
    const tbody = table.tBodies[0];
    const rows = Array.from(tbody.rows);
    rows.sort((a, b) => {
      let x = a.cells[col].textContent.replace(/[R$%,]/g,'').trim();
      let y = b.cells[col].textContent.replace(/[R$%,]/g,'').trim();
      const nx = isNaN(x) ? x.toLowerCase() : parseFloat(x);
      const ny = isNaN(y) ? y.toLowerCase() : parseFloat(y);
      if (nx < ny) return dir === 'asc' ? -1 : 1;
      if (nx > ny) return dir === 'asc' ? 1 : -1;
      return 0;
    });
    // Reanexar as linhas
    rows.forEach(row => tbody.appendChild(row));
    table.setAttribute('data-sort-dir-m', dir);
  }
</script>
</body>
</html>
