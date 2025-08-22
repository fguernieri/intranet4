<?php
include __DIR__ . '/../../auth.php';
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/db_dw.php';
include __DIR__ . '/../../sidebar.php';

// -------------------------------
// Matriz de Engenharia de Cardápio
// -------------------------------
require __DIR__ . '/../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$cardapioMatrizHtml = '';
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_FILES['sales_file'])
    && $_FILES['sales_file']['error'] === UPLOAD_ERR_OK
) {
    // Recuperar filtros
    $filterTap = isset($_POST['filter_tap']);
    $filterWab = isset($_POST['filter_wab']);
    $filterAll = isset($_POST['filter_all']);

    // Diretório de uploads
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $tmpName = $_FILES['sales_file']['tmp_name'];
    $fileName = basename($_FILES['sales_file']['name']);
    $targetFile = $uploadDir . $fileName;
    move_uploaded_file($tmpName, $targetFile);

    // Carregar planilha sem calcular fórmulas
    $spreadsheet = IOFactory::load($targetFile);
    $sheetData = $spreadsheet->getActiveSheet()->toArray(null, false, true, false);

    // Processar dados
    $rows = array_slice($sheetData, 1);
    $produtos = [];
    foreach ($rows as $row) {
        $nome   = $row[2] ?? '';
        $grupo  = $row[3] ?? '';
        $qtd    = (float) ($row[12] ?? 0);
        $total  = (float) ($row[13] ?? 0);
        // Aplicar filtro de grupo
        if (!$filterAll && ($filterTap || $filterWab)) {
            $pass = false;
            if ($filterTap && stripos($grupo, 'TAP') !== false) {
                $pass = true;
            }
            if ($filterWab && stripos($grupo, 'WAB') !== false) {
                $pass = true;
            }
            if (!$pass) continue;
        }
        if (!isset($produtos[$nome])) {
            $produtos[$nome] = ['qtd' => 0, 'total' => 0];
        }
        $produtos[$nome]['qtd']   += $qtd;
        $produtos[$nome]['total'] += $total;
    }

    // Cálculo de médias
    $mediaQtd   = array_sum(array_column($produtos, 'qtd')) / max(count($produtos), 1);
    $mediaTotal = array_sum(array_column($produtos, 'total')) / max(count($produtos), 1);

    // Classificação
    $matriz = [];
    foreach ($produtos as $nome => $dados) {
        $pop = $dados['qtd'];
        $luc = $dados['total'];
        if ($pop >= $mediaQtd && $luc >= $mediaTotal) {
            $cat = 'Estrela ⭐';
        } elseif ($pop >= $mediaQtd && $luc < $mediaTotal) {
            $cat = 'Vaca leiteira 🐄';
        } elseif ($pop < $mediaQtd && $luc >= $mediaTotal) {
            $cat = 'Quebra-cabeça 🧩';
        } else {
            $cat = 'Cachorro 🐶';
        }
        $matriz[] = ['produto'=>$nome, 'qtd'=>$pop, 'lucro'=>$luc, 'categoria'=>$cat];
    }

    // Montar tabela HTML com id e colunas clicáveis
    $cardapioMatrizHtml .= '<table id="table-matriz" class="table-auto w-full">';
    $cardapioMatrizHtml .= '<thead><tr>'
        . '<th onclick="sortMatriz(0)" class="p-2 cursor-pointer">Produto</th>'
        . '<th onclick="sortMatriz(1)" class="p-2 cursor-pointer">Qtde Vendida</th>'
        . '<th onclick="sortMatriz(2)" class="p-2 cursor-pointer">Total Vendido (R$)</th>'
        . '<th onclick="sortMatriz(3)" class="p-2 cursor-pointer">Categoria</th>'
        . '</tr></thead><tbody>';
    foreach ($matriz as $item) {
        $cardapioMatrizHtml .= '<tr class="border-b border-gray-700 hover:bg-gray-800">'
            . '<td class="p-2">'.htmlspecialchars($item['produto']).'</td>'
            . '<td class="p-2">'.$item['qtd'].'</td>'
            . '<td class="p-2">R$ '.number_format($item['lucro'],2,',','.').'</td>'
            . '<td class="p-2">'.$item['categoria'].'</td>'
            . '</tr>';
    }
    $cardapioMatrizHtml .= '</tbody></table>';
}
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
  
  <!-- Matrize de Cardápio -->
  <section class="mb-6">
    <form method="POST" enctype="multipart/form-data" class="mb-4 space-y-3">
      <div class="flex items-center space-x-4">
        <label class="inline-flex items-center">
          <input type="checkbox" name="filter_all" value="1" class="form-checkbox" checked>
          <span class="ml-2">Todos</span>
        </label>
        <label class="inline-flex items-center">
          <input type="checkbox" name="filter_tap" value="1" class="form-checkbox">
          <span class="ml-2">TAP</span>
        </label>
        <label class="inline-flex items-center">
          <input type="checkbox" name="filter_wab" value="1" class="form-checkbox">
          <span class="ml-2">WAB</span>
        </label>
      </div>
      <label class="block mb-1">Envie o arquivo de vendas (.xlsx):</label>
      <input type="file" name="sales_file" accept=".xlsx" required class="mb-2 px-3 py-2 rounded bg-gray-800 text-white" />
      <button type="submit" class="btn-acao px-4 py-2">Gerar Matriz de Cardápio</button>
    </form>
    <?php if ($cardapioMatrizHtml): ?>
      <div class="bg-gray-800 p-4 rounded">
        <h2 class="text-xl font-bold mb-2">Matriz de Engenharia de Cardápio</h2>
        <?= $cardapioMatrizHtml ?>
      </div>
    <?php endif; ?>
  </section>

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
