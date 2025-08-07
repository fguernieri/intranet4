<?php
require_once __DIR__ . '/../../config/db.php';

$inicio = $_GET['inicio'] ?? date('Y-m-01');
$fim    = $_GET['fim'] ?? date('Y-m-d');

function callCloudify(string $inicio, string $fim): array {
    $url = 'https://api.cloudify.example/cc870';
    $params = [
        'DataInicio' => str_replace('-', '', $inicio),
        'DataFim'    => str_replace('-', '', $fim)
    ];

    $ch = curl_init($url . '?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return is_array($data) ? $data : [];
}

$raw = callCloudify($inicio, $fim);

$codGrupoCozinha = 'cozinha';
$normalizado = [];
if (isset($raw['CuponsVenda'])) {
    foreach ($raw['CuponsVenda'] as $cupom) {
        $dataMov = DateTime::createFromFormat('Ymd', $cupom['DataMovimento'])->format('Y-m-d');
        foreach ($cupom['Produtos'] as $produto) {
            if (($produto['CodGrupoProduto'] ?? '') !== $codGrupoCozinha) {
                continue;
            }
            $normalizado[] = [
                'data'       => $dataMov,
                'produto'    => $produto['CodRefProduto'],
                'quantidade' => (float) $produto['Qtde'],
                'valor'      => (float) $produto['VlrTotal']
            ];
        }
    }
}

$agregado = [];
foreach ($normalizado as $item) {
    $chave = $item['data'] . '_' . $item['produto'];
    if (!isset($agregado[$chave])) {
        $agregado[$chave] = [
            'data'       => $item['data'],
            'produto_id' => $item['produto'],
            'quantidade' => 0,
            'valor'      => 0
        ];
    }
    $agregado[$chave]['quantidade'] += $item['quantidade'];
    $agregado[$chave]['valor']      += $item['valor'];
}

$pdo->exec("CREATE TABLE IF NOT EXISTS vendas_resumidas_cozinha (
    data DATE NOT NULL,
    produto_id VARCHAR(50) NOT NULL,
    quantidade DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor DECIMAL(10,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (data, produto_id)
)");

$pdo->beginTransaction();
$stmt = $pdo->prepare("INSERT INTO vendas_resumidas_cozinha (data, produto_id, quantidade, valor)
                       VALUES (:data, :produto, :quantidade, :valor)
                       ON DUPLICATE KEY UPDATE quantidade = VALUES(quantidade), valor = VALUES(valor)");
foreach ($agregado as $row) {
    $stmt->execute([
        ':data'       => $row['data'],
        ':produto'    => $row['produto_id'],
        ':quantidade' => $row['quantidade'],
        ':valor'      => $row['valor']
    ]);
}
$pdo->commit();

// Dados para os gráficos
$stmt = $pdo->prepare("SELECT data, SUM(valor) AS total
                       FROM vendas_resumidas_cozinha
                       WHERE data BETWEEN :inicio AND :fim
                       GROUP BY data ORDER BY data");
$stmt->execute([':inicio' => $inicio, ':fim' => $fim]);
$diario = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT DATE_FORMAT(data, '%Y-%m') AS mes, SUM(valor) AS total
                       FROM vendas_resumidas_cozinha
                       WHERE data BETWEEN :inicio AND :fim
                       GROUP BY mes ORDER BY mes");
$stmt->execute([':inicio' => $inicio, ':fim' => $fim]);
$mensal = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Vendas Cozinha</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col sm:flex-row">
<?php include __DIR__ . '/../../sidebar.php'; ?>
<main class="flex-1 p-4 sm:p-10 pt-20 sm:pt-10">
<h1 class="text-2xl font-bold mb-4">Vendas da Cozinha</h1>
<form method="get" class="mb-4 space-x-2">
    <label>Início:<input type="date" name="inicio" value="<?= htmlspecialchars($inicio) ?>"></label>
    <label>Fim:<input type="date" name="fim" value="<?= htmlspecialchars($fim) ?>"></label>
    <button type="submit">Atualizar</button>
</form>
<canvas id="chartDiario" height="120"></canvas>
<canvas id="chartMensal" height="120"></canvas>
</main>
<script>
const diarioLabels = <?= json_encode(array_column($diario, 'data')) ?>;
const diarioData   = <?= json_encode(array_map('floatval', array_column($diario, 'total'))) ?>;
new Chart(document.getElementById('chartDiario'), {
    type: 'bar',
    data: {
        labels: diarioLabels,
        datasets: [{ label: 'Vendas Diárias', data: diarioData, backgroundColor: 'rgba(255,99,132,0.5)' }]
    }
});

const mensalLabels = <?= json_encode(array_column($mensal, 'mes')) ?>;
const mensalData   = <?= json_encode(array_map('floatval', array_column($mensal, 'total'))) ?>;
new Chart(document.getElementById('chartMensal'), {
    type: 'line',
    data: {
        labels: mensalLabels,
        datasets: [{ label: 'Total Mensal', data: mensalData, borderColor: 'rgba(54,162,235,0.8)', fill: false }]
    }
});
</script>
</body>
</html>
