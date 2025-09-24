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
  <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col sm:flex-row">
<main class="flex-1 p-4 sm:p-10 pt-20 sm:pt-10">
  <h1 class="text-2xl font-bold mb-4">Dashboard da Cozinha</h1>

  <div
    id="base-status"
    class="card1 no-hover text-sm mb-4 hidden space-y-2"
    role="status"
  ></div>

  <!-- Disp Cozinhas -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 mb-4">
    <a href="disp_bdf_almoco.php"       class="btn-acao">Disp BDF Almoço</a>
    <a href="disp_bdf_almoco_fds.php"   class="btn-acao">Disp BDF Almoço FDS</a>
    <a href="disp_bdf_noite.php"        class="btn-acao">Disp BDF Noite</a>
    <a href="disp_wab.php"              class="btn-acao">Disp WAB</a>
    <a href="analise_vendas.php"        class="btn-acao-azul">Análise de Vendas</a>
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
        placeholder="Filtrar por multiplos termos; separe com ;"
        class="w-full sm:w-1/2 px-3 py-2 rounded bg-gray-800 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-yellow-400"
      >
      <div class="flex gap-2">
        <button
          id="toggle-detalhamento"
          class="btn-acao px-2 py-1 text-sm"
          type="button"
        >Ocultar</button>
        <button
          id="export-xlsx"
          class="btn-acao-verde px-2 py-1 text-sm"
          type="button"
          title="Exporta apenas as linhas visíveis"
        >Exportar XLSX</button>
      </div>
    </div>
    <div id="detalhamento-container" class="overflow-x-auto">
      <table id="tabela-sortable" class="min-w-full text-xs text-left">
        <thead>
          <tr class="bg-yellow-600 text-white text-sm">
            <th class="p-2 cursor-pointer" onclick="sortTable(0)">Codigo</th>
            <th class="p-2 cursor-pointer" onclick="sortTable(1)">Prato</th>
            <th class="p-2 cursor-pointer" onclick="sortTable(2)">Grupo</th>
            <th class="p-2 cursor-pointer" onclick="sortTable(3)">Custo</th>
            <th class="p-2 cursor-pointer" onclick="sortTable(3)">Preço</th>
            <th class="p-2 cursor-pointer" onclick="sortTable(5)">CMV&nbsp;(%)</th>
            <th class="p-2 cursor-pointer" onclick="sortTable(5)">Margem&nbsp;(R$)</th>
            <th class="p-2 cursor-pointer" onclick="sortTable(6)">Margem&nbsp;(%)</th>
          </tr>
        </thead>
        <tbody id="tabela-pratos" data-sort-dir="asc"></tbody>
      </table>
    </div>
  </div>
</main>

<script>
  const escapeHtml = (value) => {
    if (value === null || value === undefined) {
      return '';
    }
    return String(value).replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    })[char]);
  };

  const toNumber = (value) => {
    const num = Number(value);
    return Number.isFinite(num) ? num : 0;
  };

  async function fetchDashData() {
    const resp = await fetch('dash_data.php', { cache: 'no-cache' });
    if (!resp.ok) throw new Error('Erro ao buscar dados');
    return resp.json();
  }

  document.addEventListener('DOMContentLoaded', async () => {
    const data = await fetchDashData();
    const pratos = Array.isArray(data.tabela) ? data.tabela : [];

    const baseStatusBox = document.getElementById('base-status');
    if (baseStatusBox) {
      const divergencias = pratos.filter(function (prato) {
        const baseOrigem = prato && prato.base_origem;
        const baseDados = prato && prato.base_dados;
        return baseOrigem && baseDados && baseOrigem !== baseDados;
      });
      const semDados = pratos.filter(function (prato) {
        if (!prato || !prato.codigo) {
          return false;
        }
        if (typeof prato.possui_dados === 'boolean') {
          return !prato.possui_dados;
        }
        return !prato.base_dados;
      });
      const semCodigo = pratos.filter(function (prato) {
        return !prato || !prato.codigo;
      });

      const mensagens = [];
      if (!pratos.length) {
        mensagens.push('<p class="text-gray-300 text-sm">Nenhuma ficha com farol verde encontrada.</p>');
      } else if (divergencias.length === 0 && semDados.length === 0) {
        mensagens.push('<p class="text-green-400 font-semibold">Bases validadas</p>');
        mensagens.push(`<p class="text-gray-300 text-sm">Todos os ${pratos.length} pratos utilizam dados da base configurada.</p>`);
      } else {
        mensagens.push('<p class="text-yellow-300 font-semibold">Atenção nas bases</p>');

        if (divergencias.length) {
          mensagens.push(`<p class="text-gray-300 text-sm mt-2">Dados provenientes de outra base (${divergencias.length}):</p>`);
          const itens = divergencias.slice(0, 6).map(function (prato) {
            const nome = escapeHtml(prato && prato.nome ? prato.nome : 'Sem nome');
            const origem = escapeHtml(prato && prato.base_origem ? prato.base_origem : 'N/D');
            const dados = escapeHtml(prato && prato.base_dados ? prato.base_dados : 'Desconhecida');
            return `<li>${nome} <span class="text-gray-400">(${origem} → ${dados})</span></li>`;
          });
          mensagens.push(`<ul class="list-disc list-inside text-xs text-gray-300 space-y-1">${itens.join('')}</ul>`);
          if (divergencias.length > 6) {
            mensagens.push(`<p class="text-gray-400 text-xs mt-1">+${divergencias.length - 6} fichas adicionais.</p>`);
          }
        }

        if (semDados.length) {
          mensagens.push(`<p class="text-gray-300 text-sm mt-2">Sem dados encontrados (${semDados.length}):</p>`);
          const itensSem = semDados.slice(0, 6).map(function (prato) {
            const nome = escapeHtml(prato && prato.nome ? prato.nome : 'Sem nome');
            const codigo = escapeHtml(prato && prato.codigo ? prato.codigo : 'Sem código');
            const origem = escapeHtml(prato && prato.base_origem ? prato.base_origem : 'N/D');
            return `<li>${nome} <span class="text-gray-400">(${codigo} • ${origem})</span></li>`;
          });
          mensagens.push(`<ul class="list-disc list-inside text-xs text-gray-300 space-y-1">${itensSem.join('')}</ul>`);
          if (semDados.length > 6) {
            mensagens.push(`<p class="text-gray-400 text-xs mt-1">+${semDados.length - 6} fichas adicionais.</p>`);
          }
        }

        if (semCodigo.length) {
          mensagens.push(`<p class="text-xs text-gray-400 mt-2">Fichas sem código Cloudify: ${semCodigo.length}.</p>`);
        }
      }

      baseStatusBox.innerHTML = mensagens.join('');
      baseStatusBox.classList.remove('hidden');
    }

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
    tbody.innerHTML = pratos.map(function (p) {
      if (!p) {
        return '';
      }
      const custo = toNumber(p.custo);
      const preco = toNumber(p.preco);
      const cmv = toNumber(p.cmv);
      const margemR = preco - custo;
      const margemP = preco > 0 ? (margemR / preco * 100) : 0;
      const possuiDados = typeof p.possui_dados === 'boolean' ? p.possui_dados : Boolean(p.base_dados);
      const mismatch = Boolean(p.base_origem && p.base_dados && p.base_origem !== p.base_dados);
      const rowClasses = ['border-b', 'border-gray-700', 'hover:bg-gray-800'];
      if (!possuiDados && p.codigo) {
        rowClasses.push('bg-red-900/40');
      } else if (mismatch) {
        rowClasses.push('bg-yellow-900/40');
      }
      const baseFicha = p.base_origem ? String(p.base_origem) : 'N/D';
      const baseDados = possuiDados ? (p.base_dados ? String(p.base_dados) : 'desconhecida') : 'não encontrados';
      const tooltip = escapeHtml(`Base ficha: ${baseFicha} • Base dados: ${baseDados}`);
      const codigo = p.codigo ?? '';
      return `
        <tr class="${rowClasses.join(' ')}" title="${tooltip}">
          <td class="p-2">${codigo}</td>
          <td class="p-2">${p.nome}</td>
          <td class="p-2">${p.grupo}</td>
          <td class="p-2">R$ ${custo.toFixed(2)}</td>
          <td class="p-2">R$ ${preco.toFixed(2)}</td>
          <td class="p-2">${cmv.toFixed(1)}%</td>
          <td class="p-2">R$ ${margemR.toFixed(2)}</td>
          <td class="p-2">${margemP.toFixed(1)}%</td>
        </tr>`;
    }).join('');

    // Ajusta os cabeçalhos para ordenar pela coluna correta
    const ths = document.querySelectorAll('#tabela-sortable thead th');
    ths.forEach((th, idx) => {
      th.onclick = () => sortTable(idx);
    });

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
            dataLabels:{ name:{show:true}, value:{show:true} },
            barLabels: {
              enabled: true,
              useSeriesColors: false, 
              offsetX: -8,
              formatter: (name, opts) => `${name}: ${opts.w.globals.series[opts.seriesIndex].toFixed(1)}%`,
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

    // Exporta a tabela filtrada (apenas linhas visíveis) para XLSX
    const btnExport = document.getElementById('export-xlsx');
    if (btnExport && typeof XLSX !== 'undefined') {
      btnExport.addEventListener('click', () => {
        try {
          const table = document.getElementById('tabela-sortable');
          const headers = Array.from(table.tHead.rows[0].cells).map(th => th.textContent.trim());
          const rows = Array.from(table.tBodies[0].rows)
            .filter(tr => tr.style.display !== 'none')
            .map(tr => Array.from(tr.cells).map((td, i) => {
              const raw = td.textContent.trim();
              if ([3,4,5,6,7].includes(i)) {
                let s = raw.replace(/[R$%\s]/g, '').trim();
                if (/[,]\d{1,2}$/.test(s)) {
                  // vírgula como decimal: remove pontos (milhar) e troca vírgula por ponto
                  s = s.replace(/\./g, '').replace(/,/g, '.');
                } else if (/[.]\d{1,2}$/.test(s)) {
                  // ponto como decimal: remove vírgulas (milhar)
                  s = s.replace(/,/g, '');
                } else {
                  // fallback: normaliza vírgula para ponto
                  s = s.replace(/,/g, '.');
                }
                const num = parseFloat(s);
                return isNaN(num) ? raw : num;
              }
              return raw;
            }));

          const aoa = [headers, ...rows];
          const ws = XLSX.utils.aoa_to_sheet(aoa);

          // Aplica formatação PT-BR para decimais com vírgula
          if (ws['!ref']) {
            const range = XLSX.utils.decode_range(ws['!ref']);
            for (let R = range.s.r + 1; R <= range.e.r; ++R) { // pula o cabeçalho
              // Colunas moeda: Custo(3), Preço(4), Margem R$(6)
              [3,4,6].forEach(C => {
                const addr = XLSX.utils.encode_cell({ r: R, c: C });
                const cell = ws[addr];
                if (!cell) return;
                if (typeof cell.v === 'string') {
                  const num = parseFloat(cell.v.replace(/\./g, '').replace(/,/g, '.'));
                  if (!isNaN(num)) cell.v = num;
                }
                cell.t = 'n';
                cell.z = '[$-pt-BR]#,##0.00';
              });
              // Colunas percentuais (como número, sem %): CMV(5) e Margem %(7)
              [5,7].forEach(C => {
                const addr = XLSX.utils.encode_cell({ r: R, c: C });
                const cell = ws[addr];
                if (!cell) return;
                let val = cell.v;
                if (typeof val === 'string') {
                  val = parseFloat(val.replace(/\./g, '').replace(/,/g, '.'));
                }
                if (typeof val === 'number' && !isNaN(val)) {
                  // mantém valor em base 100 (ex.: 23.5) e formata com vírgula
                  cell.v = val;
                  cell.t = 'n';
                  cell.z = '[$-pt-BR]0.0';
                }
              });
            }
          }

          const wb = XLSX.utils.book_new();
          XLSX.utils.book_append_sheet(wb, ws, 'Detalhamento');
          XLSX.writeFile(wb, 'detalhamento.xlsx');
        } catch (e) {
          console.error('Falha ao exportar XLSX:', e);
          alert('Não foi possível exportar o XLSX.');
        }
      });
    }
  });

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
  
  // Filtro em tempo real da tabela com múltiplos termos (separe com ";"; também aceita "," e "|")
  document.getElementById('filtro-tabela').addEventListener('input', function () {
    const raw = this.value.toLowerCase();
    const termos = raw.split(/[;|,]+/).map(s => s.trim()).filter(Boolean);
    const linhas = document.querySelectorAll('#tabela-pratos tr');
    linhas.forEach(linha => {
      const textoLinha = linha.textContent.toLowerCase();
      const match = termos.length === 0 || termos.some(t => textoLinha.includes(t));
      linha.style.display = match ? '' : 'none';
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
  
  // Função de ordenação para a matriz (mantido caso seja usado em outro lugar)
  function sortMatriz(col) {
    const table = document.getElementById('table-matriz');
    if (!table) return;
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
    rows.forEach(row => tbody.appendChild(row));
    table.setAttribute('data-sort-dir-m', dir);
  }
</script>
</body>
</html>
