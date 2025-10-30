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

$periodo = $data['periodo'] ?? '';
if (empty($periodo) || !preg_match('/^(\d{4})\/(\d{2})$/', $periodo, $m)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'missing_or_invalid_periodo']);
    exit;
}

$ano = $m[1];
$mes = $m[2];
$data_mes = $ano . '-' . $mes . '-01';

try {
    $supabase = new SupabaseConnection();
    $page = 0;
    $pageSize = 2000;
    $processed = 0;
    $inserted = 0;
    $updated = 0;

    while (true) {
        $offset = $page * $pageSize;
        $detalhes = $supabase->select('fdespesastap_detalhes', [
            'select' => 'nr_empresa,nr_filial,nr_lanc,seq_lanc',
            'filters' => [ 'data_mes' => 'eq.' . $data_mes ],
            'limit' => $pageSize,
            'offset' => $offset
        ]);

        if ($detalhes === false || !is_array($detalhes) || count($detalhes) === 0) break;

        foreach ($detalhes as $d) {
            $ne = intval($d['nr_empresa'] ?? 0);
            $nf = intval($d['nr_filial'] ?? 0);
            $nl = intval($d['nr_lanc'] ?? 0);
            $ns = intval($d['seq_lanc'] ?? 0);

            if ($ne <= 0 || $nf <= 0 || $nl <= 0) continue;

            $filters = [
                'nr_empresa' => 'eq.' . $ne,
                'nr_filial' => 'eq.' . $nf,
                'nr_lanc' => 'eq.' . $nl,
                'seq_lanc' => 'eq.' . $ns,
            ];

            $updateResp = $supabase->update('fcontaspagartap_vistos', ['visto' => true], $filters);
            if ($updateResp === false) {
                // log and continue
                error_log('Erro ao atualizar visto (bulk): ' . json_encode($filters));
                continue;
            }

            if (empty($updateResp)) {
                // insert
                $payload = [
                    'nr_empresa' => $ne,
                    'nr_filial' => $nf,
                    'nr_lanc' => $nl,
                    'seq_lanc' => $ns,
                    'visto' => true
                ];
                $insertResp = $supabase->insert('fcontaspagartap_vistos', $payload);
                if ($insertResp === false) {
                    error_log('Erro ao inserir visto (bulk): ' . json_encode($payload));
                    continue;
                }
                $inserted++;
            } else {
                $updated++;
            }

            $processed++;
        }

        $page++;
    }

    echo json_encode(['success' => true, 'processed' => $processed, 'inserted' => $inserted, 'updated' => $updated]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
