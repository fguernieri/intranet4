<?php
require_once __DIR__ . '/../../auth.php';
$pdoMain = $pdo;
require_once '../../config/db.php';
$pdoMetas = $pdo;

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$id_vendedor = (int) ($data['id_vendedor'] ?? 0);
$id_tipo     = (int) ($data['id_tipo'] ?? 0);
$valor       = (float) ($data['valor'] ?? 0);
$ano         = (int) ($data['ano'] ?? date('Y'));
$mes         = (int) ($data['mes'] ?? date('m'));

// Verifica se já existe uma meta para esse vendedor, tipo, mês e ano
$sqlCheck = "
  SELECT COUNT(*) FROM metas_valores 
  WHERE id_vendedor = ? AND id_tipo = ? AND ano = ? AND mes = ?
";
$stmtCheck = $pdoMetas->prepare($sqlCheck);
$stmtCheck->execute([$id_vendedor, $id_tipo, $ano, $mes]);
$exists = $stmtCheck->fetchColumn() > 0;

if ($exists) {
    // Atualiza
    $sql = "
      UPDATE metas_valores 
      SET valor = ? 
      WHERE id_vendedor = ? AND id_tipo = ? AND ano = ? AND mes = ?
    ";
    $params = [$valor, $id_vendedor, $id_tipo, $ano, $mes];
} else {
    // Insere
    $sql = "
      INSERT INTO metas_valores (id_vendedor, id_tipo, ano, mes, valor) 
      VALUES (?, ?, ?, ?, ?)
    ";
    $params = [$id_vendedor, $id_tipo, $ano, $mes, $valor];
}

try {
    $stmt = $pdoMetas->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
