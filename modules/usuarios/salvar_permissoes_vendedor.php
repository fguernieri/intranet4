<?php
require_once '../../config/db.php';

// limpar permissões antigas
$uid = $_POST['usuario_id'];
$pdo->prepare("DELETE FROM user_vendedor_permissoes WHERE usuario_id = ?")
    ->execute([$uid]);

// inserir novas
if (!empty($_POST['vendedores'])) {
  $ins = $pdo->prepare(
    "INSERT INTO user_vendedor_permissoes(usuario_id,vendedor_id) VALUES(?,?)"
  );
  foreach ($_POST['vendedores'] as $vid) {
    $ins->execute([$uid, $vid]);
  }
}

header("Location: admin_permissoes.php?user_id=$uid&ok=1");
exit;
