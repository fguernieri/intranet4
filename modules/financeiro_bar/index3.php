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

// Antes de enviar qualquer saída HTML, tratar pedido de JSON de debug (se houver)
// Helper: carregar todos os registros de fcontaspagartap_vistos em páginas e construir um índice
// Increase default page size to pull more than 1000 results per page when possible.
// The helper still uses the actual returned row count to advance the offset so it
// remains robust against servers that silently cap results per request.
function buildVistosIndex($pageSize = 10000) {
    global $supabase;
    // optional globals used for inline diagnostics
    global $enable_vistos_debug, $buildVistosIndex_debug;

    $index = [];
    $offset = 0;
    $total_processed = 0;

    // initialize debug container if requested
    if (!empty($enable_vistos_debug)) {
        $buildVistosIndex_debug = [ 'pages' => [], 'total_processed' => 0, 'first_page_sample' => [] ];
    }

    while (true) {
        try {
            $rows = $supabase->select('fcontaspagarwab_vistos', [ 'select' => '*', 'limit' => $pageSize, 'offset' => $offset ]);
        } catch (Exception $e) {
            // Propagar a exceção para o chamador poder decidir
            throw $e;
        }

        if (!$rows || !is_array($rows) || count($rows) === 0) {
            break;
        }

        // record debug info for this page
        if (!empty($enable_vistos_debug)) {
            $buildVistosIndex_debug['pages'][] = [ 'offset' => $offset, 'fetched' => count($rows) ];
            if (empty($buildVistosIndex_debug['first_page_sample'])) {
                $buildVistosIndex_debug['first_page_sample'] = array_slice($rows, 0, 20);
            }
        }

        foreach ($rows as $v) {
            $ne = isset($v['nr_empresa']) ? strval(intval($v['nr_empresa'])) : '';
            $nf = isset($v['nr_filial']) ? strval(intval($v['nr_filial'])) : '';
            $nl = isset($v['nr_lanc']) ? strval(intval($v['nr_lanc'])) : '';
            $ns = isset($v['seq_lanc']) ? strval(intval($v['seq_lanc'])) : '';
            if ($ne === '' || $nf === '' || $nl === '' || $ns === '') continue;
            $k = $ne . '|' . $nf . '|' . $nl . '|' . $ns;
            $val = $v['visto'] ?? false;
            if (is_string($val)) $val = in_array(strtolower($val), ['t','true','1'], true);
            $index[$k] = ($val === true) ? true : false;
            $total_processed++;
        }

        // Increment offset by the actual number of rows returned.
        // Some servers impose a maximum per-request limit (e.g. 1000). If we always
        // increment by the requested $pageSize we can skip rows when the server
        // silently truncates responses. Using the real returned count avoids that.
        $offset += count($rows);
    }

    if (!empty($enable_vistos_debug)) {
        $buildVistosIndex_debug['total_processed'] = $total_processed;
    }

    return $index;
}

if (!empty($_GET['debug_vistos_json']) && $_GET['debug_vistos_json'] === '1') {
    // Carregar vistos (com paginação robusta via helper)
    try {
        $temp_vistos_index = buildVistosIndex();
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([ 'success' => false, 'error' => 'Erro ao carregar fcontaspagarwab_vistos', 'detail' => $e->getMessage() ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode([ 'success' => true, 'count' => count($temp_vistos_index), 'items' => $temp_vistos_index ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Endpoint de debug: verificar correspondência entre detalhes e índice de vistos
if (!empty($_GET['debug_vistos_matches']) && $_GET['debug_vistos_matches'] === '1') {
    $sample_limit = 200;
    $filters = [];
    // Se período está presente, filtrar também por data_mes (usar mesma lógica de conversão)
    if (!empty($_GET['periodo']) && preg_match('/^(\d{4})\/(\d{2})$/', $_GET['periodo'], $m)) {
        $filters['data_mes'] = 'eq.' . ($m[1] . '-' . $m[2] . '-01');
    } elseif (!empty($periodo_selecionado) && preg_match('/^(\d{4})\/(\d{2})$/', $periodo_selecionado, $m2)) {
        $filters['data_mes'] = 'eq.' . ($m2[1] . '-' . $m2[2] . '-01');
    }

    try {
        $detalhes = $supabase->select('fdespesaswab_detalhes', [ 'select' => '*', 'filters' => $filters, 'order' => 'vlr_total.desc', 'limit' => $sample_limit ]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([ 'success' => false, 'error' => 'Erro ao buscar detalhes', 'detail' => $e->getMessage() ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    $checks = [];
    $found = 0;
    $total = 0;
    if ($detalhes && is_array($detalhes)) {
        foreach ($detalhes as $d) {
            $k_e = isset($d['nr_empresa']) ? strval(intval($d['nr_empresa'])) : '';
            $k_f = isset($d['nr_filial']) ? strval(intval($d['nr_filial'])) : '';
            $k_l = isset($d['nr_lanc']) ? strval(intval($d['nr_lanc'])) : '';
            $k_s = isset($d['seq_lanc']) ? strval(intval($d['seq_lanc'])) : '';
            $vkey = ($k_e !== '' && $k_f !== '' && $k_l !== '' && $k_s !== '') ? ($k_e . '|' . $k_f . '|' . $k_l . '|' . $k_s) : null;
            $exists = ($vkey !== null && array_key_exists($vkey, $vistos_index));
            if ($exists) $found++;
            $total++;
            $checks[] = [
                'vkey' => $vkey,
                'exists' => $exists,
                'descricao' => $d['descricao'] ?? null,
                'cliente_fornecedor' => $d['cliente_fornecedor'] ?? null,
                'vlr_total' => isset($d['vlr_total']) ? floatval($d['vlr_total']) : null,
            ];
        }
    }

    header('Content-Type: application/json');
    echo json_encode([ 'success' => true, 'checked' => $total, 'found' => $found, 'sample_limit' => $sample_limit, 'checks' => $checks ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Endpoint de debug avançado: para cada detalhe (amostra) que não foi encontrado no índice,
// pesquisar diretamente na tabela fcontaspagartap_vistos para verificar se o registro existe
// mas com formatação/valores diferentes (ajuda a identificar problemas de normalização).
if (!empty($_GET['debug_vistos_mismatch']) && $_GET['debug_vistos_mismatch'] === '1') {
    $sample_limit = 200;
    $filters = [];
    if (!empty($_GET['periodo']) && preg_match('/^(\d{4})\/(\d{2})$/', $_GET['periodo'], $m)) {
        $filters['data_mes'] = 'eq.' . ($m[1] . '-' . $m[2] . '-01');
    } elseif (!empty($periodo_selecionado) && preg_match('/^(\d{4})\/(\d{2})$/', $periodo_selecionado, $m2)) {
        $filters['data_mes'] = 'eq.' . ($m2[1] . '-' . $m2[2] . '-01');
    }

    try {
        $detalhes = $supabase->select('fdespesaswab_detalhes', [ 'select' => '*', 'filters' => $filters, 'order' => 'vlr_total.desc', 'limit' => $sample_limit ]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([ 'success' => false, 'error' => 'Erro ao buscar detalhes', 'detail' => $e->getMessage() ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // carregar índice temporário com helper (paginado)
    $temp_vistos_index = [];
    try {
        $temp_vistos_index = buildVistosIndex();
    } catch (Exception $e) {
        // ignore — continuamos para tentar checar por consulta direta quando necessário
    }

    // Endpoint rápido: checar uma vkey específica no índice e na tabela
    if (!empty($_GET['debug_check_key'])) {
        $vkey_raw = $_GET['debug_check_key'];
        $vkey = trim($vkey_raw);
        $parts = explode('|', $vkey);
        $resp = [ 'success' => true, 'vkey' => $vkey, 'in_index' => false, 'index_value' => null, 'db_rows' => [] ];
        try {
            $idx = buildVistosIndex();
            $resp['in_index'] = array_key_exists($vkey, $idx);
            $resp['index_value'] = $resp['in_index'] ? $idx[$vkey] : null;
        } catch (Exception $e) {
            $resp['index_error'] = $e->getMessage();
        }

        if (count($parts) === 4) {
            list($ne, $nf, $nl, $ns) = $parts;
            try {
                $filt = [
                    'nr_empresa' => 'eq.' . intval($ne),
                    'nr_filial' => 'eq.' . intval($nf),
                    'nr_lanc' => 'eq.' . intval($nl),
                    'seq_lanc' => 'eq.' . intval($ns),
                ];
                $db_rows = $supabase->select('fcontaspargwab_vistos', [ 'select' => '*', 'filters' => $filt, 'limit' => 10 ]);
                $resp['db_rows'] = $db_rows ?: [];
            } catch (Exception $e) {
                $resp['db_error'] = $e->getMessage();
            }
        }

        header('Content-Type: application/json');
        echo json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    $checks = [];
    $total = 0;
    $found = 0;
    if ($detalhes && is_array($detalhes)) {
        foreach ($detalhes as $d) {
            $total++;
            $k_e = isset($d['nr_empresa']) ? strval(intval($d['nr_empresa'])) : '';
            $k_f = isset($d['nr_filial']) ? strval(intval($d['nr_filial'])) : '';
            $k_l = isset($d['nr_lanc']) ? strval(intval($d['nr_lanc'])) : '';
            $k_s = isset($d['seq_lanc']) ? strval(intval($d['seq_lanc'])) : '';
            $vkey = ($k_e !== '' && $k_f !== '' && $k_l !== '' && $k_s !== '') ? ($k_e . '|' . $k_f . '|' . $k_l . '|' . $k_s) : null;

            $exists_in_index = ($vkey !== null && array_key_exists($vkey, $temp_vistos_index));
            if ($exists_in_index) $found++;

            $db_rows = [];
            // se não existe no índice, buscar no banco por valores brutos (sem normalização)
            if (!$exists_in_index && $vkey !== null) {
                try {
                    $filt = [
                        'nr_empresa' => 'eq.' . $k_e,
                        'nr_filial' => 'eq.' . $k_f,
                        'nr_lanc' => 'eq.' . $k_l,
                        'seq_lanc' => 'eq.' . $k_s,
                    ];
                    $db_rows = $supabase->select('fcontaspagarwab_vistos', [ 'select' => '*', 'filters' => $filt, 'limit' => 10 ]);
                } catch (Exception $e) {
                    $db_rows = [ 'error' => $e->getMessage() ];
                }
            }

            $checks[] = [
                'vkey' => $vkey,
                'exists_in_index' => $exists_in_index,
                'descricao' => $d['descricao'] ?? null,
                'cliente_fornecedor' => $d['cliente_fornecedor'] ?? null,
                'vlr_total' => isset($d['vlr_total']) ? floatval($d['vlr_total']) : null,
                'db_rows_for_key' => $db_rows
            ];
        }
    }

    header('Content-Type: application/json');
    echo json_encode([ 'success' => true, 'checked' => $total, 'found_in_index' => $found, 'sample_limit' => $sample_limit, 'checks' => $checks ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Endpoint de debug: retornar algumas linhas completas de detalhes (útil para inspecionar campos disponíveis)
if (!empty($_GET['debug_vistos_rows']) && $_GET['debug_vistos_rows'] === '1') {
    $limit = intval($_GET['limit'] ?? 10);
    if ($limit <= 0 || $limit > 500) $limit = 10;
    $filters = [];
    if (!empty($_GET['periodo']) && preg_match('/^(\d{4})\/(\d{2})$/', $_GET['periodo'], $m)) {
        $filters['data_mes'] = 'eq.' . ($m[1] . '-' . $m[2] . '-01');
    } elseif (!empty($periodo_selecionado) && preg_match('/^(\d{4})\/(\d{2})$/', $periodo_selecionado, $m2)) {
        $filters['data_mes'] = 'eq.' . ($m2[1] . '-' . $m2[2] . '-01');
    }

    try {
        $detalhes = $supabase->select('fdespesaswab_detalhes', [ 'select' => '*', 'filters' => $filters, 'order' => 'vlr_total.desc', 'limit' => $limit ]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([ 'success' => false, 'error' => 'Erro ao buscar detalhes', 'detail' => $e->getMessage() ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode([ 'success' => true, 'count' => is_array($detalhes) ? count($detalhes) : 0, 'rows' => $detalhes ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
// Pré-carregar o índice de vistos para uso na renderização da página
$vistos_index = [];
try {
    // usar helper paginado para garantir que recuperamos todos os registros
    $vistos_index = buildVistosIndex();
} catch (Exception $e) {
    error_log('Erro ao pré-carregar fcontaspagarwab_vistos: ' . $e->getMessage());
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
            <!-- Dropdown para alternar entre páginas -->
            <div class="relative">
                <button id="pageMenuBtn" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded transition-colors">
                    Selecionar Bar ▾
                </button>
                <div id="pageMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded shadow-lg z-50">
                    <a href="index2.php" class="block px-4 py-2 text-sm text-gray-800 hover:bg-gray-100">Bar Da Fabrica</a>
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
            <span id="periodo-label">📅 Período selecionado: <?= htmlspecialchars($periodos_disponiveis[$periodo_selecionado] ?? $periodo_selecionado) ?></span>
            <button id="markAllBtn" class="ml-3 inline-flex items-center px-2 py-1 bg-green-600 hover:bg-green-700 text-white text-xs rounded" title="Marcar todos como lidos">
                Marcar tudo como lido
            </button>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($periodo_selecionado): ?>
        <?php if (!empty($dados_receita) || !empty($dados_despesa)): ?>
        <div class="bg-gray-800 rounded-lg p-4">
            <h3 class="text-lg text-gray-300 mb-3">
                Lançamentos financeiros <?= htmlspecialchars($periodos_disponiveis[$periodo_selecionado] ?? $periodo_selecionado) ?>
            </h3>
            <?php
            // $vistos_index já foi pré-carregado antes do HTML; preparar contadores/sample
            $vistos_count = count($vistos_index);
            $vistos_sample = array_slice(array_keys($vistos_index), 0, 20);
            ?>
            <?php if (!empty($_GET['debug_vistos']) && $_GET['debug_vistos'] === '1'): ?>
                <div class="bg-gray-900 rounded p-3 mb-4 text-xs text-gray-300">
                    <strong>Debug vistos (consolidado):</strong>
                    <div>Carregados: <?= intval($vistos_count ?? 0) ?></div>
                    <div style="max-height:180px;overflow:auto;margin-top:6px;background:#0b1220;padding:6px;border-radius:4px;">
                        <pre style="white-space:pre-wrap;color:#d1d5db;">Keys sample: <?= htmlspecialchars(print_r($vistos_sample ?? [], true)) ?></pre>
                    </div>
                    <div class="mt-2 text-gray-400">Para obter JSON completo: <a href="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>&debug_vistos_json=1" class="text-yellow-400">abrir JSON</a></div>
                </div>
            <?php endif; ?>
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
                $retirada_de_lucro = [];
                
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

                    // (getVkeyFromDetalhe moved to top-level to avoid nested definition)
                }

                // Função utilitária: extrair chaves numéricas de um detalhe de forma tolerante
                // Retorna um array associativo com chaves inteiras: ['ne'=>int,'nf'=>int,'nl'=>int,'ns'=>int]
                function extractNumericKeys($detalhe) {
                    // candidate field names for each key (try common variations)
                    $candidates = [
                        'ne' => ['nr_empresa','empresa','empresa_nr','nrempresa','empresa_id'],
                        'nf' => ['nr_filial','filial','nrfilial','filial_id'],
                        'nl' => ['nr_lanc','lanc','nr_lancamento','nr_lancamento','lanc_id','nr_lanc_id'],
                        'ns' => ['seq_lanc','seq','seq_lancamento','seq_lancamento','seq_id']
                    ];

                    $out = ['ne' => 0, 'nf' => 0, 'nl' => 0, 'ns' => 0];
                    foreach ($candidates as $k => $fields) {
                        foreach ($fields as $f) {
                            if (array_key_exists($f, $detalhe) && $detalhe[$f] !== null && $detalhe[$f] !== '') {
                                // normalize value to integer
                                $val = intval($detalhe[$f]);
                                $out[$k] = $val;
                                break;
                            }
                        }
                    }
                    return $out;
                }

                // Função utilitária: retornar vkey canônica a partir de um detalhe (usa extractNumericKeys)
                function getVkeyFromDetalhe($detalhe) {
                    $keys = extractNumericKeys($detalhe);
                    $k_e = strval($keys['ne']);
                    $k_f = strval($keys['nf']);
                    $k_l = strval($keys['nl']);
                    $k_s = strval($keys['ns']);
                    if ($k_e === '' || $k_f === '' || $k_l === '' || $k_s === '') return null;
                    // require >0 for empresa/filial/lanc and >=0 for seq
                    if (intval($k_e) <= 0 || intval($k_f) <= 0 || intval($k_l) <= 0 || intval($k_s) < 0) return null;
                    return $k_e . '|' . $k_f . '|' . $k_l . '|' . $k_s;
                }
                
                // Renderiza um badge legível para o status 'visto'
                function renderVistoLabel($detalhe, $visto_val = null) {
                    // Normaliza valor vindo na linha (pode ser booleano, string 't'/'true'/'1', etc.)
                    $val = null;
                    if (isset($detalhe['visto'])) {
                        $rv = $detalhe['visto'];
                        if (is_string($rv)) $rv = in_array(strtolower($rv), ['t','true','1'], true);
                        $val = ($rv === true) ? true : false;
                    } else {
                        if ($visto_val === true) $val = true;
                        elseif ($visto_val === false) $val = false;
                        else $val = null;
                    }

                    if ($val === true) {
                        return '<span class="text-xs px-2 py-0.5 rounded bg-green-600 text-white">Lido</span>';
                    } elseif ($val === false) {
                        return '<span class="text-xs px-2 py-0.5 rounded bg-orange-500 text-white">Não lido</span>';
                    } else {
                        return '<span class="text-xs text-gray-500">—</span>';
                    }
                }

                // Renderiza badge de status de pagamento
                function renderStatusBadge($detalhe) {
                    $status = isset($detalhe['status']) ? trim(strtolower($detalhe['status'])) : '';
                    
                    if ($status === 'pago') {
                        return '<span class="inline-flex items-center text-xs px-2 py-1 rounded bg-green-600 text-white">
                                    <span class="w-2 h-2 rounded-sm bg-green-300 mr-1.5"></span>
                                    Pago
                                </span>';
                    } elseif ($status === 'pendente') {
                        return '<span class="inline-flex items-center text-xs px-2 py-1 rounded bg-orange-500 text-white">
                                    <span class="w-2 h-2 rounded-sm bg-orange-300 mr-1.5"></span>
                                    Pendente
                                </span>';
                    } else {
                        return '<span class="text-xs text-gray-500">—</span>';
                    }
                }

                // Formata percentual do valor sobre a RECEITA OPERACIONAL (total_geral_operacional)
                function percentOfReceitaOperacional($valor) {
                    global $total_geral_operacional;
                    $total = floatval($total_geral_operacional ?? 0);
                    if ($total <= 0) return '0,0%';
                    $p = ($valor / $total) * 100.0;
                    return number_format($p, 1, ',', '.') . '%';
                }

                // Renderiza valor monetário com percentual ao lado (pequeno)
                function renderValorComPercent($valor) {
                    $v = floatval($valor ?? 0);
                    $formatted = 'R$ ' . number_format($v, 2, ',', '.');
                    $pct = percentOfReceitaOperacional($v);
                    return $formatted . ' <span class="text-xs text-gray-400 ml-2">(' . $pct . ')</span>';
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
                    $categoria = trim(strtoupper($linha['categoria'] ?? ''));
                    $categoria_pai = trim(strtoupper($linha['categoria_pai'] ?? ''));

                    // Separar RETIRADA DE LUCRO como grupo próprio
                    if ($categoria === 'RETIRADA DE LUCRO' || $categoria === 'RETIRADA_DE_LUCRO' || $categoria_pai === 'RETIRADA DE LUCRO' || $categoria_pai === 'RETIRADA_DE_LUCRO') {
                        $retirada_de_lucro[] = $linha;
                        continue;
                    }
                    
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
                
                // Criar arrays para detalhes de cada categoria (chave normalizada: UPPERCASE, trimmed)
                $detalhes_por_categoria = [];
                if ($dados_despesa_detalhes) {
                    foreach ($dados_despesa_detalhes as $detalhe) {
                        $categoria_raw = $detalhe['categoria'] ?? '';
                        $categoria = strtoupper(trim($categoria_raw));
                        if ($categoria === '') $categoria = 'SEM CATEGORIA';
                        if (!isset($detalhes_por_categoria[$categoria])) {
                            $detalhes_por_categoria[$categoria] = [];
                        }
                        $detalhes_por_categoria[$categoria][] = $detalhe;
                    }
                }

                // Computar mapa de categorias que contém itens não lidos
                $categoria_has_unread = [];
                foreach ($detalhes_por_categoria as $cat_name => $det_list) {
                    $has_unread = false;
                    foreach ($det_list as $ditem) {
                        // If the detalhe row explicitly contains a 'visto' flag, respect it
                        if (isset($ditem['visto'])) {
                            $rv = $ditem['visto'];
                            if (is_string($rv)) $rv = in_array(strtolower($rv), ['t','true','1'], true);
                            $val = ($rv === true) ? true : false;
                            if ($val === false) { $has_unread = true; break; }
                            continue;
                        }

                        // Otherwise try to resolve vkey in the preloaded index
                        $vkey = getVkeyFromDetalhe($ditem);
                        if ($vkey !== null) {
                            if (array_key_exists($vkey, $vistos_index)) {
                                if ($vistos_index[$vkey] === false) { $has_unread = true; break; }
                            } else {
                                // not present in index => consider not read
                                $has_unread = true; break;
                            }
                        } else {
                            // no vkey and no explicit visto => conservatively mark as unread
                            $has_unread = true; break;
                        }
                    }
                    $categoria_has_unread[$cat_name] = $has_unread;
                }

                // Helper to render a small badge when a category contains unread items
                function renderCategoryUnreadBadge($categoria) {
                    global $categoria_has_unread;
                    $k = strtoupper(trim($categoria));
                    if ($k === '') return '';
                    if (!empty($categoria_has_unread[$k])) {
                        // small dot, no text
                        return ' <span class="ml-2 inline-block w-2 h-2 rounded-full bg-orange-500" title="Contém não lidos" style="vertical-align:middle"></span>';
                    }
                    return '';
                }

                // Mapear categorias -> categoria_pai (usando dados de despesas) para computar flags nos pais
                $categoria_to_parent = [];
                if (!empty($dados_despesa)) {
                    foreach ($dados_despesa as $row) {
                        $cat = strtoupper(trim($row['categoria'] ?? ''));
                        $parent = strtoupper(trim($row['categoria_pai'] ?? ''));
                        if ($cat !== '') $categoria_to_parent[$cat] = $parent;
                    }
                }

                $categoria_pai_has_unread = [];
                foreach ($categoria_has_unread as $catname => $has) {
                    $parent = $categoria_to_parent[strtoupper(trim($catname))] ?? null;
                    if ($parent) {
                        if (!isset($categoria_pai_has_unread[$parent])) $categoria_pai_has_unread[$parent] = false;
                        if ($has) $categoria_pai_has_unread[$parent] = true;
                    }
                }

                function renderParentUnreadBadge($parent) {
                    global $categoria_pai_has_unread;
                    if (!empty($categoria_pai_has_unread[strtoupper(trim($parent))])) {
                        return ' <span class="ml-2 inline-block w-2 h-2 rounded-full bg-orange-500" title="Contém não lidos" style="vertical-align:middle"></span>';
                    }
                    return '';
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
                
                // --- Vistos: carregar tabela pequena e montar índice em memória (usando helper paginado)
                // suporta debug inline via ?debug_vistos_inline=1
                $debug_vistos_html = null;
                $show_debug_vistos_inline = (!empty($_GET['debug_vistos_inline']) && $_GET['debug_vistos_inline'] === '1');
                // enable detailed diagnostics inside the helper when requested
                $enable_vistos_debug = $show_debug_vistos_inline ? true : false;

                $vistos_index = [];
                try {
                    $vistos_index = buildVistosIndex();
                } catch (Exception $e) {
                    // falha ao carregar vistos — deixamos $vistos_index vazio
                    error_log('Erro ao carregar fcontaspagarwab_vistos: ' . $e->getMessage());
                }
                $vistos_count = count($vistos_index);
                $vistos_sample = array_slice(array_keys($vistos_index), 0, 20);

                // if inline debug requested, prepare HTML with diagnostics collected by the helper
                if ($show_debug_vistos_inline) {
                    global $buildVistosIndex_debug;
                    $diagn = $buildVistosIndex_debug ?? null;
                    $dbg = [
                        'success' => true,
                        'vistos_count' => $vistos_count,
                        'vistos_sample' => $vistos_sample,
                        'diagnostics' => $diagn,
                    ];
                    $debug_vistos_html = json_encode($dbg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                // Mostrar chaves de debug na UI quando solicitado via ?debug_show_keys=1
                $show_debug_keys = (!empty($_GET['debug_show_keys']) && $_GET['debug_show_keys'] === '1');
                // Mostrar o valor bruto da coluna 'visto' em vez do ícone por padrão.
                // Para forçar o comportamento antigo (bolinhas), passe debug_show_visto_raw=0
                $show_visto_raw = true;
                if (isset($_GET['debug_show_visto_raw'])) {
                    $show_visto_raw = $_GET['debug_show_visto_raw'] === '1';
                }
                // prepared container for inline debug output
                $debug_categorias_html = null;
                $show_debug_categorias_inline = (!empty($_GET['debug_categorias_inline']) && $_GET['debug_categorias_inline'] === '1');
                $total_investimento_interno = array_sum(array_column($investimento_interno, 'total_receita_mes'));
                $total_saidas_nao_operacionais = array_sum(array_column($saidas_nao_operacionais, 'total_receita_mes'));
                $total_retirada_de_lucro = array_sum(array_column($retirada_de_lucro, 'total_receita_mes'));
                
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

                // Endpoint de debug para inspeção das categorias/contagens (JSON)
                if (!empty($_GET['debug_categorias']) && $_GET['debug_categorias'] === '1') {
                    // coletar únicos e amostras
                    $unique_categoria_pai = array_values(array_unique(array_map(function($r){ return strtoupper(trim($r['categoria_pai'] ?? '')); }, $dados_despesa)));
                    $unique_categoria = array_values(array_unique(array_map(function($r){ return strtoupper(trim($r['categoria'] ?? '')); }, $dados_despesa)));

                    $resp = [
                        'success' => true,
                        'periodo' => $periodo_selecionado,
                        'counts' => [
                            'dados_receita' => count($dados_receita),
                            'dados_despesa' => count($dados_despesa),
                            'dados_despesa_detalhes' => is_array($dados_despesa_detalhes) ? count($dados_despesa_detalhes) : 0,
                            'receitas_operacionais' => count($receitas_operacionais),
                            'receitas_nao_operacionais' => count($receitas_nao_operacionais),
                            'tributos' => count($tributos),
                            'custo_variavel' => count($custo_variavel),
                            'custo_fixo' => count($custo_fixo),
                            'despesa_fixa' => count($despesa_fixa),
                            'despesa_venda' => count($despesa_venda),
                            'investimento_interno' => count($investimento_interno),
                            'saidas_nao_operacionais' => count($saidas_nao_operacionais),
                        ],
                        'unique_categoria_pai_sample' => array_slice($unique_categoria_pai, 0, 40),
                        'unique_categoria_sample' => array_slice($unique_categoria, 0, 40),
                        'despesa_samples' => array_slice($dados_despesa, 0, 10),
                        'detalhes_samples' => is_array($dados_despesa_detalhes) ? array_slice($dados_despesa_detalhes, 0, 10) : []
                    ];

                    // If caller requested inline HTML debug, store JSON pretty print into a variable
                    if ($show_debug_categorias_inline) {
                        $debug_categorias_html = json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                        // continue rendering page and show the debug panel below
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                        exit;
                    }
                }
                ?>
                <?php if (!empty($debug_categorias_html)): ?>
                    <div class="bg-gray-900 rounded p-3 mb-4 text-xs" style="color:#d1d5db; white-space:pre-wrap; overflow:auto; max-height:360px;">
                        <div style="font-weight:600;color:#e5e7eb;margin-bottom:6px;">DEBUG categorias (inline):</div>
                        <pre style="margin:0;color:#d1d5db;white-space:pre-wrap;"><?= htmlspecialchars($debug_categorias_html) ?></pre>
                    </div>
                <?php endif; ?>
                <?php if (!empty($debug_vistos_html)): ?>
                    <div class="bg-gray-900 rounded p-3 mb-4 text-xs" style="color:#d1d5db; white-space:pre-wrap; overflow:auto; max-height:360px;">
                        <div style="font-weight:600;color:#e5e7eb;margin-bottom:6px;">DEBUG vistos (inline):</div>
                        <pre style="margin:0;color:#d1d5db;white-space:pre-wrap;"><?= htmlspecialchars($debug_vistos_html) ?></pre>
                    </div>
                <?php endif; ?>

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
                        // Mostrar RECEITA BRUTA sem as RECEITAS NÃO OPERACIONAIS
                        $percentual_receita_bruta = calcularPercentualMeta($total_geral_operacional, $meta_receita_bruta);
                        
                        $cor_receita_bruta = obterCorBarra($percentual_receita_bruta, false);
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-green-400" onclick="toggleReceita('receita-bruta')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                RECEITA OPERACIONAL
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
                                <?= renderValorComPercent($total_geral_operacional) ?>
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
                                <?= renderValorComPercent($total_operacional) ?>
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
                                <?= htmlspecialchars($categoria_individual) ?><?= renderCategoryUnreadBadge($categoria_individual) ?>
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
                                <?= renderValorComPercent($valor_individual) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>

                    <!-- (RECEITAS NÃO OPERACIONAIS removed from RECEITA BRUTA section and will be rendered later as top-level category) -->
                    
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
                                (-) TRIBUTOS<?= renderParentUnreadBadge('TRIBUTOS') ?>
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
                                <?= renderValorComPercent($total_tributos) ?>
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
                                <?= htmlspecialchars($categoria_individual) ?><?= renderCategoryUnreadBadge($categoria_individual) ?>
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
                                <?= renderValorComPercent($valor_individual) ?>
                            </td>
                        </tr>
                        
                        <!-- Detalhes individuais da categoria (ocultos inicialmente) -->
                        <?php 
                            $cat_key = strtoupper(trim($linha['categoria'] ?? ''));
                            if ($cat_key === '') $cat_key = 'SEM CATEGORIA';
                            if (isset($detalhes_por_categoria[$cat_key])): ?>
                        <tr class="detalhes-categoria" id="det-tributo-<?= md5($linha['categoria']) ?>" style="display: none;">
                            <td colspan="2" class="px-0 py-0 border-b border-gray-700">
                                <div class="bg-gray-900 rounded-lg m-2">
                                    <div class="px-4 py-2 bg-gray-800 rounded-t-lg text-xs text-gray-400 font-semibold">
                                        Lançamentos individuais - <?= htmlspecialchars($linha['categoria']) ?><?= renderCategoryUnreadBadge($linha['categoria']) ?>
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
                                                <?php foreach ($detalhes_por_categoria[$cat_key] as $detalhe): ?>
                                                    <?php
                                                    // Priorizar a coluna 'visto' vinda da view (se existir). Caso contrário, usar fallback pelo índice em memória
                                                    if (isset($detalhe['visto'])) {
                                                        $val = $detalhe['visto'];
                                                        if (is_string($val)) $val = in_array(strtolower($val), ['t','true','1'], true);
                                                        $visto_val = ($val === true) ? true : false;
                                                    } else {
                                                        $k_e = isset($detalhe['nr_empresa']) ? strval(intval($detalhe['nr_empresa'])) : '';
                                                        $k_f = isset($detalhe['nr_filial']) ? strval(intval($detalhe['nr_filial'])) : '';
                                                        $k_l = isset($detalhe['nr_lanc']) ? strval(intval($detalhe['nr_lanc'])) : '';
                                                        $k_s = isset($detalhe['seq_lanc']) ? strval(intval($detalhe['seq_lanc'])) : '';
                                                        $vkey = ($k_e !== '' && $k_f !== '' && $k_l !== '' && $k_s !== '') ? ($k_e . '|' . $k_f . '|' . $k_l . '|' . $k_s) : null;
                                                        $visto_val = ($vkey !== null && array_key_exists($vkey, $vistos_index)) ? $vistos_index[$vkey] : null;
                                                    }
                                                ?>
                                                <tr class="hover:bg-gray-800 text-gray-300">
                                                    <td class="px-2 py-1 border-b border-gray-800 align-top">
                                                        <div class="flex items-start justify-between">
                                                            <div class="flex-1">
                                                                <div class="flex items-center gap-2 mb-1">
                                                                    <div class="font-medium text-sm text-gray-100"><?= htmlspecialchars($detalhe['descricao'] ?? 'SEM DESCRIÇÃO') ?></div>
                                                                    <?= renderStatusBadge($detalhe) ?>
                                                                </div>
                                                                <?php if (!empty($show_debug_keys)): ?>
                                                                    <div class="text-xs font-mono text-gray-400 mt-1">
                                                                        vkey: <?= htmlspecialchars(getVkeyFromDetalhe($detalhe) ?? 'null') ?>
                                                                        <span style="margin-left:8px;color:#9ca3af;">visto_val: <?= htmlspecialchars(var_export($visto_val, true)) ?></span>
                                                                        <?php
                                                                            // Show the precomputed numeric key values and a small JSON sample
                                                                            $__dbg_ne = intval($detalhe['nr_empresa'] ?? 0);
                                                                            $__dbg_nf = intval($detalhe['nr_filial'] ?? 0);
                                                                            $__dbg_nl = intval($detalhe['nr_lanc'] ?? 0);
                                                                            $__dbg_ns = intval($detalhe['seq_lanc'] ?? 0);
                                                                            $debug_sample = array_intersect_key($detalhe, array_flip(['nr_empresa','nr_filial','nr_lanc','seq_lanc','descricao','cliente_fornecedor','vlr_total','nr_documento']));
                                                                        ?>
                                                                        <div style="margin-top:4px;color:#9ca3af;">keys: <?= htmlspecialchars(implode('|', [strval($__dbg_ne), strval($__dbg_nf), strval($__dbg_nl), strval($__dbg_ns)])) ?></div>
                                                                        <div style="margin-top:4px;color:#9ca3af;">sample: <code><?= htmlspecialchars(json_encode($debug_sample, JSON_UNESCAPED_UNICODE)) ?></code></div>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div class="text-xs text-gray-400 mt-1">
                                                                    <?= htmlspecialchars($detalhe['cliente_fornecedor'] ?? '') ?>
                                                                    <span class="ml-2 text-xs text-gray-500">NUMERO DA NOTA: <?= htmlspecialchars($detalhe['nr_documento'] ?? '') ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="ml-3">
                                                                <span class="visto-badge"><?= renderVistoLabel($detalhe, $visto_val) ?></span>
                                                                <?php
                                                                    // Pre-compute keys and only render the clickable checkbox when all required keys exist
                                                                    $__keys = extractNumericKeys($detalhe);
                                                                    $__ne = $__keys['ne'];
                                                                    $__nf = $__keys['nf'];
                                                                    $__nl = $__keys['nl'];
                                                                    $__ns = $__keys['ns'];
                                                                ?>
                                                                <?php if ($__ne > 0 && $__nf > 0 && $__nl > 0 && $__ns >= 0): ?>
                                                                    <input type="checkbox" class="ml-2 w-4 h-4 cursor-pointer bg-white border-gray-300 rounded" aria-label="Marcar como lido"
                                                                        style="accent-color:#f59e0b;border:1px solid rgba(156,163,175,0.18);appearance:checkbox;-webkit-appearance:checkbox;display:inline-block;vertical-align:middle;box-shadow:0 0 0 1px rgba(0,0,0,0.15) inset;background-clip:padding-box;" 
                                                                        onclick="marcarComoLido(<?= $__ne ?>, <?= $__nf ?>, <?= $__nl ?>, <?= $__ns ?>, this)" <?= ($visto_val === true) ? 'checked' : '' ?> >
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-2 py-1 border-b border-gray-800 text-right font-mono text-orange-200 align-top">
                                                        <?= renderValorComPercent(floatval($detalhe['vlr_total'] ?? 0)) ?>
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
                    // RECEITA LÍQUIDA deve considerar a RECEITA BRUTA sem as não-operacionais
                    $receita_liquida = $total_geral_operacional - $total_tributos;
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
                                <?= renderValorComPercent($receita_liquida) ?>
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
                                (-) CUSTO VARIÁVEL<?= renderParentUnreadBadge('CUSTO VARIÁVEL') ?>
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
                                <?= renderValorComPercent($total_custo_variavel) ?>
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
                                <?= htmlspecialchars($categoria_individual) ?><?= renderCategoryUnreadBadge($categoria_individual) ?>
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
                                <?= renderValorComPercent($valor_individual) ?>
                            </td>
                        </tr>
                        
                        <?php 
                            $cat_key = strtoupper(trim($linha['categoria'] ?? ''));
                            if ($cat_key === '') $cat_key = 'SEM CATEGORIA';
                            if (isset($detalhes_por_categoria[$cat_key])): ?>
                        <tr class="detalhes-categoria" id="det-custo-variavel-<?= md5($linha['categoria']) ?>" style="display: none;">
                            <td colspan="2" class="px-0 py-0 border-b border-gray-700">
                                <div class="bg-gray-900 rounded-lg m-2">
                                    <div class="px-4 py-2 bg-gray-800 rounded-t-lg text-xs text-gray-400 font-semibold">
                                        Lançamentos individuais - <?= htmlspecialchars($linha['categoria']) ?><?= renderCategoryUnreadBadge($linha['categoria']) ?>
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
                                                <?php foreach ($detalhes_por_categoria[$cat_key] as $detalhe): ?>
                                                    <?php
                                                    if (isset($detalhe['visto'])) {
                                                        $val = $detalhe['visto'];
                                                        if (is_string($val)) $val = in_array(strtolower($val), ['t','true','1'], true);
                                                        $visto_val = ($val === true) ? true : false;
                                                    } else {
                                                        $k_e = isset($detalhe['nr_empresa']) ? strval(intval($detalhe['nr_empresa'])) : '';
                                                        $k_f = isset($detalhe['nr_filial']) ? strval(intval($detalhe['nr_filial'])) : '';
                                                        $k_l = isset($detalhe['nr_lanc']) ? strval(intval($detalhe['nr_lanc'])) : '';
                                                        $k_s = isset($detalhe['seq_lanc']) ? strval(intval($detalhe['seq_lanc'])) : '';
                                                        $vkey = ($k_e !== '' && $k_f !== '' && $k_l !== '' && $k_s !== '') ? ($k_e . '|' . $k_f . '|' . $k_l . '|' . $k_s) : null;
                                                        $visto_val = ($vkey !== null && array_key_exists($vkey, $vistos_index)) ? $vistos_index[$vkey] : null;
                                                    }
                                                ?>
                                                <tr class="hover:bg-gray-800 text-gray-300">
                                                    <td class="px-2 py-1 border-b border-gray-800 align-top">
                                                        <div class="flex items-start justify-between">
                                                            <div class="flex-1">
                                                                <div class="flex items-center gap-2 mb-1">
                                                                    <div class="font-medium text-sm text-gray-100"><?= htmlspecialchars($detalhe['descricao'] ?? 'SEM DESCRIÇÃO') ?></div>
                                                                    <?= renderStatusBadge($detalhe) ?>
                                                                </div>
                                                                <?php if (!empty($show_debug_keys)): ?>
                                                                    <div class="text-xs font-mono text-gray-400 mt-1">
                                                                        vkey: <?= htmlspecialchars(getVkeyFromDetalhe($detalhe) ?? 'null') ?>
                                                                        <pre style="margin:6px 0 0 0;padding:6px;background:#0f172a;color:#9ca3af;border-radius:6px;white-space:pre-wrap;overflow:auto;max-height:160px;font-size:11px;"><?= htmlspecialchars(json_encode($detalhe, JSON_UNESCAPED_UNICODE)) ?></pre>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div class="text-xs text-gray-400 mt-1">
                                                                    <?= htmlspecialchars($detalhe['cliente_fornecedor'] ?? '') ?>
                                                                    <span class="ml-2 text-xs text-gray-500">NUMERO DA NOTA: <?= htmlspecialchars($detalhe['nr_documento'] ?? '') ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="ml-3">
                                                                <span class="visto-badge"><?= renderVistoLabel($detalhe, $visto_val) ?></span>
                                                                <?php
                                                                    $__keys = extractNumericKeys($detalhe);
                                                                    $__ne = $__keys['ne'];
                                                                    $__nf = $__keys['nf'];
                                                                    $__nl = $__keys['nl'];
                                                                    $__ns = $__keys['ns'];
                                                                ?>
                                                                <?php if ($__ne > 0 && $__nf > 0 && $__nl > 0 && $__ns >= 0): ?>
                                                                    <input type="checkbox" class="ml-2 w-4 h-4 cursor-pointer bg-white border-gray-300 rounded" aria-label="Marcar como lido"
                                                                        style="accent-color:#f59e0b;border:1px solid rgba(156,163,175,0.18);appearance:checkbox;-webkit-appearance:checkbox;display:inline-block;vertical-align:middle;box-shadow:0 0 0 1px rgba(0,0,0,0.15) inset;background-clip:padding-box;" 
                                                                        onclick="marcarComoLido(<?= $__ne ?>, <?= $__nf ?>, <?= $__nl ?>, <?= $__ns ?>, this)" <?= ($visto_val === true) ? 'checked' : '' ?> >
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-2 py-1 border-b border-gray-800 text-right font-mono text-orange-200 align-top">
                                                        <?= renderValorComPercent(floatval($detalhe['vlr_total'] ?? 0)) ?>
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
                    // LUCRO BRUTO baseado na receita operacional (exclui receitas não operacionais)
                    $lucro_bruto = ($total_geral_operacional - $total_tributos) - $total_custo_variavel;
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
                                <?= renderValorComPercent($lucro_bruto) ?>
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
                                (-) CUSTO FIXO<?= renderParentUnreadBadge('CUSTO FIXO') ?>
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
                                <?= renderValorComPercent($total_custo_fixo) ?>
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
                                <?= htmlspecialchars($categoria_individual) ?><?= renderCategoryUnreadBadge($categoria_individual) ?>
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
                                <?= renderValorComPercent($valor_individual) ?>
                            </td>
                        </tr>
                        
                        <?php 
                            $cat_key = strtoupper(trim($linha['categoria'] ?? ''));
                            if ($cat_key === '') $cat_key = 'SEM CATEGORIA';
                            if (isset($detalhes_por_categoria[$cat_key])): ?>
                        <tr class="detalhes-categoria" id="det-custo-fixo-<?= md5($linha['categoria']) ?>" style="display: none;">
                            <td colspan="2" class="px-0 py-0 border-b border-gray-700">
                                <div class="bg-gray-900 rounded-lg m-2">
                                    <div class="px-4 py-2 bg-gray-800 rounded-t-lg text-xs text-gray-400 font-semibold">
                                        Lançamentos individuais - <?= htmlspecialchars($linha['categoria']) ?><?= renderCategoryUnreadBadge($linha['categoria']) ?>
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
                                                <?php foreach ($detalhes_por_categoria[$cat_key] as $detalhe): ?>
                                                <?php
                                                    if (isset($detalhe['visto'])) {
                                                        $val = $detalhe['visto'];
                                                        if (is_string($val)) $val = in_array(strtolower($val), ['t','true','1'], true);
                                                        $visto_val = ($val === true) ? true : false;
                                                    } else {
                                                        $k_e = isset($detalhe['nr_empresa']) ? strval(intval($detalhe['nr_empresa'])) : '';
                                                        $k_f = isset($detalhe['nr_filial']) ? strval(intval($detalhe['nr_filial'])) : '';
                                                        $k_l = isset($detalhe['nr_lanc']) ? strval(intval($detalhe['nr_lanc'])) : '';
                                                        $k_s = isset($detalhe['seq_lanc']) ? strval(intval($detalhe['seq_lanc'])) : '';
                                                        $vkey = ($k_e !== '' && $k_f !== '' && $k_l !== '' && $k_s !== '') ? ($k_e . '|' . $k_f . '|' . $k_l . '|' . $k_s) : null;
                                                        $visto_val = ($vkey !== null && array_key_exists($vkey, $vistos_index)) ? $vistos_index[$vkey] : null;
                                                    }
                                                ?>
                                                <tr class="hover:bg-gray-800 text-gray-300">
                                                    <td class="px-2 py-1 border-b border-gray-800 align-top">
                                                        <div class="flex items-start justify-between">
                                                            <div class="flex-1">
                                                                <div class="flex items-center gap-2 mb-1">
                                                                    <div class="font-medium text-sm text-gray-100"><?= htmlspecialchars($detalhe['descricao'] ?? 'SEM DESCRIÇÃO') ?></div>
                                                                    <?= renderStatusBadge($detalhe) ?>
                                                                </div>
                                                                <?php if (!empty($show_debug_keys)): ?>
                                                                    <div class="text-xs font-mono text-gray-400 mt-1">
                                                                        vkey: <?= htmlspecialchars(getVkeyFromDetalhe($detalhe) ?? 'null') ?>
                                                                        <pre style="margin:6px 0 0 0;padding:6px;background:#0f172a;color:#9ca3af;border-radius:6px;white-space:pre-wrap;overflow:auto;max-height:160px;font-size:11px;"><?= htmlspecialchars(json_encode($detalhe, JSON_UNESCAPED_UNICODE)) ?></pre>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div class="text-xs text-gray-400 mt-1">
                                                                    <?= htmlspecialchars($detalhe['cliente_fornecedor'] ?? '') ?>
                                                                    <span class="ml-2 text-xs text-gray-500">NUMERO DA NOTA: <?= htmlspecialchars($detalhe['nr_documento'] ?? '') ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="ml-3">
                                                                <span class="visto-badge"><?= renderVistoLabel($detalhe, $visto_val) ?></span>
                                                                <?php
                                                                    $__keys = extractNumericKeys($detalhe);
                                                                    $__ne = $__keys['ne'];
                                                                    $__nf = $__keys['nf'];
                                                                    $__nl = $__keys['nl'];
                                                                    $__ns = $__keys['ns'];
                                                                ?>
                                                                <?php if ($__ne > 0 && $__nf > 0 && $__nl > 0 && $__ns >= 0): ?>
                                                                    <input type="checkbox" class="ml-2 w-4 h-4 cursor-pointer bg-white border-gray-300 rounded" aria-label="Marcar como lido"
                                                                        style="accent-color:#f59e0b;border:1px solid rgba(156,163,175,0.18);appearance:checkbox;-webkit-appearance:checkbox;display:inline-block;vertical-align:middle;box-shadow:0 0 0 1px rgba(0,0,0,0.15) inset;background-clip:padding-box;" 
                                                                        onclick="marcarComoLido(<?= $__ne ?>, <?= $__nf ?>, <?= $__nl ?>, <?= $__ns ?>, this)" <?= ($visto_val === true) ? 'checked' : '' ?> >
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-2 py-1 border-b border-gray-800 text-right font-mono text-orange-200 align-top">
                                                        <?= renderValorComPercent(floatval($detalhe['vlr_total'] ?? 0)) ?>
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
                                (-) DESPESA FIXA<?= renderParentUnreadBadge('DESPESA FIXA') ?>
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
                                <?= renderValorComPercent($total_despesa_fixa) ?>
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
                                <?= htmlspecialchars($categoria_individual) ?><?= renderCategoryUnreadBadge($categoria_individual) ?>
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
                                <?= renderValorComPercent($valor_individual) ?>
                            </td>
                        </tr>
                        
                        <?php 
                            $cat_key = strtoupper(trim($linha['categoria'] ?? ''));
                            if ($cat_key === '') $cat_key = 'SEM CATEGORIA';
                            if (isset($detalhes_por_categoria[$cat_key])): ?>
                        <tr class="detalhes-categoria" id="det-despesa-fixa-<?= md5($linha['categoria']) ?>" style="display: none;">
                            <td colspan="2" class="px-0 py-0 border-b border-gray-700">
                                <div class="bg-gray-900 rounded-lg m-2">
                                    <div class="px-4 py-2 bg-gray-800 rounded-t-lg text-xs text-gray-400 font-semibold">
                                        Lançamentos individuais - <?= htmlspecialchars($linha['categoria']) ?><?= renderCategoryUnreadBadge($linha['categoria']) ?>
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
                                                <?php foreach ($detalhes_por_categoria[$cat_key] as $detalhe): ?>
                                                <?php
                                                    $k_e = isset($detalhe['nr_empresa']) ? strval(intval($detalhe['nr_empresa'])) : '';
                                                    $k_f = isset($detalhe['nr_filial']) ? strval(intval($detalhe['nr_filial'])) : '';
                                                    $k_l = isset($detalhe['nr_lanc']) ? strval(intval($detalhe['nr_lanc'])) : '';
                                                    $k_s = isset($detalhe['seq_lanc']) ? strval(intval($detalhe['seq_lanc'])) : '';
                                                    $vkey = ($k_e !== '' && $k_f !== '' && $k_l !== '' && $k_s !== '') ? ($k_e . '|' . $k_f . '|' . $k_l . '|' . $k_s) : null;
                                                    $visto_val = ($vkey !== null && array_key_exists($vkey, $vistos_index)) ? $vistos_index[$vkey] : null;
                                                ?>
                                                <tr class="hover:bg-gray-800 text-gray-300">
                                                    <td class="px-2 py-1 border-b border-gray-800 align-top">
                                                        <div class="flex items-start justify-between">
                                                            <div class="flex-1">
                                                                <div class="flex items-center gap-2 mb-1">
                                                                    <div class="font-medium text-sm text-gray-100"><?= htmlspecialchars($detalhe['descricao'] ?? 'SEM DESCRIÇÃO') ?></div>
                                                                    <?= renderStatusBadge($detalhe) ?>
                                                                </div>
                                                                <?php if (!empty($show_debug_keys)): ?>
                                                                    <div class="text-xs font-mono text-gray-400 mt-1">
                                                                        vkey: <?= htmlspecialchars(getVkeyFromDetalhe($detalhe) ?? 'null') ?>
                                                                        <pre style="margin:6px 0 0 0;padding:6px;background:#0f172a;color:#9ca3af;border-radius:6px;white-space:pre-wrap;overflow:auto;max-height:160px;font-size:11px;"><?= htmlspecialchars(json_encode($detalhe, JSON_UNESCAPED_UNICODE)) ?></pre>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div class="text-xs text-gray-400 mt-1">
                                                                    <?= htmlspecialchars($detalhe['cliente_fornecedor'] ?? '') ?>
                                                                    <span class="ml-2 text-xs text-gray-500">NUMERO DA NOTA: <?= htmlspecialchars($detalhe['nr_documento'] ?? '') ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="ml-3">
                                                                <span class="visto-badge"><?= renderVistoLabel($detalhe, $visto_val) ?></span>
                                                                <?php
                                                                    $__keys = extractNumericKeys($detalhe);
                                                                    $__ne = $__keys['ne'];
                                                                    $__nf = $__keys['nf'];
                                                                    $__nl = $__keys['nl'];
                                                                    $__ns = $__keys['ns'];
                                                                ?>
                                                                <?php if ($__ne > 0 && $__nf > 0 && $__nl > 0 && $__ns >= 0): ?>
                                                                    <input type="checkbox" class="ml-2 w-4 h-4 cursor-pointer bg-white border-gray-300 rounded" aria-label="Marcar como lido"
                                                                        style="accent-color:#f59e0b;border:1px solid rgba(156,163,175,0.18);appearance:checkbox;-webkit-appearance:checkbox;display:inline-block;vertical-align:middle;box-shadow:0 0 0 1px rgba(0,0,0,0.15) inset;background-clip:padding-box;" 
                                                                        onclick="marcarComoLido(<?= $__ne ?>, <?= $__nf ?>, <?= $__nl ?>, <?= $__ns ?>, this)" <?= ($visto_val === true) ? 'checked' : '' ?> >
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-2 py-1 border-b border-gray-800 text-right font-mono text-orange-200 align-top">
                                                        <?= renderValorComPercent(floatval($detalhe['vlr_total'] ?? 0)) ?>
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
                                (-) DESPESAS DE VENDA<?= renderParentUnreadBadge('DESPESAS DE VENDA') ?>
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
                                <?= renderValorComPercent($total_despesa_venda) ?>
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
                                <?= htmlspecialchars($categoria_individual) ?><?= renderCategoryUnreadBadge($categoria_individual) ?>
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
                                <?= renderValorComPercent($valor_individual) ?>
                            </td>
                        </tr>
                        
                        <?php 
                            $cat_key = strtoupper(trim($linha['categoria'] ?? ''));
                            if ($cat_key === '') $cat_key = 'SEM CATEGORIA';
                            if (isset($detalhes_por_categoria[$cat_key])): ?>
                        <tr class="detalhes-categoria" id="det-despesa-venda-<?= md5($linha['categoria']) ?>" style="display: none;">
                            <td colspan="2" class="px-0 py-0 border-b border-gray-700">
                                <div class="bg-gray-900 rounded-lg m-2">
                                    <div class="px-4 py-2 bg-gray-800 rounded-t-lg text-xs text-gray-400 font-semibold">
                                        Lançamentos individuais - <?= htmlspecialchars($linha['categoria']) ?><?= renderCategoryUnreadBadge($linha['categoria']) ?>
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
                                                <?php foreach ($detalhes_por_categoria[$cat_key] as $detalhe): ?>
                                                <?php
                                                    $k_e = isset($detalhe['nr_empresa']) ? strval(intval($detalhe['nr_empresa'])) : '';
                                                    $k_f = isset($detalhe['nr_filial']) ? strval(intval($detalhe['nr_filial'])) : '';
                                                    $k_l = isset($detalhe['nr_lanc']) ? strval(intval($detalhe['nr_lanc'])) : '';
                                                    $k_s = isset($detalhe['seq_lanc']) ? strval(intval($detalhe['seq_lanc'])) : '';
                                                    $vkey = ($k_e !== '' && $k_f !== '' && $k_l !== '' && $k_s !== '') ? ($k_e . '|' . $k_f . '|' . $k_l . '|' . $k_s) : null;
                                                    $visto_val = ($vkey !== null && array_key_exists($vkey, $vistos_index)) ? $vistos_index[$vkey] : null;
                                                ?>
                                                <tr class="hover:bg-gray-800 text-gray-300">
                                                    <td class="px-2 py-1 border-b border-gray-800 align-top">
                                                        <div class="flex items-start justify-between">
                                                            <div class="flex-1">
                                                                <div class="flex items-center gap-2 mb-1">
                                                                    <div class="font-medium text-sm text-gray-100"><?= htmlspecialchars($detalhe['descricao'] ?? 'SEM DESCRIÇÃO') ?></div>
                                                                    <?= renderStatusBadge($detalhe) ?>
                                                                </div>
                                                                    <?php if (!empty($show_debug_keys)): ?>
                                                                    <div class="text-xs font-mono text-gray-400 mt-1">
                                                                        vkey: <?= htmlspecialchars(getVkeyFromDetalhe($detalhe) ?? 'null') ?>
                                                                        <pre style="margin:6px 0 0 0;padding:6px;background:#0f172a;color:#9ca3af;border-radius:6px;white-space:pre-wrap;overflow:auto;max-height:160px;font-size:11px;"><?= htmlspecialchars(json_encode($detalhe, JSON_UNESCAPED_UNICODE)) ?></pre>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div class="text-xs text-gray-400 mt-1">
                                                                    <?= htmlspecialchars($detalhe['cliente_fornecedor'] ?? '') ?>
                                                                    <span class="ml-2 text-xs text-gray-500">NUMERO DA NOTA: <?= htmlspecialchars($detalhe['nr_documento'] ?? '') ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="ml-3">
                                                                <span class="visto-badge"><?= renderVistoLabel($detalhe, $visto_val) ?></span>
                                                                <?php
                                                                    $__keys = extractNumericKeys($detalhe);
                                                                    $__ne = $__keys['ne'];
                                                                    $__nf = $__keys['nf'];
                                                                    $__nl = $__keys['nl'];
                                                                    $__ns = $__keys['ns'];
                                                                ?>
                                                                <?php if ($__ne > 0 && $__nf > 0 && $__nl > 0 && $__ns >= 0): ?>
                                                                    <input type="checkbox" class="ml-2 w-4 h-4 cursor-pointer bg-white border-gray-300 rounded" aria-label="Marcar como lido"
                                                                        style="accent-color:#f59e0b;border:1px solid rgba(156,163,175,0.18);appearance:checkbox;-webkit-appearance:checkbox;display:inline-block;vertical-align:middle;box-shadow:0 0 0 1px rgba(0,0,0,0.15) inset;background-clip:padding-box;" 
                                                                        onclick="marcarComoLido(<?= $__ne ?>, <?= $__nf ?>, <?= $__nl ?>, <?= $__ns ?>, this)" <?= ($visto_val === true) ? 'checked' : '' ?> >
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-2 py-1 border-b border-gray-800 text-right font-mono text-orange-200 align-top">
                                                        <?= renderValorComPercent(floatval($detalhe['vlr_total'] ?? 0)) ?>
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
                    // LUCRO LÍQUIDO baseado na receita operacional (exclui receitas não operacionais)
                    $lucro_liquido = (($total_geral_operacional - $total_tributos) - $total_custo_variavel) - $total_custo_fixo - $total_despesa_fixa - $total_despesa_venda;
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
                                <?= renderValorComPercent($lucro_liquido) ?>
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
                                (-) INVESTIMENTO INTERNO<?= renderParentUnreadBadge('INVESTIMENTO INTERNO') ?>
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
                                <?= renderValorComPercent($total_investimento_interno) ?>
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
                                <?= htmlspecialchars($categoria_individual) ?><?= renderCategoryUnreadBadge($categoria_individual) ?>
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
                                <?= renderValorComPercent($valor_individual) ?>
                            </td>
                        </tr>
                        
                        <?php 
                            $cat_key = strtoupper(trim($linha['categoria'] ?? ''));
                            if ($cat_key === '') $cat_key = 'SEM CATEGORIA';
                            if (isset($detalhes_por_categoria[$cat_key])): ?>
                        <tr class="detalhes-categoria" id="det-investimento-interno-<?= md5($linha['categoria']) ?>" style="display: none;">
                            <td colspan="2" class="px-0 py-0 border-b border-gray-700">
                                <div class="bg-gray-900 rounded-lg m-2">
                                    <div class="px-4 py-2 bg-gray-800 rounded-t-lg text-xs text-gray-400 font-semibold">
                                        Lançamentos individuais - <?= htmlspecialchars($linha['categoria']) ?><?= renderCategoryUnreadBadge($linha['categoria']) ?>
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
                                                <?php foreach ($detalhes_por_categoria[$cat_key] as $detalhe): ?>
                                                <?php
                                                    $k_e = isset($detalhe['nr_empresa']) ? strval(intval($detalhe['nr_empresa'])) : '';
                                                    $k_f = isset($detalhe['nr_filial']) ? strval(intval($detalhe['nr_filial'])) : '';
                                                    $k_l = isset($detalhe['nr_lanc']) ? strval(intval($detalhe['nr_lanc'])) : '';
                                                    $k_s = isset($detalhe['seq_lanc']) ? strval(intval($detalhe['seq_lanc'])) : '';
                                                    $vkey = ($k_e !== '' && $k_f !== '' && $k_l !== '' && $k_s !== '') ? ($k_e . '|' . $k_f . '|' . $k_l . '|' . $k_s) : null;
                                                    $visto_val = ($vkey !== null && array_key_exists($vkey, $vistos_index)) ? $vistos_index[$vkey] : null;
                                                ?>
                                                <tr class="hover:bg-gray-800 text-gray-300">
                                                    <td class="px-2 py-1 border-b border-gray-800 align-top">
                                                        <div class="flex items-start justify-between">
                                                            <div class="flex-1">
                                                                <div class="flex items-center gap-2 mb-1">
                                                                    <div class="font-medium text-sm text-gray-100"><?= htmlspecialchars($detalhe['descricao'] ?? 'SEM DESCRIÇÃO') ?></div>
                                                                    <?= renderStatusBadge($detalhe) ?>
                                                                </div>
                                                                <?php if (!empty($show_debug_keys)): ?>
                                                                    <div class="text-xs font-mono text-gray-400 mt-1">vkey: <?= htmlspecialchars(getVkeyFromDetalhe($detalhe) ?? 'null') ?></div>
                                                                <?php endif; ?>
                                                                <div class="text-xs text-gray-400 mt-1">
                                                                    <?= htmlspecialchars($detalhe['cliente_fornecedor'] ?? '') ?>
                                                                    <span class="ml-2 text-xs text-gray-500">NUMERO DA NOTA: <?= htmlspecialchars($detalhe['nr_documento'] ?? '') ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="ml-3">
                                                                <span class="visto-badge"><?= renderVistoLabel($detalhe, $visto_val) ?></span>
                                                                <?php
                                                                    // Precompute numeric keys to avoid sending invalid values to the toggle endpoint
                                                                    $__keys = extractNumericKeys($detalhe);
                                                                    $__ne = $__keys['ne'];
                                                                    $__nf = $__keys['nf'];
                                                                    $__nl = $__keys['nl'];
                                                                    $__ns = $__keys['ns'];
                                                                ?>
                                                                <?php if ($visto_val !== true && $__ne > 0 && $__nf > 0 && $__nl > 0 && $__ns >= 0): ?>
                                                                    <input type="checkbox" class="ml-2 w-4 h-4 cursor-pointer bg-white border-gray-300 rounded" aria-label="Marcar como lido"
                                                                        style="accent-color:#f59e0b;border:1px solid rgba(156,163,175,0.18);appearance:checkbox;-webkit-appearance:checkbox;display:inline-block;vertical-align:middle;box-shadow:0 0 0 1px rgba(0,0,0,0.15) inset;background-clip:padding-box;" 
                                                                        onclick="marcarComoLido(<?= $__ne ?>, <?= $__nf ?>, <?= $__nl ?>, <?= $__ns ?>, this)">
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-2 py-1 border-b border-gray-800 text-right font-mono text-blue-200 align-top">
                                                        <?= renderValorComPercent(floatval($detalhe['vlr_total'] ?? 0)) ?>
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

                    <!-- RECEITAS NÃO OPERACIONAIS - Agora como categoria PAI (antes de SAÍDAS NÃO OPERACIONAIS) -->
                    <?php if (!empty($receitas_nao_operacionais)): ?>
                    <tbody>
                        <?php 
                        $meta_nao_operacional = obterMeta('RECEITAS NÃO OPERACIONAIS');
                        $percentual_nao_operacional = calcularPercentualMeta($total_nao_operacional, $meta_nao_operacional);
                        $cor_nao_operacional = obterCorBarra($percentual_nao_operacional, false);
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-blue-300" onclick="toggleReceita('nao-operacional')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                RECEITAS NÃO OPERACIONAIS<?= renderParentUnreadBadge('RECEITAS NÃO OPERACIONAIS') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <div class="w-full">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-gray-400"><?= number_format($percentual_nao_operacional, 1) ?>%</span>
                                        <span class="text-gray-500">Meta: R$ <?= number_format($meta_nao_operacional, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-2">
                                        <div class="bg-<?= $cor_nao_operacional ?>-500 h-2 rounded-full transition-all duration-300" style="width: <?= min($percentual_nao_operacional, 100) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono">
                                <?= renderValorComPercent($total_nao_operacional) ?>
                            </td>
                        </tr>
                    </tbody>

                    <!-- Detalhes das RECEITAS NÃO OPERACIONAIS (ocultos inicialmente) -->
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
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?><?= renderCategoryUnreadBadge($categoria_individual) ?>
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
                                <?= renderValorComPercent($valor_individual) ?>
                            </td>
                        </tr>
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
                                (-) SAÍDAS NÃO OPERACIONAIS<?= renderParentUnreadBadge('SAÍDAS NÃO OPERACIONAIS') ?>
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
                                <?= renderValorComPercent($total_saidas_nao_operacionais) ?>
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
                                <?= htmlspecialchars($categoria_individual) ?><?= renderCategoryUnreadBadge($categoria_individual) ?>
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
                                <?= renderValorComPercent($valor_individual) ?>
                            </td>
                        </tr>
                        
                        <?php 
                            $cat_key = strtoupper(trim($linha['categoria'] ?? ''));
                            if ($cat_key === '') $cat_key = 'SEM CATEGORIA';
                            if (isset($detalhes_por_categoria[$cat_key])): ?>
                        <tr class="detalhes-categoria" id="det-saidas-nao-operacionais-<?= md5($linha['categoria']) ?>" style="display: none;">
                            <td colspan="2" class="px-0 py-0 border-b border-gray-700">
                                <div class="bg-gray-900 rounded-lg m-2">
                                    <div class="px-4 py-2 bg-gray-800 rounded-t-lg text-xs text-gray-400 font-semibold">
                                        Lançamentos individuais - <?= htmlspecialchars($linha['categoria']) ?><?= renderCategoryUnreadBadge($linha['categoria']) ?>
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
                                                <?php foreach ($detalhes_por_categoria[$cat_key] as $detalhe): ?>
                                                <?php
                                                    $k_e = isset($detalhe['nr_empresa']) ? strval(intval($detalhe['nr_empresa'])) : '';
                                                    $k_f = isset($detalhe['nr_filial']) ? strval(intval($detalhe['nr_filial'])) : '';
                                                    $k_l = isset($detalhe['nr_lanc']) ? strval(intval($detalhe['nr_lanc'])) : '';
                                                    $k_s = isset($detalhe['seq_lanc']) ? strval(intval($detalhe['seq_lanc'])) : '';
                                                    $vkey = ($k_e !== '' && $k_f !== '' && $k_l !== '' && $k_s !== '') ? ($k_e . '|' . $k_f . '|' . $k_l . '|' . $k_s) : null;
                                                    $visto_val = ($vkey !== null && array_key_exists($vkey, $vistos_index)) ? $vistos_index[$vkey] : null;
                                                ?>
                                                <tr class="hover:bg-gray-800 text-gray-300">
                                                    <td class="px-2 py-1 border-b border-gray-800 align-top">
                                                        <div class="flex items-start justify-between">
                                                            <div class="flex-1">
                                                                <div class="flex items-center gap-2 mb-1">
                                                                    <div class="font-medium text-sm text-gray-100"><?= htmlspecialchars($detalhe['descricao'] ?? 'SEM DESCRIÇÃO') ?></div>
                                                                    <?= renderStatusBadge($detalhe) ?>
                                                                </div>
                                                                <?php if (!empty($show_debug_keys)): ?>
                                                                    <div class="text-xs font-mono text-gray-400 mt-1">vkey: <?= htmlspecialchars(getVkeyFromDetalhe($detalhe) ?? 'null') ?></div>
                                                                <?php endif; ?>
                                                                <div class="text-xs text-gray-400 mt-1">
                                                                    <?= htmlspecialchars($detalhe['cliente_fornecedor'] ?? '') ?>
                                                                    <span class="ml-2 text-xs text-gray-500">NUMERO DA NOTA: <?= htmlspecialchars($detalhe['nr_documento'] ?? '') ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="ml-3">
                                                                <span class="visto-badge"><?= renderVistoLabel($detalhe, $visto_val) ?></span>
                                                                <?php
                                                                    $__keys = extractNumericKeys($detalhe);
                                                                    $__ne = $__keys['ne'];
                                                                    $__nf = $__keys['nf'];
                                                                    $__nl = $__keys['nl'];
                                                                    $__ns = $__keys['ns'];
                                                                ?>
                                                                <?php if ($visto_val !== true && $__ne > 0 && $__nf > 0 && $__nl > 0 && $__ns >= 0): ?>
                                                                    <input type="checkbox" class="ml-2 w-4 h-4 cursor-pointer bg-white border-gray-300 rounded" style="accent-color:#f59e0b;border:1px solid rgba(156,163,175,0.18);" aria-label="Marcar como lido"
                                                                        onclick="marcarComoLido(<?= $__ne ?>, <?= $__nf ?>, <?= $__nl ?>, <?= $__ns ?>, this)">
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-2 py-1 border-b border-gray-800 text-right font-mono text-red-200 align-top">
                                                        <?= renderValorComPercent(floatval($detalhe['vlr_total'] ?? 0)) ?>
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

                    <!-- RETIRADA DE LUCRO - Categoria pai (logo abaixo das SAÍDAS NÃO OPERACIONAIS) -->
                    <?php if (!empty($retirada_de_lucro)): ?>
                    <?php
                        $total_retirada_de_lucro = array_sum(array_column($retirada_de_lucro, 'total_receita_mes'));
                        $meta_retirada = obterMeta('RETIRADA DE LUCRO');
                        $perc_retirada = calcularPercentualMeta($total_retirada_de_lucro, $meta_retirada);
                        $cor_retirada = obterCorBarra($perc_retirada, true);
                    ?>
                    <tbody>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-red-400">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) RETIRADA DE LUCRO<?= renderParentUnreadBadge('RETIRADA DE LUCRO') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <div class="w-full">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-gray-400"><?= number_format($perc_retirada, 1) ?>%</span>
                                        <span class="text-gray-500">Meta: R$ <?= number_format($meta_retirada, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-2">
                                        <div class="bg-<?= $cor_retirada ?>-500 h-2 rounded-full transition-all duration-300" style="width: <?= min($perc_retirada, 100) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono">
                                <?= renderValorComPercent($total_retirada_de_lucro) ?>
                            </td>
                        </tr>
                    </tbody>
                    <?php else: $total_retirada_de_lucro = $total_retirada_de_lucro ?? 0; ?>
                    <?php endif; ?>

                    <!-- IMPACTO CAIXA - Cálculo final (LUCRO LÍQUIDO - INVESTIMENTO INTERNO - SAÍDAS NÃO OPERACIONAIS) -->
                    <?php 
                    $impacto_caixa = (((((($total_geral - $total_tributos) - $total_custo_variavel) - $total_custo_fixo - $total_despesa_fixa - $total_despesa_venda) - $total_investimento_interno) - $total_saidas_nao_operacionais) - ($total_retirada_de_lucro ?? 0));
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
                                <?= renderValorComPercent($impacto_caixa) ?>
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
        if (subReceitaBruta) {
            if (subReceitaBruta.style.display === 'none' || subReceitaBruta.style.display === '') {
                subReceitaBruta.style.display = 'table-row-group';
            } else {
                subReceitaBruta.style.display = 'none';
                // Esconder também os detalhes das operacionais quando colapsar a RECEITA BRUTA
                var subOper = document.getElementById('sub-operacional');
                if (subOper) subOper.style.display = 'none';
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

<script>
// current period for bulk operations (YYYY/MM)
const __current_periodo = <?= json_encode($periodo_selecionado) ?>;

// Toggle menu de página
document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('pageMenuBtn');
    var menu = document.getElementById('pageMenu');
    if (btn && menu) {
        btn.addEventListener('click', function(e){
            e.stopPropagation();
            menu.classList.toggle('hidden');
        });
        document.addEventListener('click', function(){
            if (!menu.classList.contains('hidden')) menu.classList.add('hidden');
        });
    }
});
</script>

<script>
async function marcarComoLido(nr_empresa, nr_filial, nr_lanc, seq_lanc, btnEl) {
    const isCheckbox = btnEl && btnEl.tagName === 'INPUT' && btnEl.type === 'checkbox';
    let prevChecked = null;
    if (isCheckbox) {
        // checkbox has already toggled its state when clicked; previous state is inverted
        prevChecked = !btnEl.checked;
        btnEl.disabled = true;
        btnEl.classList.add('opacity-60');
    } else {
        try { btnEl.disabled = true; } catch (e) {}
    }

    try {
        const resp = await fetch('/modules/financeiro_bar/toggle_visto_wab.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nr_empresa: nr_empresa, nr_filial: nr_filial, nr_lanc: nr_lanc, seq_lanc: seq_lanc, visto: isCheckbox ? btnEl.checked : undefined }),
            credentials: 'same-origin'
        });

        const j = await resp.json();
        if (j.success) {
            const newVisto = (typeof j.visto !== 'undefined') ? Boolean(j.visto) : btnEl.checked;
            const container = btnEl.closest('div');
            if (container) {
                const badge = container.querySelector('.visto-badge');
                if (badge) {
                    if (newVisto) {
                        badge.innerHTML = '<span class="text-xs px-2 py-0.5 rounded bg-green-600 text-white">Lido</span>';
                    } else {
                        badge.innerHTML = '<span class="text-xs px-2 py-0.5 rounded bg-orange-500 text-white">Não lido</span>';
                    }
                }
            }
            // manter o checkbox visível e clicável
            try { btnEl.checked = newVisto; btnEl.disabled = false; btnEl.classList.remove('opacity-60'); } catch (e) {}
        } else {
            alert('Erro: ' + (j.error || 'unknown'));
            if (isCheckbox) {
                try { btnEl.checked = prevChecked; btnEl.disabled = false; btnEl.classList.remove('opacity-60'); } catch (e) {}
            } else {
                try { btnEl.disabled = false; } catch (e) {}
            }
        }
    } catch (err) {
        alert('Erro de rede: ' + err.message);
        if (isCheckbox) {
            try { btnEl.checked = prevChecked; btnEl.disabled = false; btnEl.classList.remove('opacity-60'); } catch (e) {}
        } else {
            try { btnEl.disabled = false; } catch (e) {}
        }
    }
}
</script>

<script>
async function marcarTodosComoLidos() {
    const periodo = __current_periodo || document.getElementById('periodo-label')?.innerText || '';
    if (!periodo) {
        alert('Nenhum período selecionado. Selecione um período antes de marcar.');
        return;
    }

    if (!confirm('Marcar todos os lançamentos do período selecionado como lidos?')) return;

    const btn = document.getElementById('markAllBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Marcando...'; }

    try {
        const resp = await fetch('/modules/financeiro_bar/mark_all_vistos_wab.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ periodo: __current_periodo }),
            credentials: 'same-origin'
        });
        const j = await resp.json();
        if (j.success) {
            // Update UI: set all badges to Lido and remove checkboxes
            document.querySelectorAll('.visto-badge').forEach(b => { b.innerHTML = '<span class="text-xs px-2 py-0.5 rounded bg-green-600 text-white">Lido</span>'; });
            document.querySelectorAll('input[type=checkbox]').forEach(c => { try { c.remove(); } catch(e){} });
            if (btn) { btn.textContent = 'Marcado'; setTimeout(()=>{ if(btn) btn.textContent = 'Marcar tudo como lido'; }, 2500); }
            alert('Todos os lançamentos do período foram marcados como lidos.');
        } else {
            alert('Erro: ' + (j.error || 'unknown'));
            if (btn) { btn.disabled = false; btn.textContent = 'Marcar tudo como lido'; }
        }
    } catch (err) {
        alert('Erro de rede: ' + err.message);
        if (btn) { btn.disabled = false; btn.textContent = 'Marcar tudo como lido'; }
    }
}

document.addEventListener('DOMContentLoaded', function(){
    var mk = document.getElementById('markAllBtn');
    if (mk) mk.addEventListener('click', function(e){ e.stopPropagation(); marcarTodosComoLidos(); });
});

</script>