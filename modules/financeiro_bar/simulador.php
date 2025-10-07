<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
            }
        }
    } catch (Exception $e) {
        $periodos_disponiveis = [];
        $dados_receita = [];
        $dados_despesa = [];
    }
}

require_once __DIR__ . '/../../sidebar.php';
?>

<div id="simulador-content" class="p-6 ml-4">
    <h2 class="text-xl text-blue-400 mb-4">Simulador Financeiro - Bar da Fabrica</h2>
    
    <!-- Filtro de Período -->
    <div class="bg-gray-800 rounded-lg p-4 mb-6">
        <h3 class="text-lg text-gray-300 mb-3">Selecionar Período Base</h3>
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-64">
                <label class="block text-sm text-gray-400 mb-1">Período (Ano/Mês):</label>
                <select name="periodo" class="w-full px-3 py-2 bg-gray-700 text-gray-300 rounded border border-gray-600 focus:border-blue-400" required>
                    <option value="">Selecione um período...</option>
                    <?php foreach ($periodos_disponiveis as $valor => $display): ?>
                        <option value="<?= htmlspecialchars($valor) ?>" <?= $periodo_selecionado === $valor ? 'selected' : '' ?>>
                            <?= htmlspecialchars($display) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded transition-colors">
                    Carregar Base
                </button>
                <a href="?" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded transition-colors">
                    Limpar
                </a>
            </div>
        </form>
        
        <?php if ($periodo_selecionado): ?>
        <div class="mt-3 text-sm text-gray-400">
            📅 Base de dados: <?= htmlspecialchars($periodos_disponiveis[$periodo_selecionado] ?? $periodo_selecionado) ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($periodo_selecionado): ?>
        <?php if (!empty($dados_receita) || !empty($dados_despesa)): ?>
        <div class="bg-gray-800 rounded-lg p-4">
            <h3 class="text-lg text-gray-300 mb-3">
                Simulação Financeira (Base: <?= htmlspecialchars($periodos_disponiveis[$periodo_selecionado] ?? $periodo_selecionado) ?>)
            </h3>
            
            <!-- Botões de ação -->
            <div class="mb-4 flex gap-2">
                <button onclick="resetarSimulacao()" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded transition-colors text-sm">
                    🔄 Restaurar Valores Base
                </button>
                <button onclick="calcularPontoEquilibrio()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded transition-colors text-sm">
                    ⚖️ Calcular Ponto de Equilíbrio
                </button>
                <button onclick="abrirModalMetas()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded transition-colors text-sm">
                    🎯 Salvar Metas
                </button>
                <button onclick="salvarSimulacao()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded transition-colors text-sm">
                    💾 Salvar Simulação
                </button>
            </div>
            
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
                
                // Função para obter meta da tabela fmetastap
                function obterMeta($categoria, $categoria_pai = null) {
                    global $supabase;
                    
                    if (!$supabase) {
                        return 0; // Se conexão não existe, retorna 0
                    }
                    
                    $categoria_upper = strtoupper(trim($categoria));
                    
                    try {
                        $resultado = null;
                        
                        // Caso 1: Buscar subcategoria com categoria pai
                        if ($categoria_pai) {
                            $categoria_pai_upper = strtoupper(trim($categoria_pai));
                            
                            $resultado = $supabase->select('fmetastap', [
                                'select' => 'META',
                                'filters' => [
                                    'CATEGORIA' => "eq.$categoria_pai_upper",
                                    'SUBCATEGORIA' => "eq.$categoria_upper"
                                ],
                                'limit' => 1
                            ]);
                        } 
                        // Caso 2: Buscar categoria principal (sem categoria pai)
                        else {
                            // Primeiro tenta buscar como categoria principal
                            $resultado = $supabase->select('fmetastap', [
                                'select' => 'META',
                                'filters' => [
                                    'CATEGORIA' => "eq.$categoria_upper"
                                ],
                                'limit' => 1
                            ]);
                            
                            // Se não encontrou, tenta buscar como subcategoria
                            if (empty($resultado)) {
                                $resultado = $supabase->select('fmetastap', [
                                    'select' => 'META',
                                    'filters' => [
                                        'SUBCATEGORIA' => "eq.$categoria_upper"
                                    ],
                                    'limit' => 1
                                ]);
                            }
                        }
                        
                        // Verifica se encontrou resultado válido
                        if (!empty($resultado) && isset($resultado[0]['META']) && is_numeric($resultado[0]['META'])) {
                            return floatval($resultado[0]['META']);
                        }
                        
                        // Meta não encontrada, retorna 0
                        return 0;
                        
                    } catch (Exception $e) {
                        error_log("Erro ao buscar meta para '$categoria' (pai: '$categoria_pai'): " . $e->getMessage());
                        return 0; // Em caso de erro, sempre retorna 0
                    }
                }
                
                // Categorias não operacionais (apenas repasses)
                $categorias_nao_operacionais = [
                    'ENTRADA DE REPASSE DE SALARIOS',
                    'ENTRADA DE REPASSE EXTRA DE SALARIOS', 
                    'ENTRADA DE REPASSE OUTROS'
                ];
                
                // Processar RECEITAS - VENDAS são operacionais, REPASSES são não operacionais
                foreach ($dados_receita as $linha) {
                    $categoria = trim(strtoupper($linha['categoria'] ?? ''));
                    $categoria_pai = trim(strtoupper($linha['categoria_pai'] ?? ''));
                    $valor = floatval($linha['total_receita_mes'] ?? 0);
                    $total_geral += $valor;
                    
                    // Verificar se é categoria não operacional (apenas repasses)
                    $eh_nao_operacional = false;
                    foreach ($categorias_nao_operacionais as $cat_nao_op) {
                        if (strpos($categoria, trim(strtoupper($cat_nao_op))) !== false || 
                            trim(strtoupper($cat_nao_op)) === $categoria) {
                            $eh_nao_operacional = true;
                            break;
                        }
                    }
                    
                    // Debug: mostrar como cada categoria está sendo classificada
                    echo "<!-- DEBUG: Categoria: '$categoria' | Pai: '$categoria_pai' | Não Op: " . ($eh_nao_operacional ? 'SIM' : 'NÃO') . " -->";
                    
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
                
                // Debug: mostrar contadores e alguns exemplos
                echo "<!-- DEBUG CONTADORES: ";
                echo "Total registros: " . count($dados_receita) . " | ";
                echo "Operacionais: " . count($receitas_operacionais) . " | ";
                echo "Não operacionais: " . count($receitas_nao_operacionais) . " | ";
                echo "Total Operacional: R$ " . number_format($total_operacional, 2) . " | ";
                echo "Total Não Operacional: R$ " . number_format($total_nao_operacional, 2);
                echo " -->";
                ?>
                

                
                <?php
                
                if (!empty($receitas_nao_operacionais)) {
                    echo "<!-- DEBUG NÃO OPERACIONAIS: ";
                    foreach ($receitas_nao_operacionais as $item) {
                        echo "'" . $item['categoria'] . "' (R$ " . number_format($item['total_receita_mes'], 2) . ") | ";
                    }
                    echo " -->";
                } else {
                    echo "<!-- DEBUG: NENHUMA RECEITA NÃO OPERACIONAL ENCONTRADA! -->";
                }
                ?>
                
                <table class="w-full text-sm text-gray-300" id="tabelaSimulador">
                    <thead class="bg-gray-700 sticky top-0">
                        <tr>
                            <th class="px-3 py-2 text-left border-b border-gray-600">Descrição</th>
                            <th class="px-3 py-2 text-center border-b border-gray-600">Meta</th>
                            <th class="px-3 py-2 text-right border-b border-gray-600">Valor Base (R$)</th>
                            <th class="px-3 py-2 text-right border-b border-gray-600 bg-blue-800">Valor Simulador (R$)</th>
                            <th class="px-3 py-2 text-right border-b border-gray-600 bg-blue-800">% sobre Faturamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- RECEITA BRUTA - Linha principal expansível -->
                        <?php 
                        $meta_receita_bruta = obterMeta('RECEITA BRUTA');
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-green-400" onclick="toggleReceita('receita-bruta')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                RECEITA BRUTA
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_receita_bruta, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-receita-bruta">
                                R$ <?= number_format($total_geral, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-receita-bruta">
                                R$ <?= number_format($total_geral, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-receita-bruta">
                                100.00%
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Subgrupos da RECEITA BRUTA -->
                    <tbody class="subcategorias" id="sub-receita-bruta" style="display: none;">
                        <!-- RECEITAS OPERACIONAIS - Subgrupo -->
                        <?php if (!empty($receitas_operacionais)): ?>
                        <?php 
                        $meta_operacional = obterMeta('RECEITAS OPERACIONAIS');
                        ?>
                        <tr class="hover:bg-gray-700 font-medium text-blue-300 text-sm">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                RECEITAS OPERACIONAIS
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_operacional, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-operacional">
                                R$ <?= number_format($total_operacional, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="text" 
                                       class="bg-transparent text-green-400 text-right w-full border-0 outline-0 valor-simulador"
                                       id="valor-sim-operacional"
                                       data-categoria="RECEITAS OPERACIONAIS"
                                       data-tipo="receita-operacional"
                                       data-valor-base="<?= $total_operacional ?>"
                                       value="<?= number_format($total_operacional, 2, ',', '.') ?>"
                                       onchange="atualizarCalculos()"
                                       onblur="formatarCampoMoeda(this)"
                                       onfocus="removerFormatacao(this)">
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-operacional">
                                <?= number_format(($total_operacional / $total_geral) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                        <?php endif; ?>
                        
                        <!-- RECEITAS NÃO OPERACIONAIS - Subgrupo (dentro da RECEITA BRUTA) -->
                        <?php if (!empty($receitas_nao_operacionais)): ?>
                        <?php 
                        $meta_nao_operacional = obterMeta('RECEITAS NÃO OPERACIONAIS');
                        ?>
                        <tr class="hover:bg-gray-700 font-medium text-blue-300 text-sm">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                RECEITAS NÃO OPERACIONAIS
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_nao_operacional, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-nao-operacional">
                                R$ <?= number_format($total_nao_operacional, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="text" 
                                       class="bg-transparent text-green-400 text-right w-full border-0 outline-0 valor-simulador"
                                       id="valor-sim-nao-operacional"
                                       data-categoria="RECEITAS NÃO OPERACIONAIS"
                                       data-tipo="receita-nao-operacional"
                                       data-valor-base="<?= $total_nao_operacional ?>"
                                       value="<?= number_format($total_nao_operacional, 2, ',', '.') ?>"
                                       onchange="atualizarCalculos()"
                                       onblur="formatarCampoMoeda(this)"
                                       onfocus="removerFormatacao(this)">
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-nao-operacional">
                                <?= number_format(($total_nao_operacional / $total_geral) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    

                    
                    <!-- TRIBUTOS - Linha principal -->
                    <?php if (!empty($tributos)): ?>
                    <tbody>
                        <?php 
                        $meta_tributos = obterMeta('TRIBUTOS');
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-orange-400" onclick="toggleReceita('tributos')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) TRIBUTOS
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_tributos, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-tributos">
                                R$ <?= number_format($total_tributos, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-gray-600" id="valor-sim-tributos">
                                R$ <?= number_format($total_tributos, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-tributos">
                                <?= number_format(($total_tributos / $total_geral) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes dos TRIBUTOS -->
                    <tbody class="subcategorias" id="sub-tributos" style="display: none;">
                        <?php foreach ($tributos as $index => $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'TRIBUTOS');
                        $categoria_id = 'tributos-' . $index;
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <?php if ($meta_individual > 0): ?>
                                    <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual, 0, ',', '.') ?></span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500">🎯 Meta</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-orange-300" id="valor-base-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-gray-600" id="valor-sim-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="text" 
                                       id="perc-<?= $categoria_id ?>"
                                       data-categoria="<?= htmlspecialchars($categoria_individual) ?>"
                                       data-tipo="percentual-tributo"
                                       data-valor-base="<?= $valor_individual ?>"
                                       value="<?= number_format(($valor_individual / $total_geral) * 100, 2, ',', '.') ?>"
                                       onchange="atualizarCalculosPercentualSubcategoria(this)"
                                       style="background: transparent; color: #fb923c; text-align: right; border: none; outline: none; width: 70px;"> %
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>
                    
                    <!-- RECEITA LÍQUIDA - Cálculo automático -->
                    <tbody>
                        <tr class="hover:bg-gray-700 font-semibold text-green-400">
                            <td class="px-3 py-2 border-b border-gray-700">
                                RECEITA LÍQUIDA
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Cálculo</span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-receita-liquida">
                                R$ <?= number_format($total_geral - $total_tributos, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-receita-liquida">
                                R$ <?= number_format($total_geral - $total_tributos, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-receita-liquida">
                                <?= number_format((($total_geral - $total_tributos) / $total_geral) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>

                    <!-- CUSTO VARIÁVEL - Linha principal -->
                    <?php if (!empty($custo_variavel)): ?>
                    <tbody>
                        <?php 
                        $meta_custo_variavel = obterMeta('CUSTO VARIÁVEL');
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-orange-400" onclick="toggleReceita('custo-variavel')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) CUSTO VARIÁVEL
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_custo_variavel, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-custo-variavel">
                                R$ <?= number_format($total_custo_variavel, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-gray-600" id="valor-sim-custo-variavel">
                                R$ <?= number_format($total_custo_variavel, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-custo-variavel">
                                <?= number_format(($total_custo_variavel / $total_geral) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes dos CUSTOS VARIÁVEIS -->
                    <tbody class="subcategorias" id="sub-custo-variavel" style="display: none;">
                        <?php foreach ($custo_variavel as $index => $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'CUSTO VARIÁVEL');
                        $categoria_id = 'custo-variavel-' . $index;
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <?php if ($meta_individual > 0): ?>
                                    <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual, 0, ',', '.') ?></span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500">🎯 Meta</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-orange-300" id="valor-base-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-gray-600" id="valor-sim-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="text" 
                                       id="perc-<?= $categoria_id ?>"
                                       data-categoria="<?= htmlspecialchars($categoria_individual) ?>"
                                       data-tipo="percentual-custo-variavel"
                                       data-valor-base="<?= $valor_individual ?>"
                                       value="<?= number_format(($valor_individual / $total_geral) * 100, 2, ',', '.') ?>"
                                       onchange="atualizarCalculosPercentualSubcategoria(this)"
                                       style="background: transparent; color: #fb923c; text-align: right; border: none; outline: none; width: 70px;"> %
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- LUCRO BRUTO - Cálculo automático -->
                    <tbody>
                        <tr class="hover:bg-gray-700 font-semibold text-green-400">
                            <td class="px-3 py-2 border-b border-gray-700">
                                LUCRO BRUTO
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Cálculo</span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-lucro-bruto">
                                R$ <?= number_format(($total_geral - $total_tributos) - $total_custo_variavel, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-lucro-bruto">
                                R$ <?= number_format(($total_geral - $total_tributos) - $total_custo_variavel, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-lucro-bruto">
                                <?= number_format(((($total_geral - $total_tributos) - $total_custo_variavel) / $total_geral) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>

                    <!-- CUSTO FIXO - Linha principal -->
                    <?php if (!empty($custo_fixo)): ?>
                    <tbody>
                        <?php 
                        $meta_custo_fixo = obterMeta('CUSTO FIXO');
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-orange-400" onclick="toggleReceita('custo-fixo')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) CUSTO FIXO
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_custo_fixo, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-custo-fixo">
                                R$ <?= number_format($total_custo_fixo, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-custo-fixo">
                                R$ <?= number_format($total_custo_fixo, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-custo-fixo">
                                <?= number_format(($total_custo_fixo / $total_geral) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes dos CUSTOS FIXOS -->
                    <tbody class="subcategorias" id="sub-custo-fixo" style="display: none;">
                        <?php foreach ($custo_fixo as $index => $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'CUSTO FIXO');
                        $categoria_id = 'custo-fixo-' . $index;
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <?php if ($meta_individual > 0): ?>
                                    <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual, 0, ',', '.') ?></span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500">🎯 Meta</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-orange-300" id="valor-base-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="number" 
                                       class="bg-transparent text-orange-300 text-right w-full border-0 outline-0 valor-simulador"
                                       id="valor-sim-<?= $categoria_id ?>"
                                       data-categoria="<?= htmlspecialchars($categoria_individual) ?>"
                                       data-tipo="custo-fixo"
                                       data-valor-base="<?= $valor_individual ?>"
                                       value="<?= number_format($valor_individual, 2, '.', '') ?>"
                                       step="0.01"
                                       onchange="atualizarCalculos()">
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-<?= $categoria_id ?>">
                                <?= number_format(($valor_individual / $total_geral) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- DESPESA FIXA - Linha principal -->
                    <?php if (!empty($despesa_fixa)): ?>
                    <tbody>
                        <?php 
                        $meta_despesa_fixa = obterMeta('DESPESA FIXA');
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-orange-400" onclick="toggleReceita('despesa-fixa')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) DESPESA FIXA
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_despesa_fixa, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-despesa-fixa">
                                R$ <?= number_format($total_despesa_fixa, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-despesa-fixa">
                                R$ <?= number_format($total_despesa_fixa, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-despesa-fixa">
                                <?= number_format(($total_despesa_fixa / $total_geral) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes das DESPESAS FIXAS -->
                    <tbody class="subcategorias" id="sub-despesa-fixa" style="display: none;">
                        <?php foreach ($despesa_fixa as $index => $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'DESPESA FIXA');
                        $categoria_id = 'despesa-fixa-' . $index;
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <?php if ($meta_individual > 0): ?>
                                    <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual, 0, ',', '.') ?></span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500">🎯 Meta</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-orange-300" id="valor-base-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="number" 
                                       class="bg-transparent text-orange-300 text-right w-full border-0 outline-0 valor-simulador"
                                       id="valor-sim-<?= $categoria_id ?>"
                                       data-categoria="<?= htmlspecialchars($categoria_individual) ?>"
                                       data-tipo="despesa-fixa"
                                       data-valor-base="<?= $valor_individual ?>"
                                       value="<?= number_format($valor_individual, 2, '.', '') ?>"
                                       step="0.01"
                                       onchange="atualizarCalculos()">
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-<?= $categoria_id ?>">
                                <?= number_format(($valor_individual / $total_geral) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- DESPESA DE VENDA - Linha principal -->
                    <?php if (!empty($despesa_venda)): ?>
                    <tbody>
                        <?php 
                        $meta_despesa_venda = obterMeta('DESPESAS DE VENDA');
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-orange-400" onclick="toggleReceita('despesa-venda')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) DESPESAS DE VENDA
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_despesa_venda, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-despesa-venda">
                                R$ <?= number_format($total_despesa_venda, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-gray-600" id="valor-sim-despesa-venda">
                                R$ <?= number_format($total_despesa_venda, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-despesa-venda">
                                <?= number_format(($total_despesa_venda / $total_geral) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes das DESPESAS DE VENDA -->
                    <tbody class="subcategorias" id="sub-despesa-venda" style="display: none;">
                        <?php foreach ($despesa_venda as $index => $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'DESPESAS DE VENDA');
                        $categoria_id = 'despesa-venda-' . $index;
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <?php if ($meta_individual > 0): ?>
                                    <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual, 0, ',', '.') ?></span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500">🎯 Meta</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-orange-300" id="valor-base-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-gray-600" id="valor-sim-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="text" 
                                       id="perc-<?= $categoria_id ?>"
                                       data-categoria="<?= htmlspecialchars($categoria_individual) ?>"
                                       data-tipo="percentual-despesa-venda"
                                       data-valor-base="<?= $valor_individual ?>"
                                       value="<?= number_format(($valor_individual / $total_geral) * 100, 2, ',', '.') ?>"
                                       onchange="atualizarCalculosPercentualSubcategoria(this)"
                                       style="background: transparent; color: #fb923c; text-align: right; border: none; outline: none; width: 70px;"> %
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- LUCRO LÍQUIDO - Cálculo automático final -->
                    <?php 
                    $lucro_liquido = (($total_geral - $total_tributos) - $total_custo_variavel) - $total_custo_fixo - $total_despesa_fixa - $total_despesa_venda;
                    ?>
                    <tbody>
                        <tr class="hover:bg-gray-700 font-bold text-green-400 bg-green-900 bg-opacity-20">
                            <td class="px-3 py-3 border-b-2 border-green-600 font-bold">
                                LUCRO LÍQUIDO
                            </td>
                            <td class="px-3 py-3 border-b-2 border-green-600 text-center">
                                <span class="text-xs text-gray-500 font-semibold">Resultado</span>
                            </td>
                            <td class="px-3 py-3 border-b-2 border-green-600 text-right font-mono font-bold" id="valor-base-lucro-liquido">
                                R$ <?= number_format($lucro_liquido, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-3 border-b-2 border-green-600 text-right font-mono font-bold bg-blue-900" id="valor-sim-lucro-liquido">
                                R$ <?= number_format($lucro_liquido, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-3 border-b-2 border-green-600 text-right font-mono font-bold bg-blue-900" id="perc-lucro-liquido">
                                <?= number_format(($lucro_liquido / $total_geral) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>

                    <!-- INVESTIMENTO INTERNO - Linha principal -->
                    <?php if (!empty($investimento_interno)): ?>
                    <tbody>
                        <?php 
                        $meta_investimento_interno = obterMeta('INVESTIMENTO INTERNO');
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-blue-400" onclick="toggleReceita('investimento-interno')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) INVESTIMENTO INTERNO
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_investimento_interno, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-investimento-interno">
                                R$ <?= number_format($total_investimento_interno, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-investimento-interno">
                                R$ <?= number_format($total_investimento_interno, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-investimento-interno">
                                <?= number_format(($total_investimento_interno / $total_geral) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes dos INVESTIMENTOS INTERNOS -->
                    <tbody class="subcategorias" id="sub-investimento-interno" style="display: none;">
                        <?php foreach ($investimento_interno as $index => $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'INVESTIMENTO INTERNO');
                        $categoria_id = 'investimento-interno-' . $index;
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <?php if ($meta_individual > 0): ?>
                                    <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual, 0, ',', '.') ?></span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500">🎯 Meta</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-blue-300" id="valor-base-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="text" 
                                       class="bg-transparent text-blue-300 text-right w-full border-0 outline-0 valor-simulador"
                                       id="valor-sim-<?= $categoria_id ?>"
                                       data-categoria="<?= htmlspecialchars($categoria_individual) ?>"
                                       data-tipo="investimento-interno"
                                       data-valor-base="<?= $valor_individual ?>"
                                       value="<?= number_format($valor_individual, 2, ',', '.') ?>"
                                       onchange="atualizarCalculos()"
                                       onblur="formatarCampoMoeda(this)"
                                       onfocus="removerFormatacao(this)">
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-<?= $categoria_id ?>">
                                <?= number_format(($valor_individual / $total_geral) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- SAÍDAS NÃO OPERACIONAIS - Linha principal -->
                    <?php if (!empty($saidas_nao_operacionais)): ?>
                    <tbody>
                        <?php 
                        $meta_saidas_nao_operacionais = obterMeta('SAÍDAS NÃO OPERACIONAIS');
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-red-400" onclick="toggleReceita('saidas-nao-operacionais')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) SAÍDAS NÃO OPERACIONAIS
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_saidas_nao_operacionais, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-saidas-nao-operacionais">
                                R$ <?= number_format($total_saidas_nao_operacionais, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-saidas-nao-operacionais">
                                R$ <?= number_format($total_saidas_nao_operacionais, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-saidas-nao-operacionais">
                                <?= number_format(($total_saidas_nao_operacionais / $total_geral) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes das SAÍDAS NÃO OPERACIONAIS -->
                    <tbody class="subcategorias" id="sub-saidas-nao-operacionais" style="display: none;">
                        <?php foreach ($saidas_nao_operacionais as $index => $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'SAÍDAS NÃO OPERACIONAIS');
                        $categoria_id = 'saidas-nao-operacionais-' . $index;
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <?php if ($meta_individual > 0): ?>
                                    <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual, 0, ',', '.') ?></span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500">🎯 Meta</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-red-300" id="valor-base-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="text" 
                                       class="bg-transparent text-red-300 text-right w-full border-0 outline-0 valor-simulador"
                                       id="valor-sim-<?= $categoria_id ?>"
                                       data-categoria="<?= htmlspecialchars($categoria_individual) ?>"
                                       data-tipo="saidas-nao-operacionais"
                                       data-valor-base="<?= $valor_individual ?>"
                                       value="<?= number_format($valor_individual, 2, ',', '.') ?>"
                                       onchange="atualizarCalculos()"
                                       onblur="formatarCampoMoeda(this)"
                                       onfocus="removerFormatacao(this)">
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-<?= $categoria_id ?>">
                                <?= number_format(($valor_individual / $total_geral) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- IMPACTO CAIXA - Cálculo final -->
                    <?php 
                    $impacto_caixa = $lucro_liquido - $total_investimento_interno - $total_saidas_nao_operacionais;
                    $cor_impacto = $impacto_caixa >= 0 ? 'green' : 'red';
                    $meta_impacto_caixa = obterMeta('IMPACTO CAIXA');
                    ?>
                    <tbody>
                        <tr class="hover:bg-gray-700 font-bold text-<?= $cor_impacto ?>-400 bg-<?= $cor_impacto ?>-900 bg-opacity-20">
                            <td class="px-3 py-3 border-b-2 border-<?= $cor_impacto ?>-600 font-bold">
                                (=) IMPACTO CAIXA
                            </td>
                            <td class="px-3 py-3 border-b-2 border-<?= $cor_impacto ?>-600 text-center">
                                <span class="text-xs text-gray-500 font-semibold">Meta: R$ <?= number_format($meta_impacto_caixa, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-3 border-b-2 border-<?= $cor_impacto ?>-600 text-right font-mono font-bold" id="valor-base-impacto-caixa">
                                R$ <?= number_format($impacto_caixa, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-3 border-b-2 border-<?= $cor_impacto ?>-600 text-right font-mono font-bold bg-blue-900" id="valor-sim-impacto-caixa">
                                R$ <?= number_format($impacto_caixa, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-3 border-b-2 border-<?= $cor_impacto ?>-600 text-right font-mono font-bold bg-blue-900" id="perc-impacto-caixa">
                                <?= number_format(($impacto_caixa / $total_geral) * 100, 2, ',', '.') ?>%
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
                    Não há dados para o período selecionado: <?= htmlspecialchars($periodos_disponiveis[$periodo_selecionado] ?? $periodo_selecionado) ?>
                </p>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="bg-gray-800 rounded-lg p-6 text-center">
            <p class="text-gray-400 text-lg mb-2">📋 Selecione um período base</p>
            <p class="text-gray-500">
                Escolha um período no filtro acima para carregar os dados base e iniciar a simulação.
            </p>
            <?php if (!empty($periodos_disponiveis)): ?>
                <p class="text-gray-500 mt-2">
                    Períodos disponíveis: <?= count($periodos_disponiveis) ?> meses com dados
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de Seleção de Meses para Metas -->
<div id="modalMetas" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center" onclick="fecharModalSeClicarFora(event)">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-800">🎯 Salvar Metas Financeiras</h3>
            <button onclick="fecharModalMetas()" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <p class="text-gray-600 mb-4">Selecione os meses para salvar as metas financeiras:</p>
        
        <div class="grid grid-cols-2 gap-3 mb-6">
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="01" class="mes-checkbox" id="mes-01">
                <span class="text-sm">Janeiro</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="02" class="mes-checkbox" id="mes-02">
                <span class="text-sm">Fevereiro</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="03" class="mes-checkbox" id="mes-03">
                <span class="text-sm">Março</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="04" class="mes-checkbox" id="mes-04">
                <span class="text-sm">Abril</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="05" class="mes-checkbox" id="mes-05">
                <span class="text-sm">Maio</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="06" class="mes-checkbox" id="mes-06">
                <span class="text-sm">Junho</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="07" class="mes-checkbox" id="mes-07">
                <span class="text-sm">Julho</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="08" class="mes-checkbox" id="mes-08">
                <span class="text-sm">Agosto</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="09" class="mes-checkbox" id="mes-09">
                <span class="text-sm">Setembro</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="10" class="mes-checkbox" id="mes-10">
                <span class="text-sm">Outubro</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="11" class="mes-checkbox" id="mes-11">
                <span class="text-sm">Novembro</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="12" class="mes-checkbox" id="mes-12">
                <span class="text-sm">Dezembro</span>
            </label>
        </div>
        
        <div class="flex justify-between items-center mb-4">
            <button onclick="selecionarTodosMeses()" class="text-blue-600 hover:text-blue-800 text-sm">
                ✅ Selecionar Todos
            </button>
            <button onclick="desmarcarTodosMeses()" class="text-gray-600 hover:text-gray-800 text-sm">
                ❌ Desmarcar Todos
            </button>
        </div>
        
        <div class="flex space-x-3">
            <button onclick="fecharModalMetas()" class="flex-1 px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-700 rounded transition-colors">
                Cancelar
            </button>
            <button onclick="salvarMetasFinanceiras()" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded transition-colors">
                💾 Salvar Metas
            </button>
        </div>
    </div>
</div>

<script>
function toggleReceita(categoriaId) {
    // Caso especial para RECEITA BRUTA - mostra os subgrupos principais (apenas os cabeçalhos)
    if (categoriaId === 'receita-bruta') {
        var subReceitaBruta = document.getElementById('sub-receita-bruta');
        
        if (subReceitaBruta) {
            if (subReceitaBruta.style.display === 'none' || subReceitaBruta.style.display === '') {
                subReceitaBruta.style.display = 'table-row-group';
            } else {
                subReceitaBruta.style.display = 'none';
            }
        }
    } 
    // Para outros casos (tributos, custo-variavel, custo-fixo, despesa-fixa, despesa-venda, etc.)
    else {
        var subcategorias = document.getElementById('sub-' + categoriaId);
        
        if (subcategorias) {
            if (subcategorias.style.display === 'none' || subcategorias.style.display === '') {
                subcategorias.style.display = 'table-row-group';
            } else {
                subcategorias.style.display = 'none';
            }
        }
    }
}

function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(valor);
}

function formatarPercentual(valor) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'percent',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(valor / 100);
}

function formatarCampoMoeda(input) {
    let valor = input.value.replace(/[^\d,]/g, ''); // Remove tudo exceto números e vírgula
    valor = valor.replace(',', '.'); // Troca vírgula por ponto para conversão
    let numero = parseFloat(valor) || 0;
    input.value = numero.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function removerFormatacao(input) {
    let valor = input.value.replace(/[^\d,]/g, '');
    input.value = valor;
}

function obterValorNumerico(input) {
    let valor = input.value.replace(/[^\d,]/g, '');
    valor = valor.replace(',', '.');
    return parseFloat(valor) || 0;
}

// Funções para campos percentuais
function formatarCampoPercentual(input) {
    let valor = input.value.replace(/[^\d,]/g, ''); // Remove tudo exceto números e vírgula
    valor = valor.replace(',', '.'); // Troca vírgula por ponto para conversão
    let numero = parseFloat(valor) || 0;
    input.value = numero.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function removerFormatacaoPercentual(input) {
    let valor = input.value.replace(/[^\d,]/g, '');
    input.value = valor;
}

function obterValorNumericoPercentual(input) {
    let valor = input.value.replace(/[^\d,]/g, '');
    valor = valor.replace(',', '.');
    return parseFloat(valor) || 0;
}



function atualizarCalculosPercentualSubcategoria(inputPercentual) {
    // Obter receita bruta total
    const receitaBrutaTotal = <?= $total_geral ?>;
    
    // Obter o percentual digitado
    let percentualTexto = inputPercentual.value.replace(/[^\d,]/g, '');
    percentualTexto = percentualTexto.replace(',', '.');
    const percentual = parseFloat(percentualTexto) || 0;
    
    // Calcular o valor absoluto da subcategoria
    const valorAbsoluto = (percentual * receitaBrutaTotal) / 100;
    
    // Atualizar o valor da subcategoria
    const inputId = inputPercentual.id;
    const valorElementId = inputId.replace('perc-', 'valor-sim-');
    const valorElement = document.getElementById(valorElementId);
    
    if (valorElement) {
        valorElement.textContent = 'R$ ' + valorAbsoluto.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    // Determinar o tipo de categoria e recalcular o total da categoria pai
    const tipo = inputPercentual.dataset.tipo;
    
    if (tipo === 'percentual-tributo') {
        recalcularTotalCategoriaPai('tributos', 'valor-sim-tributos', 'perc-tributos');
    } else if (tipo === 'percentual-custo-variavel') {
        recalcularTotalCategoriaPai('custo-variavel', 'valor-sim-custo-variavel', 'perc-custo-variavel');
    } else if (tipo === 'percentual-despesa-venda') {
        recalcularTotalCategoriaPai('despesa-venda', 'valor-sim-despesa-venda', 'perc-despesa-venda');
    }
    
    // Atualizar todos os cálculos gerais
    atualizarCalculos();
}

function recalcularTotalCategoriaPai(prefixo, elementoValorId, elementoPercId) {
    const receitaBrutaTotal = <?= $total_geral ?>;
    let totalCategoria = 0;
    
    // Somar todos os valores das subcategorias
    const subcategorias = document.querySelectorAll('[id^="valor-sim-' + prefixo + '-"]');
    
    subcategorias.forEach(elemento => {
        const valorTexto = elemento.textContent || elemento.innerText;
        const valor = valorTexto.replace(/[^\d,]/g, '').replace(',', '.');
        totalCategoria += parseFloat(valor) || 0;
    });
    
    // Atualizar o valor total da categoria pai
    const elementoValor = document.getElementById(elementoValorId);
    if (elementoValor) {
        elementoValor.textContent = 'R$ ' + totalCategoria.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    // Atualizar o percentual da categoria pai
    const elementoPerc = document.getElementById(elementoPercId);
    if (elementoPerc && receitaBrutaTotal > 0) {
        const percentualCategoria = (totalCategoria / receitaBrutaTotal) * 100;
        elementoPerc.textContent = percentualCategoria.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + '%';
    }
}

function atualizarCalculos() {
    // Buscar todos os inputs de simulação (apenas valores, não percentuais)
    const inputs = document.querySelectorAll('.valor-simulador');
    
    let totalReceitas = 0;
    let totalTributos = 0;
    let totalOperacional = 0;
    let totalNaoOperacional = 0;
    let totalCustoVariavel = 0;
    let totalCustoFixo = 0;
    let totalDespesaFixa = 0;
    let totalDespesaVenda = 0;
    let totalInvestimentoInterno = 0;
    let totalSaidasNaoOperacionais = 0;
    
    // Calcular totais por categoria
    inputs.forEach(input => {
        const valor = obterValorNumerico(input);
        const tipo = input.dataset.tipo;
        const inputId = input.id;
        
        if (tipo === 'receita' || tipo === 'receita-operacional' || tipo === 'receita-nao-operacional') {
            totalReceitas += valor;
            
            // Separar operacional e não operacional
            if (tipo === 'receita-operacional' || (inputId.includes('operacional-') && !inputId.includes('nao-operacional-'))) {
                totalOperacional += valor;
            } else if (tipo === 'receita-nao-operacional' || inputId.includes('nao-operacional-')) {
                totalNaoOperacional += valor;
            }
        } else if (tipo === 'despesa') {
            if (inputId.includes('tributos-')) {
                totalTributos += valor;
            }
        } else if (tipo === 'custo-variavel') {
            totalCustoVariavel += valor;
        } else if (tipo === 'custo-fixo') {
            totalCustoFixo += valor;
        } else if (tipo === 'despesa-fixa') {
            totalDespesaFixa += valor;
        } else if (tipo === 'despesa-venda') {
            totalDespesaVenda += valor;
        } else if (tipo === 'investimento-interno') {
            totalInvestimentoInterno += valor;
        } else if (tipo === 'saidas-nao-operacionais') {
            totalSaidasNaoOperacionais += valor;
        }
    });
    
    // CALCULAR VALORES DAS CATEGORIAS PAI BASEADOS NO PERCENTUAL SOBRE FATURAMENTO
    // TRIBUTOS - calcular como percentual sobre faturamento
    let percTributos = 0;
    const elementoPercTributos = document.getElementById('perc-tributos');
    if (elementoPercTributos) {
        const percTexto = elementoPercTributos.textContent || elementoPercTributos.innerText;
        percTributos = parseFloat(percTexto.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
    }
    totalTributos = (totalReceitas * percTributos) / 100;
    
    // CUSTO VARIÁVEL - calcular como percentual sobre faturamento
    let percCustoVariavel = 0;
    const elementoPercCustoVariavel = document.getElementById('perc-custo-variavel');
    if (elementoPercCustoVariavel) {
        const percTexto = elementoPercCustoVariavel.textContent || elementoPercCustoVariavel.innerText;
        percCustoVariavel = parseFloat(percTexto.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
    }
    totalCustoVariavel = (totalReceitas * percCustoVariavel) / 100;
    
    // DESPESAS DE VENDA - calcular como percentual sobre faturamento
    let percDespesaVenda = 0;
    const elementoPercDespesaVenda = document.getElementById('perc-despesa-venda');
    if (elementoPercDespesaVenda) {
        const percTexto = elementoPercDespesaVenda.textContent || elementoPercDespesaVenda.innerText;
        percDespesaVenda = parseFloat(percTexto.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
    }
    totalDespesaVenda = (totalReceitas * percDespesaVenda) / 100;
    
    // Calcular valores derivados (usando os valores originais por enquanto)
    const receitaLiquida = totalReceitas - totalTributos;
    const lucroBruto = receitaLiquida - totalCustoVariavel;
    const lucroLiquido = lucroBruto - totalCustoFixo - totalDespesaFixa - totalDespesaVenda;
    const impactoCaixa = lucroLiquido - totalInvestimentoInterno - totalSaidasNaoOperacionais;
    
    // Atualizar valores calculados dos grupos principais (valores originais)
    const elementos = {
        'valor-sim-receita-bruta': totalReceitas,
        'valor-sim-operacional': totalOperacional,
        'valor-sim-nao-operacional': totalNaoOperacional,
        'valor-sim-tributos': totalTributos,
        'valor-sim-receita-liquida': receitaLiquida,
        'valor-sim-custo-variavel': totalCustoVariavel,
        'valor-sim-lucro-bruto': lucroBruto,
        'valor-sim-custo-fixo': totalCustoFixo,
        'valor-sim-despesa-fixa': totalDespesaFixa,
        'valor-sim-despesa-venda': totalDespesaVenda,
        'valor-sim-lucro-liquido': lucroLiquido,
        'valor-sim-investimento-interno': totalInvestimentoInterno,
        'valor-sim-saidas-nao-operacionais': totalSaidasNaoOperacionais,
        'valor-sim-impacto-caixa': impactoCaixa
    };

    // RECALCULAR CUSTOS VARIÁVEIS BASEADOS NOS PERCENTUAIS QUANDO RECEITA MUDA
    // Verificar se os percentuais das subcategorias variáveis existem e recalcular seus valores absolutos
    const inputsPercentuaisVariaveis = document.querySelectorAll('input[data-tipo^="percentual-"]:not([data-tipo*="fixo"])');
    inputsPercentuaisVariaveis.forEach(input => {
        const percentual = obterValorNumericoPercentual(input);
        if (percentual > 0 && totalReceitas > 0) {
            const novoValorAbsoluto = (totalReceitas * percentual) / 100;
            
            // Atualizar o valor absoluto correspondente
            const valorElementId = input.id.replace('perc-', 'valor-sim-');
            const valorElement = document.getElementById(valorElementId);
            if (valorElement) {
                valorElement.textContent = formatarMoeda(novoValorAbsoluto);
            }
            
            // Se for uma subcategoria, atualizar também o input de valor
            const inputValorId = input.id.replace('perc-', 'valor-');
            const inputValor = document.getElementById(inputValorId);
            if (inputValor) {
                inputValor.value = novoValorAbsoluto.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        }
    });
    
    // Atualizar elementos na DOM
    Object.keys(elementos).forEach(elementId => {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = formatarMoeda(elementos[elementId]);
        } else {
            console.warn(`Elemento não encontrado: ${elementId}`);
        }
    });
    
    // Atualizar percentuais individuais das subcategorias (baseado no novo faturamento total)
    inputs.forEach(input => {
        const valor = obterValorNumerico(input);
        const categoriaId = input.id.replace('valor-sim-', '');
        const percElement = document.getElementById('perc-' + categoriaId);
        
        if (percElement && totalReceitas > 0) {
            const percentual = (valor / totalReceitas) * 100;
            percElement.textContent = formatarPercentual(percentual);
        }
    });
    
    // Atualizar percentuais dos grupos principais (todos baseados no novo faturamento total)
    if (totalReceitas > 0) {
        // RECALCULAR TOTAIS DAS CATEGORIAS PAI SOMANDO AS SUBCATEGORIAS PARA PERCENTUAIS CORRETOS
        let totalTributosReal = 0;
        let totalCustoVariavelReal = 0;
        let totalDespesaVendaReal = 0;
        
        // Somar subcategorias de TRIBUTOS
        const inputsTributosReal = document.querySelectorAll('input[id*="tributos-"]:not([id*="perc-"])');
        inputsTributosReal.forEach(input => {
            const valor = parseFloat(input.value.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
            totalTributosReal += valor;
        });
        
        // Somar subcategorias de CUSTO VARIÁVEL  
        const inputsCustoVariavelReal = document.querySelectorAll('input[id*="custo-variavel-"]:not([id*="perc-"])');
        inputsCustoVariavelReal.forEach(input => {
            const valor = parseFloat(input.value.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
            totalCustoVariavelReal += valor;
        });
        
        // Somar subcategorias de DESPESAS DE VENDA
        const inputsDespesaVendaReal = document.querySelectorAll('input[id*="despesa-venda-"]:not([id*="perc-"])');
        inputsDespesaVendaReal.forEach(input => {
            const valor = parseFloat(input.value.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
            totalDespesaVendaReal += valor;
        });
        
        const percentuaisGrupos = {
            'perc-receita-bruta': 100.00,
            'perc-operacional': (totalOperacional / totalReceitas) * 100,
            'perc-nao-operacional': (totalNaoOperacional / totalReceitas) * 100,
            'perc-tributos': (totalTributosReal / totalReceitas) * 100,
            'perc-receita-liquida': (receitaLiquida / totalReceitas) * 100,
            'perc-custo-variavel': (totalCustoVariavelReal / totalReceitas) * 100,
            'perc-lucro-bruto': (lucroBruto / totalReceitas) * 100,
            'perc-custo-fixo': (totalCustoFixo / totalReceitas) * 100,
            'perc-despesa-fixa': (totalDespesaFixa / totalReceitas) * 100,
            'perc-despesa-venda': (totalDespesaVendaReal / totalReceitas) * 100,
            'perc-lucro-liquido': (lucroLiquido / totalReceitas) * 100,
            'perc-investimento-interno': (totalInvestimentoInterno / totalReceitas) * 100,
            'perc-saidas-nao-operacionais': (totalSaidasNaoOperacionais / totalReceitas) * 100,
            'perc-impacto-caixa': (impactoCaixa / totalReceitas) * 100
        };
        
        Object.keys(percentuaisGrupos).forEach(elementId => {
            const element = document.getElementById(elementId);
            // Não sobrescrever campos percentuais editáveis (TRIBUTOS, CUSTO VARIÁVEL, DESPESAS DE VENDA)
            const camposEditaveis = ['perc-tributos', 'perc-custo-variavel', 'perc-despesa-venda'];
            
            if (element && !camposEditaveis.includes(elementId)) {
                // Se é um campo só leitura, atualiza o texto
                if (element.tagName !== 'INPUT') {
                    element.textContent = formatarPercentual(percentuaisGrupos[elementId]);
                }
            }
        });
    }
    
    // Garantir que a RECEITA BRUTA sempre seja 100% na simulação
    const percReceitaBruta = document.getElementById('perc-receita-bruta');
    if (percReceitaBruta) {
        percReceitaBruta.textContent = '100,00%';
    }
    
    // FORÇAR RECÁLCULO DOS TOTAIS DE GRUPOS BASEADOS NAS SUBCATEGORIAS
    // Recalcular TRIBUTOS somando as subcategorias
    recalcularTotalGrupo('tributos', 'valor-sim-tributos');
    // Recalcular CUSTO VARIÁVEL somando as subcategorias  
    recalcularTotalGrupo('custo-variavel', 'valor-sim-custo-variavel');
    // Recalcular DESPESAS DE VENDA somando as subcategorias
    recalcularTotalGrupo('despesa-venda', 'valor-sim-despesa-venda');
}

// Função auxiliar para recalcular total de um grupo baseado nas subcategorias
function recalcularTotalGrupo(grupoPrefix, totalElementId) {
    const inputsGrupo = document.querySelectorAll(`input[id*="${grupoPrefix}-"]:not([id*="perc-"])`);
    let novoTotal = 0;
    
    inputsGrupo.forEach(input => {
        const valor = parseFloat(input.value.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
        novoTotal += valor;
    });
    
    // Atualizar o elemento total do grupo
    const totalElement = document.getElementById(totalElementId);
    if (totalElement && novoTotal > 0) {
        totalElement.textContent = formatarMoeda(novoTotal);
    }
}

function resetarSimulacao() {
    // Resetar campos de valor normais (editáveis)
    const inputs = document.querySelectorAll('.valor-simulador');
    
    inputs.forEach(input => {
        const valorBase = parseFloat(input.dataset.valorBase) || 0;
        input.value = valorBase.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    });
    
    // Resetar campos percentuais das subcategorias (TRIBUTOS, CUSTO VARIÁVEL, DESPESAS DE VENDA)
    const inputsPercentuaisSubcategorias = document.querySelectorAll('input[data-tipo^="percentual-"]');
    
    inputsPercentuaisSubcategorias.forEach(input => {
        const valorBase = parseFloat(input.dataset.valorBase) || 0;
        const receitaBrutaOriginal = <?= $total_geral ?>;
        
        // Restaurar o percentual original
        if (receitaBrutaOriginal > 0) {
            const percentualOriginal = (valorBase / receitaBrutaOriginal) * 100;
            input.value = percentualOriginal.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            
            // Restaurar o valor absoluto correspondente
            const inputId = input.id;
            const valorElementId = inputId.replace('perc-', 'valor-sim-');
            const valorElement = document.getElementById(valorElementId);
            
            if (valorElement) {
                valorElement.textContent = 'R$ ' + valorBase.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        }
    });
    
    // Recalcular totais das categorias pai
    recalcularTotalCategoriaPai('tributos', 'valor-sim-tributos', 'perc-tributos');
    recalcularTotalCategoriaPai('custo-variavel', 'valor-sim-custo-variavel', 'perc-custo-variavel');
    recalcularTotalCategoriaPai('despesa-venda', 'valor-sim-despesa-venda', 'perc-despesa-venda');
    
    // Atualizar todos os cálculos
    atualizarCalculos();
}

function calcularPontoEquilibrio() {
    // GARANTIR que todos os cálculos estejam atualizados ANTES de calcular o ponto de equilíbrio
    atualizarCalculos();
    
    // Aguardar um pequeno delay para garantir que a DOM foi atualizada
    setTimeout(() => {
        calcularPontoEquilibrioInterno();
    }, 100);
}

function calcularPontoEquilibrioInterno() {
    // Usar a mesma lógica eficaz do DRE que já funciona
    // Fórmula: Fluxo(R) = alpha * R + gamma = 0
    // R = -gamma / alpha
    
    // Função para extrair valor dos campos simulador
    function extrairValor(elementId) {
        const element = document.getElementById(elementId);
        if (!element) return 0;
        const texto = element.textContent || element.innerText || '';
        return parseFloat(texto.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
    }
    
    // Pegar percentuais atuais das categorias variáveis (como no DRE)
    const receitaBruta = extrairValor('valor-sim-receita-bruta');
    const tributos = extrairValor('valor-sim-tributos');
    const custoVariavel = extrairValor('valor-sim-custo-variavel');  
    const despesaVenda = extrairValor('valor-sim-despesa-venda');
    
    // Calcular percentuais (frações de 0 a 1)
    const t = receitaBruta > 0 ? tributos / receitaBruta : 0;
    const cv = receitaBruta > 0 ? custoVariavel / receitaBruta : 0;
    const dv = receitaBruta > 0 ? despesaVenda / receitaBruta : 0;
    
    // Custos fixos
    const CF = extrairValor('valor-sim-custo-fixo');
    const DF = extrairValor('valor-sim-despesa-fixa');
    const II = extrairValor('valor-sim-investimento-interno');
    const SNO = extrairValor('valor-sim-saidas-nao-operacionais');
    
    // Receitas/saldos não operacionais
    const receitaNaoOp = extrairValor('valor-sim-nao-operacional');
    const saldoNaoOp = receitaNaoOp - SNO; // receita não op - saídas não op
    
    // Fórmula baseada na hierarquia exata: IMPACTO CAIXA = 0
    // IMPACTO CAIXA = LUCRO LÍQUIDO - INVESTIMENTO INTERNO - SAÍDAS NÃO OP
    // LUCRO LÍQUIDO = RECEITA BRUTA - TRIBUTOS - CUSTO VARIÁVEL - CUSTO FIXO - DESPESA FIXA - DESPESAS VENDA
    // 0 = RECEITA BRUTA×(1 - t - cv - dv) - CF - DF - II - SNO
    // RECEITA BRUTA×(1 - t - cv - dv) = CF + DF + II + SNO
    
    const custosVariaveisPorcentual = t + cv + dv; // % sobre receita bruta
    const custosFixosTotal = CF + DF + II + SNO; // valores absolutos (sem subtrair receita não op)
    
    const alpha = 1 - custosVariaveisPorcentual; // margem disponível
    const receitaBrutaNecessaria = custosFixosTotal / alpha;
    
    if (Math.abs(alpha) < 0.0001) {
        alert('❌ Margem insuficiente! Os custos variáveis somam praticamente 100% da receita.');
        return;
    }
    
    if (!isFinite(receitaBrutaNecessaria) || receitaBrutaNecessaria <= 0) {
        alert('❌ Não existe receita positiva que zere o IMPACTO CAIXA com os parâmetros atuais.');
        return;
    }
    
    // Calcular receita operacional necessária
    const receitaOperacionalEquilibrio = receitaBrutaNecessaria - receitaNaoOp;
    
    if (receitaOperacionalEquilibrio <= 0) {
        alert('❌ Receita operacional calculada é negativa. Reduza a receita não operacional ou os custos fixos.');
        return;
    }
    

    
    // Confirmar com o usuário  
    const confirmacao = confirm(
        `🎯 PONTO DE EQUILÍBRIO - IMPACTO CAIXA = 0\n\n` +
        `📊 RECEITA OPERACIONAL NECESSÁRIA:\n` +
        `R$ ${receitaOperacionalEquilibrio.toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n\n` +
        `📈 RECEITA BRUTA TOTAL:\n` +
        `R$ ${receitaBrutaNecessaria.toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n\n` +
        `📊 CUSTOS A COBRIR:\n` +
        `• Custo Fixo: R$ ${CF.toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n` +
        `• Despesa Fixa: R$ ${DF.toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n` +
        `• Investimento Interno: R$ ${II.toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n` +
        `• Saídas Não Op.: R$ ${SNO.toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n` +
        `➖ Receita Não Op.: R$ ${receitaNaoOp.toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n` +
        `� CUSTOS FIXOS: R$ ${(CF + DF + II).toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n` +
        `📊 CUSTOS VARIÁVEIS:\n` +
        `  • Tributos: ${(t * 100).toFixed(2)}%\n` +
        `  • Custo Variável: ${(cv * 100).toFixed(2)}%\n` +
        `  • Despesas Venda: ${(dv * 100).toFixed(2)}%\n` +
        `  • Margem Líquida: ${(alpha * 100).toFixed(2)}%\n\n` +
        `⚖️ Resultado: IMPACTO CAIXA = R$ 0,00\n\n` +
        `Deseja aplicar estes valores ao simulador?`
    );
    
    if (!confirmacao) return;
    
    // Aplicar os valores calculados
    // Aplicar receita operacional calculada (como no DRE)
    const inputReceitaOperacional = document.getElementById('valor-sim-operacional');
    if (inputReceitaOperacional) {
        inputReceitaOperacional.value = receitaOperacionalEquilibrio.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        // Disparar evento para recalcular (como no DRE)
        inputReceitaOperacional.dispatchEvent(new Event('input', { bubbles: true }));
    }
    
    // Recalcular tudo
    atualizarCalculos();
    
    // Mostrar resultado final
    setTimeout(() => {
        const impactoCaixaElement = document.getElementById('valor-sim-impacto-caixa');
        const impactoCaixaValor = impactoCaixaElement ? impactoCaixaElement.textContent : '';
        const lucroLiquidoElement = document.getElementById('valor-sim-lucro-liquido');
        const lucroLiquidoValor = lucroLiquidoElement ? lucroLiquidoElement.textContent : '';
        
        alert(`✅ Ponto de equilíbrio aplicado!\n\n` +
              `🎯 IMPACTO CAIXA: ${impactoCaixaValor}\n` +
              `💰 Lucro Líquido: ${lucroLiquidoValor}`);
    }, 500);
}

function aplicarMetaCustoFixo(metaValor) {
    const inputsCustoFixo = document.querySelectorAll('input[data-tipo="custo-fixo"]');
    if (inputsCustoFixo.length === 0) return;
    
    // Distribui a meta proporcionalmente entre as subcategorias
    const valorPorItem = metaValor / inputsCustoFixo.length;
    inputsCustoFixo.forEach(input => {
        input.value = valorPorItem.toFixed(2);
    });
}

function aplicarMetaDespesaFixa(metaValor) {
    const inputsDespesaFixa = document.querySelectorAll('input[data-tipo="despesa-fixa"]');
    if (inputsDespesaFixa.length === 0) return;
    
    const valorPorItem = metaValor / inputsDespesaFixa.length;
    inputsDespesaFixa.forEach(input => {
        input.value = valorPorItem.toFixed(2);
    });
}

function aplicarMetaInvestimentoInterno(metaValor) {
    const inputsInvestimento = document.querySelectorAll('input[data-tipo="investimento-interno"]');
    if (inputsInvestimento.length === 0 || metaValor === 0) return;
    
    const valorPorItem = metaValor / inputsInvestimento.length;
    inputsInvestimento.forEach(input => {
        input.value = valorPorItem.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    });
}

function aplicarMetaSaidasNaoOperacionais(metaValor) {
    const inputsSaidas = document.querySelectorAll('input[data-tipo="saidas-nao-operacionais"]');
    if (inputsSaidas.length === 0 || metaValor === 0) return;
    
    const valorPorItem = metaValor / inputsSaidas.length;
    inputsSaidas.forEach(input => {
        input.value = valorPorItem.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    });
}

function aplicarPercentuaisCustosVariaveis(receitaBrutaTotal, percTributos, percCustoVariavel, percDespesaVenda) {
    // Aplicar percentuais aos TRIBUTOS
    const inputsTributos = document.querySelectorAll('input[data-tipo="percentual-tributo"]');
    if (inputsTributos.length > 0) {
        const percPorItem = (percTributos * 100) / inputsTributos.length;
        inputsTributos.forEach(input => {
            input.value = percPorItem.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        });
    }
    
    // Aplicar percentuais ao CUSTO VARIÁVEL
    const inputsCustoVariavel = document.querySelectorAll('input[data-tipo="percentual-custo-variavel"]');
    if (inputsCustoVariavel.length > 0) {
        const percPorItem = (percCustoVariavel * 100) / inputsCustoVariavel.length;
        inputsCustoVariavel.forEach(input => {
            input.value = percPorItem.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        });
    }
    
    // Aplicar percentuais às DESPESAS DE VENDA
    const inputsDespesaVenda = document.querySelectorAll('input[data-tipo="percentual-despesa-venda"]');
    if (inputsDespesaVenda.length > 0) {
        const percPorItem = (percDespesaVenda * 100) / inputsDespesaVenda.length;
        inputsDespesaVenda.forEach(input => {
            input.value = percPorItem.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        });
    }
    
    // Recalcular totais das categorias pai após aplicar percentuais
    setTimeout(() => {
        // Atualizar cálculos das subcategorias percentuais
        inputsTributos.forEach(input => atualizarCalculosPercentualSubcategoria(input));
        inputsCustoVariavel.forEach(input => atualizarCalculosPercentualSubcategoria(input));
        inputsDespesaVenda.forEach(input => atualizarCalculosPercentualSubcategoria(input));
    }, 100);
}

// Função removida - não mais necessária com a nova lógica do DRE

function salvarSimulacao() {
    const dados = {
        periodo: '<?= $periodo_selecionado ?>',
        simulacao: {}
    };
    
    const inputs = document.querySelectorAll('.valor-simulador');
    inputs.forEach(input => {
        dados.simulacao[input.dataset.categoria] = {
            valorBase: parseFloat(input.dataset.valorBase) || 0,
            valorSimulador: parseFloat(input.value) || 0,
            tipo: input.dataset.tipo
        };
    });
    
    // Salvar no localStorage por enquanto
    localStorage.setItem('simulacao_financeira', JSON.stringify(dados));
    
    alert('Simulação salva com sucesso!');
}

function corrigirInputsFormatacao() {
    // Corrigir todos os inputs para usar formatação brasileira
    const inputs = document.querySelectorAll('.valor-simulador');
    inputs.forEach(input => {
        // Alterar type para text
        input.type = 'text';
        
        // Remover step
        input.removeAttribute('step');
        
        // Adicionar eventos de formatação
        input.addEventListener('focus', function() { removerFormatacao(this); });
        input.addEventListener('blur', function() { formatarCampoMoeda(this); });
        
        // Formatar valor inicial
        const valorBase = parseFloat(input.dataset.valorBase) || 0;
        input.value = valorBase.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    });
}

// Carregar simulação salva ao inicializar
document.addEventListener('DOMContentLoaded', function() {
    // Corrigir formatação dos inputs primeiro
    corrigirInputsFormatacao();
    
    const simulacaoSalva = localStorage.getItem('simulacao_financeira');
    
    if (simulacaoSalva) {
        try {
            const dados = JSON.parse(simulacaoSalva);
            
            if (dados.periodo === '<?= $periodo_selecionado ?>') {
                // Carregar valores salvos
                Object.keys(dados.simulacao).forEach(categoria => {
                    const input = document.querySelector(`input[data-categoria="${categoria}"]`);
                    if (input) {
                        const valor = dados.simulacao[categoria].valorSimulador;
                        input.value = valor.toLocaleString('pt-BR', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                });
                
                atualizarCalculos();
            }
        } catch (e) {
            console.error('Erro ao carregar simulação:', e);
        }
    }
    
    // Configurar o background do conteúdo principal
    const content = document.getElementById('content');
    if (content) {
        content.style.background = '#111827';
        content.style.paddingLeft = '2rem';
    }
    
    // Verificar se o simulador-content precisa ser movido
    const simuladorContent = document.getElementById('simulador-content');
    const content2 = document.getElementById('content');
    
    if (simuladorContent && content2 && !content2.contains(simuladorContent)) {
        content2.appendChild(simuladorContent);
    }
});

// ========= FUNÇÕES DO MODAL DE METAS =========

function abrirModalMetas() {
    const modal = document.getElementById('modalMetas');
    if (modal) {
        modal.classList.remove('hidden');
    }
}

function fecharModalMetas() {
    const modal = document.getElementById('modalMetas');
    if (modal) {
        modal.classList.add('hidden');
        // Limpar seleções
        desmarcarTodosMeses();
    }
}

function selecionarTodosMeses() {
    const checkboxes = document.querySelectorAll('.mes-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
}

function desmarcarTodosMeses() {
    const checkboxes = document.querySelectorAll('.mes-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
}

function salvarMetasFinanceiras() {
    // Obter meses selecionados
    const mesesSelecionados = [];
    const checkboxes = document.querySelectorAll('.mes-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('⚠️ Selecione pelo menos um mês para salvar as metas!');
        return;
    }
    
    checkboxes.forEach(checkbox => {
        mesesSelecionados.push(checkbox.value);
    });
    
    // Coletar dados financeiros atuais do simulador
    const metasFinanceiras = coletarMetasDoSimulador();
    
    // Debug: Mostrar dados coletados
    console.log('📊 Metas coletadas:', metasFinanceiras);
    
    if (metasFinanceiras.length === 0) {
        alert('⚠️ Nenhuma meta com valor foi encontrada no simulador!');
        return;
    }
    
    // Confirmar com usuário
    const confirmacao = confirm(
        `🎯 SALVAR METAS FINANCEIRAS\n\n` +
        `📅 Meses selecionados: ${mesesSelecionados.length}\n` +
        `📊 Metas encontradas: ${metasFinanceiras.length}\n\n` +
        `Exemplo de dados:\n` +
        `• ${metasFinanceiras[0]?.categoria}: R$ ${metasFinanceiras[0]?.meta?.toFixed(2)}\n\n` +
        `Deseja salvar essas metas na base de dados?`
    );
    
    if (!confirmacao) return;
    
    // Enviar dados para salvamento
    enviarMetasParaServidor(mesesSelecionados, metasFinanceiras);
}

function coletarMetasDoSimulador() {
    const metas = [];
    
    // Mapear nomes legíveis para as categorias
    const nomesCategorias = {
        'receita-bruta': 'RECEITA BRUTA',
        'receita-liquida': 'RECEITA LÍQUIDA', 
        'tributos': 'TRIBUTOS',
        'custo-variavel': 'CUSTO VARIÁVEL',
        'lucro-bruto': 'LUCRO BRUTO',
        'custo-fixo': 'CUSTO FIXO',
        'despesa-fixa': 'DESPESA FIXA',
        'despesas-venda': 'DESPESAS DE VENDA',
        'lucro-liquido': 'LUCRO LÍQUIDO',
        'investimento-interno': 'INVESTIMENTO INTERNO',
        'saidas-nao-operacionais': 'SAÍDAS NÃO OPERACIONAIS',
        'impacto-caixa': 'IMPACTO CAIXA'
    };
    
    // Coletar TODAS as categorias e subcategorias, salvando tudo na coluna CATEGORIA
    
    // 1. Primeiro salvar categorias pai (totais das categorias principais)
    Object.keys(nomesCategorias).forEach(categoriaId => {
        const categoriaNome = nomesCategorias[categoriaId];
        
        // Obter SEMPRE o valor ABSOLUTO total da categoria
        const totalElement = document.getElementById(`valor-sim-${categoriaId}`);
        const textoTotal = totalElement ? totalElement.textContent.trim() : '';
        
        // Extrair APENAS números, vírgulas e pontos, depois converter para float
        const valorAbsolutoString = textoTotal.replace(/[^\d,.-]/g, ''); // Remove R$, espaços, etc
        const valorAbsolutoTotal = parseFloat(valorAbsolutoString.replace(',', '.')) || 0;
        
        // Calcular percentual baseado na receita bruta APENAS para referência
        let percentualTotal = 0;
        const receitaBrutaElement = document.getElementById('valor-sim-receita-bruta');
        const receitaBruta = parseFloat(receitaBrutaElement?.textContent?.replace(/[^\d,-]/g, '').replace(',', '.')) || 0;
        if (receitaBruta > 0) {
            percentualTotal = (valorAbsolutoTotal / receitaBruta) * 100;
        }
        
        // DEBUG: Log detalhado para categorias pai
        console.log(`CATEGORIA PAI: ${categoriaNome}`);
        console.log(`  - Elemento: valor-sim-${categoriaId}`);
        console.log(`  - Texto original: "${textoTotal}"`);
        console.log(`  - Valor absoluto extraído: R$ ${valorAbsolutoTotal.toFixed(2)}`);
        console.log(`  - Percentual calculado: ${percentualTotal.toFixed(2)}%`);
        
        // GARANTIR que está salvando o VALOR ABSOLUTO no campo META
        metas.push({
            categoria: categoriaNome,     // Ex: "CUSTO FIXO" → vai para CATEGORIA
            subcategoria: '',            // Sempre vazio
            meta: valorAbsolutoTotal,    // SEMPRE VALOR ABSOLUTO (ex: 160204.54, nunca %)
            percentual: percentualTotal  // Percentual calculado apenas para referência
        });
    });
    
    // 2. Depois salvar subcategorias (extraindo das tabelas)
    const categoriasComSubcategorias = ['custo-fixo', 'tributos', 'custo-variavel', 'despesa-fixa', 'despesas-venda'];
    
    categoriasComSubcategorias.forEach(categoriaId => {
        const categoriaNome = nomesCategorias[categoriaId];
        if (!categoriaNome) return;
        
        // Procurar tabela de subcategorias desta categoria
        const tabelaSubcategorias = document.querySelector(`#sub-${categoriaId}`);
        if (!tabelaSubcategorias) return;
        
        // Procurar todas as linhas de subcategorias
        const linhasSubcategorias = tabelaSubcategorias.querySelectorAll('tr');
        
        linhasSubcategorias.forEach(linha => {
            const celulaCategoria = linha.querySelector('td:first-child');
            if (!celulaCategoria) return;
            
            const subcategoriaNome = celulaCategoria.textContent.trim();
            if (!subcategoriaNome || subcategoriaNome === '') return;
            
            // Obter SEMPRE o valor ABSOLUTO da subcategoria (última coluna da tabela)
            const celulaValor = linha.querySelector('td:last-child');
            const textoValor = celulaValor ? celulaValor.textContent.trim() : '';
            
            // Extrair APENAS números, vírgulas e pontos, depois converter para float
            const valorAbsolutoString = textoValor.replace(/[^\d,.-]/g, ''); // Remove R$, espaços, etc
            const valorAbsoluto = parseFloat(valorAbsolutoString.replace(',', '.')) || 0;
            
            // Calcular percentual baseado na receita bruta APENAS para referência
            let percentual = 0;
            const receitaBrutaElement = document.getElementById('valor-sim-receita-bruta');
            const receitaBruta = parseFloat(receitaBrutaElement?.textContent?.replace(/[^\d,-]/g, '').replace(',', '.')) || 0;
            if (receitaBruta > 0) {
                percentual = (valorAbsoluto / receitaBruta) * 100;
            }
            
            // DEBUG: Log detalhado do que está sendo coletado
            console.log(`SUBCATEGORIA: ${subcategoriaNome}`);
            console.log(`  - Texto original: "${textoValor}"`);
            console.log(`  - Valor absoluto extraído: R$ ${valorAbsoluto.toFixed(2)}`);
            console.log(`  - Percentual calculado: ${percentual.toFixed(2)}%`);
            console.log(`  - Categoria pai: ${categoriaNome}`);
            
            // GARANTIR que está salvando o VALOR ABSOLUTO no campo META
            metas.push({
                categoria: subcategoriaNome, // Ex: "ÁGUA" → vai para CATEGORIA
                subcategoria: '',           // Sempre vazio
                meta: valorAbsoluto,        // SEMPRE VALOR ABSOLUTO (ex: 87.12, nunca %)
                percentual: percentual      // Percentual calculado apenas para referência
            });
        });
    });
    
    // DEBUG: Mostrar TODAS as metas coletadas antes de enviar
    console.log('=== RESUMO FINAL DAS METAS COLETADAS ===');
    metas.forEach(meta => {
        console.log(`${meta.categoria}: META = R$ ${meta.meta.toFixed(2)}, PERCENTUAL = ${meta.percentual.toFixed(2)}%`);
    });
    console.log(`Total de metas coletadas: ${metas.length}`);
    
    return metas;
}

function enviarMetasParaServidor(meses, metas) {
    // Mostrar loading
    const btnSalvar = document.querySelector('[onclick="salvarMetasFinanceiras()"]');
    const textoOriginal = btnSalvar.innerHTML;
    btnSalvar.innerHTML = '⏳ Salvando...';
    btnSalvar.disabled = true;
    
    // Preparar dados para envio
    const dados = {
        action: 'salvar_metas',
        meses: meses,
        metas: metas,
        ano: new Date().getFullYear()
    };
    
    fetch('salvar_metas.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(dados)
    })
    .then(response => {
        // Verificar se a resposta é OK
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        // Tentar converter para JSON
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Resposta não é JSON válido:', text);
                throw new Error(`Resposta inválida do servidor: ${text.substring(0, 100)}...`);
            }
        });
    })
    .then(data => {
        btnSalvar.innerHTML = textoOriginal;
        btnSalvar.disabled = false;
        
        if (data.success) {
            alert(`✅ Metas salvas com sucesso!\n\n📊 ${data.total_registros} registros salvos`);
            fecharModalMetas();
        } else {
            alert(`❌ Erro ao salvar metas:\n${data.message}`);
        }
    })
    .catch(error => {
        btnSalvar.innerHTML = textoOriginal;
        btnSalvar.disabled = false;
        console.error('Erro completo:', error);
        alert(`❌ Erro de conexão:\n${error.message}`);
    });
}

function fecharModalSeClicarFora(event) {
    if (event.target.id === 'modalMetas') {
        fecharModalMetas();
    }
}

// Fechar modal com ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('modalMetas');
        if (modal && !modal.classList.contains('hidden')) {
            fecharModalMetas();
        }
    }
});

</script>