<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurar fuso horário do Brasil
date_default_timezone_set('America/Sao_Paulo');

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
// supabase_connection.php está no diretório pai (modules/financeiro_bar)
require_once __DIR__ . '/../supabase_connection.php';

// Verificar autenticação
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

$usuario = $_SESSION['usuario_nome'] ?? '';

// Capturar filtro de período (formato YYYY/MM)
$periodo_selecionado = $_GET['periodo'] ?? '';

try {
    $supabase = new SupabaseConnection();
    $conexao_ok = $supabase->testConnection();
    $erro_conexao = null;
} catch (Exception $e) {
    $conexao_ok = false;
    $erro_conexao = $e->getMessage();
}

$periodos_disponiveis = [];
$dados_receita = [];
$dados_despesa = [];

if ($conexao_ok) {
    try {
        // Primeiro, buscar todos os períodos disponíveis na view
        $todos_dados = $supabase->select('freceitatap', [
            'select' => 'data_mes',
            'order' => 'data_mes.desc'
        ]);
        
        // Array para tradução dos meses para português
        $meses_pt = [
            'January' => 'Janeiro',
            'February' => 'Fevereiro', 
            'March' => 'Março',
            'April' => 'Abril',
            'May' => 'Maio',
            'June' => 'Junho',
            'July' => 'Julho',
            'August' => 'Agosto',
            'September' => 'Setembro',
            'October' => 'Outubro',
            'November' => 'Novembro',
            'December' => 'Dezembro'
        ];
        
        if ($todos_dados) {
            foreach ($todos_dados as $linha) {
                $data_mes = $linha['data_mes'];
                $periodo_formato = date('Y/m', strtotime($data_mes)); // 2025/01
                $mes_ingles = date('F', strtotime($data_mes)); // January
                $mes_portugues = $meses_pt[$mes_ingles] ?? $mes_ingles;
                $periodo_display = $periodo_formato . ' - ' . $mes_portugues; // 2025/01 - Janeiro
                
                if (!isset($periodos_disponiveis[$periodo_formato])) {
                    $periodos_disponiveis[$periodo_formato] = $periodo_display;
                }
            }
        }
        
        // Se nenhum período foi selecionado, selecionar automaticamente o mês atual
        if (empty($periodo_selecionado) && !empty($periodos_disponiveis)) {
            // Tentar usar o mês atual (formato YYYY/MM)
            $mes_atual = date('Y/m');
            if (isset($periodos_disponiveis[$mes_atual])) {
                $periodo_selecionado = $mes_atual;
            } else {
                // Se o mês atual não estiver disponível, usar o mais recente
                $periodo_selecionado = array_keys($periodos_disponiveis)[0];
            }
        }
        
        // Buscar dados se um período foi selecionado (automaticamente ou pelo usuário)
        if ($periodo_selecionado) {
            // Converter período selecionado (2025/01) para data (2025-01-01)
            $partes = explode('/', $periodo_selecionado);
            if (count($partes) == 2) {
                $ano = $partes[0];
                $mes = str_pad($partes[1], 2, '0', STR_PAD_LEFT);
                $data_filtro = $ano . '-' . $mes . '-01';
                
                // Buscar receitas
                $dados_receita = $supabase->select('freceitatap', [
                    'select' => '*',
                    'filters' => [
                        'data_mes' => 'eq.' . $data_filtro
                    ],
                    'order' => 'data_mes.desc'
                ]);
                
                // Buscar despesas (para TRIBUTOS)
                $dados_despesa = $supabase->select('fdespesastap', [
                    'select' => '*',
                    'filters' => [
                        'data_mes' => 'eq.' . $data_filtro
                    ],
                    'order' => 'data_mes.desc'
                ]);
                
                // Buscar detalhes das despesas para drill-down
                $dados_despesa_detalhes = $supabase->select('fdespesastap_detalhes', [
                    'select' => '*',
                    'filters' => [
                        'data_mes' => 'eq.' . $data_filtro
                    ],
                    'order' => 'vlr_total.desc'
                ]);
            }
        }
    } catch (Exception $e) {
        $periodos_disponiveis = [];
        $dados_receita = [];
        $dados_despesa = [];
    }
}

// Função para listar todas as metas disponíveis (para debug)
function listarMetasDisponiveis($periodo = null) {
    global $supabase, $periodo_selecionado;
    
    if (!$supabase) {
        return [];
    }
    
    $periodo_busca = $periodo ?: $periodo_selecionado;
    $filtros = [];
    
    // Converter período YYYY/MM para DATA_META YYYY-MM-01
    if ($periodo_busca && preg_match('/^(\d{4})\/(\d{2})$/', $periodo_busca, $matches)) {
        $data_meta = $matches[1] . '-' . $matches[2] . '-01';
        $filtros['DATA_META'] = "eq.$data_meta";
    }
    
    try {
        $resultado = $supabase->select('fmetastap', [
            'select' => 'CATEGORIA, SUBCATEGORIA, META, PERCENTUAL, DATA_META',
            'filters' => $filtros,
            'order' => 'CATEGORIA.asc,SUBCATEGORIA.asc'
        ]);
        
        return $resultado ?: [];
        
    } catch (Exception $e) {
        error_log("Erro ao listar metas disponíveis: " . $e->getMessage());
        return [];
    }
}

// sidebar.php está na raiz do projeto (c:\xampp\htdocs), precisamos subir três níveis a partir de kpi/
require_once __DIR__ . '/../../../sidebar.php';
?>

<div id="receita-content" class="p-6 ml-4">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl text-yellow-400">Acompanhamento financeiro - Bar da Fabrica</h2>
        <div class="flex items-center gap-2">
            <a href="index.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded transition-colors flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Voltar ao Menu
        </a>
            <!-- Dropdown para alternar entre páginas -->
            <div class="relative">
                <button id="pageMenuBtn" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded transition-colors">
                    Selecionar Bar ▾
                </button>
                <div id="pageMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded shadow-lg z-50">
                    <a href="kpiwab.php" class="block px-4 py-2 text-sm text-gray-800 hover:bg-gray-100">WAB (We Are Bastards)</a>
                </div>
            </div>
            <script>
                document.addEventListener('click', function(e) {
                    const btn = document.getElementById('pageMenuBtn');
                    const menu = document.getElementById('pageMenu');
                    if (!btn || !menu) return;
                    if (btn.contains(e.target)) {
                        menu.classList.toggle('hidden');
                    } else if (!menu.contains(e.target)) {
                        menu.classList.add('hidden');
                    }
                });
            </script>
        </div>
    </div>
    
    <?php if ($periodo_selecionado): ?>

    <?php endif; ?>
    
    <!-- Filtro de Período -->
    <div class="bg-gray-800 rounded-lg p-4 mb-6">
        <h3 class="text-lg text-gray-300 mb-3">Selecionar Período</h3>
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-64">
                <label class="block text-sm text-gray-400 mb-1">Período (Ano/Mês):</label>
                <select id="selectPeriodo" name="periodo" class="w-full px-3 py-2 bg-gray-700 text-gray-300 rounded border border-gray-600 focus:border-yellow-400" required>
                    <option value="">Selecione um período...</option>
                    <?php foreach ($periodos_disponiveis as $valor => $display): ?>
                        <option value="<?= htmlspecialchars($valor) ?>" <?= $periodo_selecionado === $valor ? 'selected' : '' ?>>
                            <?= htmlspecialchars($display) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded transition-colors">
                    Consultar
                </button>
                <a href="?" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded transition-colors">
                    Limpar
                </a>
            </div>
        </form>
        
        <?php if ($periodo_selecionado): ?>
        <div class="mt-3 text-sm text-gray-400">
            📅 Período selecionado: <?= htmlspecialchars($periodos_disponiveis[$periodo_selecionado] ?? $periodo_selecionado) ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($periodo_selecionado): ?>
        <div class="bg-gray-800 rounded-lg p-4">
            <h3 class="text-lg text-gray-300 mb-3">
                DRE Analítico - <?= htmlspecialchars($periodos_disponiveis[$periodo_selecionado] ?? $periodo_selecionado) ?>
            </h3>
            <div id="dre-search-container" class="mb-4"></div>
            <div id="dre-analysis-container">
                <p class="text-gray-400 text-center py-4">Carregando análise DRE...</p>
            </div>
        </div>
                <?php
                // Processar dados para criar estrutura hierárquica
                $total_geral = 0;
                $receitas_operacionais = [];
                $receitas_nao_operacionais = [];
                $tributos = [];
                $custo_variavel = [];
                $custo_fixo = [];
                $despesa_fixa = [];
                $despesa_venda = [];
                $investimento_interno = [];
                $saidas_nao_operacionais = [];
                
                // Função para obter meta da tabela fmetastap
                function obterMeta($categoria, $categoria_pai = null, $periodo = null) {
                    global $supabase, $periodo_selecionado;
                    
                    if (!$supabase) {
                        return 0; // Se conexão não existe, retorna 0
                    }
                    
                    // Usar período passado ou período selecionado globalmente
                    $periodo_busca = $periodo ?: $periodo_selecionado;
                    
                    // Converter período YYYY/MM para DATA_META YYYY-MM-01
                    $data_meta = null;
                    if ($periodo_busca && preg_match('/^(\d{4})\/(\d{2})$/', $periodo_busca, $matches)) {
                        $data_meta = $matches[1] . '-' . $matches[2] . '-01';
                    }
                    
                    $categoria_upper = strtoupper(trim($categoria));
                    
                    try {
                        $filtros = [];
                        
                        // Adicionar filtro de DATA_META se período foi especificado
                        if ($data_meta) {
                            $filtros['DATA_META'] = "eq.$data_meta";
                        }
                        
                        if ($categoria_pai) {
                            // Buscar subcategoria: CATEGORIA = pai E SUBCATEGORIA = filha
                            $categoria_pai_upper = strtoupper(trim($categoria_pai));
                            $filtros['CATEGORIA'] = "eq.$categoria_pai_upper";
                            $filtros['SUBCATEGORIA'] = "eq.$categoria_upper";
                        } else {
                            // Buscar categoria pai: CATEGORIA = categoria E SUBCATEGORIA IS NULL
                            $filtros['CATEGORIA'] = "eq.$categoria_upper";
                            $filtros['SUBCATEGORIA'] = "is.null";
                        }
                        
                        $resultado = $supabase->select('fmetastap', [
                            'select' => 'META, PERCENTUAL',
                            'filters' => $filtros,
                            'order' => 'DATA_CRI.desc',
                            'limit' => 1
                        ]);
                        
                        // Verifica se encontrou resultado válido
                        if (!empty($resultado) && isset($resultado[0]['META']) && is_numeric($resultado[0]['META'])) {
                            return floatval($resultado[0]['META']);
                        }
                        
                        // Meta não encontrada, retorna 0
                        return 0;
                        
                    } catch (Exception $e) {
                        error_log("Erro ao buscar meta para '$categoria' (pai: '$categoria_pai', período: '$periodo_busca'): " . $e->getMessage());
                        return 0; // Em caso de erro, sempre retorna 0
                    }
                }
                
                // Função para obter percentual da meta da tabela fmetastap
                function obterPercentualMeta($categoria, $categoria_pai = null, $periodo = null) {
                    global $supabase, $periodo_selecionado;
                    
                    if (!$supabase) {
                        return 0;
                    }
                    
                    // Usar período passado ou período selecionado globalmente
                    $periodo_busca = $periodo ?: $periodo_selecionado;
                    
                    // Converter período YYYY/MM para DATA_META YYYY-MM-01
                    $data_meta = null;
                    if ($periodo_busca && preg_match('/^(\d{4})\/(\d{2})$/', $periodo_busca, $matches)) {
                        $data_meta = $matches[1] . '-' . $matches[2] . '-01';
                    }
                    
                    $categoria_upper = strtoupper(trim($categoria));
                    
                    try {
                        $filtros = [];
                        if ($data_meta) {
                            $filtros['DATA_META'] = "eq.$data_meta";
                        }
                        
                        if ($categoria_pai) {
                            // Buscar subcategoria: CATEGORIA = pai E SUBCATEGORIA = filha
                            $categoria_pai_upper = strtoupper(trim($categoria_pai));
                            $filtros['CATEGORIA'] = "eq.$categoria_pai_upper";
                            $filtros['SUBCATEGORIA'] = "eq.$categoria_upper";
                        } else {
                            // Buscar categoria pai: CATEGORIA = categoria E SUBCATEGORIA IS NULL
                            $filtros['CATEGORIA'] = "eq.$categoria_upper";
                            $filtros['SUBCATEGORIA'] = "is.null";
                        }
                        
                        $resultado = $supabase->select('fmetastap', [
                            'select' => 'PERCENTUAL',
                            'filters' => $filtros,
                            'order' => 'DATA_CRI.desc',
                            'limit' => 1
                        ]);
                        
                        if (!empty($resultado) && isset($resultado[0]['PERCENTUAL']) && is_numeric($resultado[0]['PERCENTUAL'])) {
                            return floatval($resultado[0]['PERCENTUAL']);
                        }
                        
                        return 0;
                        
                    } catch (Exception $e) {
                        error_log("Erro ao buscar percentual meta para '$categoria': " . $e->getMessage());
                        return 0;
                    }
                }
                
                // Função para calcular percentual da meta
                function calcularPercentualMeta($valor_atual, $meta) {
                    if ($meta <= 0) return 0;
                    return ($valor_atual / $meta) * 100;
                }
                
                // Função para obter cor da barra baseada no percentual
                function obterCorBarra($percentual, $eh_despesa = false) {
                    if ($eh_despesa) {
                        // Para despesas: verde quando menor (melhor)
                        if ($percentual <= 80) return 'green';
                        if ($percentual <= 95) return 'yellow';
                        return 'red';
                    } else {
                        // Para receitas: verde quando maior (melhor)  
                        if ($percentual >= 90) return 'green';
                        if ($percentual >= 70) return 'yellow';
                        return 'red';
                    }
                }
                
                // Categorias não operacionais
                $categorias_nao_operacionais = [
                    'ENTRADA DE REPASSE DE SALARIOS',
                    'ENTRADA DE REPASSE EXTRA DE SALARIOS',
                    'ENTRADA DE REPASSE', 
                    'ENTRADA DE REPASSE OUTROS'
                ];
                
                // Processar RECEITAS
                foreach ($dados_receita as $linha) {
                    $categoria = trim(strtoupper($linha['categoria'] ?? ''));
                    $categoria_pai = trim(strtoupper($linha['categoria_pai'] ?? ''));
                    $valor = floatval($linha['total_receita_mes'] ?? 0);
                    $total_geral += $valor;
                    
                    // Verificar se é categoria não operacional (comparação mais robusta)
                    $eh_nao_operacional = false;
                    foreach ($categorias_nao_operacionais as $cat_nao_op) {
                        if (strpos($categoria, trim(strtoupper($cat_nao_op))) !== false || 
                            trim(strtoupper($cat_nao_op)) === $categoria) {
                            $eh_nao_operacional = true;
                            break;
                        }
                    }
                    
                    if ($eh_nao_operacional) {
                        $receitas_nao_operacionais[] = $linha;
                    } else {
                        $receitas_operacionais[] = $linha;
                    }
                }
                
                // Processar DESPESAS (incluindo TRIBUTOS e CUSTO FIXO)
                foreach ($dados_despesa as $linha) {
                    $categoria_pai = trim(strtoupper($linha['categoria_pai'] ?? ''));
                    
                    // Separar TRIBUTOS das despesas
                    if ($categoria_pai === 'TRIBUTOS') {
                        $tributos[] = $linha;
                    }
                    // Separar CUSTO VARIÁVEL das despesas
                    elseif ($categoria_pai === 'CUSTO VARIÁVEL' || $categoria_pai === 'CUSTO VARIAVEL') {
                        $custo_variavel[] = $linha;
                    }
                    // Separar CUSTO FIXO das despesas
                    elseif ($categoria_pai === 'CUSTO FIXO') {
                        $custo_fixo[] = $linha;
                    }
                    // Separar DESPESA FIXA das despesas
                    elseif ($categoria_pai === 'DESPESA FIXA') {
                        $despesa_fixa[] = $linha;
                    }
                    // Separar DESPESAS DE VENDA das despesas
                    elseif ($categoria_pai === '(-) DESPESAS DE VENDA' || $categoria_pai === 'DESPESAS DE VENDA' || $categoria_pai === 'DESPESA DE VENDA') {
                        $despesa_venda[] = $linha;
                    }
                    // Separar INVESTIMENTO INTERNO das despesas
                    elseif ($categoria_pai === 'INVESTIMENTO INTERNO') {
                        $investimento_interno[] = $linha;
                    }
                    // Separar SAÍDAS NÃO OPERACIONAIS das despesas
                    elseif ($categoria_pai === 'SAÍDAS NÃO OPERACIONAIS' || $categoria_pai === 'SAIDAS NAO OPERACIONAIS') {
                        $saidas_nao_operacionais[] = $linha;
                    }
                }
                
                // Criar arrays para detalhes de cada categoria
                $detalhes_por_categoria = [];
                if ($dados_despesa_detalhes) {
                    foreach ($dados_despesa_detalhes as $detalhe) {
                        $categoria = $detalhe['categoria'];
                        if (!isset($detalhes_por_categoria[$categoria])) {
                            $detalhes_por_categoria[$categoria] = [];
                        }
                        $detalhes_por_categoria[$categoria][] = $detalhe;
                    }
                }
                
                // Ordenar subcategorias por valor (do maior para o menor)
                usort($receitas_operacionais, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                usort($receitas_nao_operacionais, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                usort($tributos, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                usort($custo_variavel, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                usort($custo_fixo, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                usort($despesa_fixa, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                usort($despesa_venda, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                usort($investimento_interno, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                usort($saidas_nao_operacionais, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                
                $total_operacional = array_sum(array_column($receitas_operacionais, 'total_receita_mes'));
                $total_nao_operacional = array_sum(array_column($receitas_nao_operacionais, 'total_receita_mes'));
                // Total considerado como RECEITA BRUTA (exclui RECEITAS NÃO OPERACIONAIS)
                $total_geral_operacional = $total_geral - $total_nao_operacional;
                $total_tributos = array_sum(array_column($tributos, 'total_receita_mes'));
                $total_custo_variavel = array_sum(array_column($custo_variavel, 'total_receita_mes'));
                $total_custo_fixo = array_sum(array_column($custo_fixo, 'total_receita_mes'));
                $total_despesa_fixa = array_sum(array_column($despesa_fixa, 'total_receita_mes'));
                $total_despesa_venda = array_sum(array_column($despesa_venda, 'total_receita_mes'));
                $total_investimento_interno = array_sum(array_column($investimento_interno, 'total_receita_mes'));
                $total_saidas_nao_operacionais = array_sum(array_column($saidas_nao_operacionais, 'total_receita_mes'));
                
                // DEBUG: Mostrar categorias encontradas (remover depois)
                // (Comentado para não poluir a interface)
                // echo "<!-- DEBUG: ";
                // echo "Total registros: " . count($dados_receita) . " | ";
                // echo "Operacionais: " . count($receitas_operacionais) . " | ";
                // echo "Não operacionais: " . count($receitas_nao_operacionais) . " | ";
                // echo "Tributos: " . count($tributos) . " | ";
                // echo "Custo Variável: " . count($custo_variavel) . " | ";
                // echo "Custo Fixo: " . count($custo_fixo) . " | ";
                // echo "Despesa Fixa: " . count($despesa_fixa) . " | ";
                // echo "Despesa Venda: " . count($despesa_venda) . " | ";
                // echo "Investimento Interno: " . count($investimento_interno) . " | ";
                // echo "Saídas Não Operacionais: " . count($saidas_nao_operacionais);
                // echo " -->";
                ?>

                <div id="receita-content-simple" style="padding:12px;">
                    <!-- título removido conforme solicitado -->

                    <?php
                    // --- Preparar dados para gráfico (últimos 12 meses fechados, sem o mês atual) ---
                    $chart_labels = [];
                    $chart_revenue = [];
                    $chart_fixed = [];
                    $chart_pct = [];
                    
                    // Mapeamento de meses abreviados para português
                    $meses_abrev = [
                        'Jan' => 'Jan', 'Feb' => 'Fev', 'Mar' => 'Mar', 'Apr' => 'Abr',
                        'May' => 'Mai', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Ago',
                        'Sep' => 'Set', 'Oct' => 'Out', 'Nov' => 'Nov', 'Dec' => 'Dez'
                    ];

                    if (isset($supabase) && $conexao_ok) {
                        // definir intervalo: último mês fechado
                        $first_day_this_month = date('Y-m-01');
                        $last_closed = date('Y-m-01', strtotime('-1 month', strtotime($first_day_this_month)));
                        // início = 11 meses antes do último fechado -> total 12 meses inclusive
                        $start = date('Y-m-01', strtotime('-11 months', strtotime($last_closed)));

                        // inicializar mapa de meses
                        $months = [];
                        for ($i = 0; $i < 12; $i++) {
                            $m = date('Y-m', strtotime("+{$i} months", strtotime($start)));
                            $mes_en = date('M', strtotime($m . '-01'));
                            $mes_pt = $meses_abrev[$mes_en] ?? $mes_en;
                            $ano = date('Y', strtotime($m . '-01'));
                            $months[$m] = [
                                'label' => $mes_pt . '/' . $ano,
                                'revenue' => 0.0,
                                'fixed' => 0.0,
                                'df' => 0.0, // despesa fixa
                                'cv' => 0.0, // custo variável
                                'tr' => 0.0, // tributos
                                'dv' => 0.0, // despesas de venda
                                'ii' => 0.0  // investimento interno
                            ];
                        }

                        // Buscar receitas e despesas (limite razoável) e agregar por mês no PHP
                        try {
                            $all_receitas = $supabase->select('freceitatap', [
                                'select' => 'data_mes,total_receita_mes',
                                'order' => 'data_mes.desc',
                                'limit' => 1000
                            ]) ?: [];

                            $all_despesas = $supabase->select('fdespesastap', [
                                'select' => 'data_mes,total_receita_mes,categoria_pai',
                                'order' => 'data_mes.desc',
                                'limit' => 1000
                            ]) ?: [];

                            // Agregar receitas
                            foreach ($all_receitas as $r) {
                                if (empty($r['data_mes'])) continue;
                                $mk = date('Y-m', strtotime($r['data_mes']));
                                if (!isset($months[$mk])) continue;
                                $months[$mk]['revenue'] += floatval($r['total_receita_mes'] ?? 0);
                            }

                            // Agregar custo fixo e despesa fixa a partir das despesas
                            foreach ($all_despesas as $d) {
                                if (empty($d['data_mes'])) continue;
                                $mk = date('Y-m', strtotime($d['data_mes']));
                                if (!isset($months[$mk])) continue;
                                $catpai = strtoupper(trim($d['categoria_pai'] ?? ''));
                                if ($catpai === 'CUSTO FIXO') {
                                    $months[$mk]['fixed'] += floatval($d['total_receita_mes'] ?? 0);
                                }
                                if ($catpai === 'DESPESA FIXA') {
                                    $months[$mk]['df'] += floatval($d['total_receita_mes'] ?? 0);
                                }
                                if ($catpai === 'CUSTO VARIAVEL' || $catpai === 'CUSTO VARIÁVEL') {
                                    $months[$mk]['cv'] += floatval($d['total_receita_mes'] ?? 0);
                                }
                                if ($catpai === 'TRIBUTOS') {
                                    $months[$mk]['tr'] += floatval($d['total_receita_mes'] ?? 0);
                                }
                                if ($catpai === 'DESPESAS DE VENDA' || $catpai === 'DESPESA DE VENDA' || $catpai === '(-) DESPESAS DE VENDA') {
                                    $months[$mk]['dv'] += floatval($d['total_receita_mes'] ?? 0);
                                }
                                if ($catpai === 'INVESTIMENTO INTERNO') {
                                    $months[$mk]['ii'] += floatval($d['total_receita_mes'] ?? 0);
                                }
                            }

                        } catch (Exception $e) {
                            // Se falhar, deixar arrays vazios — o gráfico exibirá zeros
                        }

                        // Construir arrays finais ordenados cronologicamente (CUSTO FIXO)
                        foreach ($months as $m => $vals) {
                            $chart_labels[] = $vals['label'];
                            $chart_revenue[] = round($vals['revenue'], 2);
                            $chart_fixed[] = round($vals['fixed'], 2);
                            if ($vals['revenue'] > 0) {
                                $chart_pct[] = round(($vals['fixed'] / $vals['revenue']) * 100, 2);
                            } else {
                                $chart_pct[] = null; // sem receita
                            }
                        }

                        // Construir arrays finais ordenados cronologicamente (DESPESA FIXA)
                        $chart_df_labels = [];
                        $chart_df_revenue = [];
                        $chart_df_df = [];
                        $chart_df_pct = [];
                        foreach ($months as $m => $vals) {
                            $chart_df_labels[] = $vals['label'];
                            $chart_df_revenue[] = round($vals['revenue'], 2);
                            $chart_df_df[] = round($vals['df'], 2);
                            if ($vals['revenue'] > 0) {
                                $chart_df_pct[] = round(($vals['df'] / $vals['revenue']) * 100, 2);
                            } else {
                                $chart_df_pct[] = null;
                            }
                        }

                        // Construir arrays finais ordenados cronologicamente (CUSTO VARIÁVEL)
                        $chart_cv_labels = [];
                        $chart_cv_revenue = [];
                        $chart_cv_cv = [];
                        $chart_cv_pct = [];
                        foreach ($months as $m => $vals) {
                            $chart_cv_labels[] = $vals['label'];
                            $chart_cv_revenue[] = round($vals['revenue'], 2);
                            $chart_cv_cv[] = round($vals['cv'], 2);
                            if ($vals['revenue'] > 0) {
                                $chart_cv_pct[] = round(($vals['cv'] / $vals['revenue']) * 100, 2);
                            } else {
                                $chart_cv_pct[] = null;
                            }
                        }

                        // Construir arrays finais ordenados cronologicamente (TRIBUTOS)
                        $chart_tr_labels = [];
                        $chart_tr_revenue = [];
                        $chart_tr_tr = [];
                        $chart_tr_pct = [];
                        foreach ($months as $m => $vals) {
                            $chart_tr_labels[] = $vals['label'];
                            $chart_tr_revenue[] = round($vals['revenue'], 2);
                            // tributos podem vir como parte das despesas quando categoria_pai = 'TRIBUTOS'
                            $chart_tr_tr[] = round($vals['tr'] ?? 0, 2);
                            if ($vals['revenue'] > 0) {
                                $chart_tr_pct[] = round((($vals['tr'] ?? 0) / $vals['revenue']) * 100, 2);
                            } else {
                                $chart_tr_pct[] = null;
                            }
                        }
                        
                        // Construir arrays finais ordenados cronologicamente (DESPESAS DE VENDA)
                        $chart_dv_labels = [];
                        $chart_dv_revenue = [];
                        $chart_dv_dv = [];
                        $chart_dv_pct = [];
                        foreach ($months as $m => $vals) {
                            $chart_dv_labels[] = $vals['label'];
                            $chart_dv_revenue[] = round($vals['revenue'], 2);
                            $chart_dv_dv[] = round($vals['dv'] ?? 0, 2);
                            if ($vals['revenue'] > 0) {
                                $chart_dv_pct[] = round((($vals['dv'] ?? 0) / $vals['revenue']) * 100, 2);
                            } else {
                                $chart_dv_pct[] = null;
                            }
                        }
                        
                        // Construir arrays finais ordenados cronologicamente (INVESTIMENTO INTERNO)
                        $chart_ii_labels = [];
                        $chart_ii_revenue = [];
                        $chart_ii_ii = [];
                        $chart_ii_pct = [];
                        foreach ($months as $m => $vals) {
                            $chart_ii_labels[] = $vals['label'];
                            $chart_ii_revenue[] = round($vals['revenue'], 2);
                            $chart_ii_ii[] = round($vals['ii'] ?? 0, 2);
                            if ($vals['revenue'] > 0) {
                                $chart_ii_pct[] = round((($vals['ii'] ?? 0) / $vals['revenue']) * 100, 2);
                            } else {
                                $chart_ii_pct[] = null;
                            }
                        }
                    }

                    // Filtrar meses sem valores (quando não houver receita E nem custo fixo)
                    $f_labels = [];
                    $f_revenue = [];
                    $f_fixed = [];
                    $f_pct = [];
                    for ($i = 0; $i < count($chart_labels); $i++) {
                        $rev = $chart_revenue[$i] ?? 0;
                        $fix = $chart_fixed[$i] ?? 0;
                        // se ambos zero, ignorar o mês
                        if (abs($rev) < 0.0001 && abs($fix) < 0.0001) {
                            continue;
                        }
                        $f_labels[] = $chart_labels[$i];
                        $f_revenue[] = $rev;
                        $f_fixed[] = $fix;
                        $f_pct[] = $chart_pct[$i] ?? null;
                    }

                    $chart_payload = [
                        'labels' => $f_labels,
                        'revenue' => $f_revenue,
                        'fixed' => $f_fixed,
                        'pct' => $f_pct
                    ];
                    // calcular máximo percentual para ajustar escala do eixo (garantir >=100) para CUSTO FIXO
                    $pct_max = 100;
                    $numeric_pcts = array_filter($f_pct, function($v){ return is_numeric($v) && $v !== null; });
                    if (!empty($numeric_pcts)) {
                        $m = max($numeric_pcts);
                        // margem de 20%
                        $pct_max = max(100, ceil($m * 1.2));
                    }
                    // calcular máximo absoluto (receita/custo) para ajustar escala do eixo esquerdo (CUSTO FIXO)
                    $abs_max = 0;
                    $numeric_revs = array_filter($f_revenue, function($v){ return is_numeric($v); });
                    $numeric_fixs = array_filter($f_fixed, function($v){ return is_numeric($v); });
                    if (!empty($numeric_revs)) $abs_max = max($abs_max, max($numeric_revs));
                    if (!empty($numeric_fixs)) $abs_max = max($abs_max, max($numeric_fixs));
                    if ($abs_max <= 0) $abs_max = 1000; // fallback
                    $abs_max = ceil($abs_max * 1.2);

                    // --- Filtrar meses sem dados para DESPESA FIXA (meses onde receita E despesa fixa são zero) ---
                    $f_df_labels = [];
                    $f_df_revenue = [];
                    $f_df_df = [];
                    $f_df_pct = [];
                    for ($i = 0; $i < count($chart_df_labels); $i++) {
                        $rev = $chart_df_revenue[$i] ?? 0;
                        $dfv = $chart_df_df[$i] ?? 0;
                        if (abs($rev) < 0.0001 && abs($dfv) < 0.0001) continue;
                        $f_df_labels[] = $chart_df_labels[$i];
                        $f_df_revenue[] = $rev;
                        $f_df_df[] = $dfv;
                        $f_df_pct[] = $chart_df_pct[$i] ?? null;
                    }

                    $chart_payload_df = [
                        'labels' => $f_df_labels,
                        'revenue' => $f_df_revenue,
                        'df' => $f_df_df,
                        'pct' => $f_df_pct
                    ];

                    // calcular máximos para DESPESA FIXA chart
                    $pct_max_df = 100;
                    $numeric_pcts_df = array_filter($f_df_pct, function($v){ return is_numeric($v) && $v !== null; });
                    if (!empty($numeric_pcts_df)) {
                        $m = max($numeric_pcts_df);
                        $pct_max_df = max(100, ceil($m * 1.2));
                    }
                    $abs_max_df = 0;
                    $numeric_revs_df = array_filter($f_df_revenue, function($v){ return is_numeric($v); });
                    $numeric_dfs = array_filter($f_df_df, function($v){ return is_numeric($v); });
                    if (!empty($numeric_revs_df)) $abs_max_df = max($abs_max_df, max($numeric_revs_df));
                    if (!empty($numeric_dfs)) $abs_max_df = max($abs_max_df, max($numeric_dfs));
                    if ($abs_max_df <= 0) $abs_max_df = 1000;
                    $abs_max_df = ceil($abs_max_df * 1.2);

                    // --- Filtrar meses sem dados para CUSTO VARIÁVEL (meses onde receita E custo variável são zero) ---
                    $f_cv_labels = [];
                    $f_cv_revenue = [];
                    $f_cv_cv = [];
                    $f_cv_pct = [];
                    for ($i = 0; $i < count($chart_cv_labels); $i++) {
                        $rev = $chart_cv_revenue[$i] ?? 0;
                        $cvv = $chart_cv_cv[$i] ?? 0;
                        if (abs($rev) < 0.0001 && abs($cvv) < 0.0001) continue;
                        $f_cv_labels[] = $chart_cv_labels[$i];
                        $f_cv_revenue[] = $rev;
                        $f_cv_cv[] = $cvv;
                        $f_cv_pct[] = $chart_cv_pct[$i] ?? null;
                    }

                    $chart_payload_cv = [
                        'labels' => $f_cv_labels,
                        'revenue' => $f_cv_revenue,
                        'cv' => $f_cv_cv,
                        'pct' => $f_cv_pct
                    ];

                    // calcular máximos para CUSTO VARIÁVEL chart
                    $pct_max_cv = 100;
                    $numeric_pcts_cv = array_filter($f_cv_pct, function($v){ return is_numeric($v) && $v !== null; });
                    if (!empty($numeric_pcts_cv)) {
                        $m = max($numeric_pcts_cv);
                        $pct_max_cv = max(100, ceil($m * 1.2));
                    }
                    $abs_max_cv = 0;
                    $numeric_revs_cv = array_filter($f_cv_revenue, function($v){ return is_numeric($v); });
                    $numeric_cvs = array_filter($f_cv_cv, function($v){ return is_numeric($v); });
                    if (!empty($numeric_revs_cv)) $abs_max_cv = max($abs_max_cv, max($numeric_revs_cv));
                    if (!empty($numeric_cvs)) $abs_max_cv = max($abs_max_cv, max($numeric_cvs));
                    if ($abs_max_cv <= 0) $abs_max_cv = 1000;
                    $abs_max_cv = ceil($abs_max_cv * 1.2);

                    // --- Filtrar meses sem dados para TRIBUTOS (meses onde receita E tributos são zero) ---
                    $f_tr_labels = [];
                    $f_tr_revenue = [];
                    $f_tr_tr = [];
                    $f_tr_pct = [];
                    for ($i = 0; $i < count($chart_tr_labels); $i++) {
                        $rev = $chart_tr_revenue[$i] ?? 0;
                        $trv = $chart_tr_tr[$i] ?? 0;
                        if (abs($rev) < 0.0001 && abs($trv) < 0.0001) continue;
                        $f_tr_labels[] = $chart_tr_labels[$i];
                        $f_tr_revenue[] = $rev;
                        $f_tr_tr[] = $trv;
                        $f_tr_pct[] = $chart_tr_pct[$i] ?? null;
                    }

                    $chart_payload_tr = [
                        'labels' => $f_tr_labels,
                        'revenue' => $f_tr_revenue,
                        'tr' => $f_tr_tr,
                        'pct' => $f_tr_pct
                    ];

                    // calcular máximos para TRIBUTOS chart
                    $pct_max_tr = 100;
                    $numeric_pcts_tr = array_filter($f_tr_pct, function($v){ return is_numeric($v) && $v !== null; });
                    if (!empty($numeric_pcts_tr)) {
                        $m = max($numeric_pcts_tr);
                        $pct_max_tr = max(100, ceil($m * 1.2));
                    }
                    $abs_max_tr = 0;
                    $numeric_revs_tr = array_filter($f_tr_revenue, function($v){ return is_numeric($v); });
                    $numeric_trs = array_filter($f_tr_tr, function($v){ return is_numeric($v); });
                    if (!empty($numeric_revs_tr)) $abs_max_tr = max($abs_max_tr, max($numeric_revs_tr));
                    if (!empty($numeric_trs)) $abs_max_tr = max($abs_max_tr, max($numeric_trs));
                    if ($abs_max_tr <= 0) $abs_max_tr = 1000;
                    $abs_max_tr = ceil($abs_max_tr * 1.2);
                    
                    // --- Filtrar meses sem dados para DESPESAS DE VENDA (meses onde receita E despesas de venda são zero) ---
                    $f_dv_labels = [];
                    $f_dv_revenue = [];
                    $f_dv_dv = [];
                    $f_dv_pct = [];
                    for ($i = 0; $i < count($chart_dv_labels); $i++) {
                        $rev = $chart_dv_revenue[$i] ?? 0;
                        $dvv = $chart_dv_dv[$i] ?? 0;
                        if (abs($rev) < 0.0001 && abs($dvv) < 0.0001) continue;
                        $f_dv_labels[] = $chart_dv_labels[$i];
                        $f_dv_revenue[] = $rev;
                        $f_dv_dv[] = $dvv;
                        $f_dv_pct[] = $chart_dv_pct[$i] ?? null;
                    }

                    $chart_payload_dv = [
                        'labels' => $f_dv_labels,
                        'revenue' => $f_dv_revenue,
                        'dv' => $f_dv_dv,
                        'pct' => $f_dv_pct
                    ];

                    // calcular máximos para DESPESAS DE VENDA chart
                    $pct_max_dv = 100;
                    $numeric_pcts_dv = array_filter($f_dv_pct, function($v){ return is_numeric($v) && $v !== null; });
                    if (!empty($numeric_pcts_dv)) {
                        $m = max($numeric_pcts_dv);
                        $pct_max_dv = max(100, ceil($m * 1.2));
                    }
                    $abs_max_dv = 0;
                    $numeric_revs_dv = array_filter($f_dv_revenue, function($v){ return is_numeric($v); });
                    $numeric_dvs = array_filter($f_dv_dv, function($v){ return is_numeric($v); });
                    if (!empty($numeric_revs_dv)) $abs_max_dv = max($abs_max_dv, max($numeric_revs_dv));
                    if (!empty($numeric_dvs)) $abs_max_dv = max($abs_max_dv, max($numeric_dvs));
                    if ($abs_max_dv <= 0) $abs_max_dv = 1000;
                    $abs_max_dv = ceil($abs_max_dv * 1.2);
                    
                    // --- Filtrar meses sem dados para INVESTIMENTO INTERNO (meses onde receita E investimento interno são zero) ---
                    $f_ii_labels = [];
                    $f_ii_revenue = [];
                    $f_ii_ii = [];
                    $f_ii_pct = [];
                    for ($i = 0; $i < count($chart_ii_labels); $i++) {
                        $rev = $chart_ii_revenue[$i] ?? 0;
                        $iiv = $chart_ii_ii[$i] ?? 0;
                        if (abs($rev) < 0.0001 && abs($iiv) < 0.0001) continue;
                        $f_ii_labels[] = $chart_ii_labels[$i];
                        $f_ii_revenue[] = $rev;
                        $f_ii_ii[] = $iiv;
                        $f_ii_pct[] = $chart_ii_pct[$i] ?? null;
                    }

                    $chart_payload_ii = [
                        'labels' => $f_ii_labels,
                        'revenue' => $f_ii_revenue,
                        'ii' => $f_ii_ii,
                        'pct' => $f_ii_pct
                    ];

                    // calcular máximos para INVESTIMENTO INTERNO chart
                    $pct_max_ii = 100;
                    $numeric_pcts_ii = array_filter($f_ii_pct, function($v){ return is_numeric($v) && $v !== null; });
                    if (!empty($numeric_pcts_ii)) {
                        $m = max($numeric_pcts_ii);
                        $pct_max_ii = max(100, ceil($m * 1.2));
                    }
                    $abs_max_ii = 0;
                    $numeric_revs_ii = array_filter($f_ii_revenue, function($v){ return is_numeric($v); });
                    $numeric_iis = array_filter($f_ii_ii, function($v){ return is_numeric($v); });
                    if (!empty($numeric_revs_ii)) $abs_max_ii = max($abs_max_ii, max($numeric_revs_ii));
                    if (!empty($numeric_iis)) $abs_max_ii = max($abs_max_ii, max($numeric_iis));
                    if ($abs_max_ii <= 0) $abs_max_ii = 1000;
                    $abs_max_ii = ceil($abs_max_ii * 1.2);
                    ?>

                    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;">
                        <!-- card para CUSTO FIXO -->
                        <div class="kpi-chart-card" style="width:calc(50% - 12px);max-width:640px;margin:12px 0;background:#111827;border-radius:10px;padding:12px;box-sizing:border-box;overflow:hidden;position:relative;">
                            <h3 style="color:#fbbf24;font-size:16px;font-weight:600;margin:0 0 12px 0;padding:0 8px;text-align:center;display:flex;align-items:center;justify-content:center;gap:12px;">
                                <span>CUSTO FIXO</span>
                                <button onclick="openDetailWithPeriod('CUSTO FIXO')" class="detail-btn">🔍 Detalhar</button>
                            </h3>
                            <div id="chart-custo-fixo" class="kpi-chart-inner" style="width:100%;background:#ffffff;border-radius:6px;padding:6px;padding-right:44px;box-sizing:border-box;height:320px;position:relative;overflow:visible;"></div>
                        </div>
                        <!-- card para DESPESA FIXA (comparativo lado a lado) -->
                        <div class="kpi-chart-card" style="width:calc(50% - 12px);max-width:640px;margin:12px 0;background:#111827;border-radius:10px;padding:12px;box-sizing:border-box;overflow:hidden;position:relative;">
                            <h3 style="color:#fbbf24;font-size:16px;font-weight:600;margin:0 0 12px 0;padding:0 8px;text-align:center;display:flex;align-items:center;justify-content:center;gap:12px;">
                                <span>DESPESA FIXA</span>
                                <button onclick="openDetailWithPeriod('DESPESA FIXA')" class="detail-btn">🔍 Detalhar</button>
                            </h3>
                            <div id="chart-despesa-fixa" class="kpi-chart-inner" style="width:100%;background:#ffffff;border-radius:6px;padding:6px;padding-right:44px;box-sizing:border-box;height:320px;position:relative;overflow:visible;"></div>
                        </div>
                    </div>
                    <!-- segunda linha: CUSTO VARIÁVEL e TRIBUTOS lado a lado (2 por linha) -->
                    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;">
                        <div class="kpi-chart-card" style="width:calc(50% - 12px);max-width:640px;margin:12px 0;background:#111827;border-radius:10px;padding:12px;box-sizing:border-box;overflow:hidden;position:relative;">
                            <h3 style="color:#fbbf24;font-size:16px;font-weight:600;margin:0 0 12px 0;padding:0 8px;text-align:center;display:flex;align-items:center;justify-content:center;gap:12px;">
                                <span>CUSTO VARIÁVEL</span>
                                <button onclick="openDetailWithPeriod('CUSTO VARIAVEL')" class="detail-btn">🔍 Detalhar</button>
                            </h3>
                            <div id="chart-custo-variavel" class="kpi-chart-inner" style="width:100%;background:#ffffff;border-radius:6px;padding:6px;padding-right:44px;box-sizing:border-box;height:320px;position:relative;overflow:visible;"></div>
                        </div>
                        <div class="kpi-chart-card" style="width:calc(50% - 12px);max-width:640px;margin:12px 0;background:#111827;border-radius:10px;padding:12px;box-sizing:border-box;overflow:hidden;position:relative;">
                            <h3 style="color:#fbbf24;font-size:16px;font-weight:600;margin:0 0 12px 0;padding:0 8px;text-align:center;display:flex;align-items:center;justify-content:center;gap:12px;">
                                <span>TRIBUTOS</span>
                                <button onclick="openDetailWithPeriod('TRIBUTOS')" class="detail-btn">🔍 Detalhar</button>
                            </h3>
                            <div id="chart-tributos" class="kpi-chart-inner" style="width:100%;background:#ffffff;border-radius:6px;padding:6px;padding-right:44px;box-sizing:border-box;height:320px;position:relative;overflow:visible;"></div>
                        </div>
                    </div>
                    <!-- terceira linha: DESPESAS DE VENDA -->
                    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;">
                        <div class="kpi-chart-card" style="width:calc(50% - 12px);max-width:640px;margin:12px 0;background:#111827;border-radius:10px;padding:12px;box-sizing:border-box;overflow:hidden;position:relative;">
                            <h3 style="color:#fbbf24;font-size:16px;font-weight:600;margin:0 0 12px 0;padding:0 8px;text-align:center;display:flex;align-items:center;justify-content:center;gap:12px;">
                                <span>DESPESAS DE VENDA</span>
                                <button onclick="openDetailWithPeriod('DESPESAS DE VENDA')" class="detail-btn">🔍 Detalhar</button>
                            </h3>
                            <div id="chart-despesas-venda" class="kpi-chart-inner" style="width:100%;background:#ffffff;border-radius:6px;padding:6px;padding-right:44px;box-sizing:border-box;height:320px;position:relative;overflow:visible;"></div>
                        </div>
                        <div class="kpi-chart-card" style="width:calc(50% - 12px);max-width:640px;margin:12px 0;background:#111827;border-radius:10px;padding:12px;box-sizing:border-box;overflow:hidden;position:relative;">
                            <h3 style="color:#fbbf24;font-size:16px;font-weight:600;margin:0 0 12px 0;padding:0 8px;text-align:center;display:flex;align-items:center;justify-content:center;gap:12px;">
                                <span>INVESTIMENTO INTERNO</span>
                                <button onclick="openDetailWithPeriod('INVESTIMENTO INTERNO')" class="detail-btn">🔍 Detalhar</button>
                            </h3>
                            <div id="chart-investimento-interno" class="kpi-chart-inner" style="width:100%;background:#ffffff;border-radius:6px;padding:6px;padding-right:44px;box-sizing:border-box;height:320px;position:relative;overflow:visible;"></div>
                        </div>
                    </div>

                    <?php
                    // --- TABELA DE ANÁLISE DOS ÚLTIMOS 6 MESES ---
                    // Preparar dados para análise mensal de cada categoria
                    $analise_mensal = [];
                    
                    // Buscar dados dos últimos 6 meses (excluindo mês atual)
                    if (isset($supabase) && $conexao_ok) {
                        $first_day_this_month = date('Y-m-01');
                        $last_closed = date('Y-m-01', strtotime('-1 month', strtotime($first_day_this_month)));
                        $start = date('Y-m-01', strtotime('-5 months', strtotime($last_closed)));
                        
                        // Inicializar estrutura
                        $meses_analise = [];
                        for ($i = 0; $i < 6; $i++) {
                            $m = date('Y-m', strtotime("+{$i} months", strtotime($start)));
                            $mes_en = date('M', strtotime($m . '-01'));
                            $mes_pt = $meses_abrev[$mes_en] ?? $mes_en;
                            $ano = date('Y', strtotime($m . '-01'));
                            $meses_analise[$m] = [
                                'label' => $mes_pt . '/' . $ano,
                                'revenue' => 0,
                                'custo_fixo' => 0,
                                'despesa_fixa' => 0,
                                'custo_variavel' => 0,
                                'tributos' => 0,
                                'despesas_venda' => 0,
                                'investimento_interno' => 0
                            ];
                        }
                        
                        // Buscar receitas
                        try {
                            $all_receitas = $supabase->select('freceitatap', [
                                'select' => 'data_mes,total_receita_mes',
                                'order' => 'data_mes.desc',
                                'limit' => 1000
                            ]) ?: [];
                            
                            foreach ($all_receitas as $r) {
                                if (empty($r['data_mes'])) continue;
                                $mk = date('Y-m', strtotime($r['data_mes']));
                                if (!isset($meses_analise[$mk])) continue;
                                $meses_analise[$mk]['revenue'] += floatval($r['total_receita_mes'] ?? 0);
                            }
                            
                            // Buscar despesas
                            $all_despesas = $supabase->select('fdespesastap', [
                                'select' => 'data_mes,total_receita_mes,categoria_pai',
                                'order' => 'data_mes.desc',
                                'limit' => 1000
                            ]) ?: [];
                            
                            foreach ($all_despesas as $d) {
                                if (empty($d['data_mes'])) continue;
                                $mk = date('Y-m', strtotime($d['data_mes']));
                                if (!isset($meses_analise[$mk])) continue;
                                $catpai = strtoupper(trim($d['categoria_pai'] ?? ''));
                                $valor = floatval($d['total_receita_mes'] ?? 0);
                                
                                if ($catpai === 'CUSTO FIXO') {
                                    $meses_analise[$mk]['custo_fixo'] += $valor;
                                } elseif ($catpai === 'DESPESA FIXA') {
                                    $meses_analise[$mk]['despesa_fixa'] += $valor;
                                } elseif ($catpai === 'CUSTO VARIAVEL' || $catpai === 'CUSTO VARIÁVEL') {
                                    $meses_analise[$mk]['custo_variavel'] += $valor;
                                } elseif ($catpai === 'TRIBUTOS') {
                                    $meses_analise[$mk]['tributos'] += $valor;
                                } elseif ($catpai === 'DESPESAS DE VENDA' || $catpai === 'DESPESA DE VENDA' || $catpai === '(-) DESPESAS DE VENDA') {
                                    $meses_analise[$mk]['despesas_venda'] += $valor;
                                } elseif ($catpai === 'INVESTIMENTO INTERNO') {
                                    $meses_analise[$mk]['investimento_interno'] += $valor;
                                }
                            }
                            
                            // Filtrar apenas meses com receita (meses com registros)
                            $analise_mensal = array_filter($meses_analise, function($mes) {
                                return $mes['revenue'] > 0;
                            });
                            
                        } catch (Exception $e) {
                            // Em caso de erro, deixar vazio
                        }
                    }
                    ?>

                    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
                    <script>
                        (function(){
                            var data = <?= json_encode($chart_payload, JSON_UNESCAPED_UNICODE) ?>;
                            console.log('chart payload', data);
                            data.revenue = data.revenue || [];
                            data.fixed = data.fixed || [];
                            data.pct = data.pct || [];

                            var options = {
                                chart: { 
                                    height: 320, 
                                    width: '100%', 
                                    type: 'line', 
                                    toolbar: { show: false }, 
                                    zoom: { enabled: false }, 
                                    selection: { enabled: false }, 
                                    background: '#ffffff' 
                                },
                                stroke: { 
                                    width: [3, 4, 2], 
                                    curve: 'smooth',
                                    dashArray: [0, 0, 5]
                                },
                                series: [
                                    { name: 'Receita Operacional', type: 'line', data: data.revenue },
                                    { name: 'Custo Fixo', type: 'line', data: data.fixed },
                                    { name: '% Custo Fixo', type: 'line', data: data.pct }
                                ],
                                xaxis: { 
                                    categories: data.labels || [], 
                                    type: 'category', 
                                    axisBorder: { show: false }, 
                                    axisTicks: { show: false } 
                                },
                                yaxis: [
                                    {
                                        seriesName: 'Receita Operacional',
                                        title: { text: 'Valor (R$)' },
                                        min: 0,
                                        labels: { formatter: function(val){ return val.toLocaleString('pt-BR', {style:'currency', currency:'BRL'}); } },
                                        opposite: false
                                    },
                                    {
                                        seriesName: 'Receita Operacional',
                                        show: false,
                                        min: 0
                                    },
                                    {
                                        seriesName: '% Custo Fixo',
                                        title: { text: '% Custo Fixo' },
                                        min: 0,
                                        labels: { formatter: function(val){ return (val === null || typeof val === 'undefined') ? '-' : val + '%'; } },
                                        opposite: true
                                    }
                                ],
                                colors: ['#10b981', '#991b1b', '#000000'],
                                markers: { 
                                    size: [5, 7, 4],
                                    strokeWidth: [2, 2, 2],
                                    hover: { size: [7, 9, 6] }
                                },
                                tooltip: {
                                    enabled: true,
                                    shared: false,
                                    custom: function(opts) {
                                        var i = opts.dataPointIndex;
                                        var w = opts.w || {};
                                        if (typeof i === 'undefined' || i === null) i = 0;
                                        var categories = (w.config && w.config.xaxis && w.config.xaxis.categories) ? w.config.xaxis.categories : (w.config && w.config.labels ? w.config.labels : []);
                                        var label = categories[i] || '';
                                        var rev = (data.revenue && data.revenue[i] != null) ? Number(data.revenue[i]) : 0;
                                        var fix = (data.fixed && data.fixed[i] != null) ? Number(data.fixed[i]) : 0;
                                        var pct = (data.pct && data.pct[i] != null) ? data.pct[i] : null;
                                        var revStr = rev.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
                                        var fixStr = fix.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
                                        var pctStr = pct === null ? '-' : pct + '%';
                                        return '<div style="padding:8px;font-size:13px;background:#fff;color:#000;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.12);">'
                                            + '<div style="font-weight:600;margin-bottom:6px;">' + label + '</div>'
                                            + '<div>Receita: <strong>' + revStr + '</strong></div>'
                                            + '<div>Custo Fixo: <strong>' + fixStr + '</strong></div>'
                                            + '<div style="margin-top:6px">% sobre receita: <strong>' + pctStr + '</strong></div>'
                                            + '</div>';
                                    }
                                },
                                legend: { 
                                    show: true, 
                                    position: 'bottom', 
                                    horizontalAlign: 'center', 
                                    fontSize: '9px', 
                                    itemMargin: { horizontal: 3, vertical: 0 },
                                    floating: false,
                                    offsetY: 0,
                                    markers: { width: 8, height: 8 }
                                },
                                grid: { show: false },
                                theme: { mode: 'light' }
                            };

                            // Compatibility hints for different ApexCharts versions: explicitly map series to axes
                            // Receita e Custo Fixo usam o primeiro eixo Y (valores em R$)
                            // % Custo Fixo usa o terceiro eixo Y (percentual)
                            options.series[0].yAxis = 0; options.series[0].yaxis = 0; options.series[0].yAxisIndex = 0;
                            options.series[1].yAxis = 1; options.series[1].yaxis = 1; options.series[1].yAxisIndex = 1;
                            options.series[2].yAxis = 2; options.series[2].yaxis = 2; options.series[2].yAxisIndex = 2;

                            var chart = new ApexCharts(document.querySelector('#chart-custo-fixo'), options);
                            chart.render().then(function(){
                                try {
                                    // Force legend to be on one line, centered and smaller text
                                    var legend = document.querySelector('#chart-custo-fixo .apexcharts-legend');
                                    if (legend) {
                                        legend.style.whiteSpace = 'nowrap';
                                        legend.style.display = 'flex';
                                        legend.style.justifyContent = 'center';
                                        legend.style.flexWrap = 'nowrap';
                                        legend.style.gap = '6px';
                                        legend.style.fontSize = '9px';
                                        legend.style.overflowX = 'auto';
                                        legend.style.maxWidth = '100%';
                                        legend.style.padding = '6px 4px 0 4px';
                                        // improve scrolling on touch devices
                                        legend.style.webkitOverflowScrolling = 'touch';
                                        // ensure inner items don't wrap
                                        Array.from(legend.querySelectorAll('.apexcharts-legend-series')).forEach(function(item){
                                            item.style.whiteSpace = 'nowrap';
                                            item.style.display = 'inline-flex';
                                            item.style.alignItems = 'center';
                                            item.style.marginRight = '6px';
                                        });
                                    }

                                    // Inject stronger CSS rules to override any ApexCharts defaults
                                    var css = "#chart-custo-fixo .apexcharts-legend{white-space:nowrap!important;display:flex!important;flex-direction:row!important;justify-content:center!important;align-items:center!important;flex-wrap:nowrap!important;overflow-x:auto!important;gap:8px!important;padding:6px 4px!important;max-width:100%!important;width:100%!important;} ";
                                    css += "#chart-custo-fixo .apexcharts-legend .apexcharts-legend-series{white-space:nowrap!important;display:inline-flex!important;align-items:center!important;margin:0 4px!important;flex-shrink:0!important;} ";
                                    css += "#chart-custo-fixo .apexcharts-legend .apexcharts-legend-text{font-size:10px!important;margin-left:3px!important;}";
                                    // Garantir visibilidade de todas as séries
                                    css += "#chart-custo-fixo .apexcharts-series path{opacity:1!important;}";
                                    css += "#chart-custo-fixo .apexcharts-series .apexcharts-marker{opacity:1!important;}";
                                    var style = document.createElement('style');
                                    style.type = 'text/css';
                                    style.appendChild(document.createTextNode(css));
                                    document.head.appendChild(style);
                                } catch (e) {
                                    // não quebrar se algo falhar
                                }

                                // Debug info: log options, payload and rendered SVG series details
                                try {
                                    console.log('chart options', options);
                                    console.log('chart payload (data)', data);
                                    if (chart && chart.w && chart.w.globals) {
                                        console.log('apex globals series', chart.w.globals.series);
                                        console.log('apex globals seriesNames', chart.w.globals.seriesNames);
                                    }
                                    var seriesNodes = Array.from(document.querySelectorAll('#chart-custo-fixo .apexcharts-series'));
                                    var seriesDebug = seriesNodes.map(function(s, i){
                                        var paths = Array.from(s.querySelectorAll('path'));
                                        return {
                                            index: i,
                                            className: s.className,
                                            rel: s.getAttribute('rel'),
                                            pathCount: paths.length,
                                            paths: paths.map(function(p){
                                                var cs = window.getComputedStyle(p);
                                                return {
                                                    d: p.getAttribute('d'),
                                                    stroke: p.getAttribute('stroke') || cs.stroke,
                                                    strokeWidth: p.getAttribute('stroke-width') || cs.strokeWidth,
                                                    opacity: p.getAttribute('opacity') || cs.opacity
                                                };
                                            })
                                        };
                                    });
                                             console.log('apex series DOM debug', seriesDebug);
                                             } catch (e) {
                                                 console.log('debug collection failed', e && e.message ? e.message : e);
                                             }
                                         });
                                        // --- build third chart data for CUSTO VARIÁVEL (render below the two charts) ---
                                        try {
                                            var data3 = <?= json_encode($chart_payload_cv, JSON_UNESCAPED_UNICODE) ?>;
                                            data3.labels = data3.labels || [];
                                            data3.revenue = data3.revenue || [];
                                            data3.cv = data3.cv || [];
                                            data3.pct = data3.pct || [];

                                            var options3 = {
                                                chart: { 
                                                    height: 320, 
                                                    width: '100%', 
                                                    type: 'line', 
                                                    toolbar: { show: false }, 
                                                    zoom: { enabled: false }, 
                                                    selection: { enabled: false }, 
                                                    background: '#ffffff' 
                                                },
                                                stroke: { 
                                                    width: [3, 4, 2], 
                                                    curve: 'smooth',
                                                    dashArray: [0, 0, 5]
                                                },
                                                series: [
                                                    { name: 'Receita Operacional', type: 'line', data: data3.revenue },
                                                    { name: 'Custo Variável', type: 'line', data: data3.cv },
                                                    { name: '% Custo Variável', type: 'line', data: data3.pct }
                                                ],
                                                xaxis: { 
                                                    categories: data3.labels || [], 
                                                    type: 'category', 
                                                    axisBorder: { show: false }, 
                                                    axisTicks: { show: false } 
                                                },
                                                yaxis: [
                                                    {
                                                        seriesName: 'Receita Operacional',
                                                        title: { text: 'Valor (R$)' },
                                                        min: 0,
                                                        labels: { formatter: function(val){ return val.toLocaleString('pt-BR', {style:'currency', currency:'BRL'}); } },
                                                        opposite: false
                                                    },
                                                    {
                                                        seriesName: 'Receita Operacional',
                                                        show: false,
                                                        min: 0
                                                    },
                                                    {
                                                        seriesName: '% Custo Variável',
                                                        title: { text: '% Custo Variável' },
                                                        min: 0,
                                                        labels: { formatter: function(val){ return (val === null || typeof val === 'undefined') ? '-' : val + '%'; } },
                                                        opposite: true
                                                    }
                                                ],
                                                colors: ['#10b981', '#991b1b', '#000000'],
                                                markers: { 
                                                    size: [5, 7, 4],
                                                    strokeWidth: [2, 2, 2],
                                                    hover: { size: [7, 9, 6] }
                                                },
                                                tooltip: {
                                                    enabled: true,
                                                    shared: false,
                                                    custom: function(opts) {
                                                        var i = opts.dataPointIndex;
                                                        var w = opts.w || {};
                                                        if (typeof i === 'undefined' || i === null) i = 0;
                                                        var categories = (w.config && w.config.xaxis && w.config.xaxis.categories) ? w.config.xaxis.categories : (w.config && w.config.labels ? w.config.labels : []);
                                                        var label = categories[i] || '';
                                                        var rev = (data3.revenue && data3.revenue[i] != null) ? Number(data3.revenue[i]) : 0;
                                                        var cv = (data3.cv && data3.cv[i] != null) ? Number(data3.cv[i]) : 0;
                                                        var pct = (data3.pct && data3.pct[i] != null) ? data3.pct[i] : null;
                                                        var revStr = rev.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
                                                        var cvStr = cv.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
                                                        var pctStr = pct === null ? '-' : pct + '%';
                                                        return '<div style="padding:8px;font-size:13px;background:#fff;color:#000;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.12);">'
                                                            + '<div style="font-weight:600;margin-bottom:6px;">' + label + '</div>'
                                                            + '<div>Receita: <strong>' + revStr + '</strong></div>'
                                                            + '<div>Custo Variável: <strong>' + cvStr + '</strong></div>'
                                                            + '<div style="margin-top:6px">% sobre receita: <strong>' + pctStr + '</strong></div>'
                                                            + '</div>';
                                                    }
                                                },
                                                legend: { 
                                                    show: true, 
                                                    position: 'bottom', 
                                                    horizontalAlign: 'center', 
                                                    fontSize: '9px', 
                                                    itemMargin: { horizontal: 3, vertical: 0 },
                                                    floating: false,
                                                    offsetY: 0,
                                                    markers: { width: 8, height: 8 }
                                                },
                                                grid: { show: false },
                                                theme: { mode: 'light' }
                                            };

                                            // Compatibility hints
                                            options3.series[0].yAxis = 0; options3.series[0].yaxis = 0; options3.series[0].yAxisIndex = 0;
                                            options3.series[1].yAxis = 1; options3.series[1].yaxis = 1; options3.series[1].yAxisIndex = 1;
                                            options3.series[2].yAxis = 2; options3.series[2].yaxis = 2; options3.series[2].yAxisIndex = 2;

                                            var chart3 = new ApexCharts(document.querySelector('#chart-custo-variavel'), options3);
                                            chart3.render().then(function(){
                                                try {
                                                    var legend3 = document.querySelector('#chart-custo-variavel .apexcharts-legend');
                                                    if (legend3) {
                                                        legend3.style.whiteSpace = 'nowrap';
                                                        legend3.style.display = 'flex';
                                                        legend3.style.justifyContent = 'center';
                                                        legend3.style.flexWrap = 'nowrap';
                                                        legend3.style.gap = '6px';
                                                        legend3.style.fontSize = '9px';
                                                        legend3.style.overflowX = 'auto';
                                                        legend3.style.maxWidth = '100%';
                                                        legend3.style.padding = '6px 4px 0 4px';
                                                    }

                                                    var css3 = "#chart-custo-variavel .apexcharts-legend{white-space:nowrap!important;display:flex!important;flex-direction:row!important;justify-content:center!important;align-items:center!important;flex-wrap:nowrap!important;overflow-x:auto!important;gap:8px!important;padding:6px 4px!important;max-width:100%!important;width:100%!important;} ";
                                                    css3 += "#chart-custo-variavel .apexcharts-legend .apexcharts-legend-series{white-space:nowrap!important;display:inline-flex!important;align-items:center!important;margin:0 4px!important;flex-shrink:0!important;} ";
                                                    css3 += "#chart-custo-variavel .apexcharts-legend .apexcharts-legend-text{font-size:10px!important;margin-left:3px!important;}";
                                                    css3 += "#chart-custo-variavel .apexcharts-series path{opacity:1!important;}";
                                                    css3 += "#chart-custo-variavel .apexcharts-series .apexcharts-marker{opacity:1!important;}";
                                                    var style3 = document.createElement('style');
                                                    style3.type = 'text/css';
                                                    style3.appendChild(document.createTextNode(css3));
                                                    document.head.appendChild(style3);
                                                } catch (e) {}
                                            });
                                        } catch (e) {
                                            console.log('failed to render third chart', e && e.message ? e.message : e);
                                        }
                                            // --- build fourth chart data for TRIBUTOS (same size, below) ---
                                            try {
                                                var data4 = <?= json_encode($chart_payload_tr, JSON_UNESCAPED_UNICODE) ?>;
                                                data4.labels = data4.labels || [];
                                                data4.revenue = data4.revenue || [];
                                                data4.tr = data4.tr || [];
                                                data4.pct = data4.pct || [];

                                                var options4 = {
                                                    chart: { 
                                                        height: 320, 
                                                        width: '100%', 
                                                        type: 'line', 
                                                        toolbar: { show: false }, 
                                                        zoom: { enabled: false }, 
                                                        selection: { enabled: false }, 
                                                        background: '#ffffff' 
                                                    },
                                                    stroke: { 
                                                        width: [3, 4, 2], 
                                                        curve: 'smooth',
                                                        dashArray: [0, 0, 5]
                                                    },
                                                    series: [
                                                        { name: 'Receita Operacional', type: 'line', data: data4.revenue },
                                                        { name: 'Tributos', type: 'line', data: data4.tr },
                                                        { name: '% Tributos', type: 'line', data: data4.pct }
                                                    ],
                                                    xaxis: { 
                                                        categories: data4.labels || [], 
                                                        type: 'category', 
                                                        axisBorder: { show: false }, 
                                                        axisTicks: { show: false } 
                                                    },
                                                    yaxis: [
                                                        {
                                                            seriesName: 'Receita Operacional',
                                                            title: { text: 'Valor (R$)' },
                                                            min: 0,
                                                            labels: { formatter: function(val){ return val.toLocaleString('pt-BR', {style:'currency', currency:'BRL'}); } },
                                                            opposite: false
                                                        },
                                                        {
                                                            seriesName: 'Receita Operacional',
                                                            show: false,
                                                            min: 0
                                                        },
                                                        {
                                                            seriesName: '% Tributos',
                                                            title: { text: '% Tributos' },
                                                            min: 0,
                                                            labels: { formatter: function(val){ return (val === null || typeof val === 'undefined') ? '-' : val + '%'; } },
                                                            opposite: true
                                                        }
                                                    ],
                                                    colors: ['#10b981', '#991b1b', '#000000'],
                                                    markers: { 
                                                        size: [5, 7, 4],
                                                        strokeWidth: [2, 2, 2],
                                                        hover: { size: [7, 9, 6] }
                                                    },
                                                    tooltip: {
                                                        enabled: true,
                                                        shared: false,
                                                        custom: function(opts) {
                                                            var i = opts.dataPointIndex;
                                                            var w = opts.w || {};
                                                            if (typeof i === 'undefined' || i === null) i = 0;
                                                            var categories = (w.config && w.config.xaxis && w.config.xaxis.categories) ? w.config.xaxis.categories : (w.config && w.config.labels ? w.config.labels : []);
                                                            var label = categories[i] || '';
                                                            var rev = (data4.revenue && data4.revenue[i] != null) ? Number(data4.revenue[i]) : 0;
                                                            var tr = (data4.tr && data4.tr[i] != null) ? Number(data4.tr[i]) : 0;
                                                            var pct = (data4.pct && data4.pct[i] != null) ? data4.pct[i] : null;
                                                            var revStr = rev.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
                                                            var trStr = tr.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
                                                            var pctStr = pct === null ? '-' : pct + '%';
                                                            return '<div style="padding:8px;font-size:13px;background:#fff;color:#000;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.12);">'
                                                                + '<div style="font-weight:600;margin-bottom:6px;">' + label + '</div>'
                                                                + '<div>Receita: <strong>' + revStr + '</strong></div>'
                                                                + '<div>Tributos: <strong>' + trStr + '</strong></div>'
                                                                + '<div style="margin-top:6px">% sobre receita: <strong>' + pctStr + '</strong></div>'
                                                                + '</div>';
                                                        }
                                                    },
                                                    legend: { 
                                                        show: true, 
                                                        position: 'bottom', 
                                                        horizontalAlign: 'center', 
                                                        fontSize: '9px', 
                                                        itemMargin: { horizontal: 3, vertical: 0 },
                                                        floating: false,
                                                        offsetY: 0,
                                                        markers: { width: 8, height: 8 }
                                                    },
                                                    grid: { show: false },
                                                    theme: { mode: 'light' }
                                                };

                                                // compatibility hints
                                                options4.series[0].yAxis = 0; options4.series[0].yaxis = 0; options4.series[0].yAxisIndex = 0;
                                                options4.series[1].yAxis = 1; options4.series[1].yaxis = 1; options4.series[1].yAxisIndex = 1;
                                                options4.series[2].yAxis = 2; options4.series[2].yaxis = 2; options4.series[2].yAxisIndex = 2;

                                                var chart4 = new ApexCharts(document.querySelector('#chart-tributos'), options4);
                                                chart4.render().then(function(){
                                                    try {
                                                        var legend4 = document.querySelector('#chart-tributos .apexcharts-legend');
                                                        if (legend4) {
                                                            legend4.style.whiteSpace = 'nowrap';
                                                            legend4.style.display = 'flex';
                                                            legend4.style.justifyContent = 'center';
                                                            legend4.style.flexWrap = 'nowrap';
                                                            legend4.style.gap = '6px';
                                                            legend4.style.fontSize = '9px';
                                                            legend4.style.overflowX = 'auto';
                                                            legend4.style.maxWidth = '100%';
                                                            legend4.style.padding = '6px 4px 0 4px';
                                                        }
                                                        var css4 = "#chart-tributos .apexcharts-legend{white-space:nowrap!important;display:flex!important;flex-direction:row!important;justify-content:center!important;align-items:center!important;flex-wrap:nowrap!important;overflow-x:auto!important;gap:8px!important;padding:6px 4px!important;max-width:100%!important;width:100%!important;} ";
                                                        css4 += "#chart-tributos .apexcharts-legend .apexcharts-legend-series{white-space:nowrap!important;display:inline-flex!important;align-items:center!important;margin:0 4px!important;flex-shrink:0!important;} ";
                                                        css4 += "#chart-tributos .apexcharts-legend .apexcharts-legend-text{font-size:10px!important;margin-left:3px!important;}";
                                                        css4 += "#chart-tributos .apexcharts-series path{opacity:1!important;}";
                                                        css4 += "#chart-tributos .apexcharts-series .apexcharts-marker{opacity:1!important;}";
                                                        var style4 = document.createElement('style');
                                                        style4.type = 'text/css';
                                                        style4.appendChild(document.createTextNode(css4));
                                                        document.head.appendChild(style4);
                                                    } catch (e) {}
                                                });
                                            } catch (e) {
                                                console.log('failed to render tributos chart', e && e.message ? e.message : e);
                                            }
                            // --- build fifth chart data for DESPESAS DE VENDA ---
                            try {
                                var data5 = <?= json_encode($chart_payload_dv, JSON_UNESCAPED_UNICODE) ?>;
                                data5.labels = data5.labels || [];
                                data5.revenue = data5.revenue || [];
                                data5.dv = data5.dv || [];
                                data5.pct = data5.pct || [];

                                var options5 = {
                                    chart: { 
                                        height: 320, 
                                        width: '100%', 
                                        type: 'line', 
                                        toolbar: { show: false }, 
                                        zoom: { enabled: false }, 
                                        selection: { enabled: false }, 
                                        background: '#ffffff' 
                                    },
                                    stroke: { 
                                        width: [3, 4, 2], 
                                        curve: 'smooth',
                                        dashArray: [0, 0, 5]
                                    },
                                    series: [
                                        { name: 'Receita Operacional', type: 'line', data: data5.revenue },
                                        { name: 'Despesas de Venda', type: 'line', data: data5.dv },
                                        { name: '% Despesas de Venda', type: 'line', data: data5.pct }
                                    ],
                                    xaxis: { 
                                        categories: data5.labels || [], 
                                        type: 'category', 
                                        axisBorder: { show: false }, 
                                        axisTicks: { show: false } 
                                    },
                                    yaxis: [
                                        {
                                            seriesName: 'Receita Operacional',
                                            title: { text: 'Valor (R$)' },
                                            min: 0,
                                            labels: { formatter: function(val){ return val.toLocaleString('pt-BR', {style:'currency', currency:'BRL'}); } },
                                            opposite: false
                                        },
                                        {
                                            seriesName: 'Receita Operacional',
                                            show: false,
                                            min: 0
                                        },
                                        {
                                            seriesName: '% Despesas de Venda',
                                            title: { text: '% Despesas de Venda' },
                                            min: 0,
                                            labels: { formatter: function(val){ return (val === null || typeof val === 'undefined') ? '-' : val + '%'; } },
                                            opposite: true
                                        }
                                    ],
                                    colors: ['#10b981', '#991b1b', '#000000'],
                                    markers: { 
                                        size: [5, 7, 4],
                                        strokeWidth: [2, 2, 2],
                                        hover: { size: [7, 9, 6] }
                                    },
                                    tooltip: {
                                        enabled: true,
                                        shared: false,
                                        custom: function(opts) {
                                            var i = opts.dataPointIndex;
                                            var w = opts.w || {};
                                            if (typeof i === 'undefined' || i === null) i = 0;
                                            var categories = (w.config && w.config.xaxis && w.config.xaxis.categories) ? w.config.xaxis.categories : (w.config && w.config.labels ? w.config.labels : []);
                                            var label = categories[i] || '';
                                            var rev = (data5.revenue && data5.revenue[i] != null) ? Number(data5.revenue[i]) : 0;
                                            var dv = (data5.dv && data5.dv[i] != null) ? Number(data5.dv[i]) : 0;
                                            var pct = (data5.pct && data5.pct[i] != null) ? data5.pct[i] : null;
                                            var revStr = rev.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
                                            var dvStr = dv.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
                                            var pctStr = pct === null ? '-' : pct + '%';
                                            return '<div style="padding:8px;font-size:13px;background:#fff;color:#000;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.12);">'
                                                + '<div style="font-weight:600;margin-bottom:6px;">' + label + '</div>'
                                                + '<div>Receita: <strong>' + revStr + '</strong></div>'
                                                + '<div>Despesas de Venda: <strong>' + dvStr + '</strong></div>'
                                                + '<div style="margin-top:6px">% sobre receita: <strong>' + pctStr + '</strong></div>'
                                                + '</div>';
                                        }
                                    },
                                    legend: { 
                                        show: true, 
                                        position: 'bottom', 
                                        horizontalAlign: 'center', 
                                        fontSize: '9px', 
                                        itemMargin: { horizontal: 3, vertical: 0 },
                                        floating: false,
                                        offsetY: 0,
                                        markers: { width: 8, height: 8 }
                                    },
                                    grid: { show: false },
                                    theme: { mode: 'light' }
                                };

                                // compatibility hints
                                options5.series[0].yAxis = 0; options5.series[0].yaxis = 0; options5.series[0].yAxisIndex = 0;
                                options5.series[1].yAxis = 1; options5.series[1].yaxis = 1; options5.series[1].yAxisIndex = 1;
                                options5.series[2].yAxis = 2; options5.series[2].yaxis = 2; options5.series[2].yAxisIndex = 2;

                                var chart5 = new ApexCharts(document.querySelector('#chart-despesas-venda'), options5);
                                chart5.render().then(function(){
                                    try {
                                        var legend5 = document.querySelector('#chart-despesas-venda .apexcharts-legend');
                                        if (legend5) {
                                            legend5.style.whiteSpace = 'nowrap';
                                            legend5.style.display = 'flex';
                                            legend5.style.justifyContent = 'center';
                                            legend5.style.flexWrap = 'nowrap';
                                            legend5.style.gap = '6px';
                                            legend5.style.fontSize = '9px';
                                            legend5.style.overflowX = 'auto';
                                            legend5.style.maxWidth = '100%';
                                            legend5.style.padding = '6px 4px 0 4px';
                                        }
                                        var css5 = "#chart-despesas-venda .apexcharts-legend{white-space:nowrap!important;display:flex!important;flex-direction:row!important;justify-content:center!important;align-items:center!important;flex-wrap:nowrap!important;overflow-x:auto!important;gap:8px!important;padding:6px 4px!important;max-width:100%!important;width:100%!important;} ";
                                        css5 += "#chart-despesas-venda .apexcharts-legend .apexcharts-legend-series{white-space:nowrap!important;display:inline-flex!important;align-items:center!important;margin:0 4px!important;flex-shrink:0!important;} ";
                                        css5 += "#chart-despesas-venda .apexcharts-legend .apexcharts-legend-text{font-size:10px!important;margin-left:3px!important;}";
                                        css5 += "#chart-despesas-venda .apexcharts-series path{opacity:1!important;}";
                                        css5 += "#chart-despesas-venda .apexcharts-series .apexcharts-marker{opacity:1!important;}";
                                        var style5 = document.createElement('style');
                                        style5.type = 'text/css';
                                        style5.appendChild(document.createTextNode(css5));
                                        document.head.appendChild(style5);
                                    } catch (e) {}
                                });
                            } catch (e) {
                                console.log('failed to render despesas de venda chart', e && e.message ? e.message : e);
                            }
                            // --- build sixth chart data for INVESTIMENTO INTERNO ---
                            try {
                                var data6 = <?= json_encode($chart_payload_ii, JSON_UNESCAPED_UNICODE) ?>;
                                data6.labels = data6.labels || [];
                                data6.revenue = data6.revenue || [];
                                data6.ii = data6.ii || [];
                                data6.pct = data6.pct || [];

                                var options6 = {
                                    chart: { 
                                        height: 320, 
                                        width: '100%', 
                                        type: 'line', 
                                        toolbar: { show: false }, 
                                        zoom: { enabled: false }, 
                                        selection: { enabled: false }, 
                                        background: '#ffffff' 
                                    },
                                    stroke: { 
                                        width: [3, 4, 2], 
                                        curve: 'smooth',
                                        dashArray: [0, 0, 5]
                                    },
                                    series: [
                                        { name: 'Receita Operacional', type: 'line', data: data6.revenue },
                                        { name: 'Investimento Interno', type: 'line', data: data6.ii },
                                        { name: '% Investimento Interno', type: 'line', data: data6.pct }
                                    ],
                                    xaxis: { 
                                        categories: data6.labels || [], 
                                        type: 'category', 
                                        axisBorder: { show: false }, 
                                        axisTicks: { show: false } 
                                    },
                                    yaxis: [
                                        {
                                            seriesName: 'Receita Operacional',
                                            title: { text: 'Valor (R$)' },
                                            min: 0,
                                            labels: { formatter: function(val){ return val.toLocaleString('pt-BR', {style:'currency', currency:'BRL'}); } },
                                            opposite: false
                                        },
                                        {
                                            seriesName: 'Receita Operacional',
                                            show: false,
                                            min: 0
                                        },
                                        {
                                            seriesName: '% Investimento Interno',
                                            title: { text: '% Investimento Interno' },
                                            min: 0,
                                            labels: { formatter: function(val){ return (val === null || typeof val === 'undefined') ? '-' : val + '%'; } },
                                            opposite: true
                                        }
                                    ],
                                    colors: ['#10b981', '#991b1b', '#000000'],
                                    markers: { 
                                        size: [5, 7, 4],
                                        strokeWidth: [2, 2, 2],
                                        hover: { size: [7, 9, 6] }
                                    },
                                    tooltip: {
                                        enabled: true,
                                        shared: false,
                                        custom: function(opts) {
                                            var i = opts.dataPointIndex;
                                            var w = opts.w || {};
                                            if (typeof i === 'undefined' || i === null) i = 0;
                                            var categories = (w.config && w.config.xaxis && w.config.xaxis.categories) ? w.config.xaxis.categories : (w.config && w.config.labels ? w.config.labels : []);
                                            var label = categories[i] || '';
                                            var rev = (data6.revenue && data6.revenue[i] != null) ? Number(data6.revenue[i]) : 0;
                                            var ii = (data6.ii && data6.ii[i] != null) ? Number(data6.ii[i]) : 0;
                                            var pct = (data6.pct && data6.pct[i] != null) ? data6.pct[i] : null;
                                            var revStr = rev.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
                                            var iiStr = ii.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
                                            var pctStr = pct === null ? '-' : pct + '%';
                                            return '<div style="padding:8px;font-size:13px;background:#fff;color:#000;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.12);">'
                                                + '<div style="font-weight:600;margin-bottom:6px;">' + label + '</div>'
                                                + '<div>Receita: <strong>' + revStr + '</strong></div>'
                                                + '<div>Investimento Interno: <strong>' + iiStr + '</strong></div>'
                                                + '<div style="margin-top:6px">% sobre receita: <strong>' + pctStr + '</strong></div>'
                                                + '</div>';
                                        }
                                    },
                                    legend: { 
                                        show: true, 
                                        position: 'bottom', 
                                        horizontalAlign: 'center', 
                                        fontSize: '9px', 
                                        itemMargin: { horizontal: 3, vertical: 0 },
                                        floating: false,
                                        offsetY: 0,
                                        markers: { width: 8, height: 8 }
                                    },
                                    grid: { show: false },
                                    theme: { mode: 'light' }
                                };

                                // compatibility hints
                                options6.series[0].yAxis = 0; options6.series[0].yaxis = 0; options6.series[0].yAxisIndex = 0;
                                options6.series[1].yAxis = 1; options6.series[1].yaxis = 1; options6.series[1].yAxisIndex = 1;
                                options6.series[2].yAxis = 2; options6.series[2].yaxis = 2; options6.series[2].yAxisIndex = 2;

                                var chart6 = new ApexCharts(document.querySelector('#chart-investimento-interno'), options6);
                                chart6.render().then(function(){
                                    try {
                                        var legend6 = document.querySelector('#chart-investimento-interno .apexcharts-legend');
                                        if (legend6) {
                                            legend6.style.whiteSpace = 'nowrap';
                                            legend6.style.display = 'flex';
                                            legend6.style.justifyContent = 'center';
                                            legend6.style.flexWrap = 'nowrap';
                                            legend6.style.gap = '6px';
                                            legend6.style.fontSize = '9px';
                                            legend6.style.overflowX = 'auto';
                                            legend6.style.maxWidth = '100%';
                                            legend6.style.padding = '6px 4px 0 4px';
                                        }
                                        var css6 = "#chart-investimento-interno .apexcharts-legend{white-space:nowrap!important;display:flex!important;flex-direction:row!important;justify-content:center!important;align-items:center!important;flex-wrap:nowrap!important;overflow-x:auto!important;gap:8px!important;padding:6px 4px!important;max-width:100%!important;width:100%!important;} ";
                                        css6 += "#chart-investimento-interno .apexcharts-legend .apexcharts-legend-series{white-space:nowrap!important;display:inline-flex!important;align-items:center!important;margin:0 4px!important;flex-shrink:0!important;} ";
                                        css6 += "#chart-investimento-interno .apexcharts-legend .apexcharts-legend-text{font-size:10px!important;margin-left:3px!important;}";
                                        css6 += "#chart-investimento-interno .apexcharts-series path{opacity:1!important;}";
                                        css6 += "#chart-investimento-interno .apexcharts-series .apexcharts-marker{opacity:1!important;}";
                                        var style6 = document.createElement('style');
                                        style6.type = 'text/css';
                                        style6.appendChild(document.createTextNode(css6));
                                        document.head.appendChild(style6);
                                    } catch (e) {}
                                });
                            } catch (e) {
                                console.log('failed to render investimento interno chart', e && e.message ? e.message : e);
                            }
                            // --- build second chart data for DESPESA FIXA ---
                            try {
                                var data2 = <?= json_encode($chart_payload_df, JSON_UNESCAPED_UNICODE) ?>;
                                data2.labels = data2.labels || [];
                                data2.revenue = data2.revenue || [];
                                data2.df = data2.df || [];
                                data2.pct = data2.pct || [];

                                var options2 = {
                                    chart: { 
                                        height: 320, 
                                        width: '100%', 
                                        type: 'line', 
                                        toolbar: { show: false }, 
                                        zoom: { enabled: false }, 
                                        selection: { enabled: false }, 
                                        background: '#ffffff' 
                                    },
                                    stroke: { 
                                        width: [3, 4, 2], 
                                        curve: 'smooth',
                                        dashArray: [0, 0, 5]
                                    },
                                    series: [
                                        { name: 'Receita Operacional', type: 'line', data: data2.revenue },
                                        { name: 'Despesa Fixa', type: 'line', data: data2.df },
                                        { name: '% Despesa Fixa', type: 'line', data: data2.pct }
                                    ],
                                    xaxis: { 
                                        categories: data2.labels || [], 
                                        type: 'category', 
                                        axisBorder: { show: false }, 
                                        axisTicks: { show: false } 
                                    },
                                    yaxis: [
                                        {
                                            seriesName: 'Receita Operacional',
                                            title: { text: 'Valor (R$)' },
                                            min: 0,
                                            labels: { formatter: function(val){ return val.toLocaleString('pt-BR', {style:'currency', currency:'BRL'}); } },
                                            opposite: false
                                        },
                                        {
                                            seriesName: 'Receita Operacional',
                                            show: false,
                                            min: 0
                                        },
                                        {
                                            seriesName: '% Despesa Fixa',
                                            title: { text: '% Despesa Fixa' },
                                            min: 0,
                                            labels: { formatter: function(val){ return (val === null || typeof val === 'undefined') ? '-' : val + '%'; } },
                                            opposite: true
                                        }
                                    ],
                                    colors: ['#10b981', '#991b1b', '#000000'],
                                    markers: { 
                                        size: [5, 7, 4],
                                        strokeWidth: [2, 2, 2],
                                        hover: { size: [7, 9, 6] }
                                    },
                                    tooltip: {
                                        enabled: true,
                                        shared: false,
                                        custom: function(opts) {
                                            var i = opts.dataPointIndex;
                                            var w = opts.w || {};
                                            if (typeof i === 'undefined' || i === null) i = 0;
                                            var categories = (w.config && w.config.xaxis && w.config.xaxis.categories) ? w.config.xaxis.categories : (w.config && w.config.labels ? w.config.labels : []);
                                            var label = categories[i] || '';
                                            var rev = (data2.revenue && data2.revenue[i] != null) ? Number(data2.revenue[i]) : 0;
                                            var df = (data2.df && data2.df[i] != null) ? Number(data2.df[i]) : 0;
                                            var pct = (data2.pct && data2.pct[i] != null) ? data2.pct[i] : null;
                                            var revStr = rev.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
                                            var dfStr = df.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
                                            var pctStr = pct === null ? '-' : pct + '%';
                                            return '<div style="padding:8px;font-size:13px;background:#fff;color:#000;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.12);">'
                                                + '<div style="font-weight:600;margin-bottom:6px;">' + label + '</div>'
                                                + '<div>Receita: <strong>' + revStr + '</strong></div>'
                                                + '<div>Despesa Fixa: <strong>' + dfStr + '</strong></div>'
                                                + '<div style="margin-top:6px">% sobre receita: <strong>' + pctStr + '</strong></div>'
                                                + '</div>';
                                        }
                                    },
                                    legend: { 
                                        show: true, 
                                        position: 'bottom', 
                                        horizontalAlign: 'center', 
                                        fontSize: '9px', 
                                        itemMargin: { horizontal: 3, vertical: 0 },
                                        floating: false,
                                        offsetY: 0,
                                        markers: { width: 8, height: 8 }
                                    },
                                    grid: { show: false },
                                    theme: { mode: 'light' }
                                };

                                // Compatibility hints
                                options2.series[0].yAxis = 0; options2.series[0].yaxis = 0; options2.series[0].yAxisIndex = 0;
                                options2.series[1].yAxis = 1; options2.series[1].yaxis = 1; options2.series[1].yAxisIndex = 1;
                                options2.series[2].yAxis = 2; options2.series[2].yaxis = 2; options2.series[2].yAxisIndex = 2;

                                var chart2 = new ApexCharts(document.querySelector('#chart-despesa-fixa'), options2);
                                chart2.render().then(function(){
                                    try {
                                        // force legend style for second chart
                                        var legend2 = document.querySelector('#chart-despesa-fixa .apexcharts-legend');
                                        if (legend2) {
                                            legend2.style.whiteSpace = 'nowrap';
                                            legend2.style.display = 'flex';
                                            legend2.style.justifyContent = 'center';
                                            legend2.style.flexWrap = 'nowrap';
                                            legend2.style.gap = '6px';
                                            legend2.style.fontSize = '9px';
                                            legend2.style.overflowX = 'auto';
                                            legend2.style.maxWidth = '100%';
                                            legend2.style.padding = '6px 4px 0 4px';
                                        }

                                        var css2 = "#chart-despesa-fixa .apexcharts-legend{white-space:nowrap!important;display:flex!important;flex-direction:row!important;justify-content:center!important;align-items:center!important;flex-wrap:nowrap!important;overflow-x:auto!important;gap:8px!important;padding:6px 4px!important;max-width:100%!important;width:100%!important;} ";
                                        css2 += "#chart-despesa-fixa .apexcharts-legend .apexcharts-legend-series{white-space:nowrap!important;display:inline-flex!important;align-items:center!important;margin:0 4px!important;flex-shrink:0!important;} ";
                                        css2 += "#chart-despesa-fixa .apexcharts-legend .apexcharts-legend-text{font-size:10px!important;margin-left:3px!important;}";
                                        css2 += "#chart-despesa-fixa .apexcharts-series path{opacity:1!important;}";
                                        css2 += "#chart-despesa-fixa .apexcharts-series .apexcharts-marker{opacity:1!important;}";
                                        var style2 = document.createElement('style');
                                        style2.type = 'text/css';
                                        style2.appendChild(document.createTextNode(css2));
                                        document.head.appendChild(style2);
                                    } catch (e) {
                                        // ignore
                                    }
                                });
                            } catch (e) {
                                console.log('failed to render second chart', e && e.message ? e.message : e);
                            }
                        })();
                    </script>

                    <script>
                    // Fix visual/layout: ensure this page content integrates with global sidebar and dark background
                    document.addEventListener('DOMContentLoaded', function() {
                        try {
                            var content = document.getElementById('content');
                            var receita = document.getElementById('receita-content');
                            // Move area into #content so the sidebar responsive behaviour works
                            if (content && receita && !content.contains(receita)) {
                                content.appendChild(receita);
                            }
                            if (content) {
                                // apply dark background similar to other pages
                                content.style.background = '#111827';
                                content.style.paddingLeft = '2rem';
                            }
                        } catch (e) {
                            // não quebrar a página se algo falhar
                        }
                    });
                    </script>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div style="padding:12px;">
            <p style="margin:0 0 8px 0;color:#444;">Selecione um período para visualizar os dados.</p>
            <?php if (!empty($periodos_disponiveis)): ?>
                <p style="margin:0;color:#666;">Períodos disponíveis: <?= count($periodos_disponiveis) ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Incluir Modal de Detalhamento -->
<?php require_once __DIR__ . '/components/category_detail_modal.php'; ?>

<!-- CSS do Modal -->
<link rel="stylesheet" href="css/kpi_modals.css?v=5.1">

<!-- JavaScript do Modal -->
<script src="js/kpi_details.js?v=5.1"></script>

<!-- Função helper para abrir modal com período -->
<script>
function openDetailWithPeriod(categoria) {
    const selectPeriodo = document.getElementById('selectPeriodo');
    const periodoSelecionado = selectPeriodo ? selectPeriodo.value : '';
    KPIDetailsModal.open(categoria, periodoSelecionado);
}

// Carregar análise DRE
document.addEventListener('DOMContentLoaded', function() {
    const periodo = '<?= $periodo_selecionado ?>';
    if (periodo) {
        loadDREAnalysis(periodo);
    }
});

// Estado global para expansão de linhas DRE e dados carregados
const dreExpandedRows = new Set();
let dreLastData = null;

async function loadDREAnalysis(periodo) {
    const container = document.getElementById('dre-analysis-container');
    if (!container) return;
    
    try {
        const response = await fetch(`api/get_dre_analysis.php?periodo=${encodeURIComponent(periodo)}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar dados');
        }
        
        dreLastData = data.linhas; // Armazenar dados
        renderDRETable(data.linhas);
        
    } catch (error) {
        console.error('Erro ao carregar DRE:', error);
        container.innerHTML = `
            <div class="text-center text-red-400 py-4">
                <p>❌ Erro ao carregar análise DRE</p>
                <p class="text-sm text-gray-500 mt-2">${error.message}</p>
            </div>
        `;
    }
}

function renderDRETable(linhas) {
    const container = document.getElementById('dre-analysis-container');
    if (!container) return;
    
    const formatCurrency = (val) => val.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    const formatPercent = (val) => {
        const sign = val > 0 ? '+' : '';
        const icon = val > 0 ? '🔺' : (val < 0 ? '🔻' : '➡️');
        return `${sign}${val.toFixed(1)}% ${icon}`;
    };
    
    const getRowClass = (tipo) => {
        switch(tipo) {
            case 'receita': return 'text-green-400 font-semibold';
            case 'despesa': return 'text-red-400 font-semibold';
            case 'resultado': return 'text-yellow-400 font-bold bg-gray-700/30';
            default: return '';
        }
    };
    
    const getVariationClass = (val, isReceita = false) => {
        // Para RECEITA OPERACIONAL: verde quando sobe, vermelho quando cai
        if (isReceita) {
            if (val > 0) return 'text-green-400';
            if (val < 0) return 'text-red-400';
            return 'text-gray-400';
        }
        
        // Para outras linhas (despesas): vermelho quando sobe, verde quando cai
        if (val > 0) return 'text-red-400';
        if (val < 0) return 'text-green-400';
        return 'text-gray-400';
    };
    
    // Obter termo de busca atual
    const searchTerm = (window.dreSearchTerm || '').toLowerCase().trim();
    
    // Criar input de busca apenas na primeira renderização
    const searchContainer = document.getElementById('dre-search-container');
    if (searchContainer && !document.getElementById('dre-search-input')) {
        searchContainer.innerHTML = `
            <input 
                type="text" 
                id="dre-search-input" 
                placeholder="🔍 Buscar subcategoria..." 
                class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded border border-gray-600 focus:border-yellow-400 focus:outline-none"
            />
        `;
        
        // Adicionar event listener
        const searchInput = document.getElementById('dre-search-input');
        searchInput.addEventListener('input', function(e) {
            filterDRETable(e.target.value);
        });
    }
    
    let html = `
        <div class="overflow-x-auto">
            <table class="w-full text-sm" style="border-collapse: collapse;">
                <thead class="bg-gray-900">
                    <tr>
                        <th class="text-left py-3 px-4 text-gray-300 font-semibold" style="white-space:nowrap;">Descrição</th>
                        <th class="text-right py-3 px-3 text-gray-300 font-semibold" style="white-space:nowrap;">Média 6M</th>
                        <th class="text-right py-3 px-3 text-gray-300 font-semibold" style="white-space:nowrap;">Média 3M</th>
                        <th class="text-right py-3 px-3 text-gray-300 font-semibold" style="white-space:nowrap;">Mês Ant.</th>
                        <th class="text-right py-3 px-3 text-gray-300 font-semibold" style="white-space:nowrap;">Valor Atual</th>
                        <th class="text-right py-3 px-3 text-gray-300 font-semibold" style="white-space:nowrap;">vs M3</th>
                        <th class="text-right py-3 px-3 text-gray-300 font-semibold" style="white-space:nowrap;">Var. M</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    for (const [chave, linha] of Object.entries(linhas)) {
        const rowClass = getRowClass(linha.tipo);
        const isReceita = chave === 'receita_operacional';
        const varClassVsM3 = getVariationClass(linha.vs_media_3m, isReceita);
        const varClassMes = getVariationClass(linha.variacao_mes, isReceita);
        
        const hasSubcategorias = linha.subcategorias && linha.subcategorias.length > 0;
        
        // Verificar se há subcategorias que correspondem à busca
        let matchingSubcategorias = [];
        let hasMatchingSubcategoria = false;
        
        if (hasSubcategorias && searchTerm) {
            matchingSubcategorias = linha.subcategorias.filter(sub => 
                sub.nome.toLowerCase().includes(searchTerm)
            );
            hasMatchingSubcategoria = matchingSubcategorias.length > 0;
        }
        
        // Auto-expandir se houver correspondência ou se não houver busca mas já estava expandido
        const isExpanded = searchTerm ? hasMatchingSubcategoria : dreExpandedRows.has(chave);
        
        // Mostrar categoria pai apenas se:
        // 1. Não há busca ativa, OU
        // 2. Há busca e existe subcategoria correspondente
        const shouldShowParent = !searchTerm || hasMatchingSubcategoria;
        
        if (shouldShowParent) {
            html += `
                <tr class="${rowClass}" style="border-bottom: 1px solid #374151;">
                    <td class="py-2 px-4 ${hasSubcategorias ? 'cursor-pointer hover:bg-gray-700/30' : ''}" 
                        ${hasSubcategorias ? `onclick="toggleDRERow('${chave}')"` : ''}
                        style="user-select: none;">
                        ${hasSubcategorias ? (isExpanded ? '▼' : '▶') : ''} ${linha.nome}
                    </td>
                    <td class="text-right py-2 px-3">${formatCurrency(linha.media_6m)}</td>
                    <td class="text-right py-2 px-3">${formatCurrency(linha.media_3m)}</td>
                    <td class="text-right py-2 px-3">${formatCurrency(linha.valor_anterior)}</td>
                    <td class="text-right py-2 px-3 font-bold">${formatCurrency(linha.valor_atual)}</td>
                    <td class="text-right py-2 px-3 ${varClassVsM3}">${formatPercent(linha.vs_media_3m)}</td>
                    <td class="text-right py-2 px-3 ${varClassMes}">${formatPercent(linha.variacao_mes)}</td>
                </tr>
            `;
        }
        
        // Renderizar subcategorias se expandido (ou se há busca ativa com matches)
        if (hasSubcategorias && isExpanded) {
            const subsToShow = searchTerm ? matchingSubcategorias : linha.subcategorias;
            
            subsToShow.forEach(sub => {
                const subVarClassVsM3 = getVariationClass(sub.vs_media_3m, isReceita);
                const subVarClassMes = getVariationClass(sub.variacao_mes, isReceita);
                
                // Destacar termo de busca no nome da subcategoria
                let displayName = sub.nome;
                if (searchTerm) {
                    const regex = new RegExp(`(${searchTerm})`, 'gi');
                    displayName = displayName.replace(regex, '<mark style="background-color: #fbbf24; color: #000; padding: 2px 4px; border-radius: 2px;">$1</mark>');
                }
                
                html += `
                    <tr class="text-gray-400 text-xs bg-gray-800/30" style="border-bottom: 1px solid #374151;">
                        <td class="py-2 px-4 pl-10">${displayName}</td>
                        <td class="text-right py-2 px-3">${formatCurrency(sub.media_6m)}</td>
                        <td class="text-right py-2 px-3">${formatCurrency(sub.media_3m)}</td>
                        <td class="text-right py-2 px-3">${formatCurrency(sub.valor_anterior)}</td>
                        <td class="text-right py-2 px-3">${formatCurrency(sub.valor_atual)}</td>
                        <td class="text-right py-2 px-3 ${subVarClassVsM3}">${formatPercent(sub.vs_media_3m)}</td>
                        <td class="text-right py-2 px-3 ${subVarClassMes}">${formatPercent(sub.variacao_mes)}</td>
                    </tr>
                `;
            });
        }
    }
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = html;
}

// Função global para toggle de linhas DRE
window.toggleDRERow = (chave) => {
    if (dreExpandedRows.has(chave)) {
        dreExpandedRows.delete(chave);
    } else {
        dreExpandedRows.add(chave);
    }
    
    // Re-renderizar com os dados já carregados
    if (dreLastData) {
        renderDRETable(dreLastData);
    }
};

// Função global para filtrar tabela DRE por busca
window.filterDRETable = (searchValue) => {
    window.dreSearchTerm = searchValue;
    
    // Re-renderizar com os dados já carregados
    if (dreLastData) {
        renderDRETable(dreLastData);
    }
};
</script>