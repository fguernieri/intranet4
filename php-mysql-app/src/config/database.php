<?php
// Configure error logging
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php/logs/php_error.log');
error_reporting(E_ALL);

try {
    // Database credentials
    $host = 'bastardsbrewery.com.br';
    $dbname = 'basta920_dw_fabrica';
    $username = 'basta920_lucas';
    $password = 'C;f.(7(2K+D%';

    // Create connection
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    error_log("[DEBUG] Database connection successful");
} catch(PDOException $e) {
    error_log("[ERROR] Database connection failed: " . $e->getMessage());
    die("Conexão falhou: " . $e->getMessage());
}
?>