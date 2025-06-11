<?php
require_once __DIR__ . '/../../auth.php';
require_once '../../config/db.php';

$nome = trim($_POST['nome'] ?? '');

if ($nome !== '') {
    $pdo->prepare("INSERT INTO metas_tipos (nome, slug, ativo) VALUES (?, ?, 1)")
        ->execute([$nome, strtolower(preg_replace('/[^a-z0-9]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $nome)))]);
}

header('Location: metas.php');
exit;
