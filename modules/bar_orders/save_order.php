<?php
// Endpoint: receives POST from order.php and writes lines to Supabase orders table
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id'])) {
    header('Content-Type: application/json', true, 401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// load config
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    require_once __DIR__ . '/config.example.php';
}

if (!defined('SUPABASE_URL') || !defined('SUPABASE_KEY')) {
    die('Supabase not configured. Copy config.example.php to config.php and fill SUPABASE_URL and SUPABASE_KEY');
}

// Collect posted items
$filial = $_POST['filial'] ?? '';
$usuario = $_POST['usuario'] ?? ($_SESSION['usuario_nome'] ?? '');
// setor informado pelo modal
$setor = $_POST['setor'] ?? '';

// support packed JSON payload to avoid max_input_vars issues when orders are very large
$payload = [];
$items_json = $_POST['items_json'] ?? '';
if ($items_json) {
    $decoded = json_decode($items_json, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        foreach ($decoded as $row) {
            $q = isset($row['qtde']) ? (float)$row['qtde'] : 0;
            if ($q <= 0) continue;
            $payload[] = [
                'data' => date('Y-m-d H:i:s'),
                'produto' => $row['produto'] ?? '',
                'categoria' => $row['categoria'] ?? '',
                'und' => $row['und'] ?? '',
                'qtde' => $q,
                'observacao' => $row['observacao'] ?? '',
                'numero_pedido' => '', // placeholder, set later
                'filial' => $filial,
                'usuario' => $usuario,
                'setor' => $setor
            ];
        }
    }
} else {
    $produtos = $_POST['produto_codigo'] ?? [];
    // old code path below will populate $payload
}

// generate a simple, sortable order number using the current timestamp (YYYYMMDDHHMMSS)
$numero_pedido = date('YmdHis');

// ensure payload items built from items_json receive the order number
if (!empty($payload)) {
    foreach ($payload as &$p) {
        if (empty($p['numero_pedido'])) $p['numero_pedido'] = $numero_pedido;
    }
    unset($p);
}

// only run the legacy form-field parsing if items_json was not used
if (empty($items_json)) {
    foreach ($produtos as $cod => $v) {
        $q = isset($_POST['quantidade'][$cod]) ? (float)$_POST['quantidade'][$cod] : 0;
        $nome = $_POST['produto_nome'][$cod] ?? '';
        $uni  = $_POST['produto_unidade'][$cod] ?? '';
        $obs  = $_POST['observacao'][$cod] ?? '';
        if ($q <= 0) continue;

        $categoria = $_POST['produto_categoria'][$cod] ?? '';
        $payload[] = [
            'data' => date('Y-m-d H:i:s'),
            'produto' => $nome,
            'categoria' => $categoria,
            'und' => $uni,
            'qtde' => $q,
            'observacao' => $obs,
            'numero_pedido' => $numero_pedido,
            'filial' => $filial,
            'usuario' => $usuario,
            'setor' => $setor
        ];
    }

    // process newly added items (from the 'new_' inputs)
    $new_insumos = $_POST['new_insumo'] ?? [];
    $new_categorias = $_POST['new_categoria'] ?? [];
    $new_unidades = $_POST['new_unidade'] ?? [];
    $new_qtdes = $_POST['new_qtde'] ?? [];
    $new_obs = $_POST['new_obs'] ?? [];

        for ($i = 0; $i < count($new_insumos); $i++) {
        $nome = trim($new_insumos[$i] ?? '');
        $cat = trim($new_categorias[$i] ?? '');
        $uni = trim($new_unidades[$i] ?? '');
        $q = isset($new_qtdes[$i]) ? (float)$new_qtdes[$i] : 0;
        $obs = trim($new_obs[$i] ?? '');
        if ($nome === '' || $cat === '' || $uni === '' || $q <= 0) {
            // skip invalid new rows
            continue;
        }
        $payload[] = [
            'data' => date('Y-m-d H:i:s'),
            'produto' => $nome,
            'categoria' => $cat,
            'und' => $uni,
            'qtde' => $q,
            'observacao' => $obs,
            'numero_pedido' => $numero_pedido,
            'filial' => $filial,
            'usuario' => $usuario,
            'setor' => $setor
        ];
    }
}

// process newly added items (from the 'new_' inputs)
$new_insumos = $_POST['new_insumo'] ?? [];
$new_categorias = $_POST['new_categoria'] ?? [];
$new_unidades = $_POST['new_unidade'] ?? [];
$new_qtdes = $_POST['new_qtde'] ?? [];
$new_obs = $_POST['new_obs'] ?? [];

// (removed duplicate new-items processing; new items are handled either via items_json or in the legacy branch above)

if (empty($payload)) {
    header('Location: order.php?filial=' . urlencode($filial) . '&status=noitems');
    exit;
}

// batching + retries + persistent failed batches
$url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . SUPABASE_ORDERS_TABLE;
$batchSize = defined('SUPABASE_BATCH_SIZE') ? SUPABASE_BATCH_SIZE : 200;
$maxRetries = 3;
$failedDir = __DIR__ . '/failed';
if (!is_dir($failedDir)) mkdir($failedDir, 0755, true);

$total = count($payload);
$batches = array_chunk($payload, $batchSize);
$anyFailure = false;

foreach ($batches as $idx => $batch) {
    $attempt = 0;
    $success = false;
    $jsonBatch = json_encode($batch);
    while ($attempt < $maxRetries && !$success) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBatch);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            $attempt++;
            // exponential backoff (ms -> seconds)
            $sleep = pow(2, $attempt - 1) * 0.5; // 0.5s, 1s, 2s
            usleep((int)($sleep * 1000000));
            continue;
        }

        if ($code >= 200 && $code < 300) {
            $success = true;
            break;
        } else {
            $attempt++;
            $sleep = pow(2, $attempt - 1) * 0.5;
            usleep((int)($sleep * 1000000));
        }
    }

    if (!$success) {
        $anyFailure = true;
        // persist failed batch to file for later reprocessing
        $fname = sprintf('%s/failed_batch_%s_%03d.json', $failedDir, date('YmdHis'), $idx+1);
        $meta = [
            'created_at' => date('c'),
            'filial' => $filial,
            'usuario' => $usuario,
            'numero_pedido' => $numero_pedido,
            'attempts' => $attempt,
            'http_code' => $code ?? null,
            'curl_error' => $err ?? null,
            'response' => $resp ?? null,
            'batch' => $batch
        ];
        file_put_contents($fname, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

if ($anyFailure) {
    header('Location: order.php?filial=' . urlencode($filial) . '&status=partial_error&pedido=' . $numero_pedido);
    exit;
} else {
    // on success redirect to a receipt page that shows the created order
    header('Location: receipt.php?pedido=' . urlencode($numero_pedido));
    exit;
}
