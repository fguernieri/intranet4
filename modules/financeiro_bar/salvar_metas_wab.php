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
    $ano = $dados['ano'] ?? date('Y');
    // Determinar target (tap ou wab). Default = tap
    $target = strtolower(trim($dados['target'] ?? 'wab'));
    $allowed = ['tap' => 'fmetaswab', 'wab' => 'fmetaswab'];
    if (!isset($allowed[$target])) {
        // fallback para tap
        $target = 'tap';
    }
    $table = $allowed[$target];
    
    // DEBUG: Log dos dados recebidos
    error_log("=== DEBUG SALVAR METAS ===");
    error_log("Meses recebidos: " . print_r($meses, true));
    error_log("Metas recebidas: " . print_r($metas, true));
    error_log("Ano: " . $ano);
    
    // Incluir a classe de conexão com Supabase
    require_once __DIR__ . '/supabase_connection.php';
    
    // Criar instância da conexão Supabase
    $supabase = new SupabaseConnection();
    
    $totalRegistros = 0;
    $registrosProcessados = [];
    $failures = [];
    $successRows = [];
    
    // Helper: executar operação supabase com retry exponencial
    function supabaseWithRetry($supabase, $method, $table, $args = [], $maxAttempts = 3) {
        $attempt = 0;
        $delayMs = 100; // initial backoff
        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                if ($method === 'select') {
                    $res = $supabase->select($table, $args);
                } elseif ($method === 'insert') {
                    $res = $supabase->insert($table, $args);
                } elseif ($method === 'update') {
                    $res = $supabase->update($table, $args[0], $args[1] ?? []);
                } else {
                    throw new Exception('Método supabase desconhecido: ' . $method);
                }

                if ($res === false) {
                    // Log and retry if attempts remain
                    error_log("[supabaseWithRetry] tentativa $attempt falhou para $method $table");
                    if ($attempt < $maxAttempts) {
                        usleep($delayMs * 1000);
                        $delayMs *= 2;
                        continue;
                    }
                    return false;
                }

                return $res;
            } catch (Exception $e) {
                error_log("[supabaseWithRetry] exceção tentativa $attempt para $method $table: " . $e->getMessage());
                if ($attempt < $maxAttempts) {
                    usleep($delayMs * 1000);
                    $delayMs *= 2;
                    continue;
                }
                return false;
            }
        }
        return false;
    }

    // Para cada mês selecionado
    foreach ($meses as $mes) {
        $mesInt = intval($mes);
        $dt = DateTime::createFromFormat('!Y-n-j', sprintf('%04d-%d-1', intval($ano), $mesInt));
        if ($dt === false) {
            $dataMeta = sprintf('%04d-%02d-01', intval($ano), $mesInt);
        } else {
            $dataMeta = $dt->format('Y-m-d');
        }

        // Per-month wrapper so one failing month doesn't abort everything
        try {
            // For each meta in this month
            foreach ($metas as $meta) {
                try {
                    if (($meta['meta'] ?? 0) == 0) {
                        continue;
                    }

                    $categoriaFiltro = 'eq.' . trim($meta['categoria'] ?? '');
                    $filtros = [
                        'CATEGORIA' => $categoriaFiltro,
                        'DATA_META' => 'eq.' . $dataMeta
                    ];

                    if (isset($meta['subcategoria']) && trim($meta['subcategoria']) !== '') {
                        $filtros['SUBCATEGORIA'] = 'eq.' . trim($meta['subcategoria']);
                    } else {
                        $filtros['SUBCATEGORIA'] = 'is.null';
                    }

                    $existentes = supabaseWithRetry($supabase, 'select', $table, [ 'filters' => $filtros, 'select' => 'ID' ]);

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

                    error_log("[salvar_metas_wab] Salvando para $dataMeta: " . json_encode($registro));

                    if ($existentes === false) {
                        // Could not fetch existing rows; register failure and continue
                        $failures[] = [ 'mes' => $mesInt, 'categoria' => $meta['categoria'] ?? '', 'subcategoria' => $meta['subcategoria'] ?? '', 'reason' => 'Erro ao verificar existentes (select falhou)'];
                        error_log("[salvar_metas_wab] select falhou para $dataMeta categoria " . ($meta['categoria'] ?? '')); 
                        continue;
                    }

                    if (!empty($existentes) && isset($existentes[0]['ID'])) {
                        $resultado = supabaseWithRetry($supabase, 'update', $table, [$registro, [ 'ID' => 'eq.' . $existentes[0]['ID'] ]]);
                    } else {
                        $resultado = supabaseWithRetry($supabase, 'insert', $table, $registro);
                    }

                    if ($resultado === false) {
                        // Register failure but continue
                        $failures[] = [
                            'mes' => $mesInt,
                            'categoria' => $meta['categoria'] ?? '',
                            'subcategoria' => $meta['subcategoria'] ?? '',
                            'reason' => 'Supabase returned false'
                        ];
                        error_log("[salvar_metas_wab] Falha ao inserir/atualizar para $dataMeta: returned false");
                    } else {
                        $totalRegistros++;
                        $registrosProcessados[] = [ 'categoria' => $meta['categoria'], 'subcategoria' => $meta['subcategoria'] ?? '', 'mes' => $mesInt, 'meta' => $meta['meta'] ];
                        $successRows[] = ['mes' => $mesInt, 'categoria' => $meta['categoria'] ?? ''];
                    }

                } catch (Exception $me) {
                    // Log meta-specific error and continue
                    $failures[] = [ 'mes' => $mesInt, 'categoria' => $meta['categoria'] ?? '', 'subcategoria' => $meta['subcategoria'] ?? '', 'reason' => $me->getMessage() ];
                    error_log("[salvar_metas_wab] Erro meta (mes $mesInt): " . $me->getMessage());
                    continue;
                }
            }

            // Small delay to reduce the chance of hitting rate limits
            usleep(80000); // 80ms

        } catch (Exception $me) {
            $failures[] = [ 'mes' => $mesInt, 'reason' => 'Erro no processamento do mês: ' . $me->getMessage() ];
            error_log("[salvar_metas_wab] Erro processando mês $mesInt: " . $me->getMessage());
            // Continue to next month
            continue;
        }
    }
    
    // Resposta de sucesso
    $response = [
        'success' => true,
        'message' => 'Metas processadas',
        'table' => $table,
        'target' => $target,
        'total_registros' => $totalRegistros,
        'meses_processados' => count($meses),
        'metas_processadas' => count($metas),
        'detalhes' => $registrosProcessados,
        'failures' => $failures,
        'success_rows' => $successRows
    ];

    // If there were failures, include a partial flag and keep HTTP 200 so front-end doesn't receive a 500
    if (!empty($failures)) {
        $response['partial'] = true;
        $response['message'] = 'Processamento parcial: algumas metas não foram salvas';
    }

    echo json_encode($response);
    
} catch (Exception $e) {
    // Log do erro
    error_log("Erro ao salvar metas no Supabase: " . $e->getMessage());
    
    // Resposta de erro
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>