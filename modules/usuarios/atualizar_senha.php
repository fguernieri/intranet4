<?php
require_once '../../auth.php';
require_once '../../config/db.php';

$id    = $_SESSION['usuario_id'];
$atual = $_POST['senha_atual']    ?? '';
$nova  = $_POST['senha_nova']     ?? '';
$conf  = $_POST['senha_confirma'] ?? '';

// Busca hash atual
$stmt = $pdo->prepare("SELECT senha_hash FROM usuarios WHERE id = :id");
$stmt->execute(['id' => $id]);
$user = $stmt->fetch();

if (!$user || !password_verify($atual, $user['senha_hash'])) {
    header('Location: alterar_senha.php?erro=atual');
    exit;
}
if ($nova !== $conf) {
    header('Location: alterar_senha.php?erro=confirma');
    exit;
}
if (password_verify($nova, $user['senha_hash'])) {
    header('Location: alterar_senha.php?erro=igual');
    exit;
}

// Atualiza para o novo hash
$hash = password_hash($nova, PASSWORD_DEFAULT);
$upd  = $pdo->prepare("UPDATE usuarios SET senha_hash = :h WHERE id = :id");
$upd->execute(['h' => $hash, 'id' => $id]);

header('Location: alterar_senha.php?ok=1');
exit;
