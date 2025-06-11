<?php
session_start();
require_once 'config/db.php';

$email = trim($_POST['email'] ?? '');
$senha = trim($_POST['senha'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = :email AND ativo = 1");
$stmt->execute(['email' => $email]);
$usuario = $stmt->fetch();

if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome'];
    $_SESSION['usuario_perfil'] = $usuario['perfil'];
    header('Location: painel.php');
    exit;
} else {
    header('Location: login.php?erro=1');
    exit;
}
