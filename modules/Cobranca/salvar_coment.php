<?php
/* modules/Cobranca/salvar_coment.php
   Grava comentário; responde JSON {status:"OK"} ou {error:"…"}        */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'].'/auth.php';
session_start();
// Adicionar verificação de autenticação
if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}
$usuario = $_SESSION['usuario_nome'] ?? 'SEM_LOGIN';

/* ---------- validação --------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error'=>'Método não permitido']); exit;
}

$id_raw  = trim($_POST['id_cliente'] ?? '');
$cliente = trim($_POST['cliente']     ?? '');
$coment  = trim($_POST['comentario']  ?? '');

if ($coment === '') {
    http_response_code(400);
    echo json_encode(['error'=>'Comentário vazio']); exit;
}

/* $id_cli = inteiro (>0) ou NULL; aceita string vazia ---------------- */
$id_cli = (ctype_digit($id_raw) && $id_raw !== '0')
        ? (int)$id_raw
        : null;

require_once $_SERVER['DOCUMENT_ROOT'].'/db_config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

try {
    $stmt = $conn->prepare(
        "INSERT INTO cobrancas_comentarios
         (id_cliente, cliente, usuario, comentario)
         VALUES (?, ?, ?, ?)"
    );
    if (!$stmt) throw new Exception('Prepare: '.$conn->error);

    /*  i = int (pode ser NULL); s s  */
    $stmt->bind_param('isss', $id_cli, $cliente, $usuario, $coment);
    if (!$stmt->execute()) throw new Exception('Execute: '.$stmt->error);

    echo json_encode(['status'=>'OK']);
} catch (Throwable $e) {
    error_log("salvar_coment.php: ".$e->getMessage());
    http_response_code(500);
    echo json_encode(['error'=>'Falha no servidor','detail'=>$e->getMessage()]);
} finally {
    $stmt?->close(); $conn->close();
}