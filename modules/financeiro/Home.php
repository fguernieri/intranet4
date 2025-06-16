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

// Organiza em matriz por categoria, subcategoria, descrição e mês
$meses = [
    1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
    7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'
];
$matriz = [];
foreach ($linhas as $linha) {
    $cat = $linha['CATEGORIA'] ?? 'SEM CATEGORIA';
    $sub = $linha['SUBCATEGORIA'] ?? 'SEM SUBCATEGORIA';
    $desc = $linha['DESCRICAO_CONTA'] ?? 'SEM DESCRIÇÃO';
    $mes = (int)date('n', strtotime($linha['DATA_VENCIMENTO']));
    $id = $linha['ID_CONTA'];
    $matriz[$cat][$sub][$desc][$mes][$id][] = floatval($linha['VALOR_EXIBIDO']);
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
$atualCat = [];
$media3Sub = [];
$atualSub = [];
foreach ($matriz as $cat => $subs) {
    $soma3Cat = 0;
    $somaAtualCat = 0;
    foreach ($subs as $sub => $descricoes) {
        $soma3Sub = 0;
        $somaAtualSub = 0;
        foreach ($descricoes as $desc => $mesValores) {
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
        }
        $media3Sub[$cat][$sub] = $soma3Sub / 3;
        $atualSub[$cat][$sub] = $somaAtualSub;
    }
    $media3Cat[$cat] = $soma3Cat / 3;
    $atualCat[$cat] = $somaAtualCat;
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
$sqlMetas = "SELECT Categoria, IFNULL(Subcategoria, '') AS Subcategoria, Meta FROM fMetasFabrica WHERE Data = CURDATE()";
$resMetas = $conn->query($sqlMetas);
$metasArray = [];
while ($m = $resMetas->fetch_assoc()){
    $cat = $m['Categoria'];
    $sub = $m['Subcategoria'];
    $metasArray[$cat][$sub] = $m['Meta'];
}
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
      <!-- Linha Receita operacional -->
      <tr class="dre-cat" style="background:#102c14;">
        <td class="p-2 text-left">Receita operacional</td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($media3Rec,2,',','.') ?></td>
        <td class="p-2 text-center"><b>100,00%</b></td>
        <td class="p-2 text-right">
          <input type="text" data-receita="1" class="simul-valor font-bold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
                 value="<?= number_format($media3Rec,2,',','.') ?>">
        </td>
        <td class="p-2 text-center">
          <input type="text" class="simul-perc font-bold bg-gray-800 text-yellow-400 text-center rounded px-1" style="width:60px;"
                 value="100,00%">
        </td>
        <td class="p-2 text-right"><?= 'R$ '.number_format(0,2,',','.') ?></td>
        <td class="p-2 text-right">
          <?= isset($metasArray['Receita operacional']['']) ? 'R$ '.number_format($metasArray['Receita operacional'][''],2,',','.') : '' ?>
        </td>
        <td class="p-2 text-center">
          <?= ($media3Rec > 0 && isset($metasArray['Receita operacional'][''])) ? number_format(($metasArray['Receita operacional']['']/$media3Rec)*100,2,',','.').'%' : '' ?>
        </td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($atualRec,2,',','.') ?></td>
        <td class="p-2 text-center">
          <?= $atualRec > 0 ? number_format(($atualRec/$atualRec)*100,2,',','.').'%' : '-' ?>
        </td>
        <td class="p-2 text-center">
          <?php
            if(isset($metasArray['Receita operacional'][''])) {
              $compMeta = $atualRec - $metasArray['Receita operacional'][''];
              echo 'R$ '. number_format($compMeta,2,',','.');
            }
          ?>
        </td>
      </tr>
      
      <!-- Linhas para Categorias/Subcategorias -->
      <?php foreach ($matrizOrdenada as $cat => $subs):
        $catId = 'cat_' . md5($cat);
      ?>
        <!-- Linha Categoria -->
        <tr class="dre-cat <?= $catId ?>" onclick="toggleRows('<?= $catId ?>', this)">
          <td class="p-2 text-left"><?= $cat ?></td>
          <td class="p-2 text-right"><?= 'R$ '.number_format($media3Cat[$cat],2,',','.') ?></td>
          <td class="p-2 text-center">
            <?= $media3Rec > 0 ? number_format(($media3Cat[$cat]/$media3Rec)*100,2,',','.') .'%' : '-' ?>
          </td>
          <td class="p-2 text-right">
            <input type="text" class="simul-valor font-bold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
                   value="<?= number_format($media3Cat[$cat],2,',','.') ?>">
          </td>
          <td class="p-2 text-center">
            <input type="text" class="simul-perc font-bold bg-gray-800 text-yellow-400 text-center rounded px-1"
                   style="width:60px;" value="<?= $media3Rec > 0 ? number_format(($media3Cat[$cat]/$media3Rec)*100,2,',','.') : '' ?>">
          </td>
          <td class="p-2 text-right"><?= 'R$ '.number_format(0,2,',','.') ?></td>
          <td class="p-2 text-right">
            <?= isset($metasArray[$cat]['']) ? 'R$ '.number_format($metasArray[$cat][''],2,',','.') : '' ?>
          </td>
          <td class="p-2 text-center">
            <?= ($media3Rec > 0 && isset($metasArray[$cat][''])) ? number_format(($metasArray[$cat]['']/$media3Rec)*100,2,',','.').'%' : '' ?>
          </td>
          <td class="p-2 text-right"><?= 'R$ '.number_format($atualCat[$cat],2,',','.') ?></td>
          <td class="p-2 text-center">
            <?= $atualRec > 0 ? number_format(($atualCat[$cat]/$atualRec)*100,2,',','.') .'%' : '-' ?>
          </td>
          <td class="p-2 text-center">
            <?php
              if(isset($metasArray[$cat][''])) {
                $compMeta = $atualCat[$cat] - $metasArray[$cat][''];
                echo 'R$ '. number_format($compMeta,2,',','.');
              }
            ?>
          </td>
        </tr>
        
        <?php foreach ($subs as $sub => $descricoes):
          $subId = $catId . '_sub_' . md5($sub);
        ?>
          <!-- Linha Subcategoria -->
          <tr class="dre-sub <?= $catId ?> dre-hide" onclick="toggleRows('<?= $subId ?>', this)">
            <td class="p-2 text-left"><?= $cat ?> &gt; <?= $sub ?></td>
            <td class="p-2 text-right"><?= 'R$ '.number_format($media3Sub[$cat][$sub],2,',','.') ?></td>
            <td class="p-2 text-center">
              <?= $media3Rec > 0 ? number_format(($media3Sub[$cat][$sub]/$media3Rec)*100,2,',','.') .'%' : '-' ?>
            </td>
            <td class="p-2 text-right">
              <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
                     value="<?= number_format($media3Sub[$cat][$sub],2,',','.') ?>">
            </td>
            <td class="p-2 text-center">
              <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
                     style="width:60px;" value="<?= $media3Rec > 0 ? number_format(($media3Sub[$cat][$sub]/$media3Rec)*100,2,',','.') : '-' ?>">
            </td>
            <td class="p-2 text-right"><?= 'R$ '.number_format(0,2,',','.') ?></td>
            <td class="p-2 text-right">
              <?= isset($metasArray[$cat][$sub]) ? 'R$ '.number_format($metasArray[$cat][$sub],2,',','.') : '' ?>
            </td>
            <td class="p-2 text-center">
              <?= ($media3Rec > 0 && isset($metasArray[$cat][$sub])) ? number_format(($metasArray[$cat][$sub]/$media3Rec)*100,2,',','.') .'%' : '' ?>
            </td>
            <td class="p-2 text-right"><?= 'R$ '.number_format($atualSub[$cat][$sub],2,',','.') ?></td>
            <td class="p-2 text-center">
              <?= $atualRec > 0 ? number_format(($atualSub[$cat][$sub]/$atualRec)*100,2,',','.') .'%' : '-' ?>
            </td>
            <td class="p-2 text-center">
              <?php
                if(isset($metasArray[$cat][$sub])) {
                  $compMeta = $atualSub[$cat][$sub] - $metasArray[$cat][$sub];
                  echo 'R$ '. number_format($compMeta,2,',','.');
                }
              ?>
            </td>
          </tr>
          
          <?php foreach ($descricoes as $desc => $mesValores): ?>
            <tr class="dre-detalhe <?= $subId ?> dre-hide">
              <td class="p-2 text-left"></td>
              <td class="p-2 text-right">
                <?php
                  $soma3 = 0;
                  foreach ($mesesUltimos3 as $m) {
                    if (isset($mesValores[$m])) {
                      foreach ($mesValores[$m] as $ids) {
                        $soma3 += array_sum($ids);
                      }
                    }
                  }
                  $mediaDet = $soma3 / 3;
                  echo 'R$ '.number_format($mediaDet,2,',','.');
                ?>
              </td>
              <td class="p-2 text-center">
                <?= $media3Rec > 0 ? number_format(($mediaDet/$media3Rec)*100,2,',','.') .'%' : '-' ?>
              </td>
              <td class="p-2 text-right">
                <input type="text" class="simul-valor bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
                       value="<?= number_format($mediaDet,2,',','.') ?>">
              </td>
              <td class="p-2 text-center">
                <input type="text" class="simul-perc bg-gray-800 text-yellow-400 text-center rounded px-1"
                       style="width:60px;" value="<?= $media3Rec > 0 ? number_format(($mediaDet/$media3Rec)*100,2,',','.') : '' ?>">
              </td>
              <td class="p-2 text-right"><?= 'R$ '.number_format(0,2,',','.') ?></td>
              <td class="p-2 text-right"></td>
              <td class="p-2 text-center"></td>
              <td class="p-2 text-right">
                <?php
                  $somaAtual = 0;
                  if (isset($mesValores[$mesAtual])) {
                    foreach ($mesValores[$mesAtual] as $ids) {
                      $somaAtual += array_sum($ids);
                    }
                  }
                  echo 'R$ '.number_format($somaAtual,2,',','.');
                ?>
              </td>
              <td class="p-2 text-center">
                <?= $atualRec > 0 ? number_format(($somaAtual/$atualRec)*100,2,',','.') .'%' : '-' ?>
              </td>
              <td class="p-2 text-center"><!-- Comparação Meta não se aplica --></td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
  
  <!-- Botão para salvar metas -->
  <button id="salvarMetasBtn" class="mt-4 bg-green-600 px-3 py-2 rounded font-bold">SALVAR METAS</button>
  
</main>

<!-- Scripts de Atualização, Toggle etc. (mantidos) -->
<script>
// Função para alternar a visibilidade das linhas filhas
function toggleRows(className, triggerRow) {
    const rows = document.querySelectorAll("tr." + className);
    rows.forEach((row) => {
        if (row !== triggerRow) {
            row.classList.toggle("dre-hide");
        }
    });
}

// Função para converter "1.234,56" em número (1234.56)
function parseBRL(str) {
    return parseFloat(str.replace(/[R$\s\.]/g, '').replace(',', '.')) || 0;
}

// Atualiza percentuais quando o input é alterado (mantido do seu código)
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.simul-valor').forEach(function(input) {
    input.addEventListener('input', function() {
      const row = input.closest('tr');
      // Pega o valor de receita se for a linha data-receita="1"
      let receitaInput = document.querySelector('.simul-valor[data-receita="1"]');
      let receitaVal = receitaInput ? parseBRL(receitaInput.value) : 1;
      let simulNum = parseBRL(input.value);
      let percInput = row.querySelector('.simul-perc');
      if (percInput && receitaVal > 0) {
        let perc = (simulNum / receitaVal) * 100;
        percInput.value = perc.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}) + '%';
      }
      // Diferença (coluna 6, index 5)
      const mediaCell = row.cells[1];
      const mediaNum = mediaCell ? parseBRL(mediaCell.textContent) : 0;
      const diff = simulNum - mediaNum;
      if(row.cells.length >= 6) {
        row.cells[5].textContent = 'R$ ' + diff.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
      }
    });
    // Dispara ao carregar
    input.dispatchEvent(new Event('input'));
  });
});

// ===================== [ ADIÇÃO 2: SALVAR METAS – VERSÃO AJUSTADA ] =====================
document.getElementById('salvarMetasBtn').addEventListener('click', function() {
    const tabela = document.getElementById('tabelaSimulacao');
    if (!tabela) {
      alert("Tabela de Simulação não encontrada.");
      return;
    }

    // Seleciona todas as linhas que sejam "dre-cat" ou "dre-sub"
    const linhas = tabela.querySelectorAll('tbody tr');
    const metas = [];

    linhas.forEach(linha => {
        if (!(linha.classList.contains('dre-cat') || linha.classList.contains('dre-sub'))) return;
        const cells = linha.querySelectorAll('td');
        if (!cells.length) return;
        
        // Use textContent para garantir que mesmo linhas ocultas sejam lidas
        let txt = cells[0].textContent.trim();
        let partes = txt.split('>');
        let categoria = partes[0].trim();
        let subcategoria = (partes.length > 1) ? partes[1].trim() : '';
        
        // Procura o input na 4ª coluna (índice 3) – que contém o valor de Simulação
        let inputSimul = cells[3] ? cells[3].querySelector('.simul-valor') : null;
        if (!inputSimul) return;
        let metaVal = parseBRL(inputSimul.value.trim());
        metas.push({ categoria, subcategoria, meta: metaVal });
    });

    if (!metas.length) {
      alert("Nenhum valor de simulação encontrado. Verifique se os inputs existem.");
      return;
    }

    // Pede a data ao usuário
    const dataMeta = prompt("Informe a data da meta (AAAA-MM-DD):", new Date().toISOString().slice(0,10));
    if (!dataMeta) {
      alert("Data não informada. Cancelado.");
      return;
    }

    // Envia via fetch (POST) para o mesmo arquivo
    fetch(location.href, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ metas: metas, data: dataMeta })
    })
    .then(r => r.json())
    .then(result => {
      if (result.sucesso) {
        alert("Metas (Simulação) salvas com sucesso!");
      } else {
        alert("Erro ao salvar metas: " + (result.erro || ''));
      }
    })
    .catch(err => {
      console.error(err);
      alert("Erro na solicitação!");
    });
});
</script>
</body>
</html>