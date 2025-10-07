<?php
// Teste de conexão Supabase
header('Content-Type: application/json');

try {
    // Incluir a classe de conexão Supabase
    require_once __DIR__ . '/supabase_connection.php';
    
    // Testar conexão
    $supabase = new SupabaseConnection();
    
    // Testar se a tabela fmetastap existe fazendo uma consulta simples
    $teste = $supabase->select('fmetastap', [
        'select' => 'ID',
        'limit' => 1
    ]);
    
    echo json_encode([
        'success' => true,
        'conexao_supabase' => 'OK',
        'tabela_fmetastap' => 'Acessível',
        'teste_resultado' => $teste,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'erro' => $e->getMessage(),
        'linha' => $e->getLine(),
        'arquivo' => $e->getFile()
    ]);
}
?>