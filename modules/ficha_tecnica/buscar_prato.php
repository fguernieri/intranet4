<?php
require_once '../../config/db_dw.php';

$validBases = ['WAB', 'BDF'];
$base = strtoupper($_POST['base'] ?? 'WAB');
if (!in_array($base, $validBases, true)) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}


$tabelaProdutos = $base === 'BDF' ? 'ProdutosBares_BDF' : 'ProdutosBares_WAB';

$codigo = trim((string)($_POST['codigo_cloudify'] ?? ''));

header('Content-Type: application/json');

if ($codigo === '') {
    echo json_encode([]);
    exit;
}

$stmt = $pdo_dw->prepare("SELECT `Nome` AS nome_prato
                          FROM `$tabelaProdutos`
                          WHERE `Cód. Ref.` = :codigo
                          LIMIT 1");
$stmt->execute([':codigo' => $codigo]);
echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: []);
