<?php
require_once __DIR__ . '/../../auth.php';
$pdoMain = $pdo;
require_once '../../config/db.php';
$pdoMetas = $pdo;

// Filtros
$anoAtual = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');
$mesAtual = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('m');

// Buscar tipos de metas
$stmt = $pdoMetas->query("SELECT * FROM metas_tipos WHERE ativo = 1 ORDER BY nome");
$tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar vendedores
$stmt = $pdoMain->query("SELECT id, nome FROM vendedores ORDER BY nome");
$vendedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar metas existentes
$stmt = $pdoMetas->prepare("SELECT * FROM metas_valores WHERE ano = ? AND mes = ?");
$stmt->execute([$anoAtual, $mesAtual]);
$metasExistentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$metasMap = [];
foreach ($metasExistentes as $meta) {
    $metasMap[$meta['id_vendedor']][$meta['id_tipo']] = $meta['valor'];
}

$anoFiltro = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

// Inicializar array para armazenar os dados do gráfico
$dadosGrafico = [];

// Obter os tipos de metas ativos
$stmtTipos = $pdoMetas->query("SELECT id, nome FROM metas_tipos WHERE ativo = 1 ORDER BY nome");
$tiposMetas = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

// Inicializar os dados do gráfico com zeros para cada mês
foreach ($tiposMetas as $tipo) {
    $dadosGrafico[$tipo['id']] = [
        'nome' => $tipo['nome'],
        'valores' => array_fill(1, 12, 0)
    ];
}

// Consultar os valores das metas por tipo e mês
$stmtValores = $pdoMetas->prepare("
    SELECT id_tipo, mes, SUM(valor) as total
    FROM metas_valores
    WHERE ano = ?
    GROUP BY id_tipo, mes
");
$stmtValores->execute([$anoFiltro]);
$resultados = $stmtValores->fetchAll(PDO::FETCH_ASSOC);

// Preencher os dados do gráfico com os valores obtidos
foreach ($resultados as $linha) {
    $idTipo = $linha['id_tipo'];
    $mes = (int) $linha['mes'];
    $total = (float) $linha['total'];
    if (isset($dadosGrafico[$idTipo])) {
        $dadosGrafico[$idTipo]['valores'][$mes] = $total;
    }
}

// Preparar os dados para o ApexCharts
$seriesPorTipo = [];
foreach ($dadosGrafico as $id => $tipo) {
    $seriesPorTipo[$id] = [
        'nome' => $tipo['nome'],
        'data' => array_values($tipo['valores'])
    ];
}
$seriesJson = json_encode($seriesPorTipo);

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestão de Metas</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../assets/css/style.css">

</head>
<body class="bg-gray-900 text-white">
  <div class="flex h-screen">
    <?php include __DIR__ . '/../../sidebar.php'; ?>
    <main class="flex-1 p-6 overflow-auto">
      <div class="flex justify-between items-center mb-4">
        <div>
          <details class="bg-gray-800 border border-gray-700 rounded p-4">
            <summary class="cursor-pointer text-white font-semibold mb-2">🛠 Tipos de Metas</summary>
            <ul class="mb-4 list-disc list-inside text-sm text-gray-200">
              <?php foreach ($tipos as $tipo): ?>
                <li><?= htmlspecialchars($tipo['nome']) ?> (ID <?= $tipo['id'] ?>)</li>
              <?php endforeach; ?>
            </ul>

            <form action="nova_meta_tipo.php" method="post" class="flex gap-2 items-center">
              <input type="text" name="nome" placeholder="Nova meta..." required class="bg-gray-900 border border-gray-600 rounded px-2 py-1 text-sm text-white" />
              <button type="submit" class="btn-acao-verde">+ Nova Meta</button>
            </form>
          </details>
        </div>
      </div>
      <!-- Filtro de Ano -->
      <form method="get" class="flex items-center gap-4 mb-6 bg-gray-800 p-4 rounded">
        <label class="text-sm">
          Ano:
          <select name="ano" class="ml-2 bg-gray-700 border border-gray-600 text-white p-1 rounded">
            <?php for ($a = date('Y') - 2; $a <= date('Y') + 1; $a++): ?>
              <option value="<?= $a ?>" <?= $anoFiltro === $a ? 'selected' : '' ?>><?= $a ?></option>
            <?php endfor; ?>
          </select>
        </label>
        <button type="submit" class="btn-acao-azul ml-auto">Filtrar</button>
      </form>

      <!-- Vários gráficos por tipo -->
      <?php foreach ($seriesPorTipo as $id => $serie): ?>
        <div class="mb-10">
          <h2 class="text-lg font-semibold text-white mb-2"><?= htmlspecialchars($serie['nome']) ?></h2>
          <div id="graficoTipo<?= $id ?>"></div>
        </div>
      <?php endforeach; ?>

      <!-- Script para Renderizar o Gráfico -->
      <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          const categorias = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
          const dadosSeries = <?= json_encode($seriesPorTipo) ?>;
          Object.entries(dadosSeries).forEach(([id, serie]) => {
            const options = {
              chart: {
                type: 'line',
                height: 300,
                background: 'transparent',
                zoom: {
                  enabled: false,
                  }
              },
              series: [{
                name: serie.nome,
                data: serie.data
              }],
              xaxis: {
                categories: categorias,
                labels: { style: { colors: '#cbd5e1' } }
              },
              yaxis: {
                labels: { style: { colors: '#cbd5e1' } }
              },
              stroke: {
                curve: 'smooth',
                width: 2
              },
              tooltip: {
                theme: 'dark',
                y: {
                  formatter: val => new Intl.NumberFormat('pt-BR', {
                    style: 'currency',
                    currency: 'BRL'
                  }).format(val)
                }
              },
              grid: {
                borderColor: '#334155'
              },
              colors: ['#22d3ee']
            };

            new ApexCharts(document.querySelector("#graficoTipo" + id), options).render();
          });
        });
      </script>
      <form method="get" class="flex flex-wrap items-center gap-4 mb-6 bg-gray-800 p-4 rounded">
        <label class="text-sm">
          Mês:
          <select name="mes" class="ml-2 bg-gray-700 border border-gray-600 text-white p-1 rounded">
            <?php for ($m = 1; $m <= 12; $m++): ?>
              <option value="<?= $m ?>" <?= $mesAtual === $m ? 'selected' : '' ?>><?= str_pad((string)$m, 2, '0', STR_PAD_LEFT) ?></option>
            <?php endfor; ?>
          </select>
        </label>
        <label class="text-sm">
          Ano:
          <select name="ano" class="ml-2 bg-gray-700 border border-gray-600 text-white p-1 rounded">
            <?php for ($a = date('Y') - 2; $a <= date('Y') + 1; $a++): ?>
              <option value="<?= $a ?>" <?= $anoAtual === $a ? 'selected' : '' ?>><?= $a ?></option>
            <?php endfor; ?>
          </select>
        </label>
        <button type="submit" class="ml-auto btn-acao-azul">Filtrar</button>
      </form>

      <h1 class="text-2xl text-yellow-400 font-semibold mb-4">Metas por Vendedor (<?= str_pad((string)$mesAtual, 2, '0', STR_PAD_LEFT) ?>/<?= $anoAtual ?>)</h1>

      <div class="overflow-auto">
        <table class="w-full text-sm bg-gray-800 rounded shadow">
          <thead>
            <tr class="bg-gray-700">
              <th class="p-2 text-left text-yellow-400 font-bold">Vendedor</th>
              <?php foreach ($tipos as $tipo): ?>
                <th class="p-2 text-left text-yellow-400 font-bold"><?= htmlspecialchars($tipo['nome']) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($vendedores as $vend): ?>
              <tr class="border-t border-gray-600">
                <td class="p-2 font-medium text-white"><?= htmlspecialchars($vend['nome']) ?></td>
                <?php foreach ($tipos as $tipo): 
                  $valor = $metasMap[$vend['id']][$tipo['id']] ?? 0;
                ?>
                  <td class="p-2">
                    <input type="number" step="0.01" class="bg-gray-900 border border-gray-600 rounded px-2 py-1 w-24" 
                      data-vendedor="<?= $vend['id'] ?>" 
                      data-tipo="<?= $tipo['id'] ?>"
                      value="<?= number_format($valor, 2, '.', '') ?>"
                      onchange="salvarMeta(this)" />
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <script>
        async function salvarMeta(input) {
          const id_vendedor = input.dataset.vendedor;
          const id_tipo = input.dataset.tipo;
          const valor = input.value;

          input.disabled = true;
          const res = await fetch('salvar_meta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_vendedor, id_tipo, valor, ano: <?= $anoAtual ?>, mes: <?= $mesAtual ?> })
          });
          input.disabled = false;

          if (res.ok) {
            input.classList.remove('border-red-500');
            input.classList.add('border-green-500');
          } else {
            input.classList.add('border-red-500');
          }
        }
      </script>
    </main>
  </div>
</body>
</html>