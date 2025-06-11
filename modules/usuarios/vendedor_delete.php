<?php
require_once __DIR__ . '/../../config/db.php';

$id = $_GET['id'] ?? null;
if ($id) {
  $stmt = $pdo->prepare("UPDATE vendedores SET ativo = 0 WHERE id = ?");
  $stmt->execute([$id]);
}
header('Location: admin_permissoes.php');
