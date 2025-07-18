<?php
// Inicia sessão e carrega config/db
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config/db.php';

// Classe para conectar via API REST do Supabase
class SupabaseApiClient {
    public $url;
    public $key;

    public function __construct() {
        $this->url = 'https://naigkvzwdboarvzcoebs.supabase.co/rest/v1/';
        $this->key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im5haWdrdnp3ZGJvYXJ2emNvZWJzIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTIwNzgxMzgsImV4cCI6MjA2NzY1NDEzOH0.0c2YJu8-HtK683L6KHA7w8AD9nebb8Y1pMAuiVLENco';
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

// Conecta via API do Supabase
try {
    $supabase = new SupabaseApiClient();

    // Barris INOX
    $dados_ordem_envase = $supabase->query(
        'vw_ordem_envase_barris',
        '"CERVEJA","DIFERENCA_INOX","ENVASE_NECESSARIO_INOX","ESTOQUE_ATUAL_INOX","DIFERENCA_PET","ENVASE_NECESSARIO_PET","ESTOQUE_ATUAL_PET"',
        '"DIFERENCA_INOX".desc'
    );

    // Barris PET
    $dados_ordem_envase_pet = $supabase->query(
        'vw_ordem_envase_barris',
        '"CERVEJA","DIFERENCA_PET","ENVASE_NECESSARIO_PET","ESTOQUE_ATUAL_PET"',
        '"DIFERENCA_PET".desc'
    );

    // Latas
    $dados_latas = $supabase->query(
        'vw_ordem_envase_latas',
        'CERVEJA,EMBALAGEM,SOMA_QUANTIDADE,MEDIA_DIARIA,ESTOQUE_ATUAL'
    );

    // Puxa a data/hora mais recente da tabela fatualizacoes
    $fatualizacoes = $supabase->query('fatualizacoes', 'data_hora', 'data_hora.desc');
    $atualizacao_recente = isset($fatualizacoes[0]['DATA_HORA']) ? $fatualizacoes[0]['DATA_HORA'] : null;

    
} catch (Exception $e) {
    die("❌ Erro de conexão via API: " . $e->getMessage());
}

// Filtragem e ordenação igual ao seu código atual...
$cervejas_permitidas = [
    'WELT PILSEN',
    'DOG SAVE THE BEER',
    'ZE DO MORRO',
    'HECTOR 5 ROUNDS',
    'HERMES E RENATO',
    'WILLIE THE BITTER',
    'JUICY JILL',
    'RANNA RIDER',
    'PINA A VIVA',
    'JEAN LE BLANC',
    'MARK THE SHADOW',
    'CRIATURA DO PANTANO',
    'XP 094 HOP HEADS',
    'WELT RED ALE'
];

$dados_filtrados = array_filter($dados_ordem_envase, function($linha) use ($cervejas_permitidas) {
    return in_array($linha['CERVEJA'], $cervejas_permitidas) && ((float)$linha['DIFERENCA_INOX'] > 0);
});
usort($dados_filtrados, function($a, $b) {
    return ((float)$b['DIFERENCA_INOX']) <=> ((float)$a['DIFERENCA_INOX']);
});

$dados_filtrados_pet = array_filter($dados_ordem_envase_pet, function($linha) use ($cervejas_permitidas) {
    return in_array($linha['CERVEJA'], $cervejas_permitidas) && ((float)$linha['DIFERENCA_PET'] > 0);
});
usort($dados_filtrados_pet, function($a, $b) {
    return ((float)$b['DIFERENCA_PET']) <=> ((float)$a['DIFERENCA_PET']);
});

$dados_latas_linha = array_filter($dados_latas, function($linha) use ($cervejas_permitidas) {
    return in_array($linha['CERVEJA'], $cervejas_permitidas);
});
usort($dados_latas_linha, function($a, $b) {
    return ((float)$b['MEDIA_DIARIA']) <=> ((float)$a['MEDIA_DIARIA']);
});

// Calcule os campos ANTES de ordenar e exibir
foreach ($dados_latas_linha as &$linha) {
    $linha['ESTOQUE_IDEAL_45_DIAS'] = $linha['MEDIA_DIARIA'] * 45;
    $linha['ORDEM_DE_ENVASE'] = $linha['ESTOQUE_IDEAL_45_DIAS'] - $linha['ESTOQUE_ATUAL'];
}
unset($linha);

// Agora sim, ordene pelo campo calculado
usort($dados_latas_linha, function($a, $b) {
    return ((float)$b['ORDEM_DE_ENVASE']) <=> ((float)$a['ORDEM_DE_ENVASE']);
});
$atualizacao_recente = $atualizacao_recente ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordem de Envase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
        }
        .divider_yellow {
            border-top: 2px solid #eab308;
        }
        .table-container {
            background: #f3f4f6;
            border-radius: 8px;
            padding: 16px;
            border: 2px solid #eab308;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .table-header {
            background: #eab308;
            color: #1f2937;
            font-weight: bold;
        }
        .table-row:nth-child(even) {
            background: #f9fafb;
        }
        .table-row:hover {
            background: #e5e7eb;
        }
        .valor-positivo {
            color: #16a34a;
            font-weight: bold;
        }
        .valor-negativo {
            color: #dc2626;
            font-weight: bold;
        }
        .valor-neutro {
            color: #6b7280;
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex flex-col sm:flex-row">

    <?php require_once __DIR__ . '/../../sidebar.php'; ?>

    <!-- Conteúdo Principal -->
    <main class="flex-1 pt-4 px-6 pb-6 overflow-auto">
        <div class="max-w-screen-xl mx-auto w-full">
            <!-- Bloco de Bem-vindo -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-2">
                <div></div>
                <a href="pcp_prod.php"
                   class="mt-2 md:mt-0 bg-yellow-500 hover:bg-yellow-600 text-slate-900 font-semibold px-4 py-2 rounded shadow transition-all text-sm"
                   style="margin-left: 8px;">
                    Ir para Painel de Planejamento de Produções - PCP
                </a>
            </div>
            <h1 class="text-center text-yellow-500 mt-0 mb-0 text-xl md:text-2xl font-bold">
                Ordem de Envase
            </h1>
            <!-- Data de atualização no topo -->
            <?php if ($atualizacao_recente): ?>
                <div class="w-full text-center text-xs text-gray-400 mb-2 mt-2">
                    Atualizado em: <span class="font-semibold">
                        <?php echo date('d/m/Y H:i', strtotime($atualizacao_recente)); ?>
                    </span>
                </div>
            <?php endif; ?>
            <hr class="divider_yellow mt-4 mb-4">

            <!-- TABELAS LADO A LADO -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- TABELA INOX -->
                <div class="table-container">
                    <h2 class="text-lg md:text-xl font-bold text-yellow-600 mb-2 text-center">
                        ORDEM DE ENVASE - BARRIS INOX
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="table-header">
                                    <th class="px-4 py-3 text-left text-xs">CERVEJA</th>
                                    <th class="px-4 py-3 text-center text-xs">ESTOQUE ATUAL</th>
                                    <th class="px-4 py-3 text-center text-xs">ESTOQUE MINIMO (7D)</th>
                                    <th class="px-4 py-3 text-center text-xs">ORDEM DE ENVASE</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dados_filtrados)): ?>
                                    <tr class="table-row">
                                        <td colspan="4" class="px-4 py-6 text-center text-gray-600">
                                            Nenhuma cerveja com ordem de envase &gt; 0
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($dados_filtrados as $linha): ?>
                                        <tr class="table-row">
                                            <td class="px-4 py-3 font-semibold text-gray-800">
                                                <?php echo htmlspecialchars($linha['CERVEJA']); ?>
                                            </td>
                                            <td class="px-4 py-3 text-center text-gray-700">
                                                <?php echo number_format((float)$linha['ESTOQUE_ATUAL_INOX'], 0, ',', '.'); ?>
                                            </td>
                                            <td class="px-4 py-3 text-center text-gray-700">
                                                <?php echo number_format((float)$linha['ENVASE_NECESSARIO_INOX'], 0, ',', '.'); ?>
                                            </td>
                                            <td class="px-4 py-3 text-center valor-positivo">
                                                <?php echo number_format((float)$linha['DIFERENCA_INOX'], 0, ',', '.'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TABELA PET -->
                <div class="table-container">
                    <h2 class="text-lg md:text-xl font-bold text-yellow-600 mb-2 text-center">
                        ORDEM DE ENVASE - BARRIS PET
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
    <tr class="table-header">
        <th class="px-4 py-3 text-left text-xs">CERVEJA</th>
        <th class="px-4 py-3 text-center text-xs">ESTOQUE ATUAL</th>
        <th class="px-4 py-3 text-center text-xs">ESTOQUE MINIMO (7D)</th>
        <th class="px-4 py-3 text-center text-xs">ORDEM DE ENVASE</th>
    </tr>
</thead>
<tbody>
    <?php if (empty($dados_filtrados_pet)): ?>
        <tr class="table-row">
            <td colspan="4" class="px-4 py-6 text-center text-gray-600">
                Nenhuma cerveja com ordem de envase &gt; 0
            </td>
        </tr>
    <?php else: ?>
        <?php foreach ($dados_filtrados_pet as $linha): ?>
            <tr class="table-row">
                <td class="px-4 py-3 font-semibold text-gray-800">
                    <?php echo htmlspecialchars($linha['CERVEJA']); ?>
                </td>
                <td class="px-4 py-3 text-center text-gray-700">
                    <?php echo number_format((float)$linha['ESTOQUE_ATUAL_PET'], 0, ',', '.'); ?>
                </td>
                <td class="px-4 py-3 text-center text-gray-700">
                    <?php echo number_format((float)$linha['ENVASE_NECESSARIO_PET'], 0, ',', '.'); ?>
                </td>
                <td class="px-4 py-3 text-center valor-positivo">
                    <?php echo number_format((float)$linha['DIFERENCA_PET'], 0, ',', '.'); ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TABELA LATAS DE LINHA COMPACTA -->
            <div class="table-container mt-8">
                <h2 class="text-lg font-bold text-yellow-600 mb-2 text-center">ORDEM DE ENVASE - LATAS</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-yellow-500 text-gray-900 font-bold">
                                <th class="px-1 py-1 text-left">CERVEJA</th>
                                <th class="px-1 py-1 text-center">ESTOQUE_ATUAL</th>
                                <th class="px-1 py-1 text-center">ESTOQUE_IDEAL_45_DIAS</th>
                                <th class="px-1 py-1 text-center">ORDEM_DE_ENVASE</th>
                                <th class="px-1 py-1 text-center">MEDIA_DIARIA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Calcule os campos ANTES de exibir
                            foreach ($dados_latas_linha as &$linha) {
                                $linha['ESTOQUE_IDEAL_45_DIAS'] = $linha['MEDIA_DIARIA'] * 45;
                                $linha['ORDEM_DE_ENVASE'] = $linha['ESTOQUE_IDEAL_45_DIAS'] - $linha['ESTOQUE_ATUAL'];
                            }
                            unset($linha);
                            // Ordene pelo campo calculado
                            usort($dados_latas_linha, function($a, $b) {
                                return ((float)$b['ORDEM_DE_ENVASE']) <=> ((float)$a['ORDEM_DE_ENVASE']);
                            });
                            ?>
                            <?php if (empty($dados_latas_linha)): ?>
                                <tr>
                                    <td colspan="5" class="px-1 py-2 text-center text-gray-600">Nenhuma cerveja encontrada</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dados_latas_linha as $linha): ?>
                                    <tr>
                                        <td class="px-1 py-1 font-semibold text-gray-800"><?php echo htmlspecialchars($linha['CERVEJA']); ?></td>
                                        <td class="px-1 py-1 text-center <?php echo ($linha['ESTOQUE_ATUAL'] < 200) ? 'valor-negativo' : 'text-gray-700'; ?>">
                                            <?php echo number_format($linha['ESTOQUE_ATUAL'], 0, ',', '.'); ?>
                                        </td>
                                        <td class="px-1 py-1 text-center text-gray-700">
                                            <?php echo number_format($linha['ESTOQUE_IDEAL_45_DIAS'], 0, ',', '.'); ?>
                                        </td>
                                        <td class="px-1 py-1 text-center valor-positivo">
                                            <?php echo number_format($linha['ORDEM_DE_ENVASE'], 0, ',', '.'); ?>
                                        </td>
                                        <td class="px-1 py-1 text-center text-gray-700">
                                            <?php echo number_format($linha['MEDIA_DIARIA'], 0, ',', '.'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>