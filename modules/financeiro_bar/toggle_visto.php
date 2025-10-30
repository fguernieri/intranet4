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

$ne = intval($data['nr_empresa'] ?? 0);
$nf = intval($data['nr_filial'] ?? 0);
$nl = intval($data['nr_lanc'] ?? 0);
$ns = intval($data['seq_lanc'] ?? 0);

if ($ne <= 0 || $nf <= 0 || $nl <= 0 || $ns < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'missing_or_invalid_keys']);
    exit;
}

try {
    $supabase = new SupabaseConnection();
    // Tentar atualizar primeiro
    $filters = [
        'nr_empresa' => 'eq.' . $ne,
        'nr_filial' => 'eq.' . $nf,
        'nr_lanc' => 'eq.' . $nl,
        'seq_lanc' => 'eq.' . $ns,
    ];

    $updateResp = $supabase->update('fcontaspagartap_vistos', ['visto' => true], $filters);

    if ($updateResp === false) {
        // erro de requisição
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'supabase_error_update']);
        exit;
    }

    // Se updateResp é vazio (nenhuma linha afetada) tentamos inserir
    if (empty($updateResp)) {
        $payload = [
            'nr_empresa' => $ne,
            'nr_filial' => $nf,
            'nr_lanc' => $nl,
            'seq_lanc' => $ns,
            'visto' => true
        ];
        $insertResp = $supabase->insert('fcontaspagartap_vistos', $payload);
        if ($insertResp === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'supabase_error_insert']);
            exit;
        }
    }

    echo json_encode(['success' => true]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
