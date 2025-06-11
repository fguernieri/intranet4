<?php
require_once '../../config/db_dw.php';

$codigo = $_POST['codigo'] ?? null;
$termo = $_POST['termo'] ?? '';

if ($codigo) {
    $stmt = $pdo_dw->prepare("SELECT `Nome` as Insumo, `Cód. Ref.` AS codigo, `Unidade` AS unidade, `Custo médio` AS custo
                              FROM ProdutosBares
                              WHERE `Cód. Ref.` = :codigo
                              LIMIT 1");
    $stmt->execute([':codigo' => $codigo]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if (strlen($termo) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo_dw->prepare("SELECT `Nome` as Insumo, `Cód. Ref.` AS codigo, `Unidade` AS unidade
                           FROM ProdutosBares
                           WHERE `Nome` LIKE :termo
                           LIMIT 20");
$stmt->execute([':termo' => '%' . $termo . '%']);
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($resultados);
