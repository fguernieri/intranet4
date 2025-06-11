<?php
include __DIR__ . '/../../config/db.php';      // Intranet
include __DIR__ . '/../../config/db_dw.php';   // Cloudify

header('Content-Type: application/json; charset=utf-8');

// 1) Carrega dados de ficha técnica e ProdutosBares
$pratos = [];
$totalCusto = 0;
$totalPreco = 0;

$sql = "SELECT nome_prato, codigo_cloudify 
        FROM ficha_tecnica 
        WHERE farol = 'verde' 
          AND codigo_cloudify IS NOT NULL";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$fichas = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($fichas as $ficha) {
    $codigo = $ficha['codigo_cloudify'];
    $sqlPB = "SELECT Grupo, `Custo médio` AS custo, Valor AS preco 
              FROM ProdutosBares 
              WHERE `Cód. Ref.` = ? 
                AND `Custo médio` > 0 
                AND Valor > 0";
    $stmtPB = $pdo_dw->prepare($sqlPB);
    $stmtPB->execute([$codigo]);
    $pb = $stmtPB->fetch(PDO::FETCH_ASSOC);

    if ($pb) {
        $cmv = ($pb['custo'] / $pb['preco']) * 100;
        $pratos[] = [
            'nome'   => $ficha['nome_prato'],
            'grupo'  => $pb['Grupo'],
            'custo'  => (float)$pb['custo'],
            'preco'  => (float)$pb['preco'],
            'cmv'    => $cmv
        ];
        $totalCusto += $pb['custo'];
        $totalPreco += $pb['preco'];
    }
}

// 2) KPIs
$kpis = [
    'total' => count($pratos),
    'custo' => $totalCusto / max(1, count($pratos)),
    'preco' => $totalPreco / max(1, count($pratos)),
    'cmv'   => ($totalCusto / max(1, $totalPreco)) * 100
];

// 3) Chart CMV por prato
$themeDark = ['foreColor' => '#f3f4f6'];
$chartCmv = [
    'chart'  => array_merge(['type' => 'bar'], $themeDark),
    'series' => [[
        'name' => 'CMV (%)',
        'data' => array_map(fn($p) => round($p['cmv'], 1), $pratos)
    ]],
    'xaxis'  => ['categories' => array_column($pratos, 'nome')],
    'colors' => ['#f59e0b']
];

// 4) Chart distribuição por grupo
$grupos = [];
foreach ($pratos as $p) {
    $grupos[$p['grupo']] = ($grupos[$p['grupo']] ?? 0) + 1;
}
$chartGrupo = [
    'chart'  => array_merge(['type' => 'donut'], $themeDark),
    'labels' => array_keys($grupos),
    'series' => array_values($grupos),
    'colors' => ['#ffff8a', '#ffe970', '#ffd256', '#ffbc3c'],
    'stroke' => [
      'show' => false  ],
    'legend'  => [
        'fontSize'        => '10px',
        'position'        => 'bottom',    // posiciona abaixo do gráfico
        'horizontalAlign'=> 'center',     // centraliza na horizontal
        'offsetY'         => 0            // ajuste vertical, se precisar
    ]
];

// 5) Disponibilidade "tempo real" (último lote)
$data = [];

// 5.1) disp_bdf_noite
$sqlNoite = "
    SELECT COUNT(*) AS total, SUM(disponivel) AS disponiveis
    FROM disp_bdf_noite
    WHERE lote_id = (SELECT MAX(lote_id) FROM disp_bdf_noite)
";
$row = $pdo->query($sqlNoite)->fetch(PDO::FETCH_ASSOC);
$data['BDF Noite'] = ($row['total']>0)
    ? round(($row['disponiveis'] / $row['total']) * 100, 1)
    : 0;

// 5.2) disp_bdf_almoco_fds
$sqlFds = "
    SELECT COUNT(*) AS total, SUM(disponivel) AS disponiveis
    FROM disp_bdf_almoco_fds
    WHERE lote_id = (SELECT MAX(lote_id) FROM disp_bdf_almoco_fds)
";
$row = $pdo->query($sqlFds)->fetch(PDO::FETCH_ASSOC);
$data['BDF Alm FDS'] = ($row['total']>0)
    ? round(($row['disponiveis'] / $row['total']) * 100, 1)
    : 0;

// 5.3) disp_wab
$sqlWab = "
    SELECT COUNT(*) AS total, SUM(disponivel) AS disponiveis
    FROM disp_wab
    WHERE lote_id = (SELECT MAX(lote_id) FROM disp_wab)
";
$row = $pdo->query($sqlWab)->fetch(PDO::FETCH_ASSOC);
$data['WAB'] = ($row['total']>0)
    ? round(($row['disponiveis'] / $row['total']) * 100, 1)
    : 0;

// 5.4) disp_bdf_almoco
$sqlAlmoco = "
    SELECT COUNT(*) AS total, SUM(disponivel) AS disponiveis
    FROM disp_bdf_almoco
    WHERE lote_id = (SELECT MAX(lote_id) FROM disp_bdf_almoco)
";
$row = $pdo->query($sqlAlmoco)->fetch(PDO::FETCH_ASSOC);
$data['BDF Almoco'] = ($row['total']>0)
    ? round(($row['disponiveis'] / $row['total']) * 100, 1)
    : 0;

// 6) Disponibilidade últimos 7 dias e 30 dias
$intervals = [
    'BDF Noite'   => 'disp_bdf_noite',
    'BDF Alm FDS' => 'disp_bdf_almoco_fds',
    'WAB'         => 'disp_wab',
    'BDF Almoco'  => 'disp_bdf_almoco'
];

$availability7d = [];
$availability30d = [];

foreach ($intervals as $label => $table) {
    // últimos 7 dias
    $sql7 = "
      SELECT SUM(disponivel) AS disponiveis, COUNT(*) AS total
      FROM {$table}
      WHERE data >= CURDATE() - INTERVAL 7 DAY
    ";
    $r7 = $pdo->query($sql7)->fetch(PDO::FETCH_ASSOC);
    $availability7d[$label] = ($r7['total']>0)
      ? round(($r7['disponiveis'] / $r7['total']) * 100, 1)
      : 0;

    // últimos 30 dias
    $sql30 = "
      SELECT SUM(disponivel) AS disponiveis, COUNT(*) AS total
      FROM {$table}
      WHERE data >= CURDATE() - INTERVAL 30 DAY
    ";
    $r30 = $pdo->query($sql30)->fetch(PDO::FETCH_ASSOC);
    $availability30d[$label] = ($r30['total']>0)
      ? round(($r30['disponiveis'] / $r30['total']) * 100, 1)
      : 0;
}

// 7) Monta e retorna JSON
$result = [
    'kpis'            => $kpis,
    'chartCmv'        => $chartCmv,
    'chartGrupo'      => $chartGrupo,
    'tabela'          => $pratos,
    'availability'    => $data,
    'availability7d'  => $availability7d,
    'availability30d' => $availability30d
];

echo json_encode($result);
exit;
?>
