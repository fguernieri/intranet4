<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==================== [ ADIÇÃO 1: TRATAMENTO POST ] ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
    $connPost = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $connPost->set_charset('utf8mb4');
    if ($connPost->connect_error) {
        echo json_encode(['sucesso' => false, 'erro' => "Conexão falhou: " . $connPost->connect_error]);
        exit;
    }
    $jsonData = json_decode(file_get_contents('php://input'), true);
    $metas = $jsonData['metas'] ?? [];
    $dataMeta = $jsonData['data'] ?? date('Y-m-d');

    if (empty($metas)) {
        echo json_encode(['sucesso' => false, 'erro' => 'Dados ausentes (metas vazias).']);
        exit;
    }

    // Exclui previamente as metas da data escolhida (para sobrescrever)
    $stmtDel = $connPost->prepare("DELETE FROM fMetasFabrica WHERE Data = ?");
    $stmtDel->bind_param("s", $dataMeta);
    $stmtDel->execute();
    $stmtDel->close();

    $stmt = $connPost->prepare("INSERT INTO fMetasFabrica (Categoria, Subcategoria, Meta, Data) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['sucesso' => false, 'erro' => $connPost->error]);
        exit;
    }
    foreach ($metas as $m) {
        $cat  = $m['categoria']    ?? '';
        $sub  = $m['subcategoria'] ?? '';
        $meta = floatval($m['meta']) ?? 0;
        $stmt->bind_param("ssds", $cat, $sub, $meta, $dataMeta);
        $stmt->execute();
    }
    $stmt->close();
    $connPost->close();

    echo json_encode(['sucesso' => true]);
    exit;
}
// ==================== [ FIM DO TRATAMENTO POST ] ====================

// Sidebar e autenticação
require_once __DIR__ . '/../../sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

// Conexão com o banco
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Defina o ano e mês atual (ou pegue do GET)
$anoAtual = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
$mesAtual = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');

// Carregue todas as parcelas do ano para médias e totais
$sql = "
    SELECT 
        c.ID_CONTA,
        c.CATEGORIA,
        c.SUBCATEGORIA,
        c.DESCRICAO_CONTA,
        d.PARCELA,
        d.VALOR,
        d.DATA_VENCIMENTO,
        c.STATUS
    FROM fContasAPagar AS c
    INNER JOIN fContasAPagarDetalhes AS d ON c.ID_CONTA = d.ID_CONTA
    WHERE YEAR(d.DATA_VENCIMENTO) = $anoAtual
";
$res = $conn->query($sql);
$linhas = [];
while ($f = $res->fetch_assoc()) {
    $linhas[] = [
        'ID_CONTA'        => $f['ID_CONTA'],
        'CATEGORIA'       => $f['CATEGORIA'],
        'SUBCATEGORIA'    => $f['SUBCATEGORIA'],
        'DESCRICAO_CONTA' => $f['DESCRICAO_CONTA'],
        'PARCELA'         => $f['PARCELA'],
        'VALOR_EXIBIDO'   => $f['VALOR'],
        'DATA_VENCIMENTO' => $f['DATA_VENCIMENTO'],
        'STATUS'          => $f['STATUS'],
    ];
}

// Organiza em matriz por categoria, subcategoria e mês (nível de descrição removido)
$meses = [
    1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
    7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'
];
$matriz = [];
foreach ($linhas as $linha) {
    $cat = $linha['CATEGORIA'] ?? 'SEM CATEGORIA';
    $sub = $linha['SUBCATEGORIA'] ?? 'SEM SUBCATEGORIA';
    $mes = (int) date('n', strtotime($linha['DATA_VENCIMENTO']));
    $id  = $linha['ID_CONTA'];
    $matriz[$cat][$sub][$mes][$id][] = floatval($linha['VALOR_EXIBIDO']);
}

// Calcule os 3 últimos meses fechados a partir do mês selecionado
$mesesUltimos3 = [];
for ($i = 1; $i <= 3; $i++) {
    $m = $mesAtual - $i;
    if ($m < 1) $m += 12;
    $mesesUltimos3[] = $m;
}

// Calcule média e mês atual para cada categoria e subcategoria
$media3Cat = [];
$atualCat   = [];
$media3Sub = [];
$atualSub   = [];
foreach ($matriz as $cat => $subs) {
    $soma3Cat = 0;
    $somaAtualCat = 0;
    foreach ($subs as $sub => $mesValores) {
        $soma3Sub    = 0;
        $somaAtualSub = 0;
        foreach ($mesesUltimos3 as $m) {
            if (isset($mesValores[$m])) {
                foreach ($mesValores[$m] as $ids) {
                    $soma3Cat += array_sum($ids);
                    $soma3Sub += array_sum($ids);
                }
            }
        }
        if (isset($mesValores[$mesAtual])) {
            foreach ($mesValores[$mesAtual] as $ids) {
                $somaAtualCat += array_sum($ids);
                $somaAtualSub += array_sum($ids);
            }
        }
        $media3Sub[$cat][$sub] = $soma3Sub / 3;
        $atualSub[$cat][$sub] = $somaAtualSub;
    }
    $media3Cat[$cat] = $soma3Cat / 3;
    $atualCat[$cat]   = $somaAtualCat;
}

// Receita operacional (exemplo simplificado)
$receitaPorMes = [];
$resRec = $conn->query("
    SELECT DATA_PAGAMENTO, SUM(VALOR_PAGO) AS TOTAL
    FROM fContasAReceberDetalhes
    WHERE STATUS = 'Pago' AND YEAR(DATA_PAGAMENTO) = $anoAtual
    GROUP BY DATA_PAGAMENTO
");
while ($row = $resRec->fetch_assoc()) {
    $mes = (int)date('n', strtotime($row['DATA_PAGAMENTO']));
    $receitaPorMes[$mes] = ($receitaPorMes[$mes] ?? 0) + floatval($row['TOTAL']);
}
$soma3Rec = 0;
foreach ($mesesUltimos3 as $m) {
    $soma3Rec += $receitaPorMes[$m] ?? 0;
}
$media3Rec = $soma3Rec / 3;
$atualRec   = $receitaPorMes[$mesAtual] ?? 0;

// Ordena as categorias, colocando "OPERACOES EXTERNAS" por último
$matrizOrdenada = [];
foreach ($matriz as $cat => $subs) {
    if (mb_strtoupper(trim($cat)) !== 'Z - SAIDA DE REPASSE') {
        $matrizOrdenada[$cat] = $subs;
    }
}
if (isset($matriz['Z - SAIDA DE REPASSE'])) {
    $matrizOrdenada['Z - SAIDA DE REPASSE'] = $matriz['Z - SAIDA DE REPASSE'];
}

// Consulta para carregar a ÚLTIMA meta gravada para cada Categoria/Subcategoria
$sqlMetas = "
    SELECT 
        fm1.Categoria, 
        fm1.Subcategoria, 
        fm1.Meta
    FROM 
        fMetasFabrica fm1
    INNER JOIN (
        SELECT 
            Categoria, 
            Subcategoria, 
            MAX(Data) as MaxData
        FROM 
            fMetasFabrica
        GROUP BY 
            Categoria, Subcategoria
    ) fm2 
    ON fm1.Categoria = fm2.Categoria 
    AND IFNULL(fm1.Subcategoria, '') = IFNULL(fm2.Subcategoria, '') -- Trata Subcategoria NULL ou vazia de forma consistente
    AND fm1.Data = fm2.MaxData
";
$resMetas = $conn->query($sqlMetas);
$metasArray = [];
if ($resMetas) {
    while ($m = $resMetas->fetch_assoc()){
        $cat = $m['Categoria'];
        $sub = $m['Subcategoria'] ?? ''; // Usar string vazia se Subcategoria for NULL/vazia
        $metasArray[$cat][$sub] = $m['Meta'];
    }
}

// Certifique-se de que as metas da Receita, Tributos e Custo Variável estejam definidas,
// utilizando 0 como valor padrão se não existirem.
$metaReceita         = $metasArray['RECEITA BRUTA']['']      ?? 0; // Ajustado para 'RECEITA BRUTA'
$metaTributos        = $metasArray['TRIBUTOS']['']           ?? 0;
$metaCustoVariavel   = $metasArray['CUSTO VARIÁVEL']['']      ?? 0;
// Calcula o Lucro Bruto da meta: Receita - Tributos - Custo Variável
$metaLucro = $metaReceita - $metaTributos - $metaCustoVariavel;

// Para as despesas, use 0 caso não existam
$metaCustoFixo      = $metasArray['CUSTO FIXO']['']      ?? 0;
$metaDespesaFixa    = $metasArray['DESPESA FIXA']['']     ?? 0;
$metaDespesaVenda   = $metasArray['DESPESA VENDA']['']    ?? 0;
$totalMetaDespesas  = $metaCustoFixo + $metaDespesaFixa + $metaDespesaVenda;

// Lucro Líquido da meta: Lucro Bruto - (Custo Fixo + Despesa Fixa + Despesa Venda)
$metaLucroLiquido = $metaLucro - $totalMetaDespesas;

// Calcule médias e totais para FAT LIQUIDO e CUSTO VARIÁVEL
$mediaFATLiquido = ($media3Rec - ($media3Cat['TRIBUTOS'] ?? 0));
$atualFATLiquido = ($atualRec - ($atualCat['TRIBUTOS'] ?? 0));

$mediaCustoVariavel = $media3Cat['CUSTO VARIÁVEL'] ?? 0;
$atualCustoVariavel = $atualCat['CUSTO VARIÁVEL'] ?? 0;

// ==================== [NOVA SEÇÃO: BUSCAR DADOS DE fOutrasReceitas] ====================
$outrasReceitasPorCatSubMes = []; // Estrutura: [categoria][subcategoria][mes] = valor
$sqlOutrasRec = "
    SELECT CATEGORIA, SUBCATEGORIA, DATA_COMPETENCIA, SUM(VALOR) AS TOTAL
    FROM fOutrasReceitas
    WHERE YEAR(DATA_COMPETENCIA) = $anoAtual
    GROUP BY CATEGORIA, SUBCATEGORIA, DATA_COMPETENCIA
    ORDER BY CATEGORIA, SUBCATEGORIA, DATA_COMPETENCIA
";
$resOutrasRec = $conn->query($sqlOutrasRec);
if ($resOutrasRec) {
    while ($row = $resOutrasRec->fetch_assoc()) {
        $catOR = !empty($row['CATEGORIA']) ? $row['CATEGORIA'] : 'NÃO CATEGORIZADO';
        $subOR = !empty($row['SUBCATEGORIA']) ? $row['SUBCATEGORIA'] : 'NÃO ESPECIFICADO';
        $mesOR = (int)date('n', strtotime($row['DATA_COMPETENCIA']));
        $outrasReceitasPorCatSubMes[$catOR][$subOR][$mesOR] = ($outrasReceitasPorCatSubMes[$catOR][$subOR][$mesOR] ?? 0) + floatval($row['TOTAL']);
    }
}

// Calcular médias e atuais para cada categoria e subcategoria de fOutrasReceitas
$media3OutrasRecSub = []; // [cat][sub] = media
$atualOutrasRecSub   = []; // [cat][sub] = atual
$totalMedia3OutrasRecGlobal = 0; // Total geral da média para a linha principal "RECEITAS NAO OPERACIONAIS"
$totalAtualOutrasRecGlobal  = 0; // Total geral atual para a linha principal "RECEITAS NAO OPERACIONAIS"

if (!empty($outrasReceitasPorCatSubMes)) {
    foreach ($outrasReceitasPorCatSubMes as $catOR => $subcategoriasOR) {
        foreach ($subcategoriasOR as $subOR => $valoresMensaisOR) {
            $soma3 = 0;
            foreach ($mesesUltimos3 as $m) {
                $soma3 += $valoresMensaisOR[$m] ?? 0;
            }
            $media3OutrasRecSub[$catOR][$subOR] = (count($mesesUltimos3) > 0) ? $soma3 / count($mesesUltimos3) : 0;
             if (count($mesesUltimos3) == 0) $media3OutrasRecSub[$catOR][$subOR] = 0;


            $atualOutrasRecSub[$catOR][$subOR] = $valoresMensaisOR[$mesAtual] ?? 0;

            $totalMedia3OutrasRecGlobal += $media3OutrasRecSub[$catOR][$subOR];
            $totalAtualOutrasRecGlobal  += $atualOutrasRecSub[$catOR][$subOR];
        }
    }
}
// ==================== [FIM DA NOVA SEÇÃO] ====================

// ==================== [CÁLCULO LUCRO LÍQUIDO - PHP (ANTECIPADO)] ====================
$mediaReceitaLiquida = $media3Rec - ($media3Cat['TRIBUTOS'] ?? 0);
$atualReceitaLiquida = $atualRec - ($atualCat['TRIBUTOS'] ?? 0);

$mediaLucroBruto = $mediaReceitaLiquida - ($media3Cat['CUSTO VARIÁVEL'] ?? 0);
$atualLucroBruto = $atualReceitaLiquida - ($atualCat['CUSTO VARIÁVEL'] ?? 0);

$mediaCustoFixo    = $media3Cat['CUSTO FIXO']   ?? 0;
$mediaDespesaFixa  = $media3Cat['DESPESA FIXA']  ?? 0;
$mediaDespesaVenda = $media3Cat['DESPESA VENDA'] ?? 0;
$totalMediaDespesas = $mediaCustoFixo + $mediaDespesaFixa + $mediaDespesaVenda;
$mediaLucroLiquido = $mediaLucroBruto - $totalMediaDespesas;

$atualCustoFixo    = $atualCat['CUSTO FIXO']   ?? 0;
$atualDespesaFixa  = $atualCat['DESPESA FIXA']  ?? 0;
$atualDespesaVenda = $atualCat['DESPESA VENDA'] ?? 0;
$totalAtualDespesas = $atualCustoFixo + $atualDespesaFixa + $atualDespesaVenda;
$atualLucroLiquido = $atualLucroBruto - $totalAtualDespesas;
// ==================== [FIM CÁLCULO LUCRO LÍQUIDO - PHP (ANTECIPADO)] ====================

// ==================== [CÁLCULO FLUXO DE CAIXA - PHP] ====================
$mediaInvestInterno = $media3Cat['INVESTIMENTO INTERNO'] ?? 0;
$mediaInvestExterno = $media3Cat['INVESTIMENTO EXTERNO'] ?? 0;
$mediaSaidaRepasse  = $media3Cat['Z - SAIDA DE REPASSE'] ?? 0;
$mediaAmortizacao   = $media3Cat['AMORTIZAÇÃO'] ?? 0;
$mediaFluxoCaixa = ($mediaLucroLiquido + $totalMedia3OutrasRecGlobal) - ($mediaInvestInterno + $mediaInvestExterno + $mediaSaidaRepasse + $mediaAmortizacao);

$atualInvestInterno = $atualCat['INVESTIMENTO INTERNO'] ?? 0;
$atualInvestExterno = $atualCat['INVESTIMENTO EXTERNO'] ?? 0;
$atualSaidaRepasse  = $atualCat['Z - SAIDA DE REPASSE'] ?? 0;
$atualAmortizacao   = $atualCat['AMORTIZAÇÃO'] ?? 0;
$atualFluxoCaixa = ($atualLucroLiquido + $totalAtualOutrasRecGlobal) - ($atualInvestInterno + $atualInvestExterno + $atualSaidaRepasse + $atualAmortizacao);
// ==================== [FIM CÁLCULO FLUXO DE CAIXA - PHP] ====================



?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>DRE Financeiro</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="/assets/css/style.css" rel="stylesheet">
  <style>
    .dre-cat    { background: #22223b; font-weight: bold; cursor:pointer; user-select: none;}
    .dre-sub    { background: #383858; font-weight: 500; cursor:pointer; }
    .dre-detalhe{ background: #232946; }
    .dre-hide   { display: none; }
    table, th, td {
      font-family: 'Segoe UI', Arial, sans-serif;
      font-size: 11px;
      border: 0.5px solid #fff;
      border-collapse: collapse;
    }
    .dre-cat, .dre-sub {
      font-size: 12px;
    }
    th, td { border: 0.5px solid #fff; }
    .col-atual {
      background-color: #1e293b !important;
      color: #ffb703 !important;
      border-left: 2px solid #ffb703;
      border-right: 2px solid #ffb703;
    }
    .toggler-icon {
        display: inline-block;
        margin-right: 5px;
        transition: transform 0.2s ease-in-out;
    }
  </style>
</head>
<body class="bg-gray-900 text-gray-100 flex min-h-screen">
<main class="flex-1 bg-gray-900 p-6 relative">

  <!-- Filtro de Ano/Mês -->
  <form method="get" class="mb-4 flex gap-2 items-end">
    <label>
      Ano:
      <select name="ano" class="text-black rounded p-1">
        <?php for ($a = date('Y')-2; $a <= date('Y')+1; $a++): ?>
          <option value="<?=$a?>" <?=$a==$anoAtual?'selected':''?>><?=$a?></option>
        <?php endfor; ?>
      </select>
    </label>
    <label>
      Mês:
      <select name="mes" class="text-black rounded p-1">
        <?php foreach ($meses as $num => $nome): ?>
          <option value="<?=$num?>" <?=$num==$mesAtual?'selected':''?>><?=$nome?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit" class="bg-yellow-400 text-black px-3 py-1 rounded font-bold">Filtrar</button>
  </form>

  <h1 class="text-2xl font-bold text-yellow-400 mb-6">
    DRE Financeiro - <?=$anoAtual?> / <?=$meses[$mesAtual]?>
  </h1>
  <span id="msg-atualiza-json" class="ml-2 text-xs"></span>
  
  <!-- Tabela de Simulação com Blocos -->
  <table id="tabelaSimulacao" class="min-w-full text-xs mx-auto border border-gray-700 rounded">
    <thead>
      <tr>
        <th rowspan="2" class="p-2 text-center bg-gray-800">CATEGORIA &gt; SUBCATEGORIA</th>
        <th colspan="2" class="p-2 text-center bg-blue-900">MÉDIA -3M</th>
        <th colspan="3" class="p-2 text-center bg-green-900">SIMULAÇÃO</th>
        <th colspan="2" class="p-2 text-center bg-red-900">META</th>
        <th colspan="3" class="p-2 text-center bg-purple-900">REALIZADO X META</th>
      </tr>
      <tr>
        <th class="p-2 text-center bg-blue-700">Média -3m</th>
        <th class="p-2 text-center bg-blue-700">% Média-3m S/ FAT.</th>
        <th class="p-2 text-center bg-green-700">Simulação</th>
        <th class="p-2 text-center bg-green-700">% Simulação S/ FAT.</th>
        <th class="p-2 text-center bg-green-700">Diferença (R$)</th>
        <th class="p-2 text-center bg-red-700">Meta</th>
        <th class="p-2 text-center bg-red-700">% Meta s/ FAT.</th>
        <th class="p-2 text-center bg-purple-700">Realizado</th>
        <th class="p-2 text-center bg-purple-700">% Realizado s/ Meta.</th>
        <th class="p-2 text-center bg-purple-700">Comparação Meta</th>
      </tr>
    </thead>
    <tbody>
      <!-- 1. RECEITA OPERACIONAL (continua editável) -->
      <tr class="dre-cat">
        <td class="p-2 text-left"><span class="toggler-icon"></span>RECEITA BRUTA</td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($media3Rec,2,',','.') ?></td>
        <td class="p-2 text-center">100,00%</td>
        <td class="p-2 text-right">
          <input type="text" data-receita="1" class="simul-valor font-bold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
                 value="<?= number_format($media3Rec,2,',','.') ?>">
        </td>
        <td class="p-2 text-center">
          <input type="text" class="simul-perc font-bold bg-gray-800 text-yellow-400 text-center rounded px-1" style="width:60px;"
                 value="100,00%">
        </td>
        <td class="p-2 text-right"><?= 'R$ '.number_format(0,2,',','.') ?></td>
        <td class="p-2 text-right"><?= isset($metasArray['RECEITA BRUTA']['']) ? 'R$ '.number_format($metasArray['RECEITA BRUTA'][''],2,',','.') : '' ?></td>
        <td class="p-2 text-center">
          <?= ($media3Rec > 0 && isset($metasArray['RECEITA BRUTA'][''])) ? number_format(($metasArray['RECEITA BRUTA']['']/$media3Rec)*100,2,',','.') .'%' : '' ?>
        </td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($atualRec,2,',','.') ?></td>
        <td class="p-2 text-center">
          <?php
            $meta_rb_val = $metasArray['RECEITA BRUTA'][''] ?? null;
            if (isset($meta_rb_val) && $meta_rb_val != 0) {
              echo number_format(($atualRec / $meta_rb_val) * 100, 2, ',', '.') . '%';
            } else { echo '-'; }
          ?>
        </td>
        <td class="p-2 text-center">
          <?php 
            $meta_val = $metasArray['RECEITA BRUTA'][''] ?? null;
            $realizado_val = $atualRec;
            if (isset($meta_val)) {
              $comparacao = $meta_val - $realizado_val;
              $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
              echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
            } else { echo '-'; }
          ?>
        </td>
      </tr>

      
     <!-- RECEITA BRUTA -->


<!-- TRIBUTOS (ajustado para seguir o padrão das demais linhas principais) -->
<?php if(isset($matrizOrdenada['TRIBUTOS'])): ?>
  <tr class="dre-cat cat_trib">
    <td class="p-2 text-left"><span class="toggler-icon">►</span>TRIBUTOS</td>
    <td class="p-2 text-right"><?= 'R$ '.number_format($media3Cat['TRIBUTOS'] ?? 0,2,',','.') ?></td>
    <td class="p-2 text-center"><?= $media3Rec>0 ? number_format((($media3Cat['TRIBUTOS'] ?? 0)/$media3Rec)*100,2,',','.') .'%' : '-' ?></td>
    <td class="p-2 text-right">
      <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
             value="<?= number_format($media3Cat['TRIBUTOS'] ?? 0,2,',','.') ?>">
    </td>
    <td class="p-2 text-center">
      <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
             style="width:60px;" value="<?= $media3Rec>0 ? number_format((($media3Cat['TRIBUTOS'] ?? 0)/$media3Rec)*100,2,',','.') : '-' ?>">
    </td>
    <td class="p-2 text-right">-</td> <!-- Diferença -->
    <td class="p-2 text-right"><?= isset($metasArray['TRIBUTOS']['']) ? 'R$ '.number_format($metasArray['TRIBUTOS'][''],2,',','.') : '' ?></td>
    <td class="p-2 text-center"><?= ($media3Rec>0 && isset($metasArray['TRIBUTOS'][''])) ? number_format(($metasArray['TRIBUTOS']['']/$media3Rec)*100,2,',','.') .'%' : '' ?></td>
    <td class="p-2 text-right"><?= 'R$ '.number_format($atualCat['TRIBUTOS'] ?? 0,2,',','.') ?></td>
    <td class="p-2 text-center">
      <?php
        $meta_t_val = $metasArray['TRIBUTOS'][''] ?? null;
        $realizado_t_val = $atualCat['TRIBUTOS'] ?? 0;
        if (isset($meta_t_val) && $meta_t_val != 0) {
          echo number_format(($realizado_t_val / $meta_t_val) * 100, 2, ',', '.') . '%';
        } else { echo '-'; }
      ?>
    </td>
    <td class="p-2 text-center">
      <?php 
        $meta_val = $metasArray['TRIBUTOS'][''] ?? null;
        $realizado_val = $atualCat['TRIBUTOS'] ?? 0;
        if (isset($meta_val)) {
          $comparacao = $meta_val - $realizado_val;
          $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
          echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
        } else { echo '-'; } ?>
    </td>
  </tr>
  <?php foreach($matrizOrdenada['TRIBUTOS'] as $sub => $mesValores): ?>
    <?php if($sub): ?>
      <tr class="dre-sub dre-hide">
        <td class="p-2 text-left" style="padding-left:2em;"><?= htmlspecialchars($sub) ?></td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($media3Sub['TRIBUTOS'][$sub] ?? 0,2,',','.') ?></td>
        <td class="p-2 text-center">
          <?= $media3Rec>0 ? number_format((($media3Sub['TRIBUTOS'][$sub] ?? 0)/$media3Rec)*100,2,',','.') .'%' : '-' ?>
        </td>
        <td class="p-2 text-right">
          <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
                 value="<?= number_format($media3Sub['TRIBUTOS'][$sub] ?? 0,2,',','.') ?>">
        </td>
        <td class="p-2 text-center">
          <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
                 style="width:60px;" value="<?= $media3Rec>0 ? number_format((($media3Sub['TRIBUTOS'][$sub] ?? 0)/$media3Rec)*100,2,',','.') : '-' ?>">
        </td>
        <td class="p-2 text-right">-</td>
        <td class="p-2 text-right"><?= isset($metasArray['TRIBUTOS'][$sub]) ? 'R$ '.number_format($metasArray['TRIBUTOS'][$sub],2,',','.') : '' ?></td>
        <td class="p-2 text-center"><?= ($media3Rec>0 && isset($metasArray['TRIBUTOS'][$sub])) ? number_format(($metasArray['TRIBUTOS'][$sub]/$media3Rec)*100,2,',','.') .'%' : '' ?></td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($atualSub['TRIBUTOS'][$sub] ?? 0,2,',','.') ?></td>
        <td class="p-2 text-center">
          <?php
            $meta_ts_val = $metasArray['TRIBUTOS'][$sub] ?? null;
            $realizado_ts_val = $atualSub['TRIBUTOS'][$sub] ?? 0;
            if (isset($meta_ts_val) && $meta_ts_val != 0) {
              echo number_format(($realizado_ts_val / $meta_ts_val) * 100, 2, ',', '.') . '%';
            } else { echo '-'; }
          ?>
        </td>
        <td class="p-2 text-center">
          <?php 
            $meta_val = $metasArray['TRIBUTOS'][$sub] ?? null;
            $realizado_val = $atualSub['TRIBUTOS'][$sub] ?? 0;
            if (isset($meta_val)) {
              $comparacao = $meta_val - $realizado_val;
              $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
              echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
            } else { echo '-'; } ?>
        </td>
      </tr>
    <?php endif; ?>
  <?php endforeach; ?>
<?php endif; ?>

<!-- RECEITA LÍQUIDA (RECEITA BRUTA - TRIBUTOS) -->
<?php
  $mediaReceitaLiquida = $media3Rec - ($media3Cat['TRIBUTOS'] ?? 0);
  $atualReceitaLiquida = $atualRec - ($atualCat['TRIBUTOS'] ?? 0);

  // As variáveis de simulação ($simReceitaLiquida, $simLucroBruto, $simLucroLiquido)
  // são calculadas e preenchidas pelo JavaScript.
?>
<tr id="rowFatLiquido" class="dre-cat" style="background:#1a4c2b;">
  <td class="p-2 text-left"><span class="toggler-icon"></span>RECEITA LÍQUIDA (RECEITA BRUTA - TRIBUTOS)</td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($mediaReceitaLiquida,2,',','.') ?></td>
  <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format(($mediaReceitaLiquida / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?></td>
  <td class="p-2 text-right">
    <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1" readonly>
  </td>
  <td class="p-2 text-center">
    <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1" style="width:60px;" readonly>
  </td>
  <td class="p-2 text-right">-</td> <!-- Diferença -->
  <td class="p-2 text-right"></td> <!-- Meta -->
  <td class="p-2 text-center"></td> <!-- % Meta -->
  <td class="p-2 text-right"><?= 'R$ '.number_format($atualReceitaLiquida,2,',','.') ?></td>
  <td class="p-2 text-center">-</td> <!-- % Realizado s/ Meta para linha calculada -->
  <td class="p-2 text-center">-</td> <!-- Comp. Meta -->
</tr>

<!-- CUSTO VARIÁVEL (categoria e subcategorias) -->
<?php if(isset($matrizOrdenada['CUSTO VARIÁVEL'])): ?>
  <tr class="dre-cat cat_cvar">
    <td class="p-2 text-left"><span class="toggler-icon">►</span>CUSTO VARIÁVEL</td>
    <td class="p-2 text-right"><?= 'R$ '.number_format($media3Cat['CUSTO VARIÁVEL'] ?? 0,2,',','.') ?></td>
    <td class="p-2 text-center"><?= $media3Rec>0 ? number_format((($media3Cat['CUSTO VARIÁVEL'] ?? 0)/$media3Rec)*100,2,',','.') .'%' : '-' ?></td>
    <td class="p-2 text-right">
      <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
             value="<?= number_format($media3Cat['CUSTO VARIÁVEL'] ?? 0,2,',','.') ?>">
    </td>
    <td class="p-2 text-center">
      <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
             style="width:60px;" value="<?= $media3Rec>0 ? number_format((($media3Cat['CUSTO VARIÁVEL'] ?? 0)/$media3Rec)*100,2,',','.') : '-' ?>">
    </td>
    <td class="p-2 text-right">-</td> <!-- Diferença -->
    <td class="p-2 text-right"><?= isset($metasArray['CUSTO VARIÁVEL']['']) ? 'R$ '.number_format($metasArray['CUSTO VARIÁVEL'][''],2,',','.') : '' ?></td>
    <td class="p-2 text-center"><?= ($media3Rec>0 && isset($metasArray['CUSTO VARIÁVEL'][''])) ? number_format(($metasArray['CUSTO VARIÁVEL']['']/$media3Rec)*100,2,',','.') .'%' : '' ?></td>
    <td class="p-2 text-right"><?= 'R$ '.number_format($atualCat['CUSTO VARIÁVEL'] ?? 0,2,',','.') ?></td>
    <td class="p-2 text-center">
      <?php
        $meta_cv_val = $metasArray['CUSTO VARIÁVEL'][''] ?? null;
        $realizado_cv_val = $atualCat['CUSTO VARIÁVEL'] ?? 0;
        if (isset($meta_cv_val) && $meta_cv_val != 0) {
          echo number_format(($realizado_cv_val / $meta_cv_val) * 100, 2, ',', '.') . '%';
        } else { echo '-'; }
      ?>
    </td>
    <td class="p-2 text-center">
      <?php 
        $meta_val = $metasArray['CUSTO VARIÁVEL'][''] ?? null;
        $realizado_val = $atualCat['CUSTO VARIÁVEL'] ?? 0;
        if (isset($meta_val)) {
          $comparacao = $meta_val - $realizado_val;
          $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
          echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
        } else { echo '-'; } ?>
    </td>
</tr>

<?php endif; ?>
  <?php foreach($matrizOrdenada['CUSTO VARIÁVEL'] as $sub => $mesValores): ?>
    <?php if($sub): // Garante que não tentamos exibir uma subcategoria vazia ?>
      <tr class="dre-sub dre-hide">
  <td class="p-2 text-left" style="padding-left:2em;"><?= htmlspecialchars($sub) ?></td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($media3Sub['CUSTO VARIÁVEL'][$sub] ?? 0,2,',','.') ?></td>
  <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format((($media3Sub['CUSTO VARIÁVEL'][$sub] ?? 0)/$media3Rec)*100,2,',','.') .'%' : '-' ?></td>
  <td class="p-2 text-right">
    <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
           value="<?= number_format($media3Sub['CUSTO VARIÁVEL'][$sub] ?? 0,2,',','.') ?>">
  </td>
  <td class="p-2 text-center">
    <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
           style="width:60px;" value="<?= $media3Rec > 0 ? number_format((($media3Sub['CUSTO VARIÁVEL'][$sub] ?? 0)/$media3Rec)*100,2,',','.') : '-' ?>">
  </td>
  <td class="p-2 text-right">-</td>
  <td class="p-2 text-right"><?= isset($metasArray['CUSTO VARIÁVEL'][$sub]) ? 'R$ '.number_format($metasArray['CUSTO VARIÁVEL'][$sub],2,',','.') : '' ?></td>
  <td class="p-2 text-center"><?= ($media3Rec > 0 && isset($metasArray['CUSTO VARIÁVEL'][$sub])) ? number_format(($metasArray['CUSTO VARIÁVEL'][$sub]/$media3Rec)*100,2,',','.') .'%' : '' ?></td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($atualSub['CUSTO VARIÁVEL'][$sub] ?? 0,2,',','.') ?></td>
  <td class="p-2 text-center">
    <?php
      $meta_cvs_val = $metasArray['CUSTO VARIÁVEL'][$sub] ?? null;
      $realizado_cvs_val = $atualSub['CUSTO VARIÁVEL'][$sub] ?? 0;
      if (isset($meta_cvs_val) && $meta_cvs_val != 0) {
        echo number_format(($realizado_cvs_val / $meta_cvs_val) * 100, 2, ',', '.') . '%';
      } else { echo '-'; }
    ?>
  </td>
  <td class="p-2 text-center">
    <?php 
      $meta_val = $metasArray['CUSTO VARIÁVEL'][$sub] ?? null;
      $realizado_val = $atualSub['CUSTO VARIÁVEL'][$sub] ?? 0;
      if (isset($meta_val)) {
        $comparacao = $meta_val - $realizado_val;
        $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
        echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
      } else { echo '-'; } ?>
  </td>
</tr>

    <?php endif; ?>
  <?php endforeach; ?>

<!-- LUCRO BRUTO (RECEITA LÍQUIDA - CUSTO VARIÁVEL) -->
<?php
  $mediaLucroBruto = $mediaReceitaLiquida - ($media3Cat['CUSTO VARIÁVEL'] ?? 0);
  $atualLucroBruto = $atualReceitaLiquida - ($atualCat['CUSTO VARIÁVEL'] ?? 0);
?>
<tr id="rowLucroBruto" class="dre-cat" style="background:#102c14;">
  <td class="p-2 text-left"><span class="toggler-icon"></span>LUCRO BRUTO (RECEITA LÍQUIDA - CUSTO VARIÁVEL)</td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($mediaLucroBruto,2,',','.') ?></td>
  <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format(($mediaLucroBruto / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?></td>
  <td class="p-2 text-right">
    <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1" readonly>
  </td>
  <td class="p-2 text-center">
    <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1" style="width:60px;" readonly>
  </td>
  <td class="p-2 text-right">-</td> <!-- Diferença -->
  <td class="p-2 text-right"></td> <!-- Meta -->
  <td class="p-2 text-center"></td> <!-- % Meta -->
  <td class="p-2 text-right"><?= 'R$ '.number_format($atualLucroBruto,2,',','.') ?></td>
  <td class="p-2 text-center">-</td> <!-- % Realizado s/ Meta para linha calculada -->
  <td class="p-2 text-center">-</td> <!-- Comp. Meta -->
</tr>


      <!-- 6. CUSTO FIXO, 7. DESPESA FIXA e 8. DESPESA VENDA (permanecem editáveis) -->
      <?php 
        foreach(['CUSTO FIXO','DESPESA FIXA','DESPESA VENDA'] as $catName): // Loop para CUSTO FIXO, DESPESA FIXA, DESPESA VENDA
          if(isset($matrizOrdenada[$catName])):
      ?>
        <tr class="dre-cat">
  <td class="p-2 text-left"><span class="toggler-icon">►</span><?= $catName ?></td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($media3Cat[$catName] ?? 0,2,',','.') ?></td>
  <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format((($media3Cat[$catName] ?? 0)/$media3Rec)*100,2,',','.') .'%' : '-' ?></td>
  <td class="p-2 text-right">
    <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
           value="<?= number_format($media3Cat[$catName] ?? 0,2,',','.') ?>">
  </td>
  <td class="p-2 text-center">
    <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
           style="width:60px;" value="<?= $media3Rec > 0 ? number_format((($media3Cat[$catName] ?? 0)/$media3Rec)*100,2,',','.') : '-' ?>">
  </td>
  <td class="p-2 text-right">-</td> <!-- Diferença -->
  <td class="p-2 text-right"><?= isset($metasArray[$catName]['']) ? 'R$ '.number_format($metasArray[$catName][''],2,',','.') : '' ?></td>
  <td class="p-2 text-center"><?= ($media3Rec > 0 && isset($metasArray[$catName][''])) ? number_format(($metasArray[$catName]['']/$media3Rec)*100,2,',','.') .'%' : '' ?></td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($atualCat[$catName] ?? 0,2,',','.') ?></td>
  <td class="p-2 text-center">
    <?php
      $meta_c_val = $metasArray[$catName][''] ?? null;
      $realizado_c_val = $atualCat[$catName] ?? 0;
      if (isset($meta_c_val) && $meta_c_val != 0) {
        echo number_format(($realizado_c_val / $meta_c_val) * 100, 2, ',', '.') . '%';
      } else { echo '-'; }
    ?>
  </td>
  <td class="p-2 text-center">
    <?php 
      $meta_val = $metasArray[$catName][''] ?? null;
      $realizado_val = $atualCat[$catName] ?? 0;
      if (isset($meta_val)) {
        $comparacao = $meta_val - $realizado_val;
        $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
        echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
      } else { echo '-'; } ?>
  </td>
</tr>

        <?php foreach($matrizOrdenada[$catName] as $sub => $mesValores): ?>
          <?php if($sub): ?>
           <tr class="dre-sub dre-hide">
  <td class="p-2 text-left" style="padding-left:2em;"><?= htmlspecialchars($sub) ?></td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($media3Sub[$catName][$sub] ?? 0,2,',','.') ?></td>
  <td class="p-2 text-center">
    <?= $media3Rec > 0 ? number_format((($media3Sub[$catName][$sub] ?? 0) / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?>
  </td>
  <td class="p-2 text-right">
    <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
           value="<?= number_format($media3Sub[$catName][$sub] ?? 0, 2, ',', '.') ?>">
  </td>
  <td class="p-2 text-center">
    <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
           style="width:60px;" value="<?= $media3Rec > 0 ? number_format((($media3Sub[$catName][$sub] ?? 0) / $media3Rec) * 100, 2, ',', '.') : '-' ?>">
  </td>
  <td class="p-2 text-right">-</td>
  <td class="p-2 text-right"><?= isset($metasArray[$catName][$sub]) ? 'R$ ' . number_format($metasArray[$catName][$sub], 2, ',', '.') : '' ?></td>
  <td class="p-2 text-center"><?= ($media3Rec > 0 && isset($metasArray[$catName][$sub])) ? number_format(($metasArray[$catName][$sub] / $media3Rec) * 100, 2, ',', '.') . '%' : '' ?></td>
  <td class="p-2 text-right"><?= 'R$ ' . number_format($atualSub[$catName][$sub] ?? 0, 2, ',', '.') ?></td>
  <td class="p-2 text-center">
    <?php
      $meta_cs_val = $metasArray[$catName][$sub] ?? null;
      $realizado_cs_val = $atualSub[$catName][$sub] ?? 0;
      if (isset($meta_cs_val) && $meta_cs_val != 0) {
        echo number_format(($realizado_cs_val / $meta_cs_val) * 100, 2, ',', '.') . '%';
      } else { echo '-'; }
    ?>
  </td>
  <td class="p-2 text-center">
    <?php 
      $meta_val = $metasArray[$catName][$sub] ?? null;
      $realizado_val = $atualSub[$catName][$sub] ?? 0;
      if (isset($meta_val)) {
        $comparacao = $meta_val - $realizado_val;
        $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
        echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
      } else { echo '-'; } ?>
  </td>
</tr>

          <?php endif; ?>
        <?php endforeach; ?>
      <?php 
          endif;
        endforeach;
      ?>

      <!-- 9. LUCRO LIQUIDO = LUCRO BRUTO - (CUSTO FIXO + DESPESA FIXA + DESPESA VENDA) (DINÂMICO, não editável) -->
      <?php 
?>
<tr class="dre-cat" style="background:#102c14;">
  <td class="p-2 text-left"><span class="toggler-icon"></span>LUCRO LÍQUIDO</td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($mediaLucroLiquido,2,',','.') ?></td>
  <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format(($mediaLucroLiquido / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?></td>
  <td class="p-2 text-right">
    <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1" readonly>
  </td>
  <td class="p-2 text-center">
    <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1" style="width:60px;" readonly>
  </td>
  <td class="p-2 text-right">-</td>
  <td class="p-2 text-right"><?= isset($metasArray['RECEITA BRUTA']['']) ? 'R$ '.number_format($metaLucroLiquido ?? 0,2,',','.') : '' ?></td>
  <td class="p-2 text-center"><?= ($media3Rec > 0 && isset($metasArray['RECEITA BRUTA'][''])) ? number_format((($metaLucroLiquido ?? 0) / $media3Rec) * 100, 2, ',', '.') . '%' : '' ?></td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($atualLucroLiquido,2,',','.') ?></td>
  <td class="p-2 text-center">
    <?php
      $meta_ll_val = $metaLucroLiquido ?? null; // Meta para Lucro Líquido já calculada
      if (isset($meta_ll_val) && $meta_ll_val != 0 && isset($metasArray['RECEITA BRUTA'][''])) { // Só mostra se houver meta de receita base
        echo number_format(($atualLucroLiquido / $meta_ll_val) * 100, 2, ',', '.') . '%';
      } else { echo '-'; }
    ?>
  </td>
  <td class="p-2 text-center">
    <?php 
      $meta_val = $metaLucroLiquido ?? null; // Meta para Lucro Líquido já calculada
      $realizado_val = $atualLucroLiquido;
      if (isset($meta_val) && isset($metasArray['RECEITA BRUTA'][''])) { // Só mostra se houver meta de receita base
        $comparacao = $meta_val - $realizado_val;
        $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
        echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
      } else { echo '-'; } ?>
  </td>
</tr>

      <!-- ==================== [NOVA SEÇÃO: RECEITAS NAO OPERACIONAIS] ==================== -->
      <tr class="dre-cat-principal bg-gray-700 text-white font-bold">
        <td class="p-2 text-left" colspan="1"><span class="toggler-icon">►</span>RECEITAS NAO OPERACIONAIS</td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($totalMedia3OutrasRecGlobal,2,',','.') ?></td>
        <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format(($totalMedia3OutrasRecGlobal / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?></td>
        <td class="p-2 text-right simul-total-cat" data-cat-total-simul="RECEITAS NAO OPERACIONAIS">
            <?= 'R$ '.number_format($totalMedia3OutrasRecGlobal,2,',','.') ?>
        </td> <!-- Total Simulado (será atualizado por JS) -->
        <td class="p-2 text-center simul-perc-cat" data-cat-perc-simul="RECEITAS NAO OPERACIONAIS">
            <?= $media3Rec > 0 ? number_format(($totalMedia3OutrasRecGlobal / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?>
        </td> <!-- % Total Simulado s/ FAT. (será atualizado por JS) -->
        <td class="p-2 text-right">-</td> <!-- Diferença (R$) -->
        <td class="p-2 text-right"></td> <!-- Meta -->
        <td class="p-2 text-center"></td> <!-- % Meta s/ FAT. -->
        <td class="p-2 text-right"><?= 'R$ '.number_format($totalAtualOutrasRecGlobal,2,',','.') ?></td>
        <td class="p-2 text-center">-</td> <!-- % Realizado s/ Meta para linha principal de Outras Receitas -->
        <td class="p-2 text-center">-</td> <!-- Comparação Meta -->
      </tr>

      <?php if (!empty($outrasReceitasPorCatSubMes)): ?>
        <?php foreach ($outrasReceitasPorCatSubMes as $catNomeOR => $subcategoriasOR): ?>
          <!-- Linha da Categoria de Outras Receitas (agora .dre-sub para ser toggleable) -->
          <tr class="dre-sub dre-hide"> <!-- Alterado de dre-subcat-l1 para dre-sub e adicionado dre-hide -->
            <td class="p-2 pl-6 text-left font-semibold" colspan="11"><?= htmlspecialchars($catNomeOR) ?></td>
          </tr>
          <?php foreach ($subcategoriasOR as $subNomeOR => $valoresMensaisOR): ?>
            <?php
              $mediaSubOR = $media3OutrasRecSub[$catNomeOR][$subNomeOR] ?? 0;
              $atualSubOR = $atualOutrasRecSub[$catNomeOR][$subNomeOR] ?? 0;
              // Usar nomes de categoria e subcategoria para data attributes, normalizando-os para JS
              $dataCatKey = htmlspecialchars(str_replace(' ', '_', $catNomeOR));
              $dataSubKey = htmlspecialchars(str_replace(' ', '_', $subNomeOR));
            ?>
            <tr class="dre-sub dre-hide"> <!-- Alterado de dre-subcat-l2 para dre-sub e adicionado dre-hide -->
              <td class="p-2 pl-10 text-left"><?= htmlspecialchars($subNomeOR) ?></td>
              <td class="p-2 text-right"><?= 'R$ '.number_format($mediaSubOR,2,',','.') ?></td>
              <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format(($mediaSubOR / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?></td>
              <td class="p-2 text-right">
                <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
                       data-cat="RECEITAS NAO OPERACIONAIS" data-sub-cat="<?= $dataCatKey ?>" data-sub-sub-cat="<?= $dataSubKey ?>"
                       value="<?= number_format($mediaSubOR,2,',','.') ?>">
              </td>
              <td class="p-2 text-center">
                <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
                       style="width:60px;" data-cat="RECEITAS NAO OPERACIONAIS" data-sub-cat="<?= $dataCatKey ?>" data-sub-sub-cat="<?= $dataSubKey ?>"
                       value="<?= $media3Rec > 0 ? number_format(($mediaSubOR / $media3Rec) * 100, 2, ',', '.') : '-' ?>">
              </td>
              <td class="p-2 text-right">-</td> <!-- Diferença (R$) -->
              <td class="p-2 text-right"></td> <!-- Meta -->
              <td class="p-2 text-center"></td> <!-- % Meta s/ FAT. -->
              <td class="p-2 text-right"><?= 'R$ '.number_format($atualSubOR,2,',','.') ?></td>
              <td class="p-2 text-center">
                <?php
                  // Para Outras Receitas, a meta é salva com Categoria = $catNomeOR e Subcategoria = $subNomeOR
                  $meta_or_val = $metasArray[$catNomeOR][$subNomeOR] ?? null;
                  if (isset($meta_or_val) && $meta_or_val != 0) {
                    echo number_format(($atualSubOR / $meta_or_val) * 100, 2, ',', '.') . '%';
                  } else { echo '-'; }
                ?>
              </td>
              <td class="p-2 text-center">-</td> <!-- Comparação Meta -->
            </tr>
          <?php endforeach; // Fim do loop de subcategorias de Outras Receitas ?>
        <?php endforeach; // Fim do loop de categorias de Outras Receitas ?>
      <?php endif; ?>
      <!-- ==================== [FIM DA NOVA SEÇÃO] ==================== -->

      <!-- 10. INVESTIMENTO INTERNO, INVESTIMENTO EXTERNO e AMORTIZAÇÃO (editáveis) -->
      <?php
        foreach(['INVESTIMENTO INTERNO','INVESTIMENTO EXTERNO','AMORTIZAÇÃO'] as $catName): // AMORTIZAÇÃO pode ter meta, mesmo que não seja exibida como editável em alguns cenários
          if(isset($matrizOrdenada[$catName])):
      ?>
        <tr class="dre-cat">
  <td class="p-2 text-left"><span class="toggler-icon">►</span><?= $catName ?></td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($media3Cat[$catName] ?? 0,2,',','.') ?></td>
  <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format((($media3Cat[$catName] ?? 0)/$media3Rec)*100,2,',','.') .'%' : '-' ?></td>
  <td class="p-2 text-right">
    <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
           value="<?= number_format($media3Cat[$catName] ?? 0,2,',','.') ?>">
  </td>
  <td class="p-2 text-center">
    <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
           style="width:60px;" value="<?= $media3Rec > 0 ? number_format((($media3Cat[$catName] ?? 0)/$media3Rec)*100,2,',','.') : '-' ?>">
  </td>
  <td class="p-2 text-right">-</td> <!-- Diferença -->
  <td class="p-2 text-right"><?= isset($metasArray[$catName]['']) ? 'R$ '.number_format($metasArray[$catName][''],2,',','.') : '' ?></td>
  <td class="p-2 text-center"><?= ($media3Rec > 0 && isset($metasArray[$catName][''])) ? number_format(($metasArray[$catName]['']/$media3Rec)*100,2,',','.') .'%' : '' ?></td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($atualCat[$catName] ?? 0,2,',','.') ?></td>
  <td class="p-2 text-center">
    <?php
      $meta_inv_val = $metasArray[$catName][''] ?? null;
      $realizado_inv_val = $atualCat[$catName] ?? 0;
      if (isset($meta_inv_val) && $meta_inv_val != 0) {
        echo number_format(($realizado_inv_val / $meta_inv_val) * 100, 2, ',', '.') . '%';
      } else { echo '-'; }
    ?>
  </td>
  <td class="p-2 text-center">
    <?php 
      $meta_val = $metasArray[$catName][''] ?? null;
      $realizado_val = $atualCat[$catName] ?? 0;
      if (isset($meta_val)) {
        $comparacao = $meta_val - $realizado_val;
        $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
        echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
      } else { echo '-'; } ?>
  </td>
</tr>
    <?php foreach(($matrizOrdenada[$catName] ?? []) as $sub => $mesValores): ?>
      <?php if($sub): // Garante que não tentamos exibir uma subcategoria vazia ?>
        <tr class="dre-sub dre-hide">
          <td class="p-2 text-left" style="padding-left:2em;"><?= htmlspecialchars($sub) ?></td>
          <td class="p-2 text-right"><?= 'R$ '.number_format($media3Sub[$catName][$sub] ?? 0,2,',','.') ?></td>
          <td class="p-2 text-center">
            <?= $media3Rec>0 ? number_format((($media3Sub[$catName][$sub] ?? 0)/$media3Rec)*100,2,',','.') .'%' : '-' ?>
          </td>
          <td class="p-2 text-right">
            <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
                   value="<?= number_format($media3Sub[$catName][$sub] ?? 0,2,',','.') ?>">
          </td>
          <td class="p-2 text-center">
            <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
                   style="width:60px;" value="<?= $media3Rec>0 ? number_format((($media3Sub[$catName][$sub] ?? 0)/$media3Rec)*100,2,',','.') : '-' ?>">
          </td>
          <td class="p-2 text-right">-</td>
          <td class="p-2 text-right"><?= isset($metasArray[$catName][$sub]) ? 'R$ ' . number_format($metasArray[$catName][$sub], 2, ',', '.') : '' ?></td>
          <td class="p-2 text-center"><?= ($media3Rec > 0 && isset($metasArray[$catName][$sub])) ? number_format(($metasArray[$catName][$sub] / $media3Rec) * 100, 2, ',', '.') . '%' : '' ?></td>
          <td class="p-2 text-right"><?= 'R$ ' . number_format($atualSub[$catName][$sub] ?? 0, 2, ',', '.') ?></td>
          <td class="p-2 text-center">
            <?php
              $meta_invs_val = $metasArray[$catName][$sub] ?? null;
              $realizado_invs_val = $atualSub[$catName][$sub] ?? 0;
              if (isset($meta_invs_val) && $meta_invs_val != 0) {
                echo number_format(($realizado_invs_val / $meta_invs_val) * 100, 2, ',', '.') . '%';
              } else { echo '-'; }
            ?>
          </td>
          <td class="p-2 text-center">
            <?php 
              $meta_val = $metasArray[$catName][$sub] ?? null;
              $realizado_val = $atualSub[$catName][$sub] ?? 0;
              if (isset($meta_val)) {
                $comparacao = $meta_val - $realizado_val;
                $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
                echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
              } else { echo '-'; } ?>
          </td>
        </tr>
      <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>
<?php endforeach; ?>

      <!-- Z - SAIDA DE REPASSE -->
      <?php
        $catNameSR = 'Z - SAIDA DE REPASSE';
        if(isset($matrizOrdenada[$catNameSR])):
      ?>
        <tr class="dre-cat">
          <td class="p-2 text-left"><span class="toggler-icon">►</span><?= htmlspecialchars($catNameSR) ?></td>
          <td class="p-2 text-right"><?= 'R$ '.number_format($media3Cat[$catNameSR] ?? 0,2,',','.') ?></td>
          <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format((($media3Cat[$catNameSR] ?? 0)/$media3Rec)*100,2,',','.') .'%' : '-' ?></td>
          <td class="p-2 text-right">
            <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
                   value="<?= number_format($media3Cat[$catNameSR] ?? 0,2,',','.') ?>">
          </td>
          <td class="p-2 text-center">
            <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
                   style="width:60px;" value="<?= $media3Rec > 0 ? number_format((($media3Cat[$catNameSR] ?? 0)/$media3Rec)*100,2,',','.') : '-' ?>">
          </td>
          <td class="p-2 text-right">-</td> <!-- Diferença -->
          <td class="p-2 text-right"><?= isset($metasArray[$catNameSR]['']) ? 'R$ '.number_format($metasArray[$catNameSR][''],2,',','.') : '' ?></td>
          <td class="p-2 text-center"><?= ($media3Rec > 0 && isset($metasArray[$catNameSR][''])) ? number_format(($metasArray[$catNameSR]['']/$media3Rec)*100,2,',','.') .'%' : '' ?></td>
          <td class="p-2 text-right"><?= 'R$ '.number_format($atualCat[$catNameSR] ?? 0,2,',','.') ?></td>
          <td class="p-2 text-center">
            <?php
              $meta_sr_val = $metasArray[$catNameSR][''] ?? null;
              $realizado_sr_val = $atualCat[$catNameSR] ?? 0;
              if (isset($meta_sr_val) && $meta_sr_val != 0) {
                echo number_format(($realizado_sr_val / $meta_sr_val) * 100, 2, ',', '.') . '%';
              } else { echo '-'; }
            ?>
          </td>
          <td class="p-2 text-center">
            <?php 
              $meta_val = $metasArray[$catNameSR][''] ?? null;
              $realizado_val = $atualCat[$catNameSR] ?? 0;
              if (isset($meta_val)) {
                $comparacao = $meta_val - $realizado_val;
                $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
                echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
              } else { echo '-'; } ?>
          </td>
        </tr>
        <?php foreach(($matrizOrdenada[$catNameSR] ?? []) as $sub => $mesValores): ?>
          <?php if($sub): // Garante que não tentamos exibir uma subcategoria vazia ?>
           <tr class="dre-sub dre-hide">
              <td class="p-2 text-left" style="padding-left:2em;"><?= htmlspecialchars($sub) ?></td>
              <td class="p-2 text-right"><?= 'R$ '.number_format($media3Sub[$catNameSR][$sub] ?? 0,2,',','.') ?></td>
              <td class="p-2 text-center">
                <?= $media3Rec > 0 ? number_format((($media3Sub[$catNameSR][$sub] ?? 0) / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?>
              </td>
              <td class="p-2 text-right">
                <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
                       value="<?= number_format($media3Sub[$catNameSR][$sub] ?? 0, 2, ',', '.') ?>">
              </td>
              <td class="p-2 text-center">
                <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
                       style="width:60px;" value="<?= $media3Rec > 0 ? number_format((($media3Sub[$catNameSR][$sub] ?? 0) / $media3Rec) * 100, 2, ',', '.') : '-' ?>">
              </td>
              <td class="p-2 text-right">-</td> <!-- Diferença -->
              <td class="p-2 text-right"><?= isset($metasArray[$catNameSR][$sub]) ? 'R$ ' . number_format($metasArray[$catNameSR][$sub], 2, ',', '.') : '' ?></td>
              <td class="p-2 text-center"><?= ($media3Rec > 0 && isset($metasArray[$catNameSR][$sub])) ? number_format(($metasArray[$catNameSR][$sub] / $media3Rec) * 100, 2, ',', '.') . '%' : '' ?></td>
              <td class="p-2 text-right"><?= 'R$ ' . number_format($atualSub[$catNameSR][$sub] ?? 0, 2, ',', '.') ?></td>
              <td class="p-2 text-center">
                <?php
                  $meta_srs_val = $metasArray[$catNameSR][$sub] ?? null;
                  $realizado_srs_val = $atualSub[$catNameSR][$sub] ?? 0;
                  if (isset($meta_srs_val) && $meta_srs_val != 0) {
                    echo number_format(($realizado_srs_val / $meta_srs_val) * 100, 2, ',', '.') . '%';
                  } else { echo '-'; }
                ?>
              </td>
              <td class="p-2 text-center">
                <?php 
                  $meta_val = $metasArray[$catNameSR][$sub] ?? null;
                  $realizado_val = $atualSub[$catNameSR][$sub] ?? 0;
                  if (isset($meta_val)) {
                    $comparacao = $meta_val - $realizado_val;
                    $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
                    echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
                  } else { echo '-'; } ?>
              </td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>

      <!-- FLUXO DE CAIXA (CALCULADO) -->
      <tr id="rowFluxoCaixa" class="dre-cat" style="background:#082f49; color: #e0f2fe; font-weight: bold;">
        <td class="p-2 text-left"><span class="toggler-icon"></span>FLUXO DE CAIXA</td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($mediaFluxoCaixa,2,',','.') ?></td>
        <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format(($mediaFluxoCaixa / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?></td>
        <td class="p-2 text-right">
          <input type="text" class="simul-valor font-bold bg-gray-700 text-yellow-300 text-right w-24 rounded px-1" readonly>
        </td>
        <td class="p-2 text-center">
          <input type="text" class="simul-perc font-bold bg-gray-700 text-yellow-300 text-center rounded px-1" style="width:60px;" readonly>
        </td>
        <td class="p-2 text-right">-</td> <!-- Diferença -->
        <td class="p-2 text-right"></td> <!-- Meta -->
        <td class="p-2 text-center"></td> <!-- % Meta -->
        <td class="p-2 text-right"><?= 'R$ '.number_format($atualFluxoCaixa,2,',','.') ?></td>
        <td class="p-2 text-center">-</td> <!-- % Realizado s/ Meta para linha calculada -->
        <td class="p-2 text-center">-</td> <!-- Comp. Meta -->
      </tr>
    </tbody>
  </table>
  
  <!-- Controles para salvar metas -->
  <div class="mt-6 flex items-end gap-4">
    <div>
      <label for="dataMetaInput" class="block text-sm font-medium text-gray-300 mb-1">Data para Salvar Metas:</label>
      <input type="date" id="dataMetaInput" name="dataMetaInput"
             class="bg-gray-700 border border-gray-600 text-gray-100 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
             value="<?= $anoAtual ?>-<?= str_pad($mesAtual, 2, '0', STR_PAD_LEFT) ?>-01">
    </div>
    <button id="pontoEquilibrioBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded font-bold">CALCULAR PONTO DE EQUILÍBRIO</button>
    <button id="salvarMetasBtn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2.5 rounded font-bold">SALVAR METAS OFICIAIS</button>
  </div>
  
  <!-- Controles para Simulações Salvas Localmente -->
  <div class="mt-4 pt-4 border-t border-gray-700 flex items-end gap-4">
    <div>
      <label for="simulationNameInput" class="block text-sm font-medium text-gray-300 mb-1">Nome da Simulação (para salvar local):</label>
      <input type="text" id="simulationNameInput"
             class="bg-gray-700 border border-gray-600 text-gray-100 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
             placeholder="Ex: Cenário Otimista <?= date('Y-m-d') ?>">
    </div>
    <button id="saveSimulationBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded font-bold">SALVAR SIMULAÇÃO ATUAL (LOCAL)</button>
    <div class="flex-grow">
      <label for="loadSimulationSelect" class="block text-sm font-medium text-gray-300 mb-1">Carregar Simulação Salva (Local):</label>
      <select id="loadSimulationSelect" class="bg-gray-700 border border-gray-600 text-gray-100 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
        <option value="">-- Selecione uma Simulação --</option>
      </select>
    </div>
     <button id="loadSelectedSimulationBtn" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2.5 rounded font-bold">CARREGAR</button>
     <button id="deleteSimulationBtn" class="bg-red-700 hover:bg-red-800 text-white px-4 py-2.5 rounded font-bold">EXCLUIR</button>
  </div>
</main>

<!-- Scripts de Atualização, Toggle etc. (mantidos) -->
<script>
// Funções Utilitárias
function parseBRL(str) {
  const stringValue = String(str || '');
  return parseFloat(stringValue.replace(/[R$\s\.]/g, '').replace(',', '.')) || 0;
}

const SIMULATION_STORAGE_PREFIX = 'dreUserSimulation_';

function formatSimValue(value) { // Formata para campos de input de simulação (sem R$)
  return (value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatSimPerc(value, base) { // Formata percentual para campos de input de simulação
  if (base === 0 || isNaN(base) || isNaN(value)) return '0,00%';
  return ((value / base) * 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
}

function getReceitaBrutaSimulada() {
  const inputReceita = document.querySelector('.simul-valor[data-receita="1"]');
  return inputReceita ? parseBRL(inputReceita.value) : 0;
}

function findCategoryRow(categoryName) {
  const catRows = document.querySelectorAll('#tabelaSimulacao tbody tr.dre-cat'); // Seletor mais específico
  for (const row of catRows) {
    const firstCell = row.cells[0];
    if (firstCell) {
      // Clona o conteúdo da célula para não modificar o DOM original ao remover o ícone
      const cellContentClone = firstCell.cloneNode(true);
      const iconSpan = cellContentClone.querySelector('.toggler-icon');
      if (iconSpan) {
        iconSpan.remove(); // Remove o nó do span do clone para limpar o texto
      }
      const cleanedText = cellContentClone.textContent.trim().toUpperCase();
      
      // Usar startsWith porque algumas categorias têm descrições longas (ex: LUCRO BRUTO (...))
      if (cleanedText.startsWith(categoryName.toUpperCase())) {
        return row;
      }
    }
  }
  return null;
}

function getSimulatedValueFromInput(row) {
    if (!row) return 0;
    const input = row.querySelector('input.simul-valor');
    return input ? parseBRL(input.value) : 0;
}

// Atualiza os totais das categorias principais com base em suas subcategorias (se houver)
function atualizarTotaisCategorias() {
  document.querySelectorAll('tr.dre-cat').forEach(catRow => {
    const catSimulValorInput = catRow.querySelector('input.simul-valor');
    // Só atualiza se o input da categoria existir e NÃO for readonly (ex: Receita Bruta é editável, Lucro Líquido não)
    // E se tiver subcategorias.
    if (!catSimulValorInput || catSimulValorInput.readOnly) {
        return;
    }

    let subtotal = 0;
    let hasSubCategories = false;
    let currentRow = catRow.nextElementSibling;

    while (currentRow && currentRow.classList.contains('dre-sub')) {
      hasSubCategories = true;
      const subInput = currentRow.querySelector('input.simul-valor');
      if (subInput) {
        subtotal += parseBRL(subInput.value);
      }
      currentRow = currentRow.nextElementSibling;
    }

    if (hasSubCategories) {
      catSimulValorInput.value = formatSimValue(subtotal);
      // O percentual será atualizado por atualizarPercentuaisSimulacao
    }
  });
}

// Atualiza os campos de percentual de simulação para todas as linhas com inputs .simul-valor
function atualizarPercentuaisSimulacao() {
  const receitaBrutaSimulada = getReceitaBrutaSimulada();
  document.querySelectorAll('tr').forEach(row => {
    const simulValorInput = row.querySelector('input.simul-valor');
    const simulPercInput = row.querySelector('input.simul-perc');

    if (simulValorInput && simulPercInput && !simulPercInput.readOnly) { // Apenas atualiza % de inputs editáveis de %
        const valor = parseBRL(simulValorInput.value);
        // Não atualiza o % da Receita Bruta aqui, pois ele é sempre 100% ou editável manualmente
        if (!simulValorInput.hasAttribute('data-receita')) {
             simulPercInput.value = formatSimPerc(valor, receitaBrutaSimulada);
        }
    }
  });
}

// Atualiza linhas sintéticas (RECEITA LÍQUIDA, LUCRO BRUTO, etc)
function recalcSinteticas() {
  const receitaBrutaSimulada = getReceitaBrutaSimulada();

  const rowTributos = document.querySelector('tr.cat_trib');
  const simTributos = getSimulatedValueFromInput(rowTributos);

  const rowCustoVariavel = document.querySelector('tr.cat_cvar');
  const simCustoVariavel = getSimulatedValueFromInput(rowCustoVariavel);

  // 1. RECEITA LÍQUIDA (rowFatLiquido)
  const simReceitaLiquida = receitaBrutaSimulada - simTributos;
  const rowRL = document.getElementById('rowFatLiquido');
  if (rowRL) {
    const inputRLValor = rowRL.querySelector('input.simul-valor');
    const inputRLPerc = rowRL.querySelector('input.simul-perc');
    if (inputRLValor) inputRLValor.value = formatSimValue(simReceitaLiquida);
    if (inputRLPerc) inputRLPerc.value = formatSimPerc(simReceitaLiquida, receitaBrutaSimulada);
  }

  // 2. LUCRO BRUTO (rowLucroBruto)
  const simLucroBruto = simReceitaLiquida - simCustoVariavel;
  const rowLB = document.getElementById('rowLucroBruto');
  if (rowLB) {
    const inputLBValor = rowLB.querySelector('input.simul-valor');
    const inputLBPerc = rowLB.querySelector('input.simul-perc');
    if (inputLBValor) inputLBValor.value = formatSimValue(simLucroBruto);
    if (inputLBPerc) inputLBPerc.value = formatSimPerc(simLucroBruto, receitaBrutaSimulada);
  }

  // Despesas para Lucro Líquido
  const simCustoFixo = getSimulatedValueFromInput(findCategoryRow('CUSTO FIXO'));
  const simDespesaFixa = getSimulatedValueFromInput(findCategoryRow('DESPESA FIXA'));
  const simDespesaVenda = getSimulatedValueFromInput(findCategoryRow('DESPESA VENDA'));
  const totalOutrasDespesasSim = simCustoFixo + simDespesaFixa + simDespesaVenda;

  // 3. LUCRO LÍQUIDO
  const simLucroLiquido = simLucroBruto - totalOutrasDespesasSim;
  const rowLL = findCategoryRow('LUCRO LÍQUIDO');
  if (rowLL) {
    const inputLLValor = rowLL.querySelector('input.simul-valor');
    const inputLLPerc = rowLL.querySelector('input.simul-perc');
    if (inputLLValor) inputLLValor.value = formatSimValue(simLucroLiquido);
    if (inputLLPerc) inputLLPerc.value = formatSimPerc(simLucroLiquido, receitaBrutaSimulada);
  }

  // 4. FLUXO DE CAIXA
  // (LUCRO LIQUIDO + RECEITAS NAO OPERACIONAIS ) - (INVESTIMENTO INTERNO + INVESTIMENTO EXTERNO + Z - SAIDA DE REPASSE)
  let simReceitasNaoOp = 0;
  document.querySelectorAll('input.simul-valor[data-cat="RECEITAS NAO OPERACIONAIS"]').forEach(input => {
    simReceitasNaoOp += parseBRL(input.value);
  });

  const simInvestInterno = getSimulatedValueFromInput(findCategoryRow('INVESTIMENTO INTERNO'));
  const simInvestExterno = getSimulatedValueFromInput(findCategoryRow('INVESTIMENTO EXTERNO'));
  const simSaidaRepasse  = getSimulatedValueFromInput(findCategoryRow('Z - SAIDA DE REPASSE'));
  const simAmortizacao   = getSimulatedValueFromInput(findCategoryRow('AMORTIZAÇÃO')); // Será 0 se a linha não existir

  const simFluxoCaixa = (simLucroLiquido + simReceitasNaoOp) - (simInvestInterno + simInvestExterno + simSaidaRepasse + simAmortizacao);

  const rowFC = document.getElementById('rowFluxoCaixa');
  if (rowFC) {
    const inputFCValor = rowFC.querySelector('input.simul-valor');
    const inputFCPerc = rowFC.querySelector('input.simul-perc');
    if (inputFCValor) inputFCValor.value = formatSimValue(simFluxoCaixa);
    if (inputFCPerc) inputFCPerc.value = formatSimPerc(simFluxoCaixa, receitaBrutaSimulada);
  }


}

document.addEventListener('DOMContentLoaded', function() {
  // Função para recalcular tudo
  function recalcularTudo() {
    atualizarTotaisCategorias(); // Soma subcategorias para totais de categoria (se aplicável)
    atualizarPercentuaisSimulacao(); // Atualiza % de linhas editáveis
    recalcSinteticas(); // Calcula linhas sintéticas (Receita Líquida, Lucro Bruto, Lucro Líquido)
  }

  // Adiciona listener para todos os inputs de simulação de valor
  document.querySelectorAll('.simul-valor').forEach(function(input) {
    input.addEventListener('input', function() {
      recalcularTudo();
    });
  });

  // Adiciona listener para os inputs de simulação de percentual (caso o usuário edite diretamente o %)
  document.querySelectorAll('input.simul-perc').forEach(function(inputPerc) {
    inputPerc.addEventListener('input', function() {
        const row = inputPerc.closest('tr');
        const inputValor = row.querySelector('input.simul-valor');
        const receitaBrutaSimulada = getReceitaBrutaSimulada();

        if (inputValor && !inputValor.readOnly && receitaBrutaSimulada > 0) {
            const percentual = parseBRL(inputPerc.value); // parseBRL pode lidar com '%'
            const novoValor = (percentual / 100) * receitaBrutaSimulada;
            inputValor.value = formatSimValue(novoValor);
            recalcularTudo(); // Recalcula tudo após alterar valor via percentual
        }
    });
  });

  // Cálculo inicial ao carregar a página
  recalcularTudo();

  // --- INÍCIO: Funcionalidade de Expandir/Recolher Subcategorias ---
  document.querySelectorAll('tr.dre-cat').forEach(catRow => {
    const togglerIcon = catRow.querySelector('.toggler-icon');
    
    // Verifica se há subcategorias para esta categoria
    let hasSubcategories = false;
    let nextRow = catRow.nextElementSibling;
    if (nextRow && nextRow.classList.contains('dre-sub')) {
        hasSubcategories = true;
    }

    if (!hasSubcategories && togglerIcon) { // Se não tem subcategorias, remove o ícone
        togglerIcon.style.display = 'none'; 
        return; // Não adiciona listener se não há o que expandir/recolher
    }

    catRow.addEventListener('click', function(event) {
      // Impede que o clique no input de simulação dispare o toggle
      if (event.target.tagName === 'INPUT') return;

      let currentRow = this.nextElementSibling;
      while (currentRow && currentRow.classList.contains('dre-sub')) {
        currentRow.classList.toggle('dre-hide');
        currentRow = currentRow.nextElementSibling;
      }
      // Atualiza o ícone
      if (togglerIcon) {
        togglerIcon.textContent = (this.nextElementSibling && !this.nextElementSibling.classList.contains('dre-hide')) ? '▼' : '►';
      }
    });
  });
  // --- FIM: Funcionalidade de Expandir/Recolher Subcategorias ---

  // --- INÍCIO: Funcionalidade de Salvar/Carregar Simulação Local ---
  function populateSimulationList() {
    const select = document.getElementById('loadSimulationSelect');
    const currentSelectedValue = select.value;
    select.innerHTML = '<option value="">-- Selecione uma Simulação --</option>'; // Clear existing options

    const simulations = [];
    for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key.startsWith(SIMULATION_STORAGE_PREFIX)) {
            const simulationName = key.substring(SIMULATION_STORAGE_PREFIX.length);
            simulations.push(simulationName);
        }
    }
    simulations.sort(); // Sort alphabetically

    simulations.forEach(simulationName => {
        const option = document.createElement('option');
        option.value = simulationName;
        option.textContent = simulationName;
        select.appendChild(option);
    });
    if (simulations.includes(currentSelectedValue)) {
        select.value = currentSelectedValue;
    }
  }

  document.getElementById('saveSimulationBtn').addEventListener('click', function() {
    const nameInput = document.getElementById('simulationNameInput');
    let simulationName = nameInput.value.trim();

    if (!simulationName) {
        simulationName = prompt('Digite um nome para esta simulação:', 'Simulação ' + new Date().toLocaleDateString('pt-BR') + ' ' + new Date().toLocaleTimeString('pt-BR'));
        if (!simulationName) return; // User cancelled
    }

    const storageKey = SIMULATION_STORAGE_PREFIX + simulationName;

    if (localStorage.getItem(storageKey)) {
        if (!confirm(`Já existe uma simulação com o nome "${simulationName}". Deseja sobrescrevê-la?`)) {
            return;
        }
    }

    const simulationData = [];
    let categoriaAtualContexto = '';

    document.querySelectorAll('#tabelaSimulacao tbody tr').forEach(row => {
        const primeiroTd = row.cells[0];
        if (!primeiroTd) return;

        const inputValorSimul = row.querySelector('input.simul-valor');

        if (row.classList.contains('dre-cat')) {
             categoriaAtualContexto = primeiroTd.textContent.trim();
        }

        if (!inputValorSimul || inputValorSimul.readOnly) {
            return; 
        }

        const valorSimulCurrent = parseBRL(inputValorSimul.value);
        let itemKey = '';

        if (row.classList.contains('dre-cat')) {
            const cat = primeiroTd.textContent.trim();
            itemKey = `${cat}|`; 
            simulationData.push({ key: itemKey, valor: valorSimulCurrent });
        } else if (row.classList.contains('dre-sub')) {
            if (categoriaAtualContexto) {
                const sub = primeiroTd.textContent.trim();
                itemKey = `${categoriaAtualContexto}|${sub}`;
                simulationData.push({ key: itemKey, valor: valorSimulCurrent });
            }
        } else if (row.classList.contains('dre-subcat-l2')) {
            if (inputValorSimul.dataset.cat === "RECEITAS NAO OPERACIONAIS" &&
                inputValorSimul.dataset.subCat && inputValorSimul.dataset.subSubCat) {
                const rnoCat = inputValorSimul.dataset.subCat.replace(/_/g, ' ');
                const rnoSubCat = inputValorSimul.dataset.subSubCat.replace(/_/g, ' ');
                itemKey = `RECEITAS NAO OPERACIONAIS|${rnoCat}|${rnoSubCat}`;
                simulationData.push({ key: itemKey, valor: valorSimulCurrent });
            }
        }
    });

    if (simulationData.length > 0) {
        try {
            localStorage.setItem(storageKey, JSON.stringify(simulationData));
            alert(`Simulação "${simulationName}" salva localmente com sucesso!`);
            nameInput.value = ''; 
            populateSimulationList();
        } catch (e) {
            alert('Erro ao salvar simulação: ' + e.message + '. O localStorage pode estar cheio.');
        }
    } else {
        alert('Nenhum dado de simulação editável encontrado para salvar.');
    }
  });

  document.getElementById('loadSelectedSimulationBtn').addEventListener('click', function() {
    const select = document.getElementById('loadSimulationSelect');
    const simulationName = select.value;

    if (!simulationName) {
        alert('Por favor, selecione uma simulação para carregar.');
        return;
    }

    const storageKey = SIMULATION_STORAGE_PREFIX + simulationName;
    const storedData = localStorage.getItem(storageKey);

  // Salvar Metas
  document.getElementById('salvarMetasBtn').addEventListener('click', function() {
    const botaoSalvar = this;
    botaoSalvar.disabled = true; // Desabilitar botão para evitar cliques duplos
    botaoSalvar.textContent = 'SALVANDO...';

    const metasParaSalvar = [];
    // Construir a data da meta com base no filtro da DRE (primeiro dia do mês)
    const dataMetaInput = document.getElementById('dataMetaInput');
    const dataMeta = dataMetaInput.value;

    if (!dataMeta) {
        alert('Por favor, selecione uma data para salvar as metas.');
        botaoSalvar.disabled = false;
        botaoSalvar.textContent = 'SALVAR METAS OFICIAIS';
        return;
    }

    let categoriaAtualContexto = '';
    document.querySelectorAll('#tabelaSimulacao tbody tr').forEach(row => {
        const primeiroTd = row.cells[0];
        if (!primeiroTd) return;

        const inputValorSimul = row.querySelector('input.simul-valor');

        // Atualizar contexto de categoria mesmo se a linha de categoria não for uma meta em si (ex: se for readonly)
        if (row.classList.contains('dre-cat')) {
             categoriaAtualContexto = primeiroTd.textContent.trim();
        }

        // Pular linhas que não têm input de valor ou cujo input é readonly
        if (!inputValorSimul || inputValorSimul.readOnly) {
            return;
        }

        const valorMeta = parseBRL(inputValorSimul.value);
        let categoriaMeta = '';
        let subcategoriaMeta = '';

        if (row.classList.contains('dre-cat')) {
            categoriaMeta = primeiroTd.textContent.trim(); // Já é o categoriaAtualContexto
            subcategoriaMeta = ''; // Meta para a categoria principal
            metasParaSalvar.push({ categoria: categoriaMeta, subcategoria: subcategoriaMeta, valor: valorMeta });

        } else if (row.classList.contains('dre-sub')) {
            if (categoriaAtualContexto) { // Garante que temos um contexto de categoria
                categoriaMeta = categoriaAtualContexto;
                subcategoriaMeta = primeiroTd.textContent.trim();
                metasParaSalvar.push({ categoria: categoriaMeta, subcategoria: subcategoriaMeta, valor: valorMeta });
            }
        } else if (row.classList.contains('dre-subcat-l2')) { // Para RECEITAS NAO OPERACIONAIS
            if (inputValorSimul.dataset.cat === "RECEITAS NAO OPERACIONAIS" &&
                inputValorSimul.dataset.subCat && inputValorSimul.dataset.subSubCat) {

                categoriaMeta = inputValorSimul.dataset.subCat.replace(/_/g, ' ');
                subcategoriaMeta = inputValorSimul.dataset.subSubCat.replace(/_/g, ' ');
                metasParaSalvar.push({ categoria: categoriaMeta, subcategoria: subcategoriaMeta, valor: valorMeta });
            }
        }
    });

    if (metasParaSalvar.length === 0) {
        alert('Nenhuma meta editável encontrada para salvar.');
        botaoSalvar.disabled = false;
        botaoSalvar.textContent = 'SALVAR METAS OFICIAIS';
        return;
    }

    // console.log('Enviando para salvar:', { metas: metasParaSalvar, data: dataMeta });

    fetch('/modules/financeiro/salvar_metas.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ metas: metasParaSalvar, data: dataMeta }),
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => { // Tenta ler o corpo do erro como texto
                throw new Error(`Erro ${response.status}: ${response.statusText}. Detalhes: ${text}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.sucesso) {
            alert('Metas salvas com sucesso!');
        } else {
            alert('Erro ao salvar metas: ' + (data.erro || 'Erro desconhecido retornado pelo servidor.'));
        }
    })
    .catch((error) => {
        console.error('Erro na requisição AJAX:', error);
        alert(`Erro ao conectar com o servidor para salvar metas. ${error.message}`);
    })
    .finally(() => {
        botaoSalvar.disabled = false;
        botaoSalvar.textContent = 'SALVAR METAS OFICIAIS';
    });
  });

    if (!storedData) {
        alert(`Simulação "${simulationName}" não encontrada no armazenamento local.`);
        return;
    }

    const simulationEntries = JSON.parse(storedData);
    const dataMap = new Map(simulationEntries.map(item => [item.key, item.valor]));

    let categoriaAtualContexto = '';
    document.querySelectorAll('#tabelaSimulacao tbody tr').forEach(row => {
        const primeiroTd = row.cells[0];
        if (!primeiroTd) return;

        const inputValorSimul = row.querySelector('input.simul-valor');

        if (row.classList.contains('dre-cat')) {
             categoriaAtualContexto = primeiroTd.textContent.trim();
        }

        if (!inputValorSimul || inputValorSimul.readOnly) {
            return;
        }

        let itemKey = '';
        if (row.classList.contains('dre-cat')) {
            const cat = primeiroTd.textContent.trim();
            itemKey = `${cat}|`;
        } else if (row.classList.contains('dre-sub')) {
            if (categoriaAtualContexto) {
                const sub = primeiroTd.textContent.trim();
                itemKey = `${categoriaAtualContexto}|${sub}`;
            }
        } else if (row.classList.contains('dre-subcat-l2')) {
             if (inputValorSimul.dataset.cat === "RECEITAS NAO OPERACIONAIS" &&
                inputValorSimul.dataset.subCat && inputValorSimul.dataset.subSubCat) {
                const rnoCat = inputValorSimul.dataset.subCat.replace(/_/g, ' ');
                const rnoSubCat = inputValorSimul.dataset.subSubCat.replace(/_/g, ' ');
                itemKey = `RECEITAS NAO OPERACIONAIS|${rnoCat}|${rnoSubCat}`;
            }
        }

        if (itemKey && dataMap.has(itemKey)) {
            inputValorSimul.value = formatSimValue(dataMap.get(itemKey));
        } else if (itemKey) { 
            // Opcional: Limpar campos que não estão na simulação salva, ou deixar como estão.
            // Por ora, vamos deixar como estão para não apagar dados que o usuário possa ter inserido manualmente
            // e que não faziam parte da simulação carregada.
        }
    });

    recalcularTudo();
    alert(`Simulação "${simulationName}" carregada.`);
  });


  // --- FIM: Funcionalidade de Salvar/Carregar Simulação Local ---

  // Botão Ponto de Equilíbrio
  document.getElementById('pontoEquilibrioBtn').addEventListener('click', function() {
    const rowFC = document.getElementById('rowFluxoCaixa');
    if (!rowFC) {
      alert('Linha do Fluxo de Caixa não encontrada.');
      return;
    }
    const inputFCValorSimul = rowFC.querySelector('input.simul-valor');
    if (!inputFCValorSimul) {
      alert('Campo de simulação do Fluxo de Caixa não encontrado.');
      return;
    }

    const fluxoCaixaSimulado = parseBRL(inputFCValorSimul.value);

    if (fluxoCaixaSimulado < 0) {
      const inputReceitaBrutaSimul = document.querySelector('.simul-valor[data-receita="1"]');
      const receitaBrutaAtual = parseBRL(inputReceitaBrutaSimul.value);
      const novaReceitaBruta = receitaBrutaAtual + Math.abs(fluxoCaixaSimulado);
      inputReceitaBrutaSimul.value = formatSimValue(novaReceitaBruta);
      recalcularTudo(); // Recalcula toda a DRE com a nova receita
    }
  });

  document.getElementById('deleteSimulationBtn').addEventListener('click', function() {
    const select = document.getElementById('loadSimulationSelect');
    const simulationName = select.value;

    if (!simulationName) {
        alert('Por favor, selecione uma simulação para excluir.');
        return;
    }

    if (confirm(`Tem certeza que deseja excluir a simulação local "${simulationName}"? Esta ação não pode ser desfeita.`)) {
        const storageKey = SIMULATION_STORAGE_PREFIX + simulationName;
        localStorage.removeItem(storageKey);
        populateSimulationList();
        alert(`Simulação "${simulationName}" excluída do armazenamento local.`);
    }
  });

  populateSimulationList(); // Popular a lista ao carregar a página
});
</script>