<?php
require_once '../../config/db_dw.php';

$validBases = ['WAB', 'BDF'];
$base = strtoupper($_POST['base'] ?? 'WAB');
if (!in_array($base, $validBases, true)) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

$tabelaInsumos = $base === 'BDF' ? 'insumos_bastards_bdf' : 'insumos_bastards_wab';

$codigo = $_POST['codigo_cloudify'] ?? null;

header('Content-Type: application/json');

if (!$codigo) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo_dw->prepare("SELECT `Produto` AS nome_prato
                          FROM `$tabelaInsumos`
                          WHERE `Cód. ref.` = :codigo
                          LIMIT 1");
$stmt->execute([':codigo' => $codigo]);
echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: []);
