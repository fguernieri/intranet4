<?php
// === modules/compras/salvar_pedido_7tragos.php ===
// Salva o pedido só para 7TRAGOS (comportamento igual ao salvar_pedido.php original).

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: insumos_cross.php');
    exit;
}

// fixa a filial
$filial  = 'CROSS';
$usuario = $_SESSION['usuario_nome'] ?? '';

// conexão + transação
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
$conn->begin_transaction();

try {
    // novo número
    $row = $conn->query("SELECT COALESCE(MAX(numero_pedido),0)+1 AS novo FROM pedidos")->fetch_assoc();
    $numeroPedido = (int)$row['novo'];
    $dataHora = date('Y-m-d H:i:s');

    // prepara statements
    $stmtInfo = $conn->prepare("
        SELECT INSUMO_CLOUDFY, CODIGO
          FROM insumos
         WHERE INSUMO = ? AND FILIAL = ?
    ");
    $stmtInfo->bind_param('ss', $insumoNome, $filial);

    $stmtInsert = $conn->prepare("
        INSERT INTO pedidos (
          numero_pedido, INSUMO_CLOUDFY, INSUMO, CODIGO,
          CATEGORIA, UNIDADE, FILIAL, QUANTIDADE,
          OBSERVACAO, USUARIO, DATA_HORA
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtInsert->bind_param(
        'issssssdsss',
        $numeroPedido,
        $insumoCloudfy,
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

    // 3) Lê e decodifica o JSON
    $jsonInput = $_POST['itensJson'] ?? '';
    $itens = json_decode($jsonInput, true);

    if (!is_array($itens)) {
        http_response_code(400);
        error_log("salvar_pedido_cross.php erro: JSON inválido ou ausente. Recebido: " . $jsonInput);
        exit('JSON inválido ou ausente');
    }
    if (empty($itens)) {
        // Nenhum item para processar, pode redirecionar ou retornar erro.
        // Se o JS já valida isso, talvez não seja estritamente necessário, mas é uma boa defesa.
        header('Location: insumos_cross.php?status=noitems'); // Exemplo de status
        exit;
    }

    // loop existentes
    foreach ($itens as $item) {
        $insumoNomeRaw = $item['insumo'] ?? '';
        $quantidadeRaw = $item['quantidade'] ?? '0';

        $insumoNome  = trim($insumoNomeRaw);
        $quantidade  = floatval($quantidadeRaw); // Convertido para float para validação e bind 'd'
        
        if ($insumoNome === '' || $quantidade <= 0) {
            continue; // Pula itens inválidos
        }

        $categoria   = substr(trim($item['categoria'] ?? ''), 0, 50);
        $unidade     = substr(trim($item['unidade']   ?? ''), 0, 20);
        $observacao  = substr(trim($item['observacao']?? ''), 0, 200);

        $stmtInfo->execute();
        $stmtInfo->bind_result($insumoCloudfy, $insumoCodigo);
        if (! $stmtInfo->fetch()) {
            $insumoCloudfy = '';
            $insumoCodigo  = '';
        }
        $stmtInfo->free_result();

        $stmtInsert->execute();
    }

    $conn->commit();
    header('Location: insumos_cross.php?status=ok&pedido='.$numeroPedido);
    exit;

} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log("salvar_pedido_cross.php erro: ".$e->getMessage());
    die("Erro ao salvar o pedido. Consulte o administrador.");
}