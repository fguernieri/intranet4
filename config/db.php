<?php
$host = 'localhost';
$dbname = 'basta920_intra_bastards';
$user = 'basta920_intra';
$pass = 'QhEOPhLzS4OD!O';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}