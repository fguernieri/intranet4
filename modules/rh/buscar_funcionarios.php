<?php
include '../../config/db.php'; // Deve expor $pdo (PDO)

$nome = isset($_GET['nome']) ? trim($_GET['nome']) : '';

if (strlen($nome) < 3) {
  echo json_encode([]);
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT nome_completo FROM funcionarios WHERE nome_completo LIKE :nome LIMIT 10");
  $stmt->execute([':nome' => "%$nome%"]);
  $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode($result);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['erro' => 'Erro na consulta: ' . $e->getMessage()]);
}
