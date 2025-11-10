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
    
    // UPSERT em batch - uma única requisição para todos os registros
    // O Supabase vai inserir novos e atualizar existentes baseado na chave única
    $resultado = $supabase->upsert($table, $todosRegistros, [
        'on_conflict' => 'CATEGORIA,SUBCATEGORIA,DATA_META'
    ]);
    
    $totalRegistros = count($todosRegistros);
    
    // DEBUG: Log do resultado
    error_log("Resultado do upsert: " . ($resultado !== false ? 'sucesso' : 'falha'));
    
    if ($resultado === false) {
        throw new Exception('Falha ao salvar metas no Supabase');
    }
    
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