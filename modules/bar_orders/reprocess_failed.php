<?php
// Simple reprocessor for failed batch files in modules/bar_orders/failed/
// Run from CLI or browser (careful with permissions). It will attempt to resend each failed batch
// and move successful ones to 'failed/processed/' or keep them if still failing.

if (php_sapi_name() !== 'cli') {
    // basic auth guard could be added here
}

require_once __DIR__ . '/config.example.php';
if (file_exists(__DIR__ . '/config.php')) require_once __DIR__ . '/config.php';

$failedDir = __DIR__ . '/failed';
$processedDir = $failedDir . '/processed';
if (!is_dir($failedDir)) mkdir($failedDir, 0755, true);
if (!is_dir($processedDir)) mkdir($processedDir, 0755, true);

$files = glob($failedDir . '/failed_batch_*.json');
if (!$files) {
    echo "No failed batches found.\n";
    exit;
}

foreach ($files as $file) {
    $content = file_get_contents($file);
    $meta = json_decode($content, true);
    if (!$meta || !isset($meta['batch'])) {
        echo "Skipping invalid file: $file\n";
        continue;
    }

    $batch = $meta['batch'];
    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . SUPABASE_ORDERS_TABLE;
    $headers = [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];

    // attempt resend with retries
    $maxRetries = 3;
    $attempt = 0;
    $success = false;
    $jsonBatch = json_encode($batch);
    while ($attempt < $maxRetries && !$success) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBatch);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            $attempt++;
            sleep(pow(2, $attempt - 1));
            continue;
        }

        if ($code >= 200 && $code < 300) {
            $success = true;
            break;
        } else {
            $attempt++;
            sleep(pow(2, $attempt - 1));
        }
    }

    if ($success) {
        $dst = $processedDir . '/' . basename($file);
        rename($file, $dst);
        echo "Reprocessed and moved: $file -> $dst\n";
    } else {
        // append attempt info
        $meta['last_attempt_at'] = date('c');
        $meta['last_attempt_code'] = $code ?? null;
        $meta['last_attempt_err'] = $err ?? null;
        file_put_contents($file, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "Still failing: $file (attempts=$attempt)\n";
    }
}

echo "Done.\n";
