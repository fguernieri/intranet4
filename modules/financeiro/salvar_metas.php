<?php
// filepath: salvar_metas.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Conexão com o banco
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
$metas = $data['metas'] ?? [];
$dataMeta = $data['data'] ?? date('Y-m-d');

if (empty($metas)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados ausentes']);
    exit;
}

// Insere na tabela fMetasFabrica (colunas: Categoria, Subcategoria, Meta, Data)
$stmt = $conn->prepare("INSERT INTO fMetasFabrica (Categoria, Subcategoria, Meta, Data) VALUES (?, ?, ?, ?)");
if (!$stmt) {
    echo json_encode(['sucesso' => false, 'erro' => $conn->error]);
    exit;
}

foreach ($metas as $linha) {
    $categoria = $linha['categoria'];
    $subcategoria = $linha['subcategoria'];
    $metaValor = floatval($linha['valor']);
    $stmt->bind_param("ssds", $categoria, $subcategoria, $metaValor, $dataMeta);
    $stmt->execute();
}

$stmt->close();
$conn->close();
echo json_encode(['sucesso' => true]);
exit;
?>