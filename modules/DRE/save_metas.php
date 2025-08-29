<?php
// Endpoint para gravar metas na tabela fmetastap do Supabase
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
// Autenticação mínima: reutiliza sessão existente
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['code' => 401, 'message' => 'Unauthorized']);
    exit;
}
if (!isset($_SESSION['usuario_perfil']) || !in_array($_SESSION['usuario_perfil'], ['admin', 'supervisor'])) {
    http_response_code(403);
    echo json_encode(['code' => 403, 'message' => 'Acesso restrito']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || empty($data)) {
    http_response_code(400);
    echo json_encode(['code' => 400, 'message' => 'Payload inválido']);
    exit;
}

// Supabase details (kept inline like api.php to reuse existing behavior)
$supabaseUrl = 'https://gybhszcefuxsdhpvxbnk.supabase.co/rest/v1/fmetastap';
$apiKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imd5YmhzemNlZnV4c2RocHZ4Ym5rIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTMwMTYwOTYsImV4cCI6MjA2ODU5MjA5Nn0.i3sOjUvpsimEvtO0uDGFNK92IZjcy1VIva_KEBdlZI8';

$toInsert = [];
$now = date('Y-m-d H:i:s');
foreach ($data as $row) {
    // Espera: CATEGORIA, SUBCATEGORIA, META, PERCENTUAL (opcional)
    $categoria = isset($row['CATEGORIA']) ? trim($row['CATEGORIA']) : '';
    $sub = isset($row['SUBCATEGORIA']) ? trim($row['SUBCATEGORIA']) : '';
    $meta = isset($row['META']) ? floatval($row['META']) : 0;
    $pct = isset($row['PERCENTUAL']) && $row['PERCENTUAL'] !== '' ? floatval($row['PERCENTUAL']) : null;
    $toInsert[] = [
        'CATEGORIA' => $categoria,
        'SUBCATEGORIA' => $sub,
        'META' => $meta,
        'PERCENTUAL' => $pct,
        'DATA' => $now,
    ];
}

$payload = json_encode($toInsert);
$ch = curl_init($supabaseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . $apiKey,
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json',
    'Prefer: return=minimal'
]);
$res = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($res === false || ($http !== 201 && $http !== 200)) {
    $err = curl_error($ch);
    curl_close($ch);
    http_response_code(500);
    echo json_encode(['code' => 500, 'message' => 'Erro ao inserir no Supabase', 'detail' => $err, 'http' => $http]);
    exit;
}
curl_close($ch);

echo json_encode(['code' => 200, 'message' => 'ok', 'inserted' => count($toInsert)]);

?>
