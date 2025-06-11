<?php
// === modules/compras/salvar_pedido.php ===

// Autenticação
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
session_start();

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: insumos.php');
    exit;
}

// Filial e usuário vêm da sessão
$filial  = $_SESSION['filial']       ?? '';
$usuario = $_SESSION['usuario_nome'] ?? '';
if (!$filial) {
    header('Location: select_filial.php');
    exit;
}

// Conecta ao banco
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Prepara query para buscar INSUMO_CLOUDFY e CODIGO na tabela insumos
$stmt_info = $conn->prepare("
    SELECT INSUMO_CLOUDFY, CODIGO
      FROM insumos
     WHERE INSUMO = ? AND FILIAL = ?
");
$stmt_info->bind_param('ss', $insumo_nome, $filial);

// Prepara inserção na tabela pedidos
$stmt_insert = $conn->prepare("
    INSERT INTO pedidos
      (INSUMO_CLOUDFY, INSUMO, CODIGO, CATEGORIA,
       UNIDADE, FILIAL, QUANTIDADE, OBSERVACAO, USUARIO)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt_insert->bind_param(
    'ssssssdss',
    $insumo_cloudfy,
    $insumo_nome,
    $insumo_codigo,
    $categoria,
    $unidade,
    $filial,
    $quantidade,
    $observacao,
    $usuario
);

// Captura arrays do POST
$insumos     = $_POST['insumo']     ?? [];
$categorias  = $_POST['categoria']  ?? [];
$unidades    = $_POST['unidade']    ?? [];
$quantidades = $_POST['quantidade'] ?? [];
$obs         = $_POST['observacao'] ?? [];

// Loop para cada item
for ($i = 0; $i < count($insumos); $i++) {
    $insumo_nome = $insumos[$i];
    $categoria   = substr($categorias[$i]  ?? '', 0, 50);
    $unidade     = substr($unidades[$i]    ?? '', 0, 20);
    $quantidade  = floatval(str_replace(',', '.', $quantidades[$i] ?? '0'));
    $observacao  = substr($obs[$i]         ?? '', 0, 200);

    // Não insere se quantidade inválida
    if ($quantidade <= 0) {
        continue;
    }

    // Busca INSUMO_CLOUDFY e CODIGO
    $stmt_info->execute();
    $stmt_info->bind_result($insumo_cloudfy, $insumo_codigo);
    if (!$stmt_info->fetch()) {
        // Se não encontrou, pula este insumo
        continue;
    }
    $stmt_info->free_result();

    // Executa inserção
    $stmt_insert->execute();
}

// Fecha statements e conexão
$stmt_info->close();
$stmt_insert->close();
$conn->close();

// Redireciona de volta com status
header('Location: insumos.php?status=ok');
exit;