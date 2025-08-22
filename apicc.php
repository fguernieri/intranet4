<?php
// Pagina para consultar posicao de estoque via API CFYCC873
// Permite personalizar os parametros e exibe a resposta na mesma pagina

$response = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta dos campos do formulario
    $accKey   = trim($_POST['AccKey'] ?? '');
    $tokenKey = trim($_POST['TokenKey'] ?? '');
    $apiName  = trim($_POST['ApiName'] ?? 'CFYCC873');

    $loginUsr = trim($_POST['LoginUsr'] ?? '');
    $nomeUsr  = trim($_POST['NomeUsr'] ?? '');
    $codEmpresa = (int)($_POST['CodEmpresa'] ?? 0);
    $codFilial  = (int)($_POST['CodFilial'] ?? 0);

    $dataRef = (int)($_POST['DataRef'] ?? 0);
    $consolidar = (int)($_POST['ConsolidarCentroEstoque'] ?? 0);
    $nrCentro = trim($_POST['NrCentroEstoque'] ?? '');
    $codRefProduto = trim($_POST['CodRefProduto'] ?? '');
    $codGrupoProduto = trim($_POST['CodGrupoProduto'] ?? '');

    // Monta o payload
    $payload = [
        'Parameters' => [
            'AccKey' => $accKey,
            'TokenKey' => $tokenKey,
            'ApiName' => $apiName,
            'Data' => [
                'Solicitante' => [
                    'LoginUsr' => $loginUsr,
                    'NomeUsr' => $nomeUsr,
                    'CodEmpresa' => $codEmpresa,
                    'CodFilial'  => $codFilial
                ],
                'Filtros' => [
                    'DataRef' => $dataRef,
                    'ConsolidarCentroEstoque' => $consolidar
                ]
            ]
        ]
    ];

    if ($nrCentro !== '') {
        $payload['Parameters']['Data']['Filtros']['NrCentroEstoque'] = $nrCentro;
    }
    if ($codRefProduto !== '') {
        $payload['Parameters']['Data']['Filtros']['CodRefProduto'] = $codRefProduto;
    }
    if ($codGrupoProduto !== '') {
        $payload['Parameters']['Data']['Filtros']['CodGrupoProduto'] = $codGrupoProduto;
    }

    // Configura a requisicao cURL
    $ch = curl_init('https://api.cloudfy.net.br/ApiCFYCC');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $result = curl_exec($ch);
    if ($result === false) {
        $error = 'Erro cURL: ' . curl_error($ch);
    } else {
        $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($httpCode !== 200) {
            $error = 'HTTP ' . $httpCode . ': ' . $result;
        } else {
            $response = json_decode($result, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error = 'Erro ao decodificar JSON: ' . json_last_error_msg();
            }
        }
    }
    curl_close($ch);
}

// Depois do curl_exec
$httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
if ($result === false) {
    $error = 'Erro cURL: ' . curl_error($ch);
} else {
    $decoded = json_decode($result, true);
    // Alguns provedores retornam erro de negócio com 200/4xx/420
    if ($httpCode !== 200 || (isset($decoded['Status']) && $decoded['Status'] < 0)) {
        // Mostra mensagem amigável sem expor AccKey/TokenKey
        $apiMsg = $decoded['Error'] ?? $decoded['Message'] ?? 'Erro desconhecido';
        $params = $decoded['Parameters'] ?? [];
        $error = "Falha na API (HTTP $httpCode): $apiMsg"
               . (!empty($params) ? ' | Detalhes: ' . json_encode($params, JSON_UNESCAPED_UNICODE) : '');
    } else {
        $response = $decoded;
    }
}

?>
<!DOCTYPE html>
<html lang='pt-br'>
<head>
    <meta charset='UTF-8'>
    <title>Consulta de Posicao de Estoque</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        label { display: block; margin-top: 10px; }
        input[type='text'], input[type='number'] { width: 300px; }
        textarea { width: 100%; height: 200px; margin-top: 10px; }
        .error { color: red; }
        .response { white-space: pre-wrap; background: #f4f4f4; padding: 10px; }
    </style>
</head>
<body>
    <h1>Consulta de Posicao de Estoque</h1>
    <form method='post'>
        <label>AccKey: <input type='text' name='AccKey' value='<?= htmlspecialchars($_POST['AccKey'] ?? '') ?>' required></label>
        <label>TokenKey: <input type='text' name='TokenKey' value='<?= htmlspecialchars($_POST['TokenKey'] ?? '') ?>' required></label>
        <label>ApiName: <input type='text' name='ApiName' value='<?= htmlspecialchars($_POST['ApiName'] ?? 'CFYCC873') ?>' required></label>

        <label>LoginUsr: <input type='text' name='LoginUsr' value='<?= htmlspecialchars($_POST['LoginUsr'] ?? '') ?>' required></label>
        <label>NomeUsr: <input type='text' name='NomeUsr' value='<?= htmlspecialchars($_POST['NomeUsr'] ?? '') ?>' required></label>
        <label>CodEmpresa: <input type='number' name='CodEmpresa' value='<?= htmlspecialchars($_POST['CodEmpresa'] ?? '1') ?>' required></label>
        <label>CodFilial: <input type='number' name='CodFilial' value='<?= htmlspecialchars($_POST['CodFilial'] ?? '1') ?>' required></label>

        <label>DataRef (YYYYMMDD): <input type='number' name='DataRef' value='<?= htmlspecialchars($_POST['DataRef'] ?? '') ?>' required></label>
        <label>ConsolidarCentroEstoque (0 ou 1): <input type='number' name='ConsolidarCentroEstoque' value='<?= htmlspecialchars($_POST['ConsolidarCentroEstoque'] ?? '1') ?>' required></label>
        <label>NrCentroEstoque: <input type='text' name='NrCentroEstoque' value='<?= htmlspecialchars($_POST['NrCentroEstoque'] ?? '') ?>'></label>
        <label>CodRefProduto: <input type='text' name='CodRefProduto' value='<?= htmlspecialchars($_POST['CodRefProduto'] ?? '') ?>'></label>
        <label>CodGrupoProduto: <input type='text' name='CodGrupoProduto' value='<?= htmlspecialchars($_POST['CodGrupoProduto'] ?? '') ?>'></label>

        <button type='submit'>Consultar</button>
    </form>

    <?php if ($error): ?>
        <div class='error'><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($response): ?>
        <h2>Resposta</h2>
        <div class='response'><?= htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></div>
    <?php endif; ?>
</body>
</html>
