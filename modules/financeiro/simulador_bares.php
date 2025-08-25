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

// ==================== [ FILTROS DE ANO/MÊS/ORDENAÇÃO ] ====================
$anoAtual = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
$mesAtual = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');
$ordenacao = isset($_GET['ordenacao']) ? $_GET['ordenacao'] : 'nome';

// ==================== [ CONSULTAS PRINCIPAIS - TAP ] ====================

// 1. Despesas (fcontasapagartap)
$paramsDespesas = 'select=ID_CONTA,CATEGORIA,SUBCATEGORIA,DESCRICAO_CONTA,PARCELA,VALOR,DATA_PAGAMENTO,STATUS&DATA_PAGAMENTO=gte.'.$anoAtual.'-01-01&DATA_PAGAMENTO=lte.'.$anoAtual.'-12-31';
$despesas = supabase_request('fcontasapagartap', 'GET', $paramsDespesas);

// 2. Outras Receitas (foutrasreceitastap)
$paramsOutrasRec = 'select=CATEGORIA,SUBCATEGORIA,DATA_COMPETENCIA,VALOR&DATA_COMPETENCIA=gte.'.$anoAtual.'-01-01&DATA_COMPETENCIA=lte.'.$anoAtual.'-12-31';
$outrasReceitas = supabase_request('foutrasreceitastap', 'GET', $paramsOutrasRec);

// 3. Receitas (fcontasareceberdetalhestap)
$paramsReceitas = "select=DATA_PAGAMENTO,VALOR_PAGO,STATUS&STATUS=eq.Pago&DATA_PAGAMENTO=gte.$anoAtual-01-01&DATA_PAGAMENTO=lte.$anoAtual-12-31";
$receitas = supabase_request('fcontasareceberdetalhestap', 'GET', $paramsReceitas);

// 4. Metas (fMetasTap)
$paramsMetas = 'select=Categoria,Subcategoria,Meta,Data&order=Data.desc';
$metas = supabase_request('fMetasTap', 'GET', $paramsMetas);

// 5. Simulações (fSimulacoesTap)
$paramsSimulacoes = 'select=UsuarioID,NomeSimulacao,DataCriacao,Ativo';
$simulacoes = supabase_request('fSimulacoesTap', 'GET', $paramsSimulacoes);

// ==================== [ PROCESSAMENTO DE MATRIZ E ORDENAÇÃO ] ====================
// Aqui você deve montar a matriz de dados igual ao Home.php, calcular médias, totais, percentuais, etc.
// Também implemente a ordenação de subcategorias conforme $ordenacao.

// ==================== [ HTML DA TABELA E FILTROS ] ====================
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
            <label>
                Ordenação:
                <select name="ordenacao" class="text-black p-1 rounded">
                    <option value="nome" <?= $ordenacao === 'nome' ? 'selected' : '' ?>>Nome</option>
                    <option value="valor" <?= $ordenacao === 'valor' ? 'selected' : '' ?>>Valor</option>
                    <option value="percentual" <?= $ordenacao === 'percentual' ? 'selected' : '' ?>>Percentual</option>
                </select>
            </label>
            <button type="submit" class="bg-blue-600 px-4 py-1 rounded text-white">Filtrar</button>
        </form>

        <!-- Exemplo de exibição de despesas -->
        <h2 class="text-xl font-bold mb-2">Despesas TAP</h2>
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
                <?php if ($despesas): ?>
                    <?php foreach ($despesas as $linha): ?>
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

        <!-- Tabela colapsada por Categoria/Subcategoria -->
        <h2 class="text-xl font-bold mb-2">Despesas TAP (Agrupadas)</h2>
        <table class="min-w-full bg-gray-800 rounded mb-8">
            <thead>
                <tr>
                    <th class="p-2">Categoria</th>
                    <th class="p-2">Subcategoria</th>
                    <th class="p-2">Total</th>
                    <th class="p-2">Detalhes</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Agrupa por categoria e subcategoria
                $agrupado = [];
                if ($despesas) {
                    foreach ($despesas as $linha) {
                        $cat = $linha['CATEGORIA'] ?: 'SEM CATEGORIA';
                        $sub = $linha['SUBCATEGORIA'] ?: 'SEM SUBCATEGORIA';
                        if (!isset($agrupado[$cat])) $agrupado[$cat] = [];
                        if (!isset($agrupado[$cat][$sub])) $agrupado[$cat][$sub] = ['total' => 0, 'itens' => []];
                        $agrupado[$cat][$sub]['total'] += floatval($linha['VALOR']);
                        $agrupado[$cat][$sub]['itens'][] = $linha;
                    }
                }
                $catIdx = 0;
                foreach ($agrupado as $cat => $subs):
                    $catIdx++;
                    $catId = 'cat' . $catIdx;
                ?>
                    <tr class="bg-gray-700 cursor-pointer" onclick="document.querySelectorAll('.<?= $catId ?>').forEach(e=>e.classList.toggle('hidden'));">
                        <td class="p-2 font-bold"><?= htmlspecialchars($cat) ?></td>
                        <td class="p-2"></td>
                        <td class="p-2 font-bold"><?= number_format(array_sum(array_column($subs, 'total')), 2, ',', '.') ?></td>
                        <td class="p-2 text-blue-400">Expandir/Recolher</td>
                    </tr>
                    <?php foreach ($subs as $sub => $dados): ?>
                        <tr class="bg-gray-600 <?= $catId ?> hidden">
                            <td class="p-2"></td>
                            <td class="p-2 font-semibold"><?= htmlspecialchars($sub) ?></td>
                            <td class="p-2"><?= number_format($dados['total'], 2, ',', '.') ?></td>
                            <td class="p-2">
                                <button type="button" onclick="document.getElementById('det-<?= md5($cat.$sub) ?>').classList.toggle('hidden');event.stopPropagation();" class="text-blue-300 underline">Detalhar</button>
                            </td>
                        </tr>
                        <tr id="det-<?= md5($cat.$sub) ?>" class="bg-gray-500 <?= $catId ?> hidden">
                            <td colspan="4" class="p-2">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr>
                                            <th class="p-1">ID</th>
                                            <th class="p-1">Descrição</th>
                                            <th class="p-1">Parcela</th>
                                            <th class="p-1">Valor</th>
                                            <th class="p-1">Data Pagamento</th>
                                            <th class="p-1">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dados['itens'] as $item): ?>
                                            <tr>
                                                <td class="p-1"><?= htmlspecialchars($item['ID_CONTA']) ?></td>
                                                <td class="p-1"><?= htmlspecialchars($item['DESCRICAO_CONTA']) ?></td>
                                                <td class="p-1"><?= htmlspecialchars($item['PARCELA']) ?></td>
                                                <td class="p-1"><?= number_format($item['VALOR'], 2, ',', '.') ?></td>
                                                <td class="p-1"><?= htmlspecialchars($item['DATA_PAGAMENTO']) ?></td>
                                                <td class="p-1"><?= htmlspecialchars($item['STATUS']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <?php if (empty($agrupado)): ?>
                    <tr><td colspan="4" class="p-2 text-center">Nenhum dado encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <script>
        // Fecha todos os detalhes ao carregar
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('tr[id^="det-"]').forEach(e => e.classList.add('hidden'));
        });
        </script>
        
        <!-- Blocos para receitas, outras receitas, metas e simulações devem ser criados aqui, seguindo a lógica do Home.php -->

        <!-- Exemplo: <div id="simulacao"></div> -->
    </main>
</body>
</html>