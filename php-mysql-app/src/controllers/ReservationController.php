<?php

class ReservationController {
    private $model;
    
    public function __construct($pdo) {
        $this->model = new ReservationModel($pdo);
    }

    public function handleRequest() {
        $action = $_POST['action'] ?? '';
        $error = '';
        $success = '';
        
        try {
            switch($action) {
                case 'create':
                    if ($this->validateReservationData($_POST)) {
                        $this->model->createReservation($_POST);
                        $success = "Reserva criada com sucesso!";
                    }
                    break;
                
                case 'cancel':
                    if (isset($_POST['id'])) {
                        $this->model->cancelReservation($_POST['id']);
                        $success = "Reserva cancelada com sucesso!";
                    }
                    break;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        // Preparar dados para a view
        $nextDays = [];
        $daysToShow = 60;
        
        for($i = 0; $i < $daysToShow; $i++) {
            $date = date('Y-m-d', strtotime("+$i days"));
            if($this->isValidWeekday($date)) {
                $nextDays[$date] = $this->model->getDailyAvailability($date);
                // Adiciona as reservas do dia
                $nextDays[$date]['reservations'] = $this->model->getReservationsByDate($date);
            }
        }

        // Carregar view
        include ROOT_PATH . '/src/views/reservations/index.php';
    }

    private function validateReservationData($data) {
        if (empty($data['customer_name'])) throw new Exception("Nome do cliente é obrigatório");
        if (empty($data['date'])) throw new Exception("Data é obrigatória");
        if (empty($data['number_of_people'])) throw new Exception("Número de pessoas é obrigatório");
        if (empty($data['phone'])) throw new Exception("Telefone é obrigatório");
        
        if (!$this->isValidWeekday($data['date'])) {
            throw new Exception("Reservas só são permitidas de terça a sexta");
        }
        
        return true;
    }

    private function isValidWeekday($date) {
        $weekday = date('w', strtotime($date));
        return in_array($weekday, [2,3,4,5]); // terça a sexta
    }
}