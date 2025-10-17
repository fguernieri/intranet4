<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurar fuso horário do Brasil
date_default_timezone_set('America/Sao_Paulo');

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
require_once __DIR__ . '/supabase_connection.php';

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
    $todos_dados = $supabase->select('freceitawab', [
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
        
        // Se nenhum período foi selecionado, selecionar automaticamente o mais recente
        if (empty($periodo_selecionado) && !empty($periodos_disponiveis)) {
            // Os períodos já vêm ordenados por data_mes.desc, então o primeiro é o mais recente
            $periodo_selecionado = array_keys($periodos_disponiveis)[0];
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
                $dados_receita = $supabase->select('freceitawab', [
                    'select' => '*',
                    'filters' => [
                        'data_mes' => 'eq.' . $data_filtro
                    ],
                    'order' => 'data_mes.desc'
                ]);
                
                // Buscar despesas (para TRIBUTOS)
                $dados_despesa = $supabase->select('fdespesaswab', [
                    'select' => '*',
                    'filters' => [
                        'data_mes' => 'eq.' . $data_filtro
                    ],
                    'order' => 'data_mes.desc'
                ]);
                
                // Buscar detalhes das despesas para drill-down
                $dados_despesa_detalhes = $supabase->select('fdespesaswab_detalhes', [
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
        $resultado = $supabase->select('fmetaswab', [
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

require_once __DIR__ . '/../../sidebar.php';
?>

<div id="receita-content" class="p-6 ml-4">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl text-yellow-400">Acompanhamento financeiro - We Are Bastards</h2>
        <div class="flex items-center gap-2">
            <a href="index.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded transition-colors flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Voltar ao Menu
        </a>
            <!-- Dropdown de seleção de bar -->
            <div class="relative">
                <button id="bar-menu-btn" class="bg-gray-700 text-white px-3 py-2 rounded hover:bg-gray-600 focus:outline-none">Selecionar Bar ▾</button>
                <div id="bar-menu" class="absolute right-0 mt-2 w-48 bg-white rounded shadow-lg hidden z-50">
                    <a href="index2.php" class="block px-4 py-2 text-sm text-gray-800 hover:bg-gray-100">Bar da Fabrica</a>
                </div>
            </div>
            <script>
                document.addEventListener('click', function(e) {
                    const btn = document.getElementById('bar-menu-btn');
                    const menu = document.getElementById('bar-menu');
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
                <select name="periodo" class="w-full px-3 py-2 bg-gray-700 text-gray-300 rounded border border-gray-600 focus:border-yellow-400" required>
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
        <?php if (!empty($dados_receita) || !empty($dados_despesa)): ?>
        <div class="bg-gray-800 rounded-lg p-4">
            <h3 class="text-lg text-gray-300 mb-3">
                Lançamentos financeiros <?= htmlspecialchars($periodos_disponiveis[$periodo_selecionado] ?? $periodo_selecionado) ?>
            </h3>
            <div class="overflow-x-auto">
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
                
                // Função para obter meta da tabela fmetaswab
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
                        
                        $resultado = $supabase->select('fmetaswab', [
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
                
                // Função para obter percentual da meta da tabela fmetaswab
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
                        
                        $resultado = $supabase->select('fmetaswab', [
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
                $total_tributos = array_sum(array_column($tributos, 'total_receita_mes'));
                $total_custo_variavel = array_sum(array_column($custo_variavel, 'total_receita_mes'));
                $total_custo_fixo = array_sum(array_column($custo_fixo, 'total_receita_mes'));
                $total_despesa_fixa = array_sum(array_column($despesa_fixa, 'total_receita_mes'));
                $total_despesa_venda = array_sum(array_column($despesa_venda, 'total_receita_mes'));
                $total_investimento_interno = array_sum(array_column($investimento_interno, 'total_receita_mes'));
                $total_saidas_nao_operacionais = array_sum(array_column($saidas_nao_operacionais, 'total_receita_mes'));
                
                // DEBUG: Mostrar categorias encontradas (remover depois)
                echo "<!-- DEBUG: ";
                echo "Total registros: " . count($dados_receita) . " | ";
                echo "Operacionais: " . count($receitas_operacionais) . " | ";
                echo "Não operacionais: " . count($receitas_nao_operacionais) . " | ";
                echo "Tributos: " . count($tributos) . " | ";
                echo "Custo Variável: " . count($custo_variavel) . " | ";
                echo "Custo Fixo: " . count($custo_fixo) . " | ";
                echo "Despesa Fixa: " . count($despesa_fixa) . " | ";
                echo "Despesa Venda: " . count($despesa_venda) . " | ";
                echo "Investimento Interno: " . count($investimento_interno) . " | ";
                echo "Saídas Não Operacionais: " . count($saidas_nao_operacionais);
                echo " -->";
                ?>
                
                <table class="w-full text-sm text-gray-300">
                    <thead class="bg-gray-700 sticky top-0">
                        <tr>
                            <th class="px-3 py-2 text-left border-b border-gray-600">Descrição</th>
                            <th class="px-3 py-2 text-center border-b border-gray-600">Meta</th>
                            <th class="px-3 py-2 text-right border-b border-gray-600">Valor (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- RECEITA BRUTA - Linha principal expansível -->
                        <?php 
                        $meta_receita_bruta = obterMeta('RECEITA BRUTA');
                        $percentual_receita_bruta = calcularPercentualMeta($total_geral, $meta_receita_bruta);
                        $cor_receita_bruta = obterCorBarra($percentual_receita_bruta, false);
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-green-400" onclick="toggleReceita('receita-bruta')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                RECEITA BRUTA
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <div class="w-full">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-gray-400"><?= number_format($percentual_receita_bruta, 1) ?>%</span>
                                        <span class="text-gray-500">Meta: R$ <?= number_format($meta_receita_bruta, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-2">
                                        <div class="bg-<?= $cor_receita_bruta ?>-500 h-2 rounded-full transition-all duration-300" style="width: <?= min($percentual_receita_bruta, 100) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono">
                                R$ <?= number_format($total_geral, 2, ',', '.') ?>
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Subgrupos da RECEITA BRUTA (logo após a RECEITA BRUTA) -->
                    <tbody class="subcategorias" id="sub-receita-bruta" style="display: none;">
                        <!-- RECEITAS OPERACIONAIS - Subgrupo -->
                        <?php if (!empty($receitas_operacionais)): ?>
                        <?php 
                        $meta_operacional = obterMeta('RECEITAS OPERACIONAIS');
                        $percentual_operacional = calcularPercentualMeta($total_operacional, $meta_operacional);
                        $cor_operacional = obterCorBarra($percentual_operacional, false);
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-medium text-blue-300 text-sm" onclick="toggleReceita('operacional')">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                RECEITAS OPERACIONAIS
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <div class="w-full">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-gray-400"><?= number_format($percentual_operacional, 1) ?>%</span>
                                        <span class="text-gray-500">Meta: R$ <?= number_format($meta_operacional, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-1.5">
                                        <div class="bg-<?= $cor_operacional ?>-500 h-1.5 rounded-full transition-all duration-300" style="width: <?= min($percentual_operacional, 100) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono">
                                R$ <?= number_format($total_operacional, 2, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    
                    <!-- Detalhes das receitas operacionais (logo após RECEITAS OPERACIONAIS) -->
                    <tbody class="subcategorias" id="sub-operacional" style="display: none;">
                        <?php foreach ($receitas_operacionais as $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'RECEITAS OPERACIONAIS');
                        $percentual_individual = calcularPercentualMeta($valor_individual, $meta_individual);
                        $cor_individual = obterCorBarra($percentual_individual, false);
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300">
                            <td class="px-3 py-2 border-b border-gray-700 pl-12">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <?php if ($meta_individual > 0): ?>
                                    <div class="w-full">
                                        <div class="flex items-center justify-between text-xs mb-1">
                                            <span class="text-gray-400 text-xs"><?= number_format($percentual_individual, 1) ?>%</span>
                                            <span class="text-gray-500 text-xs">R$ <?= number_format($meta_individual, 0, ',', '.') ?></span>
                                        </div>
                                        <div class="w-full bg-gray-600 rounded-full h-1">
                                            <div class="bg-<?= $cor_individual ?>-500 h-1 rounded-full transition-all duration-300" style="width: <?= min($percentual_individual, 100) ?>%"></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500">🎯 Aguardando meta</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-green-400">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>

                    <!-- RECEITAS NÃO OPERACIONAIS - Subgrupo -->
                    <tbody class="subcategorias" id="sub-receita-bruta-nao-op" style="display: none;">
                        <?php if (!empty($receitas_nao_operacionais)): ?>
                        <?php 
                        $meta_nao_operacional = obterMeta('RECEITAS NÃO OPERACIONAIS');
                        $percentual_nao_operacional = calcularPercentualMeta($total_nao_operacional, $meta_nao_operacional);
                        $cor_nao_operacional = obterCorBarra($percentual_nao_operacional, false);
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-medium text-blue-300 text-sm" onclick="toggleReceita('nao-operacional')">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                RECEITAS NÃO OPERACIONAIS
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <div class="w-full">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-gray-400"><?= number_format($percentual_nao_operacional, 1) ?>%</span>
                                        <span class="text-gray-500">Meta: R$ <?= number_format($meta_nao_operacional, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-1.5">
                                        <div class="bg-<?= $cor_nao_operacional ?>-500 h-1.5 rounded-full transition-all duration-300" style="width: <?= min($percentual_nao_operacional, 100) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono">
                                R$ <?= number_format($total_nao_operacional, 2, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    
                    <!-- Detalhes das receitas não operacionais (logo após RECEITAS NÃO OPERACIONAIS) -->
                    <tbody class="subcategorias" id="sub-nao-operacional" style="display: none;">
                        <?php foreach ($receitas_nao_operacionais as $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'RECEITAS NÃO OPERACIONAIS');
                        $percentual_individual = calcularPercentualMeta($valor_individual, $meta_individual);
                        $cor_individual = obterCorBarra($percentual_individual, false);
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300">
                            <td class="px-3 py-2 border-b border-gray-700 pl-12">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <?php if ($meta_individual > 0): ?>
                                    <div class="w-full">
                                        <div class="flex items-center justify-between text-xs mb-1">
                                            <span class="text-gray-400 text-xs"><?= number_format($percentual_individual, 1) ?>%</span>
                                            <span class="text-gray-500 text-xs">R$ <?= number_format($meta_individual, 0, ',', '.') ?></span>
                                        </div>
                                        <div class="w-full bg-gray-600 rounded-full h-1">
                                            <div class="bg-<?= $cor_individual ?>-500 h-1 rounded-full transition-all duration-300" style="width: <?= min($percentual_individual, 100) ?>%"></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500">🎯 Aguardando meta</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-green-400">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    
                    <!-- TRIBUTOS - Linha principal (após subgrupos da RECEITA BRUTA) -->
                    <?php if (!empty($tributos)): ?>
                    <tbody>
                        <?php 
                        $meta_tributos = obterMeta('TRIBUTOS');
                        $percentual_tributos = calcularPercentualMeta($total_tributos, $meta_tributos);
                        $cor_tributos = obterCorBarra($percentual_tributos, true); // é despesa
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-orange-400" onclick="toggleReceita('tributos')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) TRIBUTOS
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <div class="w-full">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-gray-400"><?= number_format($percentual_tributos, 1) ?>%</span>
                                        <span class="text-gray-500">Meta: R$ <?= number_format($meta_tributos, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-2">
                                        <div class="bg-<?= $cor_tributos ?>-500 h-2 rounded-full transition-all duration-300" style="width: <?= min($percentual_tributos, 100) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono">
                                R$ <?= number_format($total_tributos, 2, ',', '.') ?>
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes dos TRIBUTOS (ocultos inicialmente) -->
                    <tbody class="subcategorias" id="sub-tributos" style="display: none;">
                        <?php foreach ($tributos as $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'TRIBUTOS');
                        $percentual_individual = calcularPercentualMeta($valor_individual, $meta_individual);
                        $cor_individual = obterCorBarra($percentual_individual, true); // é despesa
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300 cursor-pointer" onclick="toggleDetalhes('tributo-<?= md5($linha['categoria']) ?>')">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <?php if ($meta_individual > 0): ?>
                                    <div class="w-full">
                                        <div class="flex items-center justify-between text-xs mb-1">
                                            <span class="text-gray-400 text-xs"><?= number_format($percentual_individual, 1) ?>%</span>
                                            <span class="text-gray-500 text-xs">R$ <?= number_format($meta_individual, 0, ',', '.') ?></span>
                                        </div>
                                        <div class="w-full bg-gray-600 rounded-full h-1">
                                            <div class="bg-<?= $cor_individual ?>-500 h-1 rounded-full transition-all duration-300" style="width: <?= min($percentual_individual, 100) ?>%"></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500">🎯 Aguardando meta</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-orange-300">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                        </tr>
                        
                        <!-- Detalhes individuais da categoria (ocultos inicialmente) -->
                        <?php if (isset($detalhes_por_categoria[$linha['categoria']])): ?>
                        <tr class="detalhes-categoria" id="det-tributo-<?= md5($linha['categoria']) ?>" style="display: none;">
                            <td colspan="2" class="px-0 py-0 border-b border-gray-700">
                                <div class="bg-gray-900 rounded-lg m-2">
                                    <div class="px-4 py-2 bg-gray-800 rounded-t-lg text-xs text-gray-400 font-semibold">
                                        Lançamentos individuais - <?= htmlspecialchars($linha['categoria']) ?>
                                    </div>
                                    <div class="px-2 py-2">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="text-gray-400">
                                                    <th class="px-2 py-1 text-left">Descrição</th>
                                                    <th class="px-2 py-1 text-right">Valor Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($detalhes_por_categoria[$linha['categoria']] as $detalhe): ?>
                                                <tr class="hover:bg-gray-800 text-gray-300">
                                                    <td class="px-2 py-1 border-b border-gray-800">
                                                        <?= htmlspecialchars($detalhe['descricao'] ?? 'SEM DESCRIÇÃO') ?>
                                                    </td>
                                                    <td class="px-2 py-1 border-b border-gray-800 text-right font-mono text-orange-200">
                                                        R$ <?= number_format(floatval($detalhe['vlr_total'] ?? 0), 2, ',', '.') ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>
                    
                    <!-- RECEITA LÍQUIDA - Cálculo automático (RECEITA BRUTA - TRIBUTOS) -->
                    <?php 
                    $receita_liquida = $total_geral - $total_tributos;
                    $meta_receita_liquida = obterMeta('RECEITA LÍQUIDA');
                    $percentual_receita_liquida = calcularPercentualMeta($receita_liquida, $meta_receita_liquida);
                    $cor_receita_liquida = obterCorBarra($percentual_receita_liquida, false);
                    ?>
                    <tbody>
                        <tr class="hover:bg-gray-700 font-semibold text-green-400">
                            <td class="px-3 py-2 border-b border-gray-700">
                                RECEITA LÍQUIDA
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <div class="w-full">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-gray-400"><?= number_format($percentual_receita_liquida, 1) ?>%</span>
                                        <span class="text-gray-500">Meta: R$ <?= number_format($meta_receita_liquida, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-2">
                                        <div class="bg-<?= $cor_receita_liquida ?>-500 h-2 rounded-full transition-all duration-300" style="width: <?= min($percentual_receita_liquida, 100) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono">
                                R$ <?= number_format($receita_liquida, 2, ',', '.') ?>
                            </td>
                        </tr>
                    </tbody>

                    <!-- CUSTO VARIÁVEL - Linha principal (após RECEITA LÍQUIDA) -->
                    <?php if (!empty($custo_variavel)): ?>
                    <tbody>
                        <?php 
                        $meta_custo_variavel = obterMeta('CUSTO VARIÁVEL');
                        $percentual_custo_variavel = calcularPercentualMeta($total_custo_variavel, $meta_custo_variavel);
                        $cor_custo_variavel = obterCorBarra($percentual_custo_variavel, true); // é despesa
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-orange-400" onclick="toggleReceita('custo-variavel')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) CUSTO VARIÁVEL
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <div class="w-full">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-gray-400"><?= number_format($percentual_custo_variavel, 1) ?>%</span>
                                        <span class="text-gray-500">Meta: R$ <?= number_format($meta_custo_variavel, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-2">
                                        <div class="bg-<?= $cor_custo_variavel ?>-500 h-2 rounded-full transition-all duration-300" style="width: <?= min($percentual_custo_variavel, 100) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono">
                                R$ <?= number_format($total_custo_variavel, 2, ',', '.') ?>
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes dos CUSTOS VARIÁVEIS (ocultos inicialmente) -->
                    <tbody class="subcategorias" id="sub-custo-variavel" style="display: none;">
                        <?php foreach ($custo_variavel as $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'CUSTO VARIÁVEL');
                        $percentual_individual = calcularPercentualMeta($valor_individual, $meta_individual);
                        $cor_individual = obterCorBarra($percentual_individual, true); // é despesa
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300 cursor-pointer" onclick="toggleDetalhes('custo-variavel-<?= md5($linha['categoria']) ?>')">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <?php if ($meta_individual > 0): ?>
                                    <div class="w-full">
                                        <div class="flex items-center justify-between text-xs mb-1">
                                            <span class="text-gray-400 text-xs"><?= number_format($percentual_individual, 1) ?>%</span>
                                            <span class="text-gray-500 text-xs">R$ <?= number_format($meta_individual, 0, ',', '.') ?></span>
                                        </div>
                                        <div class="w-full bg-gray-600 rounded-full h-1">
                                            <div class="bg-<?= $cor_individual ?>-500 h-1 rounded-full transition-all duration-300" style="width: <?= min($percentual_individual, 100) ?>%"></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500">🎯 Aguardando meta</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-orange-300">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                        </tr>
                        
                        <?php if (isset($detalhes_por_categoria[$linha['categoria']])): ?>
                        <tr class="detalhes-categoria" id="det-custo-variavel-<?= md5($linha['categoria']) ?>" style="display: none;">
                            <td colspan="2" class="px-0 py-0 border-b border-gray-700">
                                <div class="bg-gray-900 rounded-lg m-2">
                                    <div class="px-4 py-2 bg-gray-800 rounded-t-lg text-xs text-gray-400 font-semibold">
                                        Lançamentos individuais - <?= htmlspecialchars($linha['categoria']) ?>
                                    </div>
                                    <div class="px-2 py-2">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="text-gray-400">
                                                    <th class="px-2 py-1 text-left">Descrição</th>
                                                    <th class="px-2 py-1 text-right">Valor Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($detalhes_por_categoria[$linha['categoria']] as $detalhe): ?>
                                                <tr class="hover:bg-gray-800 text-gray-300">
                                                    <td class="px-2 py-1 border-b border-gray-800">
                                                        <?= htmlspecialchars($detalhe['descricao'] ?? 'SEM DESCRIÇÃO') ?>
                                                    </td>
                                                    <td class="px-2 py-1 border-b border-gray-800 text-right font-mono text-orange-200">
                                                        R$ <?= number_format(floatval($detalhe['vlr_total'] ?? 0), 2, ',', '.') ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- LUCRO BRUTO - Cálculo automático (RECEITA LÍQUIDA - CUSTO VARIÁVEL) -->
                    <?php 
                    $lucro_bruto = ($total_geral - $total_tributos) - $total_custo_variavel;
                    $meta_lucro_bruto = obterMeta('LUCRO BRUTO');
                    $percentual_lucro_bruto = calcularPercentualMeta($lucro_bruto, $meta_lucro_bruto);
                    $cor_lucro_bruto = obterCorBarra($percentual_lucro_bruto, false);
                    ?>
                    <tbody>
                        <tr class="hover:bg-gray-700 font-semibold text-green-400">
                            <td class="px-3 py-2 border-b border-gray-700">
                                LUCRO BRUTO
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <div class="w-full">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-gray-400"><?= number_format($percentual_lucro_bruto, 1) ?>%</span>
                                        <span class="text-gray-500">Meta: R$ <?= number_format($meta_lucro_bruto, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-2">
                                        <div class="bg-<?= $cor_lucro_bruto ?>-500 h-2 rounded-full transition-all duration-300" style="width: <?= min($percentual_lucro_bruto, 100) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono">
                                R$ <?= number_format($lucro_bruto, 2, ',', '.') ?>
                            </td>
                        </tr>
                    </tbody>

                    <!-- CUSTO FIXO - Linha principal (após LUCRO BRUTO) -->
                    <?php if (!empty($custo_fixo)): ?>
                    <tbody>
                        <?php 
                        $meta_custo_fixo = obterMeta('CUSTO FIXO');
                        $percentual_custo_fixo = calcularPercentualMeta($total_custo_fixo, $meta_custo_fixo);
                        $cor_custo_fixo = obterCorBarra($percentual_custo_fixo, true); // é despesa
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-orange-400" onclick="toggleReceita('custo-fixo')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) CUSTO FIXO
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <div class="w-full">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-gray-400"><?= number_format($percentual_custo_fixo, 1) ?>%</span>
                                        <span class="text-gray-500">Meta: R$ <?= number_format($meta_custo_fixo, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-2">
                                        <div class="bg-<?= $cor_custo_fixo ?>-500 h-2 rounded-full transition-all duration-300" style="width: <?= min($percentual_custo_fixo, 100) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono">
                                R$ <?= number_format($total_custo_fixo, 2, ',', '.') ?>
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes dos CUSTOS FIXOS (ocultos inicialmente) -->
                    <tbody class="subcategorias" id="sub-custo-fixo" style="display: none;">
                        <?php foreach ($custo_fixo as $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'CUSTO FIXO');
                        $percentual_individual = calcularPercentualMeta($valor_individual, $meta_individual);
                        $cor_individual = obterCorBarra($percentual_individual, true); // é despesa
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300 cursor-pointer" onclick="toggleDetalhes('custo-fixo-<?= md5($linha['categoria']) ?>')">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <?php if ($meta_individual > 0): ?>
                                    <div class="w-full">
                                        <div class="flex items-center justify-between text-xs mb-1">
                                            <span class="text-gray-400 text-xs"><?= number_format($percentual_individual, 1) ?>%</span>
                                            <span class="text-gray-500 text-xs">R$ <?= number_format($meta_individual, 0, ',', '.') ?></span>
                                        </div>
                                        <div class="w-full bg-gray-600 rounded-full h-1">
                                            <div class="bg-<?= $cor_individual ?>-500 h-1 rounded-full transition-all duration-300" style="width: <?= min($percentual_individual, 100) ?>%"></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500">🎯 Aguardando meta</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-orange-300">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                        </tr>
                        
                        <?php if (isset($detalhes_por_categoria[$linha['categoria']])): ?>
                        <tr class="detalhes-categoria" id="det-custo-fixo-<?= md5($linha['categoria']) ?>" style="display: none;">
                            <td colspan="2" class="px-0 py-0 border-b border-gray-700">
                                <div class="bg-gray-900 rounded-lg m-2">
                                    <div class="px-4 py-2 bg-gray-800 rounded-t-lg text-xs text-gray-400 font-semibold">
                                        Lançamentos individuais - <?= htmlspecialchars($linha['categoria']) ?>
                                    </div>
                                    <div class="px-2 py-2">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="text-gray-400">
                                                    <th class="px-2 py-1 text-left">Descrição</th>
                                                    <th class="px-2 py-1 text-right">Valor Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($detalhes_por_categoria[$linha['categoria']] as $detalhe): ?>
                                                <tr class="hover:bg-gray-800 text-gray-300">
                                                    <td class="px-2 py-1 border-b border-gray-800">
                                                        <?= htmlspecialchars($detalhe['descricao'] ?? 'SEM DESCRIÇÃO') ?>
                                                    </td>
                                                    <td class="px-2 py-1 border-b border-gray-800 text-right font-mono text-orange-200">
                                                        R$ <?= number_format(floatval($detalhe['vlr_total'] ?? 0), 2, ',', '.') ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- DESPESA FIXA - Linha principal (após CUSTO FIXO) -->
                    <?php if (!empty($despesa_fixa)): ?>
                    <tbody>
                        <?php 
                        $meta_despesa_fixa = obterMeta('DESPESA FIXA');
                        $percentual_despesa_fixa = calcularPercentualMeta($total_despesa_fixa, $meta_despesa_fixa);
                        $cor_despesa_fixa = obterCorBarra($percentual_despesa_fixa, true); // é despesa
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-orange-400" onclick="toggleReceita('despesa-fixa')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) DESPESA FIXA
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <div class="w-full">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-gray-400"><?= number_format($percentual_despesa_fixa, 1) ?>%</span>
                                        <span class="text-gray-500">Meta: R$ <?= number_format($meta_despesa_fixa, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-2">
                                        <div class="bg-<?= $cor_despesa_fixa ?>-500 h-2 rounded-full transition-all duration-300" style="width: <?= min($percentual_despesa_fixa, 100) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono">
                                R$ <?= number_format($total_despesa_fixa, 2, ',', '.') ?>
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes das DESPESAS FIXAS (ocultos inicialmente) -->
                    <tbody class="subcategorias" id="sub-despesa-fixa" style="display: none;">
                        <?php foreach ($despesa_fixa as $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'DESPESA FIXA');
                        $percentual_individual = calcularPercentualMeta($valor_individual, $meta_individual);
                        $cor_individual = obterCorBarra($percentual_individual, true); // é despesa
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300 cursor-pointer" onclick="toggleDetalhes('despesa-fixa-<?= md5($linha['categoria']) ?>')">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <?php if ($meta_individual > 0): ?>
                                    <div class="w-full">
                                        <div class="flex items-center justify-between text-xs mb-1">
                                            <span class="text-gray-400 text-xs"><?= number_format($percentual_individual, 1) ?>%</span>
                                            <span class="text-gray-500 text-xs">R$ <?= number_format($meta_individual, 0, ',', '.') ?></span>
                                        </div>
                                        <div class="w-full bg-gray-600 rounded-full h-1">
                                            <div class="bg-<?= $cor_individual ?>-500 h-1 rounded-full transition-all duration-300" style="width: <?= min($percentual_individual, 100) ?>%"></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500">🎯 Aguardando meta</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-orange-300">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                        </tr>
                        
                        <?php if (isset($detalhes_por_categoria[$linha['categoria']])): ?>
                        <tr class="detalhes-categoria" id="det-despesa-fixa-<?= md5($linha['categoria']) ?>" style="display: none;">
                            <td colspan="2" class="px-0 py-0 border-b border-gray-700">
                                <div class="bg-gray-900 rounded-lg m-2">
                                    <div class="px-4 py-2 bg-gray-800 rounded-t-lg text-xs text-gray-400 font-semibold">
                                        Lançamentos individuais - <?= htmlspecialchars($linha['categoria']) ?>
                                    </div>
                                    <div class="px-2 py-2">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="text-gray-400">
                                                    <th class="px-2 py-1 text-left">Descrição</th>
                                                    <th class="px-2 py-1 text-right">Valor Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($detalhes_por_categoria[$linha['categoria']] as $detalhe): ?>
                                                <tr class="hover:bg-gray-800 text-gray-300">
                                                    <td class="px-2 py-1 border-b border-gray-800">
                                                        <?= htmlspecialchars($detalhe['descricao'] ?? 'SEM DESCRIÇÃO') ?>
                                                    </td>
                                                    <td class="px-2 py-1 border-b border-gray-800 text-right font-mono text-orange-200">
                                                        R$ <?= number_format(floatval($detalhe['vlr_total'] ?? 0), 2, ',', '.') ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- DESPESA DE VENDA - Linha principal (após DESPESA FIXA) -->
                    <?php if (!empty($despesa_venda)): ?>
                    <tbody>
                        <?php 
                        $meta_despesa_venda = obterMeta('DESPESAS DE VENDA');
                        $percentual_despesa_venda = calcularPercentualMeta($total_despesa_venda, $meta_despesa_venda);
                        $cor_despesa_venda = obterCorBarra($percentual_despesa_venda, true); // é despesa
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-orange-400" onclick="toggleReceita('despesa-venda')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) DESPESAS DE VENDA
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <div class="w-full">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-gray-400"><?= number_format($percentual_despesa_venda, 1) ?>%</span>
                                        <span class="text-gray-500">Meta: R$ <?= number_format($meta_despesa_venda, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-2">
                                        <div class="bg-<?= $cor_despesa_venda ?>-500 h-2 rounded-full transition-all duration-300" style="width: <?= min($percentual_despesa_venda, 100) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono">
                                R$ <?= number_format($total_despesa_venda, 2, ',', '.') ?>
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes das DESPESAS DE VENDA (ocultos inicialmente) -->
                    <tbody class="subcategorias" id="sub-despesa-venda" style="display: none;">
                        <?php foreach ($despesa_venda as $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'DESPESAS DE VENDA');
                        $percentual_individual = calcularPercentualMeta($valor_individual, $meta_individual);
                        $cor_individual = obterCorBarra($percentual_individual, true); // é despesa
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300 cursor-pointer" onclick="toggleDetalhes('despesa-venda-<?= md5($linha['categoria']) ?>')">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <?php if ($meta_individual > 0): ?>
                                    <div class="w-full">
                                        <div class="flex items-center justify-between text-xs mb-1">
                                            <span class="text-gray-400 text-xs"><?= number_format($percentual_individual, 1) ?>%</span>
                                            <span class="text-gray-500 text-xs">R$ <?= number_format($meta_individual, 0, ',', '.') ?></span>
                                        </div>
                                        <div class="w-full bg-gray-600 rounded-full h-1">
                                            <div class="bg-<?= $cor_individual ?>-500 h-1 rounded-full transition-all duration-300" style="width: <?= min($percentual_individual, 100) ?>%"></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500">🎯 Aguardando meta</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-orange-300">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                        </tr>
                        
                        <?php if (isset($detalhes_por_categoria[$linha['categoria']])): ?>
                        <tr class="detalhes-categoria" id="det-despesa-venda-<?= md5($linha['categoria']) ?>" style="display: none;">
                            <td colspan="2" class="px-0 py-0 border-b border-gray-700">
                                <div class="bg-gray-900 rounded-lg m-2">
                                    <div class="px-4 py-2 bg-gray-800 rounded-t-lg text-xs text-gray-400 font-semibold">
                                        Lançamentos individuais - <?= htmlspecialchars($linha['categoria']) ?>
                                    </div>
                                    <div class="px-2 py-2">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="text-gray-400">
                                                    <th class="px-2 py-1 text-left">Descrição</th>
                                                    <th class="px-2 py-1 text-right">Valor Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($detalhes_por_categoria[$linha['categoria']] as $detalhe): ?>
                                                <tr class="hover:bg-gray-800 text-gray-300">
                                                    <td class="px-2 py-1 border-b border-gray-800">
                                                        <?= htmlspecialchars($detalhe['descricao'] ?? 'SEM DESCRIÇÃO') ?>
                                                    </td>
                                                    <td class="px-2 py-1 border-b border-gray-800 text-right font-mono text-orange-200">
                                                        R$ <?= number_format(floatval($detalhe['vlr_total'] ?? 0), 2, ',', '.') ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- LUCRO LÍQUIDO - Cálculo final (LUCRO BRUTO - CUSTO FIXO - DESPESA FIXA - DESPESAS DE VENDA) -->
                    <?php 
                    $lucro_liquido = (($total_geral - $total_tributos) - $total_custo_variavel) - $total_custo_fixo - $total_despesa_fixa - $total_despesa_venda;
                    $meta_lucro_liquido = obterMeta('LUCRO LÍQUIDO');
                    $percentual_lucro_liquido = calcularPercentualMeta($lucro_liquido, $meta_lucro_liquido);
                    $cor_lucro_liquido = obterCorBarra($percentual_lucro_liquido, false);
                    ?>
                    <tbody>
                        <tr class="hover:bg-gray-700 font-bold text-green-400 bg-green-900 bg-opacity-20">
                            <td class="px-3 py-3 border-b-2 border-green-600 font-bold">
                                LUCRO LÍQUIDO
                            </td>
                            <td class="px-3 py-3 border-b-2 border-green-600 text-center">
                                <div class="w-full">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-gray-400 font-semibold"><?= number_format($percentual_lucro_liquido, 1) ?>%</span>
                                        <span class="text-gray-500 font-semibold">Meta: R$ <?= number_format($meta_lucro_liquido, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-2.5">
                                        <div class="bg-<?= $cor_lucro_liquido ?>-500 h-2.5 rounded-full transition-all duration-300" style="width: <?= min($percentual_lucro_liquido, 100) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 border-b-2 border-green-600 text-right font-mono font-bold">
                                R$ <?= number_format($lucro_liquido, 2, ',', '.') ?>
                            </td>
                        </tr>
                    </tbody>

                    <!-- INVESTIMENTO INTERNO - Linha principal (após LUCRO LÍQUIDO) -->
                    <?php if (!empty($investimento_interno)): ?>
                    <tbody>
                        <?php 
                        $meta_investimento_interno = obterMeta('INVESTIMENTO INTERNO');
                        $percentual_investimento_interno = calcularPercentualMeta($total_investimento_interno, $meta_investimento_interno);
                        $cor_investimento_interno = obterCorBarra($percentual_investimento_interno, true); // é despesa
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-blue-400" onclick="toggleReceita('investimento-interno')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) INVESTIMENTO INTERNO
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <div class="w-full">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-gray-400"><?= number_format($percentual_investimento_interno, 1) ?>%</span>
                                        <span class="text-gray-500">Meta: R$ <?= number_format($meta_investimento_interno, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-2">
                                        <div class="bg-<?= $cor_investimento_interno ?>-500 h-2 rounded-full transition-all duration-300" style="width: <?= min($percentual_investimento_interno, 100) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono">
                                R$ <?= number_format($total_investimento_interno, 2, ',', '.') ?>
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes dos INVESTIMENTOS INTERNOS (ocultos inicialmente) -->
                    <tbody class="subcategorias" id="sub-investimento-interno" style="display: none;">
                        <?php foreach ($investimento_interno as $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'INVESTIMENTO INTERNO');
                        $percentual_individual = calcularPercentualMeta($valor_individual, $meta_individual);
                        $cor_individual = obterCorBarra($percentual_individual, true); // é despesa/investimento
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300 cursor-pointer" onclick="toggleDetalhes('investimento-interno-<?= md5($linha['categoria']) ?>')">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <?php if ($meta_individual > 0): ?>
                                    <div class="w-full">
                                        <div class="flex items-center justify-between text-xs mb-1">
                                            <span class="text-gray-400 text-xs"><?= number_format($percentual_individual, 1) ?>%</span>
                                            <span class="text-gray-500 text-xs">R$ <?= number_format($meta_individual, 0, ',', '.') ?></span>
                                        </div>
                                        <div class="w-full bg-gray-600 rounded-full h-1">
                                            <div class="bg-<?= $cor_individual ?>-500 h-1 rounded-full transition-all duration-300" style="width: <?= min($percentual_individual, 100) ?>%"></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500">🎯 Aguardando meta</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-blue-300">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                        </tr>
                        
                        <?php if (isset($detalhes_por_categoria[$linha['categoria']])): ?>
                        <tr class="detalhes-categoria" id="det-investimento-interno-<?= md5($linha['categoria']) ?>" style="display: none;">
                            <td colspan="2" class="px-0 py-0 border-b border-gray-700">
                                <div class="bg-gray-900 rounded-lg m-2">
                                    <div class="px-4 py-2 bg-gray-800 rounded-t-lg text-xs text-gray-400 font-semibold">
                                        Lançamentos individuais - <?= htmlspecialchars($linha['categoria']) ?>
                                    </div>
                                    <div class="px-2 py-2">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="text-gray-400">
                                                    <th class="px-2 py-1 text-left">Descrição</th>
                                                    <th class="px-2 py-1 text-right">Valor Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($detalhes_por_categoria[$linha['categoria']] as $detalhe): ?>
                                                <tr class="hover:bg-gray-800 text-gray-300">
                                                    <td class="px-2 py-1 border-b border-gray-800">
                                                        <?= htmlspecialchars($detalhe['descricao'] ?? 'SEM DESCRIÇÃO') ?>
                                                    </td>
                                                    <td class="px-2 py-1 border-b border-gray-800 text-right font-mono text-blue-200">
                                                        R$ <?= number_format(floatval($detalhe['vlr_total'] ?? 0), 2, ',', '.') ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- SAÍDAS NÃO OPERACIONAIS - Linha principal (após INVESTIMENTO INTERNO) -->
                    <?php if (!empty($saidas_nao_operacionais)): ?>
                    <tbody>
                        <?php 
                        $meta_saidas_nao_operacionais = obterMeta('SAÍDAS NÃO OPERACIONAIS');
                        $percentual_saidas_nao_operacionais = calcularPercentualMeta($total_saidas_nao_operacionais, $meta_saidas_nao_operacionais);
                        $cor_saidas_nao_operacionais = obterCorBarra($percentual_saidas_nao_operacionais, true); // é despesa
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-red-400" onclick="toggleReceita('saidas-nao-operacionais')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) SAÍDAS NÃO OPERACIONAIS
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <div class="w-full">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-gray-400"><?= number_format($percentual_saidas_nao_operacionais, 1) ?>%</span>
                                        <span class="text-gray-500">Meta: R$ <?= number_format($meta_saidas_nao_operacionais, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-2">
                                        <div class="bg-<?= $cor_saidas_nao_operacionais ?>-500 h-2 rounded-full transition-all duration-300" style="width: <?= min($percentual_saidas_nao_operacionais, 100) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono">
                                R$ <?= number_format($total_saidas_nao_operacionais, 2, ',', '.') ?>
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes das SAÍDAS NÃO OPERACIONAIS (ocultos inicialmente) -->
                    <tbody class="subcategorias" id="sub-saidas-nao-operacionais" style="display: none;">
                        <?php foreach ($saidas_nao_operacionais as $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'SAÍDAS NÃO OPERACIONAIS');
                        $percentual_individual = calcularPercentualMeta($valor_individual, $meta_individual);
                        $cor_individual = obterCorBarra($percentual_individual, true); // é saída/despesa
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300 cursor-pointer" onclick="toggleDetalhes('saidas-nao-operacionais-<?= md5($linha['categoria']) ?>')">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <?php if ($meta_individual > 0): ?>
                                    <div class="w-full">
                                        <div class="flex items-center justify-between text-xs mb-1">
                                            <span class="text-gray-400 text-xs"><?= number_format($percentual_individual, 1) ?>%</span>
                                            <span class="text-gray-500 text-xs">R$ <?= number_format($meta_individual, 0, ',', '.') ?></span>
                                        </div>
                                        <div class="w-full bg-gray-600 rounded-full h-1">
                                            <div class="bg-<?= $cor_individual ?>-500 h-1 rounded-full transition-all duration-300" style="width: <?= min($percentual_individual, 100) ?>%"></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500">🎯 Aguardando meta</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-red-300">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                        </tr>
                        
                        <?php if (isset($detalhes_por_categoria[$linha['categoria']])): ?>
                        <tr class="detalhes-categoria" id="det-saidas-nao-operacionais-<?= md5($linha['categoria']) ?>" style="display: none;">
                            <td colspan="2" class="px-0 py-0 border-b border-gray-700">
                                <div class="bg-gray-900 rounded-lg m-2">
                                    <div class="px-4 py-2 bg-gray-800 rounded-t-lg text-xs text-gray-400 font-semibold">
                                        Lançamentos individuais - <?= htmlspecialchars($linha['categoria']) ?>
                                    </div>
                                    <div class="px-2 py-2">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="text-gray-400">
                                                    <th class="px-2 py-1 text-left">Descrição</th>
                                                    <th class="px-2 py-1 text-right">Valor Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($detalhes_por_categoria[$linha['categoria']] as $detalhe): ?>
                                                <tr class="hover:bg-gray-800 text-gray-300">
                                                    <td class="px-2 py-1 border-b border-gray-800">
                                                        <?= htmlspecialchars($detalhe['descricao'] ?? 'SEM DESCRIÇÃO') ?>
                                                    </td>
                                                    <td class="px-2 py-1 border-b border-gray-800 text-right font-mono text-red-200">
                                                        R$ <?= number_format(floatval($detalhe['vlr_total'] ?? 0), 2, ',', '.') ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- IMPACTO CAIXA - Cálculo final (LUCRO LÍQUIDO - INVESTIMENTO INTERNO - SAÍDAS NÃO OPERACIONAIS) -->
                    <?php 
                    $impacto_caixa = (((($total_geral - $total_tributos) - $total_custo_variavel) - $total_custo_fixo - $total_despesa_fixa - $total_despesa_venda) - $total_investimento_interno - $total_saidas_nao_operacionais);
                    $cor_impacto = $impacto_caixa >= 0 ? 'green' : 'red';
                    ?>
                    <?php 
                    $meta_impacto_caixa = obterMeta('IMPACTO CAIXA');
                    $percentual_impacto_caixa = calcularPercentualMeta($impacto_caixa, $meta_impacto_caixa);
                    $cor_impacto_barra = obterCorBarra($percentual_impacto_caixa, false);
                    ?>
                    <tbody>
                        <tr class="hover:bg-gray-700 font-bold text-<?= $cor_impacto ?>-400 bg-<?= $cor_impacto ?>-900 bg-opacity-20">
                            <td class="px-3 py-3 border-b-2 border-<?= $cor_impacto ?>-600 font-bold">
                                (=) IMPACTO CAIXA
                            </td>
                            <td class="px-3 py-3 border-b-2 border-<?= $cor_impacto ?>-600 text-center">
                                <div class="w-full">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-gray-400 font-semibold"><?= number_format($percentual_impacto_caixa, 1) ?>%</span>
                                        <span class="text-gray-500 font-semibold">Meta: R$ <?= number_format($meta_impacto_caixa, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-2.5">
                                        <div class="bg-<?= $cor_impacto_barra ?>-500 h-2.5 rounded-full transition-all duration-300" style="width: <?= min($percentual_impacto_caixa, 100) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 border-b-2 border-<?= $cor_impacto ?>-600 text-right font-mono font-bold">
                                R$ <?= number_format($impacto_caixa, 2, ',', '.') ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
            <div class="bg-gray-800 rounded-lg p-6 text-center">
                <p class="text-gray-400 text-lg mb-2">📊 Nenhum dado encontrado</p>
                <p class="text-gray-500">
                    Não há dados de receita para o período selecionado: <?= htmlspecialchars($periodos_disponiveis[$periodo_selecionado] ?? $periodo_selecionado) ?>
                </p>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="bg-gray-800 rounded-lg p-6 text-center">
            <p class="text-gray-400 text-lg mb-2">📋 Selecione um período</p>
            <p class="text-gray-500">
                Escolha um período no filtro acima para visualizar os dados.
            </p>
            <?php if (!empty($periodos_disponiveis)): ?>
                <p class="text-gray-500 mt-2">
                    Períodos disponíveis: <?= count($periodos_disponiveis) ?> meses com dados
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleDetalhes(detalheId) {
    var detalhes = document.getElementById('det-' + detalheId);
    if (detalhes) {
        if (detalhes.style.display === 'none' || detalhes.style.display === '') {
            detalhes.style.display = 'table-row';
        } else {
            detalhes.style.display = 'none';
        }
    }
}

function toggleReceita(categoriaId) {
    var subcategorias = document.getElementById('sub-' + categoriaId);
    
    // Caso especial para RECEITA BRUTA - mostra os subgrupos principais
    if (categoriaId === 'receita-bruta') {
        var subReceitaBruta = document.getElementById('sub-receita-bruta');
        var subReceitaBrutaNaoOp = document.getElementById('sub-receita-bruta-nao-op');
        
        if (subReceitaBruta && subReceitaBrutaNaoOp) {
            if (subReceitaBruta.style.display === 'none' || subReceitaBruta.style.display === '') {
                subReceitaBruta.style.display = 'table-row-group';
                subReceitaBrutaNaoOp.style.display = 'table-row-group';
            } else {
                subReceitaBruta.style.display = 'none';
                subReceitaBrutaNaoOp.style.display = 'none';
                // Esconder também os detalhes quando colapsar a RECEITA BRUTA
                document.getElementById('sub-operacional').style.display = 'none';
                document.getElementById('sub-nao-operacional').style.display = 'none';
            }
        }
    } 
    // Para outros casos (operacional, nao-operacional, tributos, custo-variavel, custo-fixo, despesa-fixa, despesa-venda, investimento-interno, saidas-nao-operacionais)
    else if (subcategorias) {
        if (subcategorias.style.display === 'none' || subcategorias.style.display === '') {
            subcategorias.style.display = 'table-row-group';
        } else {
            subcategorias.style.display = 'none';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Configurar o background do conteúdo principal se existir
    const content = document.getElementById('content');
    if (content) {
        content.style.background = '#111827';
        content.style.paddingLeft = '2rem';
    }
    
    // Verificar se o receita-content precisa ser movido
    const receitaContent = document.getElementById('receita-content');
    const content2 = document.getElementById('content');
    
    if (receitaContent && content2 && !content2.contains(receitaContent)) {
        content2.appendChild(receitaContent);
    }
});
</script>