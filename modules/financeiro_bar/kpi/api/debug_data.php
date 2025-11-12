<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Sao_Paulo');

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
require_once __DIR__ . '/../../supabase_connection.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id'])) {
    die('Não autenticado');
}

header('Content-Type: text/plain; charset=utf-8');

try {
    $supabase = new SupabaseConnection();
    
    echo "=== DIAGNÓSTICO DE DADOS ===\n\n";
    
    // 1. Verificar dados na view fdespesastap_detalhes
    echo "1. Verificando fdespesastap_detalhes:\n";
    echo str_repeat("-", 50) . "\n";
    
    $despesas = $supabase->select('fdespesastap_detalhes', [
        'select' => 'data_mes,categoria_pai,categoria,vlr_total',
        'order' => 'data_mes.desc',
        'limit' => 100
    ]) ?: [];
    
    echo "Total de registros retornados: " . count($despesas) . "\n\n";
    
    if (!empty($despesas)) {
        // Agrupar por data_mes
        $por_mes = [];
        foreach ($despesas as $d) {
            $mes = substr($d['data_mes'], 0, 7); // YYYY-MM
            if (!isset($por_mes[$mes])) {
                $por_mes[$mes] = 0;
            }
            $por_mes[$mes]++;
        }
        
        echo "Registros por mês:\n";
        ksort($por_mes);
        foreach ($por_mes as $mes => $count) {
            echo "  $mes: $count registros\n";
        }
        
        echo "\n";
        
        // Listar categorias_pai únicas
        $categorias_pai = [];
        foreach ($despesas as $d) {
            $cat = strtoupper(trim($d['categoria_pai'] ?? ''));
            if (!isset($categorias_pai[$cat])) {
                $categorias_pai[$cat] = 0;
            }
            $categorias_pai[$cat]++;
        }
        
        echo "Categorias Pai encontradas:\n";
        arsort($categorias_pai);
        foreach ($categorias_pai as $cat => $count) {
            echo "  '$cat': $count registros\n";
        }
        
        echo "\n";
        
        // Mostrar primeiros 5 registros como exemplo
        echo "Primeiros 5 registros:\n";
        for ($i = 0; $i < min(5, count($despesas)); $i++) {
            $d = $despesas[$i];
            echo sprintf(
                "  [%d] %s | Pai: '%s' | Cat: '%s' | Valor: R$ %.2f\n",
                $i + 1,
                $d['data_mes'],
                $d['categoria_pai'] ?? 'NULL',
                $d['categoria'] ?? 'NULL',
                $d['vlr_total'] ?? 0
            );
        }
    }
    
    echo "\n\n";
    
    // 2. Verificar dados na view fdespesastap (agregada)
    echo "2. Verificando fdespesastap (agregada):\n";
    echo str_repeat("-", 50) . "\n";
    
    $despesas_agg = $supabase->select('fdespesastap', [
        'select' => 'data_mes,categoria_pai,total_receita_mes',
        'order' => 'data_mes.desc',
        'limit' => 50
    ]) ?: [];
    
    echo "Total de registros retornados: " . count($despesas_agg) . "\n\n";
    
    if (!empty($despesas_agg)) {
        $por_mes_agg = [];
        foreach ($despesas_agg as $d) {
            $mes = substr($d['data_mes'], 0, 7);
            if (!isset($por_mes_agg[$mes])) {
                $por_mes_agg[$mes] = [];
            }
            $cat = strtoupper(trim($d['categoria_pai'] ?? ''));
            $por_mes_agg[$mes][$cat] = floatval($d['total_receita_mes'] ?? 0);
        }
        
        echo "Dados agregados por mês e categoria:\n";
        ksort($por_mes_agg);
        foreach ($por_mes_agg as $mes => $cats) {
            echo "\n  $mes:\n";
            foreach ($cats as $cat => $total) {
                echo sprintf("    %-25s: R$ %12s\n", $cat, number_format($total, 2, ',', '.'));
            }
        }
    }
    
    echo "\n\n";
    
    // 3. Verificar últimos 12 meses fechados
    echo "3. Período que deveria ser analisado:\n";
    echo str_repeat("-", 50) . "\n";
    
    $first_day_this_month = date('Y-m-01');
    $last_closed = date('Y-m-01', strtotime('-1 month', strtotime($first_day_this_month)));
    $start = date('Y-m-01', strtotime('-11 months', strtotime($last_closed)));
    
    echo "Data início: $start\n";
    echo "Data fim: $last_closed\n";
    echo "Mês atual (excluído): $first_day_this_month\n\n";
    
    echo "Meses que deveriam ter dados:\n";
    for ($i = 0; $i < 12; $i++) {
        $mes = date('Y-m-01', strtotime("+{$i} months", strtotime($start)));
        echo "  " . date('M/Y', strtotime($mes)) . " ($mes)\n";
    }
    
    echo "\n\n=== FIM DO DIAGNÓSTICO ===\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
