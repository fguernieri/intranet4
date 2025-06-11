<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'].'/auth.php';
session_start();
if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

// sempre usamos o nome do cliente para buscar nos comentários
$cliente = trim($_GET['cliente'] ?? '');
if ($cliente === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetro cliente faltando']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'].'/db_config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    error_log("comentarios_cliente.php: DB Connection Error: " . $conn->connect_error);
    http_response_code(503); // Service Unavailable
    echo json_encode(['error' => 'Serviço indisponível (DB Connect)']);
    exit;
}

try {
    $sql = "
        SELECT 
            id,
            comentario,
            usuario,
            DATE_FORMAT(criado_em, '%d/%m/%Y %H:%i') AS datahora_fmt
        FROM cobrancas_comentarios
        WHERE cliente = ?
        ORDER BY criado_em DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare falhou: ' . $conn->error);
    }
    $stmt->bind_param('s', $cliente);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute falhou: ' . $stmt->error);
    }
    
    $result_obj = $stmt->get_result();
    if (!$result_obj) {
        // This case might be rare if execute() succeeded, but good to be defensive
        throw new Exception('get_result() falhou após execute bem-sucedido: ' . $stmt->error);
    }
    $res = $result_obj->fetch_all(MYSQLI_ASSOC);

    echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
} catch (Throwable $e) {
    http_response_code(500);
    error_log("comentarios_cliente.php: Exception: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
    echo json_encode([
        'error'  => 'Falha interna ao buscar comentários',
        'detail' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} finally {
    $stmt?->close();
    $conn->close();
}
