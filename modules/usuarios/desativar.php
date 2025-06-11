<?php
require_once '../../auth.php';
require_once '../../config/db.php';

if ($_SESSION['usuario_perfil'] !== 'admin') {
    echo "Acesso restrito.";
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "ID inválido.";
    exit;
}

$stmt = $pdo->prepare("UPDATE usuarios SET ativo = 0 WHERE id = :id");
$stmt->execute(['id' => $id]);

header('Location: listar.php?msg=desativado');
exit;
