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
    
    // Suportar múltiplos anos (array) ou ano único (retrocompatibilidade)
    $anos = [];
    if (isset($dados['anos']) && is_array($dados['anos']) && !empty($dados['anos'])) {
        $anos = $dados['anos'];
    } elseif (isset($dados['ano'])) {
        $anos = [$dados['ano']];
    } else {
        $anos = [date('Y')];
    }
    
    // Determinar target (tap ou wab). Default = tap
    $target = strtolower(trim($dados['target'] ?? 'tap'));
    $allowed = ['tap' => 'fmetastap', 'wab' => 'fmetaswab'];
    if (!isset($allowed[$target])) {
        // fallback para tap
        $target = 'tap';
    }
    $table = $allowed[$target];
    
    // Aumentar timeout para processamento de muitos registros
    set_time_limit(120);
    
    // DEBUG: Log dos dados recebidos
    error_log("=== DEBUG SALVAR METAS ===");
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
            $dataMeta = sprintf('%04d-%02d-01', intval($ano), intval($mes));
            
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
                    'mes' => $mes,
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
        // Fallback: alguns schemas/tabelas não possuem constraint compatível com ON CONFLICT
        // (erro 42P10: "there is no unique or exclusion constraint matching the ON CONFLICT specification").
        // Neste caso, removemos previamente os registros com as mesmas DATA_META e re-inserimos.
        error_log("Upsert falhou - tentando fallback: delete + insert");

        // Extrair DATA_META únicos dos registros para usar no DELETE
        $datasMeta = array_unique(array_map(function($r) {
            return $r['DATA_META'];
        }, $todosRegistros));

        foreach ($datasMeta as $dm) {
            // DELETE WHERE DATA_META=eq.<data>
            error_log("Fallback: deletando registros existentes para DATA_META=" . $dm);
            $delResult = $supabase->delete($table, ['DATA_META' => 'eq.' . $dm]);
            error_log("Resultado DELETE for " . $dm . ": " . var_export($delResult, true));
        }

        // Tentar INSERT em batch com os registros (sem on_conflict)
        $insertResult = $supabase->insert($table, $todosRegistros);
        error_log("Resultado INSERT (fallback): " . ($insertResult !== false ? 'sucesso' : 'falha'));

        if ($insertResult === false) {
            throw new Exception('Falha ao salvar metas no Supabase (upsert e fallback insert falharam)');
        }
        // Caso o insert tenha funcionado, considerar como sucesso e sobrescrever $resultado
        $resultado = $insertResult;
    }
    
    // Resposta de sucesso
    echo json_encode([
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