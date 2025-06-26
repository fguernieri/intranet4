<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['sucesso' => false, 'erro' => "Conexão falhou: " . $conn->connect_error]);
    exit;
}

$sqlMetas = "
    SELECT
        fm1.Categoria,
        fm1.Subcategoria,
        fm1.Meta,
        fm1.Percentual
    FROM
        fMetasFabrica fm1
    INNER JOIN (
        SELECT
            Categoria,
            Subcategoria,
            MAX(Data) as MaxData
        FROM
            fMetasFabrica
        GROUP BY
            Categoria, Subcategoria
    ) fm2
    ON fm1.Categoria = fm2.Categoria
    AND IFNULL(fm1.Subcategoria, '') = IFNULL(fm2.Subcategoria, '')
    AND fm1.Data = fm2.MaxData
";
$resMetas = $conn->query($sqlMetas);
$metas = [];
if ($resMetas) {
    while ($m = $resMetas->fetch_assoc()){
        $cat = $m['Categoria'];
        $sub = $m['Subcategoria'] ?? '';
        $metas[$cat][$sub] = [
            'valor' => floatval($m['Meta']),
            'percentual' => floatval($m['Percentual'])
        ];
    }
}

$conn->close();
header('Content-Type: application/json');
echo json_encode(['sucesso' => true, 'metas' => $metas]);