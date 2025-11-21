<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurar fuso horário do Brasil
date_default_timezone_set('America/Sao_Paulo');

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';

// Verificar autenticação
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

$usuario = $_SESSION['usuario_nome'] ?? '';

// Capturar filtros
$estabelecimento = $_GET['estabelecimento'] ?? 'bdm';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01'); // Primeiro dia do mês atual
$data_fim = $_GET['data_fim'] ?? date('Y-m-t'); // Último dia do mês atual

// Buscar dados da view de faturamento
$dados_faturamento = null;
try {
    require_once __DIR__ . '/../supabase_connection.php';
    $supabase = new SupabaseConnection();
    
    // Definir tabela baseada no estabelecimento
    $tabela_view = $estabelecimento === 'bdm' ? 'vw_faturamento_diario_bdm' : 'vw_faturamento_diario_cross';
    
    // Buscar dados agregados do período
    $resultado = $supabase->select($tabela_view, [
        'select' => '*',
        'filters' => [
            'data_operacional' => [
                "gte.{$data_inicio}",
                "lte.{$data_fim}"
            ]
        ]
    ]);
    
    // Agregar totais do período
    if ($resultado && count($resultado) > 0) {
        $faturamento_total = 0;
        $soma_tickets = 0;
        $total_clientes = 0;
        $count = 0;
        
        foreach ($resultado as $dia) {
            $faturamento_total += floatval($dia['faturamento_total'] ?? 0);
            $total_clientes += intval($dia['clientes_unicos'] ?? 0);
        }

        // Calcular ticket médio ponderado: total faturamento / total clientes
        $dados_faturamento = [
            'faturamento_total' => $faturamento_total,
            'ticket_medio' => $total_clientes > 0 ? ($faturamento_total / $total_clientes) : 0,
            'clientes_unicos' => $total_clientes
        ];
    }
    
} catch (Exception $e) {
    error_log("Erro ao buscar dados de faturamento: " . $e->getMessage());
}

// Buscar total de atendimentos (todos os registros, incluindo duplicatas)
    $total_atendimentos = 0;
    try {
    $tabela_clientes = $estabelecimento === 'bdm' ? 'vw_clientes_bdm_detalhada' : 'vw_clientes_cross_detalhada';
    
    $atendimentos = $supabase->select($tabela_clientes, [
        'select' => 'nr_cliente',
        'filters' => [
            'data_hora_entrada' => [
                "gte.{$data_inicio} 00:00:00",
                "lte.{$data_fim} 23:59:59"
            ]
        ]
    ]);
    
    if ($atendimentos) {
        $total_atendimentos = count($atendimentos);
    }
    
} catch (Exception $e) {
    error_log("Erro ao buscar total de atendimentos: " . $e->getMessage());
}

    // Recalcular ticket médio do card com base em total de atendimentos (faturamento / total_atendimentos)
    if (isset($dados_faturamento) && isset($dados_faturamento['faturamento_total'])) {
        if ($total_atendimentos > 0) {
            $dados_faturamento['ticket_medio'] = $dados_faturamento['faturamento_total'] / $total_atendimentos;
        }
    }

// Buscar tempo médio de permanência da view de clientes detalhada
$tempo_medio_permanencia = 0;
$dados_entrada_saida = [];
try {
    $tabela_clientes = $estabelecimento === 'bdm' ? 'vw_clientes_bdm_detalhada' : 'vw_clientes_cross_detalhada';
    $clientes_tempo = $supabase->select($tabela_clientes, [
        'select' => 'tempo_permanencia_horas',
        'filters' => [
            'data_hora_entrada' => [
                "gte.{$data_inicio} 00:00:00",
                "lte.{$data_fim} 23:59:59"
            ]
        ]
    ]);
    if ($clientes_tempo && count($clientes_tempo) > 0) {
        $soma_horas = 0;
        $count_tempo = 0;
        foreach ($clientes_tempo as $cliente) {
            $horas = floatval($cliente['tempo_permanencia_horas'] ?? 0);
            if ($horas > 0) {
                $soma_horas += $horas;
                $count_tempo++;
            }
        }
        $tempo_medio_permanencia = $count_tempo > 0 ? ($soma_horas / $count_tempo) : 0;
    }
} catch (Exception $e) {
    error_log("Erro ao buscar tempo médio de permanência: " . $e->getMessage());
}

// Buscar dados para o modal de entradas/saídas e distribuição de sexo por dia
// Também buscar distribuição de sexo por dia para o tooltip do modal de faturamento
// Clientes únicos por dia
$clientes_unicos_por_dia = [];
$distribuicao_sexo_por_dia = [];
// Mapa de atendimentos por dia (contagens de entradas/saídas por data) usado para ticket por dia
$atendimentos_por_dia = [];
try {
    $tabela_view_horas = 'vw_entradas_saidas_por_hora';
    $dados_horas = $supabase->select($tabela_view_horas, [
        'select' => '*',
        'filters' => [
            'data_operacional' => [
                "gte.{$data_inicio}",
                "lte.{$data_fim}"
            ]
        ]
    ]);
        if ($dados_horas && count($dados_horas) > 0) {
        $por_data = [];
        foreach ($dados_horas as $row) {
            $data = $row['data_operacional'];
                // armazenar total de atendimentos por dia se disponível na view
                if (isset($row['total_clientes_dia'])) {
                    $atendimentos_por_dia[$data] = intval($row['total_clientes_dia']);
                }
            if (!isset($por_data[$data])) {
                $por_data[$data] = [
                    'entradas' => [],
                    'saidas' => [],
                    'total_clientes_dia' => $row['total_clientes_dia'] ?? 0
                ];
            }
            // Entradas
            if (($row['qtd_entradas'] ?? 0) > 0) {
                $por_data[$data]['entradas'][] = [
                    'hora' => $row['hora'],
                    'quantidade' => $row['qtd_entradas']
                ];
            }
            // Saídas
            if (($row['qtd_saidas'] ?? 0) > 0) {
                $por_data[$data]['saidas'][] = [
                    'hora' => $row['hora'],
                    'quantidade' => $row['qtd_saidas']
                ];
            }
        }
        // Calcular percentuais para o modal
        foreach ($por_data as $data => $info) {
            $total_entradas = array_sum(array_column($info['entradas'], 'quantidade'));
            $total_saidas = array_sum(array_column($info['saidas'], 'quantidade'));
            $entradas = array_map(function($e) use ($total_entradas) {
                $e['percentual'] = $total_entradas > 0 ? ($e['quantidade'] / $total_entradas) * 100 : 0;
                return $e;
            }, $info['entradas']);
            $saidas = array_map(function($s) use ($total_saidas) {
                $s['percentual'] = $total_saidas > 0 ? ($s['quantidade'] / $total_saidas) * 100 : 0;
                return $s;
            }, $info['saidas']);
            $dados_entrada_saida[] = [
                'data' => $data,
                'entradas' => $entradas,
                'saidas' => $saidas,
                'total_entradas' => $total_entradas,
                'total_saidas' => $total_saidas,
                'total_clientes_dia' => $info['total_clientes_dia']
            ];
        }
    }

    // Buscar distribuição de sexo por dia
    $tabela_clientes = $estabelecimento === 'bdm' ? 'vw_clientes_bdm_detalhada' : 'vw_clientes_cross_detalhada';
    $clientes_por_dia = $supabase->select($tabela_clientes, [
        'select' => 'nr_cliente,sexo,data_hora_entrada',
        'filters' => [
            'data_hora_entrada' => [
                "gte.{$data_inicio} 00:00:00",
                "lte.{$data_fim} 23:59:59"
            ]
        ]
    ]);
    if ($clientes_por_dia && count($clientes_por_dia) > 0) {
        foreach ($clientes_por_dia as $cliente) {
            $data = substr($cliente['data_hora_entrada'], 0, 10);
            $nr_cliente = $cliente['nr_cliente'];
            $sexo = strtoupper(trim($cliente['sexo'] ?? ''));
            if ($sexo === 'M') {
                $sexo_label = 'Masculino';
            } elseif ($sexo === 'F') {
                $sexo_label = 'Feminino';
            } else {
                $sexo_label = 'Não informado';
            }
            // Contagem de clientes únicos por dia
            if (!isset($clientes_unicos_por_dia[$data])) {
                $clientes_unicos_por_dia[$data] = [];
            }
            if (!in_array($nr_cliente, $clientes_unicos_por_dia[$data])) {
                $clientes_unicos_por_dia[$data][] = $nr_cliente;
            }
            // Distribuição de sexo por dia
            if (!isset($distribuicao_sexo_por_dia[$data])) {
                $distribuicao_sexo_por_dia[$data] = [
                    'Masculino' => 0,
                    'Feminino' => 0,
                    'Não informado' => 0,
                    'total' => 0
                ];
            }
            // Só conta cliente único para sexo
            if (!isset($distribuicao_sexo_por_dia[$data]['clientes_unicos'])) {
                $distribuicao_sexo_por_dia[$data]['clientes_unicos'] = [];
            }
            if (!in_array($nr_cliente, $distribuicao_sexo_por_dia[$data]['clientes_unicos'])) {
                $distribuicao_sexo_por_dia[$data]['clientes_unicos'][] = $nr_cliente;
                $distribuicao_sexo_por_dia[$data][$sexo_label]++;
                $distribuicao_sexo_por_dia[$data]['total']++;
            }
        }
    }
} catch (Exception $e) {
    error_log("Erro ao buscar dados do modal de entradas/saídas: " . $e->getMessage());
}

// Buscar dados de faixas etárias
$dados_faixas_etarias = [];
try {
    $tabela_clientes = $estabelecimento === 'bdm' ? 'vw_clientes_bdm_detalhada' : 'vw_clientes_cross_detalhada';
    // Buscar todos os clientes do período
    $clientes = $supabase->select($tabela_clientes, [
        'select' => 'nr_cliente,faixa,data_hora_entrada',
        'filters' => [
            'data_hora_entrada' => [
                "gte.{$data_inicio} 00:00:00",
                "lte.{$data_fim} 23:59:59"
            ]
        ]
    ]);
    if ($clientes && count($clientes) > 0) {
        $faixas_count = [];
        // Contar todos os clientes por faixa (NÃO únicos)
        foreach ($clientes as $cliente) {
            $faixa = trim($cliente['faixa'] ?? 'Não informado');
            if (!isset($faixas_count[$faixa])) {
                $faixas_count[$faixa] = 0;
            }
            $faixas_count[$faixa]++;
        }
        // Calcular total
        $total_clientes_faixa = array_sum($faixas_count);
        // Calcular percentuais e ordenar
        foreach ($faixas_count as $faixa => $quantidade) {
            $percentual = $total_clientes_faixa > 0 ? ($quantidade / $total_clientes_faixa) * 100 : 0;
            $dados_faixas_etarias[] = [
                'faixa' => $faixa,
                'quantidade' => $quantidade,
                'percentual' => $percentual
            ];
        }
        // Ordenar por quantidade (maior para menor)
        usort($dados_faixas_etarias, function($a, $b) {
            return $b['quantidade'] - $a['quantidade'];
        });
    }
} catch (Exception $e) {
    error_log("Erro ao buscar dados de faixas etárias: " . $e->getMessage());
}

// Buscar dados de distribuição por sexo
$dados_sexo = [];
$faixas_por_sexo = [];
try {
    $tabela_clientes = $estabelecimento === 'bdm' ? 'vw_clientes_bdm_detalhada' : 'vw_clientes_cross_detalhada';
    // Buscar todos os clientes do período
    $clientes_sexo = $supabase->select($tabela_clientes, [
        'select' => 'nr_cliente,sexo,faixa,data_hora_entrada',
        'filters' => [
            'data_hora_entrada' => [
                "gte.{$data_inicio} 00:00:00",
                "lte.{$data_fim} 23:59:59"
            ]
        ]
    ]);
    if ($clientes_sexo && count($clientes_sexo) > 0) {
        $sexo_count = [];
        $faixa_por_sexo_count = [];
        // Contar todos os clientes por sexo e faixa (NÃO únicos)
        foreach ($clientes_sexo as $cliente) {
            $sexo = strtoupper(trim($cliente['sexo'] ?? ''));
            $faixa = trim($cliente['faixa'] ?? 'Não informado');
            // Mapear sexo
            if ($sexo === 'M') {
                $sexo_label = 'Masculino';
            } elseif ($sexo === 'F') {
                $sexo_label = 'Feminino';
            } else {
                $sexo_label = 'Não informado';
            }
            if (!isset($sexo_count[$sexo_label])) {
                $sexo_count[$sexo_label] = 0;
                $faixa_por_sexo_count[$sexo_label] = [];
            }
            $sexo_count[$sexo_label]++;
            // Contar faixa dentro do sexo
            if (!isset($faixa_por_sexo_count[$sexo_label][$faixa])) {
                $faixa_por_sexo_count[$sexo_label][$faixa] = 0;
            }
            $faixa_por_sexo_count[$sexo_label][$faixa]++;
        }
        // Calcular total
        $total_clientes_sexo = array_sum($sexo_count);
        // Calcular percentuais e organizar faixas por sexo
        foreach ($sexo_count as $sexo => $quantidade) {
            $percentual = $total_clientes_sexo > 0 ? ($quantidade / $total_clientes_sexo) * 100 : 0;
            $dados_sexo[] = [
                'sexo' => $sexo,
                'quantidade' => $quantidade,
                'percentual' => $percentual
            ];
            // Calcular percentual de cada faixa dentro deste sexo
            if (isset($faixa_por_sexo_count[$sexo])) {
                $faixas_por_sexo[$sexo] = [];
                foreach ($faixa_por_sexo_count[$sexo] as $faixa => $count_faixa) {
                    $perc_faixa = $quantidade > 0 ? ($count_faixa / $quantidade) * 100 : 0;
                    $faixas_por_sexo[$sexo][] = [
                        'faixa' => $faixa,
                        'quantidade' => $count_faixa,
                        'percentual' => $perc_faixa
                    ];
                }
                // Ordenar faixas por quantidade (maior para menor)
                usort($faixas_por_sexo[$sexo], function($a, $b) {
                    return $b['quantidade'] - $a['quantidade'];
                });
            }
        }
        // Ordenar: Masculino, Feminino, Não informado
        usort($dados_sexo, function($a, $b) {
            $ordem = ['Masculino' => 1, 'Feminino' => 2, 'Não informado' => 3];
            return ($ordem[$a['sexo']] ?? 99) - ($ordem[$b['sexo']] ?? 99);
        });
    }
} catch (Exception $e) {
    error_log("Erro ao buscar dados de sexo: " . $e->getMessage());
}

require_once __DIR__ . '/../../../sidebar.php';
?>

<div id="analise-publico-content" class="p-6 ml-4">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl text-yellow-400 font-bold">👥 Análise de Público</h2>
        <div class="flex gap-2">
            <a href="../" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded transition-colors flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar ao Menu
            </a>
            <a href="advanced/analise_rfv.php" class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded transition-colors flex items-center font-semibold">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 012-2h2a2 2 0 012 2v6m-6 0h6"></path>
                </svg>
                Análise RFV
            </a>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="bg-gray-800 rounded-lg p-4 mb-6">
        <h3 class="text-base text-gray-300 mb-3">🔍 Filtros</h3>
        <form method="GET" id="formFiltros" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <!-- Estabelecimento -->
            <div>
                <label class="block text-xs text-gray-400 mb-1">Estabelecimento:</label>
                <select name="estabelecimento" id="selectEstabelecimento" class="w-full px-2 py-1.5 text-sm bg-gray-700 text-gray-300 rounded border border-gray-600 focus:border-yellow-400 focus:outline-none">
                    <option value="bdm" <?= $estabelecimento === 'bdm' ? 'selected' : '' ?>>🍺 Bar do Meio</option>
                    <option value="cross" <?= $estabelecimento === 'cross' ? 'selected' : '' ?>>🎸 Crossroads</option>
                </select>
            </div>
            
            <!-- Data Início -->
            <div>
                <label class="block text-xs text-gray-400 mb-1">Data Início:</label>
                <input type="date" name="data_inicio" id="inputDataInicio" value="<?= htmlspecialchars($data_inicio) ?>" class="w-full px-2 py-1.5 text-sm bg-gray-700 text-gray-300 rounded border border-gray-600 focus:border-yellow-400 focus:outline-none" required>
            </div>
            
            <!-- Data Fim -->
            <div>
                <label class="block text-xs text-gray-400 mb-1">Data Fim:</label>
                <input type="date" name="data_fim" id="inputDataFim" value="<?= htmlspecialchars($data_fim) ?>" class="w-full px-2 py-1.5 text-sm bg-gray-700 text-gray-300 rounded border border-gray-600 focus:border-yellow-400 focus:outline-none" required>
            </div>
            
            <!-- Botões -->
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 px-3 py-1.5 text-sm bg-yellow-600 hover:bg-yellow-700 text-white rounded transition-colors font-medium">
                    Aplicar
                </button>
                <a href="?" class="px-3 py-1.5 text-sm bg-gray-600 hover:bg-gray-700 text-white rounded transition-colors">
                    Limpar
                </a>
            </div>
        </form>
        
        <!-- Info dos filtros ativos -->
        <div class="mt-3 pt-3 border-t border-gray-700">
            <div class="flex items-center gap-3 text-xs text-gray-400">
                <span>📊 Visualizando:</span>
                <span class="text-yellow-400 font-semibold">
                    <?= $estabelecimento === 'bdm' ? 'Bar do Meio' : 'Crossroads' ?>
                </span>
                <span>|</span>
                <span>📅 Período:</span>
                <span class="text-gray-300">
                    <?= date('d/m/Y', strtotime($data_inicio)) ?> até <?= date('d/m/Y', strtotime($data_fim)) ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Cards de Big Numbers -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Faturamento Total -->
        <div class="bg-gray-800 rounded-lg p-4 shadow-lg border border-gray-700 cursor-pointer hover:border-gray-600 transition-colors" onclick="abrirModalDetalhamento()">
            <div class="flex items-center justify-between mb-2">
                <div class="text-gray-400 text-xs font-medium">💰 Faturamento Total</div>
                <div class="bg-gray-700 rounded-full p-1.5">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-white">
                <?php if ($dados_faturamento): ?>
                    R$ <?= number_format($dados_faturamento['faturamento_total'], 2, ',', '.') ?>
                <?php else: ?>
                    <span class="text-xl text-gray-500">Sem dados</span>
                <?php endif; ?>
            </div>
            <div class="text-gray-500 text-xs mt-1 flex items-center justify-between">
                <span>No período selecionado</span>
                <span class="text-gray-600">🔍 Clique para detalhes</span>
            </div>
        </div>
        
        <!-- Ticket Médio -->
        <div class="bg-gray-800 rounded-lg p-4 shadow-lg border border-gray-700">
            <div class="flex items-center justify-between mb-2">
                <div class="text-gray-400 text-xs font-medium">🎯 Ticket Médio</div>
                <div class="bg-gray-700 rounded-full p-1.5">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-white">
                <?php if ($dados_faturamento): ?>
                    R$ <?= number_format($dados_faturamento['ticket_medio'], 2, ',', '.') ?>
                <?php else: ?>
                    <span class="text-xl text-gray-500">Sem dados</span>
                <?php endif; ?>
            </div>
            <div class="text-gray-500 text-xs mt-1">Valor médio por cliente</div>
        </div>
        
        <!-- Clientes Atendidos -->
        <div class="bg-gray-800 rounded-lg p-4 shadow-lg border border-gray-700">
            <div class="flex items-center justify-between mb-2">
                <div class="text-gray-400 text-xs font-medium">👥 Clientes Atendidos</div>
                <div class="bg-gray-700 rounded-full p-1.5">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-white">
                <?php if ($total_atendimentos > 0): ?>
                    <?= number_format($total_atendimentos, 0, ',', '.') ?>
                <?php else: ?>
                    <span class="text-xl text-gray-500">-</span>
                <?php endif; ?>
            </div>
            <div class="text-gray-500 text-xs mt-1">Total de atendimentos no período</div>
        </div>
        
        <!-- Tempo Médio de Permanência -->
        <div class="bg-gray-800 rounded-lg p-4 shadow-lg border border-gray-700 cursor-pointer hover:border-gray-600 transition-colors" onclick="abrirModalTempo()">
            <div class="flex items-center justify-between mb-2">
                <div class="text-gray-400 text-xs font-medium">⏱️ Tempo Médio</div>
                <div class="bg-gray-700 rounded-full p-1.5">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-white">
                <?php if ($tempo_medio_permanencia): ?>
                    <?php 
                    $horas = floor($tempo_medio_permanencia);
                    $minutos = round(($tempo_medio_permanencia - $horas) * 60);
                    ?>
                    <?= $horas ?>h <?= str_pad($minutos, 2, '0', STR_PAD_LEFT) ?>min
                <?php else: ?>
                    <span class="text-xl text-gray-500">-</span>
                <?php endif; ?>
            </div>
            <div class="text-gray-500 text-xs mt-1 flex items-center justify-between">
                <span>Permanência no estabelecimento</span>
                <span class="text-gray-600">🔍 Clique para detalhes</span>
            </div>
        </div>
    </div>
    
    <!-- Grade de Análises -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- Análise de Faixas Etárias -->
        <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-base text-yellow-400 font-bold">📊 Faixas Etárias</h3>
                <span class="text-xs text-gray-400">
                    Total: <?= array_sum(array_column($dados_faixas_etarias, 'quantidade')) ?>
                </span>
            </div>
            
            <?php if (count($dados_faixas_etarias) > 0): ?>
                <div class="space-y-2 max-h-80 overflow-y-auto">
                    <?php foreach ($dados_faixas_etarias as $faixa_data): ?>
                        <?php if ($faixa_data['faixa'] !== 'Não informado'): ?>
                            <div class="bg-gray-700/30 rounded p-2">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-white text-sm font-medium">
                                        <?= htmlspecialchars($faixa_data['faixa']) ?>
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <span class="text-gray-400 text-xs">
                                            <?= number_format($faixa_data['quantidade'], 0, ',', '.') ?>
                                        </span>
                                        <span class="text-yellow-400 font-bold text-sm">
                                            <?= number_format($faixa_data['percentual'], 1, ',', '.') ?>%
                                        </span>
                                    </div>
                                </div>
                                <div class="w-full bg-gray-600 rounded-full h-1.5">
                                    <div class="bg-yellow-400 h-1.5 rounded-full" 
                                         style="width: <?= min($faixa_data['percentual'], 100) ?>%">
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500 text-sm">
                    <p>Sem dados para o período</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Card vazio para próxima análise -->
        <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-base text-yellow-400 font-bold">👤 Distribuição por Gênero</h3>
                <span class="text-xs text-gray-400">
                    Total: <?= array_sum(array_column($dados_sexo, 'quantidade')) ?>
                </span>
            </div>
            
            <?php if (count($dados_sexo) > 0): ?>
                <div class="space-y-2">
                    <?php foreach ($dados_sexo as $sexo_data): ?>
                        <?php if ($sexo_data['sexo'] !== 'Não informado'): ?>
                            <div class="bg-gray-700/30 rounded p-3">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-2xl">
                                            <?= $sexo_data['sexo'] === 'Masculino' ? '👨' : '👩' ?>
                                        </span>
                                        <span class="text-white text-sm font-medium">
                                            <?= htmlspecialchars($sexo_data['sexo']) ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-gray-400 text-xs">
                                            <?= number_format($sexo_data['quantidade'], 0, ',', '.') ?>
                                        </span>
                                        <span class="text-yellow-400 font-bold text-sm">
                                            <?= number_format($sexo_data['percentual'], 1, ',', '.') ?>%
                                        </span>
                                    </div>
                                </div>
                                <div class="w-full bg-gray-600 rounded-full h-1.5 mb-3">
                                    <div class="<?= $sexo_data['sexo'] === 'Masculino' ? 'bg-blue-400' : 'bg-pink-400' ?> h-1.5 rounded-full" 
                                         style="width: <?= min($sexo_data['percentual'], 100) ?>%">
                                    </div>
                                </div>
                                
                                <!-- Faixas dentro do Gênero -->
                                <?php if (isset($faixas_por_sexo[$sexo_data['sexo']]) && count($faixas_por_sexo[$sexo_data['sexo']]) > 0): ?>
                                    <div class="grid grid-cols-2 gap-1.5">
                                        <?php foreach ($faixas_por_sexo[$sexo_data['sexo']] as $faixa_info): ?>
                                            <?php if ($faixa_info['faixa'] !== 'Não informado'): ?>
                                                <div class="bg-gray-600/40 rounded px-2 py-1 flex items-center justify-between">
                                                    <span class="text-gray-300 text-xs"><?= htmlspecialchars($faixa_info['faixa']) ?></span>
                                                    <span class="text-gray-400 text-xs font-medium"><?= number_format($faixa_info['percentual'], 0) ?>%</span>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500 text-sm">
                    <p>Sem dados para o período</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Gráfico Diário: barras (faturamento) + linha (clientes atendidos) -->
    <?php
    // Preparar arrays para o gráfico diário a partir de $resultado (view de faturamento diário)
    $chart_dates = [];
    $chart_fatur = [];
    $chart_clientes = [];
    $chart_ticket = [];
    if (isset($resultado) && is_array($resultado) && count($resultado) > 0) {
        // ordenar por data_operacional asc
        usort($resultado, function($a,$b){
            return strtotime($a['data_operacional']) - strtotime($b['data_operacional']);
        });
        foreach ($resultado as $dia) {
            $data = $dia['data_operacional'];
            $label = date('d/m', strtotime($data));
            $fatur = floatval($dia['faturamento_total'] ?? 0);
            // priorizar contagem de atendimentos por dia (se disponível), senão usar clientes únicos por dia, senão fallback para coluna da view
            if (isset($atendimentos_por_dia[$data])) {
                $clientes_dia = intval($atendimentos_por_dia[$data]);
            } elseif (isset($clientes_unicos_por_dia[$data])) {
                $clientes_dia = count($clientes_unicos_por_dia[$data]);
            } else {
                $clientes_dia = intval($dia['clientes_unicos'] ?? 0);
            }
            $chart_dates[] = $label;
            $chart_fatur[] = $fatur;
            $chart_clientes[] = $clientes_dia;
            // Calcular ticket por dia diretamente a partir de faturamento/clientes
            $chart_ticket[] = $clientes_dia > 0 ? ($fatur / $clientes_dia) : 0;
        }
    }
    ?>
    <div class="bg-gray-800 rounded-lg p-4 mb-6 border border-gray-700">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-base text-yellow-400 font-bold">📈 Faturamento Diário x Clientes Atendidos</h3>
            <span class="text-xs text-gray-400">Período: <?= date('d/m/Y', strtotime($data_inicio)) ?> — <?= date('d/m/Y', strtotime($data_fim)) ?></span>
        </div>
        <div id="kpi-daily-chart" style="width:100%;min-height:340px;"></div>
    </div>

    <!-- Modal de Detalhamento por Dia -->
    <div id="modalDetalhamento" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-gray-800 rounded-lg max-w-4xl w-full max-h-[80vh] overflow-hidden">
            <!-- Header do Modal -->
            <div class="flex items-center justify-between p-4 border-b border-gray-700">
                <h3 class="text-xl text-yellow-400 font-bold">📊 Detalhamento Diário - Faturamento</h3>
                <button onclick="fecharModalDetalhamento()" class="text-gray-400 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Conteúdo do Modal -->
            <div class="overflow-y-auto max-h-[calc(80vh-80px)]">
                <div class="p-4">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-700 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3 text-left text-gray-300 font-semibold">Data</th>
                                    <th class="px-4 py-3 text-left text-gray-300 font-semibold">Dia da Semana</th>
                                    <th class="px-4 py-3 text-right text-gray-300 font-semibold">Faturamento</th>
                                    <th class="px-4 py-3 text-right text-gray-300 font-semibold">Ticket Médio</th>
                                    <th class="px-4 py-3 text-center text-gray-300 font-semibold">Clientes</th>
                                    <th class="px-2 py-3 text-center text-gray-300 font-semibold">% Masc.</th>
                                    <th class="px-2 py-3 text-center text-gray-300 font-semibold">% Fem.</th>
                                </tr>
                            </thead>
                            <tbody id="tabelaDetalhamento" class="divide-y divide-gray-700">
                                <?php if ($resultado && count($resultado) > 0): ?>
                                    <?php foreach ($resultado as $dia): ?>
                                        <tr class="hover:bg-gray-700/50 transition-colors">
                                            <td class="px-4 py-3 text-gray-300">
                                                <?= date('d/m/Y', strtotime($dia['data_operacional'])) ?>
                                            </td>
                                            <td class="px-4 py-3 text-gray-400">
                                                <?= htmlspecialchars($dia['dia_semana'] ?? '-') ?>
                                            </td>
                                            <td class="px-4 py-3 text-right text-white font-semibold">
                                                R$ <?= number_format(floatval($dia['faturamento_total'] ?? 0), 2, ',', '.') ?>
                                            </td>
                                            <?php
                                            $data_dia = $dia['data_operacional'];
                                            // preferir atendimentos_por_dia (total de entradas) para calcular ticket, senão usar clientes únicos
                                            if (isset($atendimentos_por_dia[$data_dia])) {
                                                $clientes_unicos = intval($atendimentos_por_dia[$data_dia]);
                                            } elseif (isset($clientes_unicos_por_dia[$data_dia])) {
                                                $clientes_unicos = count($clientes_unicos_por_dia[$data_dia]);
                                            } else {
                                                $clientes_unicos = intval($dia['clientes_unicos'] ?? 0);
                                            }
                                            $ticket_dia = $clientes_unicos > 0 ? (floatval($dia['faturamento_total'] ?? 0) / $clientes_unicos) : 0;
                                            ?>
                                            <td class="px-4 py-3 text-right text-gray-300">
                                                R$ <?= number_format($ticket_dia, 2, ',', '.') ?>
                                            </td>
                                            <td class="px-4 py-3 text-center text-gray-300">
                                                <?= number_format($clientes_unicos, 0, ',', '.') ?>
                                            </td>
                                            </td>
                                            <?php
                                            // Colunas de % masculino e feminino
                                            $perc_masc = $perc_fem = 0;
                                            if (isset($distribuicao_sexo_por_dia[$data_dia]) && $distribuicao_sexo_por_dia[$data_dia]['total'] > 0) {
                                                $masc = $distribuicao_sexo_por_dia[$data_dia]['Masculino'];
                                                $fem = $distribuicao_sexo_por_dia[$data_dia]['Feminino'];
                                                $total = $distribuicao_sexo_por_dia[$data_dia]['total'];
                                                $perc_masc = ($masc / $total) * 100;
                                                $perc_fem = ($fem / $total) * 100;
                                            }
                                            ?>
                                            <td class="px-2 py-3 text-center text-blue-400 font-semibold">
                                                <?= number_format($perc_masc, 1, ',', '.') ?>%
                                            </td>
                                            <td class="px-2 py-3 text-center text-pink-400 font-semibold">
                                                <?= number_format($perc_fem, 1, ',', '.') ?>%
                                            </td>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                            Nenhum dado encontrado para o período selecionado
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Detalhamento Tempo de Permanência -->
    <div id="modalTempo" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-gray-800 rounded-lg max-w-6xl w-full max-h-[80vh] overflow-hidden">
            <!-- Header do Modal -->
            <div class="flex items-center justify-between p-4 border-b border-gray-700">
                <h3 class="text-xl text-yellow-400 font-bold">⏱️ Distribuição de Entradas e Saídas por Horário</h3>
                <button onclick="fecharModalTempo()" class="text-gray-400 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Conteúdo do Modal -->
            <div class="overflow-y-auto max-h-[calc(80vh-80px)]">
                <div class="p-4">
                    <?php if (count($dados_entrada_saida) > 0): ?>
                        <?php foreach ($dados_entrada_saida as $dia_dados): ?>
                            <div class="mb-6 bg-gray-700/30 rounded-lg p-4">
                                <h4 class="text-lg text-yellow-400 font-bold mb-3">
                                    📅 <?= date('d/m/Y', strtotime($dia_dados['data'])) ?> - 
                                    <?= dia_semana_ptbr($dia_dados['data']) ?>
                                </h4>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Entradas -->
                                    <div class="bg-gray-800/50 rounded-lg p-3">
                                        <h5 class="text-green-400 font-semibold mb-2 flex items-center gap-2">
                                            <span>📍</span> Entradas (<?= $dia_dados['total_entradas'] ?>)
                                        </h5>
                                        <div class="space-y-2 max-h-96 overflow-y-auto">
                                            <?php foreach ($dia_dados['entradas'] as $hora_data): ?>
                                                <div class="flex items-center justify-between text-sm">
                                                    <span class="text-gray-300 font-mono"><?= $hora_data['hora'] ?></span>
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-32 bg-gray-600 rounded-full h-2">
                                                            <div class="bg-green-400 h-2 rounded-full" 
                                                                 style="width: <?= min($hora_data['percentual'], 100) ?>%">
                                                            </div>
                                                        </div>
                                                        <span class="text-gray-400 w-12 text-right"><?= $hora_data['quantidade'] ?></span>
                                                        <span class="text-green-400 font-bold w-12 text-right"><?= number_format($hora_data['percentual'], 1) ?>%</span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Saídas -->
                                    <div class="bg-gray-800/50 rounded-lg p-3">
                                        <h5 class="text-red-400 font-semibold mb-2 flex items-center gap-2">
                                            <span>🚪</span> Saídas (<?= $dia_dados['total_saidas'] ?>)
                                        </h5>
                                        <div class="space-y-2 max-h-96 overflow-y-auto">
                                            <?php foreach ($dia_dados['saidas'] as $hora_data): ?>
                                                <div class="flex items-center justify-between text-sm">
                                                    <span class="text-gray-300 font-mono"><?= $hora_data['hora'] ?></span>
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-32 bg-gray-600 rounded-full h-2">
                                                            <div class="bg-red-400 h-2 rounded-full" 
                                                                 style="width: <?= min($hora_data['percentual'], 100) ?>%">
                                                            </div>
                                                        </div>
                                                        <span class="text-gray-400 w-12 text-right"><?= $hora_data['quantidade'] ?></span>
                                                        <span class="text-red-400 font-bold w-12 text-right"><?= number_format($hora_data['percentual'], 1) ?>%</span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <p>Nenhum dado encontrado para o período selecionado</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Conteúdo será criado aqui -->
</div>

<script>
function abrirModalDetalhamento() {
    document.getElementById('modalDetalhamento').classList.remove('hidden');
}

function fecharModalDetalhamento() {
    document.getElementById('modalDetalhamento').classList.add('hidden');
}

function abrirModalTempo() {
    document.getElementById('modalTempo').classList.remove('hidden');
}

function fecharModalTempo() {
    document.getElementById('modalTempo').classList.add('hidden');
}

// Fechar modais ao clicar fora
document.getElementById('modalDetalhamento')?.addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalDetalhamento();
    }
});

document.getElementById('modalTempo')?.addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalTempo();
    }
});

// Fechar modais com tecla ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModalDetalhamento();
        fecharModalTempo();
    }
});
</script>

<script>
// Garantir que o conteúdo do módulo esteja dentro do container principal
// sem modificar o `sidebar.php`. Isso restaura o comportamento responsivo
// quando a aplicação de produção não coloca automaticamente o conteúdo em `#content`.
document.addEventListener('DOMContentLoaded', function() {
    try {
        var myContent = document.getElementById('analise-publico-content');
        var mainContent = document.getElementById('content');
        if (myContent && mainContent && !mainContent.contains(myContent)) {
            mainContent.appendChild(myContent);
            // pequenos ajustes visuais para casar com outras páginas
            mainContent.style.background = '#0f172a';
            mainContent.style.padding = mainContent.style.padding || '16px';
        }
        // fallback visual leve se #content não existir
        if (myContent && !mainContent) {
            myContent.style.marginLeft = myContent.style.marginLeft || '0px';
        }
    } catch (err) {
        console && console.error && console.error('Erro ao posicionar conteúdo do módulo:', err);
    }
});
</script>
</script>

<!-- Plotly (usado pelo gráfico diário) -->
<script src="https://cdn.plot.ly/plotly-2.24.1.min.js"></script>
<script>
// Dados do PHP para o gráfico diário
var kpi_dates = <?php echo json_encode($chart_dates ?? []); ?>;
var kpi_fatur = <?php echo json_encode($chart_fatur ?? []); ?>;
var kpi_clientes = <?php echo json_encode($chart_clientes ?? []); ?>;
var kpi_ticket = <?php echo json_encode($chart_ticket ?? []); ?>;


if (kpi_dates.length > 0) {
    var faturTrace = {
        x: kpi_dates,
        y: kpi_fatur,
        name: 'Faturamento (R$)',
        type: 'bar',
        marker: { color: 'rgba(251,146,146,0.9)', line: { color: 'rgba(0,0,0,0.05)', width: 1 } },
        opacity: 0.92,
        yaxis: 'y1',
        customdata: kpi_ticket,
        hovertemplate: '<b>%{x}</b><br>Faturamento: R$ %{y:,.2f}<br>Ticket Médio: R$ %{customdata:,.2f}<extra></extra>'
    };

    var clientesTrace = {
        x: kpi_dates,
        y: kpi_clientes,
        name: 'Clientes Atendidos',
        type: 'scatter',
        mode: 'lines+markers',
        marker: { color: 'rgba(96,165,250,0.95)', size: 6 },
        line: { width: 2.5, color: 'rgba(96,165,250,0.9)', shape: 'spline', smoothing: 1.3 },
        fill: 'tozeroy',
        fillcolor: 'rgba(96,165,250,0.07)',
        yaxis: 'y2',
        hovertemplate: '<b>%{x}</b><br>Clientes: %{y}<extra></extra>'
    };


    // Calcular ticks para o eixo Y (faturamento) e formatar em pt-BR
    var maxF = Math.max.apply(null, kpi_fatur.concat([0]));
    var step = Math.ceil(maxF / 4) || 1;
    // arredondar step para ordem de grandeza (100, 500, 1000...) para ticks mais limpos
    function roundStep(s) {
        var pow = Math.pow(10, Math.floor(Math.log10(s)) - 1);
        return Math.ceil(s / pow) * pow;
    }
    step = roundStep(step);
    var ticks = [0, step, step*2, step*3, step*4];
    var nf = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });
    var tickText = ticks.map(function(v){ return nf.format(v); });

    var layout = {
        // aumentar margem superior para afastar a legenda/descrição do gráfico
        // manter margem esquerda moderada; título do eixo será destacado com standoff
        margin: { t: 64, l: 80, r: 56, b: 48 },
        paper_bgcolor: '#0f172a',
        plot_bgcolor: '#0f172a',
        font: { color: '#e6eef8', family: 'Inter, system-ui, Arial' },
        xaxis: { tickangle: -45, tickfont: { size: 11 }, showgrid: false },
        yaxis: {
            title: { text: 'Faturamento (R$)', standoff: 14, font: { size: 12 } },
            zeroline: false,
            automargin: true,
            tickfont: { size: 12 },
            gridcolor: 'rgba(255,255,255,0.03)',
            tickmode: 'array',
            tickvals: ticks,
            ticktext: tickText,
            ticklabelposition: 'outside'
        },
        yaxis2: {
            title: 'Clientes',
            overlaying: 'y',
            side: 'right',
            zeroline: false,
            tickfont: { color: 'rgba(96,165,250,0.9)' }
        },
        // centralizar legenda e colocá-la acima do título do gráfico, afastada do eixo
        legend: { orientation: 'h', x: 0.5, xanchor: 'center', y: 1.18, yanchor: 'bottom', font: { size: 12 }, bgcolor: 'rgba(0,0,0,0)' },
        hovermode: 'x unified',
        hoverlabel: { bgcolor: 'rgba(17,24,39,0.95)', bordercolor: 'rgba(255,255,255,0.04)', font: { color: '#e6eef8' } }
    };

    // Ajustes visuais das barras
    faturTrace.marker.line.width = 0.6;
    faturTrace.marker.line.color = 'rgba(0,0,0,0.06)';
    faturTrace.width = 0.6; // controle de largura relativa
    var config = {responsive: true, displayModeBar: false};
    Plotly.newPlot('kpi-daily-chart', [faturTrace, clientesTrace], layout, config);
} else {
    document.getElementById('kpi-daily-chart').innerHTML = '<div class="text-center text-gray-400">Nenhum dado para o período selecionado</div>';
}
</script>
<style>
body {
    background-color: #0f172a;
}
/* ...existing code... */
</style>

<?php
function dia_semana_ptbr($data) {
    $dias = [
        'Sunday' => 'Domingo',
        'Monday' => 'Segunda',
        'Tuesday' => 'Terça',
        'Wednesday' => 'Quarta',
        'Thursday' => 'Quinta',
        'Friday' => 'Sexta',
        'Saturday' => 'Sábado'
    ];
    $dia_en = date('l', strtotime($data));
    return $dias[$dia_en] ?? $dia_en;
}
?>