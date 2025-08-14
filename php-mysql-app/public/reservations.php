<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/src/config/database.php';
require_once ROOT_PATH . '/src/models/ReservationModel.php';
require_once ROOT_PATH . '/src/controllers/ReservationController.php';

$controller = new ReservationController($pdo);
$controller->handleRequest();