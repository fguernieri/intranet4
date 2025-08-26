<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sidebar igual ao acompanhamento financeiro
require_once __DIR__ . '../../../../sidebar.php';

// Autenticação igual ao acompanhamento financeiro
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}


require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Filtro: últimos 3, 6 ou 12 meses fechados
$meses = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
$periodosOpcoes = [3=>'Últimos 3 meses', 6=>'Últimos 6 meses', 12=>'Últimos 12 meses'];
$periodoSelecionado = isset($_GET['periodo']) ? intval($_GET['periodo']) : 3;
if (!in_array($periodoSelecionado, [3,6,12])) $periodoSelecionado = 3;

// Calcular meses fechados (não inclui o mês atual)
$hoje = new DateTime('now');
$primeiroDiaMesAtual = new DateTime('first day of this month');
$mesesFechados = [];
for ($i = $periodoSelecionado; $i >= 1; $i--) {
    $data = (clone $primeiroDiaMesAtual)->modify("-{$i} month");
    $ano = (int)$data->format('Y');
    $mes = (int)$data->format('n');
    $mesesFechados[] = ['ano'=>$ano, 'mes'=>$mes, 'key'=>"$ano-$mes"];
}
$anosFiltro = array_unique(array_column($mesesFechados, 'ano'));
$mesesFiltro = array_unique(array_column($mesesFechados, 'mes'));

// Buscar dados apenas dos meses fechados
$condicoes = [];
foreach ($mesesFechados as $m) {
    $condicoes[] = "(YEAR(STR_TO_DATE(DATA, '%d/%m/%Y')) = {$m['ano']} AND MONTH(STR_TO_DATE(DATA, '%d/%m/%Y')) = {$m['mes']})";
}
$wherePeriodo = implode(' OR ', $condicoes);
$sql = "
    SELECT 
        grupo, 
        produto, 
        unid, 
        qtd, 
        custo_atual,
        TOTAL, 
        fornecedor,
        STR_TO_DATE(DATA, '%d/%m/%Y') as data_formatada
    FROM fComprasTAP
    WHERE $wherePeriodo
";
$res = $conn->query($sql);

$matriz = [];
$matrizFornecedor = []; // Nova matriz para fornecedores
$colunas = [];
foreach ($mesesFechados as $m) {
    $colunas[$m['key']] = ['ano'=>$m['ano'], 'mes'=>$m['mes']];
}
while ($row = $res->fetch_assoc()) {
    $grupo = $row['grupo'];
    $produto = $row['produto'];
    $unid = $row['unid'];
    $qtd = floatval($row['qtd']);
    $custo = floatval($row['custo_atual']);
    $total = floatval($row['TOTAL']); // Usar a coluna TOTAL do banco
    $fornecedor = $row['fornecedor'] ?? 'Não Informado'; // Fornecedor
    $ano = (int)date('Y', strtotime($row['data_formatada']));
    $mes = (int)date('n', strtotime($row['data_formatada']));
    $key = "$ano-$mes";

    // Matriz por grupo (existente)
    if (!isset($matriz[$grupo][$produto])) {
        $matriz[$grupo][$produto] = [
            'unid' => $unid,
            'periodos' => []
        ];
    }
    if (!isset($matriz[$grupo][$produto]['periodos'][$key])) {
        $matriz[$grupo][$produto]['periodos'][$key] = [
            'qtd_total' => 0,
            'custo_total' => 0,
            'valor_total' => 0,
            'contagem' => 0
        ];
    }
    $matriz[$grupo][$produto]['periodos'][$key]['qtd_total'] += $qtd;
    $matriz[$grupo][$produto]['periodos'][$key]['custo_total'] += $custo;
    $matriz[$grupo][$produto]['periodos'][$key]['valor_total'] += $total; // Soma direta da coluna TOTAL
    $matriz[$grupo][$produto]['periodos'][$key]['contagem'] += 1;

    // Nova matriz por fornecedor
    if (!isset($matrizFornecedor[$fornecedor][$produto])) {
        $matrizFornecedor[$fornecedor][$produto] = [
            'unid' => $unid,
            'grupo' => $grupo, // Manter referência do grupo
            'periodos' => []
        ];
    }
    if (!isset($matrizFornecedor[$fornecedor][$produto]['periodos'][$key])) {
        $matrizFornecedor[$fornecedor][$produto]['periodos'][$key] = [
            'qtd_total' => 0,
            'custo_total' => 0,
            'valor_total' => 0,
            'contagem' => 0
        ];
    }
    $matrizFornecedor[$fornecedor][$produto]['periodos'][$key]['qtd_total'] += $qtd;
    $matrizFornecedor[$fornecedor][$produto]['periodos'][$key]['custo_total'] += $custo;
    $matrizFornecedor[$fornecedor][$produto]['periodos'][$key]['valor_total'] += $total;
    $matrizFornecedor[$fornecedor][$produto]['periodos'][$key]['contagem'] += 1;
}

// Calcular totais gerais por grupo e produto para ordenação
$totaisGrupos = [];
foreach ($matriz as $grupo => $produtos) {
    $totalGrupo = 0;
    $produtosComTotal = [];
    
    foreach ($produtos as $produto => $dados) {
        $totalProduto = 0;
        foreach ($colunas as $key => $info) {
            if (isset($dados['periodos'][$key])) {
                // Total = soma direta da coluna TOTAL do banco
                $totalProduto += $dados['periodos'][$key]['valor_total'];
            }
        }
        $produtosComTotal[$produto] = ['dados' => $dados, 'total' => $totalProduto];
        $totalGrupo += $totalProduto;
    }
    
    // Ordenar produtos por maior gasto (decrescente)
    uasort($produtosComTotal, function($a, $b) {
        return $b['total'] <=> $a['total'];
    });
    
    $totaisGrupos[$grupo] = [
        'produtos' => $produtosComTotal,
        'total' => $totalGrupo
    ];
}

// Ordenar grupos por maior gasto (decrescente)
uasort($totaisGrupos, function($a, $b) {
    return $b['total'] <=> $a['total'];
});

// Calcular Curva ABC
$totalGeralTodos = array_sum(array_column($totaisGrupos, 'total'));
$acumulado = 0;
$curvaABC = [];

foreach ($totaisGrupos as $grupo => $dadosGrupo) {
    $acumulado += $dadosGrupo['total'];
    $percentualAcumulado = ($acumulado / $totalGeralTodos) * 100;
    
    // Classificação ABC baseada no percentual acumulado
    if ($percentualAcumulado <= 80) {
        $classe = 'A'; // 80% dos gastos (mais críticos)
        $cor = '#8b0000'; // Vermelho escuro
        $icone = '🔥';
        $descricao = 'CRÍTICO';
    } elseif ($percentualAcumulado <= 95) {
        $classe = 'B'; // 15% dos gastos (importantes)
        $cor = '#b8860b'; // Dourado escuro
        $icone = '⚠️';
        $descricao = 'IMPORTANTE';
    } else {
        $classe = 'C'; // 5% dos gastos (menos críticos)
        $cor = '#006400'; // Verde escuro
        $icone = '✅';
        $descricao = 'CONTROLADO';
    }
    
    $curvaABC[$grupo] = [
        'classe' => $classe,
        'cor' => $cor,
        'icone' => $icone,
        'descricao' => $descricao,
        'percentual' => ($dadosGrupo['total'] / $totalGeralTodos) * 100,
        'percentual_acumulado' => $percentualAcumulado
    ];
}

// Calcular totais por fornecedor para ordenação
$totaisFornecedores = [];
foreach ($matrizFornecedor as $fornecedor => $produtos) {
    $totalFornecedor = 0;
    $produtosComTotal = [];
    
    foreach ($produtos as $produto => $dados) {
        $totalProduto = 0;
        foreach ($colunas as $key => $info) {
            if (isset($dados['periodos'][$key])) {
                $totalProduto += $dados['periodos'][$key]['valor_total'];
            }
        }
        $produtosComTotal[$produto] = ['dados' => $dados, 'total' => $totalProduto];
        $totalFornecedor += $totalProduto;
    }
    
    // Ordenar produtos por maior gasto (decrescente)
    uasort($produtosComTotal, function($a, $b) {
        return $b['total'] <=> $a['total'];
    });
    
    $totaisFornecedores[$fornecedor] = [
        'produtos' => $produtosComTotal,
        'total' => $totalFornecedor
    ];
}

// Ordenar fornecedores por maior gasto (decrescente)
uasort($totaisFornecedores, function($a, $b) {
    return $b['total'] <=> $a['total'];
});

// Calcular Curva ABC para fornecedores
$totalGeralFornecedores = array_sum(array_column($totaisFornecedores, 'total'));
$acumuladoForn = 0;
$curvaABCFornecedores = [];

foreach ($totaisFornecedores as $fornecedor => $dadosFornecedor) {
    $acumuladoForn += $dadosFornecedor['total'];
    $percentualAcumuladoForn = ($acumuladoForn / $totalGeralFornecedores) * 100;
    
    // Classificação ABC baseada no percentual acumulado
    if ($percentualAcumuladoForn <= 80) {
        $classe = 'A'; // 80% dos gastos (mais críticos)
        $cor = '#8b0000'; // Vermelho escuro
        $icone = '🔥';
        $descricao = 'CRÍTICO';
    } elseif ($percentualAcumuladoForn <= 95) {
        $classe = 'B'; // 15% dos gastos (importantes)
        $cor = '#b8860b'; // Dourado escuro
        $icone = '⚠️';
        $descricao = 'IMPORTANTE';
    } else {
        $classe = 'C'; // 5% dos gastos (menos críticos)
        $cor = '#006400'; // Verde escuro
        $icone = '✅';
        $descricao = 'CONTROLADO';
    }
    
    $curvaABCFornecedores[$fornecedor] = [
        'classe' => $classe,
        'cor' => $cor,
        'icone' => $icone,
        'descricao' => $descricao,
        'percentual' => ($dadosFornecedor['total'] / $totalGeralFornecedores) * 100,
        'percentual_acumulado' => $percentualAcumuladoForn
    ];
}

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Análise de Compras</title>
    <link href="/assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .dre-hide { 
            display: none; 
        }
        .dre-sub {
            transition: none;
            opacity: 1;
        }
        .dre-sub.dre-hide {
            opacity: 1;
            transform: none;
        }
        .grupo-row:hover {
            background: linear-gradient(135deg, #3a4a6a 0%, #2a3a5a 100%) !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            transition: all 0.2s ease;
        }
        /* Reduzir espaçamento entre linhas dos produtos */
        .dre-sub td {
            padding: 2px 4px !important;
            line-height: 1.2;
        }
        /* Produto alinhado literalmente abaixo do grupo */
        .dre-sub td:first-child {
            padding-left: 8px !important;
            text-align: left !important;
        }
        /* Melhorar aparência da tabela */
        table {
            border-spacing: 0 1px !important;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        /* Efeito suave nos produtos */
        .dre-sub {
            transition: all 0.2s ease;
        }
        .dre-sub:hover {
            background: rgba(255, 224, 102, 0.1) !important;
            transform: translateX(2px);
        }
        /* Cores alternadas para os meses - tons harmônicos */
        .mes-par {
            background: #1a1a1a !important;
        }
        .mes-impar {
            background: #2a2a2a !important;
        }
        /* Headers dos meses com visual moderno */
        .header-mes-par {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%) !important;
            border-left: 2px solid rgba(255, 224, 102, 0.8) !important;
            border-right: 2px solid rgba(255, 224, 102, 0.8) !important;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.3);
        }
        .header-mes-impar {
            background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%) !important;
            border-left: 2px solid rgba(255, 215, 0, 0.8) !important;
            border-right: 2px solid rgba(255, 215, 0, 0.8) !important;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.3);
        }
        /* Bordas mais sutis e elegantes */
        .borda-mes {
            border-left: 1px solid rgba(255, 224, 102, 0.3) !important;
        }
        .borda-final-mes {
            border-right: 1px solid rgba(255, 224, 102, 0.6) !important;
        }
        .borda-interna {
            border-right: 1px solid rgba(255, 255, 255, 0.1) !important;
        }
        /* Efeito hover nos grupos */
        .grupo-row:hover {
            background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%) !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            transition: all 0.2s ease;
        }
        /* Estilos para as colunas de totais e ranking */
        .total-col {
            background: #0f3460 !important;
            border-left: 1px solid rgba(15, 52, 96, 0.5) !important;
        }
        .ranking-col {
            background: #4a1c40 !important;
            border-left: 1px solid rgba(74, 28, 64, 0.5) !important;
        }
        /* Efeitos hover nas colunas especiais */
        .total-col:hover {
            background: #1a4a7a !important;
            transform: scale(1.02);
            transition: all 0.2s ease;
        }
        .ranking-col:hover {
            background: #6a2c60 !important;
            transform: scale(1.02);
            transition: all 0.2s ease;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 flex min-h-screen">
    <!-- Sidebar já incluída no topo do arquivo -->
    <main class="flex-1 bg-gray-900 p-6 relative">
        <!-- Saudação BEM VINDO -->
        <header class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-bold">
                Bem-vindo, <?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário'); ?>
            </h1>
            <p class="text-gray-400 text-sm">
                <?php
                $hoje = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                $fmt = new IntlDateFormatter(
                    'pt_BR',
                    IntlDateFormatter::FULL,
                    IntlDateFormatter::NONE,
                    'America/Sao_Paulo',
                    IntlDateFormatter::GREGORIAN
                );
                echo $fmt->format($hoje);
                ?>
            </p>
        </header>

        <h1 class="text-2xl font-bold text-yellow-400 mb-6">Análise de Compras - Curva ABC</h1>

        <!-- Navegação entre análises e Barra de busca -->
        <div class="mb-6 flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
            <!-- Botões de navegação -->
            <div class="flex gap-3">
                <div class="bg-yellow-500 text-black px-4 py-2 rounded-lg font-bold shadow-lg">
                    📊 TAP (Atual)
                </div>
                <a href="analisecompraswab.php<?php echo isset($_GET['periodo']) ? '?periodo=' . $_GET['periodo'] : ''; ?>" 
                   class="bg-gray-700 text-white px-4 py-2 rounded-lg font-bold hover:bg-gray-600 transition-colors shadow-lg border-2 border-gray-600 hover:border-gray-500">
                    📈 WAB
                </a>
            </div>
            
            <!-- Barra de busca -->
            <div class="flex-1 sm:max-w-md">
                <label for="search-produto" class="block text-sm font-medium text-gray-300 mb-2">
                    🔍 Pesquisar Produto:
                </label>
                <input type="text" 
                       id="search-produto" 
                       placeholder="Digite o nome do produto para filtrar..."
                       class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-md text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                <p class="text-xs text-gray-500 mt-1">
                    Busca em grupos e produtos em tempo real
                </p>
            </div>
        </div>

        <!-- Explicação da Curva ABC -->
        <div class="mb-4 p-3 bg-gray-800 rounded-lg text-sm">
            <strong class="text-yellow-400">Curva ABC:</strong> 
            <span class="text-red-400">A (🔥 CRÍTICO)</span> = 80% dos gastos | 
            <span class="text-yellow-400">B (⚠️ IMPORTANTE)</span> = 15% dos gastos | 
            <span class="text-green-400">C (✅ CONTROLADO)</span> = 5% dos gastos
        </div>

        <!-- Filtro de período -->
        <form method="get" class="mb-4 flex gap-2 items-end">
            <label>
                Período:
                <select name="periodo" class="bg-gray-800 text-white px-2 py-1 rounded">
                    <?php foreach ($periodosOpcoes as $val => $label): ?>
                        <option value="<?=$val?>" <?=($periodoSelecionado==$val)?'selected':''?>><?=$label?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="bg-yellow-400 text-black px-3 py-1 rounded font-bold">Filtrar</button>
        </form>

        <table class="min-w-full text-xs mx-auto border border-gray-700 rounded" style="border-collapse:separate; border-spacing:0 4px;">
            <thead>
                <tr>
                    <th rowspan="2" style="text-align:center; background:#000000; color:#ffffff; font-weight:bold; padding:12px 8px; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">Grupo</th>
                    <th rowspan="2" style="text-align:center; background:#000000; color:#ffffff; font-weight:bold; padding:12px 8px; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">Unid</th>
                    <?php 
                    $mesIndex = 0;
                    foreach ($colunas as $key => $info): 
                        $classMes = ($mesIndex % 2 == 0) ? 'header-mes-par' : 'header-mes-impar';
                        $mesIndex++;
                    ?>
                        <th colspan="3" class="<?=$classMes?>" style="text-align:center; color:#fff; font-weight:bold; padding:12px 8px; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                            <?=$meses[$info['mes']]?>/<?=$info['ano']?>
                        </th>
                    <?php endforeach; ?>
                    <th style="text-align:center; background:#0f3460; color:#e2e8f0; border-left: 1px solid rgba(255,224,102,0.3); font-weight:bold; padding:12px 8px; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">Total Geral</th>
                    <th style="text-align:center; background:#000000; color:#ffffff; border-left: 1px solid rgba(255,255,255,0.1); font-weight:bold; padding:12px 8px; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">Curva ABC</th>
                </tr>
                <tr>
                    <?php 
                    $mesIndex = 0;
                    foreach ($colunas as $key => $info): 
                        $classMes = ($mesIndex % 2 == 0) ? 'mes-par' : 'mes-impar';
                        $isFirst = ($mesIndex == 0);
                        $isLast = ($mesIndex == count($colunas) - 1);
                        $mesIndex++;
                    ?>
                        <th class="<?=$classMes?> <?= $isFirst ? 'borda-mes' : '' ?>" style="text-align:center; color:#e2e8f0; font-size:11px; font-weight:600; padding:8px 4px; <?= $isFirst ? '' : 'border-left: 1px solid rgba(255,224,102,0.2);' ?>">Qtde</th>
                        <th class="<?=$classMes?> borda-interna" style="text-align:center; color:#e2e8f0; font-size:11px; font-weight:600; padding:8px 4px;">Média</th>
                        <th class="<?=$classMes?> <?= $isLast ? 'borda-final-mes' : 'borda-interna' ?>" style="text-align:center; color:#e2e8f0; font-size:11px; font-weight:600; padding:8px 4px;">Total</th>
                    <?php endforeach; ?>
                    <th style="text-align:center; background:#0f3460; color:#e2e8f0; font-size:11px; font-weight:600; padding:8px 4px; border-left: 1px solid rgba(255,224,102,0.3);">Valor</th>
                    <th style="text-align:center; background:#000000; color:#ffffff; font-size:11px; font-weight:600; padding:8px 4px; border-left: 1px solid rgba(255,255,255,0.1);">Classe</th>
                </tr>
            </thead>
            <tbody>
        <?php 
        $rankingGrupo = 1;
        foreach ($totaisGrupos as $grupo => $dadosGrupo): 
            $produtos = $dadosGrupo['produtos'];
            $totalGrupoGeral = $dadosGrupo['total'];
            
            // Calcular Curva ABC para produtos do grupo
            $totalGrupoAtual = $dadosGrupo['total'];
            $acumuladoProduto = 0;
            $curvaABCProdutos = [];
            
            foreach ($produtos as $produto => $produtoData) {
                $acumuladoProduto += $produtoData['total'];
                $percentualAcumuladoProduto = ($acumuladoProduto / $totalGrupoAtual) * 100;
                
                if ($percentualAcumuladoProduto <= 70) {
                    $classeProduto = 'A';
                    $corProduto = '#8b0000';
                    $iconeProduto = '🔥';
                } elseif ($percentualAcumuladoProduto <= 90) {
                    $classeProduto = 'B';
                    $corProduto = '#b8860b';
                    $iconeProduto = '⚠️';
                } else {
                    $classeProduto = 'C';
                    $corProduto = '#006400';
                    $iconeProduto = '✅';
                }
                
                $curvaABCProdutos[$produto] = [
                    'classe' => $classeProduto,
                    'cor' => $corProduto,
                    'icone' => $iconeProduto,
                    'percentual' => ($produtoData['total'] / $totalGrupoAtual) * 100
                ];
            }
        ?>
            <?php 
            // Soma total do grupo por período
            $somaGrupo = array_fill_keys(array_keys($colunas), 0);
            foreach ($produtos as $produto => $produtoData) {
                $dados = $produtoData['dados'];
                foreach ($colunas as $key => $info) {
                    if (isset($dados['periodos'][$key])) {
                        // Total do grupo = soma direta da coluna TOTAL
                        $somaGrupo[$key] += $dados['periodos'][$key]['valor_total'];
                    }
                }
            }
            $grupoKey = md5($grupo);
            ?>
            <tr class="grupo-row dre-cat" style="background:#232946; border-top:2px solid #ffe066; font-weight:bold; cursor:pointer;" onclick="toggleCategory('<?=$grupoKey?>')">
                <td style="padding:15px 20px; color:#ffe066; font-size:1.1em;" colspan="2">
                    📦 <?=htmlspecialchars($grupo)?></td>
                <?php 
                $mesIndex = 0;
                foreach ($colunas as $key => $info): 
                    $classMes = ($mesIndex % 2 == 0) ? 'mes-par' : 'mes-impar';
                    $isFirst = ($mesIndex == 0);
                    $isLast = ($mesIndex == count($colunas) - 1);
                    $mesIndex++;
                ?>
                    <td class="<?=$classMes?>" style="padding:15px 8px; <?= $isFirst ? 'border-left: 1px solid rgba(255,224,102,0.3);' : 'border-left: 1px solid rgba(255,224,102,0.1);' ?>"></td>
                    <td class="<?=$classMes?>" style="padding:15px 8px; border-left: 1px solid rgba(255,255,255,0.05);"></td>
                    <td class="<?=$classMes?>" style="text-align:center; color:#ffe066; font-weight:bold; padding:15px 8px; font-size:13px; border-left: 1px solid rgba(255,255,255,0.05); <?= $isLast ? 'border-right: 1px solid rgba(255,224,102,0.4);' : '' ?>">
                        <?= $somaGrupo[$key] > 0 ? number_format($somaGrupo[$key],2,',','.') : '-' ?>
                    </td>
                <?php endforeach; ?>
                <td style="text-align:center; background:#0f3460; color:#e2e8f0; font-weight:bold; padding:15px 12px; border-left: 1px solid rgba(255,224,102,0.3);">
                    <?= $totalGrupoGeral > 0 ? number_format($totalGrupoGeral,2,',','.') : '-' ?>
                </td>
                <?php 
                // Buscar dados da Curva ABC para este grupo
                $abcData = $curvaABC[$grupo];
                ?>
                <td style="text-align:center; background:<?=$abcData['cor']?>; color:#e2e8f0; font-weight:bold; padding:15px 12px; border-left: 1px solid rgba(255,255,255,0.1);">
                    <?=$abcData['classe']?> <?=$abcData['icone']?>
                </td>
            </tr>
            <?php 
            foreach ($produtos as $produto => $produtoData): 
                $dados = $produtoData['dados'];
                $totalProduto = $produtoData['total'];
            ?>
            <tr class="dre-sub <?=$grupoKey?>">
                <td style="padding:2px 8px; font-weight:500; color:#ffe066;"> <?=htmlspecialchars($produto)?></td>
                <td style="padding:2px 4px; color:#ffe066;"> <?=htmlspecialchars($dados['unid'])?></td>
                <?php 
                $totalGeralProduto = 0;
                $mesIndex = 0;
                foreach ($colunas as $key => $info):
                    $classMes = ($mesIndex % 2 == 0) ? 'mes-par' : 'mes-impar';
                    $isFirst = ($mesIndex == 0);
                    $isLast = ($mesIndex == count($colunas) - 1);
                    $mesIndex++;
                    $qtd = isset($dados['periodos'][$key]) ? number_format($dados['periodos'][$key]['qtd_total'],2,',','.') : '-';
                    $media = (isset($dados['periodos'][$key]) && $dados['periodos'][$key]['contagem']>0) ? number_format($dados['periodos'][$key]['custo_total']/$dados['periodos'][$key]['contagem'],2,',','.') : '-';
                    // Total do mês = soma direta da coluna TOTAL
                    if (isset($dados['periodos'][$key])) {
                        $total = $dados['periodos'][$key]['valor_total'];
                    } else {
                        $total = 0;
                    }
                    $totalFormat = $total > 0 ? number_format($total,2,',','.') : '-';
                    $totalGeralProduto += $total;
                ?>
                    <td class="<?=$classMes?>" style="text-align:center; padding:6px 4px; color:#cbd5e0; font-size:12px; <?= $isFirst ? 'border-left: 1px solid rgba(255,224,102,0.3);' : 'border-left: 1px solid rgba(255,224,102,0.1);' ?>"> <?=$qtd?></td>
                    <td class="<?=$classMes?>" style="text-align:center; padding:6px 4px; color:#cbd5e0; font-size:12px; border-left: 1px solid rgba(255,255,255,0.05);"> <?=$media?></td>
                    <td class="<?=$classMes?>" style="text-align:center; padding:6px 4px; color:#fff; font-size:12px; font-weight:500; border-left: 1px solid rgba(255,255,255,0.05); <?= $isLast ? 'border-right: 1px solid rgba(255,224,102,0.4);' : '' ?>"> <?=$totalFormat?></td>
                <?php endforeach; ?>
                <td style="text-align:center; background:#0f3460; color:#e2e8f0; font-weight:500; padding:2px 4px; border-left: 1px solid rgba(255,224,102,0.3);">
                    <?= $totalGeralProduto > 0 ? number_format($totalGeralProduto,2,',','.') : '-' ?>
                </td>
                <?php 
                // Buscar dados da Curva ABC para este produto
                $abcProdutoData = $curvaABCProdutos[$produto];
                ?>
                <td style="text-align:center; background:<?=$abcProdutoData['cor']?>; color:#e2e8f0; font-weight:500; padding:2px 4px; border-left: 1px solid rgba(255,255,255,0.1);">
                    <?=$abcProdutoData['classe']?> <?=$abcProdutoData['icone']?>
                </td>
            </tr>
            <?php 
            endforeach; 
            $rankingGrupo++;
            ?>
        <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Segunda Análise: Por Fornecedor -->
        <div class="mt-16">
            <h2 class="text-2xl font-bold text-blue-400 mb-6">📋 Análise por Fornecedor - Curva ABC</h2>
            
            <!-- Explicação da Curva ABC para fornecedores -->
            <div class="mb-4 p-3 bg-gray-800 rounded-lg text-sm">
                <strong class="text-blue-400">Análise por Fornecedor:</strong> 
                <span class="text-red-400">A (🔥 CRÍTICO)</span> = 80% dos gastos | 
                <span class="text-yellow-400">B (⚠️ IMPORTANTE)</span> = 15% dos gastos | 
                <span class="text-green-400">C (✅ CONTROLADO)</span> = 5% dos gastos
            </div>

            <!-- Barra de busca para fornecedores -->
            <div class="mb-4">
                <label for="search-fornecedor" class="block text-sm font-medium text-gray-300 mb-2">
                    🔍 Pesquisar Fornecedor:
                </label>
                <input type="text" 
                       id="search-fornecedor" 
                       placeholder="Digite o nome do fornecedor para filtrar..."
                       class="w-full max-w-md px-3 py-2 bg-gray-800 border border-gray-600 rounded-md text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <p class="text-xs text-gray-500 mt-1">
                    Busca em fornecedores e produtos em tempo real
                </p>
            </div>

            <table id="tabela-fornecedores" class="min-w-full text-xs mx-auto border border-gray-700 rounded" style="border-collapse:separate; border-spacing:0 4px;">
                <thead>
                    <tr>
                        <th rowspan="2" style="text-align:center; background:#000000; color:#ffffff; font-weight:bold; padding:12px 8px; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">Fornecedor</th>
                        <th rowspan="2" style="text-align:center; background:#000000; color:#ffffff; font-weight:bold; padding:12px 8px; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">Unid</th>
                        <?php 
                        $mesIndex = 0;
                        foreach ($colunas as $key => $info): 
                            $classMes = ($mesIndex % 2 == 0) ? 'header-mes-par' : 'header-mes-impar';
                            $mesIndex++;
                        ?>
                            <th colspan="3" class="<?=$classMes?>" style="text-align:center; color:#fff; font-weight:bold; padding:12px 8px; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                                <?=$meses[$info['mes']]?>/<?=$info['ano']?>
                            </th>
                        <?php endforeach; ?>
                        <th style="text-align:center; background:#0f3460; color:#e2e8f0; border-left: 1px solid rgba(255,224,102,0.3); font-weight:bold; padding:12px 8px; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">Total Geral</th>
                        <th style="text-align:center; background:#000000; color:#ffffff; border-left: 1px solid rgba(255,255,255,0.1); font-weight:bold; padding:12px 8px; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">Curva ABC</th>
                    </tr>
                    <tr>
                        <?php 
                        $mesIndex = 0;
                        foreach ($colunas as $key => $info): 
                            $classMes = ($mesIndex % 2 == 0) ? 'mes-par' : 'mes-impar';
                            $isFirst = ($mesIndex == 0);
                            $isLast = ($mesIndex == count($colunas) - 1);
                            $mesIndex++;
                        ?>
                            <th class="<?=$classMes?> <?= $isFirst ? 'borda-mes' : '' ?>" style="text-align:center; color:#e2e8f0; font-size:11px; font-weight:600; padding:8px 4px; <?= $isFirst ? '' : 'border-left: 1px solid rgba(255,224,102,0.2);' ?>">Qtde</th>
                            <th class="<?=$classMes?> borda-interna" style="text-align:center; color:#e2e8f0; font-size:11px; font-weight:600; padding:8px 4px;">Média</th>
                            <th class="<?=$classMes?> <?= $isLast ? 'borda-final-mes' : 'borda-interna' ?>" style="text-align:center; color:#e2e8f0; font-size:11px; font-weight:600; padding:8px 4px;">Total</th>
                        <?php endforeach; ?>
                        <th style="text-align:center; background:#0f3460; color:#e2e8f0; font-size:11px; font-weight:600; padding:8px 4px; border-left: 1px solid rgba(255,224,102,0.3);">Valor</th>
                        <th style="text-align:center; background:#000000; color:#ffffff; font-size:11px; font-weight:600; padding:8px 4px; border-left: 1px solid rgba(255,255,255,0.1);">Classe</th>
                    </tr>
                </thead>
                <tbody>
            <?php 
            $rankingFornecedor = 1;
            foreach ($totaisFornecedores as $fornecedor => $dadosFornecedor): 
                $produtos = $dadosFornecedor['produtos'];
                $totalFornecedorGeral = $dadosFornecedor['total'];
                
                // Calcular Curva ABC para produtos do fornecedor
                $totalFornecedorAtual = $dadosFornecedor['total'];
                $acumuladoProdutoForn = 0;
                $curvaABCProdutosForn = [];
                
                foreach ($produtos as $produto => $produtoData) {
                    $acumuladoProdutoForn += $produtoData['total'];
                    $percentualAcumuladoProdutoForn = ($acumuladoProdutoForn / $totalFornecedorAtual) * 100;
                    
                    if ($percentualAcumuladoProdutoForn <= 70) {
                        $classeProdutoForn = 'A';
                        $corProdutoForn = '#8b0000';
                        $iconeProdutoForn = '🔥';
                    } elseif ($percentualAcumuladoProdutoForn <= 90) {
                        $classeProdutoForn = 'B';
                        $corProdutoForn = '#b8860b';
                        $iconeProdutoForn = '⚠️';
                    } else {
                        $classeProdutoForn = 'C';
                        $corProdutoForn = '#006400';
                        $iconeProdutoForn = '✅';
                    }
                    
                    $curvaABCProdutosForn[$produto] = [
                        'classe' => $classeProdutoForn,
                        'cor' => $corProdutoForn,
                        'icone' => $iconeProdutoForn,
                        'percentual' => ($produtoData['total'] / $totalFornecedorAtual) * 100
                    ];
                }
            ?>
                <?php 
                // Soma total do fornecedor por período
                $somaFornecedor = array_fill_keys(array_keys($colunas), 0);
                foreach ($produtos as $produto => $produtoData) {
                    $dados = $produtoData['dados'];
                    foreach ($colunas as $key => $info) {
                        if (isset($dados['periodos'][$key])) {
                            $somaFornecedor[$key] += $dados['periodos'][$key]['valor_total'];
                        }
                    }
                }
                $fornecedorKey = md5($fornecedor);
                ?>
                <tr class="fornecedor-row dre-cat-forn" style="background:#1a3a5a; border-top:2px solid #66a3ff; font-weight:bold; cursor:pointer;" onclick="toggleCategoryFornecedor('<?=$fornecedorKey?>')">
                    <td style="padding:15px 20px; color:#66a3ff; font-size:1.1em;" colspan="2">
                        🏢 <?=htmlspecialchars($fornecedor)?></td>
                    <?php 
                    $mesIndex = 0;
                    foreach ($colunas as $key => $info): 
                        $classMes = ($mesIndex % 2 == 0) ? 'mes-par' : 'mes-impar';
                        $isFirst = ($mesIndex == 0);
                        $isLast = ($mesIndex == count($colunas) - 1);
                        $mesIndex++;
                    ?>
                        <td class="<?=$classMes?>" style="padding:15px 8px; <?= $isFirst ? 'border-left: 1px solid rgba(102,163,255,0.3);' : 'border-left: 1px solid rgba(102,163,255,0.1);' ?>"></td>
                        <td class="<?=$classMes?>" style="padding:15px 8px; border-left: 1px solid rgba(255,255,255,0.05);"></td>
                        <td class="<?=$classMes?>" style="text-align:center; color:#66a3ff; font-weight:bold; padding:15px 8px; font-size:13px; border-left: 1px solid rgba(255,255,255,0.05); <?= $isLast ? 'border-right: 1px solid rgba(102,163,255,0.4);' : '' ?>">
                            <?= $somaFornecedor[$key] > 0 ? number_format($somaFornecedor[$key],2,',','.') : '-' ?>
                        </td>
                    <?php endforeach; ?>
                    <td style="text-align:center; background:#0f3460; color:#e2e8f0; font-weight:bold; padding:15px 12px; border-left: 1px solid rgba(102,163,255,0.3);">
                        <?= $totalFornecedorGeral > 0 ? number_format($totalFornecedorGeral,2,',','.') : '-' ?>
                    </td>
                    <?php 
                    // Buscar dados da Curva ABC para este fornecedor
                    $abcDataForn = $curvaABCFornecedores[$fornecedor];
                    ?>
                    <td style="text-align:center; background:<?=$abcDataForn['cor']?>; color:#e2e8f0; font-weight:bold; padding:15px 12px; border-left: 1px solid rgba(255,255,255,0.1);">
                        <?=$abcDataForn['classe']?> <?=$abcDataForn['icone']?>
                    </td>
                </tr>
                <?php 
                foreach ($produtos as $produto => $produtoData): 
                    $dados = $produtoData['dados'];
                    $totalProduto = $produtoData['total'];
                ?>
                <tr class="dre-sub-forn <?=$fornecedorKey?>">
                    <td style="padding:2px 8px; font-weight:500; color:#66a3ff;"> <?=htmlspecialchars($produto)?> <span class="text-gray-400 text-xs">(<?=htmlspecialchars($dados['grupo'])?>)</span></td>
                    <td style="padding:2px 4px; color:#66a3ff;"> <?=htmlspecialchars($dados['unid'])?></td>
                    <?php 
                    $totalGeralProdutoForn = 0;
                    $mesIndex = 0;
                    foreach ($colunas as $key => $info):
                        $classMes = ($mesIndex % 2 == 0) ? 'mes-par' : 'mes-impar';
                        $isFirst = ($mesIndex == 0);
                        $isLast = ($mesIndex == count($colunas) - 1);
                        $mesIndex++;
                        $qtd = isset($dados['periodos'][$key]) ? number_format($dados['periodos'][$key]['qtd_total'],2,',','.') : '-';
                        $media = (isset($dados['periodos'][$key]) && $dados['periodos'][$key]['contagem']>0) ? number_format($dados['periodos'][$key]['custo_total']/$dados['periodos'][$key]['contagem'],2,',','.') : '-';
                        if (isset($dados['periodos'][$key])) {
                            $total = $dados['periodos'][$key]['valor_total'];
                        } else {
                            $total = 0;
                        }
                        $totalFormat = $total > 0 ? number_format($total,2,',','.') : '-';
                        $totalGeralProdutoForn += $total;
                    ?>
                        <td class="<?=$classMes?>" style="text-align:center; padding:6px 4px; color:#cbd5e0; font-size:12px; <?= $isFirst ? 'border-left: 1px solid rgba(102,163,255,0.3);' : 'border-left: 1px solid rgba(102,163,255,0.1);' ?>"> <?=$qtd?></td>
                        <td class="<?=$classMes?>" style="text-align:center; padding:6px 4px; color:#cbd5e0; font-size:12px; border-left: 1px solid rgba(255,255,255,0.05);"> <?=$media?></td>
                        <td class="<?=$classMes?>" style="text-align:center; padding:6px 4px; color:#fff; font-size:12px; font-weight:500; border-left: 1px solid rgba(255,255,255,0.05); <?= $isLast ? 'border-right: 1px solid rgba(102,163,255,0.4);' : '' ?>"> <?=$totalFormat?></td>
                    <?php endforeach; ?>
                    <td style="text-align:center; background:#0f3460; color:#e2e8f0; font-weight:500; padding:2px 4px; border-left: 1px solid rgba(102,163,255,0.3);">
                        <?= $totalGeralProdutoForn > 0 ? number_format($totalGeralProdutoForn,2,',','.') : '-' ?>
                    </td>
                    <?php 
                    // Buscar dados da Curva ABC para este produto
                    $abcProdutoDataForn = $curvaABCProdutosForn[$produto];
                    ?>
                    <td style="text-align:center; background:<?=$abcProdutoDataForn['cor']?>; color:#e2e8f0; font-weight:500; padding:2px 4px; border-left: 1px solid rgba(255,255,255,0.1);">
                        <?=$abcProdutoDataForn['classe']?> <?=$abcProdutoDataForn['icone']?>
                    </td>
                </tr>
                <?php 
                endforeach; 
                $rankingFornecedor++;
                ?>
            <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <script>
        function initializeDREToggle() {
            // Inicializa todos os elementos como fechados (escondidos)
            document.querySelectorAll('tr.dre-sub').forEach(subRow => {
               subRow.classList.add('dre-hide');
            });
            // Inicializa fornecedores também fechados
            document.querySelectorAll('tr.dre-sub-forn').forEach(subRow => {
               subRow.classList.add('dre-hide');
            });
        }

        // Função para alternar categorias - versão simplificada
        function toggleCategory(categoryKey) {
            const categoryElements = document.querySelectorAll('tr.dre-sub.' + CSS.escape(categoryKey));
            
            // Verifica se algum produto está visível
            const isOpening = categoryElements.length > 0 && categoryElements[0].classList.contains('dre-hide');
            
            // Toggle simples das linhas de produtos
            categoryElements.forEach(element => {
                if (isOpening) {
                    element.classList.remove('dre-hide');
                } else {
                    element.classList.add('dre-hide');
                }
            });
        }

        // Função para alternar categorias de fornecedores
        function toggleCategoryFornecedor(categoryKey) {
            const categoryElements = document.querySelectorAll('tr.dre-sub-forn.' + CSS.escape(categoryKey));
            
            // Verifica se algum produto está visível
            const isOpening = categoryElements.length > 0 && categoryElements[0].classList.contains('dre-hide');
            
            // Toggle simples das linhas de produtos
            categoryElements.forEach(element => {
                if (isOpening) {
                    element.classList.remove('dre-hide');
                } else {
                    element.classList.add('dre-hide');
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            initializeDREToggle();
            console.log('Página de análise de compras carregada - efeitos simplificados');
            
            // Funcionalidade de busca para grupos/produtos
            const searchInput = document.getElementById('search-produto');
            const allRows = document.querySelectorAll('tbody tr');
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase().trim();
                    
                    if (searchTerm === '') {
                        // Se busca vazia, mostra todos os grupos e esconde produtos
                        allRows.forEach(row => {
                            if (row.classList.contains('grupo-row')) {
                                row.style.display = '';
                            } else if (row.classList.contains('dre-sub')) {
                                row.style.display = '';
                                row.classList.add('dre-hide'); // Esconde produtos por padrão
                            }
                        });
                    } else {
                        let hasVisibleProducts = {};
                        
                        // Primeiro, processa todos os produtos
                        allRows.forEach(row => {
                            if (row.classList.contains('dre-sub')) {
                                const productName = row.querySelector('td:first-child').textContent.toLowerCase();
                                const groupKey = Array.from(row.classList).find(cls => cls !== 'dre-sub');
                                
                                if (productName.includes(searchTerm)) {
                                    row.style.display = '';
                                    row.classList.remove('dre-hide'); // Mostra produto que match
                                    hasVisibleProducts[groupKey] = true;
                                } else {
                                    row.style.display = 'none'; // Esconde produto que não match
                                }
                            }
                        });
                        
                        // Depois, processa os grupos
                        allRows.forEach(row => {
                            if (row.classList.contains('grupo-row')) {
                                const groupName = row.querySelector('td').textContent.toLowerCase();
                                const groupKey = row.getAttribute('onclick').match(/'([^']+)'/)[1];
                                
                                // Mostra grupo se o nome do grupo match OU se tem produtos visíveis
                                if (groupName.includes(searchTerm) || hasVisibleProducts[groupKey]) {
                                    row.style.display = '';
                                    
                                    // Se o grupo match mas não tem produtos visíveis, mostra todos os produtos do grupo
                                    if (groupName.includes(searchTerm) && !hasVisibleProducts[groupKey]) {
                                        document.querySelectorAll(`.dre-sub.${groupKey}`).forEach(productRow => {
                                            productRow.style.display = '';
                                            productRow.classList.remove('dre-hide');
                                        });
                                    }
                                } else {
                                    row.style.display = 'none'; // Esconde grupo se não match e não tem produtos visíveis
                                }
                            }
                        });
                    }
                });
            }

            // Funcionalidade de busca para fornecedores
            const searchFornecedorInput = document.getElementById('search-fornecedor');
            const allFornecedorRows = document.querySelectorAll('#tabela-fornecedores tbody tr');
            
            if (searchFornecedorInput) {
                searchFornecedorInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase().trim();
                    
                    if (searchTerm === '') {
                        // Se busca vazia, mostra todos os fornecedores e esconde produtos
                        allFornecedorRows.forEach(row => {
                            if (row.classList.contains('fornecedor-row')) {
                                row.style.display = '';
                            } else if (row.classList.contains('dre-sub-forn')) {
                                row.style.display = '';
                                row.classList.add('dre-hide'); // Esconde produtos por padrão
                            }
                        });
                    } else {
                        let hasVisibleProductsForn = {};
                        
                        // Primeiro, processa todos os produtos do fornecedor
                        allFornecedorRows.forEach(row => {
                            if (row.classList.contains('dre-sub-forn')) {
                                const productName = row.querySelector('td:first-child').textContent.toLowerCase();
                                const fornecedorKey = Array.from(row.classList).find(cls => cls !== 'dre-sub-forn');
                                
                                if (productName.includes(searchTerm)) {
                                    row.style.display = '';
                                    row.classList.remove('dre-hide'); // Mostra produto que match
                                    hasVisibleProductsForn[fornecedorKey] = true;
                                } else {
                                    row.style.display = 'none'; // Esconde produto que não match
                                }
                            }
                        });
                        
                        // Depois, processa os fornecedores
                        allFornecedorRows.forEach(row => {
                            if (row.classList.contains('fornecedor-row')) {
                                const fornecedorName = row.querySelector('td').textContent.toLowerCase();
                                const fornecedorKey = row.getAttribute('onclick').match(/'([^']+)'/)[1];
                                
                                // Mostra fornecedor se o nome match OU se tem produtos visíveis
                                if (fornecedorName.includes(searchTerm) || hasVisibleProductsForn[fornecedorKey]) {
                                    row.style.display = '';
                                    
                                    // Se o fornecedor match mas não tem produtos visíveis, mostra todos os produtos do fornecedor
                                    if (fornecedorName.includes(searchTerm) && !hasVisibleProductsForn[fornecedorKey]) {
                                        document.querySelectorAll(`.dre-sub-forn.${fornecedorKey}`).forEach(productRow => {
                                            productRow.style.display = '';
                                            productRow.classList.remove('dre-hide');
                                        });
                                    }
                                } else {
                                    row.style.display = 'none'; // Esconde fornecedor se não match e não tem produtos visíveis
                                }
                            }
                        });
                    }
                });
            }
        });
        $(document).ready(function() {
            $('.select2').select2({
                placeholder: "Selecione",
                allowClear: true,
                width: 'resolve'
            });
        });
        </script>
    </main>
</body>
</html>