<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../auth.php';


require_once __DIR__ . '/../../vendor/autoload.php';

if (isset($_GET['limpar']) && $_GET['limpar'] == '1') {
    $tmpFile = __DIR__ . '/tmp_dados.json';
    if (file_exists($tmpFile)) {
        $tempo = filemtime($tmpFile);
        if (time() - $tempo > 60) { // só apaga se tiver +60 segundos
            unlink($tmpFile);
        }
    }
    exit;
}

use PhpOffice\PhpSpreadsheet\IOFactory;

$dados = [];
$mesesDisponiveis = [];
$dadosFiltrados = [];
$mesSelecionado = $_GET['mes'] ?? null;
$palavrasChave = isset($_GET['keywords']) ? explode(',', strtolower($_GET['keywords'])) : [];

function extrairMesesUnicos($colunaDatas) {
    $meses = [];

    foreach ($colunaDatas as $data) {
        if (!$data) continue;
        $timestamp = strtotime(str_replace('/', '-', $data));
        if ($timestamp !== false) {
            $mesFormatado = date('m/Y', $timestamp);
            $meses[$mesFormatado] = true;
        }
    }

    return array_keys($meses);
}

function filtrarPorMesEProduto($dados, $mesFiltro, $keywords, $cabecalho) {
    $filtrados = [];
    $idxProduto = array_search('Produto', $cabecalho);
    $idxData = 0;

    foreach (array_slice($dados, 1) as $linha) {
        $data = $linha[$idxData] ?? '';
        $produto = strtolower($linha[$idxProduto] ?? '');

        $timestamp = strtotime(str_replace('/', '-', $data));
        $mesLinha = $timestamp ? date('m/Y', $timestamp) : '';

        $matchMes = !$mesFiltro || $mesLinha === $mesFiltro;
        $matchProduto = empty($keywords) || array_filter($keywords, fn($palavra) => str_contains($produto, trim($palavra)));

        if ($matchMes && $matchProduto) {
            $filtrados[] = $linha;
        }
    }

    return $filtrados;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo'])) {
    $arquivo = $_FILES['arquivo']['tmp_name'];

    try {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($arquivo);
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($sheet->getRowIterator() as $row) {
            $linha = [];
            foreach ($row->getCellIterator() as $cell) {
                try {
                    $valor = $cell->getFormattedValue();
                } catch (Exception $e) {
                    $valor = '[ERRO DE FÓRMULA]';
                }

                if (is_string($valor) && preg_match('/^="?(.*?)"?$/', $valor, $m)) {
                    $valor = trim($m[1]);
                }

                $linha[] = $valor;
            }
            $dados[] = $linha;
        }

        $colunaDatas = array_column($dados, 0);
        $mesesDisponiveis = extrairMesesUnicos($colunaDatas);
        $dadosFiltrados = filtrarPorMesEProduto($dados, $mesSelecionado, $palavrasChave, $dados[0]);

        file_put_contents(__DIR__ . '/tmp_dados.json', json_encode($dados));
    } catch (Exception $e) {
        echo 'Erro ao processar arquivo: ' . $e->getMessage();
    }
} elseif (file_exists(__DIR__ . '/tmp_dados.json')) {
    $dados = json_decode(file_get_contents(__DIR__ . '/tmp_dados.json'), true);
    $colunaDatas = array_column($dados, 0);
    $mesesDisponiveis = extrairMesesUnicos($colunaDatas);
    $dadosFiltrados = filtrarPorMesEProduto($dados, $mesSelecionado, $palavrasChave, $dados[0]);
}
?>
<!DOCTYPE html>

<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Fechamentos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-900 text-white min-h-screen">
  <div class="flex min-h-screen w-full">
    <aside>
      <?php include __DIR__ . '/../../sidebar.php'; ?>
    </aside>


    <main class="flex-1 p-10 overflow-auto">

      <h1 class="text-2xl font-bold mb-6">Fechamento Bulldog</h1>

      <form method="POST" enctype="multipart/form-data" class="mb-6 space-y-4">
          <input type="file" name="arquivo" accept=".xlsx" required class="text-black bg-gray-600 rounded" />
          <button type="submit" class="btn-acao">Enviar</button>
      </form>

    <!-- Novo Formulário com estilo padronizado -->
    <?php if (!empty($mesesDisponiveis)): ?>
    <form method="GET" class="bg-gray-800 rounded-lg p-6 grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 text-white">
        <div>
            <label for="mes" class="block mb-2 text-sm font-semibold">🗓️ Mês</label>
            <select name="mes" id="mes" class="w-full bg-gray-700 border border-gray-600 rounded-md text-sm p-2">
                <option value="">-- Todos os meses --</option>
                <?php foreach ($mesesDisponiveis as $mes): ?>
                    <option value="<?= htmlspecialchars($mes) ?>" <?= ($mes === $mesSelecionado) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($mes) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="keywords" class="block mb-2 text-sm font-semibold">🔍 Palavras-chave (produto)</label>
            <input type="text" name="keywords" id="keywords"
                   value="<?= htmlspecialchars($_GET['keywords'] ?? 'MAÇARICO, CHARUTO, CINZEIRO, CORTADOR') ?>"
                   class="w-full bg-gray-700 border border-gray-600 rounded-md text-sm p-2"
                   placeholder="ex: fitzgerald, gin, spritz">
        </div>
        <div class="md:col-span-2 flex justify-end items-end">
            <button type="submit" class="btn-acao">Aplicar Filtros</button>
        </div>
    </form>
    <?php endif; ?>

    <?php if (!empty($dadosFiltrados)): ?>
        <button
            onclick="document.getElementById('tabela-detalhes').classList.toggle('hidden')"
            class="btn-acao mb-4"
        >
            🔽 Mostrar/Esconder Detalhes
        </button>

        <div id="tabela-detalhes" class="overflow-auto hidden transition-all duration-300 ease-in-out">
            <table class="table-auto w-full text-sm text-left border border-gray-700">
                <thead class="bg-gray-700 text-white sticky top-0 z-10">
                    <tr>
                        <?php
                        $cabecalho = $dados[0];
                        $indices = [];
                        $colunasDesejadas = ['Data', 'Produto', 'Preço', 'Qtde. total', 'Total'];

                        foreach ($colunasDesejadas as $coluna) {
                            $idx = array_search($coluna, $cabecalho);
                            if ($idx !== false) {
                                $indices[$coluna] = $idx;
                                echo "<th class='px-4 py-2 border bg-gray-700'>" . htmlspecialchars($coluna) . "</th>";
                            }
                        }
                        ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dadosFiltrados as $linha): ?>
                        <tr class="border-t border-gray-600">
                            <?php foreach ($indices as $idx): ?>
                                <td class="px-4 py-1 border"><?= htmlspecialchars($linha[$idx] ?? '') ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <hr class="divider_yellow my-6">

        <!-- RESUMO POR PRODUTO -->
        <div class="mt-10 card1 no-hover p-6">
            <h2 class="text-xl font-semibold mb-4">📊 Resumo por Produto</h2>
            <div class="overflow-auto">
                <table class="table-auto text-sm text-left border border-gray-700 w-full">
                    <thead class="bg-gray-700 text-white">
                        <tr>
                            <th class="px-4 py-2 border">Produto</th>
                            <th class="px-4 py-2 border">Preço</th>
                            <th class="px-4 py-2 border">Soma Qtde. total</th>
                            <th class="px-4 py-2 border">Soma Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $resumo = [];

                        foreach ($dadosFiltrados as $linha) {
                            $produto = $linha[$indices['Produto']] ?? '';
                            $preco = floatval(str_replace(',', '.', $linha[$indices['Preço']] ?? 0));
                            $qtde = intval($linha[$indices['Qtde. total']] ?? 0);
                            $total = floatval(str_replace(',', '.', $linha[$indices['Total']] ?? 0));

                            if (!isset($resumo[$produto])) {
                                $resumo[$produto] = ['preco' => $preco, 'qtde' => 0, 'total' => 0];
                            }

                            $resumo[$produto]['qtde'] += $qtde;
                            $resumo[$produto]['total'] += $total;
                        }

                        $totalGeralQtde = 0;
                        $totalGeralValor = 0;

                        foreach ($resumo as $produto => $info) {
                            echo "<tr class='border-t border-gray-600'>";
                            echo "<td class='px-4 py-1 border'>" . htmlspecialchars($produto) . "</td>";
                            echo "<td class='px-4 py-1 border'>R$ " . number_format($info['preco'], 2, ',', '.') . "</td>";
                            echo "<td class='px-4 py-1 border'>" . $info['qtde'] . "</td>";
                            echo "<td class='px-4 py-1 border'>R$ " . number_format($info['total'], 2, ',', '.') . "</td>";
                            echo "</tr>";

                            $totalGeralQtde += $info['qtde'];
                            $totalGeralValor += $info['total'];
                        }

                        echo "<tr class='bg-gray-800 font-bold border-t border-gray-500'>";
                        echo "<td class='px-4 py-2 border text-right' colspan='2'>TOTAL GERAL</td>";
                        echo "<td class='px-4 py-2 border text-white'>" . $totalGeralQtde . "</td>";
                        echo "<td class='px-4 py-2 border text-white'>R$ " . number_format($totalGeralValor, 2, ',', '.') . "</td>";
                        echo "</tr>";

                        echo "<tr class='bg-gray-800 font-bold border-t border-gray-500'>";
                        echo "<td class='px-4 py-2 border text-right' colspan='3'>REPASSE (85%)</td>";
                        echo "<td class='px-4 py-2 border text-white'>R$ " . number_format($totalGeralValor * 0.85, 2, ',', '.') . "</td>";
                        echo "</tr>";
                        ?>
                    </tbody>
                </table>
            </div>
            <button onclick="copiarResumoEmail()" class="mt-4 btn-acao">📋 Copiar para E-mail</button>
        </div>
    <?php endif; ?>
    
<script>
window.addEventListener("unload", function () {
    navigator.sendBeacon(window.location.pathname + "?limpar=1");
});
</script>

</body>

<script>
function copiarResumoEmail() {
    const tabelaOriginal = document.querySelector('div.mt-10 table');
    const tabela = tabelaOriginal.cloneNode(true);

    const estiloHeader = "background-color:#f3f4f6;font-weight:bold;border:1px solid #ccc;padding:6px;";
    const estiloCelula = "border:1px solid #ccc;padding:6px;color:#333;";
    const estiloTotal = "background-color:#e5e7eb;font-weight:bold;border:1px solid #ccc;padding:6px;color:#111;";

    tabela.removeAttribute("class");
    const linhas = tabela.querySelectorAll("tr");
    linhas.forEach((linha, i) => {
        const celulas = linha.children;
        for (const celula of celulas) {
            celula.removeAttribute("class");
            celula.removeAttribute("style");

            if (i === 0) {
                celula.setAttribute("style", estiloHeader);
            } else if (linha.innerText.includes("TOTAL GERAL") || linha.innerText.includes("REPASSE")) {
                celula.setAttribute("style", estiloTotal);
            } else {
                celula.setAttribute("style", estiloCelula);
            }
        }
    });

    const container = document.createElement('div');
    container.appendChild(tabela);
    document.body.appendChild(container);

    const range = document.createRange();
    range.selectNode(tabela);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
    document.execCommand('copy');
    document.body.removeChild(container);

    alert("Resumo copiado!");
}
</script>
</html>
