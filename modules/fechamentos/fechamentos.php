<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// ====== util: limpar cache antigo ao sair ======
if (isset($_GET['limpar']) && $_GET['limpar'] === '1') {
    $tmpFiles = [
        __DIR__ . '/tmp_dados.json',
        __DIR__ . '/tmp_choripan.json',
    ];
    foreach ($tmpFiles as $tmpFile) {
        if (file_exists($tmpFile)) {
            $tempo = filemtime($tmpFile);
            // só apaga se tiver +60 segundos
            if (time() - $tempo > 60) {
                @unlink($tmpFile);
            }
        }
    }
    exit;
}

// ====== estado ======
$dados = [];
$mesesDisponiveis = [];
$dadosFiltrados = [];
$mesSelecionado = $_GET['mes'] ?? null;
$palavrasChave = isset($_GET['keywords']) ? explode(',', strtolower($_GET['keywords'])) : [];

// ====== helpers ======
function extrairMesesUnicos($colunaDatas) {
    $meses = [];
    foreach ($colunaDatas as $data) {
        if (!$data) continue;
        $timestamp = strtotime(str_replace('/', '-', (string)$data));
        if ($timestamp !== false) {
            $mesFormatado = date('m/Y', $timestamp);
            $meses[$mesFormatado] = true;
        }
    }
    return array_values(array_keys($meses));
}

function filtrarPorMesEProduto($dados, $mesFiltro, $keywords, $cabecalho) {
    $filtrados = [];
    $idxProduto = array_search('Produto', $cabecalho, true);
    $idxData = 0; // primeira coluna é Data

    foreach (array_slice($dados, 1) as $linha) {
        $data = $linha[$idxData] ?? '';
        $produto = strtolower((string)($idxProduto !== false ? ($linha[$idxProduto] ?? '') : ''));

        $timestamp = strtotime(str_replace('/', '-', (string)$data));
        $mesLinha = $timestamp ? date('m/Y', $timestamp) : '';

        $matchMes = !$mesFiltro || $mesLinha === $mesFiltro;
        $matchProduto = empty($keywords) || array_filter($keywords, fn($palavra) => str_contains($produto, trim($palavra)));

        if ($matchMes && $matchProduto) {
            $filtrados[] = $linha;
        }
    }

    return $filtrados;
}

// ====== upload principal (Bulldog) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo'])) {
    $arquivo = $_FILES['arquivo']['tmp_name'] ?? '';

    if ($arquivo && is_uploaded_file($arquivo)) {
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

            if (!empty($dados)) {
                $colunaDatas = array_column($dados, 0);
                $mesesDisponiveis = extrairMesesUnicos($colunaDatas);
                $cab = $dados[0] ?? [];
                $dadosFiltrados = !empty($cab) ? filtrarPorMesEProduto($dados, $mesSelecionado, $palavrasChave, $cab) : [];
                file_put_contents(__DIR__ . '/tmp_dados.json', json_encode($dados, JSON_UNESCAPED_UNICODE));
            }
        } catch (Exception $e) {
            echo 'Erro ao processar arquivo: ' . htmlspecialchars($e->getMessage());
        }
    }
} elseif (file_exists(__DIR__ . '/tmp_dados.json')) {
    $dados = json_decode(file_get_contents(__DIR__ . '/tmp_dados.json'), true) ?: [];
    if (!empty($dados)) {
        $colunaDatas = array_column($dados, 0);
        $mesesDisponiveis = extrairMesesUnicos($colunaDatas);
        $cab = $dados[0] ?? [];
        $dadosFiltrados = !empty($cab) ? filtrarPorMesEProduto($dados, $mesSelecionado, $palavrasChave, $cab) : [];
    }
}

// ====== Fechamento Choripan: rebates ======
$rawWelt      = str_replace(',', '.', $_POST['rebate_welt']      ?? '');
$rawEspecial  = str_replace(',', '.', $_POST['rebate_especiais'] ?? '');
$rebateWelt      = filter_var($rawWelt,     FILTER_VALIDATE_FLOAT);
$rebateEspeciais = filter_var($rawEspecial, FILTER_VALIDATE_FLOAT);
$rebateWelt      = ($rebateWelt      !== false) ? (float)$rebateWelt      : 1.00;
$rebateEspeciais = ($rebateEspeciais !== false) ? (float)$rebateEspeciais : 0.50;

// ====== Fechamento Choripan: ingestão / cache ======
$dadosChoripan = [];
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (($_POST['formulario'] ?? '') === 'choripan')
) {
    // upload novo
    if (!empty($_FILES['arquivoChoripan']['tmp_name']) && is_uploaded_file($_FILES['arquivoChoripan']['tmp_name'])) {
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

                // filtra linha: pula se PRODUTO **ou** CLIENTE vazios (se existirem as colunas)
                $produtoVazio = ($idxProdutoCh !== false && $idxProdutoCh !== null) ? (trim($linhaCh[$idxProdutoCh] ?? '') === '') : false;
                $clienteVazio = ($idxClienteCh !== false && $idxClienteCh !== null) ? (trim($linhaCh[$idxClienteCh] ?? '') === '') : false;
                if ($produtoVazio || $clienteVazio) {
                    continue;
                }

                $dadosChoripan[] = $linhaCh;
            }

            file_put_contents(__DIR__ . '/tmp_choripan.json', json_encode($dadosChoripan, JSON_UNESCAPED_UNICODE));
        } catch (Exception $eCh) {
            echo 'Erro ao processar Fechamento Choripan: ' . htmlspecialchars($eCh->getMessage());
        }
    }
    // sem upload: tenta cache
    else {
        $cache = __DIR__ . '/tmp_choripan.json';
        if (is_file($cache)) {
            $dadosChoripan = json_decode(file_get_contents($cache), true) ?: [];
        } else {
            $dadosChoripan = [];
        }
    }
} else {
    // fora do submit choripan, mantém vazio (ou carrega último cache se quiser)
    $cache = __DIR__ . '/tmp_choripan.json';
    if (is_file($cache)) {
        $dadosChoripan = json_decode(file_get_contents($cache), true) ?: [];
    }
}

// ====== Fechamento Choripan: totalização e repasses ======
$totaisChoripan = [];
$repasseWelt = $repasseEspeciais = $totalRepasse = 0.0;

if (!empty($dadosChoripan)) {
    $cabCh      = $dadosChoripan[0] ?? [];
    $idxCli     = array_search('Cliente',            $cabCh, true);
    $idxProd    = array_search('Produto',            $cabCh, true);
    $idxQuant   = array_search('Quantidade Vendida', $cabCh, true);

    foreach (array_slice($dadosChoripan, 1) as $linha) {
        $cliente = ($idxCli   !== false && $idxCli   !== null) ? ($linha[$idxCli]   ?? '') : '';
        if (stripos((string)$cliente, 'choripan') === false) {
            continue;
        }
        $produto = ($idxProd  !== false && $idxProd  !== null) ? ($linha[$idxProd]  ?? '') : '';
        $quant   = ($idxQuant !== false && $idxQuant !== null) ? (int)($linha[$idxQuant] ?? 0) : 0;

        if ($cliente === '' || $produto === '') continue;

        $totaisChoripan[$cliente][$produto] =
            ($totaisChoripan[$cliente][$produto] ?? 0) + $quant;
    }

    // cálculo dos repasses
    $quantWelt   = 0;
    $quantOutros = 0;
    foreach ($totaisChoripan as $cli => $produtos) {
        foreach ($produtos as $prod => $qtde) {
            if (strcasecmp((string)$prod, 'Welt Pilsen') === 0) {
                $quantWelt += (int)$qtde;
            } else {
                $quantOutros += (int)$qtde;
            }
        }
    }
    $repasseWelt      = $quantWelt   * $rebateWelt;
    $repasseEspeciais = $quantOutros * $rebateEspeciais;
    $totalRepasse     = $repasseWelt + $repasseEspeciais;
}

// ====== Fechamento GOD Save ======
$totaisGodSave = [];
$litrosBastards = 0;
$litrosEspeciais = 0;
$percentualBonificacao = 30;
$percentualEspeciais   = 25;
$bonificacaoBastards   = 0;
$bonificacaoEspeciais  = 0;

if (!empty($dadosChoripan)) {
    $cabCh      = $dadosChoripan[0] ?? [];
    $idxCli     = array_search('Cliente',            $cabCh, true);
    $idxProd    = array_search('Produto',            $cabCh, true);
    $idxQuant   = array_search('Quantidade Vendida', $cabCh, true);
    $idxTipo    = array_search('Tipo',               $cabCh, true); // <-- agora definido

    foreach (array_slice($dadosChoripan, 1) as $linha) {
        $cliente = ($idxCli   !== false && $idxCli   !== null) ? ($linha[$idxCli]   ?? '') : '';
        if (stripos((string)$cliente, 'god save') === false) {
            continue;
        }

        // filtra por Tipo=Chopp somente se a coluna existir
        $tipo = ($idxTipo !== false && $idxTipo !== null) ? ($linha[$idxTipo] ?? '') : '';
        if ($idxTipo !== false && $idxTipo !== null) {
            if (strcasecmp((string)$tipo, 'Chopp') !== 0) {
                continue;
            }
        }

        $produto = ($idxProd  !== false && $idxProd  !== null) ? ($linha[$idxProd]  ?? '') : '';
        $quant   = ($idxQuant !== false && $idxQuant !== null) ? (int)($linha[$idxQuant] ?? 0) : 0;

        if ($cliente === '' || $produto === '') continue;

        $totaisGodSave[$cliente][$produto] =
            ($totaisGodSave[$cliente][$produto] ?? 0) + $quant;

        if (strcasecmp((string)$produto, 'Bastards Pilsen') === 0) {
            $litrosBastards += $quant;
        } else {
            $litrosEspeciais += $quant;
        }
    }

    $bonificacaoBastards  = $litrosBastards  * ($percentualBonificacao / 100);
    $bonificacaoEspeciais = $litrosEspeciais * ($percentualEspeciais   / 100);
}

// Expor variáveis (garantindo definidos)
$totaisGodSave         = $totaisGodSave         ?? [];
$litrosBastards        = $litrosBastards        ?? 0;
$litrosEspeciais       = $litrosEspeciais       ?? 0;
$percentualBonificacao = $percentualBonificacao ?? 0;
$percentualEspeciais   = $percentualEspeciais   ?? 0;
$bonificacaoBastards   = $bonificacaoBastards   ?? 0;
$bonificacaoEspeciais  = $bonificacaoEspeciais  ?? 0;

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

      <!-- Filtros -->
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
                        $cabecalho = $dados[0] ?? [];
                        $indices = [];
                        $colunasDesejadas = ['Data', 'Produto', 'Preço', 'Qtde. total', 'Total'];

                        foreach ($colunasDesejadas as $coluna) {
                            $idx = array_search($coluna, $cabecalho, true);
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
                                <td class="px-4 py-1 border"><?= htmlspecialchars((string)($linha[$idx] ?? '')) ?></td>
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
                        $idxProdutoResumo = $indices['Produto'] ?? null;
                        $idxPrecoResumo   = $indices['Preço'] ?? null;
                        $idxQtdeResumo    = $indices['Qtde. total'] ?? null;
                        $idxTotalResumo   = $indices['Total'] ?? null;

                        foreach ($dadosFiltrados as $linha) {
                            $produto = ($idxProdutoResumo !== null) ? ($linha[$idxProdutoResumo] ?? '') : '';
                            $preco   = ($idxPrecoResumo   !== null) ? (float)str_replace(',', '.', (string)($linha[$idxPrecoResumo] ?? 0)) : 0.0;
                            $qtde    = ($idxQtdeResumo    !== null) ? (int)($linha[$idxQtdeResumo] ?? 0) : 0;
                            $total   = ($idxTotalResumo   !== null) ? (float)str_replace(',', '.', (string)($linha[$idxTotalResumo] ?? 0)) : 0.0;

                            if ($produto === '') continue;

                            if (!isset($resumo[$produto])) {
                                $resumo[$produto] = ['preco' => $preco, 'qtde' => 0, 'total' => 0.0];
                            }

                            $resumo[$produto]['qtde']  += $qtde;
                            $resumo[$produto]['total'] += $total;
                        }

                        $totalGeralQtde = 0;
                        $totalGeralValor = 0.0;

                        foreach ($resumo as $produto => $info) {
                            echo "<tr class='border-t border-gray-600'>";
                            echo "<td class='px-4 py-1 border'>" . htmlspecialchars((string)$produto) . "</td>";
                            echo "<td class='px-4 py-1 border'>R$ " . number_format((float)$info['preco'], 2, ',', '.') . "</td>";
                            echo "<td class='px-4 py-1 border'>" . (int)$info['qtde'] . "</td>";
                            echo "<td class='px-4 py-1 border'>R$ " . number_format((float)$info['total'], 2, ',', '.') . "</td>";
                            echo "</tr>";

                            $totalGeralQtde += (int)$info['qtde'];
                            $totalGeralValor += (float)$info['total'];
                        }

                        echo "<tr class='bg-gray-800 font-bold border-t border-gray-500'>";
                        echo "<td class='px-4 py-2 border text-right' colspan='2'>TOTAL GERAL</td>";
                        echo "<td class='px-4 py-2 border text-white'>" . (int)$totalGeralQtde . "</td>";
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

      <!-- GOD SAVE -->
      <h1 class="text-2xl font-bold mb-4">Fechamento GOD</h1>
      <div id="card-godsave" class="mt-6 card1 no-hover p-6">
        <div class="flex flex-col md:flex-row gap-4">
          <div>
            <label for="percent_bastards" class="block mb-1 text-sm font-semibold">Bastards Pilsen (%)</label>
            <input
              type="number"
              id="percent_bastards"
              step="0.01"
              value="<?= htmlspecialchars((string)$percentualBonificacao) ?>"
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
              value="<?= htmlspecialchars((string)$percentualEspeciais) ?>"
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
                    <td class="px-4 py-1 border"><?= htmlspecialchars((string)$cliente) ?></td>
                    <td class="px-4 py-1 border"><?= htmlspecialchars((string)$produto) ?></td>
                    <td class="px-4 py-1 border"><?= (int)$qtde ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
              <tr class="border-t border-gray-600">
                <td class="px-4 py-1 border text-center text-lg font-semibold" colspan="3">
                  TOTAL BASTARDS PILSEN <?= (int)$litrosBastards ?> L = 
                  <span id="bonificacao-godsave-text"><?= number_format((int)$bonificacaoBastards, 0, ',', '.') ?></span> L
                </td>
              </tr>
              <tr class="border-t border-gray-600">
                <td class="px-4 py-1 border text-center text-lg font-semibold" colspan="3">
                  TOTAL ESPECIAIS <?= (int)$litrosEspeciais ?> L = 
                  <span id="bonificacao-especiais-text"><?= number_format((int)$bonificacaoEspeciais, 0, ',', '.') ?></span> L
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <button onclick="copiarGodSaveEmail()" class="mt-4 btn-acao">📋 Copiar para E-mail</button>
      </div>

      <hr class="divider_yellow my-6">

      <!-- CHORIPAN -->
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
            value="<?= number_format((float)$rebateWelt, 2, '.', '') ?>"
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
            value="<?= number_format((float)$rebateEspeciais, 2, '.', '') ?>"
            class="w-full bg-gray-700 border border-gray-600 rounded-md text-sm p-2"
          />
        </div>

        <!-- botão -->
        <div>
          <button type="submit" class="btn-acao w-full">Enviar XLSX | Atualizar</button>
        </div>

        <!-- Botão de copiar para e-mail -->
        <button onclick="copiarChoripanEmail()" type="button" class="mt-4 btn-acao">📋 Copiar para E-mail</button>
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
                      <td class="px-4 py-1 border"><?= htmlspecialchars((string)$cliente) ?></td>
                      <td class="px-4 py-1 border"><?= htmlspecialchars((string)$produto) ?></td>
                      <td class="px-4 py-1 border"><?= (int)$qtde ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endforeach; ?>
                <tr class="border-t border-gray-600">
                  <td class="px-4 py-1 border text-center text-lg font-semibold" colspan='3'>
                    TOTAL REPASSE R$ <?= number_format((float)$repasseWelt,      2, ',', '.') ?>
                    + R$ <?= number_format((float)$repasseEspeciais, 2, ',', '.') ?>
                    = R$ <?= number_format((float)$totalRepasse,     2, ',', '.') ?>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    </main>
  </div>

<script>
const litrosBastards = <?= (int)$litrosBastards ?>;
const litrosEspeciais = <?= (int)$litrosEspeciais ?>;

function copiarChoripanEmail() {
    const cardOriginal = document.getElementById('card-choripan');
    if (!cardOriginal) {
        alert('Sem conteúdo do Choripan para copiar.');
        return;
    }
    const card = cardOriginal.cloneNode(true);

    const estiloHeader = "background-color:#3b568c;font-weight:bold;border:1px solid #ccc;padding:8px;font-size:14px;";
    const estiloCelula = "border:1px solid #ccc;padding:8px;color:#333;font-size:12px;background-color:#fff;";
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
                    linha.innerText.includes("TOTAL GERAL") ||
                    linha.innerText.includes("REPASSE")
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
    alert('Conteúdo do Choripan copiado! Agora é só colar no e-mail.');
}

function copiarGodSaveEmail() {
    const cardOriginal = document.getElementById('card-godsave');
    if (!cardOriginal) {
        alert('Sem conteúdo do GOD Save para copiar.');
        return;
    }
    const card = cardOriginal.cloneNode(true);

    card.querySelectorAll('input, button').forEach(el => el.remove());

    const estiloHeader = "background-color:#3b568c;font-weight:bold;border:1px solid #ccc;padding:8px;font-size:14px;";
    const estiloCelula = "border:1px solid #ccc;padding:8px;color:#333;font-size:12px;background-color:#fff;";
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

function copiarResumoEmail() {
    const tabelaOriginal = document.getElementById('bulldog');
    if (!tabelaOriginal) {
        alert('Sem resumo para copiar.');
        return;
    }
    const tabela = tabelaOriginal.cloneNode(true);

    const estiloHeader = "background-color:#3b568c;font-weight:bold;border:1px solid #ccc;padding:6px;font-size:14px;";
    const estiloCelula = "border:1px solid #ccc;padding:6px;color:#333;background-color:#fff;font-size:12px;";
    const estiloTotal  = "background-color:#e5e7eb;font-weight:bold;border:1px solid #ccc;padding:6px;color:#111;font-size:12px;";

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
</body>
</html>
