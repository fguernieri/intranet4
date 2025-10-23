<?php
// Inicia sessão e carrega config/db
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/projecao_cervejas_functions.php';

// Lista das cervejas que devem ser exibidas
$cervejas_permitidas = [
    'BASTARDS PILSEN',
    "JUICY JILL",
    'DOG SAVE THE BEER', 
    'ZE DO MORRO',
    'HECTOR 5 ROUNDS',
    'HERMES E RENATO',
    'WILLIE THE BITTER',
    'RANNA RIDER',
    'PINA A VIVA',
    'JEAN LE BLANC',
    'MARK THE SHADOW',
    'CRIATURA DO PANTANO',
    'XP 094 HOP HEADS',
    'OLD BUT GOLD',
    'BASTARDS RED ALE'
];

// Classe para conectar via API REST do Supabase
class SupabaseApiClient {
    private $url;
    private $key;
    
    public function __construct() {
        $this->url = 'https://gybhszcefuxsdhpvxbnk.supabase.co/rest/v1/'; // NOVA URL
        $this->key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imd5YmhzemNlZnV4c2RocHZ4Ym5rIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTMwMTYwOTYsImV4cCI6MjA2ODU5MjA5Nn0.i3sOjUvpsimEvtO0uDGFNK92IZjcy1VIva_KEBdlZI8'; // NOVA CHAVE
    }
    
    public function query($table, $select = '*', $order = null) {
        $url = $this->url . $table . '?select=' . urlencode($select);
        
        if ($order) {
            $url .= '&order=' . urlencode($order);
        }
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'apikey: ' . $this->key,
                    'Authorization: Bearer ' . $this->key,
                    'Content-Type: application/json'
                ]
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception('Erro ao fazer requisição para Supabase');
        }
        
        return json_decode($response, true);
    }
    
    // NOVA FUNÇÃO: Busca dados de uma cerveja específica
    public function getDadosCerveja($nome_cerveja) {
        try {
            $select = 'DATA,CERVEJA,ESTOQUE_INICIAL,MEDIA_DIARIA,PRODUCAO_FUTURA,PROD_SEM_TANQUE,PRODUCAO_ATRASADA,ESTOQUE_ACUMULADO,PROJECAO_COM_E_SEM_TANQUE,PROJECAO_COM_PRODUCAO_ATRASADA';
            
            // URL com filtro específico para a cerveja
            $url = $this->url . 'vw_projecao_estoque?select=' . urlencode($select) . '&CERVEJA=eq.' . urlencode($nome_cerveja) . '&order=DATA.asc&limit=100';
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'apikey: ' . $this->key,
                        'Authorization: Bearer ' . $this->key,
                        'Content-Type: application/json'
                    ]
                ]
            ]);
            
            $response = file_get_contents($url, false, $context);
            
            if ($response === false) {
                throw new Exception('Erro ao fazer requisição para cerveja: ' . $nome_cerveja);
            }
            
            $data = json_decode($response, true);
            
            // Converte para maiúscula para compatibilidade
            $dados_normalizados = [];
            foreach ($data as $row) {
                $linha_normalizada = [];
                foreach ($row as $chave => $valor) {
                    $linha_normalizada[strtoupper($chave)] = $valor;
                }
                $dados_normalizados[] = $linha_normalizada;
            }
            
            echo "<!-- CERVEJA '$nome_cerveja': " . count($dados_normalizados) . " registros encontrados -->\n";
            
            return $dados_normalizados;
        } catch (Exception $e) {
            echo "<!-- ERRO ao buscar '$nome_cerveja': " . $e->getMessage() . " -->\n";
            return [];
        }
    }
    
    public function getMediaVendasPCP() {
        try {
            $select = 'CERVEJA,MEDIA_DIARIA';
            $data = $this->query('vw_mediavendasparapcp', $select, 'CERVEJA');
            
            $medias = [];
            foreach ($data as $row) {
                $medias[strtoupper($row['CERVEJA'])] = (float)$row['MEDIA_DIARIA'];
            }
            
            return $medias;
        } catch (Exception $e) {
            error_log("Erro ao buscar médias de vendas PCP: " . $e->getMessage());
            return false;
        }
    }
}

// Conecta via API do Supabase
try {
    $supabase = new SupabaseApiClient();
    // Testa conexão apenas buscando 1 registro
    
} catch (Exception $e) {
    die("❌ Erro de conexão via API: " . $e->getMessage());
}

// Obtém médias de vendas
$medias_vendas_pcp = $supabase->getMediaVendasPCP();
if ($medias_vendas_pcp === false) {
    $medias_vendas_pcp = [];
}

// NOVA ABORDAGEM: Busca dados individualmente para cada cerveja
echo "<!-- === BUSCANDO DADOS INDIVIDUAIS === -->\n";
$dados_por_cerveja = [];

foreach ($cervejas_permitidas as $cerveja) {
    echo "<!-- Buscando dados para: '$cerveja' -->\n";
    
    $dados_cerveja = $supabase->getDadosCerveja($cerveja);
    
    if (!empty($dados_cerveja)) {
        $dados_por_cerveja[$cerveja] = $dados_cerveja;
        echo "<!-- ✅ '$cerveja': " . count($dados_cerveja) . " registros -->\n";
    } else {
        echo "<!-- ❌ '$cerveja': Nenhum registro encontrado -->\n";
        
        // Tenta variações do nome se não encontrar
        $variacoes = [];
        if (strpos($cerveja, 'ZE') !== false) {
            $variacoes = ['ZÉ DO MORRO', 'ZE DO MORRO', 'Zé do Morro'];
        }
        
        foreach ($variacoes as $variacao) {
            if ($variacao !== $cerveja) {
                echo "<!-- Tentando variação: '$variacao' -->\n";
                $dados_variacao = $supabase->getDadosCerveja($variacao);
                if (!empty($dados_variacao)) {
                    $dados_por_cerveja[$cerveja] = $dados_variacao;
                    echo "<!-- ✅ Encontrado com variação '$variacao': " . count($dados_variacao) . " registros -->\n";
                    break;
                }
            }
        }
    }
}

echo "<!-- === FIM BUSCA INDIVIDUAL === -->\n";

// Função para calcular estoque mínimo
function getEstoqueMinimo($cerveja, $medias_vendas_pcp) {
    $cerveja_upper = strtoupper($cerveja);
    
    if (isset($medias_vendas_pcp[$cerveja_upper]) && $medias_vendas_pcp[$cerveja_upper] > 0) {
        return $medias_vendas_pcp[$cerveja_upper] * 15;
    }
    
    return 1000;
}

// Debug final: Resumo dos dados obtidos
echo "<!-- === RESUMO FINAL === -->\n";
foreach ($dados_por_cerveja as $cerveja => $dados_cerveja) {
    $total_registros = count($dados_cerveja);
    if ($total_registros > 0) {
        $primeira_data = $dados_cerveja[0]['DATA'];
        $ultima_data = end($dados_cerveja)['DATA'];
        echo "<!-- FINAL - $cerveja: $total_registros registros | De: $primeira_data | Até: $ultima_data -->\n";
    }
}
echo "<!-- Total de cervejas com dados: " . count($dados_por_cerveja) . " -->\n";

// NOVO: Ordenar por estoque mínimo (maior para menor)
echo "<!-- === ORDENANDO POR ESTOQUE MÍNIMO === -->\n";

// Cria array com cerveja e seu estoque mínimo para ordenação
$cervejas_com_estoque_minimo = [];

foreach ($cervejas_permitidas as $cerveja) {
    $estoque_minimo = getEstoqueMinimo($cerveja, $medias_vendas_pcp);
    $tem_dados = isset($dados_por_cerveja[$cerveja]);
    
    $cervejas_com_estoque_minimo[] = [
        'cerveja' => $cerveja,
        'estoque_minimo' => $estoque_minimo,
        'tem_dados' => $tem_dados
    ];
    
    echo "<!-- $cerveja: Estoque mínimo = " . number_format($estoque_minimo, 2) . " litros -->\n";
}

// Ordena do maior estoque mínimo para o menor
usort($cervejas_com_estoque_minimo, function($a, $b) {
    // Primeiro ordena por ter dados (cervejas com dados primeiro)
    if ($a['tem_dados'] !== $b['tem_dados']) {
        return $b['tem_dados'] - $a['tem_dados'];
    }
    // Depois ordena por estoque mínimo (maior para menor)
    return $b['estoque_minimo'] - $a['estoque_minimo'];
});

// Reordena as listas baseado na ordenação
$cervejas_permitidas_ordenadas = [];
foreach ($cervejas_com_estoque_minimo as $item) {
    $cervejas_permitidas_ordenadas[] = $item['cerveja'];
}

echo "<!-- === ORDEM FINAL === -->\n";
foreach ($cervejas_permitidas_ordenadas as $index => $cerveja) {
    $estoque_minimo = getEstoqueMinimo($cerveja, $medias_vendas_pcp);
    $tem_dados = isset($dados_por_cerveja[$cerveja]) ? 'COM DADOS' : 'SEM DADOS';
    echo "<!-- " . ($index + 1) . "º: $cerveja | " . number_format($estoque_minimo, 2) . " L | $tem_dados -->\n";
}

// Substitui a lista original pela ordenada
$cervejas_permitidas = $cervejas_permitidas_ordenadas;

// Busca a data/hora mais recente da tabela fatualizacoes
try {
    $fatualizacoes = $supabase->query('fatualizacoes', 'data_hora', 'data_hora.desc');
    $atualizacao_recente = isset($fatualizacoes[0]['data_hora']) ? $fatualizacoes[0]['data_hora'] : null;
} catch (Exception $e) {
    $atualizacao_recente = null;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de planejamento de produções</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@4.0.0"></script>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
        }
        .divider_yellow {
            border-top: 2px solid #eab308;
        }
        .grafico-container {
            background: #f3f4f6;
            border-radius: 8px;
            padding: 12px;
            border: 2px solid #eab308;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .chart-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .sem-dados {
            background: #fee2e2;
            border: 2px solid #ef4444;
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex flex-col sm:flex-row">

    <?php require_once __DIR__ . '/../../sidebar.php'; ?>

    <!-- Conteúdo Principal -->
    <main class="flex-1 pt-4 px-6 pb-6 overflow-auto flex flex-col items-center">
        <div class="max-w-screen-xl mx-auto w-full relative">
            <h1 class="text-center text-yellow-500 mt-0 mb-0 text-xl md:text-2xl font-bold">
                Painel de planejamento de produções
            </h1>
            <a href="ordem_envase.php"
               class="absolute right-0 top-0 bg-yellow-500 hover:bg-yellow-600 text-slate-900 font-semibold px-4 py-2 rounded shadow transition-all text-sm"
               style="margin-top: 4px; margin-right: 2px;">
                Ir para Ordem de Envase
            </a>
            <?php if ($atualizacao_recente): ?>
                <div class="text-center text-xs text-gray-400 mb-2">
                    Atualizado em: <span class="font-semibold">
                        <?php echo date('d/m/Y H:i', strtotime($atualizacao_recente)); ?>
                    </span>
                </div>
            <?php endif; ?>
            <hr class="divider_yellow mt-4 mb-4">
            
            <!-- Informações gerais -->
            <div class="mb-4 text-center">
                <p class="text-gray-300">
                    PROJEÇÃO PARA 60 DIAS - Ordenado por estoque mínimo (maior → menor)
                </p>
            </div>
            
            <!-- Container dos gráficos -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-6xl mx-auto">
                <?php foreach ($cervejas_permitidas as $cerveja): ?>
                    <?php 
                    $dados_cerveja = isset($dados_por_cerveja[$cerveja]) ? $dados_por_cerveja[$cerveja] : [];
                    $tem_dados = !empty($dados_cerveja);
                    $estoque_minimo = getEstoqueMinimo($cerveja, $medias_vendas_pcp);
                    $grafico_id = 'chart_' . preg_replace('/[^a-zA-Z0-9]/', '_', $cerveja);
                    $classe_container = $tem_dados ? 'grafico-container' : 'grafico-container sem-dados';
                    ?>
                    <div class="<?php echo $classe_container; ?>">
                        <h3 class="text-center <?php echo $tem_dados ? 'text-gray-800' : 'text-red-800'; ?> font-bold mb-3">
                            <?php echo htmlspecialchars($cerveja); ?><br>
                            <small class="<?php echo $tem_dados ? 'text-gray-600' : 'text-red-600'; ?>">
                                <?php if ($tem_dados): ?>
                                    Estoque mínimo para 15 dias: <span class="font-bold"><?php echo number_format($estoque_minimo, 2); ?> litros</span>
                                <?php else: ?>
                                    ⚠️ Sem dados disponíveis
                                    <br>Estoque mínimo para 15 dias: <?php echo number_format($estoque_minimo, 2); ?> litros
                                <?php endif; ?>
                            </small>
                        </h3>
                        <div class="chart-wrapper">
                            <?php if ($tem_dados): ?>
                                <canvas id="<?php echo $grafico_id; ?>"></canvas>
                            <?php else: ?>
                                <div class="flex items-center justify-center h-full">
                                    <p class="text-red-600 text-center">
                                        Dados não encontrados<br>
                                        <small>Verifique o nome da cerveja na base de dados</small>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

   
    <script>
    const diasTanquePorCerveja = {
        'ACID BLOOD': 30,
        'COLAB FERMI IPA DAY': 21,
        'COLAB TURBINADA 2XIPA': 30,
        'CRIATURA DO PANTANO': 21,
        'DOG SAVE THE BEER': 21,
        'HECTOR 5 ROUNDS': 21,
        'HERMES E RENATO': 21,
        'JEAN LE BLANC': 21,
        'JUICY JILL': 30,
        'LATIDO DA SEREIA': 21,
        'MARK THE SHADOW': 21,
        'OLD BUT GOLD': 30,
        'PINA A VIVA': 30,
        'RANNA RIDER': 21,
        'WELT PILSEN': 21,
        'WELT RED ALE': 21,
        'WILLIE THE BITTER': 21,
        'XP 086 WEST COAST': 21,
        'XP 094 HOP HEADS': 21,
        'XP GOLDEN FLAMINGO': 21,
        'ZE DO MORRO': 21
    };
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const dadosCervejas = {
            <?php foreach ($dados_por_cerveja as $cerveja => $dados_cerveja): ?>
                <?php 
                $estoque_minimo = getEstoqueMinimo($cerveja, $medias_vendas_pcp);
                $grafico_id = 'chart_' . preg_replace('/[^a-zA-Z0-9]/', '_', $cerveja);
                ?>
                '<?php echo $grafico_id; ?>': {
                    cerveja: '<?php echo addslashes($cerveja); ?>',
                    estoqueMinimo: <?php echo (float)$estoque_minimo; ?>,
                    dados: <?php echo json_encode($dados_cerveja); ?>
                },
            <?php endforeach; ?>
        };
        
        Object.keys(dadosCervejas).forEach(function(chartId) {
            const cervejaInfo = dadosCervejas[chartId];
            const ctx = document.getElementById(chartId);
            if (!ctx) return;

            const datas = [];
            const estoqueAcumulado = [];
            const projecaoAgendada = [];
            const projecaoAtrasada = [];
            const estoqueMinimo = [];
            
            cervejaInfo.dados.forEach(function(linha) {
                const dataFormatada = linha.DATA.split('T')[0];
                const partesData = dataFormatada.split('-');
                const dataExibicao = `${partesData[2]}/${partesData[1]}`;
                
                datas.push(dataExibicao);
                estoqueAcumulado.push(parseFloat(linha.ESTOQUE_ACUMULADO) || 0);
                projecaoAgendada.push(parseFloat(linha.PROJECAO_COM_E_SEM_TANQUE) || 0);
                projecaoAtrasada.push(parseFloat(linha.PROJECAO_COM_PRODUCAO_ATRASADA) || 0);
                estoqueMinimo.push(cervejaInfo.estoqueMinimo);
            });
            
            // Descobre quantos dias de tanque para esta cerveja
            const diasTanque = diasTanquePorCerveja[cervejaInfo.cerveja.toUpperCase()] || 21;
            const faixaFinalIndex = Math.min(diasTanque - 1, datas.length - 1);
            let limiteLabel = datas[faixaFinalIndex];
            if (!datas.includes(limiteLabel)) {
                limiteLabel = datas[datas.length - 1];
            }

            // Ponto preto no limite de reação
            const pontosLimite = [];
            if (faixaFinalIndex >= 0 && faixaFinalIndex < datas.length) {
                pontosLimite.push({
                    x: limiteLabel,
                    y: estoqueAcumulado[faixaFinalIndex]
                });
            }

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: datas,
                    datasets: [
                        {
                            label: 'Produção Agendada',
                            data: projecaoAgendada,
                            borderColor: '#7F1D1D',
                            backgroundColor: 'rgba(127, 29, 29, 0.15)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.1,
                            pointRadius: 0,
                            order: 2
                        },
                        {
                            label: 'Produção Atrasada',
                            data: projecaoAtrasada,
                            borderColor: '#6B7280',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            fill: false,
                            tension: 0.1,
                            pointRadius: 0,
                            order: 3
                        },
                        {
                            label: 'Estoque mínimo para 15 dias',
                            data: estoqueMinimo,
                            borderColor: '#000000',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            borderDash: [10, 5],
                            fill: false,
                            pointRadius: 0,
                            tension: 0,
                            order: 4
                        },
                        {
                            label: 'Projeção (Estoque)',
                            data: estoqueAcumulado,
                            borderColor: '#EAB308',
                            backgroundColor: 'rgba(234, 179, 8, 0.4)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.1,
                            pointRadius: 0,
                            order: 1
                        },
                        // Ponto preto no limite de reação
                        {
                            label: 'Limite reação',
                            data: [
                                { x: limiteLabel, y: 0 },
                                { x: limiteLabel, y: Math.max(...estoqueAcumulado, ...projecaoAgendada, ...projecaoAtrasada, ...estoqueMinimo) * 1.0 }
                            ],
                            borderColor: 'red',
                            borderWidth: 3,
                            borderDash: [4, 4], // pontilhado
                            pointRadius: 0,
                            fill: false,
                            showLine: true,
                            type: 'line',
                            order: 10
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            type: 'category',
                            title: {
                                display: true,
                                text: 'Data (60 dias)',
                                font: { size: 12 }
                            },
                            ticks: {
                                maxTicksLimit: 12,
                                font: { size: 10 }
                            },
                            min: 0
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Litros',
                                font: { size: 12 }
                            },
                            beginAtZero: true,
                            min: 0,
                            ticks: {
                                font: { size: 10 }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                font: { size: 9 },
                                padding: 8
                            }
                        },
                        annotation: {
                            annotations: {
                                tempoReacao: {
                                    type: 'box',
                                    xMin: datas[0],
                                    xMax: limiteLabel,
                                    yMin: 0,
                                    yMax: 'max',
                                    backgroundColor: 'rgba(234, 179, 8, 0.10)',
                                    borderWidth: 0,
                                    label: {
                                        display: true,
                                        content: 'Tempo de reação',
                                        position: 'start',
                                        color: '#eab308',
                                        font: { size: 10 }
                                    }
                                }
                                // Removido limiteReacao
                            }
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    }
                }
            });
        });
    });
    </script>
</body>

</html>
