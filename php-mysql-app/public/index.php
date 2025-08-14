<?php
define('ROOT_PATH', dirname(__DIR__));

// Primeiro carrega a conexão
require_once ROOT_PATH . '/src/config/database.php';

// Depois carrega as outras dependências
require_once ROOT_PATH . '/src/models/OrderModel.php';
require_once ROOT_PATH . '/src/controllers/index.php';

// Inicia o controller
$controller = new MainController();
$controller->handleRequest();