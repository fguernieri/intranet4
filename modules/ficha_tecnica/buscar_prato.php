<?php
require_once '../../config/db_dw.php';

$codigo = $_POST['codigo_cloudify'] ?? null;

if (!$codigo) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo_dw->prepare("SELECT `Produto` AS nome_prato
                          FROM insumos_bastards
                          WHERE `Cód. ref.` = :codigo
                          LIMIT 1");
$stmt->execute([':codigo' => $codigo]);
echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: []);
