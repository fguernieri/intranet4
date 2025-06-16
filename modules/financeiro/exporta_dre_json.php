<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

$sql = "
    SELECT 
        CATEGORIA, 
        SUBCATEGORIA, 
        DESCRICAO_CONTA,
        VALOR_EXIBIDO,
        DATA_EXIBIDA,
        STATUS
    FROM vw_financeiro_dre_fabrica
    WHERE YEAR(DATA_EXIBIDA) = 2025
    ORDER BY CATEGORIA, SUBCATEGORIA, DESCRICAO_CONTA
";
$res = $conn->query($sql);
$linhas = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$conn->close();

file_put_contents(__DIR__ . '/dre_2025.json', json_encode($linhas, JSON_UNESCAPED_UNICODE));
echo "JSON gerado com sucesso!";