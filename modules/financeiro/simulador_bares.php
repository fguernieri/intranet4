<?php
// filepath: c:\xampp\htdocs\modules\financeiro\simulador_bares.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once dirname(__FILE__) . '/db_config_financeiro.php';

// Função genérica para requisições REST Supabase
function supabase_request($table, $method = 'GET', $params = '', $body = null) {
    $url = SUPABASE_URL . "/rest/v1/$table" . ($params ? "?$params" : "");
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    $headers = [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
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

// Exemplo: Buscar dados da tabela fcontasapagartap
$anoAtual = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
$params = 'select=ID_CONTA,CATEGORIA,SUBCATEGORIA,DESCRICAO_CONTA,PARCELA,VALOR,DATA_PAGAMENTO,STATUS&DATA_PAGAMENTO=gte.'.$anoAtual.'-01-01&DATA_PAGAMENTO=lte.'.$anoAtual.'-12-31';
$linhas = supabase_request('fcontasapagartap', 'GET', $params);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Simulador Bares - Supabase REST</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
</head>
<body class="bg-gray-900 text-gray-100">
    <main class="container mx-auto p-8">
        <h1 class="text-2xl font-bold mb-6">Simulador Bares (Supabase REST API)</h1>
        <table class="min-w-full bg-gray-800 rounded">
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
    </main>
</body>
</html>