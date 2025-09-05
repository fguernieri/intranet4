<?php
// server-side proxy to fetch order_item_meta rows for given pedido list
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

// load module config if present (defines SUPABASE_URL / SUPABASE_KEY etc)
if (file_exists(__DIR__ . '/config.php')) {
  require_once __DIR__ . '/config.php';
} else if (file_exists(__DIR__ . '/config.example.php')) {
  require_once __DIR__ . '/config.example.php';
}

header('Content-Type: application/json');

if (!defined('SUPABASE_URL') || !defined('SUPABASE_KEY')) {
  http_response_code(500);
  echo json_encode(['error' => 'Server configuration missing']);
  exit;
}

$b = rtrim(SUPABASE_URL, '/');

$q = $_GET['pedidos'] ?? '';
if (!$q) {
  echo json_encode(['items'=>[]]);
  exit;
}

// sanitize: expect comma separated list
$list = array_filter(array_map('trim', explode(',', $q)));
if (!count($list)) { echo json_encode(['items'=>[]]); exit; }

$encoded = array_map(function($v){ return rawurlencode($v); }, $list);
$in = implode(',', $encoded);

$url = "$b/rest/v1/order_item_meta?numero_pedido=in.($in)&select=*";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'apikey: ' . SUPABASE_KEY,
  'Authorization: Bearer ' . SUPABASE_KEY,
  'Content-Type: application/json'
]);
$resp = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err || $code < 200 || $code >= 300) {
  http_response_code(502);
  echo json_encode(['error' => 'Failed to fetch metas', 'detail'=>$err ?: $resp]);
  exit;
}

$rows = json_decode($resp, true) ?: [];
echo json_encode(['items' => $rows]);

