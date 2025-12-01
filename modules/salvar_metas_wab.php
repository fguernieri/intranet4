<?php
// Limpar qualquer output anterior
ob_clean();

// Configurar headers para JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Desabilitar relatórios de erro para evitar HTML no response
// Keep display off but log everything to error log for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Variáveis de diagnóstico disponíveis para retorno em caso de erro
$diagnostic_datas_meta = [];
$diagnostic_deleted = [];
$diagnostic_insert_result = null;

// Convert uncaught exceptions to JSON response and log
set_exception_handler(function($e){
    error_log("Uncaught exception in salvar_metas_wab.php: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno no servidor', 'error' => $e->getMessage()]);
    exit;
});

// Catch fatal errors and return JSON for easier debugging
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && ($err['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
        error_log("Fatal error in salvar_metas_wab.php: " . print_r($err, true));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Fatal error', 'error' => $err]);
        exit;
    }
});

try {
    // Verificar se é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    // Obter dados JSON
    $input = file_get_contents('php://input');
    $dados = json_decode($input, true);
    
    // Debug: Verificar se os dados JSON são válidos
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Dados JSON inválidos: ' . json_last_error_msg());
    }
    
    if (!$dados) {
        throw new Exception('Dados JSON inválidos');
    }
    
    // Validar dados obrigatórios
    if (!isset($dados['action']) || $dados['action'] !== 'salvar_metas') {
        throw new Exception('Ação inválida');
    }
    
    if (!isset($dados['meses']) || !is_array($dados['meses']) || empty($dados['meses'])) {
        throw new Exception('Meses não selecionados');
    }
    
    if (!isset($dados['metas']) || !is_array($dados['metas']) || empty($dados['metas'])) {
        throw new Exception('Metas não informadas');
    }
    
    $meses = $dados['meses'];
    $metas = $dados['metas'];
    
    // Suportar múltiplos anos (array) ou ano único (retrocompatibilidade)
    $anos = [];
    if (isset($dados['anos']) && is_array($dados['anos']) && !empty($dados['anos'])) {
        $anos = $dados['anos'];
    } elseif (isset($dados['ano'])) {
        $anos = [$dados['ano']];
    } else {
        $anos = [date('Y')];
    }
    
    // Determinar target (tap ou wab). Default = wab
    $target = strtolower(trim($dados['target'] ?? 'wab'));
    $allowed = ['tap' => 'fmetaswab', 'wab' => 'fmetaswab'];
    if (!isset($allowed[$target])) {
        // fallback para wab
        $target = 'wab';
    }
    $table = $allowed[$target];
    
    // Aumentar timeout para processamento de muitos registros
    set_time_limit(120);
    
    // DEBUG: Log dos dados recebidos
    error_log("=== DEBUG SALVAR METAS WAB ===");
    error_log("Meses recebidos: " . print_r($meses, true));
    error_log("Anos recebidos: " . print_r($anos, true));
    error_log("Metas recebidas: " . print_r($metas, true));
    error_log("Target table: " . $table);
    
    // Incluir a classe de conexão com Supabase
    require_once __DIR__ . '/supabase_connection.php';
    
    // Criar instância da conexão Supabase
    $supabase = new SupabaseConnection();
    
    // Construir array com TODOS os registros para batch insert/upsert
    $todosRegistros = [];
    $registrosProcessados = [];
    
    // Para cada ano selecionado
    foreach ($anos as $ano) {
        // Para cada mês selecionado
        foreach ($meses as $mes) {
            $mesInt = intval($mes);
            $dt = DateTime::createFromFormat('!Y-n-j', sprintf('%04d-%d-1', intval($ano), $mesInt));
            if ($dt === false) {
                $dataMeta = sprintf('%04d-%02d-01', intval($ano), $mesInt);
            } else {
                $dataMeta = $dt->format('Y-m-d');
            }
            
            // Para cada meta financeira
            foreach ($metas as $meta) {
                // Pular metas com valor zero
                if (($meta['meta'] ?? 0) == 0) {
                    continue;
                }
                
                // Tratar subcategoria vazia como NULL para o banco
                $subcategoria = isset($meta['subcategoria']) && trim($meta['subcategoria']) !== '' 
                                ? trim($meta['subcategoria']) 
                                : null;
                
                $registro = [
                    'CATEGORIA' => trim($meta['categoria'] ?? ''),
                    'SUBCATEGORIA' => $subcategoria,
                    'META' => floatval($meta['meta'] ?? 0),
                    'PERCENTUAL' => floatval($meta['percentual'] ?? 0),
                    'DATA' => date('Y-m-d H:i:s'),
                    'DATA_META' => $dataMeta,
                    'DATA_CRI' => date('Y-m-d H:i:s')
                ];
                
                $todosRegistros[] = $registro;
                
                $registrosProcessados[] = [
                    'categoria' => $meta['categoria'],
                    'subcategoria' => $subcategoria,
                    'ano' => $ano,
                    'mes' => $mesInt,
                    'meta' => $meta['meta']
                ];
            }
        }
    }
    
    // DEBUG: Log do total de registros a serem salvos
    error_log("Total de registros a salvar: " . count($todosRegistros));
    
    // Se não houver registros, retornar erro
    if (empty($todosRegistros)) {
        throw new Exception('Nenhum registro válido para salvar');
    }
    
    // NOVA LÓGICA: Substituir metas do(s) mês(es) selecionado(s)
    // Passo 1: extrair DATA_META únicos dos registros para deletar tudo daquele mês
    $datasMeta = array_values(array_unique(array_map(function($r) {
        return $r['DATA_META'];
    }, $todosRegistros)));
    $diagnostic_datas_meta = $datasMeta;

    // Log
    error_log("Datas META a serem substituídas (WAB): " . implode(', ', $datasMeta));

    // Passo 2: deletar todas as metas existentes para cada DATA_META selecionada
    $deleted = [];
    foreach ($datasMeta as $dm) {
        error_log("Deletando registros existentes para DATA_META=" . $dm . " na tabela " . $table);
        $delResult = $supabase->delete($table, ['DATA_META' => 'eq.' . $dm]);
        $deleted[$dm] = $delResult;
        if ($delResult === false) {
            error_log("Aviso: DELETE retornou false para DATA_META=" . $dm . " (ver supabase_debug.log)");
        }
    }

    // Passo 3: inserir todos os registros do simulador (INSERT em batch)
    error_log("Inserindo registros (INSERT batch) na tabela " . $table . " (WAB)");
    $insertResult = $supabase->insert($table, $todosRegistros);
    $diagnostic_insert_result = $insertResult;
    error_log("Resultado INSERT (WAB): " . ($insertResult !== false ? 'sucesso' : 'falha'));

    if ($insertResult === false) {
        throw new Exception('Falha ao inserir metas no Supabase após deleção (WAB)');
    }

    $resultado = $insertResult;
    $totalRegistros = count($todosRegistros);
    
    // Resposta de sucesso
    $response = [
        'success' => true,
        'message' => 'Metas salvas com sucesso no Supabase',
        'table' => $table,
        'target' => $target,
        'anos' => $anos,
        'anos_processados' => count($anos),
        'total_registros' => $totalRegistros,
        'meses_processados' => count($meses),
        'metas_processadas' => count($metas),
        'detalhes' => $registrosProcessados
    ];

    echo json_encode($response);
    
} catch (Exception $e) {
    // Log do erro
    error_log("Erro ao salvar metas no Supabase: " . $e->getMessage());

    // Tentar anexar as últimas linhas do log do Supabase para facilitar debug em produção
    $supabaseLogTail = null;
    $debugFilePath = __DIR__ . '/supabase_debug.log';
    if (file_exists($debugFilePath) && is_readable($debugFilePath)) {
        $lines = @file($debugFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            $tail = array_slice($lines, max(0, count($lines) - 200));
            $supabaseLogTail = implode("\n", $tail);
        }
    }

    // Resposta de erro com debug adicional do Supabase (se disponível)
    $errorResponse = [
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ],
        'diagnostic' => [
            'datas_meta' => $diagnostic_datas_meta,
            'deleted' => $deleted ?? $diagnostic_deleted,
            'insert_result' => $diagnostic_insert_result
        ]
    ];

    if ($supabaseLogTail !== null) {
        $errorResponse['supabase_debug_tail'] = $supabaseLogTail;
    } else {
        $errorResponse['supabase_debug_tail'] = 'log not available';
    }

    // Montar debug completo (supabase log + php error log + server info + trace)
    $fullDebug = [];
    $fullDebug[] = "--- SERVER INFO ---";
    $fullDebug[] = 'REQUEST_URI: ' . ($_SERVER['REQUEST_URI'] ?? '');
    $fullDebug[] = 'REMOTE_ADDR: ' . ($_SERVER['REMOTE_ADDR'] ?? '');
    $fullDebug[] = 'SERVER_SOFTWARE: ' . ($_SERVER['SERVER_SOFTWARE'] ?? '');
    $fullDebug[] = 'PHP_VERSION: ' . phpversion();
    if (function_exists('curl_version')) {
        $cv = curl_version();
        $fullDebug[] = 'CURL_VERSION: ' . ($cv['version'] ?? '');
    }

    // Supabase config summary (redacted keys)
    $supabaseConfigPath = __DIR__ . '/config/supabase_config.php';
    if (file_exists($supabaseConfigPath)) {
        $cfg = @include $supabaseConfigPath;
        if (is_array($cfg)) {
            $cfgSummary = [
                'url' => $cfg['url'] ?? null,
                'use_service_key' => $cfg['use_service_key'] ?? null,
                'anon_key_present' => !empty($cfg['anon_key']),
                'service_key_present' => !empty($cfg['service_key'])
            ];
            $fullDebug[] = "--- SUPABASE CONFIG (summary, keys redacted) ---";
            $fullDebug[] = json_encode($cfgSummary);
        }
    }

    $fullDebug[] = "--- SUPABASE DEBUG LOG (tail) ---";
    if ($supabaseLogTail !== null) {
        $fullDebug[] = $supabaseLogTail;
    } else {
        $fullDebug[] = 'supabase_debug.log not available or not readable';
    }

    // PHP error log
    $phpErrorLogPath = @ini_get('error_log');
    $phpLogTail = null;
    if ($phpErrorLogPath && file_exists($phpErrorLogPath) && is_readable($phpErrorLogPath)) {
        $lines = @file($phpErrorLogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            $tail = array_slice($lines, max(0, count($lines) - 200));
            $phpLogTail = implode("\n", $tail);
        }
    }
    $fullDebug[] = "--- PHP ERROR LOG (tail) ---";
    $fullDebug[] = $phpLogTail ?? 'php error_log not available';

    $fullDebug[] = "--- EXCEPTION TRACE ---";
    $fullDebug[] = $e->getTraceAsString();

    // Combine and redact potential tokens (JWTs and long base64-like strings)
    $fullDebugText = implode("\n", $fullDebug);
    // Redact typical JWT tokens starting with eyJ
    $fullDebugText = preg_replace('/eyJ[A-Za-z0-9_\-\.]{10,}/', '***REDACTED_TOKEN***', $fullDebugText);
    // Redact long base64-like strings
    $fullDebugText = preg_replace('/[A-Za-z0-9_\-]{40,}/', '***REDACTED***', $fullDebugText);

    // Limit size to last 20000 chars to avoid huge responses
    if (strlen($fullDebugText) > 20000) {
        $fullDebugText = substr($fullDebugText, -20000);
    }

    $errorResponse['full_debug'] = $fullDebugText;

    echo json_encode($errorResponse);
}
?>