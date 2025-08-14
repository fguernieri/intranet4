<?php
define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/src/config/database.php';
require_once ROOT_PATH . '/src/models/OrderModel.php';  // Changed from index.php
require_once ROOT_PATH . '/src/controllers/index.php';

try {
    $model = new OrderModel($pdo);
    $orders = $model->getOrders();
    include ROOT_PATH . '/src/views/display.php';
} catch (Exception $e) {
    error_log($e->getMessage());
    echo "Error loading display";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel de Senhas</title>
    <style>
        body { 
            font-family: Arial; 
            margin: 0;
            background: #000;
            color: #fff;
        }
        .display-container {
            display: flex;
            min-height: 100vh;
        }
        .column {
            flex: 1;
            padding: 20px;
            text-align: center;
        }
        .preparing {
            background: #2c3e50;
        }
        .ready {
            background: #27ae60;
        }
        .number {
            font-size: 48px;
            margin: 20px 0;
        }
        h1 {
            font-size: 36px;
            margin-bottom: 30px;
        }
    </style>
    <meta http-equiv="refresh" content="5">
</head>
<body>
    <div class="display-container">
        <div class="column preparing">
            <h1>EM PREPARO</h1>
            <?php if(!empty($orders)): ?>
                <?php foreach($orders as $order): ?>
                    <?php if($order['status'] == 'preparing'): ?>
                        <div class="number">
                            <?= htmlspecialchars($order['ticket_number']) ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="column ready">
            <h1>PRONTO</h1>
            <?php if(!empty($orders)): ?>
                <?php foreach($orders as $order): ?>
                    <?php if($order['status'] == 'ready'): ?>
                        <div class="number">
                            <?= htmlspecialchars($order['ticket_number']) ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>