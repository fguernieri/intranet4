<?php
// === modules/compras/salvar_novos_insumos.php ===

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
session_start();

// 1) Só processa POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Uso apenas via POST');
}

// 2) Filial: primeiro tenta o POST, senão session
$filial  = $_POST['filial']          ?? $_SESSION['filial'] ?? null;
$usuario = $_SESSION['usuario_nome'] ?? '';
if (! $filial) {
    die('Filial não informada');
}

// 3) Conexão ao banco
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Erro de conexão: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// 4) Prepara o INSERT
$stmt = $conn->prepare("
    INSERT INTO novos_insumos (
      insumo,
      categoria,
      unidade,
      quantidade,
      observacao,
      filial,
      usuario,
      data_hora
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
if (! $stmt) {
    die('Erro ao preparar: ' . $conn->error);
}

// 5) Bind dos parâmetros
$stmt->bind_param(
    'sssdssss',
    $insumoNome,
    $categoria,
    $unidade,
    $quantidade,
    $observacao,
    $filial,
    $usuario,
    $dataHora
);

// 6) Dados do POST (garante array)
$newInsumos     = $_POST['new_insumo']     ?? [];
$newCategorias  = $_POST['new_categoria']  ?? [];
$newUnidades    = $_POST['new_unidade']    ?? [];
$newQuantities  = $_POST['new_quantidade'] ?? [];
$newObs         = $_POST['new_observacao'] ?? [];

// 7) Data de gravação
$dataHora = date('Y-m-d H:i:s');

// 8) Loop e gravação
$gravados = 0;
foreach ($newInsumos as $i => $raw) {
    $insumoNome = trim($raw);
    $quantidade = floatval(str_replace(',', '.', $newQuantities[$i] ?? '0'));

    // valida
    if ($insumoNome === '' || $quantidade <= 0) {
        continue;
    }

    $categoria  = substr(trim($newCategorias[$i] ?? ''), 0, 50);
    $unidade    = substr(trim($newUnidades[$i]   ?? ''), 0, 20);
    $observacao = substr(trim($newObs[$i]        ?? ''), 0, 200);

    if (! $stmt->execute()) {
        error_log("Erro ao inserir novo_insumo: " . $stmt->error);
        continue;
    }
    $gravados++;
}

// 9) Resposta
$stmt->close();
$conn->close();

// Retorna quantos gravou (você pode mudar para JSON ou simplesmente deixar em branco)
echo "Novos insumos gravados: $gravados";
