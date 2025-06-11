<?php
include '../../config/db.php'; // Deve expor $pdo

$nome = isset($_GET['nome']) ? trim($_GET['nome']) : '';

if (!$nome) {
  echo json_encode(['erro' => 'Nome não informado']);
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE nome_completo = :nome LIMIT 1");
  $stmt->execute([':nome' => $nome]);
  $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);

  echo json_encode($funcionario ?: []);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['erro' => 'Erro no servidor: ' . $e->getMessage()]);
}
