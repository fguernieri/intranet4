<?php
// Configure error logging
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php/logs/php_error.log');
error_reporting(E_ALL);

try {
    // Credenciais do banco de ORDERS
    $host = 'bastardsbrewery.com.br';
    $dbname = 'basta920_dw_fabrica';
    $username = 'basta920_lucas';
    $password = 'C;f.(7(2K+D%';

    // Cria conexão PDO específica para orders
    $pdo_orders = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    error_log("[DEBUG] Database ORDERS connection successful");
} catch(PDOException $e) {
    error_log("[ERROR] Database ORDERS connection failed: " . $e->getMessage());
    die("Conexão ORDERS falhou: " . $e->getMessage());
}
?>