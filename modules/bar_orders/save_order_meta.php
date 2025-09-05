<?php
// Ensure responses are JSON and hide PHP HTML errors; return JSON for fatal errors where possible
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// convert PHP errors/fatal to JSON where possible
set_exception_handler(function($e){
    http_response_code(500);
    echo json_encode(['error' => 'Exception', 'message' => (string)$e->getMessage()]);
    exit;
});
register_shutdown_function(function(){
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // fatal error — try to return JSON
        http_response_code(500);
        // avoid sending HTML from PHP engine
        echo json_encode(['error' => 'Fatal error', 'message' => $err['message']]);
        exit;
    }
});

// load config
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    require_once __DIR__ . '/config.example.php';
}

// read JSON payload
$body = file_get_contents('php://input');
if (!$body) {
    header('Content-Type: application/json', true, 400);
    echo json_encode(['error' => 'Empty body']);
    exit;
}

$data = json_decode($body, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    header('Content-Type: application/json', true, 400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$numero_pedido = $data['numero_pedido'] ?? '';
$items = $data['items'] ?? [];
$usuario = $_SESSION['usuario_nome'] ?? ($data['usuario'] ?? '');

if (!$numero_pedido || !is_array($items) || empty($items)) {
    header('Content-Type: application/json', true, 400);
    echo json_encode(['error' => 'Missing numero_pedido or items']);
    exit;
}

// Build batch payload for Supabase upsert
$batch = [];
foreach ($items as $it) {
    $produto = $it['produto'] ?? '';
    if ($produto === '') continue;
    $row = [
        'numero_pedido' => $numero_pedido,
        'produto' => $produto,
        'fornecedor' => $it['fornecedor'] ?? null,
        'status' => $it['status'] ?? null,
        'obs_comprador' => $it['obs_comprador'] ?? null,
        'extra' => $it['extra'] ?? new stdClass(),
        'updated_by' => $usuario,
        'updated_at' => date('c')
    ];
    $batch[] = $row;
}

if (empty($batch)) {
    header('Content-Type: application/json', true, 400);
    echo json_encode(['error' => 'No valid items']);
    exit;
}

if (!defined('SUPABASE_URL') || !defined('SUPABASE_KEY')) {
    header('Content-Type: application/json', true, 500);
    echo json_encode(['error' => 'Supabase not configured']);
    exit;
}

$base = rtrim(SUPABASE_URL, '/');
$url = $base . '/rest/v1/order_item_meta';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($batch));
$headers = [
    'apikey: ' . SUPABASE_KEY,
    'Authorization: Bearer ' . SUPABASE_KEY,
    'Content-Type: application/json',
    // In Supabase, Prefer: resolution=merge-duplicates enables upsert behaviour when unique constraint exists
    'Prefer: resolution=merge-duplicates'
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$resp = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    header('Content-Type: application/json', true, 500);
    echo json_encode(['error' => 'Curl error', 'detail' => $err]);
    exit;
}

if ($code >= 200 && $code < 300) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'rows' => json_decode($resp, true)]);
    exit;
} else {
    header('Content-Type: application/json', true, $code);
    echo json_encode(['error' => 'Supabase error', 'http_code' => $code, 'response' => json_decode($resp, true)]);
    exit;
}

