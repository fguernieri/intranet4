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
    if (mb_strtoupper(trim($cat)) !== 'OPERACOES EXTERNAS') {
        $matrizOrdenada[$cat] = $subs;
    }
}
if (isset($matriz['OPERACOES EXTERNAS'])) {
    $matrizOrdenada['OPERACOES EXTERNAS'] = $matriz['OPERACOES EXTERNAS'];
}

// Consulta para carregar as metas cadastradas (para a data atual)
$sqlMetas = "
    SELECT m1.Categoria, m1.Meta
    FROM fMetasFabrica m1
    INNER JOIN (
        SELECT Categoria, MAX(Data) as MaxData
        FROM fMetasFabrica
        GROUP BY Categoria
    ) m2 ON m1.Categoria = m2.Categoria AND m1.Data = m2.MaxData
";
$resMetas = $conn->query($sqlMetas);
$metasArray = [];
while ($m = $resMetas->fetch_assoc()){
    $cat = $m['Categoria'];
    $metasArray[$cat] = $m['Meta'];
}

// Certifique-se de que as metas da Receita, Tributos e Custo Variável estejam definidas,
// utilizando 0 como valor padrão se não existirem.
$metaReceita         = $metasArray['Receita operacional']['']   ?? 0;
$metaTributos        = $metasArray['TRIBUTOS']['']                ?? 0;
$metaCustoVariavel   = $metasArray['CUSTO VARIÁVEL']['']           ?? 0;
// Calcula o Lucro Bruto da meta: Receita - Tributos - Custo Variável
$metaLucro = $metaReceita - $metaTributos - $metaCustoVariavel;

// Para as despesas, use 0 caso não existam
$metaCustoFixo      = $metasArray['CUSTO FIXO']['']   ?? 0;
$metaDespesaFixa    = $metasArray['DESPESA FIXA']['']  ?? 0;
$metaDespesaVenda   = $metasArray['DESPESA VENDA'][''] ?? 0;
$totalMetaDespesas  = $metaCustoFixo + $metaDespesaFixa + $metaDespesaVenda;

// Lucro Líquido da meta: Lucro Bruto - (Custo Fixo + Despesa Fixa + Despesa Venda)
$metaLucroLiquido = $metaLucro - $totalMetaDespesas;

// Calcule médias e totais para FAT LIQUIDO e CUSTO VARIÁVEL
$mediaFATLiquido = ($media3Rec - ($media3Cat['TRIBUTOS'] ?? 0));
$atualFATLiquido = ($atualRec - ($atualCat['TRIBUTOS'] ?? 0));

$mediaCustoVariavel = $media3Cat['CUSTO VARIÁVEL'] ?? 0;
$atualCustoVariavel = $atualCat['CUSTO VARIÁVEL'] ?? 0;
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
    .dre-cat    { background: #22223b; font-weight: bold; cursor:pointer; }
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
        <th rowspan="2" class="p-2 text-center bg-gray-800">Categoria &gt; SubCategoria</th>
        <th colspan="2" class="p-2 text-center bg-blue-900">Média -3m / % Média-3m S/ FAT.</th>
        <th colspan="3" class="p-2 text-center bg-green-900">Simulação / % Simulação S/ FAT. / Diferença (R$)</th>
        <th colspan="2" class="p-2 text-center bg-red-900">Meta / % Meta s/ FAT.</th>
        <th colspan="3" class="p-2 text-center bg-purple-900">Realizado / % Realizado s/ FAT. / Comparação Meta</th>
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
        <th class="p-2 text-center bg-purple-700">% Realizado s/ FAT.</th>
        <th class="p-2 text-center bg-purple-700">Comparação Meta</th>
      </tr>
    </thead>
    <tbody>
      <!-- 1. RECEITA OPERACIONAL (continua editável) -->
      <tr class="dre-cat">
        <td class="p-2 text-left">RECEITA BRUTA</td>
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
        <td class="p-2 text-right"><?= isset($metasArray['Receita operacional']['']) ? 'R$ '.number_format($metasArray['Receita operacional'][''],2,',','.') : '' ?></td>
        <td class="p-2 text-center">
          <?= ($media3Rec > 0 && isset($metasArray['Receita operacional'][''])) ? number_format(($metasArray['Receita operacional']['']/$media3Rec)*100,2,',','.') .'%' : '' ?>
        </td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($atualRec,2,',','.') ?></td>
        <td class="p-2 text-center"><?= $atualRec > 0 ? '100,00%' : '-' ?></td>
        <td class="p-2 text-center">
          <?php
            if(isset($metasArray['Receita operacional'][''])) {
              $comp = $atualRec - $metasArray['Receita operacional'][''];
              echo 'R$ '.number_format($comp,2,',','.');
            }
          ?>
        </td>
      </tr>

      
     <!-- RECEITA BRUTA -->


<!-- TRIBUTOS (ajustado para seguir o padrão das demais linhas principais) -->
<?php if(isset($matrizOrdenada['TRIBUTOS'])): ?>
  <tr class="dre-cat cat_trib">
    <td class="p-2 text-left">TRIBUTOS</td>
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
    <td class="p-2 text-right">-</td>
    <td class="p-2 text-right"><?= isset($metasArray['TRIBUTOS']) ? 'R$ '.number_format($metasArray['TRIBUTOS'],2,',','.') : '' ?></td>
    <td class="p-2 text-center"><?= ($media3Rec>0 && isset($metasArray['TRIBUTOS'])) ? number_format(($metasArray['TRIBUTOS']/$media3Rec)*100,2,',','.') .'%' : '' ?></td>
    <td class="p-2 text-right"><?= 'R$ '.number_format($atualCat['TRIBUTOS'] ?? 0,2,',','.') ?></td>
    <td class="p-2 text-right"><?= ($atualRec>0)?number_format((($atualCat['TRIBUTOS'] ?? 0)/$atualRec)*100,2,',','.') .'%' : '-' ?></td>
    <td class="p-2 text-center">-</td>
  </tr>
  <?php foreach($matrizOrdenada['TRIBUTOS'] as $sub => $mesValores): ?>
    <?php if($sub): ?>
      <tr class="dre-sub">
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
        <td class="p-2 text-center"><?= ($atualRec>0)?number_format((($atualSub['TRIBUTOS'][$sub] ?? 0)/$atualRec)*100,2,',','.') .'%' : '-' ?></td>
        <td class="p-2 text-center">-</td>
      </tr>
    <?php endif; ?>
  <?php endforeach; ?>
<?php endif; ?>

<!-- RECEITA LÍQUIDA (RECEITA BRUTA - TRIBUTOS) -->
<?php
  $mediaReceitaLiquida = $media3Rec - ($media3Cat['TRIBUTOS'] ?? 0);
  $atualReceitaLiquida = $atualRec - ($atualCat['TRIBUTOS'] ?? 0);
?>
<tr id="rowFatLiquido" class="dre-cat" style="background:#1a4c2b;">
  <td class="p-2 text-left">RECEITA LÍQUIDA (RECEITA BRUTA - TRIBUTOS)</td>
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
  <td class="p-2 text-right"><?= $atualRec > 0 ? number_format(($atualReceitaLiquida / $atualRec) * 100, 2, ',', '.') . '%' : '-' ?></td>
  <td class="p-2 text-center">-</td> <!-- Comp. Meta -->
</tr>

<!-- CUSTO VARIÁVEL (categoria e subcategorias) -->
<?php if(isset($matrizOrdenada['CUSTO VARIÁVEL'])): ?>
  <tr class="dre-cat cat_cvar">
    <td class="p-2 text-left">CUSTO VARIÁVEL</td>
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
    <td class="p-2 text-right">-</td>
    <td class="p-2 text-right"><?= isset($metasArray['CUSTO VARIÁVEL']) ? 'R$ '.number_format($metasArray['CUSTO VARIÁVEL'],2,',','.') : '' ?></td>
    <td class="p-2 text-center"><?= ($media3Rec>0 && isset($metasArray['CUSTO VARIÁVEL'])) ? number_format(($metasArray['CUSTO VARIÁVEL']/$media3Rec)*100,2,',','.') .'%' : '' ?></td>
    <td class="p-2 text-right"><?= 'R$ '.number_format($atualCat['CUSTO VARIÁVEL'] ?? 0,2,',','.') ?></td>
    <td class="p-2 text-center"><?= ($atualRec>0)?number_format((($atualCat['CUSTO VARIÁVEL'] ?? 0)/$atualRec)*100,2,',','.') .'%' : '-' ?></td>
    <td class="p-2 text-center">-</td>
</tr>

<?php endif; ?>
  <?php foreach($matrizOrdenada['CUSTO VARIÁVEL'] as $sub => $mesValores): ?>
    <?php if($sub): ?>
      <tr class="dre-sub">
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
  <td class="p-2 text-center"><?= ($atualRec > 0) ? number_format((($atualSub['CUSTO VARIÁVEL'][$sub] ?? 0)/$atualRec)*100,2,',','.') .'%' : '-' ?></td>
  <td class="p-2 text-center">-</td>
</tr>

    <?php endif; ?>
  <?php endforeach; ?>

<!-- LUCRO BRUTO (RECEITA LÍQUIDA - CUSTO VARIÁVEL) -->
<?php
  $mediaLucroBruto = $mediaReceitaLiquida - ($media3Cat['CUSTO VARIÁVEL'] ?? 0);
  $atualLucroBruto = $atualReceitaLiquida - ($atualCat['CUSTO VARIÁVEL'] ?? 0);
?>
<tr id="rowLucroBruto" class="dre-cat" style="background:#102c14;">
  <td class="p-2 text-left">LUCRO BRUTO (RECEITA LÍQUIDA - CUSTO VARIÁVEL)</td>
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
  <td class="p-2 text-right"><?= $atualRec > 0 ? number_format(($atualLucroBruto / $atualRec) * 100, 2, ',', '.') . '%' : '-' ?></td>
  <td class="p-2 text-center">-</td> <!-- Comp. Meta -->
</tr>


      <!-- 6. CUSTO FIXO, 7. DESPESA FIXA e 8. DESPESA VENDA (permanecem editáveis) -->
      <?php 
        foreach(['CUSTO FIXO','DESPESA FIXA','DESPESA VENDA'] as $catName):
          if(isset($matrizOrdenada[$catName])):
      ?>
        <tr class="dre-cat">
  <td class="p-2 text-left"><?= $catName ?></td>
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
  <td class="p-2 text-right">-</td>
  <td class="p-2 text-right"><?= isset($metasArray[$catName]) ? 'R$ '.number_format($metasArray[$catName],2,',','.') : '' ?></td>
  <td class="p-2 text-center"><?= ($media3Rec > 0 && isset($metasArray[$catName])) ? number_format(($metasArray[$catName]/$media3Rec)*100,2,',','.') .'%' : '' ?></td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($atualCat[$catName] ?? 0,2,',','.') ?></td>
  <td class="p-2 text-center"><?= ($atualRec > 0) ? number_format((($atualCat[$catName] ?? 0)/$atualRec)*100,2,',','.') .'%' : '-' ?></td>
  <td class="p-2 text-center">-</td>
</tr>

        <?php foreach($matrizOrdenada[$catName] as $sub => $mesValores): ?>
          <?php if($sub): ?>
           <tr class="dre-sub">
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
  <td class="p-2 text-center"><?= ($atualRec > 0) ? number_format((($atualSub[$catName][$sub] ?? 0) / $atualRec) * 100, 2, ',', '.') . '%' : '-' ?></td>
  <td class="p-2 text-center">-</td>
</tr>

          <?php endif; ?>
        <?php endforeach; ?>
      <?php 
          endif;
        endforeach;
      ?>

      <!-- 9. LUCRO LIQUIDO = LUCRO BRUTO - (CUSTO FIXO + DESPESA FIXA + DESPESA VENDA) (DINÂMICO, não editável) -->
      <?php 
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
?>
<tr class="dre-cat" style="background:#102c14;">
  <td class="p-2 text-left">LUCRO LÍQUIDO</td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($mediaLucroLiquido,2,',','.') ?></td>
  <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format(($mediaLucroLiquido / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?></td>
  <td class="p-2 text-right">
    <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1" readonly>
  </td>
  <td class="p-2 text-center">
    <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1" style="width:60px;" readonly>
  </td>
  <td class="p-2 text-right">-</td>
  <td class="p-2 text-right"><?= isset($metasArray['Receita operacional']['']) ? 'R$ '.number_format($metaLucroLiquido ?? 0,2,',','.') : '' ?></td>
  <td class="p-2 text-center"><?= ($media3Rec > 0 && isset($metasArray['Receita operacional'][''])) ? number_format((($metaLucroLiquido ?? 0) / $media3Rec) * 100, 2, ',', '.') . '%' : '' ?></td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($atualLucroLiquido,2,',','.') ?></td>
  <td class="p-2 text-right"><?= $atualRec > 0 ? number_format(($atualLucroLiquido / $atualRec) * 100, 2, ',', '.') . '%' : '-' ?></td>
  <td class="p-2 text-center">-</td>
</tr>
      <!-- 10. INVESTIMENTO INTERNO, INVESTIMENTO EXTERNO e AMORTIZAÇÃO (editáveis) -->
      <?php 
        foreach(['INVESTIMENTO INTERNO','INVESTIMENTO EXTERNO','AMORTIZAÇÃO'] as $catName):
          if(isset($matrizOrdenada[$catName])):
      ?>
        <tr class="dre-cat">
  <td class="p-2 text-left"><?= $catName ?></td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($media3Cat[$catName] ?? 0,2,',','.') ?></td>
  <td class="p-2 text-center"><?= $media3Rec>0 ? number_format((($media3Cat[$catName] ?? 0)/$media3Rec)*100,2,',','.') .'%' : '-' ?></td>
  <td class="p-2 text-right">
    <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
           value="<?= number_format($media3Cat[$catName] ?? 0,2,',','.') ?>">
  </td>
  <td class="p-2 text-center">
    <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
           style="width:60px;" value="<?= $media3Rec>0 ? number_format((($media3Cat[$catName] ?? 0)/$media3Rec)*100,2,',','.') : '-' ?>">
  </td>
  <td class="p-2 text-right">-</td>
  <td class="p-2 text-right"><?= isset($metasArray[$catName]) ? 'R$ '.number_format($metasArray[$catName],2,',','.') : '' ?></td>
  <td class="p-2 text-center"><?= ($media3Rec > 0 && isset($metasArray[$catName])) ? number_format(($metasArray[$catName]/$media3Rec)*100,2,',','.') .'%' : '' ?></td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($atualCat[$catName] ?? 0,2,',','.') ?></td>
  <td class="p-2 text-center"><?= ($atualRec>0)?number_format((($atualCat[$catName] ?? 0)/$atualRec)*100,2,',','.') .'%' : '-' ?></td>
  <td class="p-2 text-center">-</td>
</tr>
    <?php foreach(($matrizOrdenada[$catName] ?? []) as $sub => $mesValores): ?>
      <?php if($sub): ?>
        <tr class="dre-sub">
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
          <td class="p-2 text-center"><?= ($atualRec > 0) ? number_format((($atualSub[$catName][$sub] ?? 0) / $atualRec) * 100, 2, ',', '.') . '%' : '-' ?></td>
          <td class="p-2 text-center">-</td>
        </tr>
      <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>
<?php endforeach; ?>
    </tbody>
  </table>
  
  <!-- Botão para salvar metas -->
  <button id="salvarMetasBtn" class="mt-4 bg-green-600 px-3 py-2 rounded font-bold">SALVAR METAS</button>
  
</main>

<!-- Scripts de Atualização, Toggle etc. (mantidos) -->
<script>
function parseBRL(str) {
  const stringValue = String(str || '');
  return parseFloat(stringValue.replace(/[R$\s\.]/g, '').replace(',', '.')) || 0;
}

function getReceitaBrutaSimulada() {
  const inputReceita = document.querySelector('.simul-valor[data-receita="1"]');
  return inputReceita ? parseBRL(inputReceita.value) : 0;
}

function recalcSinteticas() {
  const receitaBrutaSimulada = getReceitaBrutaSimulada();

  // Pega o valor SIMULADO de Tributos
  const inputTributos = document.querySelector('tr.cat_trib input.simul-valor');
  const tributosSimulados = inputTributos ? parseBRL(inputTributos.value) : 0;

  // Pega o valor SIMULADO de Custo Variável
  const inputCustoVariavel = document.querySelector('tr.cat_cvar input.simul-valor');
  const custoVariavelSimulado = inputCustoVariavel ? parseBRL(inputCustoVariavel.value) : 0;

  // 1. Calcula e atualiza SIMULAÇÃO da RECEITA LÍQUIDA
  const receitaLiquidaSimulada = receitaBrutaSimulada - tributosSimulados;
  const rowReceitaLiquida = document.getElementById('rowFatLiquido');
  if (rowReceitaLiquida) {
    const valorCell = rowReceitaLiquida.cells[3]; // Coluna "Simulação"
    const percCell = rowReceitaLiquida.cells[4];  // Coluna "% Simulação"

    if (valorCell) valorCell.textContent = receitaLiquidaSimulada.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
    if (percCell) percCell.textContent = receitaBrutaSimulada > 0 ? (receitaLiquidaSimulada / receitaBrutaSimulada * 100).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}) + '%' : '-';
  }

  // 2. Calcula e atualiza SIMULAÇÃO do LUCRO BRUTO
  const lucroBrutoSimulado = receitaLiquidaSimulada - custoVariavelSimulado;
  const rowLucroBruto = document.getElementById('rowLucroBruto');
  if (rowLucroBruto) {
    const valorCell = rowLucroBruto.cells[3]; // Coluna "Simulação"
    const percCell = rowLucroBruto.cells[4];  // Coluna "% Simulação"

    if (valorCell) valorCell.textContent = lucroBrutoSimulado.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
    if (percCell) percCell.textContent = receitaBrutaSimulada > 0 ? (lucroBrutoSimulado / receitaBrutaSimulada * 100).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}) + '%' : '-';
  }

  // 3. Calcula e atualiza SIMULAÇÃO do LUCRO LÍQUIDO
  let totalDespesasSimuladas = 0;
  document.querySelectorAll('.dre-cat').forEach(row => {
    const categoriaTexto = row.cells[0] ? row.cells[0].textContent.trim() : '';
    if (['CUSTO FIXO', 'DESPESA FIXA', 'DESPESA VENDA'].includes(categoriaTexto)) {
      const inputValorDespesa = row.querySelector('.simul-valor');
      if (inputValorDespesa) {
        totalDespesasSimuladas += parseBRL(inputValorDespesa.value);
      }
    }
  });

  const lucroLiquidoSimulado = lucroBrutoSimulado - totalDespesasSimuladas;
  const rowLucroLiquido = document.getElementById('rowLucroLiquido');
  if (rowLucroLiquido) {
    const valorCell = rowLucroLiquido.cells[3]; // Coluna "Simulação"
    const percCell = rowLucroLiquido.cells[4];  // Coluna "% Simulação"

    if (valorCell) valorCell.textContent = lucroLiquidoSimulado.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
    if (percCell) percCell.textContent = receitaBrutaSimulada > 0 ? (lucroLiquidoSimulado / receitaBrutaSimulada * 100).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}) + '%' : '-';
  }
}

// Função para Recalcular o Subtotal da Simulação
function recalcularSubtotalSimulacao() {
  let subtotalSimulacao = 0;
  document.querySelectorAll('td:nth-child(4)').forEach(cell => { // Coluna "Simulação"
    if (cell.textContent && !isNaN(parseBRL(cell.textContent))) {
      subtotalSimulacao += parseBRL(cell.textContent);
    }
  });

  // Exibir o Subtotal
  const subtotalElement = document.getElementById('subtotalSimulacao'); // Elemento onde o subtotal será exibido
  if (subtotalElement) {
    subtotalElement.textContent = subtotalSimulacao.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  }
}

// Atualiza o percentual de simulação de TODAS as linhas sempre em relação à RECEITA BRUTA da simulação
function atualizarPercentuaisSimulacao() {
  const recIn = getReceitaBrutaSimulada();
  document.querySelectorAll('.simul-valor').forEach(input => {
    const row = input.closest('tr');
    const percEl = row.querySelector('.simul-perc');
    if (percEl) {
      const valor = parseBRL(input.value);
      percEl.value = recIn > 0 ? (valor / recIn * 100).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}) + '%' : '-';
    }
  });
}

document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.simul-valor, .simul-perc').forEach(function(input) {
    input.addEventListener('input', function() {
      // Atualiza percentuais de todas as linhas
      atualizarPercentuaisSimulacao();
      // Atualiza linhas sintéticas
      recalcSinteticas();
      // Recalcula o subtotal da simulação
      recalcularSubtotalSimulacao();
    });
    // Dispara ao iniciar
    input.dispatchEvent(new Event('input'));
  });

  // Chamar o recálculo do subtotal no início para popular os campos com base nos valores iniciais
  recalcularSubtotalSimulacao();
});
</script>
<script>
// Função para converter "1.234,56" em número (1234.56)
function parseBRL(str) {
    return parseFloat(String(str).replace(/[R$\s\.]/g, '').replace(',', '.')) || 0;
}
function formatBRL(v){
  return 'R$ '+ v.toLocaleString('pt-BR',{minimumFractionDigits:2});
}

// recalc das linhas sintéticas
function recalcSinteticas(){
  const recIn  = parseBRL(document.querySelector('.simul-valor[data-receita="1"]').value);
  const tribIn = parseBRL(document.querySelector('.dre-cat.cat_trib .simul-valor').value);
  const cvIn   = parseBRL(document.querySelector('.dre-cat.cat_cvar .simul-valor').value);

  // FAT LIQUIDO
  const fatSim = recIn - tribIn;
  const rowFat = document.getElementById('rowFatLiquido');
  const mediaFat = parseBRL(rowFat.cells[1].textContent);
  rowFat.cells[3].textContent = formatBRL(fatSim);
  rowFat.cells[4].textContent = recIn>0? (fatSim/recIn*100).toFixed(2)+'%':'-';
  rowFat.cells[5].textContent = formatBRL(fatSim - mediaFat);

  // LUCRO BRUTO
  const lbSim = fatSim - cvIn;
  const rowLB = document.getElementById('rowLucroBruto');
  const mediaLB = parseBRL(rowLB.cells[1].textContent);
  rowLB.cells[3].textContent = formatBRL(lbSim);
  rowLB.cells[4].textContent = recIn>0? (lbSim/recIn*100).toFixed(2)+'%':'-';
  rowLB.cells[5].textContent = formatBRL(lbSim - mediaLB);
}

document.addEventListener('DOMContentLoaded', ()=>{
  // corrija a declaração de metas (remova duplicata)
  let metas = [];

  document.querySelectorAll('.simul-valor').forEach(input => {
    input.addEventListener('input', e => {
      const row = e.target.closest('tr');
      const recIn = parseBRL(document.querySelector('.simul-valor[data-receita="1"]').value)||1;
      const simul = parseBRL(e.target.value);
      const percEl = row.querySelector('.simul-perc');
      if (percEl) {
        percEl.value = recIn>0
          ? (simul/recIn*100).toLocaleString('pt-BR',{minimumFractionDigits:2}) + '%'
          : '-';
      }
      const media = parseBRL(row.cells[1].textContent);
      if (row.cells[5]) {
        row.cells[5].textContent =
          'R$ ' + (simul - media).toLocaleString('pt-BR',{minimumFractionDigits:2});
      }
      // Recalcula FAT LIQUIDO e LUCRO BRUTO
      recalcSinteticas();
    });
    // dispare ao iniciar
    input.dispatchEvent(new Event('input'));
  });

  // Botão salvar metas
  document.getElementById('salvarMetasBtn').addEventListener('click', function() {
    metas = [];
    document.querySelectorAll('tbody tr').forEach(linha => {
      if (!linha.classList.contains('dre-cat') && !linha.classList.contains('dre-sub')) return;
      const cells = linha.querySelectorAll('td');
      let txt = cells[0].textContent.trim();
      let partes = txt.split('>');
      let categoria = partes[0].trim();
      let subcategoria = (partes[1]||'').trim();
      let inputSim = cells[3]?.querySelector('.simul-valor');
      if (!inputSim) return;
      metas.push({
        categoria,
        subcategoria,
        meta: parseBRL(inputSim.value)
      });
    });
    if (!metas.length) { alert("Nenhum valor encontrado."); return; }
    const dataMeta = prompt("Data (AAAA-MM-DD):", new Date().toISOString().slice(0,10));
    if (!dataMeta) { alert("Cancelado."); return; }
    fetch(location.href, {
      method: 'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({metas,data:dataMeta})
    })
    .then(r=>r.json())
    .then(js=>{
      alert(js.sucesso ? "Salvo!" : "Erro: "+js.erro);
    })
    .catch(()=>alert("Falha no envio."));
  });
});

function onCategoriaChange(categoria) {
    var inputPercentual = document.getElementById('inputPercentual');
    var inputValor = document.getElementById('inputValor');
    
    // Resetando ambos os campos para bloqueio total
    inputPercentual.disabled = true;
    inputValor.disabled = true;
    
    if (categoria === 'CUSTO VARIÁVEL' || categoria === 'TRIBUTOS' || categoria === 'DESPESA VENDA') {
        inputPercentual.disabled = false;
    } else if (categoria === 'DESPESA FIXA' || categoria === 'CUSTO FIXO') {
        inputValor.disabled = false;
    }
}
</script>
<script>
// parse e formatadores:
function parseBRL(str){
  return parseFloat(str.replace(/[R$\s\.]/g,'').replace(',', '.'))||0;
}
function formatBRL(v){
  return 'R$ '+ v.toLocaleString('pt-BR',{minimumFractionDigits:2});
}

// recalc FAT LIQUIDO e LUCRO BRUTO (já supondo que #rowFatLiquido e #rowLucroBruto existam)
function recalcSinteticas(){
  const rec = parseBRL(document.querySelector('.simul-valor[data-receita="1"]').value);
  const trib = parseBRL(document.querySelector('.cat_trib .simul-valor').value);
  const cv   = parseBRL(document.querySelector('.cat_cvar .simul-valor').value);

  // FAT
  const fat = rec - trib;
  const rowF = document.getElementById('rowFatLiquido');
  const mediaF = parseBRL(rowF.cells[1].textContent);
  let row = rowCat.nextElementSibling;
  while (row && !row.classList.contains('dre-cat')) {
    if (row.classList.contains('dre-sub')) {
      let input = row.querySelector('.simul-valor');
      if (input) soma += parseBRL(input.value);
    }
    row = row.nextElementSibling;
  }

  // Atualiza o input da categoria
  let inputCat = rowCat.querySelector('.simul-valor');
  if (inputCat) inputCat.value = soma.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});

  // Atualiza também o campo de % da categoria
  let recIn = parseBRL(document.querySelector('.simul-valor[data-receita="1"]').value)||1;
  let percCat = rowCat.querySelector('.simul-perc');
  if (percCat) percCat.value = recIn>0 ? (soma/recIn*100).toLocaleString('pt-BR',{minimumFractionDigits:2, maximumFractionDigits:2}) + '%' : '-';
}
</script>