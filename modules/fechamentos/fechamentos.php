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

// === Início Fechamento Choripan ===
// 1. Captura os rebates (aceita vírgula ou ponto)
$rawWelt      = str_replace(',', '.', $_POST['rebate_welt']      ?? '');
$rawEspecial  = str_replace(',', '.', $_POST['rebate_especiais'] ?? '');
$rebateWelt      = filter_var($rawWelt,     FILTER_VALIDATE_FLOAT) ?: 1.00;
$rebateEspeciais = filter_var($rawEspecial, FILTER_VALIDATE_FLOAT) ?: 0.50;

// 2. Carrega ou processa o arquivo
$dadosChoripan = [];
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['formulario'] ?? '') === 'choripan'
) {
    // se veio upload novo, parseia e salva cache
    if (!empty($_FILES['arquivoChoripan']['tmp_name'])) {
        $tmpCh = $_FILES['arquivoChoripan']['tmp_name'];
        try {
            $readerCh = IOFactory::createReader('Xlsx');
            $readerCh->setReadDataOnly(true);
            $spreadsheetCh = $readerCh->load($tmpCh);
            $sheetCh = $spreadsheetCh->getActiveSheet();

            $cabecalhoCh  = [];
            $idxProdutoCh = $idxClienteCh = null;

            foreach ($sheetCh->getRowIterator() as $i => $rowCh) {
                // pula as 4 primeiras linhas
                if ($i < 4) {
                    continue;
                }
                $linhaCh = [];
                foreach ($rowCh->getCellIterator() as $cellCh) {
                    try {
                        $valorCh = $cellCh->getFormattedValue();
                    } catch (Exception $eCh) {
                        $valorCh = '[ERRO DE FÓRMULA]';
                    }
                    if (is_string($valorCh) && preg_match('/^="?(.+?)"?$/', $valorCh, $mCh)) {
                        $valorCh = trim($mCh[1]);
                    }
                    $linhaCh[] = $valorCh;
                }

                // identifica cabeçalho e índices
                if (empty($cabecalhoCh)) {
                    $cabecalhoCh   = $linhaCh;
                    $idxProdutoCh  = array_search('Produto', $cabecalhoCh, true);
                    $idxClienteCh  = array_search('Cliente', $cabecalhoCh, true);
                    $dadosChoripan[] = $cabecalhoCh;
                    continue;
                }

                // filtra linha: pula se PRODUTO **ou** CLIENTE vazios
                $produtoVazio = ($idxProdutoCh !== false && trim($linhaCh[$idxProdutoCh] ?? '') === '');
                $clienteVazio = ($idxClienteCh !== false && trim($linhaCh[$idxClienteCh] ?? '') === '');
                if ($produtoVazio || $clienteVazio) {
                    continue;
                }

                $dadosChoripan[] = $linhaCh;
            }

            file_put_contents(__DIR__ . '/tmp_choripan.json', json_encode($dadosChoripan));
        } catch (Exception $eCh) {
            echo 'Erro ao processar Fechamento Choripan: ' . $eCh->getMessage();
        }
    }
    // se não veio arquivo, carrega do cache
    else {
        $dadosChoripan = json_decode(file_get_contents(__DIR__ . '/tmp_choripan.json'), true);
    }

    // 3. Totalizador por Cliente/Produto (só “choripan”)
    $totaisChoripan = [];
    if (!empty($dadosChoripan)) {
        $cabCh      = $dadosChoripan[0];
        $idxCli     = array_search('Cliente',           $cabCh, true);
        $idxProd    = array_search('Produto',           $cabCh, true);
        $idxQuant   = array_search('Quantidade Vendida',$cabCh, true);

        foreach (array_slice($dadosChoripan, 1) as $linha) {
            $cliente = $linha[$idxCli]  ?? '';
            if (stripos($cliente, 'choripan') === false) {
                continue;
            }
            $produto = $linha[$idxProd]  ?? '';
            $quant   = intval($linha[$idxQuant] ?? 0);
            $totaisChoripan[$cliente][$produto] = 
                ($totaisChoripan[$cliente][$produto] ?? 0) + $quant;
        }
    }

    // 4. Cálculo de repasses
    $quantWelt   = 0;
    $quantOutros = 0;
    foreach ($totaisChoripan as $cli => $produtos) {
        foreach ($produtos as $prod => $qtde) {
            // comparação case-insensitive
            if (strcasecmp($prod, 'Welt Pilsen') === 0) {
                $quantWelt += $qtde;
            } else {
                $quantOutros += $qtde;
            }
        }
    }
    $repasseWelt      = $quantWelt   * $rebateWelt;
    $repasseEspeciais = $quantOutros * $rebateEspeciais;
    $totalRepasse     = $repasseWelt + $repasseEspeciais;
}
// === Fim Fechamento Choripan ===

// === Início Fechamento GOD Save ===
$totaisGodSave = [];
$litrosBastards = 0;

$litrosEspeciais = 0;
$percentualBonificacao = 30;
$percentualEspeciais = 25;
$bonificacaoBastards = 0;
$bonificacaoEspeciais = 0;


if (!empty($dadosChoripan)) {
    $cabCh      = $dadosChoripan[0];
    $idxCli     = array_search('Cliente',           $cabCh, true);
    $idxProd    = array_search('Produto',           $cabCh, true);
    $idxQuant   = array_search('Quantidade Vendida',$cabCh, true);


    foreach (array_slice($dadosChoripan, 1) as $linha) {
        $cliente = $linha[$idxCli]  ?? '';
        if (stripos($cliente, 'god save') === false) {
            continue;
        }
        $tipo = $linha[$idxTipo]  ?? '';
        if (strcasecmp($tipo, 'Chopp') !== 0) {
            continue;
        }

        $produto = $linha[$idxProd]  ?? '';
        $quant   = intval($linha[$idxQuant] ?? 0);

        $totaisGodSave[$cliente][$produto] =
            ($totaisGodSave[$cliente][$produto] ?? 0) + $quant;

        if (strcasecmp($produto, 'Bastards Pilsen') === 0) {
            $litrosBastards += $quant;
        } else {
            $litrosEspeciais += $quant;
        }
    }

    $bonificacaoBastards  = $litrosBastards  * ($percentualBonificacao / 100);
    $bonificacaoEspeciais = $litrosEspeciais * ($percentualEspeciais / 100);
}

// Expor variáveis
$totaisGodSave         = $totaisGodSave         ?? [];
$litrosBastards        = $litrosBastards        ?? 0;
$litrosEspeciais       = $litrosEspeciais       ?? 0;
$percentualBonificacao = $percentualBonificacao ?? 0;
$percentualEspeciais   = $percentualEspeciais   ?? 0;
$bonificacaoBastards   = $bonificacaoBastards   ?? 0;
$bonificacaoEspeciais  = $bonificacaoEspeciais  ?? 0;

// === Fim Fechamento GOD Save ===

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
                <table id="bulldog" class="table-auto text-sm text-left border border-gray-700 w-full">
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

<h1 class="text-2xl font-bold mb-4">Fechamento GOD</h1>
<div id="card-godsave" class="mt-6 card1 no-hover p-6">
  <div class="flex flex-col md:flex-row gap-4">
    <div>
      <label for="percent_bastards" class="block mb-1 text-sm font-semibold">Bastards Pilsen (%)</label>
      <input
        type="number"
        id="percent_bastards"
        step="0.01"
        value="<?= $percentualBonificacao ?>"
        oninput="atualizarBonificacaoGodSave()"
        class="w-full bg-gray-700 border border-gray-600 rounded-md text-sm p-2"
      />
    </div>
    <div>
      <label for="percent_especiais" class="block mb-1 text-sm font-semibold">Especiais (%)</label>
      <input
        type="number"
        id="percent_especiais"
        step="0.01"
        value="<?= $percentualEspeciais ?>"
        oninput="atualizarBonificacaoGodSave()"
        class="w-full bg-gray-700 border border-gray-600 rounded-md text-sm p-2"
      />
    </div>
  </div>
  <div class="overflow-auto mt-4">
    <table class="table-auto w-full text-sm text-left border border-gray-700 mt-4 mb-4">
      <thead class="bg-gray-700 text-white">
        <tr>
          <th class="px-4 py-2 border">Cliente</th>
          <th class="px-4 py-2 border">Produto</th>
          <th class="px-4 py-2 border">Quantidade vendida</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($totaisGodSave as $cliente => $lista): ?>
          <?php foreach ($lista as $produto => $qtde): ?>
            <tr class="border-t border-gray-600">
              <td class="px-4 py-1 border"><?= htmlspecialchars($cliente) ?></td>
              <td class="px-4 py-1 border"><?= htmlspecialchars($produto) ?></td>
              <td class="px-4 py-1 border"><?= $qtde ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        <tr class="border-t border-gray-600">
          <td class="px-4 py-1 border text-center text-lg font-semibold" colspan="3">
            TOTAL BASTARDS PILSEN <?= $litrosBastards ?> L = R$ <span id="bonificacao-godsave-text"><?= number_format($bonificacaoBastards, 2, ',', '.') ?></span>
          </td>
        </tr>
        <tr class="border-t border-gray-600">
          <td class="px-4 py-1 border text-center text-lg font-semibold" colspan="3">
            TOTAL ESPECIAIS <?= $litrosEspeciais ?> L = R$ <span id="bonificacao-especiais-text"><?= number_format($bonificacaoEspeciais, 2, ',', '.') ?></span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  <button onclick="copiarGodSaveEmail()" class="mt-4 btn-acao">📋 Copiar para E-mail</button>
</div>

<hr class="divider_yellow my-6">

<h1 class="text-2xl font-bold mb-4">Fechamento Choripan</h1>
<form method="POST" enctype="multipart/form-data" class="mb-6 grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
  <input type="hidden" name="formulario" value="choripan" />

  <!-- upload -->
  <div>
    <label for="arquivoChoripan" class="block mb-1 text-sm font-semibold">Arquivo Choripan</label>
    <input
      type="file"
      name="arquivoChoripan"
      id="arquivoChoripan"
      accept=".xlsx"
      class="w-full text-black bg-gray-600 rounded p-2"
    />
  </div>

  <!-- Rebate Welt -->
  <div>
    <label for="rebate_welt" class="block mb-1 text-sm font-semibold">Rebate Welt (R$)</label>
    <input
      type="number"
      name="rebate_welt"
      id="rebate_welt"
      step="0.01"
      value="<?= number_format($rebateWelt, 2, '.', '') ?>"
      class="w-full bg-gray-700 border border-gray-600 rounded-md text-sm p-2"
    />
  </div>

  <!-- Rebate Especiais -->
  <div>
    <label for="rebate_especiais" class="block mb-1 text-sm font-semibold">Rebate Especiais (R$)</label>
    <input
      type="number"
      name="rebate_especiais"
      id="rebate_especiais"
      step="0.01"
      value="<?= number_format($rebateEspeciais, 2, '.', '') ?>"
      class="w-full bg-gray-700 border border-gray-600 rounded-md text-sm p-2"
    />
  </div>

  <!-- botão -->
  <div>
    <button type="submit" class="btn-acao w-full">Enviar XLSX | Atualizar</button>
  </div>
  
  <!-- Botão de copiar para e-mail -->
  <button
    onclick="copiarChoripanEmail()" class="mt-4 btn-acao">
    📋 Copiar para E-mail
  </button>
</form>

<?php if (!empty($totaisChoripan)): ?>
  <div id="card-choripan" class="mt-6 card1 no-hover p-6">
    <div class="overflow-auto">
      <table class="table-auto w-full text-sm text-left border border-gray-700 mt-4 mb-4">
        <thead class="bg-gray-700 text-white">
          <tr>
            <th class="px-4 py-2 border">Cliente</th>
            <th class="px-4 py-2 border">Produto</th>
            <th class="px-4 py-2 border">Quantidade vendida</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($totaisChoripan as $cliente => $lista): ?>
            <?php foreach ($lista as $produto => $qtde): ?>
              <tr class="border-t border-gray-600">
                <td class="px-4 py-1 border"><?= htmlspecialchars($cliente) ?></td>
                <td class="px-4 py-1 border"><?= htmlspecialchars($produto) ?></td>
                <td class="px-4 py-1 border"><?= $qtde ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
          <tr class="border-t border-gray-600-600">
            <td class="px-4 py-1 border text-center text-lg font-semibold" colspan='3'>
              TOTAL REPASSE R$ <?= number_format($repasseWelt,      2, ',', '.') ?>
              + R$ <?= number_format($repasseEspeciais, 2, ',', '.') ?>
              = R$ <?= number_format($totalRepasse,     2, ',', '.') ?>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

  </div>
<?php endif; ?>

</body>

<script>
const litrosBastards = <?= $litrosBastards ?>;
const litrosEspeciais = <?= $litrosEspeciais ?>;


function copiarChoripanEmail() {
    const cardOriginal = document.getElementById('card-choripan');
    const card = cardOriginal.cloneNode(true);

    // estilos idênticos aos do copiarResumoEmail
    const estiloHeader = "background-color:#3b568c;font-weight:bold;border:1px solid #ccc;padding:8px;font-size:14px;";
    const estiloCelula = "border:1px solid #ccc;padding:8px;color:#333;font-size:12px;";
    const estiloTotal  = "background-color:#e5e7eb;font-weight:bold;border:1px solid #ccc;padding:8px;color:#111111;font-size:12px;";

    // percorre todas as tabelas dentro do card
    card.querySelectorAll('table').forEach(table => {
        table.removeAttribute('class');
        const linhas = table.querySelectorAll('tr');
        linhas.forEach((linha, i) => {
            const celulas = linha.children;
            for (const celula of celulas) {
                celula.removeAttribute('class');
                celula.removeAttribute('style');
                if (i === 0) {
                    // cabeçalho
                    celula.setAttribute("style", estiloHeader);
                } else if (
                    linha.innerText.includes("TOTAL GERAL") ||
                    linha.innerText.includes("REPASSE")
                ) {
                    // se por acaso tiver alguma linha de totalização
                    celula.setAttribute("style", estiloTotal);
                } else {
                    // todas as outras células
                    celula.setAttribute("style", estiloCelula);
                }
            }
        });
    });
    

    // clona o card para fora da tela, copia e limpa
    const container = document.createElement('div');
    container.style.position = 'absolute';
    container.style.left = '-9999px';
    container.appendChild(card);
    document.body.appendChild(container);

    const range = document.createRange();
    range.selectNode(container);
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
    document.execCommand('copy');
    sel.removeAllRanges();

    document.body.removeChild(container);
    alert('Conteúdo do Choripan copiado! Agora é só colar no e-mail.');
}

function copiarGodSaveEmail() {
    const cardOriginal = document.getElementById('card-godsave');
    const card = cardOriginal.cloneNode(true);

    card.querySelectorAll('input, button').forEach(el => el.remove());

    const estiloHeader = "background-color:#3b568c;font-weight:bold;border:1px solid #ccc;padding:8px;font-size:14px;";
    const estiloCelula = "border:1px solid #ccc;padding:8px;color:#333;font-size:12px;";
    const estiloTotal  = "background-color:#e5e7eb;font-weight:bold;border:1px solid #ccc;padding:8px;color:#111111;font-size:12px;";

    card.querySelectorAll('table').forEach(table => {
        table.removeAttribute('class');
        const linhas = table.querySelectorAll('tr');
        linhas.forEach((linha, i) => {
            const celulas = linha.children;
            for (const celula of celulas) {
                celula.removeAttribute('class');
                celula.removeAttribute('style');
                if (i === 0) {
                    celula.setAttribute("style", estiloHeader);
                } else if (
                    linha.innerText.includes("TOTAL BASTARDS") ||
                    linha.innerText.includes("TOTAL ESPECIAIS")

                ) {
                    celula.setAttribute("style", estiloTotal);
                } else {
                    celula.setAttribute("style", estiloCelula);
                }
            }
        });
    });

    const container = document.createElement('div');
    container.style.position = 'absolute';
    container.style.left = '-9999px';
    container.appendChild(card);
    document.body.appendChild(container);

    const range = document.createRange();
    range.selectNode(container);
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
    document.execCommand('copy');
    sel.removeAllRanges();

    document.body.removeChild(container);
    alert('Conteúdo do God Save copiado! Agora é só colar no e-mail.');
}

function atualizarBonificacaoGodSave() {
    const pctB = parseFloat(document.getElementById('percent_bastards')?.value) || 0;
    const pctE = parseFloat(document.getElementById('percent_especiais')?.value) || 0;
    const bonB = litrosBastards * (pctB / 100);
    const bonE = litrosEspeciais * (pctE / 100);
    const spanB = document.getElementById('bonificacao-godsave-text');
    const spanE = document.getElementById('bonificacao-especiais-text');
    if (spanB) {
        spanB.textContent = bonB.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    if (spanE) {
        spanE.textContent = bonE.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
}

document.getElementById('percent_bastards') && atualizarBonificacaoGodSave();

</script>


<script>
function copiarResumoEmail() {
    const tabelaOriginal = document.getElementById('bulldog');
    const tabela = tabelaOriginal.cloneNode(true);

    const estiloHeader = "background-color:#3b568c;font-weight:bold;border:1px solid #ccc;padding:6px;font-size:14px;";
    const estiloCelula = "border:1px solid #ccc;padding:6px;color:#333;background-color:#fff;font-size:12px;";
    const estiloTotal = "background-color:#e5e7eb;font-weight:bold;border:1px solid #ccc;padding:6px;color:#111;font-size:12px;";

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
