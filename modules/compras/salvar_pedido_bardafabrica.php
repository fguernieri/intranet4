<?php
// === modules/compras/salvar_pedido_bardafabrica.php ===
// Recebe JSON único em itensJson e insere no BD.

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit;
}

$filial  = 'BAR DA FABRICA';
$usuario = $_SESSION['usuario_nome'] ?? '';

require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
$conn->begin_transaction();

try {
    $dataHora = date('Y-m-d H:i:s');

    // 1) Prepara SELECT de código
    $stmtInfo = $conn->prepare("
        SELECT CODIGO
          FROM insumos
         WHERE INSUMO = ? AND FILIAL = ?
    ");
    $stmtInfo->bind_param('ss', $insumoNome, $filial);

    // 2) Prepara INSERT em pedidos
    $stmtInsert = $conn->prepare("
        INSERT INTO pedidos (
          INSUMO, CODIGO, CATEGORIA, UNIDADE, FILIAL,
          QUANTIDADE, OBSERVACAO, USUARIO, DATA_HORA, SETOR
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtInsert->bind_param(
        'sssssdssss',
        $insumoNome,
        $insumoCodigo,
        $categoria,
        $unidade,
        $filial,
        $quantidade,
        $observacao,
        $usuario,
        $dataHora,
        $setor // <-- aqui salva o setor
    );

    // 3) Lê e decodifica o JSON
    $jsonInput = $_POST['itensJson'] ?? '';
    $itens = json_decode($jsonInput, true);

    if (!is_array($itens)) {
        http_response_code(400);
        exit('JSON inválido ou ausente');
    }
    if (empty($itens)) {
        // Nenhum item para processar
        header('Location: insumos_bardafabrica.php?status=noitems'); // Exemplo de status
        exit;
    }

    $setor = $_POST['setor'] ?? '';

    // Validação do setor
    $setoresValidos = ['COZINHA', 'BAR', 'GERENCIA'];
    if (!in_array($setor, $setoresValidos)) {
        die('Setor inválido.');
    }

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

        // busca código no catálogo
        $stmtInfo->execute();
        $stmtInfo->bind_result($insumoCodigo);
        if (! $stmtInfo->fetch()) {
            $insumoCodigo = '';
        }
        $stmtInfo->free_result();

        // insere no pedido
        $stmtInsert->execute();
    }

    $conn->commit();
    header('Location: insumos_bardafabrica.php?status=ok');
    exit;

} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log("salvar_pedido_bardafabrica.php erro: " . $e->getMessage());
    die("Erro ao salvar o pedido. Consulte o administrador.");
}