<?php
// filepath: c:\xampp\htdocs\modules\financeiro\simulador_bares.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==================== [ AUTENTICAÇÃO DE USUÁRIO PADRÃO HOME.PHP ] ====================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
$connAuth = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$connAuth->set_charset('utf8mb4');
if ($connAuth->connect_error) {
    die('Conexão falhou: ' . $connAuth->connect_error);
}
if (empty($_SESSION['usuario_id'])) {
    die('Acesso restrito: usuário não autenticado.');
}
if (!isset($_SESSION['usuario_perfil']) || !in_array($_SESSION['usuario_perfil'], ['admin', 'supervisor'])) {
    die('Acesso restrito: perfil não autorizado.');
}

// ==================== [ SUPABASE CONFIG ] ====================
$config = parse_ini_file(__DIR__ . '/config.ini');
if (!$config || !isset($config['SUPABASE_URL']) || !isset($config['SUPABASE_API_KEY'])) {
    die('Configuração do Supabase não encontrada ou incompleta.');
}
$SUPABASE_URL = $config['SUPABASE_URL'];
$SUPABASE_API_KEY = $config['SUPABASE_API_KEY'];

// Função genérica para requisições REST Supabase
function supabase_request($table, $method = 'GET', $params = '', $body = null) {
    global $SUPABASE_URL, $SUPABASE_API_KEY;
    $url = $SUPABASE_URL . "/rest/v1/$table" . ($params ? "?$params" : "");
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    $headers = [
        'apikey: ' . $SUPABASE_API_KEY,
        'Authorization: Bearer ' . $SUPABASE_API_KEY,
        'Content-Type: application/json'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// ==================== [ AUTENTICAÇÃO E PERMISSÃO BÁSICA ] ====================
session_start();
if (empty($_SESSION['usuario_id']) || !isset($_SESSION['usuario_perfil']) || !in_array($_SESSION['usuario_perfil'], ['admin', 'supervisor'])) {
    die('Acesso restrito.');
}

// ==================== [ FILTROS DE ANO/MÊS ] ====================
$anoAtual = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
$mesAtual = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');

// ==================== [ EXEMPLO DE BUSCA DE DADOS PARA O TAP ] ====================
$params = 'select=ID_CONTA,CATEGORIA,SUBCATEGORIA,DESCRICAO_CONTA,PARCELA,VALOR,DATA_PAGAMENTO,STATUS&DATA_PAGAMENTO=gte.'.$anoAtual.'-01-01&DATA_PAGAMENTO=lte.'.$anoAtual.'-12-31';
$linhas = supabase_request('fcontasapagartap', 'GET', $params);

// Aqui você pode buscar outras tabelas do TAP, como receitas, metas, simulações, etc, usando supabase_request

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Simulador TAP - Supabase REST</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
</head>
<body class="bg-gray-900 text-gray-100">
    <main class="container mx-auto p-8">
        <h1 class="text-2xl font-bold mb-6">Simulador TAP (Supabase REST API)</h1>
        <!-- Filtros -->
        <form method="get" class="mb-4 flex gap-4">
            <label>
                Ano:
                <input type="number" name="ano" value="<?= $anoAtual ?>" class="text-black p-1 rounded" style="width:80px;">
            </label>
            <label>
                Mês:
                <input type="number" name="mes" value="<?= $mesAtual ?>" min="1" max="12" class="text-black p-1 rounded" style="width:60px;">
            </label>
            <button type="submit" class="bg-blue-600 px-4 py-1 rounded text-white">Filtrar</button>
        </form>

        <!-- Tabela principal (exemplo, expanda conforme sua lógica) -->
        <table class="min-w-full bg-gray-800 rounded mb-8">
            <thead>
                <tr>
                    <th class="p-2">ID</th>
                    <th class="p-2">Categoria</th>
                    <th class="p-2">Subcategoria</th>
                    <th class="p-2">Descrição</th>
                    <th class="p-2">Parcela</th>
                    <th class="p-2">Valor</th>
                    <th class="p-2">Data Pagamento</th>
                    <th class="p-2">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($linhas): ?>
                    <?php foreach ($linhas as $linha): ?>
                        <tr>
                            <td class="p-2"><?= htmlspecialchars($linha['ID_CONTA']) ?></td>
                            <td class="p-2"><?= htmlspecialchars($linha['CATEGORIA']) ?></td>
                            <td class="p-2"><?= htmlspecialchars($linha['SUBCATEGORIA']) ?></td>
                            <td class="p-2"><?= htmlspecialchars($linha['DESCRICAO_CONTA']) ?></td>
                            <td class="p-2"><?= htmlspecialchars($linha['PARCELA']) ?></td>
                            <td class="p-2"><?= number_format($linha['VALOR'], 2, ',', '.') ?></td>
                            <td class="p-2"><?= htmlspecialchars($linha['DATA_PAGAMENTO']) ?></td>
                            <td class="p-2"><?= htmlspecialchars($linha['STATUS']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="p-2 text-center">Nenhum dado encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Aqui você pode adicionar blocos para metas, simulações, controles, gráficos, etc -->
        <!-- Exemplo: <div id="simulacao"></div> -->
    </main>
</body>
</html>