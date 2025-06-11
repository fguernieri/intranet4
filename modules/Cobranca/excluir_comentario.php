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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$comment_id_raw = trim($_POST['comment_id'] ?? '');
error_log("excluir_comentario.php: Received raw POST 'comment_id': " . var_export($_POST['comment_id'] ?? null, true) . ". Trimmed to: '" . $comment_id_raw . "'");

if (!ctype_digit($comment_id_raw) || $comment_id_raw === '0') {
    // Log detalhado da falha na validação
    error_log("excluir_comentario.php: Validation FAILED for comment_id_raw: '" . $comment_id_raw . "'. ctype_digit: " . (ctype_digit($comment_id_raw) ? 'true' : 'false') . ", is '0' string: " . ($comment_id_raw === '0' ? 'true' : 'false'));
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'ID do comentário inválido']);
    exit;
}
$comment_id = (int)$comment_id_raw;

require_once $_SERVER['DOCUMENT_ROOT'].'/db_config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

try {
    $stmt = $conn->prepare("DELETE FROM cobrancas_comentarios WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Prepare falhou: ' . $conn->error);
    }
    $stmt->bind_param('i', $comment_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute falhou: ' . $stmt->error);
    }

    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'OK', 'message' => 'Comentário excluído']);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Comentário não encontrado ou já excluído']);
    }
} catch (Throwable $e) {
    error_log("excluir_comentario.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error'  => 'Falha interna ao excluir comentário', 'detail' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} finally {
    $stmt?->close();
    $conn->close();
}