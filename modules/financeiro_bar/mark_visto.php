<?php
// Endpoint para marcar / desmarcar o campo `visto` na tabela fcontaspagartap_vistos
// Regras de segurança básicas:
// - Apenas método POST
// - Usuário autenticado na sessão
// - Parâmetros esperados: nr_empresa, nr_filial, nr_lanc, seq_lanc
// - action: 'set' (usa parametro 'visto' => 1|0) ou 'toggle' (inverte o valor; se inexistente, cria com visto=1)

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
require_once __DIR__ . '/supabase_connection.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id'])) {
	http_response_code(403);
	echo json_encode(['success' => false, 'error' => 'Usuário não autenticado']);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['success' => false, 'error' => 'Método inválido, use POST']);
	exit;
}

$raw = file_get_contents('php://input');
$data = $_POST;
// Se veio JSON no body, preferir o JSON
if ($raw) {
	$maybe = json_decode($raw, true);
	if (is_array($maybe)) $data = array_merge($data, $maybe);
}

$required = ['nr_empresa','nr_filial','nr_lanc','seq_lanc'];
foreach ($required as $k) {
	if (!isset($data[$k]) || $data[$k] === '') {
		http_response_code(400);
		echo json_encode(['success' => false, 'error' => "Parâmetro faltando: $k"]);
		exit;
	}
}

$nr_empresa = intval($data['nr_empresa']);
$nr_filial = intval($data['nr_filial']);
$nr_lanc = intval($data['nr_lanc']);
$seq_lanc = intval($data['seq_lanc']);

$action = isset($data['action']) ? strtolower(trim($data['action'])) : null;
$supabase = new SupabaseConnection();

// Helper: montar filtros no formato esperado pelo SupabaseConnection
function key_filters($ne, $nf, $nl, $ns) {
	return [
		'nr_empresa' => 'eq.' . $ne,
		'nr_filial' => 'eq.' . $nf,
		'nr_lanc' => 'eq.' . $nl,
		'seq_lanc' => 'eq.' . $ns,
	];
}

// Ler registro atual (se existir)
try {
	$filters = key_filters($nr_empresa, $nr_filial, $nr_lanc, $seq_lanc);
	$existing = $supabase->select('fcontaspagartap_vistos', ['select' => '*', 'filters' => $filters, 'limit' => 1]);
} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(['success' => false, 'error' => 'Erro ao acessar banco', 'detail' => $e->getMessage()]);
	exit;
}

$cur = null;
if ($existing && is_array($existing) && count($existing) > 0) {
	$cur = $existing[0];
}

// Determinar novo valor
if ($action === 'set') {
	if (!isset($data['visto'])) {
		http_response_code(400);
		echo json_encode(['success' => false, 'error' => "Para action=set é necessário o parâmetro 'visto' (1 ou 0)"]);
		exit;
	}
	$v = $data['visto'];
	if (is_string($v)) $v = in_array(strtolower($v), ['1','true','t','yes'], true) ? 1 : 0;
	$new_val = ($v) ? 1 : 0;
} else {
	// toggle por padrão
	if ($cur === null) {
		$new_val = 1; // criar como visto
	} else {
		$curval = $cur['visto'] ?? 0;
		if (is_string($curval)) $curval = in_array(strtolower($curval), ['1','true','t','yes'], true) ? 1 : 0;
		$new_val = ($curval) ? 0 : 1;
	}
}

// Persistir: tentar UPDATE; se não existir, INSERT
try {
	$filters = key_filters($nr_empresa, $nr_filial, $nr_lanc, $seq_lanc);
	$update_res = false;
	if ($cur !== null) {
		$update_res = $supabase->update('fcontaspagartap_vistos', ['visto' => $new_val], $filters);
		if ($update_res === false) {
			// falha no update
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => 'Falha ao atualizar registro de visto']);
			exit;
		}
	} else {
		// Inserir novo
		$row = [
			'nr_empresa' => $nr_empresa,
			'nr_filial' => $nr_filial,
			'nr_lanc' => $nr_lanc,
			'seq_lanc' => $seq_lanc,
			'visto' => $new_val
		];
		$insert_res = $supabase->insert('fcontaspagartap_vistos', $row);
		if ($insert_res === false) {
			http_response_code(500);
			echo json_encode(['success' => false, 'error' => 'Falha ao inserir registro de visto']);
			exit;
		}
	}
} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(['success' => false, 'error' => 'Erro ao persistir visto', 'detail' => $e->getMessage()]);
	exit;
}

// Retornar estado atual
echo json_encode([
	'success' => true,
	'nr_empresa' => $nr_empresa,
	'nr_filial' => $nr_filial,
	'nr_lanc' => $nr_lanc,
	'seq_lanc' => $seq_lanc,
	'visto' => ($new_val ? true : false),
	'action' => $action ?: 'toggle'
], JSON_UNESCAPED_UNICODE);
exit;
