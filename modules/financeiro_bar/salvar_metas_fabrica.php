<?php
// Limpar qualquer output anterior
ob_clean();

// Configurar headers para JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Desabilitar relatórios de erro para evitar HTML no response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Tratamento de exceções/fatal errors para responder em JSON
set_exception_handler(function($e){
    error_log("Uncaught exception in salvar_metas_fabrica.php: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno no servidor', 'error' => $e->getMessage()]);
    exit;
});

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && ($err['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
        error_log("Fatal error in salvar_metas_fabrica.php: " . print_r($err, true));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Fatal error', 'error' => $err]);
        exit;
    }
});

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    $input = file_get_contents('php://input');
    $dados = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Dados JSON inválidos: ' . json_last_error_msg());
    }

    if (!$dados) throw new Exception('Dados JSON inválidos');

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

    // Suportar múltiplos anos
    $anos = [];
    if (isset($dados['anos']) && is_array($dados['anos']) && !empty($dados['anos'])) {
        $anos = $dados['anos'];
    } elseif (isset($dados['ano'])) {
        $anos = [$dados['ano']];
    } else {
        $anos = [date('Y')];
    }

    set_time_limit(120);

    // LOG para debug
    error_log("=== DEBUG SALVAR METAS FABRICA ===");
    error_log("Meses recebidos: " . print_r($meses, true));
    error_log("Anos recebidos: " . print_r($anos, true));
    error_log("Metas recebidas: " . print_r($metas, true));

    require_once __DIR__ . '/supabase_connection.php';
    $supabase = new SupabaseConnection();

    $todosRegistros = [];
    $registrosProcessados = [];

    foreach ($anos as $ano) {
        foreach ($meses as $mes) {
            $mesInt = intval($mes);
            $dataMeta = sprintf('%04d-%02d-01', intval($ano), $mesInt);

            foreach ($metas as $meta) {
                // Algumas linhas de cálculo sempre devem ser salvas mesmo quando o valor é 0
                $raw_meta_val = floatval($meta['meta'] ?? 0);
                $categoria_raw = isset($meta['categoria']) ? trim($meta['categoria']) : '';
                $categoria_upper = mb_strtoupper($categoria_raw, 'UTF-8');
                $force_save_zero = in_array($categoria_upper, [
                    'RECEITA OPERACIONAL',
                    'RECEITA LÍQUIDA',
                    'LUCRO BRUTO',
                    'LUCRO LÍQUIDO'
                ]);
                if ($raw_meta_val == 0 && !$force_save_zero) continue;

                $subcategoria = isset($meta['subcategoria']) && trim($meta['subcategoria']) !== '' ? trim($meta['subcategoria']) : null;

                // Normalizar nomes para facilitar pesquisas (usar uppercase UTF-8)
                $categoria_norm = mb_strtoupper(trim($meta['categoria'] ?? ''), 'UTF-8');
                $subcategoria_norm = is_null($subcategoria) ? null : mb_strtoupper($subcategoria, 'UTF-8');

                $registro = [
                    'CATEGORIA' => $categoria_norm,
                    'SUBCATEGORIA' => $subcategoria_norm,
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

    error_log("Total de registros a salvar (fabrica): " . count($todosRegistros));
    
    // Debug helper: escreve em error_log, php://stderr e arquivo de debug local
    $debug_log_file = __DIR__ . '/salvar_metas_fabrica_debug.log';
    function salvar_metas_log_debug($msg) {
        global $debug_log_file;
        $ts = date('Y-m-d H:i:s');
        $full = "[{$ts}] " . $msg . PHP_EOL;
        // para o log do PHP/Apache
        error_log($full);
        // tentar escrever no stderr (útil quando rodar via CLI)
        if (defined('STDERR')) {
            @fwrite(STDERR, $full);
        } else {
            // fallback para php://stderr
            $fp = @fopen('php://stderr', 'w');
            if ($fp) {
                @fwrite($fp, $full);
                @fclose($fp);
            }
        }
        // também gravar em arquivo local para inspeção
        @file_put_contents($debug_log_file, $full, FILE_APPEND | LOCK_EX);
    }

    salvar_metas_log_debug("Total de registros a salvar (fabrica): " . count($todosRegistros));
    if (empty($todosRegistros)) {
        throw new Exception('Nenhum registro válido para salvar');
    }

    // Substituir metas do(s) mês(es) selecionado(s): deletar existentes e inserir (mesma técnica do WAB)
    salvar_metas_log_debug('Iniciando substituição de metas em fmetasfabricafinal (registros=' . count($todosRegistros) . ')');

    // Obter lista única de DATA_META presentes nos registros
    $datasMeta = array_values(array_unique(array_map(function($r){ return $r['DATA_META']; }, $todosRegistros)));
    salvar_metas_log_debug('Datas META a serem substituídas (fabrica): ' . implode(', ', $datasMeta));

    // Deletar registros existentes para cada DATA_META
    foreach ($datasMeta as $d) {
        salvar_metas_log_debug('Deletando registros existentes para DATA_META=' . $d . ' na tabela fmetasfabricafinal');
        $delResult = $supabase->delete('fmetasfabricafinal', ['DATA_META' => 'eq.' . $d]);
        salvar_metas_log_debug('Resultado delete for ' . $d . ': ' . var_export($delResult, true));
        if ($delResult === false) {
            salvar_metas_log_debug('Aviso: DELETE retornou false para DATA_META=' . $d . ' (ver supabase_debug.log)');
        }
    }

    // Inserir todos os registros do simulador (INSERT em batch simples)
    salvar_metas_log_debug('Inserindo registros (INSERT batch) na tabela fmetasfabricafinal (fabrica)');
    $insertResult = $supabase->insert('fmetasfabricafinal', $todosRegistros);
    salvar_metas_log_debug('Resultado INSERT (raw): ' . var_export($insertResult, true));

    // Se falhar no insert em lote, tentar inserir em chunks menores (fallback)
    if ($insertResult === false) {
        salvar_metas_log_debug('INSERT em lote retornou false — iniciando fallback por chunks');
        $batchSize = 50;
        $chunks = array_chunk($todosRegistros, $batchSize);
        $inserted = 0;
        foreach ($chunks as $i => $chunk) {
            salvar_metas_log_debug(sprintf('Inserindo chunk %d/%d (size=%d)', $i+1, count($chunks), count($chunk)));
            $ins = $supabase->insert('fmetasfabricafinal', $chunk);
            salvar_metas_log_debug('Resultado insert chunk: ' . var_export($ins, true));
            if ($ins === false) {
                salvar_metas_log_debug('Falha ao inserir chunk ' . ($i+1));
                throw new Exception('Falha ao salvar metas (insert fallback) no Supabase (fmetasfabricafinal)');
            }
            $inserted += count($chunk);
        }
        salvar_metas_log_debug('Fallback insert concluído. total_inseridos=' . $inserted);
        $resultado = true;
    } else {
        $resultado = $insertResult;
    }

    $totalRegistros = count($todosRegistros);

    $response = [
        'success' => true,
        'message' => 'Metas salvas com sucesso (fmetasfabricafinal)',
        'table' => 'fmetasfabricafinal',
        'anos' => $anos,
        'anos_processados' => count($anos),
        'total_registros' => $totalRegistros,
        'meses_processados' => count($meses),
        'metas_processadas' => count($metas),
        'detalhes' => $registrosProcessados
    ];

    echo json_encode($response);

} catch (Exception $e) {
    // Log detalhado para diagnóstico
    $errMsg = "Erro ao salvar metas fabrica: " . $e->getMessage();
    error_log($errMsg);
    if (function_exists('salvar_metas_log_debug')) {
        salvar_metas_log_debug($errMsg);
        salvar_metas_log_debug('Exception file: ' . $e->getFile() . ' line: ' . $e->getLine());
        salvar_metas_log_debug('Stack trace: ' . $e->getTraceAsString());
        // Tentar incluir o JSON de entrada e um resumo dos registros gerados
        $input_preview = isset($input) ? substr($input, 0, 10000) : 'no_input_available';
        salvar_metas_log_debug('Input JSON (preview): ' . $input_preview);
        if (isset($todosRegistros)) {
            salvar_metas_log_debug('Registros a salvar (count): ' . count($todosRegistros));
            // show up to first 20 registros for context
            $sample = array_slice($todosRegistros, 0, 20);
            salvar_metas_log_debug('Registros sample: ' . var_export($sample, true));
        }
    }

    $debug_response = ['file' => $e->getFile(), 'line' => $e->getLine()];
    // Incluir preview do input para o cliente (cuidados com dados sensíveis)
    $debug_response['input_preview'] = isset($input) ? substr($input, 0, 10000) : null;

    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'debug' => $debug_response]);
}
?>