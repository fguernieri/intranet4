<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', 0); // Em produção, erros devem ir para log
ini_set('log_errors',   1);

require_once $_SERVER['DOCUMENT_ROOT'].'/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/vendedor_alias.php';
session_start();
if (empty($_SESSION['usuario_id'])) {
    header('Location:/login.php');
    exit;
}
$usuario = $_SESSION['usuario_nome'] ?? 'Usuário'; // Adicionado para a saudação

require_once $_SERVER['DOCUMENT_ROOT'].'/db_config.php';
require_once __DIR__ . '/../../sidebar.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
$aliasData = getVendedorAliasMap($pdo);
$aliasMap  = $aliasData['alias_to_nome'];

$total_por_vendedor = [];
$total_por_periodo = [
    '1_6'   => ['label' => '1 a 6 dias', 'total' => 0.0, 'count' => 0],
    '7_14'  => ['label' => '7 a 14 dias', 'total' => 0.0, 'count' => 0],
    '15_30' => ['label' => '15 a 30 dias', 'total' => 0.0, 'count' => 0],
    '31_90' => ['label' => '31 a 90 dias', 'total' => 0.0, 'count' => 0],
    'gt_90' => ['label' => 'Acima de 90 dias', 'total' => 0.0, 'count' => 0],
];
$grand_total_aberto = 0.0;
$vendedor_cliente_data = []; // Nova estrutura para detalhes do cliente por vendedor
$database_error_message = null;

if (!$conn->connect_error) {
    $sql = "SELECT
                CLIENTE, 
                VENDEDOR, 
                   TOTAL_COM_JUROS AS VALOR_VENCIDO, 
                   DIAS_VENCIDOS 
            FROM vw_cobrancas_vencidas";
    
    if ($rs = $conn->query($sql)) {
        while ($row = $rs->fetch_assoc()) {
            $vendedor = $row['VENDEDOR'] ?: '(Não Especificado)';
            $vendedor = resolveVendedorNome($vendedor, $aliasMap);
            $cliente = $row['CLIENTE'] ?: '(Cliente Não Especificado)'; // Assumindo que CLIENTE existe na view
            $valor_vencido = (float)$row['VALOR_VENCIDO'];
            $dias_vencidos = (int)$row['DIAS_VENCIDOS'];


            // Total por Vendedor
            $total_por_vendedor[$vendedor] = ($total_por_vendedor[$vendedor] ?? 0.0) + $valor_vencido;

            // Total por Período
            if ($dias_vencidos >= 1 && $dias_vencidos <= 6) {
                $total_por_periodo['1_6']['total'] += $valor_vencido;
                $total_por_periodo['1_6']['count']++;
            } elseif ($dias_vencidos >= 7 && $dias_vencidos <= 14) {
                $total_por_periodo['7_14']['total'] += $valor_vencido;
                $total_por_periodo['7_14']['count']++;
            } elseif ($dias_vencidos >= 15 && $dias_vencidos <= 30) {
                $total_por_periodo['15_30']['total'] += $valor_vencido;
                $total_por_periodo['15_30']['count']++;
            } elseif ($dias_vencidos >= 31 && $dias_vencidos <= 90) {
                $total_por_periodo['31_90']['total'] += $valor_vencido;
                $total_por_periodo['31_90']['count']++;
            } elseif ($dias_vencidos > 90) {
                $total_por_periodo['gt_90']['total'] += $valor_vencido;
                $total_por_periodo['gt_90']['count']++;
            }
            $grand_total_aberto += $valor_vencido;

            // Dados de cliente por vendedor para a popup
            $vendedor_cliente_data[$vendedor][$cliente] =
                ($vendedor_cliente_data[$vendedor][$cliente] ?? 0.0) + $valor_vencido;
        }
        $rs->free();
        if (!empty($total_por_vendedor)) {
            arsort($total_por_vendedor); // Ordena vendedores por maior valor apenas se não estiver vazio
        }
    } else {
        error_log('SQL falhou em cobrancas_detalhes.php: ' . $conn->error);
        $database_error_message = "Erro ao consultar os dados de cobrança.";
    }
    $conn->close();
} else {
    error_log('Falha de conexão em cobrancas_detalhes.php: ' . $conn->connect_error);
    $database_error_message = "Não foi possível conectar ao banco de dados.";
}

function format_currency(float $value): string {
    return 'R$ ' . number_format($value, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Detalhamento de Cobranças</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="/assets/css/style.css" rel="stylesheet">
    <style>
        /* body { Tailwind já cuida do bg-gray-900 text-gray-100 } */
        .panel { background: #1a1a1a; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .panel-title { font-size: 1.5em; font-weight: bold; color: #facc15; /* Tailwind yellow-400 */ margin-bottom: 1rem; text-align: center; }
        .detail-table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
        .detail-table th, .detail-table td { padding: 0.75rem 1rem; border: 1px solid #333; text-align: left; }
        .detail-table thead th { background: #2a2a2a; color: #facc15; /* Tailwind yellow-400 */ }
        .detail-table tbody tr:nth-child(even) { background: #222; }
        .detail-table td.currency { text-align: right; font-weight: bold; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.75rem; } /* Renomeado e ajustado minmax */
        .summary-item-card { background: #2a2a2a; padding: 0.75rem; border-radius: 8px; text-align: center; display: flex; flex-direction: column; justify-content: space-between; height: 100%;}
        .summary-item-card .label { font-size: 0.8rem; color: #cbd5e1; margin-bottom: 0.25rem; }
        .summary-item-card .value { font-size: 1.1rem; font-weight: bold; margin-bottom: 0.1rem; }
        .summary-item-card .count { font-size: 0.7rem; color: #94a3b8; }

        /* Estilos para o ícone de informação e linha de detalhe (movidos do segundo bloco style) */
        .info-icon {
            margin-left: 8px;
            color: #facc15; /* Amarelo, como os títulos */
            cursor: pointer;
            font-weight: bold;
        }
        .info-icon:hover {
            color: #fff; /* Branco ao passar o mouse */
        }
        .info-icon {
            margin-left: 8px;
            color: #facc15; /* Amarelo, como os títulos */
            cursor: pointer;
            font-weight: bold;
        }
        .info-icon:hover {
            color: #fff; /* Branco ao passar o mouse */
        }
        .detail-row td {
            padding: 0 !important; /* Remove padding da célula da linha de detalhe */
            background-color: #252525 !important; /* Fundo um pouco diferente para a sub-tabela */
            border-top: 2px solid #facc15 !important; /* Borda amarela no topo */
        }

        /* Estilos para a popup de detalhes do vendedor */
        #vendedor-detail-overlay {
            position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); /* Fundo escurecido */
            z-index: 1009; /* Abaixo da popup, acima do resto */
            display: none; /* Inicialmente oculto */
        }
        #vendedor-detail-popup {
            position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            background: #1f2937; /* Tailwind bg-gray-800 ou similar */
            color: #f3f4f6; /* Tailwind text-gray-100 ou similar */
            padding: 1.5rem; /* 24px */
            border-radius: 8px; /* Tailwind rounded-lg */
            z-index: 1010; /* Acima do overlay */
            width: 90%;
            max-width: 600px; /* Largura máxima da popup */
            max-height: 85vh; /* Altura máxima, com scroll */
            overflow-y: auto;
            display: none; /* Inicialmente oculto */
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }
        #vendedor-detail-popup .popup-title {
            font-size: 1.25rem; /* Tailwind text-xl */
            font-weight: bold;
            color: #facc15; /* Tailwind yellow-400 */
            margin-bottom: 1rem;
            text-align: center;
        }
        #vendedor-detail-popup .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #4b5563; /* Tailwind gray-600 */
            color: white;
            border: none;
            border-radius: 50%; /* Botão redondo */
            width: 28px; /* Tamanho do botão */
            height: 28px;
            font-size: 1.2rem; /* Tamanho do 'X' */
            line-height: 28px; /* Centralizar 'X' */
            text-align: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        #vendedor-detail-popup .close-btn:hover {
            background: #374151; /* Tailwind gray-700 */
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 flex min-h-screen">
    <!-- SIDEBAR -->

    <main class="flex-1 bg-gray-900 p-6">
        <!-- Saudação e Data Adicionadas -->
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
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold text-yellow-400">Detalhamento de Cobranças</h1>
            <a href="/modules/Cobranca/cobrancas.php" 
               class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-lg text-sm shadow-md transition duration-150 ease-in-out">
                &larr; Voltar ao Painel Principal
            </a>
        </div>

        <?php if ($database_error_message): ?>
            <div class="panel bg-red-700 text-white">
                <h2 class="panel-title text-white">Erro no Sistema</h2>
                <p class="text-center"><?= htmlspecialchars($database_error_message) ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$database_error_message): // Só mostra os painéis de dados se não houver erro de banco ?>
            <!-- Painel Único para Todos os Resumos -->
            <div class="panel mb-6">
                <h2 class="panel-title mb-3">Resumo de cobranças em aberto</h2>
                <div class="summary-grid">
                    <!-- Card Total Geral em Aberto (com destaque) -->
                    <div class="summary-item-card total-geral-card">
                        <div class="label">Total Geral em Aberto</div>
                        <div class="value text-red-400"><?= format_currency($grand_total_aberto) ?></div>
                        <div class="count">&nbsp;</div>
                    </div>

                    <!-- Cards por Período de Vencimento -->
                    <?php foreach ($total_por_periodo as $periodo_key => $data): ?>
                    <?php if ($data['total'] > 0 || $data['count'] > 0): // Opcional: mostrar apenas se houver dados ?>
                    <div class="summary-item-card">
                        <div class="label"><?= htmlspecialchars($data['label']) ?></div>
                        <div class="value text-red-400"><?= format_currency($data['total']) ?></div>
                        <div class="count">(<?= $data['count'] ?> ocorrências)</div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- Painel Tabela Total em Aberto por Vendedor (Abaixo dos resumos) -->
            <div class="panel">
                <h2 class="panel-title">Total em aberto por vendedor</h2>
                <?php if (!empty($total_por_vendedor)): ?>
                <table class="detail-table" id="table-total-por-vendedor">
                    <thead>
                        <tr>
                            <th>Vendedor</th>
                            <th class="currency">Total em Aberto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($total_por_vendedor as $vendedor => $total): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($vendedor) ?>
                                <span class="info-icon" data-vendedor="<?= htmlspecialchars($vendedor) ?>" title="Ver clientes de <?= htmlspecialchars($vendedor) ?>">&#9432;</span>
                            </td>
                            <td class="currency"><?= format_currency($total) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-center text-gray-400">Nenhum dado de vendedor encontrado.</p>
                <?php endif; ?>
            </div>
        <?php endif; // Fim do if (!$database_error_message) ?>
    </main>

    <!-- Popup para Detalhes de Clientes por Vendedor -->
    <div id="vendedor-detail-overlay"></div>
    <div id="vendedor-detail-popup">
        <button class="close-btn" id="close-vendedor-popup">&times;</button>
        <h3 class="popup-title" id="vendedor-popup-title">Clientes do Vendedor</h3>
        <div id="vendedor-popup-content">
            {/* Tabela de clientes será inserida aqui */}
        </div>
    </div>

<script>
    // Passa os dados de cliente por vendedor para o JavaScript
    const vendedorClienteData = <?= json_encode($vendedor_cliente_data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
    console.log('Dados brutos de vendedorClienteData:', vendedorClienteData);

    document.addEventListener('DOMContentLoaded', function() {
        const tableVendedores = document.getElementById('table-total-por-vendedor');
        const overlay = document.getElementById('vendedor-detail-overlay');
        const popup = document.getElementById('vendedor-detail-popup');
        const closePopupButton = document.getElementById('close-vendedor-popup');
        const popupTitle = document.getElementById('vendedor-popup-title');
        const popupContent = document.getElementById('vendedor-popup-content');
        const mainContentArea = document.querySelector('main'); // Para delegação de evento

        console.log('DOMContentLoaded disparado.');
        if (typeof vendedorClienteData !== 'object' || vendedorClienteData === null) {
            console.error('ERRO: vendedorClienteData não é um objeto válido:', vendedorClienteData);
            return; // Impede a execução se os dados base estiverem errados
        }

        console.log('Elementos da popup selecionados:', 
            { tableVendedores, overlay, popup, closePopupButton, popupTitle, popupContent }
        );

        if (!overlay || !popup || !closePopupButton || !popupTitle || !popupContent) {
            console.error('ERRO CRÍTICO: Um ou mais elementos da interface da popup não foram encontrados. Verifique os IDs no HTML.');
            return; // Impede a execução se a UI da popup estiver incompleta
        }

        if (!tableVendedores) {
            console.error('ERRO CRÍTICO: Tabela #table-total-por-vendedor não foi encontrada no DOM ao carregar a página.');
            return;
        }

        console.log('Adicionando event listener ao DOCUMENTO para delegação de cliques em .info-icon');
        // Usando delegação de eventos no documento para maior robustez
        document.addEventListener('click', function(event) {
            const iconElement = event.target.closest('.info-icon');
            // console.log('Document click. Target:', event.target, 'Found icon:', iconElement); // Log para qualquer clique

            if (iconElement && iconElement.dataset.vendedor) {
                // Verifica se o ícone clicado está dentro da nossa tabela de vendedores
                // Isso evita que ícones com a mesma classe em outros lugares da página acionem a popup
                if (tableVendedores.contains(iconElement)) {
                    console.log('Ícone válido clicado DENTRO da tabela de vendedores. Vendedor:', iconElement.dataset.vendedor);
                    const vendedorNome = iconElement.dataset.vendedor;
                    
                    if (!vendedorClienteData.hasOwnProperty(vendedorNome)) {
                        console.error('ERRO: Nenhum dado encontrado para o vendedor:', vendedorNome, 'em vendedorClienteData.');
                        popupContent.innerHTML = '<p class="text-center text-red-500">Dados não encontrados para este vendedor.</p>';
                        // Considerar mostrar a popup mesmo com erro para feedback
                    }
                    
                    const clientesDoVendedor = vendedorClienteData[vendedorNome] || {}; // Garante que é um objeto
                    console.log('Dados dos clientes para o vendedor:', clientesDoVendedor);

                    popupTitle.textContent = 'Clientes de: ' + vendedorNome;
                    let tableHtml = '<table class="detail-table"><thead><tr><th>Cliente</th><th class="currency">Total Aberto</th></tr></thead><tbody>';
                    if (Object.keys(clientesDoVendedor).length > 0) {
                        for (const [cliente, total] of Object.entries(clientesDoVendedor).sort(([,a],[,b]) => b-a)) { // Ordena por maior valor
                            tableHtml += `<tr><td>${cliente}</td><td class="currency">R$ ${parseFloat(total).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td></tr>`;
                        }
                    } else {
                        tableHtml += '<tr><td colspan="2" class="text-center">Nenhum cliente encontrado para este vendedor.</td></tr>';
                    }
                    tableHtml += '</tbody></table>';
                    popupContent.innerHTML = tableHtml;

                    console.log('Preparado para mostrar popup e overlay.');
                    overlay.style.display = 'block';
                    popup.style.display = 'block';
                } else {
                     // console.log('Ícone clicado, mas não está dentro da tabela de vendedores esperada.');
                }
            } else {
                // console.log('Clique não foi em um .info-icon com data-vendedor.');
            }
        });
            
        function closePopup() {
            console.log('Função closePopup chamada.');
            overlay.style.display = 'none';
            popup.style.display = 'none';
        }

        if (closePopupButton) closePopupButton.addEventListener('click', closePopup);
        if (overlay) overlay.addEventListener('click', closePopup);
    });
</script>
</body>
</html>