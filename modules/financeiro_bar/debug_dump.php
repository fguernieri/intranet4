<?php
// Página temporária de diagnóstico — imprime logs e info redigida
// Acessar em navegador: /modules/financeiro_bar/debug_dump.php

header('Content-Type: text/plain; charset=utf-8');
// minimizar riscos: permitir acesso apenas em ambientes controlados

echo "--- DEBUG DUMP (redacted) ---\n";
echo "Gerado em: " . date('c') . "\n\n";

// Server info
echo "--- SERVER INFO ---\n";
echo 'PHP_VERSION: ' . phpversion() . "\n";
if (function_exists('curl_version')) {
    $cv = curl_version();
    echo 'CURL_VERSION: ' . ($cv['version'] ?? '') . "\n";
}
echo 'REQUEST_URI: ' . ($_SERVER['REQUEST_URI'] ?? '') . "\n";
echo 'REMOTE_ADDR: ' . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n\n";

// Supabase config summary (redacted)
echo "--- SUPABASE CONFIG (summary) ---\n";
$cfgPath = __DIR__ . '/config/supabase_config.php';
if (file_exists($cfgPath)) {
    $cfg = @include $cfgPath;
    if (is_array($cfg)) {
        $summary = [
            'url' => $cfg['url'] ?? null,
            'use_service_key' => $cfg['use_service_key'] ?? null,
            'anon_key_present' => !empty($cfg['anon_key']),
            'service_key_present' => !empty($cfg['service_key']),
        ];
        echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
    } else {
        echo "config file included but returned non-array\n\n";
    }
} else {
    echo "supabase_config.php not found at $cfgPath\n\n";
}

// Helper para redigir tokens
function redact_text($text) {
    if ($text === null) return '';
    // Redact JWT-like tokens starting with eyJ
    $text = preg_replace('/eyJ[A-Za-z0-9_\-\.]{10,}/', '***REDACTED_TOKEN***', $text);
    // Redact long base64-like strings
    $text = preg_replace('/[A-Za-z0-9_\-]{40,}/', '***REDACTED***', $text);
    return $text;
}

// Supabase wrapper debug log
$debugLog = __DIR__ . '/supabase_debug.log';
echo "--- SUPABASE DEBUG LOG (tail 200 lines) ---\n";
if (file_exists($debugLog) && is_readable($debugLog)) {
    $lines = @file($debugLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        $tail = array_slice($lines, max(0, count($lines) - 200));
        $out = implode("\n", $tail);
        echo redact_text($out) . "\n\n";
    } else {
        echo "Cannot read $debugLog\n\n";
    }
} else {
    echo "$debugLog not found or not readable\n\n";
}

// PHP Error Log (from php.ini)
$phpErr = @ini_get('error_log');
echo "--- PHP ERROR LOG (tail 200 lines) ---\n";
if ($phpErr && file_exists($phpErr) && is_readable($phpErr)) {
    $lines = @file($phpErr, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        $tail = array_slice($lines, max(0, count($lines) - 200));
        echo redact_text(implode("\n", $tail)) . "\n\n";
    } else {
        echo "Cannot read $phpErr\n\n";
    }
} else {
    echo "php error_log not available (ini_get('error_log') returned: " . var_export($phpErr, true) . ")\n\n";
}

// Recent request body (if present) — helpful when invoked immediately after a failing request
echo "--- LAST REQUEST BODY (php://input) ---\n";
$body = @file_get_contents('php://input');
if ($body) {
    echo redact_text($body) . "\n\n";
} else {
    echo "(no php://input available for this request)\n\n";
}

echo "--- END DEBUG DUMP ---\n";

// Reminder: Remove this file after debugging to avoid exposing information.
?>