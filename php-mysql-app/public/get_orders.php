<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/src/config/database.php';
require_once ROOT_PATH . '/src/models/OrderModel.php';

header('Content-Type: application/json');

try {
    $model = new OrderModel($pdo);
    $orders = $model->getOrders();
    echo json_encode($orders);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}