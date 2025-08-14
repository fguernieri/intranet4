<?php

class ReservationModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getDailyAvailability($date) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT SUM(number_of_people) as total 
                FROM reservations 
                WHERE date_reserved = ? 
                AND status = 'confirmed'
            ");
            $stmt->execute([$date]);
            $result = $stmt->fetch();
            
            $settings = $this->getSettings();
            $maxPeople = $settings['max_people_per_day'];
            $marginError = $settings['margin_error_percent'];
            
            $reserved = $result['total'] ?? 0;
            $maxWithMargin = $maxPeople + ($maxPeople * ($marginError/100));
            
            return [
                'max_people' => $maxPeople,
                'reserved' => $reserved,
                'available' => max(0, $maxWithMargin - $reserved),
                'margin_error' => $marginError,
                'max_with_margin' => $maxWithMargin
            ];
        } catch (Exception $e) {
            throw new Exception("Erro ao calcular disponibilidade: " . $e->getMessage());
        }
    }

    public function createReservation($data) {
        try {
            $availability = $this->getDailyAvailability($data['date']);
            if ($data['number_of_people'] > $availability['available']) {
                throw new Exception("Capacidade excedida para este dia");
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO reservations 
                (date_reserved, customer_name, number_of_people, phone, notes) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $data['date'],
                $data['customer_name'],
                $data['number_of_people'],
                $data['phone'],
                $data['notes'] ?? null
            ]);
        } catch (Exception $e) {
            throw new Exception("Erro ao criar reserva: " . $e->getMessage());
        }
    }

    public function getReservationsByDate($date) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM reservations 
                WHERE date_reserved = ? 
                AND status = 'confirmed'
                ORDER BY created_at
            ");
            $stmt->execute([$date]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            throw new Exception("Erro ao buscar reservas: " . $e->getMessage());
        }
    }

    public function cancelReservation($id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE reservations 
                SET status = 'cancelled' 
                WHERE id = ?
            ");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            throw new Exception("Erro ao cancelar reserva: " . $e->getMessage());
        }
    }

    private function getSettings() {
        $stmt = $this->pdo->query("SELECT * FROM reservation_settings LIMIT 1");
        return $stmt->fetch();
    }
}