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
    
    public function getOrdemEnvase() {
        try {
            $select = '"CERVEJA","DIFERENCA_INOX","ENVASE_NECESSARIO_INOX","ESTOQUE_ATUAL_INOX","DIFERENCA_PET","ENVASE_NECESSARIO_PET","ESTOQUE_ATUAL_PET"';
            $url = $this->url . 'vw_ordem_envase_barris?select=' . urlencode($select) . '&order="DIFERENCA_INOX".desc';

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
        } catch (Exception $e) {
            error_log("Erro ao buscar dados de ordem de envase: " . $e->getMessage());
            return false;
        }
    }
}

// Conecta via API do Supabase
try {
    $supabase = new SupabaseApiClient();
    $dados_ordem_envase = $supabase->getOrdemEnvase();
} catch (Exception $e) {
    die("❌ Erro de conexão via API: " . $e->getMessage());
}

if ($dados_ordem_envase === false) {
    die("❌ Erro ao obter dados de ordem de envase");
}

echo "<!-- Total de registros encontrados: " . count($dados_ordem_envase) . " -->\n";

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

// Filtra apenas as cervejas permitidas e DIFERENCA_INOX > 0
$dados_filtrados = array_filter($dados_ordem_envase, function($linha) use ($cervejas_permitidas) {
    return in_array($linha['CERVEJA'], $cervejas_permitidas) && ((float)$linha['DIFERENCA_INOX'] > 0);
});

// Ordena do maior para o menor DIFERENCA_INOX
usort($dados_filtrados, function($a, $b) {
    return ((float)$b['DIFERENCA_INOX']) <=> ((float)$a['DIFERENCA_INOX']);
});

// Busca a data/hora mais recente da tabela fatualizacoes
$atualizacao_recente = '';
try {
    $url_fatualizacoes = 'https://naigkvzwdboarvzcoebs.supabase.co/rest/v1/fatualizacoes?select=data_hora&order=data_hora.desc&limit=1';
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'apikey: ' . $supabase->key,
                'Authorization: Bearer ' . $supabase->key,
                'Content-Type: application/json'
            ]
        ]
    ]);
    $response = file_get_contents($url_fatualizacoes, false, $context);
    if ($response !== false) {
        $data = json_decode($response, true);
        if (!empty($data[0]['data_hora'])) {
            $atualizacao_recente = date('d/m/Y H:i:s', strtotime($data[0]['data_hora']));
        }
    }
} catch (Exception $e) {
    $atualizacao_recente = '';
}

// Busca dados das latas
$select_latas = '"CERVEJA","MEDIA_DIARIA","ESTOQUE_ATUAL"';
$url_latas = $supabase->url . 'vw_ordem_envase_latas?select=' . urlencode($select_latas);

$context_latas = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            'apikey: ' . $supabase->key,
            'Authorization: Bearer ' . $supabase->key,
            'Content-Type: application/json'
        ]
    ]
]);
$response_latas = file_get_contents($url_latas, false, $context_latas);
$dados_latas = [];
if ($response_latas !== false) {
    $data_latas = json_decode($response_latas, true);
    foreach ($data_latas as $row) {
        $cerveja = $row['CERVEJA'];
        $media_diaria = (float)$row['MEDIA_DIARIA'];
        $estoque_atual = (float)$row['ESTOQUE_ATUAL'];
        $estoque_ideal_45 = $media_diaria * 45;
        $ordem_envase = ($estoque_ideal_45 - $estoque_atual) < 0 ? 0 : ($estoque_ideal_45 - $estoque_atual);

        $dados_latas[] = [
            'CERVEJA' => $cerveja,
            'MEDIA_DIARIA' => $media_diaria,
            'ESTOQUE_ATUAL' => $estoque_atual,
            'ESTOQUE_IDEAL_45_DIAS' => $estoque_ideal_45,
            'ORDEM_DE_ENVASE' => $ordem_envase
        ];
    }
}
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
<header class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold">
        Bem-vindo, <?= htmlspecialchars($usuario); ?>
    </h1>
    <p class="text-gray-400 text-sm">
        <?php
          $hoje = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
          $fmt = new IntlDateFormatter(
            'pt_BR',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            'America/Sao_Paulo',
            IntlDateFormatter::GREGORIAN
          );
          echo $fmt->format($hoje);
        ?>
    </p>
</header>

            <h1 class="text-center text-yellow-500 mt-0 mb-0 text-xl md:text-2xl font-bold">
                Ordem de Envase
            </h1>
            <?php if ($atualizacao_recente): ?>
                <div class="text-center text-xs text-gray-400 mb-2">
                    Atualizado em: <span class="font-semibold"><?php echo $atualizacao_recente; ?></span>
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
                                    <th class="px-4 py-3 text-center text-xs">ORDEM DE ENVASE INOX</th>
                                    <th class="px-4 py-3 text-center text-xs">ESTOQUE MINIMO</th>
                                    <th class="px-4 py-3 text-center text-xs">ESTOQUE ATUAL</th>
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
                                            <td class="px-4 py-3 text-center valor-positivo">
                                                <?php echo number_format((float)$linha['DIFERENCA_INOX'], 2, ',', '.'); ?>
                                            </td>
                                            <td class="px-4 py-3 text-center text-gray-700">
                                                <?php echo number_format((float)$linha['ENVASE_NECESSARIO_INOX'], 2, ',', '.'); ?>
                                            </td>
                                            <td class="px-4 py-3 text-center text-gray-700">
                                                <?php echo number_format((float)$linha['ESTOQUE_ATUAL_INOX'], 2, ',', '.'); ?>
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
                                    <th class="px-4 py-3 text-center text-xs">ORDEM DE ENVASE PET</th>
                                    <th class="px-4 py-3 text-center text-xs">ESTOQUE MINIMO</th>
                                    <th class="px-4 py-3 text-center text-xs">ESTOQUE ATUAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Filtra e ordena para PET
                                $dados_filtrados_pet = array_filter($dados_ordem_envase, function($linha) use ($cervejas_permitidas) {
                                    return in_array($linha['CERVEJA'], $cervejas_permitidas) && ((float)$linha['DIFERENCA_PET'] > 0);
                                });
                                usort($dados_filtrados_pet, function($a, $b) {
                                    return ((float)$b['DIFERENCA_PET']) <=> ((float)$a['DIFERENCA_PET']);
                                });
                                ?>
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
                                            <td class="px-4 py-3 text-center valor-positivo">
                                                <?php echo number_format((float)$linha['DIFERENCA_PET'], 2, ',', '.'); ?>
                                            </td>
                                            <td class="px-4 py-3 text-center text-gray-700">
                                                <?php echo number_format((float)$linha['ENVASE_NECESSARIO_PET'], 2, ',', '.'); ?>
                                            </td>
                                            <td class="px-4 py-3 text-center text-gray-700">
                                                <?php echo number_format((float)$linha['ESTOQUE_ATUAL_PET'], 2, ',', '.'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TABELA LATAS DE LINHA -->
            <div class="table-container mt-8">
                <h2 class="text-lg md:text-xl font-bold text-yellow-600 mb-2 text-center">
                    ORDEM DE ENVASE - LATAS
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="table-header">
                                <th class="px-4 py-3 text-left text-xs">CERVEJA</th>
                                <th class="px-4 py-3 text-center text-xs">ORDEM DE ENVASE</th>
                                <th class="px-4 py-3 text-center text-xs">MEDIA DIARIA</th>
                                <th class="px-4 py-3 text-center text-xs">ESTOQUE ATUAL</th>
                                <th class="px-4 py-3 text-center text-xs">ESTOQUE IDEAL 45 DIAS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
$dados_latas_linha = array_filter($dados_latas, function($linha) use ($cervejas_permitidas) {
    return in_array($linha['CERVEJA'], $cervejas_permitidas);
});
usort($dados_latas_linha, function($a, $b) {
    return ((float)$b['ORDEM_DE_ENVASE']) <=> ((float)$a['ORDEM_DE_ENVASE']);
});
?>
<?php if (empty($dados_latas_linha)): ?>
    <tr class="table-row">
        <td colspan="5" class="px-4 py-6 text-center text-gray-600">
            Nenhuma cerveja encontrada para latas de linha
        </td>
    </tr>
<?php else: ?>
    <?php foreach ($dados_latas_linha as $linha): ?>
        <tr class="table-row">
            <td class="px-4 py-3 font-semibold text-gray-800">
                <?php echo htmlspecialchars($linha['CERVEJA']); ?>
            </td>
            <td class="px-4 py-3 text-center valor-positivo">
                <?php echo number_format($linha['ORDEM_DE_ENVASE'], 2, ',', '.'); ?>
            </td>
            <td class="px-4 py-3 text-center text-gray-700">
                <?php echo number_format($linha['MEDIA_DIARIA'], 2, ',', '.'); ?>
            </td>
            <td class="px-4 py-3 text-center text-gray-700">
                <?php echo number_format($linha['ESTOQUE_ATUAL'], 2, ',', '.'); ?>
            </td>
            <td class="px-4 py-3 text-center text-gray-700">
                <?php echo number_format($linha['ESTOQUE_IDEAL_45_DIAS'], 2, ',', '.'); ?>
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