<?php
class OrderModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        error_log("[DEBUG] OrderModel construído");
    }

    private function ticketExists($ticket_number) {
        $sql = "SELECT COUNT(*) FROM orders 
                WHERE ticket_number = ? 
                AND DATE(created_at) = CURDATE()
                AND status != 'delivered'";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$ticket_number]);
        return $stmt->fetchColumn() > 0;
    }

    public function createOrder($ticket_number) {
        try {
            error_log("[DEBUG] Verificando duplicidade da senha: " . $ticket_number);
            
            if ($this->ticketExists($ticket_number)) {
                error_log("[DEBUG] Senha duplicada detectada: " . $ticket_number);
                throw new Exception("Esta senha já está em uso");
            }

            $sql = "INSERT INTO orders (ticket_number, status, created_at) VALUES (?, 'preparing', NOW())";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([$ticket_number]);
            
            error_log("[DEBUG] Nova senha criada: " . ($result ? "Sucesso" : "Falha"));
            return $result;

        } catch (PDOException $e) {
            error_log("[ERRO] PDOException: " . $e->getMessage());
            throw new Exception("Erro ao criar senha");
        }
    }

    public function updateOrderStatus($id) {
        try {
            $sql = "UPDATE orders 
                    SET status = 'ready', 
                        updated_at = NOW() 
                    WHERE id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            
            $result = $stmt->execute();
            error_log("[DEBUG] Update order ID {$id} result: " . ($result ? 'Success' : 'Failed'));
            
            return $result;
        } catch (PDOException $e) {
            error_log("[ERROR] Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function deliverOrder($id) {
        try {
            $sql = "UPDATE orders 
                    SET status = 'delivered', 
                        delivered_at = NOW() 
                    WHERE id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            
            $result = $stmt->execute();
            error_log("[DEBUG] Deliver order ID {$id} result: " . ($result ? 'Success' : 'Failed'));
            
            return $result;
        } catch (PDOException $e) {
            error_log("[ERROR] Deliver failed: " . $e->getMessage());
            return false;
        }
    }

    public function deleteOrder($id) {
        $stmt = $this->pdo->prepare("DELETE FROM orders WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getOrders() {
        try {
            $sql = "SELECT *,
                    TIMESTAMPDIFF(MINUTE, created_at, NOW()) as preparing_time,
                    TIMESTAMPDIFF(MINUTE, updated_at, NOW()) as ready_time
                FROM orders 
                WHERE status IN ('preparing', 'ready')
                ORDER BY 
                    CASE status
                        WHEN 'ready' THEN 1
                        ELSE 0
                    END,
                    created_at ASC";
            
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("[ERROR] Get orders failed: " . $e->getMessage());
            return [];
        }
    }

    public function getDeliveredToday() {
        return $this->pdo->query("
            SELECT * FROM orders 
            WHERE status = 'delivered' 
            AND DATE(delivered_at) = CURDATE()
            ORDER BY delivered_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}

$orderModel = new OrderModel($pdo); // Qual $pdo é esse?
?>