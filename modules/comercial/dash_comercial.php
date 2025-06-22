<?php
declare(strict_types=1);

// 1) Autenticação e sessão — carrega o $pdo principal
require_once __DIR__ . '/../../auth.php';
$pdoMain = $pdo; // conexão principal (intranet)

// 2) Conexão DW — para dados de pedidos
require_once '../../config/db_dw.php';

// 3) Permissões de vendedores vindas da sessão
$permissoes = $_SESSION['vendedores_permitidos'] ?? [];



// 4) Busca nomes desses vendedores (para select e filtro)
$nomesPermitidos = [];
if (!empty($permissoes)) {
    $ph = implode(',', array_fill(0, count($permissoes), '?'));
    $stmt = $pdoMain->prepare("SELECT nome FROM vendedores WHERE id IN ($ph)");
    $stmt->execute($permissoes);
    $nomesPermitidos = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// 5) Parâmetros de filtro vindos da UI (vendedores e datas)
$selectVendedores = $_GET['vendedores'] ?? ['ALL'];
$selectVendedores = is_array($selectVendedores)
    ? $selectVendedores
    : [$selectVendedores];

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date']   ?? date('Y-m-t');

// 6) Define quais vendedores realmente filtrar
if (!in_array('ALL', $selectVendedores, true)) {
    $filteredVend = array_values(
        array_intersect($nomesPermitidos, $selectVendedores)
    );
} else {
    $filteredVend = $nomesPermitidos;
}

// 7) Monta cláusulas SQL dinâmicas e parâmetros
$whereClauses = [];
$queryParams = [];

if (!empty($filteredVend)) {
    $ph2 = implode(',', array_fill(0, count($filteredVend), '?'));
    $whereClauses[] = "Vendedor IN ($ph2)";
    $queryParams   = array_merge($queryParams, $filteredVend);
}
// filtro de data
$whereClauses[] = "DATE(DataPedido) BETWEEN ? AND ?";
$queryParams[]  = $startDate;
$queryParams[]  = $endDate;

// 8) Consulta na view/tabela DB DW
$sql = "
SELECT
  Empresa,
  NumeroPedido    AS NumeroPedido,
  CodCliente      AS CodCliente,
  Cliente,
  Estado,
  DataPedido      AS DataPedido,
  Vendedor,
  DataFaturamento AS DataFaturamento,
  ValorFaturado   AS ValorFaturado,
  FormaPagamento  AS FormaPagamento
FROM PedidosComercial
" . ($whereClauses ? ' WHERE ' . implode(' AND ', $whereClauses) : '');

$stmt  = $pdo_dw->prepare($sql);
$stmt->execute($queryParams);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 9) Totais para cards
$totalPedidos  = count($pedidos);
$totalFaturado = array_sum(array_column($pedidos, 'ValorFaturado'));
$totalEstados  = count(array_unique(array_column($pedidos, 'Estado')));

// total de clientes únicos direto do result
$allClients    = array_column($pedidos, 'CodCliente');
$allClients    = array_filter($allClients, fn($c) => $c !== null && $c !== '');
$totalClientes = count(array_unique($allClients));

// 10) Calcula datas mínima e máxima
$data_inicial = null;
$data_final   = null;
foreach ($pedidos as $p) {
    $ts = strtotime($p['DataPedido']);
    if ($data_inicial === null || $ts < $data_inicial) {
        $data_inicial = $ts;
    }
    if ($data_final === null || $ts > $data_final) {
        $data_final = $ts;
    }
}

// 11) Processa estatísticas para charts
$porVendedor      = [];
$porPagamento     = [];
$porData          = [];
$pedidosPorDia    = [];
$pedidosPorEstado = [];
$clientesPorV     = [];

// --- 11.1) Novo: total de cadastros por vendedor entre $startDate e $endDate ---
$stmtCadastro = $pdo->prepare("
  SELECT 
    vendedor_nome, 
    COUNT(*) AS total_cadastros
  FROM cadastro_clientes
  WHERE DATE(data_cadastro) BETWEEN ? AND ?
  GROUP BY vendedor_nome
  ORDER BY total_cadastros DESC
");
$stmtCadastro->execute([$startDate, $endDate]);
$cadRows    = $stmtCadastro->fetchAll(PDO::FETCH_ASSOC);
$cadLabels  = array_column($cadRows, 'vendedor_nome');
$cadValues  = array_map('intval', array_column($cadRows, 'total_cadastros'));


// Buscar metas de faturamento
require_once __DIR__ . '/../../config/db.php'; // conexão de metas
$pdoMetas = $pdo;

$anoMeta = date('Y', strtotime($startDate));
$mesMeta = date('m', strtotime($startDate));

$sql_meta = "
  SELECT v.nome, mv.valor
  FROM metas_valores mv
  JOIN metas_tipos mt ON mv.id_tipo = mt.id
  JOIN vendedores v ON v.id = mv.id_vendedor
  WHERE mv.ano = ? AND mv.mes = ? AND mt.slug = 'faturamento'
";
$stmt_meta = $pdoMetas->prepare($sql_meta);
$stmt_meta->execute([$anoMeta, $mesMeta]);
$metas = $stmt_meta->fetchAll(PDO::FETCH_KEY_PAIR);


foreach ($pedidos as $p) {
    $ven = $p['Vendedor'];
    $fp  = $p['FormaPagamento'] ?? 'N/A';
    $val = (float) $p['ValorFaturado'];
    $d   = substr($p['DataPedido'], 0, 10);
    $est = $p['Estado'] ?? 'N/A';
    $cli = $p['CodCliente'];

    $porVendedor[$ven]      = ($porVendedor[$ven]      ?? 0) + $val;
    $porPagamento[$fp]      = ($porPagamento[$fp]      ?? 0) + $val;
    $porData[$d]            = ($porData[$d]            ?? 0) + $val;
    $pedidosPorDia[$d]      = ($pedidosPorDia[$d]      ?? 0) + 1;
    $pedidosPorEstado[$est] = ($pedidosPorEstado[$est] ?? 0) + 1;

    if ($cli) {
        if (!in_array($cli, $clientesPorV[$ven] ?? [], true)) {
            $clientesPorV[$ven][] = $cli;
        }
    }
}


$metasV = [];
foreach (array_keys($porVendedor) as $vendedor) {
  $metasV[$vendedor] = isset($metas[$vendedor]) ? (float)$metas[$vendedor] : 0;
}

// Calcula somatório de meta
$metaFaturamentoTotal = 0;
foreach ($filteredVend as $vendedor) {
    if (isset($metasV[$vendedor])) {
        $metaFaturamentoTotal += $metasV[$vendedor];
    }
}

// Calcula % atingido da meta
$percentualMeta = $metaFaturamentoTotal > 0
    ? ($totalFaturado / $metaFaturamentoTotal) * 100
    : 0;

$clientesCount = array_map('count', $clientesPorV);

$sql = "SELECT MAX(data_hora) AS UltimaAtualizacao FROM fAtualizacoes";
$stmt = $pdo_dw->query($sql);
$UltimaAtualizacao = $stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard Comercial v1.2</title>
  <link rel="stylesheet" href="../../assets/css/style.css" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>
<body class="body bg-gray-900 text-white">
  <div class="flex h-screen">
    <?php 
    include __DIR__ . '/../../sidebar.php'; 
    ?>
    <main class="flex-1 p-6 overflow-auto">
      <h1 class="text-3xl font-bold mb-2 text-yellow-400 text-center">Bem-vindo, <?= htmlspecialchars($_SESSION['usuario_nome']); ?></h1>
      <h2 class="text-xl font-semibold mb-6 text-center text-white">Dashboard Comercial</h2>
      <?php require_once '../../components/tempo_curitiba.php'; ?>

      <!-- Formulário de filtros -->
      <form method="get" class="bg-gray-800 rounded-lg p-6 grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 text-white">
        <div>
          <label class="block mb-2 text-sm font-semibold">🧑‍💼 Vendedores</label>
          <select name="vendedores[]" multiple class="w-full h-32 bg-gray-700 border border-gray-600 rounded-md text-sm p-2">
            <option value="ALL" <?= in_array('ALL',$selectVendedores,true)? 'selected':'' ?>>Todos</option>
            <?php foreach($nomesPermitidos as $nome): ?>
              <option value="<?= htmlspecialchars($nome) ?>" <?= in_array($nome,$selectVendedores,true)? 'selected':'' ?>><?= htmlspecialchars($nome) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="grid-cols-2 gap-6">
          <div><label class="block mb-2 text-sm font-semibold">📅 Data Início</label><input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="w-full bg-gray-700 border border-gray-600 rounded-md p-2 text-sm"></div>
          <div><label class="block mb-2 text-sm font-semibold">📅 Data Fim</label><input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="w-full bg-gray-700 border border-gray-600 rounded-md p-2 text-sm"></div>
          <div class="p-2"><p><strong>Período disponível:</strong> <?= $data_inicial? date('d/m/Y',$data_inicial):'' ?> a <?= $data_final? date('d/m/Y',$data_final):'' ?></p></div>
          <div><p class="p-2 text-sm text-gray-400 mt-auto">Última Atualização em: <?=date('d/m/Y H:i:s', strtotime($UltimaAtualizacao))?></p></div>
          <div class="flex justify-end gap-4">
            <button id="btnCarregarMetas" class="btn-acao" type="button">Carregar Clientes</button>
            <a class="btn-acao" href="metas.php">Cadastro Metas</a>
            <button type="submit" class="btn-acao">Aplicar Filtros</button>
          </div>
        </div>
      </form>
      
      <!-- Cards de resumo -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5 mb-8">
        <div class="card1"><p>💵 Total Faturado</p><p>R$ <?= number_format($totalFaturado,2,',','.') ?></p></div>
        <div class="card1">
          <p>🎯 Meta de Faturamento</p>
          <p>
            <span class="text-lg font-bold text-white">R$ <?= number_format($metaFaturamentoTotal/ 1000, 0, '', '') . 'k' ?></span>
            <span class="text-sm text-gray-400 ml-1">(<?= number_format($percentualMeta, 1, ',', '.') ?>%)</span>
          </p>
        </div>        
        <div class="card1"><p>📦 Total de Pedidos</p><p><?= $totalPedidos ?></p></div>
        <div class="card1"><p>🏪 Clientes Únicos</p><p><?= $totalClientes ?></p></div>
        <div class="card1"><p>🌎 Estados com Pedido</p><p><?= $totalEstados ?></p></div>
      </div>      
      
      <!-- Charts -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <?php 
          $charts = [
            'chartVendedor' => 'Total por Vendedor',
            'chartPagamento' => 'Total por Forma de Pagamento',
            'chartData' => 'Faturamento por Data',
            'chartPedidosDia' => 'Pedidos por Dia',
            'chartClientes' => 'Clientes Únicos por Vendedor',
            'chartEstado' => 'Pedidos por Estado'
          ];
          $charts['chartCadastros'] = 'Abertura de Clientes';


          $sortableCharts = ['chartVendedor', 'chartClientes', 'chartEstado', 'chartPedidosDia', 'chartData'];
          $sortableCharts[] = 'chartCadastros';


          foreach ($charts as $id => $label): ?>
            <div class="rounded-xl bg-white/5 p-4 shadow-md">
              <div class="flex justify-between items-center mb-2">
                <div class="flex justify-between items-center">
                  <p class="font-medium text-white p-2"><?= $label ?></p>
                  <label class="flex items-center space-x-2">
                    <input type="checkbox" class="form-checkbox toggle-meta" data-target="<?= $id ?>" />
                    <span class="text-sm">Exibir metas</span>
                  </label>
                </div>

                <?php if (in_array($id, $sortableCharts)): ?>
                  <select 
                    data-target="<?= $id ?>" 
                    class="sort-dropdown bg-gray-800 text-white text-xs rounded px-2 py-1 border border-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="default">Original</option>
                    <option value="asc">A-Z</option>
                    <option value="desc">Z-A</option>
                    <option value="value_asc">↑ Valor</option>
                    <option value="value_desc">↓ Valor</option>
                  </select>
                <?php endif; ?>
              </div>
              <div id="<?= $id ?>"></div>
            </div>
        <?php endforeach; ?>
      </div>
      
          <!-- Tabela de Pedidos com Ordenação e Seção Retrátil -->
    <section class="mt-10">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-2xl font-semibold text-yellow-400 text-center w-full">📦 Tabela de Pedidos</h3>
        <button onclick="toggleTabelaPedidos()" class="ml-4 text-sm bg-yellow-500 hover:bg-yellow-600 text-black font-semibold px-3 py-1 rounded">
          Mostrar/Ocultar
        </button>
      </div>

      <div id="tabelaPedidosWrapper" class="overflow-auto rounded-lg shadow">
        <table class="sortable min-w-full divide-y divide-gray-700 bg-gray-800 text-white text-sm">
          <thead class="bg-gray-700">
            <tr>
              <th class="px-4 py-2 cursor-pointer" onclick="sortTable(0)">📦 Pedido</th>
              <th class="px-4 py-2 cursor-pointer" onclick="sortTable(1)">👤 Cliente</th>
              <th class="px-4 py-2 cursor-pointer" onclick="sortTable(2)">🗺 Estado</th>
              <th class="px-4 py-2 cursor-pointer" onclick="sortTable(3)">🧑‍💼 Vendedor</th>
              <th class="px-4 py-2 cursor-pointer" onclick="sortTable(4)">📅 Data Faturamento</th>
              <th class="px-4 py-2 cursor-pointer" onclick="sortTable(5)">💰 Valor</th>
            </tr>
          </thead>
          <tbody data-sort-dir="asc">
            <?php foreach ($pedidos as $row): ?>
            <tr class="hover:bg-gray-700">
              <td class="px-4 py-2"><?= $row['NumeroPedido'] ?? '' ?></td>
              <td class="px-4 py-2"><?= $row['Cliente'] ?? '' ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($row['Estado'] ?? '') ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($row['Vendedor'] ?? '') ?></td>
              <td class="px-4 py-2">
                <?= $row['DataFaturamento'] ? htmlspecialchars(date('d/m/Y', strtotime($row['DataFaturamento']))) : '' ?>
              </td>
              <td class="px-4 py-2">R$ <?= isset($row['ValorFaturado']) ? number_format((float)$row['ValorFaturado'], 2, ',', '.') : '0,00' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

      
      <script>
        // 💸 Arredondamento para valores inteiros
        const arredondar = arr => arr.map(v => parseFloat(parseFloat(v).toFixed(0)));
        
        // metas
        const metasVendedor = <?= json_encode($metasV) ?>;

        // 📊 Mapa com dados dos gráficos categóricos
        const chartDataMap = {
          chartVendedor: {
            labels: <?= json_encode(array_keys($porVendedor)) ?>.map(v => v.split(' ')[0]),
            values: arredondar(<?= json_encode(array_values($porVendedor)) ?>),
            type: 'bar'
          },
          chartClientes: {
            labels: <?= json_encode(array_keys($clientesCount)) ?>.map(v => v.split(' ')[0]),
            values: <?= json_encode(array_values($clientesCount)) ?>,
            type: 'bar'
          },
          chartEstado: {
            labels: <?= json_encode(array_keys($pedidosPorEstado)) ?>,
            values: <?= json_encode(array_values($pedidosPorEstado)) ?>,
            type: 'bar'
          },
          chartPedidosDia: {
            labels: <?= json_encode(array_keys($pedidosPorDia)) ?>,
            values: <?= json_encode(array_values($pedidosPorDia)) ?>,
            type: 'bar'
          },
          chartData: {
            labels: <?= json_encode(array_keys($porData)) ?>,
            values: arredondar(<?= json_encode(array_values($porData)) ?>),
            type: 'line'
          },
            
          chartCadastros: {
            labels: <?= json_encode($cadLabels, JSON_THROW_ON_ERROR) ?>,
            values: <?= json_encode($cadValues, JSON_THROW_ON_ERROR) ?>,
            type: 'bar'
          }
        };

        // 🎯 Ordenação padrão por gráfico
        const defaultSorts = {
          chartVendedor: 'value_desc',
          chartClientes: 'value_desc',
          chartEstado: 'value_desc',
          chartPedidosDia: 'asc',
          chartData: 'asc',
          chartCadastros:   'value_desc'

        };

        // 🍩 Donut chart (forma de pagamento) - sem ordenação
        const donutChart = {
          id: 'chartPagamento',
          type: 'donut',
          labels: <?= json_encode(array_keys($porPagamento)) ?>,
          values: <?= json_encode(array_values($porPagamento)) ?>
        };

        // 📈 Renderiza qualquer gráfico
        function renderApex(selector, options, metas = null, toggle = null) {
          const el = document.querySelector(selector);
          if (!el) return;

          const series = [{ name: 'Faturamento', data: options.series }];

          if (metas && toggle?.checked) {
            series.push({ name: 'Meta', data: metas });
          }

          el.innerHTML = '';
          new ApexCharts(el, {
            chart: {
              type: options.type,
              height: 300,
              background: 'transparent'
            },
            theme: { mode: 'dark' },
            series,
            xaxis: { categories: options.labels },
            plotOptions: {
              bar: {
                horizontal: false,
                columnWidth: '40%',
                dataLabels: { position: 'top' }
              }
            },
            dataLabels: { enabled: false },
            tooltip: {
              y: {
                formatter: val => new Intl.NumberFormat('pt-BR', {
                  style: 'currency',
                  currency: 'BRL'
                }).format(val)
              }
            }
          }).render();
        }

        function sortAndRenderChart(chartId, sortBy) {
          const { labels, values, type } = chartDataMap[chartId];
          let combined = labels.map((label, i) => ({ label, value: values[i] }));

          switch (sortBy) {
            case 'asc':        combined.sort((a, b) => a.label.localeCompare(b.label)); break;
            case 'desc':       combined.sort((a, b) => b.label.localeCompare(a.label)); break;
            case 'value_asc':  combined.sort((a, b) => a.value - b.value); break;
            case 'value_desc': combined.sort((a, b) => b.value - a.value); break;
          }

          const sortedLabels = combined.map(x => x.label);
          const sortedValues = combined.map(x => x.value);

          const toggle = document.querySelector(`.toggle-meta[data-target="${chartId}"]`);
          let metas = null;

          if (chartId === 'chartVendedor') {
            const nomeCompletoOriginal = Object.keys(metasVendedor); // eg. 'HAANY MEDEIROS'
            metas = sortedLabels.map(primeiroNome => {
              const fullName = nomeCompletoOriginal.find(n => n.startsWith(primeiroNome)) || '';
              return metasVendedor[fullName] ?? 0;
            });
          }

          renderApex(`#${chartId}`, {
            type,
            labels: sortedLabels,
            series: sortedValues
          }, metas, toggle);
        }


        // 🚀 Inicializa gráficos ao carregar a página
        window.addEventListener('load', () => {
          // Gráficos com ordenação dinâmica
          Object.keys(chartDataMap).forEach(chartId => {
            const defaultSort = defaultSorts[chartId] || 'default';
            sortAndRenderChart(chartId, defaultSort);

            // Atualiza o <select> com o valor padrão
            const dropdown = document.querySelector(`.sort-dropdown[data-target="${chartId}"]`);
            if (dropdown) dropdown.value = defaultSort;
          });

          // Donut chart
          renderApex(`#${donutChart.id}`, {
            chart: { type: 'donut', height: 300, background: 'transparent' },
            theme: { mode: 'dark' },
            series: donutChart.values,
            labels: donutChart.labels,
            tooltip: {
              y: {
                formatter: val => new Intl.NumberFormat('pt-BR', {
                  style: 'currency',
                  currency: 'BRL'
                }).format(val)
              }
            }
          });
        });

        document.addEventListener('DOMContentLoaded', () => {
        // Dropdowns de ordenação
        document.querySelectorAll('.sort-dropdown').forEach(dropdown => {
          dropdown.addEventListener('change', e => {
            const chartId = e.target.dataset.target;
            const sortBy = e.target.value;
            sortAndRenderChart(chartId, sortBy);
          });
        });

        // Checkboxes de meta
        document.querySelectorAll('.toggle-meta').forEach(toggle => {
          toggle.addEventListener('change', e => {
            const chartId = e.target.dataset.target;
            const sortSelect = document.querySelector(`.sort-dropdown[data-target="${chartId}"]`);
            const currentSort = sortSelect?.value || 'default';
            sortAndRenderChart(chartId, currentSort);
          });
        });
      });
      
      // Scripts Tabela pedidos
      function sortTable(col) {
        const table = document.querySelector("table.sortable tbody");
        const rows = Array.from(table.querySelectorAll("tr"));
        const isNumeric = col === 5 || col === 0;

        const sortedRows = rows.sort((a, b) => {
          const aText = a.children[col].innerText.trim();
          const bText = b.children[col].innerText.trim();

          if (isNumeric) {
            const normalize = str => parseFloat(
              str.replace(/[^\d,.-]/g, '') // remove "R$", espaços, etc.
                 .replace(/\./g, '')       // remove ponto de milhar
                 .replace(',', '.')        // troca vírgula decimal por ponto
            ) || 0;

            const aNum = normalize(aText);
            const bNum = normalize(bText);
            return aNum - bNum;
            
          } else if (col === 4) {
            return new Date(aText.split('/').reverse().join('-')) - new Date(bText.split('/').reverse().join('-'));
          } else {
            return aText.localeCompare(bText);
          }
        });

        const direction = table.getAttribute("data-sort-dir") === "asc" ? "desc" : "asc";
        table.setAttribute("data-sort-dir", direction);
        if (direction === "desc") sortedRows.reverse();

        rows.forEach(row => table.removeChild(row));
        sortedRows.forEach(row => table.appendChild(row));
      }

      function toggleTabelaPedidos() {
        const el = document.getElementById("tabelaPedidosWrapper");
        el.classList.toggle("hidden");
      }

  // Exibe ou oculta o modal
  function toggleModal(show) {
    const modal = document.getElementById('modalCarregarMetas');
    if (show) {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    } else {
      modal.classList.remove('flex');
      modal.classList.add('hidden');
    }
  }

  // Validação básica antes do submit
  function validarArquivo() {
    const input = document.querySelector('input[name="arquivo_metas"]');
    const file  = input.files[0];
    if (!file) {
      alert('Selecione um arquivo .xlsx');
      return false;
    }
    const ext = file.name.split('.').pop().toLowerCase();
    if (ext !== 'xlsx') {
      alert('Formato inválido. Use somente .xlsx');
      return false;
    }
    return true;
  }

  // Atrela clique do botão de abertura
  document.getElementById('btnCarregarMetas')
          .addEventListener('click', () => toggleModal(true));
</script>


</body>

<!-- Modal backdrop -->
<div
  id="modalCarregarMetas"
  class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center"
>
  <div class="bg-gray-700 rounded-lg shadow-lg w-96 p-6">
    <h2 class="text-xl font-semibold mb-4">Importar Base de Clientes</h2>
    <form
      action="import_cadastros.php"
      method="POST"
      enctype="multipart/form-data"
      onsubmit="return validarArquivo()"
    >
      <label class="block mb-2">
        <span class="text-gray-700">Arquivo .xlsx</span>
        <input
          type="file"
          name="arquivo_metas"
          accept=".xlsx"
          required
          class="mt-1 block w-full"
        />
      </label>
      <div class="flex justify-end space-x-2 mt-4">
        <button
          type="button"
          onclick="toggleModal(false)"
          class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400"
        >
          Cancelar
        </button>
        <button
          type="submit"
          class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
        >
          Importar
        </button>
      </div>
    </form>
  </div>
</div>

</html>