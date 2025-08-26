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

// Filtros
$meses = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
$periodosOpcoes = [3=>'Últimos 3 meses', 6=>'Últimos 6 meses', 12=>'Últimos 12 meses'];
$basesOpcoes = ['TAP'=>'TAP', 'WAB'=>'WAB'];

$periodoSelecionado = isset($_GET['periodo']) ? intval($_GET['periodo']) : 6;
$baseSelecionada = isset($_GET['base']) ? $_GET['base'] : 'TAP';

if (!in_array($periodoSelecionado, [3,6,12])) $periodoSelecionado = 6;
if (!in_array($baseSelecionada, ['TAP','WAB'])) $baseSelecionada = 'TAP';

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

// Buscar dados para análise de aumento de preços
$condicoes = [];
foreach ($mesesFechados as $m) {
    $condicoes[] = "(YEAR(STR_TO_DATE(DATA, '%d/%m/%Y')) = {$m['ano']} AND MONTH(STR_TO_DATE(DATA, '%d/%m/%Y')) = {$m['mes']})";
}
$wherePeriodo = implode(' OR ', $condicoes);

$tabela = $baseSelecionada === 'TAP' ? 'fComprasTAP' : 'fComprasWAB';
$sql = "
    SELECT 
        grupo, 
        produto, 
        unid, 
        qtd, 
        custo_atual,
        TOTAL, 
        fornecedor,
        STR_TO_DATE(DATA, '%d/%m/%Y') as data_formatada,
        YEAR(STR_TO_DATE(DATA, '%d/%m/%Y')) as ano,
        MONTH(STR_TO_DATE(DATA, '%d/%m/%Y')) as mes
    FROM {$tabela}
    WHERE $wherePeriodo
    ORDER BY produto, data_formatada
";
$res = $conn->query($sql);

// Organizar dados por produto para calcular variações
$produtosDados = [];
while ($row = $res->fetch_assoc()) {
    $produto = $row['produto'];
    $grupo = $row['grupo'];
    $unid = $row['unid'];
    $custo = floatval($row['custo_atual']);
    $qtd = floatval($row['qtd']);
    $total = floatval($row['TOTAL']);
    $fornecedor = $row['fornecedor'] ?? 'Não Informado';
    $dataKey = $row['ano'] . '-' . str_pad($row['mes'], 2, '0', STR_PAD_LEFT);
    
    if (!isset($produtosDados[$produto])) {
        $produtosDados[$produto] = [
            'grupo' => $grupo,
            'unid' => $unid,
            'fornecedor' => $fornecedor,
            'historico' => []
        ];
    }
    
    if (!isset($produtosDados[$produto]['historico'][$dataKey])) {
        $produtosDados[$produto]['historico'][$dataKey] = [
            'custo_total' => 0,
            'qtd_total' => 0,
            'valor_total' => 0,
            'ocorrencias' => 0
        ];
    }
    
    $produtosDados[$produto]['historico'][$dataKey]['custo_total'] += $custo;
    $produtosDados[$produto]['historico'][$dataKey]['qtd_total'] += $qtd;
    $produtosDados[$produto]['historico'][$dataKey]['valor_total'] += $total;
    $produtosDados[$produto]['historico'][$dataKey]['ocorrencias'] += 1;
}

// Calcular aumentos de preço
$aumentos = [];
foreach ($produtosDados as $produto => $dados) {
    $historico = $dados['historico'];
    
    // Ordenar por data
    ksort($historico);
    $datas = array_keys($historico);
    
    if (count($datas) >= 2) {
        // Pegar primeiro e último período
        $primeiroPeriodo = reset($historico);
        $ultimoPeriodo = end($historico);
        
        // Calcular preço médio de cada período
        $precoInicial = $primeiroPeriodo['ocorrencias'] > 0 ? 
            $primeiroPeriodo['custo_total'] / $primeiroPeriodo['ocorrencias'] : 0;
        $precoFinal = $ultimoPeriodo['ocorrencias'] > 0 ? 
            $ultimoPeriodo['custo_total'] / $ultimoPeriodo['ocorrencias'] : 0;
        
        if ($precoInicial > 0 && $precoFinal > $precoInicial) {
            $percentualAumento = (($precoFinal - $precoInicial) / $precoInicial) * 100;
            $valorAumento = $precoFinal - $precoInicial;
            
            // Calcular impacto financeiro (quantidade * aumento de preço)
            $qtdTotal = array_sum(array_column($historico, 'qtd_total'));
            $impactoFinanceiro = $qtdTotal * $valorAumento;
            
            $aumentos[] = [
                'produto' => $produto,
                'grupo' => $dados['grupo'],
                'unid' => $dados['unid'],
                'fornecedor' => $dados['fornecedor'],
                'preco_inicial' => $precoInicial,
                'preco_final' => $precoFinal,
                'valor_aumento' => $valorAumento,
                'percentual_aumento' => $percentualAumento,
                'qtd_total' => $qtdTotal,
                'impacto_financeiro' => $impactoFinanceiro,
                'primeiro_periodo' => reset($datas),
                'ultimo_periodo' => end($datas)
            ];
        }
    }
}

// Ordenar por percentual de aumento (decrescente) e pegar top 15
usort($aumentos, function($a, $b) {
    return $b['percentual_aumento'] <=> $a['percentual_aumento'];
});
$top15Aumentos = array_slice($aumentos, 0, 15);

// Calcular estatísticas gerais
$totalItensAnalisados = count($produtosDados);
$itensComAumento = count($aumentos);
$impactoTotalCalculado = array_sum(array_column($aumentos, 'impacto_financeiro'));
$aumentoMedio = $itensComAumento > 0 ? array_sum(array_column($aumentos, 'percentual_aumento')) / $itensComAumento : 0;

// Análise por fornecedor
$fornecedorAumentos = [];
foreach ($aumentos as $item) {
    $fornecedor = $item['fornecedor'];
    
    if (!isset($fornecedorAumentos[$fornecedor])) {
        $fornecedorAumentos[$fornecedor] = [
            'produtos' => [],
            'total_impacto' => 0,
            'aumentos_somados' => 0,
            'count_produtos' => 0
        ];
    }
    
    $fornecedorAumentos[$fornecedor]['produtos'][] = $item;
    $fornecedorAumentos[$fornecedor]['total_impacto'] += $item['impacto_financeiro'];
    $fornecedorAumentos[$fornecedor]['aumentos_somados'] += $item['percentual_aumento'];
    $fornecedorAumentos[$fornecedor]['count_produtos']++;
}

// Calcular média de aumento por fornecedor e ordenar
foreach ($fornecedorAumentos as $fornecedor => $dados) {
    $fornecedorAumentos[$fornecedor]['aumento_medio'] = $dados['aumentos_somados'] / $dados['count_produtos'];
}

// Ordenar fornecedores por maior impacto financeiro
uasort($fornecedorAumentos, function($a, $b) {
    return $b['total_impacto'] <=> $a['total_impacto'];
});

$top10Fornecedores = array_slice($fornecedorAumentos, 0, 10, true);

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Análise de Aumentos de Preços</title>
    <link href="/assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .ranking-card {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            border: 1px solid rgba(255, 165, 0, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        }
        .ranking-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(255, 165, 0, 0.2);
            border-color: rgba(255, 165, 0, 0.5);
        }
        .ranking-number {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
        .ranking-number.top3 {
            background: linear-gradient(135deg, #ffd700, #ffb347);
            color: #000;
        }
        .ranking-number.top10 {
            background: linear-gradient(135deg, #c0c0c0, #a8a8a8);
        }
        .stats-card {
            background: linear-gradient(135deg, #0f3460, #082347);
            border: 1px solid rgba(102, 163, 255, 0.3);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        .stats-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .aumento-critico { color: #ff4757; }
        .aumento-alto { color: #ffa502; }
        .aumento-moderado { color: #f1c40f; }
        .aumento-baixo { color: #2ed573; }
        
        .impacto-alto { background: rgba(255, 71, 87, 0.1); border-left: 4px solid #ff4757; }
        .impacto-medio { background: rgba(255, 165, 2, 0.1); border-left: 4px solid #ffa502; }
        .impacto-baixo { background: rgba(241, 196, 15, 0.1); border-left: 4px solid #f1c40f; }
        
        .filtros-container {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .explicacao-analise {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }
        
        .fornecedor-card {
            background: linear-gradient(135deg, #2d3748, #1a202c);
            border: 1px solid rgba(102, 163, 255, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        }
        
        .fornecedor-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(102, 163, 255, 0.2);
            border-color: rgba(102, 163, 255, 0.5);
        }
        
        .produto-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .produto-info {
            flex: 1;
        }
        
        .chart-container {
            width: 300px;
            height: 120px;
            position: relative;
        }
        
        @media (max-width: 1024px) {
            .produto-item {
                flex-direction: column;
                align-items: stretch;
            }
            .chart-container {
                width: 100%;
                height: 200px;
            }
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

        <h1 class="text-3xl font-bold text-red-400 mb-6">📈 Análise de Aumentos de Preços - Top 15</h1>

        <!-- Navegação entre análises -->
        <div class="mb-6 flex gap-3 flex-wrap">
            <a href="analisecomprastap.php<?php echo isset($_GET['periodo']) ? '?periodo=' . $_GET['periodo'] : ''; ?>" 
               class="bg-gray-700 text-white px-4 py-2 rounded-lg font-bold hover:bg-gray-600 transition-colors shadow-lg border-2 border-gray-600 hover:border-gray-500">
                📊 Análise TAP
            </a>
            <a href="analisecompraswab.php<?php echo isset($_GET['periodo']) ? '?periodo=' . $_GET['periodo'] : ''; ?>" 
               class="bg-gray-700 text-white px-4 py-2 rounded-lg font-bold hover:bg-gray-600 transition-colors shadow-lg border-2 border-gray-600 hover:border-gray-500">
                📈 Análise WAB
            </a>
            <div class="bg-red-500 text-white px-4 py-2 rounded-lg font-bold shadow-lg">
                🔥 Aumentos de Preços (Atual)
            </div>
        </div>

        <!-- Explicação da Análise -->
        <div class="explicacao-analise">
            <h3 class="text-xl font-bold text-white mb-3">🎯 Como funciona esta análise:</h3>
            <div class="text-sm text-gray-100 space-y-2">
                <p>• <strong>Comparação Temporal:</strong> Analisa o primeiro vs último período do intervalo selecionado</p>
                <p>• <strong>Cálculo de Aumento:</strong> (Preço Final - Preço Inicial) / Preço Inicial × 100</p>
                <p>• <strong>Impacto Financeiro:</strong> Quantidade Total × Valor do Aumento</p>
                <p>• <strong>Ranking:</strong> Os 15 produtos com maior percentual de aumento de preço</p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filtros-container">
            <form method="get" class="flex gap-4 items-end flex-wrap">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        📅 Período de Análise:
                    </label>
                    <select name="periodo" class="bg-gray-800 text-white px-3 py-2 rounded-lg border border-gray-600 focus:ring-2 focus:ring-red-500">
                        <?php foreach ($periodosOpcoes as $val => $label): ?>
                            <option value="<?=$val?>" <?=($periodoSelecionado==$val)?'selected':''?>><?=$label?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        🗃️ Base de Dados:
                    </label>
                    <select name="base" class="bg-gray-800 text-white px-3 py-2 rounded-lg border border-gray-600 focus:ring-2 focus:ring-red-500">
                        <?php foreach ($basesOpcoes as $val => $label): ?>
                            <option value="<?=$val?>" <?=($baseSelecionada==$val)?'selected':''?>><?=$label?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="bg-red-500 text-white px-6 py-2 rounded-lg font-bold hover:bg-red-600 transition-colors shadow-lg">
                    🔍 Analisar
                </button>
            </form>
        </div>

        <!-- Cards de Resumo -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="stats-card">
                <div class="stats-value text-blue-400"><?= number_format($totalItensAnalisados) ?></div>
                <div class="text-sm text-gray-300">Itens Analisados</div>
            </div>
            <div class="stats-card">
                <div class="stats-value text-red-400"><?= number_format($itensComAumento) ?></div>
                <div class="text-sm text-gray-300">Com Aumento de Preço</div>
            </div>
            <div class="stats-card">
                <div class="stats-value text-yellow-400"><?= number_format($aumentoMedio, 1) ?>%</div>
                <div class="text-sm text-gray-300">Aumento Médio</div>
            </div>
            <div class="stats-card">
                <div class="stats-value text-green-400">R$ <?= number_format($impactoTotalCalculado, 2, ',', '.') ?></div>
                <div class="text-sm text-gray-300">Impacto Financeiro</div>
            </div>
        </div>

        <!-- Lista dos Top 15 Aumentos -->
        <?php if (empty($top15Aumentos)): ?>
            <div class="text-center py-12">
                <div class="text-6xl mb-4">😴</div>
                <h3 class="text-xl font-bold text-gray-400 mb-2">Nenhum aumento de preço detectado</h3>
                <p class="text-gray-500">No período selecionado, não foram encontrados produtos com aumento de preço.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php 
                foreach ($top15Aumentos as $index => $item): 
                    $ranking = $index + 1;
                    $rankingClass = $ranking <= 3 ? 'top3' : ($ranking <= 10 ? 'top10' : '');
                    
                    // Classificar aumento
                    $percentual = $item['percentual_aumento'];
                    if ($percentual >= 50) {
                        $aumentoClass = 'aumento-critico';
                        $aumentoLabel = 'CRÍTICO';
                        $aumentoIcon = '🚨';
                    } elseif ($percentual >= 25) {
                        $aumentoClass = 'aumento-alto';
                        $aumentoLabel = 'ALTO';
                        $aumentoIcon = '⚠️';
                    } elseif ($percentual >= 10) {
                        $aumentoClass = 'aumento-moderado';
                        $aumentoLabel = 'MODERADO';
                        $aumentoIcon = '📊';
                    } else {
                        $aumentoClass = 'aumento-baixo';
                        $aumentoLabel = 'BAIXO';
                        $aumentoIcon = '📈';
                    }
                    
                    // Classificar impacto financeiro
                    $impacto = $item['impacto_financeiro'];
                    if ($impacto >= 1000) {
                        $impactoClass = 'impacto-alto';
                    } elseif ($impacto >= 100) {
                        $impactoClass = 'impacto-medio';
                    } else {
                        $impactoClass = 'impacto-baixo';
                    }
                ?>
                <div class="ranking-card <?= $impactoClass ?>">
                    <div class="flex items-start gap-4">
                        <!-- Número do Ranking -->
                        <div class="ranking-number <?= $rankingClass ?>">
                            <?= $ranking ?>
                        </div>
                        
                        <!-- Informações do Produto -->
                        <div class="flex-1">
                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                                <!-- Info Principal -->
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold text-yellow-400 mb-1">
                                        <?= htmlspecialchars($item['produto']) ?>
                                    </h3>
                                    <div class="text-sm text-gray-300 space-y-1">
                                        <p><span class="text-gray-400">Grupo:</span> <?= htmlspecialchars($item['grupo']) ?></p>
                                        <p><span class="text-gray-400">Fornecedor:</span> <?= htmlspecialchars($item['fornecedor']) ?></p>
                                        <p><span class="text-gray-400">Unidade:</span> <?= htmlspecialchars($item['unid']) ?></p>
                                    </div>
                                </div>
                                
                                <!-- Métricas -->
                                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-center">
                                    <!-- Aumento Percentual -->
                                    <div class="bg-gray-800 rounded-lg p-3">
                                        <div class="text-lg font-bold <?= $aumentoClass ?>">
                                            <?= $aumentoIcon ?> <?= number_format($item['percentual_aumento'], 1) ?>%
                                        </div>
                                        <div class="text-xs text-gray-400"><?= $aumentoLabel ?></div>
                                    </div>
                                    
                                    <!-- Preços -->
                                    <div class="bg-gray-800 rounded-lg p-3">
                                        <div class="text-sm text-gray-300">
                                            <div class="text-green-400">R$ <?= number_format($item['preco_inicial'], 2, ',', '.') ?></div>
                                            <div class="text-xs text-gray-500">→</div>
                                            <div class="text-red-400">R$ <?= number_format($item['preco_final'], 2, ',', '.') ?></div>
                                        </div>
                                        <div class="text-xs text-gray-400">Inicial → Final</div>
                                    </div>
                                    
                                    <!-- Valor do Aumento -->
                                    <div class="bg-gray-800 rounded-lg p-3">
                                        <div class="text-lg font-bold text-red-400">
                                            +R$ <?= number_format($item['valor_aumento'], 2, ',', '.') ?>
                                        </div>
                                        <div class="text-xs text-gray-400">Por Unidade</div>
                                    </div>
                                    
                                    <!-- Impacto Financeiro -->
                                    <div class="bg-gray-800 rounded-lg p-3">
                                        <div class="text-lg font-bold text-orange-400">
                                            R$ <?= number_format($item['impacto_financeiro'], 2, ',', '.') ?>
                                        </div>
                                        <div class="text-xs text-gray-400">Impacto Total</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Informações Adicionais -->
                            <div class="mt-4 pt-4 border-t border-gray-700 flex justify-between text-xs text-gray-400">
                                <span>Quantidade Total: <?= number_format($item['qtd_total'], 2, ',', '.') ?> <?= htmlspecialchars($item['unid']) ?></span>
                                <span>Período: <?= $item['primeiro_periodo'] ?> até <?= $item['ultimo_periodo'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Segunda Análise: Top 10 Fornecedores com Maiores Aumentos -->
        <div class="mt-16">
            <h2 class="text-3xl font-bold text-blue-400 mb-6">🏢 Top 10 Fornecedores - Maiores Aumentos de Preços</h2>
            
            <div class="mb-4 p-3 bg-gray-800 rounded-lg text-sm">
                <strong class="text-blue-400">Análise por Fornecedor:</strong> 
                Fornecedores ordenados por maior impacto financeiro total dos aumentos de preços, com histórico detalhado de cada produto.
            </div>

            <?php if (empty($top10Fornecedores)): ?>
                <div class="text-center py-12">
                    <div class="text-6xl mb-4">🏢</div>
                    <h3 class="text-xl font-bold text-gray-400 mb-2">Nenhum fornecedor com aumentos detectado</h3>
                    <p class="text-gray-500">No período selecionado, não foram encontrados fornecedores com aumentos de preços.</p>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php 
                    $rankingFornecedor = 1;
                    foreach ($top10Fornecedores as $fornecedor => $dadosFornecedor): 
                        $produtos = $dadosFornecedor['produtos'];
                        $totalImpacto = $dadosFornecedor['total_impacto'];
                        $aumentoMedio = $dadosFornecedor['aumento_medio'];
                        $countProdutos = $dadosFornecedor['count_produtos'];
                    ?>
                    <div class="fornecedor-card">
                        <div class="mb-4 pb-4 border-b border-gray-600">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold">
                                        <?= $rankingFornecedor ?>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold text-blue-400">
                                            🏢 <?= htmlspecialchars($fornecedor) ?>
                                        </h3>
                                        <p class="text-sm text-gray-400">
                                            <?= $countProdutos ?> produto<?= $countProdutos > 1 ? 's' : '' ?> com aumento
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-bold text-orange-400">
                                        R$ <?= number_format($totalImpacto, 2, ',', '.') ?>
                                    </div>
                                    <div class="text-sm text-gray-400">Impacto Total</div>
                                    <div class="text-sm text-yellow-400">
                                        Média: <?= number_format($aumentoMedio, 1) ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Lista de produtos do fornecedor -->
                        <div class="space-y-3">
                            <?php foreach ($produtos as $produto): ?>
                            <div class="produto-item">
                                <div class="produto-info">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="font-semibold text-yellow-400">
                                            <?= htmlspecialchars($produto['produto']) ?>
                                        </h4>
                                        <div class="text-right">
                                            <?php 
                                            $percentual = $produto['percentual_aumento'];
                                            if ($percentual >= 50) {
                                                $aumentoClass = 'text-red-400';
                                                $aumentoIcon = '🚨';
                                            } elseif ($percentual >= 25) {
                                                $aumentoClass = 'text-orange-400';
                                                $aumentoIcon = '⚠️';
                                            } elseif ($percentual >= 10) {
                                                $aumentoClass = 'text-yellow-400';
                                                $aumentoIcon = '📊';
                                            } else {
                                                $aumentoClass = 'text-green-400';
                                                $aumentoIcon = '📈';
                                            }
                                            ?>
                                            <span class="<?= $aumentoClass ?> font-bold">
                                                <?= $aumentoIcon ?> <?= number_format($produto['percentual_aumento'], 1) ?>%
                                            </span>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                                        <div>
                                            <span class="text-gray-400">Grupo:</span>
                                            <span class="text-gray-300"><?= htmlspecialchars($produto['grupo']) ?></span>
                                        </div>
                                        <div>
                                            <span class="text-gray-400">Preço:</span>
                                            <span class="text-green-400">R$ <?= number_format($produto['preco_inicial'], 2, ',', '.') ?></span>
                                            <span class="text-gray-500">→</span>
                                            <span class="text-red-400">R$ <?= number_format($produto['preco_final'], 2, ',', '.') ?></span>
                                        </div>
                                        <div>
                                            <span class="text-gray-400">Qtd:</span>
                                            <span class="text-gray-300"><?= number_format($produto['qtd_total'], 2, ',', '.') ?> <?= htmlspecialchars($produto['unid']) ?></span>
                                        </div>
                                        <div>
                                            <span class="text-gray-400">Impacto:</span>
                                            <span class="text-orange-400">R$ <?= number_format($produto['impacto_financeiro'], 2, ',', '.') ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Gráfico de linha -->
                                <div class="chart-container">
                                    <canvas id="chart-<?= md5($produto['produto'] . $fornecedor) ?>"></canvas>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php 
                    $rankingFornecedor++;
                    endforeach; 
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Rodapé com informações -->
        <div class="mt-12 p-6 bg-gray-800 rounded-lg border border-gray-700">
            <h4 class="text-lg font-bold text-gray-300 mb-3">📋 Informações sobre a Análise</h4>
            <div class="text-sm text-gray-400 space-y-2">
                <p>• <strong>Base de Dados:</strong> <?= $baseSelecionada ?> (<?= ucfirst(strtolower($baseSelecionada)) ?>)</p>
                <p>• <strong>Período Analisado:</strong> <?= $periodosOpcoes[$periodoSelecionado] ?></p>
                <p>• <strong>Critério de Ordenação:</strong> Maior percentual de aumento de preço</p>
                <p>• <strong>Impacto Financeiro:</strong> Calculado com base na quantidade total consumida no período</p>
                <p>• <strong>Última Atualização:</strong> <?= date('d/m/Y H:i:s') ?></p>
            </div>
        </div>
    </main>
    
    <script>
    // Dados dos produtos para gráficos
    const produtosData = <?= json_encode($produtosDados) ?>;
    const mesesFechados = <?= json_encode($mesesFechados) ?>;
    
    // Configuração comum dos gráficos
    const chartConfig = {
        type: 'line',
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: '#666',
                    borderWidth: 1,
                    callbacks: {
                        label: function(context) {
                            return 'Preço: R$ ' + context.parsed.y.toFixed(2).replace('.', ',');
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#9CA3AF',
                        font: {
                            size: 10
                        }
                    }
                },
                y: {
                    display: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#9CA3AF',
                        font: {
                            size: 10
                        },
                        callback: function(value) {
                            return 'R$ ' + value.toFixed(2);
                        }
                    }
                }
            },
            elements: {
                line: {
                    tension: 0.4,
                    borderWidth: 2
                },
                point: {
                    radius: 4,
                    hoverRadius: 6
                }
            }
        }
    };
    
    // Função para criar gráfico de um produto
    function createProductChart(produto, canvasId) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        const produtoData = produtosData[produto];
        
        if (!produtoData || !produtoData.historico) return;
        
        // Preparar dados do gráfico
        const labels = [];
        const precos = [];
        
        mesesFechados.forEach(mes => {
            const key = mes.key;
            const mesNome = mes.mes < 10 ? '0' + mes.mes : mes.mes;
            const anoAbrev = mes.ano.toString().substr(-2);
            labels.push(mesNome + '/' + anoAbrev);
            
            if (produtoData.historico[key]) {
                const precoMedio = produtoData.historico[key].custo_total / produtoData.historico[key].ocorrencias;
                precos.push(precoMedio);
            } else {
                precos.push(null);
            }
        });
        
        // Determinar cor da linha baseada na tendência
        const primeiroPreco = precos.find(p => p !== null);
        const ultimoPreco = precos.slice().reverse().find(p => p !== null);
        let lineColor = '#10B981'; // Verde por padrão
        
        if (ultimoPreco && primeiroPreco && ultimoPreco > primeiroPreco) {
            const aumento = ((ultimoPreco - primeiroPreco) / primeiroPreco) * 100;
            if (aumento >= 25) {
                lineColor = '#EF4444'; // Vermelho para aumentos altos
            } else if (aumento >= 10) {
                lineColor = '#F59E0B'; // Laranja para aumentos moderados
            } else {
                lineColor = '#F59E0B'; // Amarelo para aumentos baixos
            }
        }
        
        const config = {
            ...chartConfig,
            data: {
                labels: labels,
                datasets: [{
                    data: precos,
                    borderColor: lineColor,
                    backgroundColor: lineColor + '20',
                    fill: false,
                    spanGaps: true
                }]
            }
        };
        
        new Chart(ctx, config);
    }
    
    // Criar gráficos para todos os produtos dos fornecedores
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($top10Fornecedores)): ?>
        <?php foreach ($top10Fornecedores as $fornecedor => $dadosFornecedor): ?>
        <?php foreach ($dadosFornecedor['produtos'] as $produto): ?>
        createProductChart('<?= addslashes($produto['produto']) ?>', 'chart-<?= md5($produto['produto'] . $fornecedor) ?>');
        <?php endforeach; ?>
        <?php endforeach; ?>
        <?php endif; ?>
    });
    </script>
</body>
</html>