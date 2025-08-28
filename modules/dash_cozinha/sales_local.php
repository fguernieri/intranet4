<?php
// Fallback: retorna vendas agregadas do banco local (vendas_resumidas_cozinha)
// Parâmetros: GET inicio=YYYY-MM-DD, fim=YYYY-MM-DD

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

$inicio = isset($_GET['inicio']) ? $_GET['inicio'] : date('Y-m-01');
$fim    = isset($_GET['fim'])    ? $_GET['fim']    : date('Y-m-d');

// Garante formato de data simples
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio)) $inicio = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim))    $fim    = date('Y-m-d');

$sql = "SELECT produto_id AS codigo, SUM(quantidade) AS quantidade
        FROM vendas_resumidas_cozinha
        WHERE data BETWEEN :ini AND :fim
        GROUP BY produto_id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':ini'=>$inicio, ':fim'=>$fim]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
  'params' => ['inicio'=>$inicio, 'fim'=>$fim],
  'vendas' => array_map(function($r){ return ['codigo'=>$r['codigo'], 'nome'=>'', 'quantidade'=>(float)$r['quantidade']]; }, $rows)
]);
exit;
?>

