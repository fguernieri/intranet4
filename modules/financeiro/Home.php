<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Carregue todas as parcelas do ano para médias e totais (pagas e em aberto)
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
    $id = $f['ID_CONTA'];
    $valor = $f['VALOR'];
    $data  = $f['DATA_VENCIMENTO'];
    $linhas[] = [
        'ID_CONTA'        => $id,
        'CATEGORIA'       => $f['CATEGORIA'],
        'SUBCATEGORIA'    => $f['SUBCATEGORIA'],
        'DESCRICAO_CONTA' => $f['DESCRICAO_CONTA'],
        'PARCELA'         => $f['PARCELA'],
        'VALOR_EXIBIDO'   => $valor,
        'DATA_VENCIMENTO' => $data,
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
$atualRec = $receitaPorMes[$mesAtual] ?? 0;

// Ordena as categorias, deixando "OPERACOES EXTERNAS" por último
$matrizOrdenada = [];
foreach ($matriz as $cat => $subs) {
    if (mb_strtoupper(trim($cat)) !== 'OPERACOES EXTERNAS') {
        $matrizOrdenada[$cat] = $subs;
    }
}
if (isset($matriz['OPERACOES EXTERNAS'])) {
    $matrizOrdenada['OPERACOES EXTERNAS'] = $matriz['OPERACOES EXTERNAS'];
} elseif (isset($matriz['Operacoes Externas'])) {
    $matrizOrdenada['Operacoes Externas'] = $matriz['Operacoes Externas'];
} elseif (isset($matriz['operações externas'])) {
    $matrizOrdenada['operações externas'] = $matriz['operações externas'];
}

// Consulta: traz todas as parcelas do mês atual, para exibição detalhada
$dataIni = sprintf('%04d-%02d-01', $anoAtual, $mesAtual);
$dataFim = date('Y-m-d', strtotime("$dataIni +1 month"));
$sqlParcelas = "
    SELECT
        c.CATEGORIA,
        c.SUBCATEGORIA,
        c.DESCRICAO_CONTA,
        d.PARCELA,
        d.DATA_VENCIMENTO,
        d.VALOR
    FROM
        fContasAPagar AS c
    INNER JOIN
        fContasAPagarDetalhes AS d ON c.ID_CONTA = d.ID_CONTA
    WHERE
        d.DATA_VENCIMENTO >= ?
        AND d.DATA_VENCIMENTO < ?
    ORDER BY
        c.CATEGORIA, c.SUBCATEGORIA, c.DESCRICAO_CONTA, d.PARCELA
";
$stmt = $conn->prepare($sqlParcelas);
$stmt->bind_param('ss', $dataIni, $dataFim);
$stmt->execute();
$res = $stmt->get_result();

$matrizParcelas = [];
while ($row = $res->fetch_assoc()) {
    $cat = $row['CATEGORIA'] ?? 'SEM CATEGORIA';
    $sub = $row['SUBCATEGORIA'] ?? 'SEM SUBCATEGORIA';
    $desc = $row['DESCRICAO_CONTA'] ?? 'SEM DESCRIÇÃO';
    $parcela = $row['PARCELA'] ?? 'SEM PARCELA';
    $matrizParcelas[$cat][$sub][$desc][$parcela] = [
        'valor' => floatval($row['VALOR']),
        'data_venc' => $row['DATA_VENCIMENTO']
    ];
}
$stmt->close();
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
    .dre-hide { display: none; }
    table, th, td {
      font-family: 'Segoe UI', Arial, sans-serif;
      font-size: 11px;
      border: 0.5px solid #fff; /* divisória branca e fina */
      border-collapse: collapse;
    }
    .dre-cat, .dre-sub {
      font-size: 12px;
    }
    th, td {
      border: 0.5px solid #fff; /* divisória branca e fina */
    }
    .col-atual {
      background-color: #1e293b !important; /* azul escuro */
      color: #ffb703 !important;            /* laranja/amarelo */
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
  <table class="min-w-full text-xs mx-auto border border-gray-700 rounded">
    <thead class="bg-gray-700 text-yellow-400">
      <tr>
        <th class="p-2 text-left">Categoria</th>
        <th class="p-2 text-center">Média 3 meses</th>
        <th class="p-2 text-center">Simulação</th> <!-- NOVA COLUNA -->
        <th class="p-2 text-center">% s/ Receita (Média)</th>
        <th class="p-2 text-center">% s/ Receita (Simulação)</th> <!-- NOVA COLUNA -->
        <th class="p-2 text-right font-bold col-atual" style="font-weight: bold;">Mês Atual</th>
        <th class="p-2 text-center font-bold col-atual" style="font-weight: bold;">% s/ Receita (Atual)</th>
      </tr>
    </thead>
    <tbody>
      <!-- Receita operacional -->
      <tr class="dre-cat" style="background:#102c14;">
        <td class="p-2 font-bold" style="font-weight: bold;"><b>Receita operacional</b></td>
        <td class="p-2 text-right font-bold" style="font-weight: bold;"><b><?= 'R$ '.number_format($media3Rec,2,',','.') ?></b></td>
        <td class="p-2 text-right font-bold" style="font-weight: bold;">
          <input type="text" data-receita="1" class="simul-valor font-bold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1" value="<?= number_format($media3Rec,2,',','.') ?>">
        </td>
        <td class="p-2 text-center font-bold" style="font-weight: bold;"><b>100,00%</b></td>
        <td class="p-2 text-center font-bold" style="font-weight: bold;">
          <input type="text" class="simul-perc font-bold bg-gray-800 text-yellow-400 text-center rounded px-1" style="width:60px;" value="100,00%">
        </td>
        <td class="p-2 text-right font-bold" style="font-weight: bold;"><b><?= 'R$ '.number_format($atualRec,2,',','.') ?></b></td>
        <td class="p-2 text-center font-bold col-atual" style="font-weight: bold;"><b>100,00%</b></td>
      </tr>
      <?php foreach ($matrizOrdenada as $cat => $subs): 
        $catId = 'cat_' . md5($cat);
      ?>
        <!-- Linha da categoria -->
        <tr class="dre-cat <?= $catId ?>">
          <td class="p-2 font-bold">
            <span class="toggle-icon" style="margin-right:6px;">+</span>
            <?=$cat?>
          </td>
          <td class="p-2 text-right font-bold"><?= 'R$ '.number_format($media3Cat[$cat],2,',','.') ?></td>
          <td class="p-2 text-right font-bold">
            <input type="text" class="simul-valor font-bold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1" value="<?= number_format($media3Cat[$cat],2,',','.') ?>">
          </td>
          <td class="p-2 text-center font-bold">
            <?= $media3Rec > 0 ? number_format(($media3Cat[$cat]/$media3Rec)*100, 2, ',', '.') . '%' : '-' ?>
          </td>
          <td class="p-2 text-center font-bold">
            <input type="text" class="simul-perc font-bold bg-gray-800 text-yellow-400 text-center rounded px-1" style="width:60px;" value="<?= $media3Rec > 0 ? number_format(($media3Cat[$cat]/$media3Rec)*100, 2, ',', '.') : '' ?>">
          </td>
          <td class="p-2 text-right font-bold"><?= 'R$ '.number_format($atualCat[$cat],2,',','.') ?></td>
          <td class="p-2 text-center font-bold col-atual">
            <?= $atualRec > 0 ? number_format(($atualCat[$cat]/$atualRec)*100, 2, ',', '.') . '%' : '-' ?>
          </td>
        </tr>
        <?php foreach ($subs as $sub => $descricoes): 
          $subId = $catId . '_sub_' . md5($sub);
        ?>
          <!-- Linha da subcategoria -->
          <tr class="dre-sub <?= $catId ?> dre-hide">
            <td class="p-2 font-semibold" style="padding-left:2em;">
              <span class="toggle-icon" style="margin-right:6px;">+</span>
              <?=$sub?>
            </td>
            <td class="p-2 text-right font-semibold"><?= 'R$ '.number_format($media3Sub[$cat][$sub],2,',','.') ?></td>
            <td class="p-2 text-right font-semibold">
              <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1" value="<?= number_format($media3Sub[$cat][$sub],2,',','.') ?>">
            </td>
            <td class="p-2 text-center font-semibold">
              <?= $media3Rec > 0 ? number_format(($media3Sub[$cat][$sub]/$media3Rec)*100, 2, ',', '.') . '%' : '-' ?>
            </td>
            <td class="p-2 text-center font-semibold">
              <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center w-16 rounded px-1" value="<?= $media3Rec > 0 ? number_format(($media3Sub[$cat][$sub]/$media3Rec)*100, 2, ',', '.') . '%' : '-' ?>">
            </td>
            <td class="p-2 text-right font-semibold"><?= 'R$ '.number_format($atualSub[$cat][$sub],2,',','.') ?></td>
            <td class="p-2 text-center font-semibold">
              <?= $atualRec > 0 ? number_format(($atualSub[$cat][$sub]/$atualRec)*100, 2, ',', '.') . '%' : '-' ?>
            </td>
          </tr>
          <?php foreach ($descricoes as $desc => $mesValores): ?>
            <tr class="dre-detalhe <?=$subId?> dre-hide">
              <td class="p-2" style="padding-left:3em;"><?=$desc?></td>
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
                  echo 'R$ '.number_format($soma3/3,2,',','.');
                ?>
              </td>
              <td class="p-2 text-right">
                <input type="text" class="simul-valor bg-gray-800 text-yellow-400 text-right w-24 rounded px-1" value="<?php
                  $soma3 = 0;
                  foreach ($mesesUltimos3 as $m) {
                    if (isset($mesValores[$m])) {
                      foreach ($mesValores[$m] as $ids) {
                        $soma3 += array_sum($ids);
                      }
                    }
                  }
                  echo number_format($soma3/3,2,',','.');
                ?>">
              </td>
              <td class="p-2"></td>
              <td class="p-2 text-center">
                <input type="text" class="simul-perc bg-gray-800 text-yellow-400 text-center w-16 rounded px-1" value="<?php
                  $soma3 = 0;
                  foreach ($mesesUltimos3 as $m) {
                    if (isset($mesValores[$m])) {
                      foreach ($mesValores[$m] as $ids) {
                        $soma3 += array_sum($ids);
                      }
                    }
                  }
                  $perc = $media3Rec > 0 ? number_format(($soma3/3/$media3Rec)*100, 2, ',', '.') . '%' : '-';
                  echo $perc;
                ?>">
              </td>
              <td class="p-2 text-right">
                <?php
                  $somaAtual = 0;
                  if (isset($mesValores[$mesAtual])) {
                    foreach ($mesValores[$mesAtual] as $ids) {
                      $somaAtual += array_sum($ids);
                    }
                  }
                  echo 'R$ '.number_format($somaAtual,2,',','.');
                ?>
              </td>
              <td class="p-2"></td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
</main>
<script>
function toggleRows(cl, el) {
  document.querySelectorAll('.'+cl).forEach(row => {
    row.classList.toggle('dre-hide');
  });
  // Alterna o ícone +/- apenas na linha clicada
  if (el) {
    let icon = el.querySelector('.toggle-icon');
    if (icon) icon.textContent = icon.textContent === '+' ? '-' : '+';
  }
}

// Travar apenas as categorias (exceto receita operacional)
document.querySelectorAll('tr.dre-cat .simul-valor').forEach(function(input) {
  if (!input.hasAttribute('data-receita')) {
    input.setAttribute('readonly', 'readonly');
    input.style.background = '#22223b';
    input.style.cursor = 'not-allowed';
  }
});

// Função para buscar o valor da receita operacional da simulação
function getReceitaSimulacao() {
  // Pega o input de simulação da receita operacional (linha que tem data-receita="1")
  let receita = document.querySelector('tr.dre-cat .simul-valor[data-receita="1"]');
  if (!receita) receita = document.querySelector('.simul-valor[data-receita="1"]');
  return parseFloat(receita.value.replace(/\./g,'').replace(',','.')) || 1;
}

// Atualiza o percentual ao editar o valor
document.querySelectorAll('.simul-valor').forEach(function(input) {
  input.addEventListener('input', function() {
    const tr = input.closest('tr');
    const receitaVal = getReceitaSimulacao();
    const valor = parseFloat(input.value.replace(/\./g,'').replace(',','.')) || 0;
    const percInput = tr.querySelector('.simul-perc');
    if (percInput) percInput.value = receitaVal > 0 ? (valor / receitaVal * 100).toFixed(2).replace('.', ',') : '';
  });
});

// Atualiza o valor ao editar o percentual
document.querySelectorAll('.simul-perc').forEach(function(input) {
  input.addEventListener('input', function() {
    const tr = input.closest('tr');
    const receitaVal = getReceitaSimulacao();
    const perc = parseFloat(input.value.replace(/\./g,'').replace(',','.')) || 0;
    const valorInput = tr.querySelector('.simul-valor');
    if (valorInput) valorInput.value = (receitaVal * perc / 100).toFixed(2).replace('.', ',');
  });
});

// Atualiza o valor e percentual da receita operacional ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
  let receitaInput = document.querySelector('.simul-valor[data-receita="1"]');
  if (receitaInput) {
    receitaInput.dispatchEvent(new Event('input'));
  }
});

// Atualiza todas as porcentagens ao editar a receita operacional
let receitaInput = document.querySelector('.simul-valor[data-receita="1"]');
function atualizarPercentuais(receitaVal) {
  document.querySelectorAll('tr.dre-sub .simul-valor').forEach(function(input) {
    const tr = input.closest('tr');
    const valor = parseFloat(input.value.replace(',', '.')) || 0;
    const percInput = tr.querySelector('.simul-perc');
    if (percInput) percInput.value = receitaVal > 0 ? (valor / receitaVal * 100).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}) + '%' : '0,00%';
    // Atualiza categoria pai
    input.dispatchEvent(new Event('input'));
  });
}
if (receitaInput) {
  receitaInput.addEventListener('input', function() {
    const receitaVal = parseFloat(receitaInput.value.replace(',', '.')) || 1;
    atualizarPercentuais(receitaVal);
  });
}

// Efeito expandir/recolher subcategorias ao clicar na categoria
document.querySelectorAll('tr.dre-cat').forEach(function(catTr) {
  catTr.addEventListener('click', function() {
    const classes = Array.from(catTr.classList).filter(c => c.startsWith('cat_'));
    if (classes.length) {
      const catClass = classes[0];
      document.querySelectorAll('tr.dre-sub.' + catClass).forEach(function(subTr) {
        subTr.classList.toggle('dre-hide');
      });
      // Alterna o ícone +/-
//       const icon = catTr.querySelector('.toggle-icon');
//       if (icon) icon.textContent = icon.textContent === '+' ? '-' : '+';
    }
  });
});

document.querySelectorAll('.btn-perc-minus').forEach(function(btn) {
  btn.addEventListener('click', function(event) {
    event.stopPropagation(); // Impede expandir/recolher
    const input = btn.parentElement.querySelector('.simul-perc');
    let val = parseFloat(input.value.replace(',', '.')) || 0;
    val = Math.max(0, val - 0.05);
    input.value = val.toFixed(2).replace('.', ',');
    input.dispatchEvent(new Event('input'));
  });
});
document.querySelectorAll('.btn-perc-plus').forEach(function(btn) {
  btn.addEventListener('click', function(event) {
    event.stopPropagation(); // Impede expandir/recolher
    const input = btn.parentElement.querySelector('.simul-perc');
    let val = parseFloat(input.value.replace(',', '.')) || 0;
    val = val + 0.05;
    input.value = val.toFixed(2).replace('.', ',');
    input.dispatchEvent(new Event('input'));
  });
});
</script>
</body>
</html>