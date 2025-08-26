<?php
// Inicia sessão e carrega config/db
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config/db.php';

// Classe para conectar via API REST do Supabase (igual ordem_envase)
class SupabaseApiClient {
    public $url;
    public $key;

    public function __construct() {
        $this->url = 'https://gybhszcefuxsdhpvxbnk.supabase.co/rest/v1/';
        $this->key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imd5YmhzemNlZnV4c2RocHZ4Ym5rIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTMwMTYwOTYsImV4cCI6MjA2ODU5MjA5Nn0.i3sOjUvpsimEvtO0uDGFNK92IZjcy1VIva_KEBdlZI8';
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
        $data = json_decode($response, true);
        $dados_normalizados = [];
        foreach ($data as $row) {
            $linha_normalizada = [];
            foreach ($row as $chave => $valor) {
                $linha_normalizada[strtoupper($chave)] = $valor;
            }
            $dados_normalizados[] = $linha_normalizada;
        }
        return $dados_normalizados;
    }
}

// Busca dados da view vw_bicos_por_cliente
try {
    $supabase = new SupabaseApiClient();
    $dados_bicos = $supabase->query(
        'vw_bico_por_cliente',
        '"CLIENTE","TOTAL_BICOS","DIAS_ALOCADO"'
    );
    $dados_volume = $supabase->query(
        'vw_volume_comprado_por_pedido_cliente',
        '"CLIENTE","CERVEJA","TOTAL_VOLUME"'
    );
    $dados_media = $supabase->query(
        'vw_media_mensal_clientes',
        '"CLIENTE","CERVEJA","MEDIA_3_MESES","MEDIA_6_MESES","MEDIA_9_MESES"'
    );
} catch (Exception $e) {
    die("❌ Erro de conexão via API: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chopeiras por Cliente</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex flex-col sm:flex-row">

    <?php require_once __DIR__ . '/../../sidebar.php'; ?>

    <!-- Conteúdo Principal -->
    <main class="flex-1 pt-4 px-6 pb-6 overflow-auto">
        <div class="max-w-screen-xl mx-auto w-full">
            <h1 class="text-center text-yellow-500 mt-0 mb-6 text-xl md:text-2xl font-bold">
                Análise de consumo - Cliente com chopeiras fixas
            </h1>
            <div class="flex justify-center gap-4 mb-6">
                <a href="ordem_envase.php" class="bg-yellow-500 hover:bg-yellow-600 text-slate-900 font-semibold px-4 py-2 rounded transition">Ir para Ordem de Envase</a>
                <a href="pcp_prod.php" class="bg-yellow-500 hover:bg-yellow-600 text-slate-900 font-semibold px-4 py-2 rounded transition">Ir para Planejamento de Produção</a>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full bg-slate-800 text-sm">
                    <thead>
                        <tr>
                            <th class="px-2 py-1 text-left text-yellow-400 font-semibold w-64 max-w-sm truncate">Cliente</th>
                            <th class="px-2 py-1 text-left text-yellow-400 font-semibold">Total de Bicos</th>
                            <th class="px-2 py-1 text-left text-yellow-400 font-semibold">Média Mensal (L)</th>
                            <th class="px-2 py-1 text-left text-yellow-400 font-semibold">Média por Bico/Mês (L)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        
                        $volume_por_cliente = [];
                        $media_total_por_cliente = [];
                        foreach ($dados_volume as $linha) {
                            $cliente = $linha['CLIENTE'];
                            $cerveja = $linha['CERVEJA'];
                            $media_mensal = round(($linha['TOTAL_VOLUME'] / 90) * 30, 1);
                            $volume_por_cliente[$cliente][] = [
                                'CERVEJA' => $cerveja,
                                'MEDIA_MENSAL' => $media_mensal
                            ];
                            if (!isset($media_total_por_cliente[$cliente])) $media_total_por_cliente[$cliente] = 0;
                            $media_total_por_cliente[$cliente] += $media_mensal;
                        }

                        
                        usort($dados_bicos, function($a, $b) use ($media_total_por_cliente) {
                            
                            if ($b['TOTAL_BICOS'] != $a['TOTAL_BICOS']) {
                                return $b['TOTAL_BICOS'] - $a['TOTAL_BICOS'];
                            }
                            
                            $mediaA = $media_total_por_cliente[$a['CLIENTE']] ?? 0;
                            $mediaB = $media_total_por_cliente[$b['CLIENTE']] ?? 0;
                            return $mediaB <=> $mediaA;
                        });

                        $soma_bicos = 0;
                        $soma_media = 0;
                        $idx = 0;
                        foreach ($dados_bicos as $linha):
                            
                            if (in_array($linha['CLIENTE'], ['BASTARDS SC', 'BASTARDS GO'])) {
                                continue;
                            }
                            if ($linha['DIAS_ALOCADO'] > 15):
                                $soma_bicos += $linha['TOTAL_BICOS'];
                                $cliente_id = 'cliente_' . $idx;
                                $media_cliente = $media_total_por_cliente[$linha['CLIENTE']] ?? 0;
                                $soma_media += $media_cliente;
                        ?>
                            <tr class="border-b border-slate-700 hover:bg-slate-700 transition">
                                <td class="px-2 py-1 w-64 max-w-sm truncate align-top flex items-center gap-2">
                                    <!-- Botão de expansão -->
                                    <button type="button" onclick="toggleSub('<?php echo $cliente_id; ?>')" class="text-yellow-400 hover:underline focus:outline-none">
                                        <span id="icon_<?php echo $cliente_id; ?>">&#9654;</span>
                                    </button>
                                    <?php echo htmlspecialchars($linha['CLIENTE']); ?>
                                    <!-- Novo ícone para tooltip -->
                                    <button type="button" onclick="toggleTooltip('<?php echo $cliente_id; ?>')" class="ml-2 text-blue-400 hover:text-blue-300 focus:outline-none" title="Ver análise avançada">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="inline h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/>
                                            <path stroke="currentColor" stroke-width="2" d="M12 16v-4M12 8h.01"/>
                                        </svg>
                                    </button>
                                    <!-- Tooltip customizado -->
                                    <div id="tooltip_<?php echo $cliente_id; ?>" class="hidden absolute z-50 bg-slate-800 border border-yellow-400 rounded shadow-lg p-4 mt-2 w-max min-w-[320px]">
                                        <button type="button" onclick="toggleTooltip('<?php echo $cliente_id; ?>')" class="absolute top-2 right-2 text-red-400 hover:text-red-300 focus:outline-none" title="Fechar">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="inline h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2"/>
                                                <line x1="6" y1="18" x2="18" y2="6" stroke="currentColor" stroke-width="2"/>
                                            </svg>
                                        </button>
                                        <table class="text-xs w-full mt-4">
                                            <thead>
                                                <tr>
                                                    <th class="text-yellow-400 px-2 py-1">Cerveja</th>
                                                    <th class="text-yellow-400 px-2 py-1">3 meses</th>
                                                    <th class="text-yellow-400 px-2 py-1">6 meses</th>
                                                    <th class="text-yellow-400 px-2 py-1">9 meses</th>
                                                    <th class="text-yellow-400 px-2 py-1">Δ 3→6</th>
                                                    <th class="text-yellow-400 px-2 py-1">Δ 6→9</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            // Filtra e ordena as cervejas do cliente pelo maior consumo nos últimos 3 meses
                                            $medias_cliente = array_filter($dados_media, function($media) use ($linha) {
                                                return $media['CLIENTE'] === $linha['CLIENTE'];
                                            });
                                            usort($medias_cliente, function($a, $b) {
                                                return $b['MEDIA_3_MESES'] <=> $a['MEDIA_3_MESES'];
                                            });
                                            foreach ($medias_cliente as $media) {
                                                $m3 = $media['MEDIA_3_MESES'];
                                                $m6 = $media['MEDIA_6_MESES'];
                                                $m9 = $media['MEDIA_9_MESES'];
                                                $delta36 = $m6 > 0 ? round((($m3 - $m6) / $m6) * 100, 1) : 0;
                                                $delta69 = $m9 > 0 ? round((($m6 - $m9) / $m9) * 100, 1) : 0;
                                                ?>
                                                <tr>
                                                    <td class="px-2 py-1"><?php echo htmlspecialchars($media['CERVEJA']); ?></td>
                                                    <td class="px-2 py-1 text-yellow-200"><?php echo htmlspecialchars($m3); ?></td>
                                                    <td class="px-2 py-1 text-yellow-200"><?php echo htmlspecialchars($m6); ?></td>
                                                    <td class="px-2 py-1 text-yellow-200"><?php echo htmlspecialchars($m9); ?></td>
                                                    <td class="px-2 py-1 <?php echo $delta36 >= 0 ? 'text-green-400' : 'text-red-400'; ?>">
                                                        <?php echo ($delta36 >= 0 ? '+' : '') . $delta36 . '%'; ?>
                                                    </td>
                                                    <td class="px-2 py-1 <?php echo $delta69 >= 0 ? 'text-green-400' : 'text-red-400'; ?>">
                                                        <?php echo ($delta69 >= 0 ? '+' : '') . $delta69 . '%'; ?>
                                                    </td>
                                                </tr>
                                                <?php
                                            }
                                            ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                                <td class="px-2 py-1 align-top"><?php echo htmlspecialchars($linha['TOTAL_BICOS']); ?></td>
                                <td class="px-2 py-1 align-top">
                                    <span id="media_<?php echo $cliente_id; ?>" data-total="<?php echo htmlspecialchars(round($media_cliente)); ?>">
                                        <?php echo htmlspecialchars(round($media_cliente)); ?>
                                    </span>
                                </td>
                                <td class="px-2 py-1 align-top">
                                    <?php
                                    $media_por_bico = $linha['TOTAL_BICOS'] > 0 ? round($media_cliente / $linha['TOTAL_BICOS']) : 0;
                                    echo htmlspecialchars($media_por_bico);
                                    ?>
                                </td>
                            </tr>
                            <?php
                            // Subnível: uma linha para cada cerveja, formato matriz
                            if (!empty($volume_por_cliente[$linha['CLIENTE']])) {
                                // Ordena as cervejas do cliente pelo maior MEDIA_MENSAL
                                usort($volume_por_cliente[$linha['CLIENTE']], function($a, $b) {
                                    return $b['MEDIA_MENSAL'] <=> $a['MEDIA_MENSAL'];
                                });
                                $primeira = true;
                                foreach ($volume_por_cliente[$linha['CLIENTE']] as $cerveja) {
                                    ?>
                                    <tr id="<?php echo $cliente_id; ?>" class="hidden bg-slate-900 subrow_<?php echo $cliente_id; ?>">
                                        <td class="px-2 py-1 w-64 max-w-sm truncate"><?php echo $primeira ? '<span class="text-slate-400">Cerveja</span>' : ''; ?></td>
                                        <td class="px-2 py-1"><?php echo htmlspecialchars($cerveja['CERVEJA']); ?></td>
                                        <td class="px-2 py-1 text-yellow-200"><?php echo htmlspecialchars(round($cerveja['MEDIA_MENSAL'])); ?> L</td>
                                        <td class="px-2 py-1 text-yellow-200">
                                            <?php
                                            $media_bico_cerveja = $linha['TOTAL_BICOS'] > 0 ? round($cerveja['MEDIA_MENSAL'] / $linha['TOTAL_BICOS']) : 0;
                                            echo htmlspecialchars($media_bico_cerveja);
                                            ?>
                                        </td>
                                    </tr>
                                    <?php
                                    $primeira = false;
                                }
                            } else {
                                ?>
                                <tr id="<?php echo $cliente_id; ?>" class="hidden bg-slate-900 subrow_<?php echo $cliente_id; ?>">
                                    <td colspan="3" class="px-2 py-1 text-slate-400">Sem dados de volume</td>
                                </tr>
                                <?php
                            }
                            ?>
                        <?php
                                $idx++;
                            endif;
                        endforeach;
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
<script>
function toggleSub(id) {
    const rows = document.querySelectorAll('.subrow_' + id);
    const icon = document.getElementById('icon_' + id);
    const mediaCell = document.getElementById('media_' + id);
    if (rows.length && rows[0].classList.contains('hidden')) {
        rows.forEach(row => row.classList.remove('hidden'));
        icon.innerHTML = '&#9660;';
        mediaCell.innerHTML = '--';
    } else {
        rows.forEach(row => row.classList.add('hidden'));
        icon.innerHTML = '&#9654;';
        mediaCell.innerHTML = mediaCell.getAttribute('data-total');
    }
}

function toggleTooltip(id) {
    const tooltip = document.getElementById('tooltip_' + id);
    if (tooltip.classList.contains('hidden')) {
        document.querySelectorAll('[id^="tooltip_"]').forEach(t => t.classList.add('hidden'));
        tooltip.classList.remove('hidden');
    } else {
        tooltip.classList.add('hidden');
    }
}

window.addEventListener('DOMContentLoaded', () => {
    <?php for ($i = 0; $i < $idx; $i++): ?>
        document.getElementById('media_cliente_<?php echo $i; ?>')?.setAttribute('data-total', document.getElementById('media_cliente_<?php echo $i; ?>')?.innerText);
    <?php endfor; ?>
});
</script>