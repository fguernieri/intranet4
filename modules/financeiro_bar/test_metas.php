<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/supabase_connection.php';

try {
    $supabase = new SupabaseConnection();
    
    echo "<h2>Teste de Conexão com fmetastap</h2>";
    
    // Teste 1: Listar todas as metas
    echo "<h3>1. Todas as metas:</h3>";
    $todas_metas = $supabase->select('fmetastap', [
        'select' => 'CATEGORIA,SUBCATEGORIA,META',
        'limit' => 10
    ]);
    
    echo "<pre>";
    print_r($todas_metas);
    echo "</pre>";
    
    // Teste 2: Buscar meta específica
    echo "<h3>2. Meta específica - RECEITA BRUTA:</h3>";
    $meta_receita = $supabase->select('fmetastap', [
        'select' => 'META',
        'filters' => [
            'CATEGORIA' => 'eq.RECEITA BRUTA'
        ],
        'limit' => 1
    ]);
    
    echo "<pre>";
    print_r($meta_receita);
    echo "</pre>";
    
    // Teste 3: Verificar se existe a tabela
    echo "<h3>3. Teste de existência da tabela:</h3>";
    $teste_tabela = $supabase->select('fmetastap', [
        'select' => '*',
        'limit' => 1
    ]);
    
    echo "<pre>";
    print_r($teste_tabela);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "Erro: " . $e->getMessage();
    echo "</div>";
}
?>