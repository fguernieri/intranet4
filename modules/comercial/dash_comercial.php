<?php
declare(strict_types=1);

// 1) Autenticação e sessão — carrega o $pdo principal
require_once __DIR__ . '/../../auth.php';
$pdoMain = $pdo; // conexão principal (intranet)

// 2) Conexão DW — para dados de pedidos
require_once __DIR__ . '/../../config/db_dw.php';
require_once __DIR__ . '/../../config/db.php'; // conexão de metas
require_once __DIR__ . '/../../vendedor_alias.php';
$aliasData = getVendedorAliasMap($pdoMain);
$aliasMap = $aliasData['alias_to_nome'];
$nomeToTodos = $aliasData['nome_to_todos'];


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
    $namesForFilter = [];
    foreach ($filteredVend as $n) {
        $namesForFilter = array_merge($namesForFilter, $nomeToTodos[$n] ?? [$n]);
    }
    $ph2 = implode(',', array_fill(0, count($namesForFilter), '?'));
    $whereClauses[] = "Vendedor IN ($ph2)";
    $queryParams   = array_merge($queryParams, $namesForFilter);
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
foreach ($pedidos as &$p) {
    $p['Vendedor'] = resolveVendedorNome($p['Vendedor'], $aliasMap);
}
unset($p);

// Montar agregação mês a mês em PHP
$mensal = [];
foreach ($pedidos as $p) {
    // extrai “YYYY-MM” de DataPedido
    $key = date('Y-m', strtotime($p['DataPedido']));
    if (!isset($mensal[$key])) {
        $mensal[$key] = [
            'ano'             => date('Y', strtotime($p['DataPedido'])),
            'mes'             => date('m', strtotime($p['DataPedido'])),
            'faturado'        => 0.0,
            'total_pedidos'   => 0,
            'total_clientes'  => [],
            'total_estados'   => [],
        ];
    }
    // acumula faturamento e contagem
    $mensal[$key]['faturado']      += (float)$p['ValorFaturado'];
    $mensal[$key]['total_pedidos'] += 1;
    // para clientes/estados únicos, guardamos num array como chave
    $mensal[$key]['total_clientes'][$p['CodCliente']]    = true;
    $mensal[$key]['total_estados'][$p['Estado']]         = true;
}

// agora converte o array para índices numéricos e contas finais
$mensal = array_map(
    fn($r) => [
        'ano'             => $r['ano'],
        'mes'             => $r['mes'],
        'faturado'        => $r['faturado'],
        'total_pedidos'   => $r['total_pedidos'],
        'total_clientes'  => count($r['total_clientes']),
        'total_estados'   => count($r['total_estados']),
    ],
    $mensal
);

// ordena cronologicamente (por chave YYYY-MM)
uksort($mensal, fn($a, $b) => strcmp($a, $b));
$mensal = array_values($mensal);

// Exemplo de uso para o gráfico consolidado:
$labelsFatMensal = array_map(fn($r) => "{$r['mes']}/{$r['ano']}", $mensal);
$seriesFatMensal = array_map(fn($r) => $r['faturado'], $mensal);


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
$sqlCad = "SELECT vendedor_nome, COUNT(*) AS total_cadastros FROM cadastro_clientes WHERE DATE(data_cadastro) BETWEEN ? AND ?";
$paramsCad = [$startDate, $endDate];
if (!empty($filteredVend)) {
    $namesCad = [];
    foreach ($filteredVend as $n) {
        $namesCad = array_merge($namesCad, $nomeToTodos[$n] ?? [$n]);
    }
    $phCad = implode(',', array_fill(0, count($namesCad), '?'));
    $sqlCad .= " AND vendedor_nome IN ($phCad)";
    $paramsCad = array_merge($paramsCad, $namesCad);
}
$sqlCad .= " GROUP BY vendedor_nome ORDER BY total_cadastros DESC";
$stmtCadastro = $pdo->prepare($sqlCad);
$stmtCadastro->execute($paramsCad);
$cadRows = $stmtCadastro->fetchAll(PDO::FETCH_ASSOC);
$cadGrouped = [];
foreach ($cadRows as $r) {
    $nome = resolveVendedorNome($r['vendedor_nome'], $aliasMap);
    $cadGrouped[$nome] = ($cadGrouped[$nome] ?? 0) + (int)$r['total_cadastros'];
}
$cadLabels = array_keys($cadGrouped);
$cadValues = array_values($cadGrouped);
$totalCadGeral = array_sum($cadValues);

// 11.2 - Cadastro Mensal
$sqlMensalCad = "SELECT DATE_FORMAT(data_cadastro, '%Y-%m') AS mes, COUNT(*) AS total_cad_mes FROM cadastro_clientes WHERE DATE(data_cadastro) BETWEEN ? AND ?";
$paramsMensal = [$startDate, $endDate];
if (!empty($filteredVend)) {
    $namesCad = [];
    foreach ($filteredVend as $n) {
        $namesCad = array_merge($namesCad, $nomeToTodos[$n] ?? [$n]);
    }
    $phCad = implode(',', array_fill(0, count($namesCad), '?'));
    $sqlMensalCad .= " AND vendedor_nome IN ($phCad)";
    $paramsMensal = array_merge($paramsMensal, $namesCad);
}
$sqlMensalCad .= " GROUP BY mes ORDER BY mes";
$stmtMensalCad = $pdo->prepare($sqlMensalCad);
$stmtMensalCad->execute($paramsMensal);
$mensalCadRows = $stmtMensalCad->fetchAll(PDO::FETCH_ASSOC);

// Labels no formato “MM/YYYY”
$labelsCad = array_map(
  fn($r) => date('m/Y', strtotime($r['mes'].'-01')),
  $mensalCadRows
);
$seriesCad = array_column($mensalCadRows, 'total_cad_mes');

// 11.2.1 - Listagem de clientes por mês (para tooltip do gráfico "Abertura de Clientes")
$sqlMensalNomes = "SELECT DATE_FORMAT(data_cadastro, '%Y-%m') AS mes, nome_fantasia FROM cadastro_clientes WHERE DATE(data_cadastro) BETWEEN ? AND ?";
$paramsNomes = [$startDate, $endDate];
if (!empty($filteredVend)) {
    $namesCad = [];
    foreach ($filteredVend as $n) {
        $namesCad = array_merge($namesCad, $nomeToTodos[$n] ?? [$n]);
    }
    $phCad = implode(',', array_fill(0, count($namesCad), '?'));
    $sqlMensalNomes .= " AND vendedor_nome IN ($phCad)";
    $paramsNomes = array_merge($paramsNomes, $namesCad);
}
$sqlMensalNomes .= " ORDER BY data_cadastro";
$stmtMensalNomes = $pdo->prepare($sqlMensalNomes);
$stmtMensalNomes->execute($paramsNomes);
$rowsMensalNomes = $stmtMensalNomes->fetchAll(PDO::FETCH_ASSOC);

// Mapeia para chave label "MM/YYYY" -> [nomes]
$cadNamesByLabel = [];
foreach ($rowsMensalNomes as $rowNome) {
    $label = date('m/Y', strtotime(($rowNome['mes'] ?? '1970-01') . '-01'));
    $nome  = trim((string)($rowNome['nome_fantasia'] ?? ''));
    if ($nome === '') continue;
    $cadNamesByLabel[$label][] = $nome;
}
// Alinha na mesma ordem das labels do gráfico
$cadNamesAligned = array_map(
    fn($label) => array_values(array_unique($cadNamesByLabel[$label] ?? [])),
    $labelsCad
);



// Buscar metas de faturamento
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

// Buscar metas de abertura
$pdoMetas = $pdo;

$anoMeta = date('Y', strtotime($startDate));
$mesMeta = date('m', strtotime($startDate));

$sql_meta = "
  SELECT v.nome, mv.valor
  FROM metas_valores mv
  JOIN metas_tipos mt ON mv.id_tipo = mt.id
  JOIN vendedores v ON v.id = mv.id_vendedor
  WHERE mv.ano = ? AND mv.mes = ? AND mt.nome = 'Abertura'
";
$stmt_meta = $pdoMetas->prepare($sql_meta);
$stmt_meta->execute([$anoMeta, $mesMeta]);
$metas_abertura = $stmt_meta->fetchAll(PDO::FETCH_KEY_PAIR);


foreach ($pedidos as $p) {
    $ven = resolveVendedorNome($p['Vendedor'], $aliasMap);
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

$metasAB = [];
foreach (array_keys($porVendedor) as $vendedor) {
  $metasAB[$vendedor] = isset($metas_abertura[$vendedor]) ? (float)$metas_abertura[$vendedor] : 0;
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

$clientesCount = [];
foreach ($clientesPorV as $vendedor => $clientes) {
  $clientesCount[$vendedor] = count($clientes);
}

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
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x/dist/cdn.min.js" defer></script>
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
      
    <div x-data="dashboard()">
          <!-- Nav de Tabs -->
      <nav class="flex space-x-4 border-b mb-6">
        <button
          @click="tab = 'vendedor'"
          :class="tab === 'vendedor'
            ? 'border-blue-500 text-blue-600'
            : 'border-transparent text-gray-600 hover:text-yellow-600'"
          class="px-3 py-2 font-medium border-b-2 transition-colors"
        >
          Por Vendedor
        </button>
      
        <button
          @click="tab = 'mensal'"
          :class="tab === 'mensal'
            ? 'border-blue-500 text-blue-600'
            : 'border-transparent text-gray-600 hover:text-yellow-600'"
          class="px-3 py-2 font-medium border-b-2 transition-colors"
        >
          Mensal
        </button>
      </nav>
      <div x-show="tab === 'vendedor'" x-cloak>
      <!-- Cards de resumo -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-5 mb-8">
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
        <div class="card1"><p>🆕 Novos Cadastros</p><p><?= $totalCadGeral ?></p></div>
      </div>      
      
      <!-- Charts -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <?php 
          $charts = [
            'chartVendedor' => 'Total por Vendedor',
            'chartCadastros' => 'Abertura de Clientes',
            'chartData' => 'Faturamento por Data',
            'chartPedidosDia' => 'Pedidos por Dia',
            'chartClientes' => 'Clientes Únicos por Vendedor',
            'chartEstado' => 'Pedidos por Estado',
            'chartPagamento' => 'Total por Forma de Pagamento',
          ];

          $sortableCharts = ['chartVendedor', 'chartCadastros', 'chartClientes', 'chartEstado', 'chartPedidosDia', 'chartData'];


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
        <div class="flex items-center gap-4 mb-4">
          <h3 class="flex-grow text-2xl font-semibold text-yellow-400 text-center">📦 Tabela de Pedidos</h3>
          <input id="filtroPedidos" type="text" placeholder="Filtrar..." class="px-2 py-1 rounded bg-gray-700 text-sm text-white" oninput="filtrarTabelaPedidos()" />
          <button onclick="toggleTabelaPedidos()" class="text-sm bg-yellow-500 hover:bg-yellow-600 text-black font-semibold px-3 py-1 rounded">
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
      </div>
      <div x-show="tab === 'mensal'" x-cloak>
        <h1 class="text-3xl font-bold mb-6 text-yellow-400 text-center">CONSOLIDADO MENSAL</h1>
        <div x-ref="chartMensal" class="rounded-xl bg-white/5 p-4 shadow-md flex items-center mb-6"></div>
        <div x-ref="chartMensalCad" class="rounded-xl bg-white/5 p-4 shadow-md flex items-center mb-6"></div>
        
      </div>
    </div>
      
      <script>
        // 💸 Arredondamento para valores inteiros
        const arredondar = arr => arr.map(v => parseFloat(parseFloat(v).toFixed(0)));
        
        // metas
        const metasVendedor = <?= json_encode($metasV) ?>;
        const metasAbertura = <?= json_encode($metasAB) ?>;


        // 📊 Mapa com dados dos gráficos categóricos
        const chartDataMap = {
          chartVendedor: {
            labels: <?= json_encode(array_keys($porVendedor)) ?>.map(v => v.split(' ')[0]),
            values: arredondar(<?= json_encode(array_values($porVendedor)) ?>),
            type: 'bar'
          },
          chartClientes: {
            labels: <?= json_encode(array_keys($clientesCount)) ?>,
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
            labels: <?= json_encode($cadLabels, JSON_THROW_ON_ERROR) ?>.map(v => v.split(' ')[0]),
            values: <?= json_encode($cadValues, JSON_THROW_ON_ERROR) ?>,
            names:  <?= json_encode($cadNamesAligned, JSON_THROW_ON_ERROR) ?>,
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
          // Define tooltip customizado para "Abertura de Clientes" (mostrar nomes)
          const chartId = String(selector || '').replace('#','');
          const hasNames = Array.isArray(options.names);
          const currencyFmt = val => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val);
          const escapeHtml = (s) => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));

          const tooltip = (chartId === 'chartCadastros' && hasNames)
            ? {
                custom: ({ dataPointIndex }) => {
                  const nomes = options.names?.[dataPointIndex] || [];
                  if (!nomes.length) return '<div class="apexcharts-tooltip">Sem clientes</div>';
                  const itens = nomes.map(n => `<div>${escapeHtml(n)}</div>`).join('');
                  return `<div class="apexcharts-tooltip">${itens}</div>`;
                }
              }
            : {
                y: { formatter: currencyFmt }
              };

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
            tooltip
          }).render();
        }

        function sortAndRenderChart(chartId, sortBy) {
          const { labels, values, type, names } = chartDataMap[chartId];
          let combined = labels.map((label, i) => ({ label, value: values[i], names: names ? names[i] : undefined }));

          switch (sortBy) {
            case 'asc':        combined.sort((a, b) => a.label.localeCompare(b.label)); break;
            case 'desc':       combined.sort((a, b) => b.label.localeCompare(a.label)); break;
            case 'value_asc':  combined.sort((a, b) => a.value - b.value); break;
            case 'value_desc': combined.sort((a, b) => b.value - a.value); break;
          }

          const sortedLabels = combined.map(x => x.label);
          const sortedValues = combined.map(x => x.value);
          const sortedNames  = combined.map(x => x.names);

          const toggle = document.querySelector(`.toggle-meta[data-target="${chartId}"]`);
          let metas = null;

          if (chartId === 'chartVendedor') {
            const nomeCompletoOriginal = Object.keys(metasVendedor); // eg. 'HAANY MEDEIROS'
            metas = sortedLabels.map(primeiroNome => {
              const fullName = nomeCompletoOriginal.find(n => n.startsWith(primeiroNome)) || '';
              return metasVendedor[fullName] ?? 0;
            });
          }
          
          if (chartId === 'chartCadastros') {
            metas = sortedLabels.map(primeiroNome => {
              const found = Object.keys(metasAbertura)
                .find(m => m.startsWith(primeiroNome));
              return found ? metasAbertura[found] : 0;
            });
          }
          
          renderApex(`#${chartId}`, {
            type,
            labels: sortedLabels,
            series: sortedValues,
            names:  sortedNames
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

      function filtrarTabelaPedidos() {
        const filtro = document.getElementById("filtroPedidos").value.toLowerCase();
        const linhas = document.querySelectorAll("#tabelaPedidosWrapper tbody tr");
        linhas.forEach(tr => {
          const texto = tr.textContent.toLowerCase();
          tr.style.display = texto.includes(filtro) ? "" : "none";
        });
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
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('dashboard', () => ({
    tab: 'vendedor',
    labelsMensal: <?= json_encode($labelsFatMensal) ?>,
    seriesMensal: <?= json_encode($seriesFatMensal) ?>,
    labelsCad:    <?= json_encode($labelsCad) ?>,
    seriesCad:    <?= json_encode($seriesCad) ?>,

    chartConfigs: [
      {
        key: 'barChart',
        ref: 'chartMensal',
        title: 'Faturamento Mensal',
        dataKey: 'seriesMensal',
        labelKey: 'labelsMensal',
        // options específicas para o gráfico de barras
        baseOptions: {
          chart: { type: 'bar', height: 350, background: 'transparent', zoom: { enabled: false }, toolbar: { show: false } },
          theme: { mode: 'dark' },
          dataLabels: { 
            enabled: true,
            formatter: v => new Intl.NumberFormat('pt-BR',{ style:'currency',currency:'BRL',maximumFractionDigits:0 }).format(v),
          },
          title: {
            text: 'Faturamento Mensal',
            align: 'left',
            color: '#e7e7e7',
            },          
          plotOptions: {
            bar: { columnWidth: '50%', horizontal: false }
          },
          tooltip: {
            y: {
              formatter: v => new Intl.NumberFormat('pt-BR',{ style:'currency',currency:'BRL' }).format(v)
            }
          }
        }
      },
      {
        key: 'cadMensal',
        ref: 'chartMensalCad',
        title: 'Cadastros Mensais',
        dataKey: 'seriesCad',
        labelKey: 'labelsCad',
        // options específicas para o gráfico de linha
        baseOptions: {
          chart: { type: 'area', height: 350, background: 'transparent', zoom: { enabled: false }, toolbar: { show: false } },
          theme: { mode: 'dark' },
          stroke: { curve: 'smooth', width: 3 },
          yaxis: { min: 0 },
          dataLabels: { enabled: false },
          markers: { size: 3 },
          tooltip: { enabled: true },
          title: {
            text: 'Abertura de Clientes',
            align: 'left',
            color: '#e7e7e7',
          },          
        }
      },
      // ... novos configs aqui
    ],

    chartInstances: {},

    init() {
      this.$watch('tab', tab => {
        if (tab !== 'mensal') return;

        this.chartConfigs.forEach(cfg => {
          this.$nextTick(() => {
            const container = this.$refs[cfg.ref];
            const inst      = this.chartInstances[cfg.key];
            const data      = this[cfg.dataKey].map(v => Math.round(v));
            const labels    = this[cfg.labelKey];

            // mergia baseOptions + série + categorias
            const options = {
              ...cfg.baseOptions,
              series: [{ name: cfg.title, data }],
              xaxis: { categories: labels }
            };

            if (!inst) {
              this.chartInstances[cfg.key] = new ApexCharts(container, options);
              this.chartInstances[cfg.key].render();
            } else {
              inst.updateOptions(options);
            }
          });
        });
      });
    }
  }));
});
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
