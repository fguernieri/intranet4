<?php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
require_once __DIR__ . '/supabase_connection.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'not_authenticated']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid_json']);
    exit;
}

// Support new single-PK `id` used by Fábrica tables. Legacy composite keys are no longer required here.
$id = intval($data['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'missing_or_invalid_id']);
    exit;
}

try {
    $supabase = new SupabaseConnection();
    // Build filters for id-based lookup
    $filters = [ 'id' => 'eq.' . $id ];

    // First, try to read existing record to determine current state
    $selectResp = $supabase->select('fcontaspagarfabrica_vistos', ['select' => 'visto', 'filters' => $filters, 'limit' => 1]);
    if ($selectResp === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'supabase_error_select']);
        exit;
    }

$desiredProvided = array_key_exists('visto', $data);
$desiredVisto = null;
if ($desiredProvided) {
    $v = $data['visto'];
    if (is_string($v)) {
        $desiredVisto = in_array(strtolower($v), ['1','true','t','yes','y','on'], true);
    } else {
        $desiredVisto = boolval($v);
    }
}

$newVisto = true; // default insertion when no row and no explicit value
    if (!empty($selectResp) && isset($selectResp[0]['visto'])) {
    $current = boolval($selectResp[0]['visto']);
    $target = $desiredProvided ? $desiredVisto : !$current; // set explicit or flip

    // Update existing row
        $updateResp = $supabase->update('fcontaspagarfabrica_vistos', ['visto' => $target], $filters);
    if ($updateResp === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'supabase_error_update']);
        exit;
    }
    $newVisto = $target;
} else {
    // No existing row: insert new with provided value or default true
        $payload = [
            'id' => $id,
            'visto' => $desiredProvided ? $desiredVisto : true
        ];
        $insertResp = $supabase->insert('fcontaspagarfabrica_vistos', $payload);
    if ($insertResp === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'supabase_error_insert']);
        exit;
    }
    $newVisto = $payload['visto'];
}

    echo json_encode(['success' => true, 'visto' => $newVisto]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
