<?php
require_once '../../config/db.php';      // Intranet (ficha_tecnica + ingredientes)
require_once '../../config/db_dw.php';   // Cloudify (insumos_bastards)

header('Content-Type: application/json');

$codigo = isset($_GET['cod_cloudify']) ? trim($_GET['cod_cloudify']) : '';
$ficha_intranet = [];
$ficha_cloudify = [];
$status = 'cinza';

if ($codigo !== '') {
    // Ficha INTRANET
    $sql_intranet = "
        SELECT codigo AS codigo_insumo, descricao AS nome_insumo, unidade, quantidade
        FROM ingredientes
        WHERE ficha_id = (
            SELECT id FROM ficha_tecnica WHERE codigo_cloudify = :codigo
        )";
    $stmt = $pdo->prepare($sql_intranet);
    $stmt->execute([':codigo' => $codigo]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ficha_intranet[$row['codigo_insumo']] = $row;
    }

    // Ficha CLOUDIFY
    $sql_cloud = "
        SELECT `Cód. ref..1` AS codigo_insumo, `Insumo` AS nome_insumo, `Und.` AS unidade, `Qtde.` AS quantidade
        FROM insumos_bastards
        WHERE `Cód. ref.` = :codigo";
    $stmt2 = $pdo_dw->prepare($sql_cloud);
    $stmt2->execute([':codigo' => $codigo]);
    foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ficha_cloudify[$row['codigo_insumo']] = $row;
    }

    // Se alguma das fichas estiver vazia, vermelho
    if (empty($ficha_intranet) || empty($ficha_cloudify)) {
        $status = 'vermelho';
    } else {
        $todos_codigos = array_unique(array_merge(
            array_keys($ficha_intranet),
            array_keys($ficha_cloudify)
        ));

        $diferencas = false;
        foreach ($todos_codigos as $cod) {
            $a = $ficha_intranet[$cod] ?? null;
            $b = $ficha_cloudify[$cod] ?? null;

            if ($a && $b) {
                if (
                    trim($a['nome_insumo']) !== trim($b['nome_insumo']) ||
                    trim($a['unidade']) !== trim($b['unidade']) ||
                    floatval($a['quantidade']) != floatval($b['quantidade'])
                ) {
                    $diferencas = true;
                    break;
                }
            } else {
                $diferencas = true;
                break;
            }
        }

        $status = $diferencas ? 'amarelo' : 'verde';
    }
    
    // Atualizar o status do farol na tabela ficha_tecnica
    $update_sql = "UPDATE ficha_tecnica SET farol = :farol WHERE codigo_cloudify = :codigo";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([
        ':farol' => $status,
        ':codigo' => $codigo
    ]);

}
echo json_encode(['status' => $status]);
exit;
