<?php

// Definir ROOT_PATH
define('ROOT_PATH', dirname(dirname(__DIR__)));

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/index.php';

// Inicializa o modelo com a conexão PDO
$orderModel = new OrderModel($pdo);

$url = 'http://localhost/php-mysql-app/src/views/admin.php';
$concurrent = 50;
$totalRequests = 1000;  // Aumentado para 1000 pedidos

// Configurações de simulação
$readyRate = 0.70;      // 70% dos pedidos serão marcados como prontos
$deliveredRate = 0.85;  // 85% dos pedidos prontos serão entregues

// Array para controlar números já usados
$usedNumbers = array();

$results = array();
$curl_arr = array();
$mh = curl_multi_init();

// Métricas
$db_inserts = 0;
$db_ready = 0;
$db_delivered = 0;
$db_errors = 0;
$start_time = microtime(true);

for ($i = 0; $i < $totalRequests; $i++) {
    try {
        // Gerar número único de ticket
        do {
            $ticket_number = sprintf("%04d", rand(1, 9999));
        } while (in_array($ticket_number, $usedNumbers));
        
        // Registrar número usado
        $usedNumbers[] = $ticket_number;
        
        // 1. Criar pedido (status: preparing)
        if ($orderModel->createOrder($ticket_number)) {
            $db_inserts++;
            
            // Simular delay entre operações
            usleep(rand(100000, 300000)); // 0.1 a 0.3 segundos
            
            // Buscar ID do pedido criado
            $stmt = $pdo->prepare("SELECT id FROM orders WHERE ticket_number = ?");
            $stmt->execute([$ticket_number]);
            $order = $stmt->fetch();
            
            if ($order) {
                // 2. Atualizar para pronto (70% dos pedidos)
                if (rand(1, 100) <= ($readyRate * 100)) {
                    if ($orderModel->updateOrder($order['id'])) {
                        $db_ready++;
                        
                        // Simular tempo de preparação
                        usleep(rand(200000, 500000)); // 0.2 a 0.5 segundos
                        
                        // 3. Marcar como entregue (85% dos pedidos prontos)
                        if (rand(1, 100) <= ($deliveredRate * 100)) {
                            if ($orderModel->markAsDelivered($order['id'])) {
                                $db_delivered++;
                            }
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        $db_errors++;
        echo "Erro DB: " . $e->getMessage() . "\n";
        continue;
    }

    // Testar interface web
    $curl_arr[$i] = curl_init();
    curl_setopt($curl_arr[$i], CURLOPT_URL, $url);
    curl_setopt($curl_arr[$i], CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_arr[$i], CURLOPT_POST, true);
    curl_setopt($curl_arr[$i], CURLOPT_POSTFIELDS, http_build_query([
        'ticket_number' => $ticket_number
    ]));
    curl_multi_add_handle($mh, $curl_arr[$i]);
    
    if (($i + 1) % $concurrent === 0) {
        $running = null;
        do {
            curl_multi_exec($mh, $running);
        } while ($running > 0);
        
        foreach($curl_arr as $id => $ch) {
            $results[$id] = array(
                'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
                'time' => curl_getinfo($ch, CURLINFO_TOTAL_TIME)
            );
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        
        $curl_arr = array();
        echo "Lote " . (($i + 1) / $concurrent) . " de " . ($totalRequests / $concurrent) . " processado\n";
    }
}

curl_multi_close($mh);

// Análise dos resultados
$success = 0;
$failed = 0;
$totalTime = 0;
$end_time = microtime(true);

foreach ($results as $result) {
    if ($result['http_code'] === 200) $success++;
    else $failed++;
    $totalTime += $result['time'];
}

echo "\n=== RESULTADOS DO TESTE DE STRESS ===\n";
echo "Duração total: " . round($end_time - $start_time, 2) . " segundos\n";
echo "\nMÉTRICAS HTTP:\n";
echo "Total de requisições: $totalRequests\n";
echo "Requisições bem sucedidas: $success\n";
echo "Requisições falhas: $failed\n";
echo "Tempo médio de resposta: " . round($totalTime / count($results), 3) . " segundos\n";
echo "\nMÉTRICAS DO BANCO DE DADOS:\n";
echo "Total de pedidos criados: $db_inserts\n";
echo "Pedidos marcados como prontos: $db_ready\n";
echo "Pedidos entregues: $db_delivered\n";
echo "Erros de banco: $db_errors\n";
echo "\nDISTRIBUIÇÃO DE STATUS:\n";
echo "Preparando: " . ($db_inserts - $db_ready) . " pedidos\n";
echo "Prontos: " . ($db_ready - $db_delivered) . " pedidos\n";
echo "Entregues: $db_delivered pedidos\n";

// Adicionar mais métricas ao relatório
echo "\nTEMPOS MÉDIOS:\n";
echo "Tempo médio entre criação e pronto: " . 
    round(($db_ready > 0 ? $totalTime/$db_ready : 0), 2) . " segundos\n";
echo "Tempo médio entre pronto e entrega: " . 
    round(($db_delivered > 0 ? $totalTime/$db_delivered : 0), 2) . " segundos\n";
echo "\nTAXAS DE CONVERSÃO:\n";
echo "Taxa de pedidos prontos: " . 
    round(($db_ready/$db_inserts) * 100, 1) . "%\n";
echo "Taxa de pedidos entregues: " . 
    round(($db_delivered/$db_ready) * 100, 1) . "%\n";

// Limpar dados de teste
try {
    $stmt = $pdo->prepare("DELETE FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $stmt->execute();
    echo "\nLimpeza: " . $stmt->rowCount() . " registros de teste removidos\n";
} catch (PDOException $e) {
    echo "Erro na limpeza: " . $e->getMessage() . "\n";
}