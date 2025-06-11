<?php
// === modules/compras/salvar_pedido_wearebastards.php ===
// Salva o pedido só para WE ARE BASTSRDS (removido INSUMO_CLOUDFY e número manual).

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: insumos_wearebastards.php');
    exit;
}

// fixa a filial
$filial  = 'WE ARE BASTARDS'; // Ajustado para corresponder ao nome usado em insumos_wearebastards.php
$usuario = $_SESSION['usuario_nome'] ?? '';

// conexão + transação
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
$conn->begin_transaction();

try {
    $dataHora = date('Y-m-d H:i:s');

    // prepara statements
    $stmtInfo = $conn->prepare("
        SELECT CODIGO
          FROM insumos
         WHERE INSUMO = ? AND FILIAL = ?
    ");
    $stmtInfo->bind_param('ss', $insumoNome, $filial);

    $stmtInsert = $conn->prepare("
        INSERT INTO pedidos (
          INSUMO, CODIGO, CATEGORIA, UNIDADE, FILIAL,
          QUANTIDADE, OBSERVACAO, USUARIO, DATA_HORA
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtInsert->bind_param(
        'sssssdsss',
        $insumoNome,
        $insumoCodigo,
        $categoria,
        $unidade,
        $filial,
        $quantidade,
        $observacao,
        $usuario,
        $dataHora
    );

    // Lê e decodifica o JSON de itens
    $jsonInput = $_POST['itensJson'] ?? '';
    $itens = json_decode($jsonInput, true);

    if (!is_array($itens)) {
        http_response_code(400);
        error_log("salvar_pedido_wearebastards.php erro: JSON inválido ou ausente. Recebido: " . $jsonInput);
        exit('JSON inválido ou ausente');
    }
    if (empty($itens)) {
        // Nenhum item para processar
        header('Location: insumos_wearebastards.php?status=noitems'); // Exemplo de status
        exit;
    }

    // loop e inserção
    foreach ($itens as $item) {
        $insumoNomeRaw = $item['insumo'] ?? '';
        $quantidadeRaw = $item['quantidade'] ?? '0';

        $insumoNome  = trim($insumoNomeRaw);
        $quantidade  = floatval($quantidadeRaw);

        if ($insumoNome === '' || $quantidade <= 0) {
            continue;
        }

        $categoria   = substr(trim($item['categoria'] ?? ''), 0, 50);
        $unidade     = substr(trim($item['unidade']   ?? ''), 0, 20);
        $observacao  = substr(trim($item['observacao']?? ''), 0, 200);

        // busca o código no insumos
        $stmtInfo->execute();
        $stmtInfo->bind_result($insumoCodigo);
        if (! $stmtInfo->fetch()) {
            $insumoCodigo = '';
        }
        $stmtInfo->free_result();

        // insere o item de pedido
        $stmtInsert->execute();
    }

    $conn->commit();
    header('Location: insumos_wearebastards.php?status=ok');
    exit;

} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log("salvar_pedido_wearebastards.php erro: ".$e->getMessage());
    die("Erro ao salvar o pedido. Consulte o administrador.");
}