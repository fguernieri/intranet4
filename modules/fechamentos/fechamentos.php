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
            if (strcasecmp((string)$prod, 'Bastards Pilsen') === 0) {
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

    $bonificacaoBastards  = (int) round($litrosBastards  * ($percentualBonificacao / 100));
    $bonificacaoEspeciais = (int) round($litrosEspeciais * ($percentualEspeciais   / 100));
}

// Expor variáveis (garantindo definidos)
$totaisGodSave         = $totaisGodSave         ?? [];
$litrosBastards        = $litrosBastards        ?? 0;
$litrosEspeciais       = $litrosEspeciais       ?? 0;
$percentualBonificacao = $percentualBonificacao ?? 0;
$percentualEspeciais   = $percentualEspeciais   ?? 0;
$bonificacaoBastards   = $bonificacaoBastards   ?? 0;
$bonificacaoEspeciais  = $bonificacaoEspeciais  ?? 0;

// ====== Fechamento Hermes e Renato (a partir do mesmo XLS de Vendas) ======
// Consolida a soma da Coluna J (10ª coluna, índice 9) para os produtos especificados
$hermesLataValor = 0.0;
$hermesValor     = 0.0;
$hermesTotal     = 0.0;

if (!empty($dadosChoripan)) {
    $cabHerm   = $dadosChoripan[0] ?? [];
    $idxProdH  = array_search('Produto', $cabHerm, true);

    // Função local para converter valores monetários (com R$, ponto e vírgula) para float
    $toFloat = function($str) {
        $s = (string)$str;
        $s = trim($s);
        // remove qualquer caractere que não seja dígito, vírgula, ponto, sinal
        $s = preg_replace('/[^0-9,.-]/u', '', $s);
        // se houver mais de uma vírgula, mantém apenas a última como decimal
        $parts = explode(',', $s);
        if (count($parts) > 1) {
            $decimal = array_pop($parts);
            $int = implode('', $parts);
            $s = $int . '.' . $decimal;
        } else {
            // não tem vírgula, troca possível separador decimal ponto
            $s = str_replace(',', '.', $s);
        }
        // remove espaços
        $s = str_replace([' '], '', $s);
        return is_numeric($s) ? (float)$s : 0.0;
    };

    foreach (array_slice($dadosChoripan, 1) as $linha) {
        $produto = ($idxProdH !== false && $idxProdH !== null) ? (string)($linha[$idxProdH] ?? '') : '';
        if ($produto === '') { continue; }
        $valorStr = (string)($linha[9] ?? '0'); // Coluna J
        $valorNum = $toFloat($valorStr);

        if (strcasecmp($produto, 'HERMES E RENATO-LATA 350ML') === 0) {
            $hermesLataValor += $valorNum;
        } elseif (strcasecmp($produto, 'HERMES E RENATO') === 0) {
            $hermesValor += $valorNum;
        }
    }
}

$hermesTotal = (float)$hermesLataValor + (float)$hermesValor;
$hermesRepassePercent = 10.0; // valor inicial padrão, ajustável via input (JS)
$hermesRepasseValor   = (float)round($hermesTotal * ($hermesRepassePercent/100), 2);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Fechamentos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        /* Estilos personalizados para melhorar a harmonia visual */
        .card-section {
            background-color: #1f2937;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }
        
        .card-header {
            background-color: #111827;
            padding: 1rem 1.5rem;
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
            border-bottom: 2px solid #fbbf24;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #fbbf24;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .table-container {
            border-radius: 0.5rem;
            overflow: hidden;
            border: 1px solid #374151;
        }
        
        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .custom-table th {
            background-color: #374151;
            color: #f3f4f6;
            font-weight: 600;
            text-align: left;
            padding: 0.75rem 1rem;
        }
        
        .custom-table td {
            padding: 0.625rem 1rem;
            border-top: 1px solid #4b5563;
        }
        
        .custom-table tr:hover td {
            background-color: #2d3748;
        }
        
        .custom-table tr.total-row td {
            background-color: #374151;
            font-weight: 600;
            color: #fbbf24;
        }
        
        .btn-group {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #e5e7eb;
        }
        
        .form-control {
            width: 100%;
            background-color: #374151;
            border: 1px solid #4b5563;
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            color: #f3f4f6;
            transition: border-color 0.15s ease-in-out;
        }
        
        .form-control:focus {
            border-color: #60a5fa;
            outline: none;
        }
        
        .btn-primary {
            background-color: #2563eb;
            color: white;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            border: none;
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }
        
        .btn-primary:hover {
            background-color: #1d4ed8;
        }
        
        .btn-secondary {
            background-color: #4b5563;
            color: white;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            border: none;
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }
        
        .btn-secondary:hover {
            background-color: #374151;
        }
        
        .btn-success {
            background-color: #059669;
            color: white;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            border: none;
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }
        
        .btn-success:hover {
            background-color: #047857;
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">
  <div class="flex min-h-screen w-full">
    <aside>
      <?php include __DIR__ . '/../../sidebar.php'; ?>
    </aside>

    <main class="flex-1 p-6 overflow-auto max-w-7xl mx-auto">

      <div class="card-section">
        <div class="card-header">
          <h1 class="section-title text-2xl">📊 Fechamento Bulldog</h1>
        </div>
        <div class="card-body">
            <p class="mb-2">Cloudify > Relatórios Gerais > Vendas > Relatório de vendas</p>
            <p class="mb-2">Inicio e Fim > Grupo de Produtos > T - SOUVENIR > Filiais > Bastards Taproom > Excel</p>
          <form method="POST" enctype="multipart/form-data" class="mb-6">
            <div class="form-group">
              <label for="arquivo" class="form-label">Selecione o arquivo Excel</label>
              <div class="flex gap-4">
                <input type="file" name="arquivo" id="arquivo" accept=".xlsx" required class="form-control" />
                <button type="submit" class="btn-primary">Enviar</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Filtros -->
      <?php if (!empty($mesesDisponiveis)): ?>
      <div class="card-section">
        <div class="card-header">
          <h2 class="section-title">🔍 Filtros de Pesquisa</h2>
        </div>
        <div class="card-body">
          <form method="GET" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="form-group">
              <label for="mes" class="form-label">🗓️ Mês</label>
              <select name="mes" id="mes" class="form-control">
                <option value="">-- Todos os meses --</option>
                <?php foreach ($mesesDisponiveis as $mes): ?>
                  <option value="<?= htmlspecialchars($mes) ?>" <?= ($mes === $mesSelecionado) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($mes) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="keywords" class="form-label">🔍 Palavras-chave (produto)</label>
              <input type="text" name="keywords" id="keywords"
                value="<?= htmlspecialchars($_GET['keywords'] ?? 'MAÇARICO, CHARUTO, CINZEIRO, CORTADOR') ?>"
                class="form-control"
                placeholder="ex: fitzgerald, gin, spritz">
            </div>
            <div class="md:col-span-2 flex justify-end">
              <button type="submit" class="btn-primary">Aplicar Filtros</button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($dadosFiltrados)): ?>
        <div class="card-section">
          <div class="card-header">
            <div class="flex justify-between items-center">
              <h2 class="section-title">📋 Detalhes das Vendas</h2>
              <button
                onclick="document.getElementById('tabela-detalhes').classList.toggle('hidden')"
                class="btn-secondary"
              >
                🔽 Mostrar/Esconder Detalhes
              </button>
            </div>
          </div>
          <div class="card-body">
            <div id="tabela-detalhes" class="overflow-auto hidden transition-all duration-300 ease-in-out table-container">
              <table class="custom-table">
                <thead>
                  <tr>
                    <?php
                    $cabecalho = $dados[0] ?? [];
                    $indices = [];
                    $colunasDesejadas = ['Data', 'Produto', 'Preço', 'Qtde. total', 'Total'];

                    foreach ($colunasDesejadas as $coluna) {
                      $idx = array_search($coluna, $cabecalho, true);
                      if ($idx !== false) {
                        $indices[$coluna] = $idx;
                        echo "<th>" . htmlspecialchars($coluna) . "</th>";
                      }
                    }
                    ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($dadosFiltrados as $linha): ?>
                    <tr>
                      <?php foreach ($indices as $idx): ?>
                        <td><?= htmlspecialchars((string)($linha[$idx] ?? '')) ?></td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- RESUMO POR PRODUTO -->
        <div class="card-section">
          <div class="card-header">
            <h2 class="section-title text-xl">📊 Resumo por Produto</h2>
          </div>
          <div class="card-body">
            <div class="table-container">
              <table id="bulldog" class="custom-table">
                <thead>
                  <tr>
                    <th>Produto</th>
                    <th>Preço</th>
                    <th>Soma Qtde. total</th>
                    <th>Soma Total</th>
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
                      echo "<tr>";
                      echo "<td>" . htmlspecialchars((string)$produto) . "</td>";
                      echo "<td>R$ " . number_format((float)$info['preco'], 2, ',', '.') . "</td>";
                      echo "<td>" . (int)$info['qtde'] . "</td>";
                      echo "<td>R$ " . number_format((float)$info['total'], 2, ',', '.') . "</td>";
                      echo "</tr>";

                      $totalGeralQtde += (int)$info['qtde'];
                      $totalGeralValor += (float)$info['total'];
                  }

                  echo "<tr class='total-row'>";
                  echo "<td colspan='2'>TOTAL GERAL</td>";
                  echo "<td>" . (int)$totalGeralQtde . "</td>";
                  echo "<td>R$ " . number_format($totalGeralValor, 2, ',', '.') . "</td>";
                  echo "</tr>";

                  echo "<tr class='total-row'>";
                  echo "<td colspan='3'>REPASSE (85%)</td>";
                  echo "<td>R$ " . number_format($totalGeralValor * 0.85, 2, ',', '.') . "</td>";
                  echo "</tr>";
                  ?>
                </tbody>
              </table>
            </div>
            <button onclick="copiarResumoEmail()" class="mt-4 btn-primary">📋 Copiar para E-mail</button>
          </div>
        </div>
      <?php endif; ?>

      <script>
      window.addEventListener("unload", function () {
          navigator.sendBeacon(window.location.pathname + "?limpar=1");
      });
      </script>

      <!-- Relatório de Vendas -->
      <div class="card-section">
        <div class="card-header">
          <h1 class="section-title text-2xl">📤 Upload Relatório de Recebimentos</h1>

        </div>
        <div class="card-body">
            <p class="mb-2">ARB > Relatórios > Comercial > Vendas > Vendas por cliente</p>
            <p>Data Pagamento > Inicial e Final > Buscar > Produto > Excel</p>
          <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <input type="hidden" name="formulario" value="choripan" />
            <div class="md:col-span-4 form-group">
              <label for="arquivoChoripan" class="form-label">Selecione o arquivo Excel:</label>
              <input
                type="file"
                name="arquivoChoripan"
                id="arquivoChoripan"
                accept=".xlsx"
                class="form-control"
              />
            </div>
            <div>
              <button type="submit" class="btn-primary w-full">Enviar XLSX | Atualizar</button>
            </div>
          </form>
        </div>
      </div>

      <!-- GOD SAVE -->
      <div class="card-section">
        <div class="card-header">
          <h1 class="section-title text-2xl">🏆 Fechamento GOD</h1>
        </div>
        <div class="card-body">
          <div id="card-godsave" class="mt-6">
            <div class="flex flex-col md:flex-row gap-4">
              <div class="form-group">
                <label for="percent_bastards" class="form-label">Bastards Pilsen (%)</label>
                <input
                  type="number"
                  id="percent_bastards"
                  step="0.01"
                  value="<?= htmlspecialchars((string)$percentualBonificacao) ?>"
                  oninput="atualizarBonificacaoGodSave()"
                  class="form-control"
                />
              </div>
              <div class="form-group">
                <label for="percent_especiais" class="form-label">Especiais (%)</label>
                <input
                  type="number"
                  id="percent_especiais"
                  step="0.01"
                  value="<?= htmlspecialchars((string)$percentualEspeciais) ?>"
                  oninput="atualizarBonificacaoGodSave()"
                  class="form-control"
                />
              </div>
            </div>
            <div class="table-container mt-4">
              <table class="custom-table">
                <thead>
                  <tr>
                    <th>Cliente</th>
                    <th>Produto</th>
                    <th>Quantidade vendida</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($totaisGodSave as $cliente => $lista): ?>
                    <?php foreach ($lista as $produto => $qtde): ?>
                      <tr>
                        <td><?= htmlspecialchars((string)$cliente) ?></td>
                        <td><?= htmlspecialchars((string)$produto) ?></td>
                        <td><?= (int)$qtde ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                  <tr class="total-row">
                    <td class="text-center" colspan="3">
                      TOTAL BASTARDS PILSEN <?= (int)$litrosBastards ?> L = 
                      <span id="bonificacao-godsave-text"><?= (int)$bonificacaoBastards ?></span> L
                    </td>
                  </tr>
                  <tr class="total-row">
                    <td class="text-center" colspan="3">
                      TOTAL ESPECIAIS <?= (int)$litrosEspeciais ?> L = 
                      <span id="bonificacao-especiais-text"><?= (int)$bonificacaoEspeciais ?></span> L
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="btn-group mt-4">
              <button onclick="copiarGodSaveEmail()" class="btn-secondary">📋 Copiar para E-mail</button>
              <button onclick="exportarGodSavePNG()" class="btn-primary">🖼️ Exportar PNG</button>
            </div>
          </div>
        </div>
      </div>
      
      <!-- HERMES E RENATO -->
      <div class="card-section">
        <div class="card-header">
          <h1 class="section-title text-2xl">🥃 Fechamento Hermes e Renato</h1>
        </div>
        <div class="card-body">
          <div id="card-hermes" class="space-y-4">
            <div class="table-container">
              <table class="custom-table">
                <thead>
                  <tr>
                    <th>Produto</th>
                    <th>R$ Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>HERMES E RENATO-LATA 350ML</td>
                    <td>R$ <?= number_format((float)$hermesLataValor, 2, ',', '.') ?></td>
                  </tr>
                  <tr>
                    <td>HERMES E RENATO</td>
                    <td>R$ <?= number_format((float)$hermesValor, 2, ',', '.') ?></td>
                  </tr>
                  <tr class="total-row">
                    <td>Total</td>
                    <td>R$ <?= number_format((float)$hermesTotal, 2, ',', '.') ?></td>
                  </tr>
                  <tr class="total-row">
                    <td class="text-center" colspan="2">
                      REPASSE <span id="repasse-hermes-percent-text"><?= htmlspecialchars((string)$hermesRepassePercent) ?></span>% =
                      R$ <span id="repasse-hermes-text"><?= number_format((float)$hermesRepasseValor, 2, ',', '.') ?></span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="flex flex-col md:flex-row gap-4 items-end">
              <div class="form-group">
                <label for="percent_hermes" class="form-label">Repasse (%)</label>
                <input
                  type="number"
                  id="percent_hermes"
                  step="0.01"
                  value="<?= htmlspecialchars((string)$hermesRepassePercent) ?>"
                  oninput="atualizarRepasseHermes()"
                  class="form-control"
                />
              </div>
              
            </div>

            <div class="btn-group">
              <button onclick="copiarHermesEmail()" class="btn-secondary">📧 Copiar para E-mail</button>
              <button onclick="exportarHermesPNG()" class="btn-primary">🖼️ Exportar PNG</button>
            </div>
          </div>
        </div>
      </div>

      <!-- CHORIPAN -->
      <div class="card-section">
        <div class="card-header">
          <h1 class="section-title text-2xl">🍻 Fechamento Choripan</h1>
        </div>
        <div class="card-body">
          <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end mb-6">
            <input type="hidden" name="formulario" value="choripan" />

            <!-- Rebate Welt -->
            <div class="form-group">
              <label for="rebate_welt" class="form-label">Rebate Pilsen (R$)</label>
              <input
                type="number"
                name="rebate_welt"
                id="rebate_welt"
                step="0.01"
                value="<?= number_format((float)$rebateWelt, 2, '.', '') ?>"
                class="form-control"
              />
            </div>

            <!-- Rebate Especiais -->
            <div class="form-group">
              <label for="rebate_especiais" class="form-label">Rebate Especiais (R$)</label>
              <input
                type="number"
                name="rebate_especiais"
                id="rebate_especiais"
                step="0.01"
                value="<?= number_format((float)$rebateEspeciais, 2, '.', '') ?>"
                class="form-control"
              />
            </div>

            <!-- botão -->
            <div>
              <button type="submit" class="btn-primary w-full">Atualizar</button>
            </div>
          </form>

          <div class="btn-group mb-6">
            <button onclick="copiarChoripanEmail()" type="button" class="btn-secondary">📋 Copiar para E-mail</button>
            <button onclick="exportarChoripanPNG()" type="button" class="btn-primary">🖼️ Exportar PNG</button>
          </div>

          <?php if (!empty($totaisChoripan)): ?>
            <div id="card-choripan" class="table-container">
              <table class="custom-table">
                <thead>
                  <tr>
                    <th>Cliente</th>
                    <th>Produto</th>
                    <th>Quantidade vendida</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($totaisChoripan as $cliente => $lista): ?>
                    <?php foreach ($lista as $produto => $qtde): ?>
                      <tr>
                        <td><?= htmlspecialchars((string)$cliente) ?></td>
                        <td><?= htmlspecialchars((string)$produto) ?></td>
                        <td><?= (int)$qtde ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                  <tr class="total-row">
                    <td class="text-center" colspan='3'>
                      TOTAL REPASSE R$ <?= number_format((float)$repasseWelt,      2, ',', '.') ?>
                      + R$ <?= number_format((float)$repasseEspeciais, 2, ',', '.') ?>
                      = R$ <?= number_format((float)$totalRepasse,     2, ',', '.') ?>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </main>
  </div>

<script>
const litrosBastards = <?= (int)$litrosBastards ?>;
const litrosEspeciais = <?= (int)$litrosEspeciais ?>;
const hermesTotal = <?= json_encode((float)$hermesTotal) ?>;

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
    const bonB = Math.round(litrosBastards * (pctB / 100));
    const bonE = Math.round(litrosEspeciais * (pctE / 100));
    const spanB = document.getElementById('bonificacao-godsave-text');
    const spanE = document.getElementById('bonificacao-especiais-text');
    if (spanB) {
        spanB.textContent = bonB.toLocaleString('pt-BR');
    }
    if (spanE) {
        spanE.textContent = bonE.toLocaleString('pt-BR');
    }
}

function exportarChoripanPNG() {
    const cardOriginal = document.getElementById('card-choripan');
    if (!cardOriginal) {
        alert('Sem conteúdo do Choripan para exportar.');
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

    html2canvas(card).then(canvas => {
        const link = document.createElement('a');
        link.href = canvas.toDataURL('image/png');
        link.download = 'choripan.png';
        link.click();
        document.body.removeChild(container);
    });
}

function exportarGodSavePNG() {
    const cardOriginal = document.getElementById('card-godsave');
    if (!cardOriginal) {
        alert('Sem conteúdo do GOD Save para exportar.');
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

    html2canvas(card).then(canvas => {
        const link = document.createElement('a');
        link.href = canvas.toDataURL('image/png');
        link.download = 'godsave.png';
        link.click();
        document.body.removeChild(container);
    });
}
document.getElementById('percent_bastards') && atualizarBonificacaoGodSave();
document.getElementById('percent_hermes') && atualizarRepasseHermes();

function atualizarRepasseHermes() {
    const pct = parseFloat(document.getElementById('percent_hermes')?.value) || 0;
    const valor = (hermesTotal || 0) * (pct / 100);
    const span = document.getElementById('repasse-hermes-text');
    const spanPct = document.getElementById('repasse-hermes-percent-text');
    if (span) {
        span.textContent = (valor || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    if (spanPct) {
        spanPct.textContent = (pct || 0).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }
}

function copiarHermesEmail() {
    const cardOriginal = document.getElementById('card-hermes');
    if (!cardOriginal) {
        alert('Sem conteúdo do Hermes e Renato para copiar.');
        return;
    }
    const table = cardOriginal.querySelector('table')?.cloneNode(true);
    if (!table) {
        alert('Tabela do Hermes e Renato não encontrada.');
        return;
    }

    const estiloHeader = "background-color:#3b568c;font-weight:bold;border:1px solid #ccc;padding:8px;font-size:14px;";
    const estiloCelula = "border:1px solid #ccc;padding:8px;color:#333;font-size:12px;background-color:#fff;";
    const estiloTotal  = "background-color:#e5e7eb;font-weight:bold;border:1px solid #ccc;padding:8px;color:#111111;font-size:12px;";

    table.removeAttribute('class');
    const linhasC = table.querySelectorAll('tr');
    linhasC.forEach((linha, i) => {
        const celulas = linha.children;
        for (const celula of celulas) {
            celula.removeAttribute('class');
            celula.removeAttribute('style');
            if (i === 0) {
                celula.setAttribute("style", estiloHeader);
            } else if (
                linha.innerText.toUpperCase().includes("TOTAL") ||
                linha.innerText.toUpperCase().includes("REPASSE")
            ) {
                celula.setAttribute("style", estiloTotal);
            } else {
                celula.setAttribute("style", estiloCelula);
            }
        }
    });

    const container = document.createElement('div');
    container.style.position = 'absolute';
    container.style.left = '-9999px';
    container.appendChild(table);
    document.body.appendChild(container);

    const range = document.createRange();
    range.selectNode(container);
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
    document.execCommand('copy');
    sel.removeAllRanges();

    document.body.removeChild(container);
    alert('Conteúdo do Hermes e Renato copiado! Agora é só colar no e-mail.');
}

function exportarHermesPNG() {
    const cardOriginal = document.getElementById('card-hermes');
    if (!cardOriginal) {
        alert('Sem conteúdo do Hermes e Renato para exportar.');
        return;
    }
    const table = cardOriginal.querySelector('table')?.cloneNode(true);
    if (!table) {
        alert('Tabela do Hermes e Renato não encontrada.');
        return;
    }

    const estiloHeader = "background-color:#3b568c;font-weight:bold;border:1px solid #ccc;padding:8px;font-size:14px;";
    const estiloCelula = "border:1px solid #ccc;padding:8px;color:#333;font-size:12px;background-color:#fff;";
    const estiloTotal  = "background-color:#e5e7eb;font-weight:bold;border:1px solid #ccc;padding:8px;color:#111111;font-size:12px;";

    table.removeAttribute('class');
    const linhasP = table.querySelectorAll('tr');
    linhasP.forEach((linha, i) => {
        const celulas = linha.children;
        for (const celula of celulas) {
            celula.removeAttribute('class');
            celula.removeAttribute('style');
            if (i === 0) {
                celula.setAttribute("style", estiloHeader);
            } else if (
                linha.innerText.toUpperCase().includes("TOTAL") ||
                linha.innerText.toUpperCase().includes("REPASSE")
            ) {
                celula.setAttribute("style", estiloTotal);
            } else {
                celula.setAttribute("style", estiloCelula);
            }
        }
    });

    const container = document.createElement('div');
    container.style.position = 'absolute';
    container.style.left = '-9999px';
    container.appendChild(table);
    document.body.appendChild(container);

    html2canvas(table).then(canvas => {
        const link = document.createElement('a');
        link.href = canvas.toDataURL('image/png');
        link.download = 'hermes_renato.png';
        link.click();
        document.body.removeChild(container);
    });
}

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
