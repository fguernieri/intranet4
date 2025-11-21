<?php
// Análise RFV (Recência, Frequência, Valor) de clientes
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}
$usuario = $_SESSION['usuario_nome'] ?? '';

// Filtros
$estabelecimento = $_GET['estabelecimento'] ?? 'bdm';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

require_once __DIR__ . '/../../supabase_connection.php';
$supabase = new SupabaseConnection();
$tabela_clientes = $estabelecimento === 'bdm' ? 'vw_clientes_bdm_detalhada' : 'vw_clientes_cross_detalhada';

// Buscar dados detalhados dos clientes
$clientes = $supabase->select($tabela_clientes, [
    'select' => 'nr_cliente,data_hora_entrada,vlr_compras',
    'filters' => [
        'data_hora_entrada' => [
            "gte.{$data_inicio} 00:00:00",
            "lte.{$data_fim} 23:59:59"
        ]
    ]
]);

// Calcular RFV
$rfv = [];
$hoje = date('Y-m-d');
if ($clientes && count($clientes) > 0) {
    foreach ($clientes as $c) {
        $id = $c['nr_cliente'];
        $data = substr($c['data_hora_entrada'], 0, 10);
        $valor = floatval($c['vlr_compras'] ?? 0);
        if (!isset($rfv[$id])) {
            $rfv[$id] = [
                'recencia' => $data,
                'frequencia' => 0,
                'valor' => 0
            ];
        }
        // Recência: última visita
        if ($data > $rfv[$id]['recencia']) {
            $rfv[$id]['recencia'] = $data;
        }
        // Frequência: soma das visitas
        $rfv[$id]['frequencia']++;
        // Valor: soma do faturamento
        $rfv[$id]['valor'] += $valor;
    }
    // Calcular dias desde última visita
    foreach ($rfv as $id => &$dados) {
        $dados['dias_recencia'] = (strtotime($hoje) - strtotime($dados['recencia'])) / 86400;
    }
    unset($dados);

    // Calcular scores RFV por percentil baseado em contagem estrita (mais intuitivo com muitos empates)
    $recencias = array_column($rfv, 'dias_recencia');
    $frequencias = array_column($rfv, 'frequencia');
    $valores = array_column($rfv, 'valor');
    sort($recencias); sort($frequencias); sort($valores);

    function percentile_score($arr, $score, $higher_is_better = true) {
        // Percentil baseado em contagem estrita: proporção de valores < score
        $n = count($arr);
        if ($n === 0) return 3;
        $less = 0;
        foreach ($arr as $v) {
            if ($v < $score) $less++;
        }
        $pct = $less / $n; // [0,1)
        $quint = min(5, max(1, intval(floor($pct * 5)) + 1));
        if ($higher_is_better) return $quint;
        return 6 - $quint; // invert for recency (menor melhor)
    }

    foreach ($rfv as $id => &$dados) {
        $dados['score_r'] = percentile_score($recencias, $dados['dias_recencia'], false);
        $dados['score_f'] = percentile_score($frequencias, $dados['frequencia'], true);
        $dados['score_v'] = percentile_score($valores, $dados['valor'], true);
    }
    unset($dados);

    // Segmentação RFV (exemplo)
    $segmentos = [
        'Campeões' => function($r,$f,$v){ return $r==5 && $f==5 && $v==5; },
        'Fiéis' => function($r,$f,$v){ return $r>=4 && $f>=4 && $v>=4 && !($r==5&&$f==5&&$v==5); },
        'Promissores' => function($r,$f,$v){ return $r==5 && $f>=3 && $v>=3 && !($r==5&&$f==5&&$v==5); },
        'Clientes Recentes' => function($r,$f,$v){ return $r==5 && $f<=2 && $v<=2; },
        'Potencial Fiéis' => function($r,$f,$v){ return $r>=3 && $f>=3 && $v>=3 && !($r>=4&&$f>=4&&$v>=4); },
        'Prestes a Hibernar' => function($r,$f,$v){ return $r<=2 && $f>=3 && $v>=2; },
        'Hibernados' => function($r,$f,$v){ return $r==1 && $f<=2 && $v<=2; },
        'Perdidos' => function($r,$f,$v){ return $r==1 && $f==1 && $v==1; },
        'Em Risco' => function($r,$f,$v){ return $r<=2 && $f==5 && $v==5; },
        'Atenção' => function($r,$f,$v){ return $r==3 && $f==3 && $v<=2; },
        'Não Perder' => function($r,$f,$v){ return $r==5 && $f==5 && $v<=2; },
        // (sem regra catch-all) — queremos que as regras principais cubram os casos
    ];
    $rfv_segmentado = [];
    foreach ($rfv as $id => $dados) {
        foreach ($segmentos as $nome => $fn) {
            if ($fn($dados['score_r'],$dados['score_f'],$dados['score_v'])) {
                $rfv_segmentado[$nome][] = $id;
                break;
            }
        }
    }
        // Verificar ids não atribuídos a nenhum segmento (para debug discreto)
        $all_ids = array_keys($rfv);
        $assigned = [];
        foreach ($rfv_segmentado as $arr) { $assigned = array_merge($assigned, $arr); }
        $rfv_unmatched = array_values(array_diff($all_ids, $assigned));
        $rfv_unmatched_count = count($rfv_unmatched);

        // Reatribuição automática por similaridade (Euclidiana) — calcula centroides e atribui
        $reassigned = [];
        if ($rfv_unmatched_count > 0) {
            // Calcular centroides (média R,F,V) para cada segmento existente
            $centroids = [];
            foreach ($rfv_segmentado as $seg => $ids) {
                $n = count($ids);
                if ($n === 0) continue;
                $sumR = $sumF = $sumV = 0;
                foreach ($ids as $cid) {
                    // proteger se algum cliente não tiver scores
                    $sumR += $rfv[$cid]['score_r'] ?? 0;
                    $sumF += $rfv[$cid]['score_f'] ?? 0;
                    $sumV += $rfv[$cid]['score_v'] ?? 0;
                }
                $centroids[$seg] = [$sumR / $n, $sumF / $n, $sumV / $n];
            }

            // Se não houver centroides (caso raro), não reatribuir
            if (!empty($centroids)) {
                foreach ($rfv_unmatched as $cid) {
                    $bestSeg = null;
                    $bestDist = null;
                    $sr = $rfv[$cid]['score_r'];
                    $sf = $rfv[$cid]['score_f'];
                    $sv = $rfv[$cid]['score_v'];
                    foreach ($centroids as $seg => $cent) {
                        $dr = $sr - $cent[0];
                        $df = $sf - $cent[1];
                        $dv = $sv - $cent[2];
                        $dist = sqrt($dr*$dr + $df*$df + $dv*$dv);
                        if ($bestDist === null || $dist < $bestDist) {
                            $bestDist = $dist;
                            $bestSeg = $seg;
                        }
                    }
                    if ($bestSeg === null) {
                        // fallback: primeiro segmento existente
                        $keys = array_keys($rfv_segmentado);
                        $bestSeg = $keys[0] ?? 'Sem Segmento';
                    }
                    $rfv_segmentado[$bestSeg][] = $cid;
                    $reassigned[$cid] = $bestSeg;
                }
            }
        }
        $reassigned_count = count($reassigned);
}
?>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Análise RFV de Clientes</title>
    <link rel="stylesheet" href="/assets/css/tailwind.min.css">
</head>
<body class="bg-gray-900 text-gray-100">
    <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/sidebar.php'; ?>
    <div class="p-8 ml-4">
        <h1 class="text-3xl text-yellow-400 font-bold mb-6">📊 Análise RFV de Clientes</h1>
        <!-- Banner de debug removido conforme solicitado -->
        <form method="GET" class="mb-6 flex gap-4 flex-wrap">
            <select name="estabelecimento" class="px-3 py-2 rounded bg-gray-800 text-gray-200 border border-gray-700">
                <option value="bdm" <?= $estabelecimento === 'bdm' ? 'selected' : '' ?>>Bar do Meio</option>
                <option value="cross" <?= $estabelecimento === 'cross' ? 'selected' : '' ?>>Crossroads</option>
            </select>
            <input type="date" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>" class="px-3 py-2 rounded bg-gray-800 text-gray-200 border border-gray-700">
            <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" class="px-3 py-2 rounded bg-gray-800 text-gray-200 border border-gray-700">
            <button type="submit" class="px-4 py-2 rounded bg-yellow-500 text-white font-bold">Filtrar</button>
        </form>

        <div class="mb-2" style="min-height:360px;">
            <div id="rfv-treemap" style="width:100%;height:100%;"></div>
        </div>
        <div id="rfv-legend" class="mb-4 flex flex-wrap gap-2 text-sm justify-center"></div>
        <div id="rfv-treemap-msg" class="mb-4 text-center text-gray-400"></div>
    </div>
</body>
<script src="https://cdn.plot.ly/plotly-2.24.1.min.js"></script>
<script>
// Dados do PHP para JS
var rfvSegmentado = <?php echo json_encode($rfv_segmentado ?? []); ?>;
var totalClientes = Object.values(rfvSegmentado).reduce((a,b)=>a+b.length,0);
var segLabels = Object.keys(rfvSegmentado);
var segValues = segLabels.map(l => rfvSegmentado[l].length);
var percents = segValues.map(v => totalClientes > 0 ? ((v/totalClientes)*100).toFixed(1) : '0.0');
var baseColors = [
    '#4ade80','#22d3ee','#818cf8','#fbbf24','#f472b6','#a3e635','#fca5a5','#f59e42','#c4b5fd','#fde68a','#f87171','#a7f3d0'
];

if (totalClientes > 0 && segLabels.length > 0) {
    try {
        // Construir arrays ids/parents para uma abordagem alternativa do treemap
        var ids = ['RFV_root'].concat(segLabels.map((l,i) => 'seg_' + i));
        var labels = ['RFV'].concat(segLabels);
        var parents = [''].concat(segLabels.map(() => 'RFV_root'));
        var values = [totalClientes].concat(segValues);
        var colors = ['#0f172a'].concat(baseColors.slice(0, segLabels.length));

        // Ajustar tamanho do layout de forma responsiva e re-renderizar no resize
        var container = document.getElementById('rfv-treemap');
        // garantir que o container ocupe 100% da largura do seu pai — não usar vw para evitar overflow
        container.style.width = '100%';
        container.style.maxWidth = '100%';

        // Remover nó raiz artificial e renderizar apenas os segmentos como nós de topo,
        // assim o treemap preenche todo o espaço sem áreas vazias geradas pelo root
        var trace = {
            type: 'treemap',
            ids: segLabels.map((l,i) => 'seg_' + i),
            labels: segLabels,
            parents: segLabels.map(() => ''),
            values: segValues,
            marker: { colors: colors.slice(1) },
            textinfo: 'label+value+percent entry',
            hovertemplate: '%{label}<br>%{value} clientes<br>%{percentEntry:.1%} do total<extra></extra>',
            branchvalues: 'total'
        };

        function renderTreemap() {
            // Usar a largura real do container para evitar overflow/horizontal scroll
            var w = container.clientWidth || Math.round(window.innerWidth * 0.95);
            // Diminuir a altura para não ficar tão alto (ajustável)
            var h = Math.max(360, Math.round(window.innerHeight * 0.45));
            // garantir que o contêiner tenha a mesma altura do layout do plot
            container.style.height = h + 'px';
            var layout = {
                title: 'Segmentação RFV dos Clientes',
                margin: { t: 40, l: 8, r: 8, b: 8 },
                autosize: false,
                width: w,
                height: h,
                paper_bgcolor: '#1e293b',
                plot_bgcolor: '#1e293b',
                font: { color: '#e5e7eb' }
            };
            Plotly.react('rfv-treemap', [trace], layout, {responsive: true});
        }

        renderTreemap();
        window.addEventListener('resize', function(){
            // debounce rápido
            clearTimeout(window._rfv_resize);
            window._rfv_resize = setTimeout(renderTreemap, 120);
        });
        // Preencher legenda com cores, contagens e percentuais
                function renderLegend() {
                        var legend = document.getElementById('rfv-legend');
                        if (!legend) return;
                        // garantir classes para centralizar a legenda abaixo do gráfico
                        legend.className = 'mb-4 flex flex-wrap gap-2 text-sm justify-center';
                        var legendColors = baseColors.slice(0, segLabels.length);
                        var html = '';
                        for (var i = 0; i < segLabels.length; i++) {
                                var label = segLabels[i];
                                var count = segValues[i] || 0;
                                var pct = percents[i] || '0.0';
                                var color = legendColors[i] || '#6b7280';
                                html += '<div class="inline-flex items-center gap-2 bg-gray-800/70 px-2 py-0.5 rounded mr-2 mb-2" style="min-width:80px;">'
                                         + '<span style="width:10px;height:10px;background:' + color + ';display:inline-block;border-radius:3px;border:1px solid rgba(0,0,0,0.25)"></span>'
                                         + '<div style="line-height:1">'
                                             + '<div style="color:#e5e7eb;font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px">' + label + '</div>'
                                             + '<div style="color:#9ca3af;font-size:11px">' + count + ' • ' + pct + '%</div>'
                                         + '</div>'
                                 + '</div>';
                        }
                        legend.innerHTML = html;
                }

                renderLegend();
        document.getElementById('rfv-treemap-msg').innerHTML = '';
    } catch (e) {
        document.getElementById('rfv-treemap-msg').innerHTML = 'Erro ao renderizar o gráfico: ' + e.message;
        console.error('Plotly error:', e);
    }
} else {
    document.getElementById('rfv-treemap').style.display = 'none';
    document.getElementById('rfv-treemap-msg').innerHTML = 'Nenhum dado para exibir o gráfico RFV.';
}
</script>
</html>