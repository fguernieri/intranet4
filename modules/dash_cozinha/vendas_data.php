<?php
// modules/dash_cozinha/vendas_data.php
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/db_dw.php';
require_once __DIR__ . '/vendas_migrate.php';

cozinha_vendas_ensure_tables($pdo);

header('Content-Type: application/json; charset=utf-8');

function date_param($key, $defaultDaysAgo = null) {
    if (!empty($_GET[$key])) return $_GET[$key];
    if ($defaultDaysAgo !== null) return date('Y-m-d', strtotime("-{$defaultDaysAgo} days"));
    return null;
}

$action = $_GET['action'] ?? 'overview';
$de  = date_param('de', 30);
$ate = date('Y-m-d', strtotime(($_GET['ate'] ?? 'today')));

try {
    if ($action === 'overview') {
        // Faturamento diário
        $sql = "SELECT data, SUM(total) faturamento
                FROM cozinha_vendas_diarias
                WHERE data BETWEEN :de AND :ate
                GROUP BY data ORDER BY data";
        $st = $pdo->prepare($sql);
        $st->execute([':de'=>$de, ':ate'=>$ate]);
        $fat = $st->fetchAll(PDO::FETCH_ASSOC);
        $cats = array_map(fn($r)=>$r['data'], $fat);
        $serFat = [[ 'name' => 'Faturamento', 'data' => array_map(fn($r)=> (float)$r['faturamento'], $fat) ]];

        // Top pratos por quantidade no período
        $sqlTop = "SELECT codigo, COALESCE(MAX(produto),'') produto, SUM(qtde) qtd
                   FROM cozinha_vendas_diarias
                   WHERE data BETWEEN :de AND :ate
                   GROUP BY codigo ORDER BY qtd DESC LIMIT 20";
        $st = $pdo->prepare($sqlTop);
        $st->execute([':de'=>$de, ':ate'=>$ate]);
        $top = $st->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'periodo' => ['de'=>$de,'ate'=>$ate],
            'faturamento' => [ 'categories' => $cats, 'series' => $serFat ],
            'pratos' => $top
        ]);
        exit;
    }

    if ($action === 'list_pratos') {
        $sql = "SELECT codigo, COALESCE(MAX(produto),'') produto
                FROM cozinha_vendas_diarias
                WHERE data BETWEEN :de AND :ate
                GROUP BY codigo ORDER BY produto";
        $st = $pdo->prepare($sql);
        $st->execute([':de'=>$de, ':ate'=>$ate]);
        echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'series_pratos') {
        $codes = isset($_GET['codes']) ? array_filter(array_map('trim', explode(',', $_GET['codes']))) : [];
        if (!$codes) { echo json_encode(['categories'=>[], 'series'=>[]]); exit; }

        // Build dates axis
        $period = new DatePeriod(new DateTime($de), new DateInterval('P1D'), (new DateTime($ate))->modify('+1 day'));
        $categories = [];
        foreach ($period as $dt) { $categories[] = $dt->format('Y-m-d'); }

        $series = [];
        $in  = implode(',', array_fill(0, count($codes), '?'));
        $sql = "SELECT codigo, data, SUM(qtde) qtd
                FROM cozinha_vendas_diarias
                WHERE data BETWEEN ? AND ? AND codigo IN ($in)
                GROUP BY codigo, data";
        $params = array_merge([$de, $ate], $codes);
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $byCodeDate = [];
        foreach ($rows as $r) { $byCodeDate[$r['codigo']][$r['data']] = (float)$r['qtd']; }

        // Get names
        $sqlN = "SELECT codigo, COALESCE(MAX(produto),'') produto
                 FROM cozinha_vendas_diarias WHERE codigo IN ($in) GROUP BY codigo";
        $stN = $pdo->prepare($sqlN);
        $stN->execute($codes);
        $names = [];
        foreach ($stN->fetchAll(PDO::FETCH_ASSOC) as $r) { $names[$r['codigo']] = $r['produto']; }

        foreach ($codes as $c) {
            $data = [];
            foreach ($categories as $d) { $data[] = isset($byCodeDate[$c][$d]) ? (float)$byCodeDate[$c][$d] : 0; }
            $series[] = [ 'name' => ($names[$c] ?? $c), 'data' => $data ];
        }

        echo json_encode(['categories'=>$categories, 'series'=>$series]);
        exit;
    }

    if ($action === 'matriz') {
        // Popularidade (qtd), CMV (%) do Cloudify, e preço (médio do período via Excel)
        // 1) Vendas agregadas
        $sql = "SELECT codigo, COALESCE(MAX(produto),'') produto,
                       COALESCE(MAX(grupo),'') grupo,
                       AVG(preco) preco_medio,
                       SUM(qtde) qtd
                FROM cozinha_vendas_diarias
                WHERE data BETWEEN :de AND :ate
                GROUP BY codigo";
        $st = $pdo->prepare($sql);
        $st->execute([':de'=>$de, ':ate'=>$ate]);
        $vendas = $st->fetchAll(PDO::FETCH_ASSOC);

        // 2) CMV por código a partir do DW
        $cmvByCode = [];
        if ($vendas) {
            $codes = array_column($vendas, 'codigo');
            $in = implode(',', array_fill(0, count($codes), '?'));
            $sqlC = "SELECT `Cód. Ref.` AS codigo, `Custo médio` AS custo, Valor AS preco
                     FROM ProdutosBares WHERE `Cód. Ref.` IN ($in)";
            $stC = $pdo_dw->prepare($sqlC);
            $stC->execute($codes);
            foreach ($stC->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $preco = (float)($r['preco'] ?? 0);
                $custo = (float)($r['custo'] ?? 0);
                $cmv   = $preco > 0 ? ($custo / $preco) * 100.0 : 0.0;
                $cmvByCode[(string)$r['codigo']] = $cmv;
            }
        }

        // 3) Monta pontos para scatter/bubble
        $points = [];
        foreach ($vendas as $v) {
            $codigo = (string)$v['codigo'];
            $points[] = [
                'name' => $v['produto'] ?: $codigo,
                'codigo' => $codigo,
                'grupo' => $v['grupo'],
                // x: CMV (%), y: quantidade, z: preço (tamanho do marcador)
                'x' => round($cmvByCode[$codigo] ?? 0, 1),
                'y' => (float)$v['qtd'],
                'z' => round((float)$v['preco_medio'], 2)
            ];
        }

        echo json_encode(['points'=>$points]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['erro'=>'Ação inválida']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['erro'=>$e->getMessage()]);
}
?>

