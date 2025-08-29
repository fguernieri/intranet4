<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
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

$supabaseListUrl = 'https://gybhszcefuxsdhpvxbnk.supabase.co/rest/v1/fsimulacoestap?select=NOME,DATA&order=DATA.desc&limit=200';
$apiKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imd5YmhzemNlZnV4c2RocHZ4Ym5rIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTMwMTYwOTYsImV4cCI6MjA2ODU5MjA5Nn0.i3sOjUvpsimEvtO0uDGFNK92IZjcy1VIva_KEBdlZI8';

$nome = isset($_GET['nome']) ? trim($_GET['nome']) : null;
try {
    if ($nome === null) {
        // Return list of distinct names
        $ch = curl_init($supabaseListUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $apiKey,
            'Authorization: Bearer ' . $apiKey,
        ]);
        $res = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($res === false || $http !== 200) {
            http_response_code(500);
            echo json_encode(['code' => 500, 'message' => 'Erro ao consultar Supabase', 'http' => $http]);
            exit;
        }
        $rows = json_decode($res, true);
        // Build distinct list of names with latest date
        $names = [];
        foreach ($rows as $r) {
            $n = $r['NOME'] ?? '';
            if ($n === '') continue;
            if (!isset($names[$n]) || ($r['DATA'] ?? '') > $names[$n]) $names[$n] = $r['DATA'] ?? '';
        }
        $out = [];
        foreach ($names as $n => $d) $out[] = ['nome' => $n, 'data' => $d];
        echo json_encode(['code' => 200, 'simulacoes' => $out]);
        exit;
    } else {
        // Return rows for the requested name
        $enc = rawurlencode($nome);
    $url = 'https://gybhszcefuxsdhpvxbnk.supabase.co/rest/v1/fsimulacoestap?select=NOME,CATEGORIA,SUBCATEGORIA,META,PERCENTUAL&NOME=eq.' . $enc;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $apiKey,
            'Authorization: Bearer ' . $apiKey,
        ]);
        $res = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($res === false || $http !== 200) {
            http_response_code(500);
            echo json_encode(['code' => 500, 'message' => 'Erro ao consultar Supabase', 'http' => $http]);
            exit;
        }
        $rows = json_decode($res, true);
        echo json_encode(['code' => 200, 'rows' => $rows]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['code' => 500, 'message' => $e->getMessage()]);
    exit;
}

?>
