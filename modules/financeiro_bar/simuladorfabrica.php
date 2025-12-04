<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurar fuso horário do Brasil
date_default_timezone_set('America/Sao_Paulo');

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
require_once __DIR__ . '/supabase_connection.php';

// Debug helper: grava em error_log, php://stderr e arquivo local
$simulador_debug_file = __DIR__ . '/simuladorfabrica_debug.log';
function salvar_simulador_log_debug($msg) {
    global $simulador_debug_file;
    $ts = date('Y-m-d H:i:s');
    $full = "[{$ts}] " . $msg . PHP_EOL;
    error_log($full);
    if (defined('STDERR')) {
        @fwrite(STDERR, $full);
    } else {
        $fp = @fopen('php://stderr', 'w');
        if ($fp) { @fwrite($fp, $full); @fclose($fp); }
    }
    @file_put_contents($simulador_debug_file, $full, FILE_APPEND | LOCK_EX);
}

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
    if (function_exists('salvar_simulador_log_debug')) {
        salvar_simulador_log_debug('Erro criando SupabaseConnection: ' . $e->getMessage() . ' file:' . $e->getFile() . ' line:' . $e->getLine());
        salvar_simulador_log_debug('Stack: ' . $e->getTraceAsString());
    }
}

$periodos_disponiveis = [];
$dados_receita = [];
$dados_despesa = [];

if ($conexao_ok) {
    try {
        // Buscar todos os períodos disponíveis nas views de receita aberta e fechada (mesma lógica do index5.php)
        $periodos_aberto = $supabase->select('freceitafabrica_aberto', [
            'select' => 'data_mes',
            'order' => 'data_mes.desc'
        ]);
        $periodos_fechado = $supabase->select('freceitafabrica_fechado', [
            'select' => 'data_mes',
            'order' => 'data_mes.desc'
        ]);
        $todos_dados = array_merge($periodos_aberto ?: [], $periodos_fechado ?: []);

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
                $periodo_formato = date('Y/m', strtotime($data_mes));
                $mes_ingles = date('F', strtotime($data_mes));
                $mes_portugues = $meses_pt[$mes_ingles] ?? $mes_ingles;
                $periodo_display = $periodo_formato . ' - ' . $mes_portugues;
                $periodos_disponiveis[$periodo_formato] = $periodo_display;
            }
            // Ordenar por data decrescente
            uksort($periodos_disponiveis, function($a, $b) { return strcmp($b, $a); });
        }

        // Se nenhum período foi selecionado, selecionar automaticamente o mês atual ou mais recente passado
        if (empty($periodo_selecionado) && !empty($periodos_disponiveis)) {
            $mes_atual = date('Y/m');
            if (isset($periodos_disponiveis[$mes_atual])) {
                $periodo_selecionado = $mes_atual;
            } else {
                $periodos_keys = array_keys($periodos_disponiveis);
                $periodo_selecionado = null;
                foreach ($periodos_keys as $periodo) {
                    if ($periodo <= $mes_atual) {
                        $periodo_selecionado = $periodo;
                        break;
                    }
                }
                if (!$periodo_selecionado) {
                    $periodo_selecionado = end($periodos_keys);
                }
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

                // Determinar se o período selecionado está disponível na view aberta
                $periodos_aberto_set = [];
                if (!empty($periodos_aberto) && is_array($periodos_aberto)) {
                    foreach ($periodos_aberto as $p) {
                        $dm = $p['data_mes'] ?? null;
                        if ($dm) {
                            $k = date('Y/m', strtotime($dm));
                            $periodos_aberto_set[$k] = true;
                        }
                    }
                }

                $mes_atual = date('Y/m');
                $receita_origem_aberto = false;
                if (isset($periodos_aberto_set[$periodo_selecionado]) || $periodo_selecionado === $mes_atual) {
                    // Para o mês corrente ou quando o período existe na view aberta, usar a view aberta
                    $dados_receita = $supabase->select('freceitafabrica_aberto', [
                        'select' => '*',
                        'filters' => [ 'data_mes' => 'eq.' . $data_filtro ],
                        'order' => 'data_mes.desc'
                    ]);
                    $receita_origem_aberto = true;
                } else {
                    // Para meses passados usar a view fechada (valores consolidados)
                    $dados_receita = $supabase->select('freceitafabrica_fechado', [
                        'select' => '*',
                        'filters' => [ 'data_mes' => 'eq.' . $data_filtro ],
                        'order' => 'data_mes.desc'
                    ]);
                    $receita_origem_aberto = false;
                }

                // Buscar despesas SEMPRE de fdespesasfabrica e detalhes das despesas
                $dados_despesa = $supabase->select('fdespesasfabrica', [
                    'select' => '*',
                    'filters' => [ 'data_mes' => 'eq.' . $data_filtro ],
                    'order' => 'data_mes.desc'
                ]);

                $dados_despesa_detalhes = $supabase->select('fdespesasfabrica_detalhes', [
                    'select' => '*',
                    'filters' => [ 'data_mes' => 'eq.' . $data_filtro ],
                    'order' => 'valor.desc'
                ]);
            }
        }
    } catch (Exception $e) {
        $periodos_disponiveis = [];
        $dados_receita = [];
        $dados_despesa = [];
        if (function_exists('salvar_simulador_log_debug')) {
            salvar_simulador_log_debug('Erro no bloco principal de leitura de dados: ' . $e->getMessage());
            salvar_simulador_log_debug('Exception file: ' . $e->getFile() . ' line: ' . $e->getLine());
            salvar_simulador_log_debug('Stack trace: ' . $e->getTraceAsString());
        }
    }
}

require_once __DIR__ . '/../../sidebar.php';
?>

<div id="simulador-content" class="p-6 ml-4">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl text-blue-400">Simulador Financeiro - Fabrica</h2>
        <div class="flex items-center gap-2">
            <a href="index.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded transition-colors flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar ao Menu
            </a>
            <!-- Dropdown para alternar para simulador do Bar da Fabrica -->
            <div class="relative">
                <button id="simuladorFabricaMenuBtn" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded transition-colors">Selecionar Bar ▾</button>
                <div id="simuladorFabricaMenu" class="absolute right-0 mt-2 w-48 bg-white rounded shadow-lg hidden z-50">
                    <a href="simulador.php" class="block px-4 py-2 text-sm text-gray-800 hover:bg-gray-100">Bar da Fabrica</a>
                </div>
            </div>
            <script>
                document.addEventListener('click', function(e) {
                    const btn = document.getElementById('simuladorFabricaMenuBtn');
                    const menu = document.getElementById('simuladorFabricaMenu');
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
                $investimento_externo = [];
                $amortizacao = [];
                $saidas_nao_operacionais = [];
                $retirada_de_lucro = [];
                
                // Função para obter meta da tabela fmetasfabrica
                function obterMeta($categoria, $categoria_pai = null) {
                    global $supabase, $periodo_selecionado;

                    if (!$supabase) {
                        return 0; // Se conexão não existe, retorna 0
                    }

                    // Converter período YYYY/MM para DATA_META YYYY-MM-01 se disponível
                    $filtros = [];
                    if (!empty($periodo_selecionado) && preg_match('/^(\d{4})\/(\d{2})$/', $periodo_selecionado, $m)) {
                        $data_meta = $m[1] . '-' . $m[2] . '-01';
                        $filtros['DATA_META'] = "eq.$data_meta";
                    }

                    $categoria_upper = mb_strtoupper(trim($categoria), 'UTF-8');

                    try {
                        $resultado = null;

                        if ($categoria_pai) {
                            // Buscar subcategoria com categoria pai
                            $categoria_pai_upper = mb_strtoupper(trim($categoria_pai), 'UTF-8');
                            $filtros['CATEGORIA'] = "eq.$categoria_pai_upper";
                            $filtros['SUBCATEGORIA'] = "eq.$categoria_upper";

                            $resultado = $supabase->select('fmetasfabricafinal', [
                                'select' => 'META',
                                'filters' => $filtros,
                                'order' => 'DATA_CRI.desc',
                                'limit' => 1
                            ]);
                        } else {
                            // Buscar categoria pai explicitamente (SUBCATEGORIA is null)
                            $filtros['CATEGORIA'] = "eq.$categoria_upper";
                            $filtros['SUBCATEGORIA'] = 'is.null';

                            $resultado = $supabase->select('fmetasfabricafinal', [
                                'select' => 'META',
                                'filters' => $filtros,
                                'order' => 'DATA_CRI.desc',
                                'limit' => 1
                            ]);

                            // Se não encontrou meta de categoria pai, tentar como subcategoria (retrocompat)
                            if (empty($resultado)) {
                                $filtros = [];
                                if (!empty($periodo_selecionado) && preg_match('/^(\d{4})\/(\d{2})$/', $periodo_selecionado, $m2)) {
                                    $data_meta2 = $m2[1] . '-' . $m2[2] . '-01';
                                    $filtros['DATA_META'] = "eq.$data_meta2";
                                }
                                $filtros['SUBCATEGORIA'] = "eq.$categoria_upper";

                                $resultado = $supabase->select('fmetasfabricafinal', [
                                    'select' => 'META',
                                    'filters' => $filtros,
                                    'order' => 'DATA_CRI.desc',
                                    'limit' => 1
                                ]);
                            }
                        }

                        // Verifica se encontrou resultado válido
                        if (!empty($resultado) && isset($resultado[0]['META']) && is_numeric($resultado[0]['META'])) {
                            return floatval($resultado[0]['META']);
                        }

                        return 0;

                    } catch (Exception $e) {
                        $msg = "Erro ao buscar meta para '$categoria' (pai: '$categoria_pai', periodo: '{$periodo_selecionado}'): " . $e->getMessage();
                        if (function_exists('salvar_simulador_log_debug')) {
                            salvar_simulador_log_debug($msg);
                            salvar_simulador_log_debug('Exception file: ' . $e->getFile() . ' line: ' . $e->getLine());
                            salvar_simulador_log_debug('Stack trace: ' . $e->getTraceAsString());
                        } else {
                            error_log($msg);
                        }
                        return 0;
                    }
                }
                
                // Categorias não operacionais (apenas repasses)
                $categorias_nao_operacionais = [
                    'ENTRADA DE REPASSE DE SALARIOS',
                    'ENTRADA DE REPASSE EXTRA DE SALARIOS', 
                    'ENTRADA DE REPASSE OUTROS'
                ];

                // Determinar se estamos lendo a view aberta (mês atual) ou fechada
                $receita_origem_aberto = (isset($receita_view) && $receita_view === 'freceitafabrica_aberto');

                // Processar RECEITAS - normalizar campos de valor e separar operacionais / não operacionais
                foreach ($dados_receita as $linha) {
                    $categoria = trim(strtoupper($linha['categoria'] ?? ''));
                    $categoria_pai = trim(strtoupper($linha['categoria_pai'] ?? ''));

                    // Normalizar o campo de total da receita (ordem de preferência)
                    $total_linha = floatval(
                        $linha['vlr_total'] ?? $linha['valor'] ?? $linha['valor_total'] ?? $linha['total_receita_mes'] ?? $linha['total'] ?? 0
                    );

                    // Quando os dados vêm da view aberta, tentar extrair valor já recebido (vlr_pago / valor_pago)
                    if ($receita_origem_aberto) {
                        $recebido = floatval($linha['vlr_pago'] ?? $linha['valor_pago'] ?? $linha['valor_pago_recebido'] ?? 0);
                    } else {
                        // em view fechada consideramos tudo como recebido (consolidado)
                        $recebido = $total_linha;
                    }
                    $pendente = max(0.0, $total_linha - $recebido);

                    // Anotar nos dados para uso posterior na UI
                    $linha['recebido'] = $recebido;
                    $linha['pendente'] = $pendente;
                    $linha['total_receita_mes'] = $total_linha; // garantir campo padronizado

                    // Atualizar totais
                    $total_geral += $total_linha;

                    // Forçar OUTRAS RECEITAS como não operacional
                    if ($categoria === 'OUTRAS RECEITAS') {
                        $receitas_nao_operacionais[] = $linha;
                        continue;
                    }

                    // Classificação mais robusta para não operacionais:
                    // - categoria ou categoria_pai contendo 'REPASSE' ou similar
                    // - categoria_pai explicitamente 'RECEITAS NÃO OPERACIONAIS' ou variações
                    $eh_nao_operacional = false;

                    // Categoria pai que explicitamente marca não operacional
                    $pai_nao_op_candidates = ['RECEITAS NÃO OPERACIONAIS', 'SAÍDAS NÃO OPERACIONAIS', 'RECEITA NÃO OPERACIONAL', 'RECEITAS NAO OPERACIONAIS'];
                    foreach ($pai_nao_op_candidates as $p) {
                        if (strpos($categoria_pai, $p) !== false || $categoria_pai === $p) {
                            $eh_nao_operacional = true;
                            break;
                        }
                    }

                    // Procurar variações de 'repasse' na categoria ou na categoria pai
                    if (!$eh_nao_operacional) {
                        if (strpos($categoria, 'REPASSE') !== false || strpos($categoria_pai, 'REPASSE') !== false || strpos($categoria, 'REPASS') !== false || strpos($categoria_pai, 'REPASS') !== false) {
                            $eh_nao_operacional = true;
                        }
                    }

                    // Checar a lista conhecida de repasses (versões completas)
                    if (!$eh_nao_operacional) {
                        foreach ($categorias_nao_operacionais as $cat_nao_op) {
                            if (strpos($categoria, trim(strtoupper($cat_nao_op))) !== false || trim(strtoupper($cat_nao_op)) === $categoria) {
                                $eh_nao_operacional = true;
                                break;
                            }
                        }
                    }

                    if ($eh_nao_operacional) {
                        $receitas_nao_operacionais[] = $linha;
                    } else {
                        $receitas_operacionais[] = $linha;
                    }
                }
                
                // Processar DESPESAS (incluindo TRIBUTOS e CUSTO FIXO)
                // Helper: normaliza strings (remove acentos, pontuação, deixa em UPPER)
                function _normalize_cat($s) {
                    $s = $s ?? '';
                    $s = trim($s);
                    // tentar transliteração básica
                    $s = @iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s;
                    $s = strtoupper($s);
                    // remover caracteres que não são letras/números/espaço
                    $s = preg_replace('/[^A-Z0-9 ]+/', '', $s);
                    // normalizar múltiplos espaços
                    $s = preg_replace('/\s+/', ' ', $s);
                    return $s;
                }

                foreach ($dados_despesa as $linha) {
                    $categoria_pai_raw = $linha['categoria_pai'] ?? '';
                    $categoria_pai = _normalize_cat($categoria_pai_raw);
                    // Também normalizar a categoria 'filha' (se existir) para permitir detecção
                    $categoria_raw = $linha['categoria'] ?? '';
                    $categoria = _normalize_cat($categoria_raw);

                    // Separar TRIBUTOS das despesas
                    if ($categoria_pai === 'TRIBUTOS') {
                        $tributos[] = $linha;
                        continue;
                    }

                    // Separar CUSTO VARIÁVEL das despesas
                    if (in_array($categoria_pai, ['CUSTO VARIAVEL', 'CUSTO VARIAVEL'])) {
                        $custo_variavel[] = $linha;
                        continue;
                    }

                    // Separar CUSTO FIXO das despesas
                    if ($categoria_pai === 'CUSTO FIXO') {
                        $custo_fixo[] = $linha;
                        continue;
                    }

                    // Separar DESPESA FIXA das despesas
                    if ($categoria_pai === 'DESPESA FIXA') {
                        $despesa_fixa[] = $linha;
                        continue;
                    }

                    // Separar DESPESAS DE VENDA das despesas (variações previstas)
                    if (strpos($categoria_pai, 'DESPESAS DE VENDA') !== false || strpos($categoria_pai, 'DESPESA DE VENDA') !== false || strpos($categoria_pai, 'DESPESA VENDA') !== false || strpos($categoria_pai, 'DESPESASVENDA') !== false) {
                        $despesa_venda[] = $linha;
                        continue;
                    }

                    // Separar INVESTIMENTO INTERNO das despesas
                    if ($categoria_pai === 'INVESTIMENTO INTERNO') {
                        $investimento_interno[] = $linha;
                        continue;
                    }

                    // Separar INVESTIMENTO EXTERNO das despesas
                    if ($categoria_pai === 'INVESTIMENTO EXTERNO') {
                        $investimento_externo[] = $linha;
                        continue;
                    }

                    // Separar AMORTIZACAO / AMORTIZAÇÃO das despesas
                    if (strpos($categoria_pai, 'AMORTIZAC') !== false) {
                        $amortizacao[] = $linha;
                        continue;
                    }

                    // Capturar variações relacionadas a 'REPASSE' (ex: 'Z - SAIDA DE REPASSE')
                    // Muitas bases usam o literal 'Z - SAIDA DE REPASSE' para agrupar saídas não operacionais.
                    // Normalizamos acima, então verificar tanto a versão raw quanto a normalizada.
                    // Detectar repasses tanto na categoria pai quanto na categoria filha (vários formatos possíveis)
                    if (
                        strpos($categoria_pai, 'REPASSE') !== false ||
                        strpos($categoria, 'REPASSE') !== false ||
                        strpos($categoria_pai, 'SAIDA DE REPASSE') !== false ||
                        strpos($categoria, 'SAIDA DE REPASSE') !== false ||
                        strpos($categoria_pai_raw, 'Z - SAIDA DE REPASSE') !== false ||
                        strpos($categoria_raw, 'Z - SAIDA DE REPASSE') !== false
                    ) {
                        $saidas_nao_operacionais[] = $linha;
                        continue;
                    }

                    // Separar SAIDAS NAO OPERACIONAIS (variações sem acento/parênteses)
                    // Também aceitar variações quando a marcação aparece na categoria filha
                    if (
                        strpos($categoria_pai, 'SAIDAS NAO OPERACIONAIS') !== false ||
                        strpos($categoria, 'SAIDAS NAO OPERACIONAIS') !== false ||
                        strpos($categoria_pai, 'SAIDASNAOOPERACIONAIS') !== false ||
                        strpos($categoria, 'SAIDASNAOOPERACIONAIS') !== false ||
                        strpos($categoria_pai, 'SAIDAS NAO OPERACIONAL') !== false ||
                        strpos($categoria, 'SAIDAS NAO OPERACIONAL') !== false ||
                        strpos($categoria_pai, 'SAIDAS NAO') !== false ||
                        strpos($categoria, 'SAIDAS NAO') !== false
                    ) {
                        $saidas_nao_operacionais[] = $linha;
                        continue;
                    }

                    // Separar RETIRADA DE LUCRO como grupo próprio
                    if (strpos($categoria_pai, 'RETIRADA DE LUCRO') !== false || strpos($categoria_pai, 'RETIRADADELUCR O') !== false) {
                        $retirada_de_lucro[] = $linha;
                        continue;
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
                usort($investimento_externo, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                usort($amortizacao, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                usort($retirada_de_lucro, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                usort($saidas_nao_operacionais, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                
                // Remover possíveis duplicatas por nome de categoria (evita linhas repetidas na tabela)
                function dedupe_by_categoria_name_fabrica($arr) {
                    $out = [];
                    $seen = [];
                    foreach ($arr as $r) {
                        $nome = trim(strtoupper($r['categoria'] ?? ''));
                        if ($nome === '') continue;
                        if (in_array($nome, $seen, true)) continue;
                        $seen[] = $nome;
                        $out[] = $r;
                    }
                    return $out;
                }

                $receitas_operacionais = dedupe_by_categoria_name_fabrica($receitas_operacionais);
                $receitas_nao_operacionais = dedupe_by_categoria_name_fabrica($receitas_nao_operacionais);
                $tributos = dedupe_by_categoria_name_fabrica($tributos);
                $custo_variavel = dedupe_by_categoria_name_fabrica($custo_variavel);
                $custo_fixo = dedupe_by_categoria_name_fabrica($custo_fixo);
                $despesa_fixa = dedupe_by_categoria_name_fabrica($despesa_fixa);
                $despesa_venda = dedupe_by_categoria_name_fabrica($despesa_venda);
                $investimento_interno = dedupe_by_categoria_name_fabrica($investimento_interno);
                $investimento_externo = dedupe_by_categoria_name_fabrica($investimento_externo);
                $amortizacao = dedupe_by_categoria_name_fabrica($amortizacao);
                $retirada_de_lucro = dedupe_by_categoria_name_fabrica($retirada_de_lucro);
                $saidas_nao_operacionais = dedupe_by_categoria_name_fabrica($saidas_nao_operacionais);

                $total_operacional = array_sum(array_column($receitas_operacionais, 'total_receita_mes'));
                $total_nao_operacional = array_sum(array_column($receitas_nao_operacionais, 'total_receita_mes'));
                $total_tributos = array_sum(array_column($tributos, 'total_receita_mes'));
                $total_custo_variavel = array_sum(array_column($custo_variavel, 'total_receita_mes'));
                $total_custo_fixo = array_sum(array_column($custo_fixo, 'total_receita_mes'));
                $total_despesa_fixa = array_sum(array_column($despesa_fixa, 'total_receita_mes'));
                $total_despesa_venda = array_sum(array_column($despesa_venda, 'total_receita_mes'));
                $total_investimento_interno = array_sum(array_column($investimento_interno, 'total_receita_mes'));
                $total_investimento_externo = array_sum(array_column($investimento_externo, 'total_receita_mes'));
                $total_amortizacao = array_sum(array_column($amortizacao, 'total_receita_mes'));
                $total_saidas_nao_operacionais = array_sum(array_column($saidas_nao_operacionais, 'total_receita_mes'));
                $total_retirada_de_lucro = array_sum(array_column($retirada_de_lucro, 'total_receita_mes'));

                // Total considerado como RECEITA BRUTA (exclui RECEITAS NÃO OPERACIONAIS)
                $total_geral_operacional = $total_geral - $total_nao_operacional;

                // Valores operacionais (para exibição das métricas que devem ignorar receitas não operacionais)
                $receita_liquida_operacional = $total_geral_operacional - $total_tributos;
                $lucro_bruto_operacional = $receita_liquida_operacional - $total_custo_variavel;
                $lucro_liquido_operacional = $lucro_bruto_operacional - $total_custo_fixo - $total_despesa_fixa - $total_despesa_venda;

                // Valores totais (incluem RECEITAS NÃO OPERACIONAIS) — usados para IMPACTO CAIXA
                $receita_liquida_total = $total_geral - $total_tributos;
                $lucro_bruto_total = $receita_liquida_total - $total_custo_variavel;
                $lucro_liquido_total = $lucro_bruto_total - $total_custo_fixo - $total_despesa_fixa - $total_despesa_venda;
                
                // Debug: mostrar contadores e alguns exemplos
                echo "<!-- DEBUG CONTADORES: ";
                echo "Total registros: " . count($dados_receita) . " | ";
                echo "Operacionais: " . count($receitas_operacionais) . " | ";
                echo "Não operacionais: " . count($receitas_nao_operacionais) . " | ";
                echo "Total Operacional: R$ " . number_format($total_operacional, 2) . " | ";
                echo "Total Não Operacional: R$ " . number_format($total_nao_operacional, 2);
                echo " -->";
                // DEBUG: listar itens de CUSTO VARIAVEL para inspeção (útil em PROD)
                echo "<!-- DEBUG_CUSTO_VARIAVEL: count=" . count($custo_variavel) . " ";
                foreach ($custo_variavel as $cv_item) {
                    $nome = trim($cv_item['categoria'] ?? 'SEM_CATEGORIA');
                    $val = floatval($cv_item['total_receita_mes'] ?? 0);
                    echo "[" . htmlspecialchars($nome) . ":R$" . number_format($val, 2, '.', '') . "] ";
                }
                echo "-->";
                ?>

                <?php
                // --- Prefetch de todas as metas pertinentes ---
                // Isso garante que as linhas calculadas também possam exibir um valor de meta
                // (se houver) ou uma derivação a partir de componentes.
                $meta_receita_bruta = obterMeta('RECEITA BRUTA');
                // Meta exibida no cabeçalho 'RECEITA OPERACIONAL' (input com data-categoria="RECEITA OPERACIONAL")
                $meta_receita_operacional = obterMeta('RECEITA OPERACIONAL');
                $meta_operacional = obterMeta('RECEITAS OPERACIONAIS');
                $meta_tributos = obterMeta('TRIBUTOS');
                $meta_custo_variavel = obterMeta('CUSTO VARIÁVEL');
                $meta_custo_fixo = obterMeta('CUSTO FIXO');
                $meta_despesa_fixa = obterMeta('DESPESA FIXA');
                $meta_despesa_venda = obterMeta('DESPESAS DE VENDA');
                $meta_investimento_interno = obterMeta('INVESTIMENTO INTERNO');
                $meta_nao_operacional = obterMeta('RECEITAS NÃO OPERACIONAIS');
                $meta_saidas_nao_operacionais = obterMeta('SAÍDAS NÃO OPERACIONAIS');
                $meta_impacto_caixa = obterMeta('IMPACTO CAIXA');

                // Metas calculadas: pegar somente da tabela de metas. Se não existir, manter 0.
                $meta_receita_liquida = obterMeta('RECEITA LÍQUIDA');
                // Bases calculadas considerando apenas RECEITA OPERACIONAL
                $base_receita_liquida = ($total_geral_operacional - $total_tributos);

                $meta_lucro_bruto = obterMeta('LUCRO BRUTO');
                $base_lucro_bruto = ($total_geral_operacional - $total_tributos) - $total_custo_variavel;

                $meta_lucro_liquido = obterMeta('LUCRO LÍQUIDO');
                $base_lucro_liquido = (($total_geral_operacional - $total_tributos) - $total_custo_variavel) - $total_custo_fixo - $total_despesa_fixa - $total_despesa_venda;

                // Fallbacks seguros para evitar notices
                $meta_receita_bruta = $meta_receita_bruta ?? 0;
                $meta_operacional = $meta_operacional ?? 0;
                $meta_tributos = $meta_tributos ?? 0;
                $meta_custo_variavel = $meta_custo_variavel ?? 0;
                $meta_custo_fixo = $meta_custo_fixo ?? 0;
                $meta_despesa_fixa = $meta_despesa_fixa ?? 0;
                $meta_despesa_venda = $meta_despesa_venda ?? 0;
                $meta_investimento_interno = $meta_investimento_interno ?? 0;
                $meta_nao_operacional = $meta_nao_operacional ?? 0;
                $meta_saidas_nao_operacionais = $meta_saidas_nao_operacionais ?? 0;
                $meta_impacto_caixa = $meta_impacto_caixa ?? 0;
                $meta_receita_liquida = $meta_receita_liquida ?? 0;
                $meta_lucro_bruto = $meta_lucro_bruto ?? 0;
                $meta_lucro_liquido = $meta_lucro_liquido ?? 0;

                // DEBUG: imprimir metas e bases como comentário HTML para inspeção rápida
                // Checar rapidamente se as tabelas de metas possuem ao menos 1 registro
                $check_final = [];
                $check_old = [];
                try {
                    $check_final = $supabase->select('fmetasfabricafinal', [ 'select' => 'CATEGORIA', 'limit' => 1 ]);
                } catch (Exception $e) {
                    // ignorar
                }
                try {
                    $check_old = $supabase->select('fmetasfabrica', [ 'select' => 'CATEGORIA', 'limit' => 1 ]);
                } catch (Exception $e) {
                    // ignorar
                }

                $has_final = !empty($check_final) ? 1 : 0;
                $has_old = !empty($check_old) ? 1 : 0;

                echo "<!-- METAS_DEBUG: ";
                echo "meta_receita_bruta=" . number_format($meta_receita_bruta, 2, '.', '') . " ";
                echo "meta_receita_liquida=" . number_format($meta_receita_liquida, 2, '.', '') . " ";
                echo "meta_lucro_bruto=" . number_format($meta_lucro_bruto, 2, '.', '') . " ";
                echo "meta_lucro_liquido=" . number_format($meta_lucro_liquido, 2, '.', '') . " ";
                echo "base_receita_liquida=" . number_format($base_receita_liquida ?? 0, 2, '.', '') . " ";
                echo "base_lucro_bruto=" . number_format($base_lucro_bruto ?? 0, 2, '.', '') . " ";
                echo "base_lucro_liquido=" . number_format($base_lucro_liquido ?? 0, 2, '.', '') . " ";
                echo "total_geral=" . number_format($total_geral ?? 0, 2, '.', '') . " ";
                echo "total_tributos=" . number_format($total_tributos ?? 0, 2, '.', '') . " ";
                echo "total_custo_variavel=" . number_format($total_custo_variavel ?? 0, 2, '.', '') . "";
                echo " -->";
                echo "<!-- METAS_TABLES: fmetasfabricafinal=" . $has_final . " fmetasfabrica=" . $has_old . " -->";
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
                        <tr id="row-receita-bruta" data-toggle="receita-bruta" class="hover:bg-gray-700 cursor-pointer font-semibold text-green-400" onclick="toggleReceita('receita-bruta')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                RECEITA OPERACIONAL
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_receita_operacional ?? $meta_operacional ?? $meta_receita_bruta, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-receita-bruta">
                                R$ <?= number_format($total_geral_operacional, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-receita-bruta">
                                <input type="text"
                                       class="bg-transparent text-green-400 text-right w-full border-0 outline-0 valor-simulador"
                                       id="valor-sim-operacional"
                                       data-categoria="RECEITA OPERACIONAL"
                                       data-tipo="receita-operacional"
                                       data-valor-base="<?= $total_geral_operacional ?>"
                                       value="<?= number_format($total_geral_operacional, 2, ',', '.') ?>"
                                       onchange="atualizarCalculos()"
                                       onblur="formatarCampoMoeda(this)"
                                       onfocus="removerFormatacao(this)">
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-receita-bruta">
                                100,00%
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
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-operacional-sub">
                                R$ <?= number_format($total_operacional, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-operacional">
                                <?= number_format($total_geral_operacional > 0 ? ($total_operacional / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>%
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
                                <?= number_format($total_geral_operacional > 0 ? ($total_tributos / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>%
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
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual ?? 0, 0, ',', '.') ?></span>
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
                                       value="<?= number_format($total_geral_operacional > 0 ? ($valor_individual / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>"
                                       onchange="atualizarCalculosPercentualSubcategoria(this)"
                                       style="background: transparent; color: #fb923c; text-align: right; border: none; outline: none; width: 70px;"> %
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                        <?php endif; ?>

                        <!-- INVESTIMENTO EXTERNO - (moved) -->
                        <!-- removed from here to preserve ordering; rendered later after INVESTIMENTO INTERNO -->
                    
                    <!-- RECEITA LÍQUIDA - Cálculo automático -->
                    <tbody>
                        <?php // meta_receita_liquida pré-carregada acima (ver METAS_DEBUG) ?>
                        <tr class="hover:bg-gray-700 font-semibold text-green-400">
                            <td class="px-3 py-2 border-b border-gray-700">
                                RECEITA LÍQUIDA
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_receita_liquida ?? 0, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-receita-liquida">
                                R$ <?= number_format($base_receita_liquida, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-receita-liquida">
                                R$ <?= number_format($base_receita_liquida, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-receita-liquida">
                                <?= number_format($total_geral_operacional > 0 ? (($base_receita_liquida) / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>

                    <!-- CUSTO VARIÁVEL - Linha principal -->
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
                                <?= number_format($total_geral_operacional > 0 ? ($total_custo_variavel / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes dos CUSTOS VARIÁVEIS -->
                    <tbody class="subcategorias" id="sub-custo-variavel" style="display: none;">
                        <?php if (!empty($custo_variavel)): foreach ($custo_variavel as $index => $linha): ?>
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
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual ?? 0, 0, ',', '.') ?></span>
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
                                       value="<?= number_format($total_geral_operacional > 0 ? ($valor_individual / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>"
                                       onchange="atualizarCalculosPercentualSubcategoria(this)"
                                       style="background: transparent; color: #fb923c; text-align: right; border: none; outline: none; width: 70px;"> %
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                    <!-- RETIRADA DE LUCRO - Categoria pai -->
                    <?php if (!empty($retirada_de_lucro)): ?>
                    <?php $meta_retirada = obterMeta('RETIRADA DE LUCRO'); ?>
                    <tbody>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-pink-400" onclick="toggleReceita('retirada-de-lucro')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) RETIRADA DE LUCRO
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_retirada, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-retirada-de-lucro">
                                R$ <?= number_format($total_retirada_de_lucro ?? 0, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-retirada-de-lucro">
                                R$ <?= number_format($total_retirada_de_lucro ?? 0, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-retirada-de-lucro">
                                <?= number_format($total_geral_operacional > 0 ? (($total_retirada_de_lucro ?? 0) / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    <tbody class="subcategorias" id="sub-retirada-de-lucro" style="display:none;">
                        <?php foreach ($retirada_de_lucro as $index => $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'RETIRADA DE LUCRO');
                        $categoria_id = 'retirada-de-lucro-' . $index;
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual ?? 0, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-pink-300" id="valor-base-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="text" 
                                       class="bg-transparent text-pink-300 text-right w-full border-0 outline-0 valor-simulador"
                                       id="valor-sim-<?= $categoria_id ?>"
                                       data-categoria="<?= htmlspecialchars($categoria_individual) ?>"
                                       data-tipo="retirada-de-lucro"
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
                    <!-- AMORTIZAÇÃO - (moved) -->
                    <!-- removed from here to preserve ordering; rendered later after INVESTIMENTO INTERNO -->

                    <!-- LUCRO BRUTO - Cálculo automático -->
                    <tbody>
                        <?php // meta_lucro_bruto pré-carregado acima (ver METAS_DEBUG) ?>
                        <tr class="hover:bg-gray-700 font-semibold text-green-400">
                            <td class="px-3 py-2 border-b border-gray-700">
                                LUCRO BRUTO
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_lucro_bruto ?? 0, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-lucro-bruto">
                                R$ <?= number_format($base_lucro_bruto, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-lucro-bruto">
                                R$ <?= number_format($base_lucro_bruto, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-lucro-bruto">
                                <?= number_format($total_geral_operacional > 0 ? (($base_lucro_bruto) / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>%
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
                                <?= number_format($total_geral_operacional > 0 ? ($total_custo_fixo / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>%
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
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual ?? 0, 0, ',', '.') ?></span>
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
                                <?= number_format($total_geral_operacional > 0 ? ($valor_individual / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>%
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
                                <?= number_format($total_geral_operacional > 0 ? ($total_despesa_fixa / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>%
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
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual ?? 0, 0, ',', '.') ?></span>
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
                                <?= number_format($total_geral_operacional > 0 ? ($total_despesa_venda / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>%
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
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual ?? 0, 0, ',', '.') ?></span>
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
                                       value="<?= number_format($total_geral_operacional > 0 ? ($valor_individual / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>"
                                       onchange="atualizarCalculosPercentualSubcategoria(this)"
                                       style="background: transparent; color: #fb923c; text-align: right; border: none; outline: none; width: 70px;"> %
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- LUCRO LÍQUIDO - Cálculo automático final -->
                    <?php 
                    // Lucro líquido operacional (usar base operacional)
                    $lucro_liquido = (($total_geral_operacional - $total_tributos) - $total_custo_variavel) - $total_custo_fixo - $total_despesa_fixa - $total_despesa_venda;
                    ?>
                    <?php // meta_lucro_liquido pré-carregado acima (ver METAS_DEBUG) ?>
                    <tbody>
                        <tr class="hover:bg-gray-700 font-bold text-green-400 bg-green-900 bg-opacity-20">
                            <td class="px-3 py-3 border-b-2 border-green-600 font-bold">
                                LUCRO LÍQUIDO
                            </td>
                            <td class="px-3 py-3 border-b-2 border-green-600 text-center">
                                <span class="text-xs text-gray-500 font-semibold">R$ <?= number_format($meta_lucro_liquido ?? 0, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-3 border-b-2 border-green-600 text-right font-mono font-bold" id="valor-base-lucro-liquido">
                                R$ <?= number_format($lucro_liquido, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-3 border-b-2 border-green-600 text-right font-mono font-bold bg-blue-900" id="valor-sim-lucro-liquido">
                                R$ <?= number_format($lucro_liquido, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-3 border-b-2 border-green-600 text-right font-mono font-bold bg-blue-900" id="perc-lucro-liquido">
                                <?= number_format($total_geral_operacional > 0 ? ($lucro_liquido / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>%
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
                                <?= number_format($total_geral_operacional > 0 ? ($total_investimento_interno / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>%
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
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual ?? 0, 0, ',', '.') ?></span>
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
                                <?= number_format($total_geral_operacional > 0 ? ($valor_individual / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- INVESTIMENTO EXTERNO - Linha principal (após INVESTIMENTO INTERNO) -->
                    <?php if (!empty($investimento_externo)): ?>
                    <tbody>
                        <?php $meta_investimento_externo = obterMeta('INVESTIMENTO EXTERNO'); ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-blue-400" onclick="toggleReceita('investimento-externo')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) INVESTIMENTO EXTERNO
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_investimento_externo, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-investimento-externo">
                                R$ <?= number_format($total_investimento_externo, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-investimento-externo">
                                R$ <?= number_format($total_investimento_externo, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-investimento-externo">
                                <?= number_format($total_geral_operacional > 0 ? ($total_investimento_externo / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>

                    <!-- Detalhes dos INVESTIMENTOS EXTERNOS -->
                    <tbody class="subcategorias" id="sub-investimento-externo" style="display: none;">
                        <?php foreach ($investimento_externo as $index => $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'INVESTIMENTO EXTERNO');
                        $categoria_id = 'investimento-externo-' . $index;
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual ?? 0, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-blue-300" id="valor-base-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="text" 
                                       class="bg-transparent text-blue-300 text-right w-full border-0 outline-0 valor-simulador"
                                       id="valor-sim-<?= $categoria_id ?>"
                                       data-categoria="<?= htmlspecialchars($categoria_individual) ?>"
                                       data-tipo="investimento-externo"
                                       data-valor-base="<?= $valor_individual ?>"
                                       value="<?= number_format($valor_individual, 2, ',', '.') ?>"
                                       onchange="atualizarCalculos()"
                                       onblur="formatarCampoMoeda(this)"
                                       onfocus="removerFormatacao(this)">
                            </td>
                                <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-<?= $categoria_id ?>">
                                <?= number_format($total_geral_operacional > 0 ? ($valor_individual / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- AMORTIZAÇÃO - Linha principal (após INVESTIMENTO EXTERNO) -->
                    <?php if (!empty($amortizacao)): ?>
                    <tbody>
                        <?php $meta_amortizacao = obterMeta('AMORTIZAÇÃO'); ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-indigo-400" onclick="toggleReceita('amortizacao')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) AMORTIZAÇÃO
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_amortizacao, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-amortizacao">
                                R$ <?= number_format($total_amortizacao, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-amortizacao">
                                R$ <?= number_format($total_amortizacao, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-amortizacao">
                                <?= number_format($total_geral_operacional > 0 ? ($total_amortizacao / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>

                    <!-- Detalhes da AMORTIZAÇÃO -->
                    <tbody class="subcategorias" id="sub-amortizacao" style="display: none;">
                        <?php foreach ($amortizacao as $index => $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'AMORTIZAÇÃO');
                        $categoria_id = 'amortizacao-' . $index;
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual ?? 0, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-indigo-300" id="valor-base-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="text" 
                                       class="bg-transparent text-indigo-300 text-right w-full border-0 outline-0 valor-simulador"
                                       id="valor-sim-<?= $categoria_id ?>"
                                       data-categoria="<?= htmlspecialchars($categoria_individual) ?>"
                                       data-tipo="amortizacao"
                                       data-valor-base="<?= $valor_individual ?>"
                                       value="<?= number_format($valor_individual, 2, ',', '.') ?>"
                                       onchange="atualizarCalculos()"
                                       onblur="formatarCampoMoeda(this)"
                                       onfocus="removerFormatacao(this)">
                            </td>
                                <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-<?= $categoria_id ?>">
                                <?= number_format($total_geral_operacional > 0 ? ($valor_individual / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- RECEITAS NÃO OPERACIONAIS - Linha principal (separada) -->
                    <?php if (!empty($receitas_nao_operacionais) || (!empty($total_nao_operacional) && $total_nao_operacional > 0)): ?>
                    <?php 
                    $meta_nao_operacional = obterMeta('RECEITAS NÃO OPERACIONAIS');
                    ?>
                    <tbody>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-blue-300" onclick="toggleReceita('nao-operacionais')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                RECEITAS NÃO OPERACIONAIS
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_nao_operacional, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono">
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
                    </tbody>
                    <?php endif; ?>

                    <!-- SAÍDAS NÃO OPERACIONAIS - Linha principal -->
                    <?php if (!empty($saidas_nao_operacionais) || (!empty($total_saidas_nao_operacionais) && $total_saidas_nao_operacionais > 0)): ?>
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
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono">
                                R$ <?= number_format($total_saidas_nao_operacionais, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="text" 
                                       class="bg-transparent text-red-300 text-right w-full border-0 outline-0 valor-simulador"
                                       id="valor-sim-saidas-nao-operacionais"
                                       data-categoria="SAÍDAS NÃO OPERACIONAIS"
                                       data-tipo="saidas-nao-operacionais"
                                       data-valor-base="<?= $total_saidas_nao_operacionais ?>"
                                       value="<?= number_format($total_saidas_nao_operacionais, 2, ',', '.') ?>"
                                       onchange="atualizarCalculos()"
                                       onblur="formatarCampoMoeda(this)"
                                       onfocus="removerFormatacao(this)">
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-saidas-nao-operacionais">
                                <?= number_format($total_geral_operacional > 0 ? ($total_saidas_nao_operacionais / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes das SAÍDAS NÃO OPERACIONAIS -->
                    <?php // DEBUG: contagem e total para inspeção rápida ?>
                    <?php echo "<!-- DEBUG SAIDAS: count=" . count(
                        $saidas_nao_operacionais
                    ) . " total=" . number_format($total_saidas_nao_operacionais, 2, '.', '') . " -->"; ?>
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
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual ?? 0, 0, ',', '.') ?></span>
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
                    // Calcular IMPACTO CAIXA baseado na lógica do index5.php.
                    // Incluir RECEITAS NÃO OPERACIONAIS no cálculo (receitas não operacionais aumentam o caixa,
                    // enquanto as SAÍDAS NÃO OPERACIONAIS diminuem). Logo:
                    // IMPACTO CAIXA = LUCRO LÍQUIDO + RECEITAS NÃO OPERACIONAIS - INVESTIMENTO INTERNO - INVESTIMENTO EXTERNO - AMORTIZAÇÃO - SAÍDAS NÃO OPERACIONAIS - RETIRADA DE LUCRO
                    $impacto_caixa = (
                        $lucro_liquido
                        + ($total_nao_operacional ?? 0)
                        - ($total_investimento_interno ?? 0)
                        - ($total_investimento_externo ?? 0)
                        - ($total_amortizacao ?? 0)
                        - ($total_saidas_nao_operacionais ?? 0)
                        - ($total_retirada_de_lucro ?? 0)
                    );
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
                                <?= number_format($total_geral_operacional > 0 ? (($impacto_caixa) / $total_geral_operacional) * 100 : 0, 2, ',', '.') ?>%
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
        
        <p class="text-gray-600 mb-4">Selecione os anos e os meses para salvar as metas financeiras:</p>
        
        <!-- Seletor de Anos (múltiplos) -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">📅 Anos:</label>
            <div class="grid grid-cols-3 gap-2 mb-2">
                <?php
                $anoAtual = date('Y');
                for ($ano = $anoAtual - 1; $ano <= $anoAtual + 5; $ano++) {
                    $checked = ($ano == $anoAtual) ? 'checked' : '';
                    echo "
                    <label class='flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded border border-gray-200'>
                        <input type='checkbox' value='$ano' class='ano-checkbox' id='ano-$ano' $checked>
                        <span class='text-sm font-medium'>$ano</span>
                    </label>
                    ";
                }
                ?>
            </div>
            <div class="flex gap-2 text-xs">
                <button type="button" onclick="selecionarTodosAnos()" class="text-blue-600 hover:text-blue-800">
                    ✅ Selecionar Todos
                </button>
                <button type="button" onclick="desmarcarTodosAnos()" class="text-gray-600 hover:text-gray-800">
                    ❌ Desmarcar Todos
                </button>
            </div>
        </div>
        
        <label class="block text-sm font-medium text-gray-700 mb-2">📅 Meses:</label>
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
// Toggle de debug cliente: defina `window.simulador_debug_verbose = true` no console para ver logs detalhados
window.simulador_debug_verbose = window.simulador_debug_verbose || false;

function toggleReceita(categoriaId) {
    // Caso especial para RECEITA BRUTA - mostra os subgrupos principais (apenas os cabeçalhos)
    if (categoriaId === 'receita-bruta') {
        var subReceitaBruta = document.getElementById('sub-receita-bruta');
        
        if (subReceitaBruta) {
            var computed = window.getComputedStyle(subReceitaBruta);
            if (!computed || computed.display === 'none') {
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
            var computed = window.getComputedStyle(subcategorias);
            if (!computed || computed.display === 'none') {
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

// Parseia strings no formato pt-BR e en-US com segurança
function parseBRNumber(str) {
    if (str === null || str === undefined) return 0;
    str = str.toString().trim();
    if (str === '') return 0;
    // Remover espaços e símbolo R$
    str = str.replace(/\s/g, '').replace(/R\$/g, '');

    // Se contém vírgula, assumimos formato pt-BR: milhares com ponto, decimal com vírgula
    if (str.indexOf(',') !== -1) {
        // remover pontos de milhares
        str = str.replace(/\./g, '');
        // manter dígitos, sinais e vírgula
        str = str.replace(/[^0-9,\-]/g, '');
        // trocar vírgula por ponto para parseFloat
        str = str.replace(',', '.');
    } else {
        // Formato com ponto decimal (ex: 1234.56) ou apenas dígitos
        str = str.replace(/[^0-9\.\-]/g, '');
    }

    const n = parseFloat(str);
    return isNaN(n) ? 0 : n;
}



function atualizarCalculosPercentualSubcategoria(inputPercentual) {
    // Obter receita bruta total (usar apenas RECEITA OPERACIONAL como base)
    const receitaBrutaTotal = <?= $total_geral_operacional ?>;
    
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
    const receitaBrutaTotal = <?= $total_geral_operacional ?>;
    let totalCategoria = 0;
    
    // Somar todos os valores das subcategorias
    const subcategorias = document.querySelectorAll('[id^="valor-sim-' + prefixo + '-"]');
    
    subcategorias.forEach(elemento => {
        const valorTexto = elemento.textContent || elemento.innerText;
        totalCategoria += parseBRNumber(valorTexto) || 0;
    });
    
    // Atualizar o valor total da categoria pai
    const elementoValor = document.getElementById(elementoValorId);
    if (elementoValor) {
        const formatted = 'R$ ' + totalCategoria.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        if (elementoValor.tagName === 'INPUT' || elementoValor.tagName === 'TEXTAREA') {
            elementoValor.value = totalCategoria.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } else {
            elementoValor.textContent = formatted;
        }
    }
    
    // Atualizar o percentual da categoria pai
    const elementoPerc = document.getElementById(elementoPercId);
    if (elementoPerc && receitaBrutaTotal > 0) {
        const percentualCategoria = (totalCategoria / receitaBrutaTotal) * 100;
        const pctFormatted = percentualCategoria.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
        if (elementoPerc.tagName === 'INPUT' || elementoPerc.tagName === 'TEXTAREA') {
            elementoPerc.value = percentualCategoria.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } else {
            elementoPerc.textContent = pctFormatted;
        }
    }
}

function atualizarCalculos() {
    // Buscar todos os inputs de simulação (apenas valores, não percentuais)
    const inputs = document.querySelectorAll('.valor-simulador');

    // DEBUG: listar inputs e valores para depuração de soma
    if (window.simulador_debug_verbose) {
        try {
            console.group('ATUALIZAR_CALCULOS: inputs encontrados: ' + inputs.length);
            inputs.forEach(i => {
                try {
                    const raw = (i.value !== undefined) ? i.value : (i.textContent || i.innerText || '');
                    console.log('INPUT', i.id || '(no-id)', 'tipo=' + (i.dataset.tipo || ''), 'raw="' + raw + '"', 'parsed=', parseBRNumber(raw));
                } catch (e) {
                    console.log('INPUT_ERROR', i, e);
                }
            });
            console.groupEnd();
        } catch (e) {
            console.warn('Erro ao debugar inputs em atualizarCalculos:', e);
        }
    }
    
    
    let totalReceitas = 0;
    let totalTributos = 0;
    let totalOperacional = 0;
    let totalNaoOperacional = 0;
    let totalCustoVariavel = 0;
    let totalCustoFixo = 0;
    let totalDespesaFixa = 0;
    let totalDespesaVenda = 0;
    let totalInvestimentoInterno = 0;
    let totalInvestimentoExterno = 0;
    let totalAmortizacao = 0;
    let totalRetiradaDeLucro = 0;
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
        } else if (tipo === 'investimento-externo') {
            totalInvestimentoExterno += valor;
        } else if (tipo === 'amortizacao') {
            totalAmortizacao += valor;
        } else if (tipo === 'retirada-de-lucro') {
            totalRetiradaDeLucro += valor;
        } else if (tipo === 'saidas-nao-operacionais') {
            // Evitar contar o total agregado (linha pai) junto com as subcategorias.
            // Os inputs das subcategorias usam ids como 'valor-sim-saidas-nao-operacionais-<index>'
            // enquanto o pai tem id exato 'valor-sim-saidas-nao-operacionais'.
            if (inputId && inputId !== 'valor-sim-saidas-nao-operacionais') {
                totalSaidasNaoOperacionais += valor;
            }
        }
    });
    
    // CALCULAR VALORES DAS CATEGORIAS PAI BASEADOS NO PERCENTUAL SOBRE FATURAMENTO
    // TRIBUTOS - calcular como percentual sobre faturamento
    let percTributos = 0;
    const elementoPercTributos = document.getElementById('perc-tributos');
    if (elementoPercTributos) {
        if (elementoPercTributos.tagName === 'INPUT') {
            // se for input editável, use a função utilitária que já lida com percentuais
            percTributos = obterValorNumericoPercentual(elementoPercTributos);
        } else {
            const percTexto = elementoPercTributos.textContent || elementoPercTributos.innerText;
            percTributos = parseBRNumber(percTexto) || 0;
        }
    }
    // TRIBUTOS calculados sobre RECEITA OPERACIONAL
    totalTributos = (totalOperacional * percTributos) / 100;
    
    // CUSTO VARIÁVEL - calcular como percentual sobre faturamento
    let percCustoVariavel = 0;
    const elementoPercCustoVariavel = document.getElementById('perc-custo-variavel');
    if (elementoPercCustoVariavel) {
        if (elementoPercCustoVariavel.tagName === 'INPUT') {
            percCustoVariavel = obterValorNumericoPercentual(elementoPercCustoVariavel);
        } else {
            const percTexto = elementoPercCustoVariavel.textContent || elementoPercCustoVariavel.innerText;
            percCustoVariavel = parseBRNumber(percTexto) || 0;
        }
    }
    // CUSTO VARIÁVEL calculado sobre RECEITA OPERACIONAL
    totalCustoVariavel = (totalOperacional * percCustoVariavel) / 100;
    
    // DESPESAS DE VENDA - calcular como percentual sobre faturamento
    let percDespesaVenda = 0;
    const elementoPercDespesaVenda = document.getElementById('perc-despesa-venda');
    if (elementoPercDespesaVenda) {
        if (elementoPercDespesaVenda.tagName === 'INPUT') {
            percDespesaVenda = obterValorNumericoPercentual(elementoPercDespesaVenda);
        } else {
            const percTexto = elementoPercDespesaVenda.textContent || elementoPercDespesaVenda.innerText;
            percDespesaVenda = parseBRNumber(percTexto) || 0;
        }
    }
    // DESPESAS DE VENDA calculadas sobre RECEITA OPERACIONAL
    totalDespesaVenda = (totalOperacional * percDespesaVenda) / 100;
    
    // Calcular valores derivados (usando os valores originais por enquanto)
    // RECEITA LÍQUIDA = RECEITA OPERACIONAL - TRIBUTOS
    const receitaLiquida = totalOperacional - totalTributos;
    const lucroBruto = receitaLiquida - totalCustoVariavel;
    const lucroLiquido = lucroBruto - totalCustoFixo - totalDespesaFixa - totalDespesaVenda;
    // IMPACTO CAIXA inclui RECEITAS NÃO OPERACIONAIS (aumentam o caixa)
    const impactoCaixa = lucroLiquido + totalNaoOperacional - totalInvestimentoInterno - totalInvestimentoExterno - totalAmortizacao - totalSaidasNaoOperacionais - totalRetiradaDeLucro;
    
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
        'valor-sim-investimento-externo': totalInvestimentoExterno,
        'valor-sim-amortizacao': totalAmortizacao,
        'valor-sim-retirada-de-lucro': totalRetiradaDeLucro,
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
                if (valorElement.tagName === 'INPUT' || valorElement.tagName === 'TEXTAREA') {
                    valorElement.value = novoValorAbsoluto.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                } else {
                    valorElement.textContent = formatarMoeda(novoValorAbsoluto);
                }
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
            const formatted = formatarMoeda(elementos[elementId]);
            // Se o próprio elemento for um input/textarea, atualizar value
            if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                element.value = formatted.replace('R$', '').trim();
            } else {
                // Caso a célula contenha um input (ex: td que abriga o input), atualizar o input interno
                const innerInput = element.querySelector('input, textarea');
                if (innerInput) {
                    innerInput.value = formatted.replace('R$', '').trim();
                } else {
                    // Caso contrário, atualizar o texto da célula
                    element.textContent = formatted;
                }
            }
        } else {
            if (window.simulador_debug_verbose) console.debug(`Elemento não encontrado: ${elementId}`);
        }
    });

    // Expor os totais calculados para uso por outras funções (evita re-parsear DOM imediatamente)
    try {
        window.simulador_totais = {
            totalReceitas: totalReceitas,
            totalTributos: totalTributos,
            totalOperacional: totalOperacional,
            totalNaoOperacional: totalNaoOperacional,
            totalCustoVariavel: totalCustoVariavel,
            totalCustoFixo: totalCustoFixo,
            totalDespesaFixa: totalDespesaFixa,
            totalDespesaVenda: totalDespesaVenda,
            totalInvestimentoInterno: totalInvestimentoInterno,
            totalInvestimentoExterno: totalInvestimentoExterno,
            totalAmortizacao: totalAmortizacao,
            totalRetiradaDeLucro: totalRetiradaDeLucro,
            totalSaidasNaoOperacionais: totalSaidasNaoOperacionais,
            receitaLiquida: receitaLiquida,
            lucroBruto: lucroBruto,
            lucroLiquido: lucroLiquido,
            impactoCaixa: impactoCaixa
        };
    } catch (e) {
        // ignore if environment doesn't allow
    }
    
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
        
        // Somar subcategorias de TRIBUTOS (inputs ou células)
        const inputsTributosReal = document.querySelectorAll('[id*="valor-sim-tributos-"]:not([id*="perc-"])');
        inputsTributosReal.forEach(el => {
            let valor = 0;
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                valor = parseBRNumber(el.value || '') || 0;
            } else {
                valor = parseBRNumber(el.textContent || el.innerText || '') || 0;
            }
            totalTributosReal += valor;
        });
        
        // Somar subcategorias de CUSTO VARIÁVEL  
        const inputsCustoVariavelReal = document.querySelectorAll('[id*="valor-sim-custo-variavel-"]:not([id*="perc-"])');
        inputsCustoVariavelReal.forEach(el => {
            let valor = 0;
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                valor = parseBRNumber(el.value || '') || 0;
            } else {
                valor = parseBRNumber(el.textContent || el.innerText || '') || 0;
            }
            totalCustoVariavelReal += valor;
        });
        
        // Somar subcategorias de DESPESAS DE VENDA
        const inputsDespesaVendaReal = document.querySelectorAll('[id*="valor-sim-despesa-venda-"]:not([id*="perc-"])');
        inputsDespesaVendaReal.forEach(el => {
            let valor = 0;
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                valor = parseBRNumber(el.value || '') || 0;
            } else {
                valor = parseBRNumber(el.textContent || el.innerText || '') || 0;
            }
            totalDespesaVendaReal += valor;
        });
        
        // Base de faturamento usada para percentuais: preferir RECEITA OPERACIONAL
        const baseFaturamento = (totalOperacional > 0) ? totalOperacional : (totalReceitas > 0 ? totalReceitas : 1);

        const percentuaisGrupos = {
            'perc-receita-bruta': 100.00,
            'perc-operacional': (totalOperacional / baseFaturamento) * 100,
            'perc-nao-operacional': (totalNaoOperacional / baseFaturamento) * 100,
            'perc-tributos': (totalTributosReal / baseFaturamento) * 100,
            'perc-receita-liquida': (receitaLiquida / baseFaturamento) * 100,
            'perc-custo-variavel': (totalCustoVariavelReal / baseFaturamento) * 100,
            'perc-lucro-bruto': (lucroBruto / baseFaturamento) * 100,
            'perc-custo-fixo': (totalCustoFixo / baseFaturamento) * 100,
            'perc-despesa-fixa': (totalDespesaFixa / baseFaturamento) * 100,
            'perc-despesa-venda': (totalDespesaVendaReal / baseFaturamento) * 100,
            'perc-lucro-liquido': (lucroLiquido / baseFaturamento) * 100,
            'perc-investimento-interno': (totalInvestimentoInterno / baseFaturamento) * 100,
            'perc-investimento-externo': (totalInvestimentoExterno / baseFaturamento) * 100,
            'perc-amortizacao': (totalAmortizacao / baseFaturamento) * 100,
            'perc-retirada-de-lucro': (totalRetiradaDeLucro / baseFaturamento) * 100,
            'perc-saidas-nao-operacionais': (totalSaidasNaoOperacionais / baseFaturamento) * 100,
            'perc-impacto-caixa': (impactoCaixa / baseFaturamento) * 100
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
    // Expor os totais calculados para chamadas subsequentes do goal-seek
    try {
        window.simulador_totais = {
            totalReceitas: totalReceitas,
            totalOperacional: totalOperacional,
            totalNaoOperacional: totalNaoOperacional,
            totalTributos: totalTributos,
            totalCustoVariavel: totalCustoVariavel,
            totalCustoFixo: totalCustoFixo,
            totalDespesaFixa: totalDespesaFixa,
            totalDespesaVenda: totalDespesaVenda,
            totalInvestimentoInterno: totalInvestimentoInterno,
            totalInvestimentoExterno: totalInvestimentoExterno,
            totalAmortizacao: totalAmortizacao,
            totalRetiradaDeLucro: totalRetiradaDeLucro,
            totalSaidasNaoOperacionais: totalSaidasNaoOperacionais,
            receitaLiquida: receitaLiquida,
            lucroBruto: lucroBruto,
            lucroLiquido: lucroLiquido,
            impactoCaixa: impactoCaixa
        };
    } catch(e) { console.warn('Não foi possível setar window.simulador_totais', e); }
    // Recalcular TRIBUTOS somando as subcategorias
    recalcularTotalGrupo('tributos', 'valor-sim-tributos');
    // Recalcular CUSTO VARIÁVEL somando as subcategorias  
    recalcularTotalGrupo('custo-variavel', 'valor-sim-custo-variavel');
    // Recalcular DESPESAS DE VENDA somando as subcategorias
    recalcularTotalGrupo('despesa-venda', 'valor-sim-despesa-venda');
    // Atualizar percentuais ao lado dos valores absolutos (META column)
    try { if (typeof atualizarPercentuaisAoLado === 'function') atualizarPercentuaisAoLado(); } catch(e) { /* ignore */ }
}

// Função auxiliar para recalcular total de um grupo baseado nas subcategorias
function recalcularTotalGrupo(grupoPrefix, totalElementId) {
    // Selecionar elementos (inputs ou células) que representam as subcategorias
    const elementosGrupo = document.querySelectorAll(`[id*="valor-sim-${grupoPrefix}-"]:not([id*="perc-"])`);
    let novoTotal = 0;

    elementosGrupo.forEach(el => {
        let valor = 0;
        if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
            valor = parseBRNumber(el.value || '') || 0;
        } else {
            const texto = (el.textContent || el.innerText || '').toString();
            valor = parseBRNumber(texto) || 0;
        }
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
        const valorBase = parseBRNumber(input.dataset.valorBase) || 0;
        input.value = valorBase.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    });

    // Garantir que os totais principais (receita bruta / operacional / não operacional)
    // reflitam os valores base imediatamente após restaurar.
    try {
        // Somar valores base das receitas (inputs com tipo receita*)
        let somaReceitasBase = 0;
        let somaOperacionalBase = 0;
        let somaNaoOperacionalBase = 0;

        inputs.forEach(input => {
            const tipo = input.dataset.tipo || '';
            const valorBase = parseBRNumber(input.dataset.valorBase) || 0;
            if (tipo.indexOf('receita') === 0) {
                somaReceitasBase += valorBase;
            }
            if (tipo === 'receita-operacional') somaOperacionalBase += valorBase;
            if (tipo === 'receita-nao-operacional') somaNaoOperacionalBase += valorBase;
        });

        // Atualizar elementos principais: procurar input interno antes de escrever textContent
        function aplicarValorElemento(id, valor) {
            const el = document.getElementById(id);
            const formatted = valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (!el) return;
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                el.value = formatted;
            } else {
                const inner = el.querySelector('input, textarea');
                if (inner) inner.value = formatted;
                else el.textContent = 'R$ ' + formatted;
            }
        }

        aplicarValorElemento('valor-sim-receita-bruta', somaReceitasBase);
        aplicarValorElemento('valor-sim-operacional', somaOperacionalBase);
        aplicarValorElemento('valor-sim-nao-operacional', somaNaoOperacionalBase);
    } catch (e) {
        console.warn('Erro ao aplicar totais base na restauração:', e);
    }

        // Sincronizar todos os valores do simulador com os valores exibidos na coluna 'Valor Base'
        try {
            const baseElems = document.querySelectorAll('[id^="valor-base-"]');
            baseElems.forEach(baseEl => {
                const idSuffix = baseEl.id.replace('valor-base-', '');
                const simId = 'valor-sim-' + idSuffix;
                const simEl = document.getElementById(simId);

                // Extrair número do elemento base (suporta input ou texto)
                let valorNum = 0;
                if (baseEl.tagName === 'INPUT' || baseEl.tagName === 'TEXTAREA') {
                    valorNum = parseBRNumber(baseEl.value || '') || 0;
                } else {
                    valorNum = parseBRNumber(baseEl.textContent || baseEl.innerText || '') || 0;
                }

                if (!simEl) return;

                const formatted = valorNum.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                if (simEl.tagName === 'INPUT' || simEl.tagName === 'TEXTAREA') {
                    simEl.value = formatted;
                } else {
                    const inner = simEl.querySelector('input, textarea');
                    if (inner) inner.value = formatted;
                    else simEl.textContent = 'R$ ' + formatted;
                }
            });
        } catch (e) {
            console.warn('Erro ao sincronizar valor-base -> valor-sim:', e);
        }
    
    // Resetar campos percentuais das subcategorias (TRIBUTOS, CUSTO VARIÁVEL, DESPESAS DE VENDA)
    const inputsPercentuaisSubcategorias = document.querySelectorAll('input[data-tipo^="percentual-"]');
    
    inputsPercentuaisSubcategorias.forEach(input => {
        const valorBase = parseBRNumber(input.dataset.valorBase) || 0;
        // Usar RECEITA OPERACIONAL como base para percentuais (corrige discrepância)
        const receitaBrutaOriginal = <?= $total_geral_operacional ?>;

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
                const formatted = 'R$ ' + valorBase.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                // Se a célula contém um input interno, atualizá-lo
                if (valorElement.tagName === 'INPUT' || valorElement.tagName === 'TEXTAREA') {
                    valorElement.value = valorBase.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                } else {
                    const inner = valorElement.querySelector('input, textarea');
                    if (inner) inner.value = valorBase.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    else valorElement.textContent = formatted;
                }
            }
        }
    });
    
    // Recalcular totais das categorias pai
    recalcularTotalCategoriaPai('tributos', 'valor-sim-tributos', 'perc-tributos');
    recalcularTotalCategoriaPai('custo-variavel', 'valor-sim-custo-variavel', 'perc-custo-variavel');
    recalcularTotalCategoriaPai('despesa-venda', 'valor-sim-despesa-venda', 'perc-despesa-venda');
    
    // Atualizar todos os cálculos
    atualizarCalculos();

    // Re-sincronizar COLUNA 'Valor Simulador' a partir da COLUNA 'Valor Base' após os cálculos
    // (atualizarCalculos pode reescrever alguns valores; forçamos a igualdade final)
    try {
        const baseElemsFinal = document.querySelectorAll('[id^="valor-base-"]');
        baseElemsFinal.forEach(baseEl => {
            const idSuffix = baseEl.id.replace('valor-base-', '');
            const simId = 'valor-sim-' + idSuffix;
            const simEl = document.getElementById(simId);

            let valorNum = 0;
            if (baseEl.tagName === 'INPUT' || baseEl.tagName === 'TEXTAREA') {
                valorNum = parseBRNumber(baseEl.value || '') || 0;
            } else {
                valorNum = parseBRNumber(baseEl.textContent || baseEl.innerText || '') || 0;
            }

            if (!simEl) return;

            const formatted = valorNum.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (simEl.tagName === 'INPUT' || simEl.tagName === 'TEXTAREA') {
                simEl.value = formatted;
            } else {
                const inner = simEl.querySelector('input, textarea');
                if (inner) inner.value = formatted;
                else simEl.textContent = 'R$ ' + formatted;
            }
        });
    } catch (e) {
        console.warn('Erro ao re-sincronizar valor-base -> valor-sim (final):', e);
    }
}

function calcularPontoEquilibrio() {
    // GARANTIR que todos os cálculos estejam atualizados ANTES de calcular o ponto de equilíbrio
    // If an input is currently focused, blur it to fire onblur/onchange handlers and formatting
    try { if (document.activeElement && document.activeElement.tagName === 'INPUT') document.activeElement.blur(); } catch(e) { /* ignore */ }
    // Recalcular explicitamente
    atualizarCalculos();

    // Aguardar um ciclo de render + pequeno timeout para garantir que
    // qualquer onblur/onchange/formatting tenha sido processado e que
    // `window.simulador_totais` tenha sido atualizado.
    if (typeof requestAnimationFrame === 'function') {
        requestAnimationFrame(() => {
            setTimeout(() => calcularPontoEquilibrioInterno(), 50);
        });
    } else {
        setTimeout(() => calcularPontoEquilibrioInterno(), 100);
    }
}

function calcularPontoEquilibrioInterno() {
    // Usar a mesma lógica eficaz do DRE que já funciona
    // Fórmula: Fluxo(R) = alpha * R + gamma = 0
    // R = -gamma / alpha
    
    // Função para extrair valor dos campos simulador
    function extrairValor(elementId) {
        const element = document.getElementById(elementId);
        if (!element) return 0;
        // Se for um input, ler o value (já formatado ou não). Caso contrário, usar textContent.
        if (element.value !== undefined) {
            const raw = element.value || element.getAttribute('value') || '';
            return parseBRNumber(raw) || 0;
        }
        const texto = element.textContent || element.innerText || '';
        return parseBRNumber(texto) || 0;
    }
    
    // Preferir totais já calculados por atualizarCalculos (mais determinístico)
    const simulTotais = (window && window.simulador_totais) ? window.simulador_totais : null;
    // Preferir RECEITA OPERACIONAL como base para percentuais e cálculos do goal-seek
    const receitaOperacional = simulTotais ? simulTotais.totalOperacional : extrairValor('valor-sim-operacional');
    const tributos = simulTotais ? simulTotais.totalTributos : extrairValor('valor-sim-tributos');
    const custoVariavel = simulTotais ? simulTotais.totalCustoVariavel : extrairValor('valor-sim-custo-variavel');
    const despesaVenda = simulTotais ? simulTotais.totalDespesaVenda : extrairValor('valor-sim-despesa-venda');

    // Calcular percentuais (frações de 0 a 1) usando RECEITA OPERACIONAL como denominador
    const basePercentual = receitaOperacional > 0 ? receitaOperacional : (simulTotais ? (simulTotais.totalReceitas || 1) : 1);
    const t = basePercentual > 0 ? tributos / basePercentual : 0;
    const cv = basePercentual > 0 ? custoVariavel / basePercentual : 0;
    const dv = basePercentual > 0 ? despesaVenda / basePercentual : 0;
    
    // Custos fixos (ler dos totais calculados quando disponíveis)
    const CF = simulTotais ? simulTotais.totalCustoFixo : extrairValor('valor-sim-custo-fixo');
    const DF = simulTotais ? simulTotais.totalDespesaFixa : extrairValor('valor-sim-despesa-fixa');
    const II = simulTotais ? simulTotais.totalInvestimentoInterno : extrairValor('valor-sim-investimento-interno');
    const IE = simulTotais ? simulTotais.totalInvestimentoExterno : extrairValor('valor-sim-investimento-externo');
    const AM = simulTotais ? simulTotais.totalAmortizacao : extrairValor('valor-sim-amortizacao');
    const RET = simulTotais ? simulTotais.totalRetiradaDeLucro : extrairValor('valor-sim-retirada-de-lucro');
    const SNO = simulTotais ? simulTotais.totalSaidasNaoOperacionais : extrairValor('valor-sim-saidas-nao-operacionais');
    
    // Receitas/saldos não operacionais
    const receitaNaoOp = extrairValor('valor-sim-nao-operacional');
    const saldoNaoOp = receitaNaoOp - SNO; // receita não op - saídas não op
    
    // Fórmula baseada na hierarquia exata: IMPACTO CAIXA = 0
    // IMPACTO CAIXA = LUCRO LÍQUIDO - INVESTIMENTO INTERNO - SAÍDAS NÃO OP
    // LUCRO LÍQUIDO = RECEITA BRUTA - TRIBUTOS - CUSTO VARIÁVEL - CUSTO FIXO - DESPESA FIXA - DESPESAS VENDA
    // 0 = RECEITA BRUTA×(1 - t - cv - dv) - CF - DF - II - SNO
    // RECEITA BRUTA×(1 - t - cv - dv) = CF + DF + II + SNO
    
    const custosVariaveisPorcentual = t + cv + dv; // fração sobre RECEITA OPERACIONAL
    const custosFixosTotal = CF + DF + II + IE + AM + SNO + RET; // valores absolutos que precisam ser cobertos (sem considerar receita não operacional)

    const alpha = 1 - custosVariaveisPorcentual; // margem disponível sobre RECEITA OPERACIONAL
    // Resolver a equação IMPACTO_CAIXA = 0 adequadamente:
    // 0 = Rop*(1 - t - cv - dv) - custosFixosTotal + receitaNaoOp
    // => Rop*(alpha) = custosFixosTotal - receitaNaoOp
    const numerador = custosFixosTotal - receitaNaoOp;
    const receitaOperacionalNecessaria = numerador / alpha;
    
    if (Math.abs(alpha) < 0.0001) {
        alert('❌ Margem insuficiente! Os custos variáveis somam praticamente 100% da receita.');
        return;
    }
    if (!isFinite(receitaOperacionalNecessaria) || receitaOperacionalNecessaria <= 0) {
        alert('❌ Não existe receita operacional positiva que zere o IMPACTO CAIXA com os parâmetros atuais.');
        return;
    }
    
    // Calcular receita operacional necessária
    const receitaOperacionalEquilibrio = receitaOperacionalNecessaria;
    // Para exibição também calculamos a receita bruta total necessária (inclui receitas não operacionais)
    const receitaBrutaNecessaria = receitaOperacionalEquilibrio + receitaNaoOp;
    
    if (receitaOperacionalEquilibrio <= 0) {
        alert('❌ Receita operacional calculada é negativa. Reduza a receita não operacional ou os custos fixos.');
        return;
    }
    

    
    // Confirmar com o usuário
    const confirmacao = confirm(`🎯 PONTO DE EQUILÍBRIO - IMPACTO CAIXA = 0

📊 RECEITA OPERACIONAL NECESSÁRIA:
R$ ${receitaOperacionalEquilibrio.toLocaleString('pt-BR', {minimumFractionDigits: 2})}

📈 RECEITA BRUTA TOTAL:
R$ ${receitaBrutaNecessaria.toLocaleString('pt-BR', {minimumFractionDigits: 2})}

📊 CUSTOS A COBRIR:
• Custo Fixo: R$ ${CF.toLocaleString('pt-BR', {minimumFractionDigits: 2})}
• Despesa Fixa: R$ ${DF.toLocaleString('pt-BR', {minimumFractionDigits: 2})}
• Investimento Interno: R$ ${II.toLocaleString('pt-BR', {minimumFractionDigits: 2})}
• Investimento Externo: R$ ${IE.toLocaleString('pt-BR', {minimumFractionDigits: 2})}
• Amortização: R$ ${AM.toLocaleString('pt-BR', {minimumFractionDigits: 2})}
• Retirada de Lucro: R$ ${RET.toLocaleString('pt-BR', {minimumFractionDigits: 2})}
• Saídas Não Op.: R$ ${SNO.toLocaleString('pt-BR', {minimumFractionDigits: 2})}
➖ Receita Não Op.: R$ ${receitaNaoOp.toLocaleString('pt-BR', {minimumFractionDigits: 2})}

  • Tributos: ${(t * 100).toFixed(2)}%
  • Despesas Venda: ${(dv * 100).toFixed(2)}%
  • Margem Líquida: ${(alpha * 100).toFixed(2)}%

📌 CUSTOS FIXOS + INVESTIMENTOS: R$ ${(CF + DF + II + IE + AM + RET).toLocaleString('pt-BR', {minimumFractionDigits: 2})}

Deseja aplicar estes valores ao simulador?`);

    if (!confirmacao) return;

    // Aplicar os valores calculados
    // Aplicar receita operacional calculada (como no DRE)
    const inputReceitaOperacional = document.getElementById('valor-sim-operacional');
    // Mais robusto: procurar input direto ou input dentro do TD pai
    const formattedReceita = receitaOperacionalEquilibrio.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    let elReceita = document.getElementById('valor-sim-operacional');
    if (!elReceita) {
        const tdReceita = document.getElementById('valor-sim-receita-bruta');
        if (tdReceita) elReceita = tdReceita.querySelector('input, textarea');
    }

    console.log('PONTO_EQ: calculado receitaOperacionalEquilibrio=', receitaOperacionalEquilibrio, 'formatado=', formattedReceita, 'inputEncontrado=', !!elReceita);

    if (elReceita) {
        try {
            console.log('PONTO_EQ: valor antigo do input:', elReceita.value || elReceita.textContent || '');
            // Se for input/textarea, setar value; caso contrário, setar textContent
            if (elReceita.tagName === 'INPUT' || elReceita.tagName === 'TEXTAREA') {
                elReceita.value = formattedReceita;
                // Forçar formatação e disparar blur para handlers vinculados
                try { formatarCampoMoeda(elReceita); } catch(e) { /* ignore */ }
                try { elReceita.blur(); } catch(e) { /* ignore */ }
            } else {
                elReceita.textContent = formattedReceita;
            }

            // Disparar eventos para garantir que handlers atuem
            if (elReceita.dispatchEvent) {
                elReceita.dispatchEvent(new Event('input', { bubbles: true }));
                elReceita.dispatchEvent(new Event('change', { bubbles: true }));
            }
            console.log('PONTO_EQ: valor aplicado ao input.');
        } catch (err) {
            console.warn('PONTO_EQ: falha ao aplicar valor no input:', err);
        }
    } else {
        console.warn('PONTO_EQ: input `valor-sim-operacional` não encontrado no DOM. Tentando atualizar célula pai, MAS ATENÇÃO: se não houver input a soma não será atualizada.');
        const tdReceita = document.getElementById('valor-sim-receita-bruta');
        if (tdReceita) tdReceita.textContent = 'R$ ' + formattedReceita;
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
            valorBase: parseBRNumber(input.dataset.valorBase) || 0,
            valorSimulador: parseBRNumber(input.value) || 0,
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
        const valorBase = parseBRNumber(input.dataset.valorBase) || 0;
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
                try { if (typeof atualizarPercentuaisAoLado === 'function') atualizarPercentuaisAoLado(); } catch(e) { /* ignore */ }
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

function selecionarTodosAnos() {
    const checkboxes = document.querySelectorAll('.ano-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
}

function desmarcarTodosAnos() {
    const checkboxes = document.querySelectorAll('.ano-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
}

function salvarMetasFinanceiras() {
    // Obter anos selecionados
    const anosSelecionados = [];
    const checkboxesAnos = document.querySelectorAll('.ano-checkbox:checked');
    
    if (checkboxesAnos.length === 0) {
        alert('⚠️ Selecione pelo menos um ano para salvar as metas!');
        return;
    }
    
    checkboxesAnos.forEach(checkbox => {
        anosSelecionados.push(checkbox.value);
    });
    
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
    console.log('📅 Anos selecionados:', anosSelecionados);
    
    if (metasFinanceiras.length === 0) {
        alert('⚠️ Nenhuma meta com valor foi encontrada no simulador!');
        return;
    }
    
    // Separar categorias pai e subcategorias para debug
    const categoriasPai = metasFinanceiras.filter(meta => meta.subcategoria === '');
    const subcategorias = metasFinanceiras.filter(meta => meta.subcategoria !== '');
    
    // Preview das categorias pai
    let previewMetas = 'CATEGORIAS PAI:\n';
    categoriasPai.slice(0, 5).forEach(meta => {
        previewMetas += `• ${meta.categoria}: R$ ${meta.meta.toFixed(2)}\n`;
    });
    
    if (categoriasPai.length > 5) {
        previewMetas += `• ... e mais ${categoriasPai.length - 5} categorias pai\n`;
    }
    
    // Preview das subcategorias
    previewMetas += '\nSUBCATEGORIAS:\n';
    subcategorias.slice(0, 5).forEach(meta => {
        previewMetas += `• ${meta.categoria} → ${meta.subcategoria}: R$ ${meta.meta.toFixed(2)}\n`;
    });
    
    if (subcategorias.length > 5) {
        previewMetas += `• ... e mais ${subcategorias.length - 5} subcategorias\n`;
    }
    
    // Confirmar com usuário
    const totalCombinacoes = anosSelecionados.length * mesesSelecionados.length;
    const confirmacao = confirm(
        `🎯 SALVAR METAS FINANCEIRAS\n\n` +
        `📅 Anos: ${anosSelecionados.join(', ')}\n` +
        `📅 Meses: ${mesesSelecionados.length} selecionado(s)\n` +
        `📊 Total de combinações: ${totalCombinacoes} (anos × meses)\n` +
        `📊 Metas encontradas: ${metasFinanceiras.length}\n\n` +
        `Dados coletados da coluna "Valor Simulador (R$)":\n` +
        previewMetas +
        `\nDeseja salvar essas metas na base de dados?`
    );
    
    if (!confirmacao) return;
    
    // Enviar dados para salvamento
    enviarMetasParaServidor(mesesSelecionados, metasFinanceiras, anosSelecionados);
}

function coletarMetasDoSimulador() {
    const metas = [];
    
    // CATEGORIAS PAI: Usar IDs diretos dos elementos
    const categoriasPrincipais = [
        // Cabeçalho principal do simulador está identificado pelo input `valor-sim-operacional` (exibe "RECEITA OPERACIONAL")
        { id: 'valor-sim-operacional', nome: 'RECEITA OPERACIONAL', percId: 'perc-receita-bruta' },
        // Linha resumo das receitas operacionais (subgrupo) — salvar também como categoria própria
        { id: 'valor-sim-operacional-sub', nome: 'RECEITAS OPERACIONAIS', percId: 'perc-operacional' },
        { id: 'valor-sim-tributos', nome: 'TRIBUTOS', percId: 'perc-tributos' },
        { id: 'valor-sim-receita-liquida', nome: 'RECEITA LÍQUIDA', percId: 'perc-receita-liquida' },
        { id: 'valor-sim-custo-variavel', nome: 'CUSTO VARIÁVEL', percId: 'perc-custo-variavel' },
        { id: 'valor-sim-lucro-bruto', nome: 'LUCRO BRUTO', percId: 'perc-lucro-bruto' },
        { id: 'valor-sim-custo-fixo', nome: 'CUSTO FIXO', percId: 'perc-custo-fixo' },
        { id: 'valor-sim-despesa-fixa', nome: 'DESPESA FIXA', percId: 'perc-despesa-fixa' },
        { id: 'valor-sim-despesa-venda', nome: 'DESPESAS DE VENDA', percId: 'perc-despesa-venda' },
        { id: 'valor-sim-lucro-liquido', nome: 'LUCRO LÍQUIDO', percId: 'perc-lucro-liquido' },
        { id: 'valor-sim-investimento-interno', nome: 'INVESTIMENTO INTERNO', percId: 'perc-investimento-interno' },
        { id: 'valor-sim-investimento-externo', nome: 'INVESTIMENTO EXTERNO', percId: 'perc-investimento-externo' },
        { id: 'valor-sim-amortizacao', nome: 'AMORTIZAÇÃO', percId: 'perc-amortizacao' },
        // Incluir explicitamente RECEITAS NÃO OPERACIONAIS (pai) para ser persistida
        { id: 'valor-sim-nao-operacional', nome: 'RECEITAS NÃO OPERACIONAIS', percId: 'perc-nao-operacional' },
        { id: 'valor-sim-saidas-nao-operacionais', nome: 'SAÍDAS NÃO OPERACIONAIS', percId: 'perc-saidas-nao-operacionais' },
        { id: 'valor-sim-retirada-de-lucro', nome: 'RETIRADA DE LUCRO', percId: 'perc-retirada-de-lucro' },
        { id: 'valor-sim-impacto-caixa', nome: 'IMPACTO CAIXA', percId: 'perc-impacto-caixa' }
    ];
    
    categoriasPrincipais.forEach(cat => {
        const elementoValor = document.getElementById(cat.id);
        const elementoPerc = document.getElementById(cat.percId);
        
        if (elementoValor) {
            // Preferir input/textarea filho (quando a célula contém um input em vez de ser o input em si)
            let inputFilho = null;
            if (elementoValor.tagName === 'INPUT' || elementoValor.tagName === 'TEXTAREA') {
                inputFilho = elementoValor;
            } else {
                inputFilho = elementoValor.querySelector('input, textarea');
            }

            // Extrair VALOR SIMULADOR, priorizando input filho quando existir
            let textoValor = '';
            if (inputFilho) {
                textoValor = inputFilho.value || inputFilho.textContent || '';
            } else {
                textoValor = elementoValor.textContent || elementoValor.innerText || '';
            }

            let valorMeta = 0;
            if (textoValor) {
                const valorLimpo = textoValor.replace(/R\$\s*/, '');
                valorMeta = parseBRNumber(valorLimpo) || 0;
            }

            // Extrair PERCENTUAL — priorizar input filho dentro do elemento de percentual
            let percentual = 0;
            if (elementoPerc) {
                let percFonte = elementoPerc;
                if (elementoPerc.tagName !== 'INPUT' && elementoPerc.tagName !== 'TEXTAREA') {
                    const percInput = elementoPerc.querySelector('input, textarea');
                    if (percInput) percFonte = percInput;
                }
                const textoPerc = percFonte.value || percFonte.textContent || percFonte.innerText || '';
                if (textoPerc && textoPerc.indexOf('%') !== -1) {
                    const percLimpo = textoPerc.replace(/[^\d,\-]/g, '');
                    percentual = parseBRNumber(percLimpo) || 0;
                } else if (textoPerc) {
                    const percLimpo = textoPerc.replace(/[^\d,\-]/g, '');
                    percentual = parseBRNumber(percLimpo) || 0;
                }
            }

            // Determinar nome da categoria: preferir `data-categoria` do input filho, depois da célula
            let nomeCategoriaElemento = null;
            if (inputFilho && inputFilho.dataset && inputFilho.dataset.categoria) {
                nomeCategoriaElemento = inputFilho.dataset.categoria;
            } else if (elementoValor && elementoValor.dataset && elementoValor.dataset.categoria) {
                nomeCategoriaElemento = elementoValor.dataset.categoria;
            }
            const nomeCategoriaSalva = (nomeCategoriaElemento && nomeCategoriaElemento.trim() !== '') ? nomeCategoriaElemento.trim() : cat.nome;

            // CATEGORIA PAI (sem subcategoria)
            metas.push({
                categoria: nomeCategoriaSalva, // Campo CATEGORIA na fmetasfabrica (usa data-categoria se disponível)
                subcategoria: '',              // Vazio para categoria pai
                meta: valorMeta,               // Campo META na fmetasfabrica
                percentual: percentual         // Campo PERCENTUAL na fmetasfabrica
            });
            
            // SOLUÇÃO ESPECÍFICA: Se for DESPESAS DE VENDA, criar subcategoria COMISSÃO automaticamente
            if (cat.nome === 'DESPESAS DE VENDA' && valorMeta > 0) {
                metas.push({
                    categoria: 'DESPESAS DE VENDA',  // Campo CATEGORIA na fmetasfabrica
                    subcategoria: 'COMISSÃO',        // Campo SUBCATEGORIA na fmetasfabrica
                    meta: valorMeta,                 // Mesmo valor da categoria pai
                    percentual: percentual           // Mesmo percentual da categoria pai
                });
            }
        }
    });
    
    // SUBCATEGORIAS: Das tabelas expandidas
    const tabelasSubcategorias = [
        { id: 'sub-custo-fixo', categoriaPai: 'CUSTO FIXO' },
        { id: 'sub-tributos', categoriaPai: 'TRIBUTOS' },
        { id: 'sub-custo-variavel', categoriaPai: 'CUSTO VARIÁVEL' },
        { id: 'sub-despesa-fixa', categoriaPai: 'DESPESA FIXA' },
        { id: 'sub-despesas-venda', categoriaPai: 'DESPESAS DE VENDA' },
        { id: 'sub-investimento-interno', categoriaPai: 'INVESTIMENTO INTERNO' },
        { id: 'sub-investimento-externo', categoriaPai: 'INVESTIMENTO EXTERNO' },
        { id: 'sub-amortizacao', categoriaPai: 'AMORTIZAÇÃO' },
        { id: 'sub-retirada-de-lucro', categoriaPai: 'RETIRADA DE LUCRO' },
        { id: 'sub-saidas-nao-operacionais', categoriaPai: 'SAÍDAS NÃO OPERACIONAIS' }
    ];
    
    tabelasSubcategorias.forEach(tabela => {
        const elemento = document.getElementById(tabela.id);
        if (!elemento) return;
        
        const linhas = elemento.querySelectorAll('tr');
        
        linhas.forEach(linha => {
            const celulas = linha.querySelectorAll('td');
            if (celulas.length < 3) return;
            
            // Primeira célula = nome da subcategoria
            const nomeSubcategoria = celulas[0].textContent.trim();
            if (!nomeSubcategoria) return;
            
            let valorMeta = 0;
            let percentual = 0;
            
            // ABORDAGEM HÍBRIDA: IDs específicos + busca por posição
            
            // 1. Tentar por IDs conhecidos primeiro
            const elementosComId = linha.querySelectorAll('[id]');
            elementosComId.forEach(elemento => {
                const id = elemento.id;
                
                if (id.includes('valor-sim-')) {
                    let textoValor = '';
                    if (elemento.tagName === 'INPUT') {
                        textoValor = elemento.value;
                    } else {
                        textoValor = elemento.textContent || elemento.innerText || '';
                    }
                    
                    if (textoValor.includes('R$')) {
                        const valorLimpo = textoValor.replace(/R\$\s*/, '');
                        valorMeta = parseBRNumber(valorLimpo) || 0;
                    }
                }
                
                if (id.includes('perc-')) {
                    let textoPerc = '';
                    if (elemento.tagName === 'INPUT') {
                        textoPerc = elemento.value;
                    } else {
                        textoPerc = elemento.textContent || elemento.innerText || '';
                    }
                    
                    if (textoPerc) {
                        const percLimpo = textoPerc.replace(/[^\d,\-]/g, '');
                        percentual = parseBRNumber(percLimpo) || 0;
                    }
                }
            });
            
            // 2. BUSCA POR POSIÇÃO: Para tabelas com estrutura padrão
            // Baseado na estrutura: [Nome] [Meta] [Valor Base] [Valor Sim] [Percentual]
            if (celulas.length >= 5) {
                // Última célula geralmente é o percentual (pode ser input ou texto)
                const ultimaCelula = celulas[celulas.length - 1];
                
                // Buscar input dentro da célula (para percentuais editáveis)
                const inputPerc = ultimaCelula.querySelector('input');
                if (inputPerc && percentual === 0) {
                    const valuePerc = inputPerc.value || '';
                    if (valuePerc) {
                        const percLimpo = valuePerc.replace(/[^\d,\-]/g, '');
                        percentual = parseBRNumber(percLimpo) || 0;
                    }
                }
                
                // Se não há input, pegar texto da célula
                if (percentual === 0) {
                    const textoPerc = ultimaCelula.textContent.trim();
                    if (textoPerc.includes('%')) {
                        const percLimpo = textoPerc.replace(/[^\d,\-]/g, '');
                        percentual = parseBRNumber(percLimpo) || 0;
                    }
                }
                
                // Penúltima célula geralmente é o valor simulador
                if (valorMeta === 0 && celulas.length >= 4) {
                    const penultimaCelula = celulas[celulas.length - 2];
                    const textoValor = penultimaCelula.textContent.trim();
                    if (textoValor.includes('R$')) {
                        const valorLimpo = textoValor.replace(/R\$\s*/, '');
                        valorMeta = parseBRNumber(valorLimpo) || 0;
                    }
                }
            }
            
            // 3. FALLBACK: Busca por texto em todas as células
            if (valorMeta === 0 || percentual === 0) {
                for (let i = 1; i < celulas.length; i++) {
                    const texto = celulas[i].textContent.trim();
                    
                    // Procurar valor monetário
                    if (texto.includes('R$') && valorMeta === 0) {
                        const valorLimpo = texto.replace(/R\$\s*/, '');
                        valorMeta = parseBRNumber(valorLimpo) || 0;
                    }
                    
                    // Procurar percentual
                    if (texto.includes('%') && !texto.includes('R$') && percentual === 0) {
                        const percLimpo = texto.replace(/[^\d,\-]/g, '');
                        percentual = parseBRNumber(percLimpo) || 0;
                    }
                }
            }
            

            
            // SUBCATEGORIA
            metas.push({
                categoria: tabela.categoriaPai,   // Campo CATEGORIA na fmetasfabrica
                subcategoria: nomeSubcategoria,   // Campo SUBCATEGORIA na fmetasfabrica  
                meta: valorMeta,                  // Campo META na fmetasfabrica
                percentual: percentual            // Campo PERCENTUAL na fmetasfabrica
            });
        });
    });
    
    // Garantir que exista uma meta pai chamada 'RECEITA OPERACIONAL'
    try {
        const existeReceitaOperacional = metas.some(m => (m.categoria || '').toString().toUpperCase().trim() === 'RECEITA OPERACIONAL');
        const existeReceitasOperacionais = metas.some(m => (m.categoria || '').toString().toUpperCase().trim() === 'RECEITAS OPERACIONAIS');

        // Se já existe RECEITA OPERACIONAL, nada a fazer
        if (!existeReceitaOperacional) {
            // Preferir duplicar a linha de RECEITAS OPERACIONAIS (quando existir e tiver valor)
            let fonte = metas.find(m => (m.categoria || '').toString().toUpperCase().trim() === 'RECEITAS OPERACIONAIS');

            // Se não encontrar, tentar qualquer variante que contenha a palavra 'RECEITA' e 'OPERACION'
            if (!fonte) {
                fonte = metas.find(m => {
                    const c = (m.categoria || '').toString().toUpperCase();
                    return c.includes('RECEITA') && c.includes('OPERACION');
                });
            }

            if (fonte && Number(fonte.meta) && Number(fonte.meta) !== 0) {
                metas.unshift({
                    categoria: 'RECEITA OPERACIONAL',
                    subcategoria: '',
                    meta: Number(fonte.meta) || 0,
                    percentual: Number(fonte.percentual) || 0
                });
            } else {
                // Fallback robusto: ler o header explicitamente
                const elHeader = document.getElementById('valor-sim-operacional');
                if (elHeader) {
                    let inputFilho = null;
                    if (elHeader.tagName === 'INPUT' || elHeader.tagName === 'TEXTAREA') {
                        inputFilho = elHeader;
                    } else {
                        inputFilho = elHeader.querySelector('input, textarea');
                    }

                    let texto = '';
                    if (inputFilho) texto = inputFilho.value || inputFilho.textContent || '';
                    else texto = elHeader.textContent || elHeader.innerText || '';

                    const valor = parseBRNumber((texto || '').toString().replace(/R\$\s*/g, '')) || 0;

                    // Tentar recuperar base para percentual do atributo data-valor-base
                    let percentual = 0;
                    const baseAttr = (inputFilho && inputFilho.dataset && inputFilho.dataset.valorBase) ? inputFilho.dataset.valorBase : (elHeader.dataset ? elHeader.dataset.valorBase : null);
                    const baseNum = baseAttr ? parseBRNumber(baseAttr.toString()) || 0 : 0;
                    if (baseNum > 0) percentual = (valor / baseNum) * 100;

                    // Só adicionar quando houver valor significativo
                    if (valor && valor !== 0) {
                        metas.unshift({
                            categoria: 'RECEITA OPERACIONAL',
                            subcategoria: '',
                            meta: valor,
                            percentual: parseFloat(percentual.toFixed(2))
                        });
                    }
                }
            }
        }
    } catch (e) {
        console.warn('Erro ao garantir RECEITA OPERACIONAL nas metas:', e);
    }

    let debugInfo = '🔍 DEBUG COMISSÃO:\n\n';
    
    // Verificar se a tabela sub-despesas-venda existe
    const tabelaDespesasVenda = document.getElementById('sub-despesa-venda');
    if (tabelaDespesasVenda) {
        debugInfo += '✅ Tabela sub-despesa-venda ENCONTRADA\n';
        const linhasDespesas = tabelaDespesasVenda.querySelectorAll('tr');
        debugInfo += `📊 ${linhasDespesas.length} linhas na tabela\n\n`;
        
        debugInfo += '📋 SUBCATEGORIAS ENCONTRADAS:\n';
        linhasDespesas.forEach((linha, index) => {
            const celulas = linha.querySelectorAll('td');
            if (celulas.length > 0) {
                const nomeSubcat = celulas[0].textContent.trim();
                const isComissao = nomeSubcat.toUpperCase().includes('COMISSÃO') || nomeSubcat.toUpperCase().includes('COMISSAO');
                
                debugInfo += `${index + 1}. "${nomeSubcat}"`;
                if (isComissao) debugInfo += ' ⭐ COMISSÃO!';
                debugInfo += '\n';
                
                // Se for comissão, mostrar detalhes das células
                if (isComissao) {
                    debugInfo += '   📱 Células:\n';
                    celulas.forEach((cel, i) => {
                        const texto = cel.textContent.trim();
                        const id = cel.id || 'sem-id';
                        debugInfo += `   ${i}: "${texto}" (${id})\n`;
                    });
                }
            }
        });
    } else {
        debugInfo += '❌ Tabela sub-despesa-venda NÃO ENCONTRADA\n';
    }
    
    // Verificar se COMISSÃO foi coletada nas metas
    const subcategorias = metas.filter(m => m.subcategoria !== '');
    debugInfo += `\n� Total de subcategorias coletadas: ${subcategorias.length}\n`;
    
    const comissaoEncontrada = metas.find(m => m.subcategoria.toUpperCase().includes('COMISSÃO') || m.subcategoria.toUpperCase().includes('COMISSAO'));
    if (comissaoEncontrada) {
        debugInfo += `✅ COMISSÃO nas metas: ${JSON.stringify(comissaoEncontrada, null, 2)}\n`;
    } else {
        debugInfo += '❌ COMISSÃO NÃO ENCONTRADA nas metas\n';
        debugInfo += '\n📋 Subcategorias coletadas:\n';
        subcategorias.forEach((s, i) => {
            debugInfo += `${i + 1}. ${s.categoria} → ${s.subcategoria}\n`;
        });
    }
    
    return metas;
}

function enviarMetasParaServidor(meses, metas, anos) {
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
        anos: Array.isArray(anos) ? anos : [anos] // Garantir que seja array
    };
    
    fetch('salvar_metas_fabrica.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(dados),
        signal: AbortSignal.timeout(120000) // 120 segundos de timeout
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
            alert(
                `✅ Metas salvas com sucesso!\n\n` +
                `� Anos: ${data.anos ? data.anos.join(', ') : 'N/A'}\n` +
                `�📊 ${data.total_registros} registros salvos\n` +
                `📆 ${data.meses_processados} meses × ${data.anos_processados || 1} anos processados`
            );
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

// Delegated click handler: rows may have data-toggle attribute (fallback to inline onclick still present)
document.addEventListener('click', function(e) {
    if (!e || !e.target) return;
    var tr = (e.target.closest) ? e.target.closest('tr[data-toggle]') : null;
    if (tr && tr.getAttribute) {
        var toggle = tr.getAttribute('data-toggle');
        if (toggle) {
            toggleReceita(toggle);
        }
    }
});

// Atualiza, ao lado do valor absoluto exibido nas células `valor-base-*`,
// um pequeno percentual dentro da célula META (2ª coluna). Preserva o conteúdo existente
// e adiciona/atualiza um `<span class="simulador-inline-pct">` com o percentual.
function atualizarPercentuaisAoLado() {
    function parseMoney(el) {
        if (!el) return 0;
        const txt = (el.value !== undefined ? (el.value || el.getAttribute('value') || '') : (el.textContent || el.innerText || '')).toString();
        if (!txt) return 0;
        const cleaned = txt.replace(/[^0-9,\-\.]/g, '').replace(/\./g, '').replace(',', '.');
        return parseFloat(cleaned) || 0;
    }

    // Obter faturamento atual (usar valor-sim-receita-bruta se presente, fallback para valor-base-receita-bruta)
    const totalEl = document.getElementById('valor-sim-receita-bruta') || document.getElementById('valor-base-receita-bruta');
    const totalGeral = parseMoney(totalEl) || 1;

    document.querySelectorAll('td[id^="valor-base-"]').forEach(td => {
        const id = td.id;
        const itemVal = parseMoney(td);

        const m = id.match(/(.+)-\d+$/);
        let parentVal = itemVal;
        if (m) {
            const parentId = m[1];
            const parentEl = document.getElementById(parentId);
            parentVal = parseMoney(parentEl);
        }

        const pctToShow = totalGeral > 0 ? (m ? (itemVal / totalGeral) * 100 : (parentVal / totalGeral) * 100) : 0;

        const tr = td.closest('tr');
        if (!tr) return;
        const metaCell = tr.cells && tr.cells.length >= 2 ? tr.cells[1] : null;
        if (!metaCell) return;

        const formatted = pctToShow.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
        const oldPct = metaCell.querySelector('.simulador-inline-pct');
        if (oldPct) oldPct.remove();
        const pctSpan = document.createElement('span');
        pctSpan.className = 'simulador-inline-pct';
        pctSpan.style.fontSize = '0.75rem';
        pctSpan.style.color = '#9ca3af';
        pctSpan.style.marginLeft = '8px';
        pctSpan.textContent = formatted;
        metaCell.appendChild(pctSpan);
    });
}

// Atualizar as anotações quando a página carrega
document.addEventListener('DOMContentLoaded', function(){
    try { atualizarPercentuaisAoLado(); } catch(e) { /* ignore */ }
});

</script>