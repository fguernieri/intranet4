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
$stmt = $pdoMain->query("SELECT id, nome FROM vendedores WHERE ativo = 1 ORDER BY nome");
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

// CLONAGEM DE METAS - PDO IMPLEMENTAÇÃO
if(isset($_GET['action']) && $_GET['action'] == 'clonar') {
  $mes_origem = $_GET['mes_origem'];
  $mes_destino = $_GET['mes_destino'];
  $ano = date('Y'); // ajuste conforme sua lógica se necessário

  // Excluir metas existentes do mês destino
  $stmtDelete = $pdoMetas->prepare("DELETE FROM metas_valores WHERE mes = ? AND ano = ?");
  $stmtDelete->execute([$mes_destino, $ano]);

  // Buscar metas do mês origem
  $stmtSelect = $pdoMetas->prepare("SELECT id_vendedor, id_tipo, valor FROM metas_valores WHERE mes = ? AND ano = ?");
  $stmtSelect->execute([$mes_origem, $ano]);
  $metas = $stmtSelect->fetchAll(PDO::FETCH_ASSOC);

  // Inserir metas clonadas no mês destino
  $stmtInsert = $pdoMetas->prepare("INSERT INTO metas_valores (id_vendedor, id_tipo, valor, ano, mes) VALUES (?, ?, ?, ?, ?)");
  foreach ($metas as $meta) {
    $stmtInsert->execute([$meta['id_vendedor'], $meta['id_tipo'], $meta['valor'], $ano, $mes_destino]);
  }

  echo json_encode(['message' => 'Metas clonadas com sucesso.']);
  exit;
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestão de Metas</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../assets/css/style.css">
  <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
  <style>
    .btn-acao-cinza {
      background-color: #4b5563;
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 0.375rem;
      font-weight: 500;
      transition: background-color 0.2s;
    }
    .btn-acao-cinza:hover {
      background-color: #374151;
    }
  </style>
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
        <button type="submit" class="ml-2 btn-acao-azul">Filtrar</button>
        <!-- FRONTEND SELECTOR -->
        <p class="ml-auto">Selecione o mês para clonar as metas:</p>
        <select id="mes_origem_selector" class="bg-gray-700 border border-gray-600 text-white p-1 rounded ml-2">
          <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>"><?= str_pad((string)$m, 2, '0', STR_PAD_LEFT) ?></option>
          <?php endfor; ?>
        </select>
        <button onclick="clonarMetas()" class="btn-acao-azul">
          Clonar Metas
        </button>
      </form>

      <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl text-yellow-400 font-semibold">Metas por Vendedor (<?= str_pad((string)$mesAtual, 2, '0', STR_PAD_LEFT) ?>/<?= $anoAtual ?>)</h1>
        <button id="btnExportarPNG" class="btn-acao-verde flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          Exportar PNG
        </button>
      </div>

      <div class="overflow-auto" id="tabelaMetas">
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
        function clonarMetas() {
          if(confirm('Tem certeza que deseja clonar as metas do mês selecionado? Isso substituirá as metas atuais do mês destino.')) {
            const mesOrigem = document.getElementById('mes_origem_selector').value;
            const mesDestino = document.querySelector('select[name=mes]').value;
            fetch('metas.php?action=clonar&mes_origem=' + mesOrigem + '&mes_destino=' + mesDestino)
            .then(response => response.json())
            .then(data => {
              alert(data.message);
              location.reload();
            });
          }
        }
      </script>
    </main>
  </div>
  <!-- Modal de seleção de vendedores -->
  <div id="modalSelecaoVendedores" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-gray-800 p-6 rounded-lg shadow-lg w-full max-w-md">
      <h2 class="text-xl text-yellow-400 font-semibold mb-4">Selecione os Vendedores</h2>
      
      <div class="mb-4">
        <div class="flex justify-between mb-2">
          <button id="btnSelecionarTodos" class="text-sm text-blue-400 hover:underline">Selecionar Todos</button>
          <button id="btnLimparSelecao" class="text-sm text-red-400 hover:underline">Limpar Seleção</button>
        </div>
        <div class="max-h-60 overflow-y-auto p-2 bg-gray-900 rounded">
          <?php foreach ($vendedores as $vend): ?>
            <div class="flex items-center mb-2">
              <input type="checkbox" id="vend-<?= $vend['id'] ?>" value="<?= $vend['id'] ?>" class="checkbox-vendedor mr-2">
              <label for="vend-<?= $vend['id'] ?>" class="text-white"><?= htmlspecialchars($vend['nome']) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      
      <div class="flex justify-end gap-2">
        <button id="btnCancelarExportacao" class="btn-acao-cinza">Cancelar</button>
        <button id="btnConfirmarExportacao" class="btn-acao-verde">Exportar</button>
      </div>
    </div>
  </div>

  <script>
    // Manipulação do modal
    const modal = document.getElementById('modalSelecaoVendedores');
    const btnExportarPNG = document.getElementById('btnExportarPNG');
    const btnCancelarExportacao = document.getElementById('btnCancelarExportacao');
    const btnConfirmarExportacao = document.getElementById('btnConfirmarExportacao');
    const btnSelecionarTodos = document.getElementById('btnSelecionarTodos');
    const btnLimparSelecao = document.getElementById('btnLimparSelecao');
    const checkboxesVendedores = document.querySelectorAll('.checkbox-vendedor');
    
    // Abrir modal
    btnExportarPNG.addEventListener('click', () => {
      modal.classList.remove('hidden');
    });
    
    // Fechar modal
    btnCancelarExportacao.addEventListener('click', () => {
      modal.classList.add('hidden');
    });
    
    // Selecionar todos
    btnSelecionarTodos.addEventListener('click', () => {
      checkboxesVendedores.forEach(checkbox => {
        checkbox.checked = true;
      });
    });
    
    // Limpar seleção
    btnLimparSelecao.addEventListener('click', () => {
      checkboxesVendedores.forEach(checkbox => {
        checkbox.checked = false;
      });
    });
    
    // Exportar para PNG
    btnConfirmarExportacao.addEventListener('click', async () => {
      // Obter IDs dos vendedores selecionados
      const vendedoresSelecionados = Array.from(checkboxesVendedores)
        .filter(checkbox => checkbox.checked)
        .map(checkbox => checkbox.value);
      
      if (vendedoresSelecionados.length === 0) {
        alert('Selecione pelo menos um vendedor para exportar.');
        return;
      }
      
      // Criar uma tabela temporária apenas com os vendedores selecionados
      const tabelaOriginal = document.querySelector('#tabelaMetas table');
      const tabelaTemp = tabelaOriginal.cloneNode(true);
      
      // Manter apenas o cabeçalho e as linhas dos vendedores selecionados
      const linhas = tabelaTemp.querySelectorAll('tbody tr');
      linhas.forEach(linha => {
        const idVendedor = linha.querySelector('input').dataset.vendedor;
        if (!vendedoresSelecionados.includes(idVendedor)) {
          linha.remove();
        }
      });
      
      // Criar um elemento temporário para renderizar a tabela
      const tempDiv = document.createElement('div');
      tempDiv.style.position = 'absolute';
      tempDiv.style.left = '-9999px';
      tempDiv.style.background = '#1f2937'; // Fundo escuro
      tempDiv.style.padding = '20px';
      tempDiv.style.borderRadius = '8px';
      
      // Adicionar título
      const titulo = document.createElement('h2');
      titulo.textContent = `Metas por Vendedor (${String(<?= $mesAtual ?>).padStart(2, '0')}/<?= $anoAtual ?>)`;
      titulo.style.color = '#fbbf24'; // Amarelo
      titulo.style.fontSize = '20px';
      titulo.style.fontWeight = 'bold';
      titulo.style.marginBottom = '16px';
      
      tempDiv.appendChild(titulo);
      tempDiv.appendChild(tabelaTemp);
      document.body.appendChild(tempDiv);
      
      try {
        // Capturar a tabela como imagem
        const canvas = await html2canvas(tempDiv, {
          backgroundColor: '#1f2937',
          scale: 2 // Melhor qualidade
        });
        
        // Converter para PNG e fazer download
        const dataUrl = canvas.toDataURL('image/png');
        const link = document.createElement('a');
        link.href = dataUrl;
        link.download = `Metas_Vendedores_${String(<?= $mesAtual ?>).padStart(2, '0')}_<?= $anoAtual ?>.png`;
        link.click();
        
        // Fechar o modal
        modal.classList.add('hidden');
      } catch (error) {
        console.error('Erro ao exportar para PNG:', error);
        alert('Ocorreu um erro ao exportar para PNG. Por favor, tente novamente.');
      } finally {
        // Remover o elemento temporário
        document.body.removeChild(tempDiv);
      }
    });
  </script>
</body>
</html>