<?php
// Endpoint simples para listar e atualizar mapeamento de meses das metas
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/supabase_connection.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$sim = $_GET['sim'] ?? ($_POST['sim'] ?? 'tap');

// Mapear simulador para tabela de metas existente
$table_map = [
    'tap' => 'fmetastap',
    'wab' => 'fmetaswab',
    'fabrica' => 'fmetasfabricafinal'
];

$table = $table_map[$sim] ?? $table_map['tap'];

// Função auxiliar para escolher tabela alternativa para fábrica
function tableExists($pdo, $t) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$t]);
    return $stmt->fetchColumn() > 0;
}

try {
    // Preferir buscar metas via Supabase (mesmo local usado pelos simuladores)
    $useSupabase = false;
    $supabase = null;
    try {
        $supabase = new SupabaseConnection();
        $useSupabase = $supabase->testConnection();
    } catch (Exception $se) {
        $useSupabase = false;
    }

    // Garantir tabela local de mapeamento `metas_meses` se $pdo disponível
    if (isset($pdo)) {
        $create = "CREATE TABLE IF NOT EXISTS metas_meses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            simulador VARCHAR(50) NOT NULL,
            meta_key VARCHAR(128) NOT NULL,
            meses TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY simulador_meta (simulador, meta_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        try { $pdo->exec($create); } catch (Exception $ecreate) { /* ignore create errors */ }
    }

    if ($action === 'fetch') {
        $periodo = $_GET['periodo'] ?? '';
        $data_meta = null;
        $filters = [];
        if (!empty($periodo) && preg_match('/^(\d{4})\/(\d{2})$/', $periodo, $m)) {
            $data_meta = $m[1] . '-' . $m[2] . '-01';
            $filters['DATA_META'] = 'eq.' . $data_meta;
        }

        $out = [];

        if ($useSupabase && $supabase) {
            // Tentar buscar na tabela do Supabase
            $selectCols = 'CATEGORIA,SUBCATEGORIA,META,PERCENTUAL,DATA_META';
            $params = ['select' => $selectCols, 'order' => 'CATEGORIA,SUBCATEGORIA'];
            if (!empty($filters)) $params['filters'] = $filters;

            $rows = $supabase->select($table, $params);
            if ($rows === false) {
                // log
                @file_put_contents(__DIR__ . '/metas_api_debug.log', date('c') . " supabase select returned false table={$table}\n", FILE_APPEND | LOCK_EX);
                throw new Exception('Erro ao consultar Supabase (ver log)');
            }

            // Normalizar e anexar meses locais
            foreach ($rows as $r) {
                $cat = isset($r['CATEGORIA']) ? $r['CATEGORIA'] : ($r['categoria'] ?? '');
                $sub = isset($r['SUBCATEGORIA']) ? $r['SUBCATEGORIA'] : ($r['subcategoria'] ?? '');
                $metaVal = isset($r['META']) ? $r['META'] : 0;
                $pct = isset($r['PERCENTUAL']) ? $r['PERCENTUAL'] : 0;
                $dataMetaRow = isset($r['DATA_META']) ? $r['DATA_META'] : ($r['data_meta'] ?? '');
                $meta_key = md5((string)$cat . '|' . (string)$sub . '|' . (string)$dataMetaRow . '|' . (string)$metaVal);

                $rowOut = [
                    'CATEGORIA' => $cat,
                    'SUBCATEGORIA' => $sub,
                    'META' => $metaVal,
                    'PERCENTUAL' => $pct,
                    'DATA_META' => $dataMetaRow,
                    'meta_key' => $meta_key,
                    'meses' => []
                ];

                if (isset($pdo)) {
                    $stmt_m = $pdo->prepare('SELECT meses FROM metas_meses WHERE simulador = ? AND meta_key = ? LIMIT 1');
                    $stmt_m->execute([$sim, $meta_key]);
                    $mm = $stmt_m->fetchColumn();
                    $rowOut['meses'] = $mm ? array_values(array_filter(array_map('trim', explode(',', $mm)))) : [];
                }

                $out[] = $rowOut;
            }

            echo json_encode(['ok' => true, 'metas' => $out]);
            exit;
        }

        // Fallback: tentar pelo banco local (antigo comportamento)
        if (!isset($pdo)) throw new Exception('Conexão ao Supabase falhou e PDO local não disponível');

        // se fábrica e tabela final não existir, tentar alternativa
        if ($sim === 'fabrica' && !tableExists($pdo, $table)) {
            if (tableExists($pdo, 'fmetasfabrica')) $table = 'fmetasfabrica';
        }

        $where = '';
        $params = [];
        if ($data_meta) {
            $where = 'WHERE DATA_META = ?';
            $params[] = $data_meta;
        }

        $sql = "SELECT COALESCE(CATEGORIA,'') AS CATEGORIA, COALESCE(SUBCATEGORIA,'') AS SUBCATEGORIA, COALESCE(META,0) AS META, COALESCE(PERCENTUAL,0) AS PERCENTUAL, COALESCE(DATA_META,'') AS DATA_META, MD5(CONCAT(COALESCE(CATEGORIA,''),'|',COALESCE(SUBCATEGORIA,''),'|',COALESCE(DATA_META,''),'|',COALESCE(META,''))) AS meta_key FROM `" . str_replace('`','',$table) . "` $where ORDER BY CATEGORIA, SUBCATEGORIA";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        $stmt_m = $pdo->prepare('SELECT meses FROM metas_meses WHERE simulador = ? AND meta_key = ? LIMIT 1');
        foreach ($rows as $r) {
            $stmt_m->execute([$sim, $r['meta_key']]);
            $mm = $stmt_m->fetchColumn();
            $r['meses'] = $mm ? array_values(array_filter(array_map('trim', explode(',', $mm)))) : [];
            $out[] = $r;
        }

        echo json_encode(['ok' => true, 'metas' => $out]);
        exit;
    }

    if ($action === 'save_months') {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $meta_key = $data['meta_key'] ?? null;
        $meses = $data['meses'] ?? [];
        if (!$meta_key) { throw new Exception('meta_key é obrigatório'); }
        if (!is_array($meses)) $meses = [$meses];
        $meses_str = implode(',', array_values(array_map('trim', $meses)));

        if (!isset($pdo)) throw new Exception('Conexão PDO local necessária para salvar mapeamento');

        $ins = $pdo->prepare('INSERT INTO metas_meses (simulador, meta_key, meses) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE meses = VALUES(meses), created_at = NOW()');
        $ins->execute([$sim, $meta_key, $meses_str]);

        echo json_encode(['ok' => true, 'meta_key' => $meta_key, 'meses' => explode(',', $meses_str)]);
        exit;
    }

    echo json_encode(['error' => 'action inválida']);
    exit;

} catch (Exception $e) {
    // Log detalhado server-side
    @file_put_contents(__DIR__ . '/metas_api_debug.log', date('c') . " ERROR: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

?>
