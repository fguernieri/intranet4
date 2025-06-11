<?php
require_once '../../auth.php';
require_once '../../config/db.php';

$nome = $_POST['nome'];
$email = $_POST['email'];
$senha = $_POST['senha'];
$cargo = $_POST['cargo'];
$setor = $_POST['setor'];
$perfil = $_POST['perfil'];

$senha_hash = password_hash($senha, PASSWORD_DEFAULT); // Criptografando corretamente

$stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, cargo, setor, perfil) 
                       VALUES (:nome, :email, :senha, :cargo, :setor, :perfil)");
$stmt->execute([
    'nome' => $nome,
    'email' => $email,
    'senha' => $senha_hash,
    'cargo' => $cargo,
    'setor' => $setor,
    'perfil' => $perfil
]);

header('Location: novo.php?ok=1');
exit;
