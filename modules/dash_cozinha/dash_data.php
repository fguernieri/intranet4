<?php
include __DIR__ . '/../../config/db.php';      // Intranet
include __DIR__ . '/../../config/db_dw.php';   // Cloudify

header('Content-Type: application/json; charset=utf-8');

/**
 * Carrega os produtos do DW de acordo com a base informada.
 *
 * @param PDO    $pdoDw   Conexão com o DW.
 * @param string $tabela  Nome da tabela (ProdutosBares_WAB/BDF).
 * @param array  $codigos Lista de códigos Cloudify.
 *
 * @return array<string,array{grupo:?string,custo:float,preco:float}>
 */
function carregarProdutosPorBase(PDO $pdoDw, string $tabela, array $codigos): array
{
    if (empty($codigos)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($codigos), '?'));
    $sql = "SELECT `Cód. Ref.` AS codigo, Grupo, `Custo médio` AS custo, Valor AS preco
            FROM `$tabela`
            WHERE `Cód. Ref.` IN ($placeholders)";

    $stmt = $pdoDw->prepare($sql);
    $stmt->execute($codigos);

    $dados = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $codigo = (string)($row['codigo'] ?? '');
        if ($codigo === '') {
            continue;
        }
        $dados[$codigo] = [
            'grupo' => $row['Grupo'] ?? 'Não categorizado',
            'custo' => isset($row['custo']) ? (float)$row['custo'] : 0.0,
            'preco' => isset($row['preco']) ? (float)$row['preco'] : 0.0,
        ];
    }

    return $dados;
}

// 1) Carrega dados de ficha técnica e ProdutosBares
$pratos = [];
$totalCusto = 0.0;
$totalPreco = 0.0;

$sql = "SELECT nome_prato, codigo_cloudify, base_origem
        FROM ficha_tecnica
        WHERE farol = 'verde'";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$fichas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$todosCodigos = [];

foreach ($fichas as &$ficha) {
    $base = strtoupper($ficha['base_origem'] ?? 'WAB');
    if (!in_array($base, ['WAB', 'BDF'], true)) {
        $base = 'WAB';
    }
    $ficha['base_origem'] = $base;

    $codigo = $ficha['codigo_cloudify'];
    if ($codigo !== null && $codigo !== '') {
        $todosCodigos[] = (string)$codigo;
    }
}
unset($ficha);

$todosCodigos = array_values(array_unique(array_filter($todosCodigos, static function ($codigo) {
    return $codigo !== '';
})));

$tabelasDw = [
    'WAB' => 'ProdutosBares_WAB',
    'BDF' => 'ProdutosBares_BDF',
];

$dadosDw = [];
foreach ($tabelasDw as $base => $tabela) {
    $dadosDw[$base] = carregarProdutosPorBase($pdo_dw, $tabela, $todosCodigos);
}

foreach ($fichas as $ficha) {
    $codigo = $ficha['codigo_cloudify'];
    $baseOrigem = $ficha['base_origem'];
    $nomePrato = $ficha['nome_prato'];

    if ($codigo === null || $codigo === '') {
        $pratos[] = [
            'codigo'         => $codigo,
            'nome'           => $nomePrato,
            'grupo'          => 'Não categorizado',
            'custo'          => 0.0,
            'preco'          => 0.0,
            'cmv'            => 0.0,
            'base_origem'    => $baseOrigem,
            'base_dados'     => null,
            'possui_dados'   => false,
            'usou_fallback'  => false,
        ];
        continue;
    }

    $codigoStr = (string)$codigo;
    $dados = $dadosDw[$baseOrigem][$codigoStr] ?? null;
    $baseDados = $dados ? $baseOrigem : null;

    if ($dados === null) {
        $baseAlternativa = $baseOrigem === 'WAB' ? 'BDF' : 'WAB';
        $dados = $dadosDw[$baseAlternativa][$codigoStr] ?? null;
        if ($dados !== null) {
            $baseDados = $baseAlternativa;
        }
    }

    if ($dados !== null) {
        $custo = (float)$dados['custo'];
        $preco = (float)$dados['preco'];
        $cmv = ($preco > 0) ? ($custo / $preco) * 100 : 0.0;

        $pratos[] = [
            'codigo'         => $codigo,
            'nome'           => $nomePrato,
            'grupo'          => $dados['grupo'] ?? 'Não categorizado',
            'custo'          => $custo,
            'preco'          => $preco,
            'cmv'            => $cmv,
            'base_origem'    => $baseOrigem,
            'base_dados'     => $baseDados,
            'possui_dados'   => true,
            'usou_fallback'  => ($baseDados !== null && $baseDados !== $baseOrigem),
        ];

        $totalCusto += $custo;
        $totalPreco += $preco;
    } else {
        $pratos[] = [
            'codigo'         => $codigo,
            'nome'           => $nomePrato,
            'grupo'          => 'Não categorizado',
            'custo'          => 0.0,
            'preco'          => 0.0,
            'cmv'            => 0.0,
            'base_origem'    => $baseOrigem,
            'base_dados'     => null,
            'possui_dados'   => false,
            'usou_fallback'  => false,
        ];
    }
}

// 2) KPIs
// Contagem de pratos com preço e custo válidos para médias
$pratosValidos = array_filter($pratos, function($p) {
    return $p['preco'] > 0;
});
$countValidos = count($pratosValidos);

$kpis = [
    'total' => count($pratos),
    'custo' => $countValidos > 0 ? $totalCusto / $countValidos : 0,
    'preco' => $countValidos > 0 ? $totalPreco / $countValidos : 0,
    'cmv'   => ($totalPreco > 0) ? ($totalCusto / $totalPreco) * 100 : 0
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
