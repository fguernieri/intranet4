<?php
// Limpar qualquer output anterior
ob_clean();

// Configurar headers para JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Desabilitar relatórios de erro para evitar HTML no response
error_reporting(0);
ini_set('display_errors', 0);

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
    
    // Para cada mês selecionado
    foreach ($meses as $mes) {
        // Normalize month to integer and build a proper Y-m-d string
        $mesInt = intval($mes);
        // Use DateTime to avoid accidental day/month swaps and ensure ISO format
        $dt = DateTime::createFromFormat('!Y-n-j', sprintf('%04d-%d-1', intval($ano), $mesInt));
        if ($dt === false) {
            // Fallback: try simple sprintf and trust padding
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
            
            // Verificar se já existe uma meta para essa categoria/subcategoria no mês
            $filtros = [
                'CATEGORIA' => 'eq.' . trim($meta['categoria'] ?? ''),
                'DATA_META' => 'eq.' . $dataMeta
            ];
            
            // Adicionar filtro de subcategoria baseado no valor
            if (isset($meta['subcategoria']) && trim($meta['subcategoria']) !== '') {
                $filtros['SUBCATEGORIA'] = 'eq.' . trim($meta['subcategoria']);
            } else {
                $filtros['SUBCATEGORIA'] = 'is.null';
            }
            
            $existentes = $supabase->select($table, [
                'filters' => $filtros,
                'select' => 'ID'
            ]);
            
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
            
            // DEBUG: Log do registro a ser salvo
            error_log("Registro para " . $dataMeta . ": " . print_r($registro, true));
            
            if (!empty($existentes) && isset($existentes[0]['ID'])) {
                // Atualizar registro existente
                $resultado = $supabase->update($table, $registro, [
                    'ID' => 'eq.' . $existentes[0]['ID']
                ]);
            } else {
                // Inserir novo registro
                $resultado = $supabase->insert($table, $registro);
            }
            
            if ($resultado !== false) {
                $totalRegistros++;
                $registrosProcessados[] = [
                    'categoria' => $meta['categoria'],
                    'subcategoria' => $meta['subcategoria'],
                    'mes' => $mes,
                    'meta' => $meta['meta']
                ];
            }
        }
    }
    
    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Metas salvas com sucesso no Supabase',
        'table' => $table,
        'target' => $target,
        'total_registros' => $totalRegistros,
        'meses_processados' => count($meses),
        'metas_processadas' => count($metas),
        'detalhes' => $registrosProcessados
    ]);
    
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