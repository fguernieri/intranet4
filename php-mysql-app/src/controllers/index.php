<?php
class MainController {
    private $model;
    
    public function __construct() {
        global $pdo;
        $this->model = new OrderModel($pdo);
    }

    public function handleRequest() {
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = $_POST['action'] ?? '';
                $response = ['success' => false];

                switch ($action) {
                    case 'create':
                        if (!empty($_POST['ticket_number'])) {
                            try {
                                $response['success'] = $this->model->createOrder($_POST['ticket_number']);
                            } catch (Exception $e) {
                                $response['success'] = false;
                                $response['error'] = $e->getMessage();
                            }
                        }
                        break;
                        
                    case 'update':
                        if (!empty($_POST['id'])) {
                            $response['success'] = $this->model->updateOrderStatus($_POST['id']);
                        }
                        break;
                        
                    case 'deliver':
                        if (!empty($_POST['id'])) {
                            $response['success'] = $this->model->deliverOrder($_POST['id']);
                        }
                        break;
                        
                    case 'delete':
                        if (!empty($_POST['id'])) {
                            $response['success'] = $this->model->deleteOrder($_POST['id']);
                        }
                        break;
                }

                if (isset($_GET['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit;
                }
            }

            // Load data for display
            $orders = $this->model->getOrders();
            $deliveredToday = $this->model->getDeliveredToday();
            
            // If AJAX request, return only orders partial
            if (isset($_GET['ajax'])) {
                include ROOT_PATH . '/src/views/partials/orders.php';
                exit;
            }

            // Regular page load
            include ROOT_PATH . '/src/views/admin.php';
            
        } catch (Exception $e) {
            error_log("[ERROR] Controller error: " . $e->getMessage());
            if (isset($_GET['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['error' => $e->getMessage()]);
                exit;
            }
            $error = $e->getMessage();
            include ROOT_PATH . '/src/views/admin.php';
        }
    }
}