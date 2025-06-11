<?php
/* modules/Cobranca/cobrancas.php
   CLIENTE (+) → PEDIDOS (+) → PARCELAS
   Ordenação: maior “Dias Vencidos” em todos os níveis
   Detalhes em DIV flutuante centralizada com comentários
-------------------------------------------------------------------- */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors',   1);

require_once $_SERVER['DOCUMENT_ROOT'].'/auth.php';
session_start();
if (empty($_SESSION['usuario_id'])) {
    header('Location:/login.php');
    exit;
}
$usuario = $_SESSION['usuario_nome'] ?? 'Usuário'; // Adicionado para a saudação

/* ---------- carrega dados da view ---------------------------------- */
require_once $_SERVER['DOCUMENT_ROOT'].'/db_config.php';
require_once __DIR__ . '/../../sidebar.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

$rows = [];
if (!$conn->connect_error) {
    $sql = "SELECT  ID_CLIENTE, CLIENTE, VENDEDOR,
                     NUMERO_PEDIDO, NUMERO_PARCELA,
                     TOTAL_COM_JUROS AS VALOR_VENCIDO,
                     DIAS_VENCIDOS,
                     DATE_FORMAT(DATA_VENCIMENTO,'%Y-%m-%d') AS DATA_VENCIMENTO
            FROM vw_cobrancas_vencidas
            ORDER BY CLIENTE, NUMERO_PEDIDO, NUMERO_PARCELA";
    if ($rs = $conn->query($sql)) {
        $rows = $rs->fetch_all(MYSQLI_ASSOC);
        $rs->free();
    } else {
        error_log('SQL falhou em cobrancas.php: ' . $conn->error);
    }
    $conn->close();
} else {
    error_log('Falha de conexão: ' . $conn->connect_error);
}

/* ---------- monta árvore CLIENTE → PEDIDO → PARCELAS --------------- */
$tree = [];
foreach ($rows as $r) {
    $cid = $r['ID_CLIENTE'] ?: $r['CLIENTE'];
    $pid = $r['NUMERO_PEDIDO'];

    $tree[$cid] ??= [
        'cliente'    => $r['CLIENTE'],
        'valor'      => 0,
        'dias'       => 0,
        'venc'       => $r['DATA_VENCIMENTO'],
        'vendedores' => [],
        'pedidos'    => []
    ];

    if (!isset($tree[$cid]['pedidos'][$pid])) {
        $tree[$cid]['pedidos'][$pid] = [
            'num'      => $pid,
            'valor'    => 0,
            'dias'     => 0,
            'venc'     => $r['DATA_VENCIMENTO'],
            'parcelas' => []
        ];
    }

    $tree[$cid]['valor'] += $r['VALOR_VENCIDO'];
    $tree[$cid]['dias']   = max($tree[$cid]['dias'], $r['DIAS_VENCIDOS']);
    $tree[$cid]['venc']   = min($tree[$cid]['venc'], $r['DATA_VENCIMENTO']);
    $tree[$cid]['vendedores'][$r['VENDEDOR']] = true;

    $ped =& $tree[$cid]['pedidos'][$pid];
    $ped['valor'] += $r['VALOR_VENCIDO'];
    $ped['dias']   = max($ped['dias'], $r['DIAS_VENCIDOS']);
    $ped['venc']   = min($ped['venc'], $r['DATA_VENCIMENTO']);

    $ped['parcelas'][] = [
        'parcela' => $r['NUMERO_PARCELA'],
        'valor'   => $r['VALOR_VENCIDO'],
        'dias'    => $r['DIAS_VENCIDOS'],
        'venc'    => $r['DATA_VENCIMENTO']
    ];
}

foreach ($tree as &$cli) {
    $cli['vendedor'] = count($cli['vendedores']) === 1
                     ? array_key_first($cli['vendedores'])
                     : 'VÁRIOS';
    unset($cli['vendedores']);

    $cli['pedidos'] = array_values($cli['pedidos']);
    usort($cli['pedidos'], fn($a, $b) => $b['dias'] <=> $a['dias']);
    foreach ($cli['pedidos'] as &$p) {
        usort($p['parcelas'], fn($x, $y) => $y['dias'] <=> $x['dias']);
    }
    unset($p);
}
unset($cli);
uasort($tree, fn($a, $b) => $b['dias'] <=> $a['dias']);

$json_output = json_encode($tree, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK);

$total_geral_em_aberto = 0;
foreach ($tree as $cliente_data) {
    $total_geral_em_aberto += $cliente_data['valor'];
}

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('JSON Encode Error: ' . json_last_error_msg());
    $json_output = json_encode(['error' => 'Falha ao gerar dados']);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Cobranças em Aberto</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/v/dt/dt-2.3.1/datatables.min.css"/>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/v/dt/dt-2.3.1/datatables.min.js"></script>

<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>
<!-- Custom CSS -->
<link href="/assets/css/style.css" rel="stylesheet">

<style>
    /* body { font-family: Inter, Arial, sans-serif; margin: 1rem; background: #111; color: #eee; } */ /* Tailwind cuidará disso */
    .dashboard-grid { display: flex; gap: 1rem; }
    .panel { background: #1a1a1a; padding: 1rem; border-radius: 8px; flex: 1; }
    table.dataTable { width: 100% !important; border-collapse: collapse; font-size: .85em; }
    table.dataTable th, table.dataTable td { padding: 4px 8px; border: 1px solid #333; }
    table.dataTable thead th { background: #2a2a2a; color: #facc15; } /* Alterado para amarelo (Tailwind yellow-400) */
    table.dataTable tbody tr:hover { background: #2c2c2c; cursor: pointer; }
    td.valor { font-weight: bold; text-align: right; }
    td.dias { text-align: center; /* font-weight será aplicado por JS ou CSS específico */ }

    /* overlay e floating detail */
    #overlay {
        position: fixed; top: 0; left: 0;
        width: 100%; height: 100%; background: rgba(0,0,0,0.5);
        z-index: 999; display: none;
    }
    #detail-float {
        position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
        background: #1a1a1a; padding: 1.5rem; border-radius: 8px;
        z-index: 1000; width: 90%; max-width: 750px; /* Aumentado o max-width */
        max-height: 80vh; overflow-y: auto; display: none;
    }
    #detail-float .close-btn {
        position: absolute; top: 10px; right: 10px;
        background: #444; color: #fff; border: none;
        border-radius: 4px; width: 24px; height: 24px;
        cursor: pointer;
    }
    .detail-header { margin-bottom: 1rem; }
    .detail-header h3 { margin: 0 0 .5rem; color: #00aaff; }
    .header-info {
        display: flex; justify-content: space-between;
        font-size: .95em; margin-bottom: 1rem;
    }
    .header-info span { flex: 1; text-align: center; }
    .detalhe-pedido { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px dashed #444; }
    .detalhe-pedido:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .detalhe-pedido h5 { margin: 5px 0; color: #77c7ff; }
    .info-linha { display: flex; justify-content: space-between; padding: 4px 0; font-size: .95em; }
    .info-linha span { flex: 1; text-align: right; }
    .parcelas-lista { margin-left: 20px; margin-top: 8px; border-left: 2px solid #555; padding-left: 10px; }
    .parcelas-lista .info-linha { background: #222; padding: 4px 6px; border-radius: 3px; margin-bottom: 4px; }
    /* Estilo para dias atrasados nas parcelas, incluindo semi-bold */
    .parcelas-lista .info-linha span.dias.atrasado {
        /* background-color: #cc0000; */ /* Removido fundo vermelho */
        color: #fff;
        font-weight: 600; /* Semi-bold */
    }

    /* comentários */
    #comentarios-historico {
        max-height: 160px; /* Increased height */
        overflow-y: auto;
        border: 1px solid #444;
        padding: 8px;
        margin-bottom: 10px;
        font-size: .9em;
        background: #222;
        border-radius: 4px;
    }
    .comment-item {
        background: #2a2a2a;
        border: 1px solid #383838;
        border-radius: 4px;
        padding: 8px 10px;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .comment-item:last-child {
        margin-bottom: 0;
    }
    .comment-content { /* Wrapper for text part */
        flex-grow: 1;
        margin-right: 8px; /* Space before delete button */
        word-break: break-word;
    }
    .comment-content small { /* Timestamp */
        color: #77c7ff;
        margin-right: 6px;
    }
    .comment-content strong { /* User */
        color: #ccc;
    }
    .comment-content span { /* Comment text */
        color: #eee;
    }
    .delete-comment-btn {
        background: #e53e3e; /* Tailwind red-600 like */
        color: white;
        border: none;
        border-radius: 4px;
        padding: 4px 8px;
        cursor: pointer;
        font-size: 0.9em;
        line-height: 1.2;
        flex-shrink: 0;
        align-self: center;
    }
    .delete-comment-btn:hover { background: #c53030; } /* Tailwind red-700 like */
    textarea#comentario-cliente-txt { width: calc(100% - 90px); /* Adjusted for button width + margin */ height: 3.5rem; background: #2d2d2d; color: #eee; border: 1px solid #444; padding: 8px; box-sizing: border-box; margin-right: 8px; border-radius: 4px; }
    button#salvar-comentario-btn { background: #38a169; /* Tailwind green-600 like */ border: none; color: #fff; padding: 8px 15px; cursor: pointer; border-radius: 4px; font-weight: bold; }
    button#salvar-comentario-btn:hover { background: #2f855a; } /* Tailwind green-700 like */
</style>
</head>
<body>
<body class="bg-gray-900 text-gray-100 flex min-h-screen">

 
  <main class="flex-1 bg-gray-900 p-6 relative">
    <!-- Saudação adicionada -->
    <header class="mb-8">
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
    <div class="dashboard-grid">
        <div class="panel" id="panel-clientes">
            <div class="flex justify-between items-center mb-4"> 
                <h2 class="text-2xl font-bold text-yellow-400">PAINEL DE COBRANÇA</h2> 
                <a href="/modules/Cobranca/cobrancas_detalhes.php" 
                   class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg text-sm shadow-md transition duration-150 ease-in-out">
                    Ver Detalhamento
                </a>
            </div>

            <!-- Caixa "Total Geral em Aberto" menor, à esquerda e abaixo do título -->
            <div class="flex justify-start gap-4 mb-6">
                <div id="panel-total-geral-interno" class="bg-gray-800 p-2 rounded-md" style="width: 200px; max-width: 100%;"> 
                    <h2 class="text-base font-bold text-yellow-400 mb-1 text-center">Valor total em aberto</h2> 
                    <p class="text-lg font-bold text-red-400 text-center"> 
                        R$ <?= number_format($total_geral_em_aberto, 2, ',', '.') ?> 
                    </p>
                </div>

                <!-- Caixa para informações de contagem de clientes - estilizada e posicionada ao lado -->
                <div id="panel-clientes-info-custom"
                     class="bg-gray-800 p-2 rounded-md" 
                     style="width: 200px; max-width: 100%;">
                    <h2 class="text-base font-bold text-yellow-400 mb-1 text-center">Clientes com débito</h2>
                    <p id="clientes-info-text-content" class="text-lg font-bold text-gray-300 text-center">
                        {/* O número será injetado pelo JavaScript */}
                    </p>
                </div>
            </div>

            <table id="tbl-cli" class="display compact stripe">
                <thead>
                    <tr>
                        <th>CLIENTE</th><th>VENDEDOR</th><th>VALOR (R$)</th>
                        <th>DIAS</th><th>VENC.</th>
                    </tr>
                </thead>
                <tbody></tbody>
        </div>
    </div>
 
    <!-- overlay e floating detail -->
            </table>
        </div>
    </div>

    <!-- overlay e floating detail -->
    <div id="overlay"></div>
    <div id="detail-float">
        <button class="close-btn" data-close>✕</button>
        <div class="detail-header">
            <h3 id="detail-cliente-nome"></h3>
            <div class="header-info">
                <span id="detail-valor-total"></span>
                <span id="detail-vendedor"></span>
            </div>
        </div>
        <h4>Pedidos e Parcelas</h4>
        <div id="pedidos-e-parcelas-container"></div>
        <hr style="border-color:#444;margin:1rem 0;"/>
        <div id="comentarios-secao">
            <h4>Comentários</h4>
            <div id="comentarios-historico"><em>(sem comentários)</em></div>
            <div style="display:flex;align-items:flex-start;">
                <textarea id="comentario-cliente-txt" placeholder="Novo comentário…"></textarea>
                <button id="salvar-comentario-btn">Salvar</button> <!-- .salvar class can be removed if ID is used for styling -->
            </div>
        </div>
    </div>
</main>
<script>
const dados = <?= $json_output ?>;
const money = v => v.toLocaleString('pt-BR',{minimumFractionDigits:2});
let tbl, sel = null;

$(function() {
    if (dados && dados.error) {
        console.error("Erro ao carregar dados para a tabela:", dados.error);
        $('#panel-clientes').html(`<p class="text-red-500 font-bold p-4">Erro ao carregar dados da visão: ${dados.error}. Verifique o log do servidor.</p>`);
        // Disable functionality that depends on 'dados'
        return; 
    }

    // monta DataTable
    const mapped = Object.entries(dados).flatMap(([k,c]) => {
        const num = Number(k), id = num || k;
        if (!id) return [];
        return [{ id_cli: id, cliente: c.cliente, vendedor: c.vendedor, valor: c.valor, dias: c.dias, venc: c.venc }];
    });

    tbl = $('#tbl-cli').DataTable({
        data: mapped,
        columns: [
            { data: 'cliente' },
            { data: 'vendedor' },
            { data: 'valor',   className: 'valor', render: d => money(d) }, // Formata como moeda
            { 
                data: 'dias',
                className: 'dias', // Para alinhamento e outros estilos base se necessário
                createdCell: function(td, cellData, rowData, row, col) {
                    const diasVencidos = parseInt(cellData);
                    if (diasVencidos > 0) {
                        const color = getDaysOverdueColor(diasVencidos);
                        $(td).css({'background-color': color, 'color': '#fff', 'font-weight': '600'});
                    }
                }
            },
            { data: 'venc' }
        ],
        order: [[3,'desc']], paging:false, searching:false, info:true,
        language:{ /* search:'Buscar:', */ info:'_TOTAL_ clientes', infoEmpty:'Nenhum', infoFiltered:'(de _MAX_)' }, // Removida a tradução de 'search'
        rowId:'id_cli'
    });

    // Manipula a exibição da contagem de clientes em uma caixa customizada
    const dtInfoOriginal = $('#tbl-cli_info'); // ID padrão do elemento de info do DataTables
    const customInfoBox = $('#panel-clientes-info-custom');
    const customInfoTextContent = $('#clientes-info-text-content'); // Novo elemento para o texto

    if (dtInfoOriginal.length && customInfoBox.length && customInfoTextContent.length) {
        // Função para atualizar o conteúdo da caixa customizada
        const updateCustomInfoText = () => {
            customInfoTextContent.text(dtInfoOriginal.text().replace(/[^0-9]/g, '') || '0'); // Extrai apenas números ou mostra 0
        };

        // Atualização inicial
        updateCustomInfoText();

        // Oculta o elemento de info original do DataTables
        dtInfoOriginal.hide();

        // Garante que a caixa customizada seja atualizada e a original permaneça oculta em redesenhos da tabela
        tbl.on('draw.dt', function () {
            dtInfoOriginal.hide(); // Reafirma que está oculto
            updateCustomInfoText();
        });
    }

    // abre detail flutuante
    $('#tbl-cli tbody').on('click','tr',function(){
        const r = tbl.row(this).data();
        if(!r||!r.id_cli) return;
        sel = { id:r.id_cli, nome:r.cliente };
        $('#detail-cliente-nome').text(`Cliente: ${r.cliente}`);
        $('#detail-valor-total').text(`Total R$ ${money(dados[sel.id].valor)}`);
        $('#detail-vendedor').text(`Vendedor: ${dados[sel.id].vendedor}`);
        renderPedidos(sel.id);
        carregaHistorico();
        $('#overlay,#detail-float').show();
    });

    // fecha ao clicar em X ou overlay
    $('[data-close],#overlay').on('click',()=>$('#overlay,#detail-float').hide());

    // salvar comentário
    $('#salvar-comentario-btn').on('click', async ()=>{
        if(!sel) return;
        const txt = $('#comentario-cliente-txt').val().trim();
        if(!txt) { alert('Comentário vazio'); return; }
        try {
            const resp = await $.post(
                '/modules/Cobranca/salvar_coment.php',
                { id_cliente: sel.id, cliente: sel.nome, comentario: txt },
                null, 'json'
            );
            if(resp.status==='OK') {
                $('#comentario-cliente-txt').val('');
                carregaHistorico();
            } else {
                alert(resp.error||'Falha ao salvar');
            }
        } catch(e) {
            console.error("Erro ao salvar comentário (AJAX):", e); // Log detalhado do erro AJAX
            let errorMsg = 'Erro de comunicação ao tentar salvar o comentário.';
            if (e.responseJSON && e.responseJSON.error) {
                errorMsg = e.responseJSON.error;
                if (e.responseJSON.detail) {
                    errorMsg += ` (Detalhe: ${e.responseJSON.detail})`;
                }
            } else if (e.responseText && e.status >= 400) { // Considerar e.status para erros HTTP
                // Se não for JSON, mas houver texto na resposta (ex: erro fatal do PHP)
                errorMsg += ` Resposta do servidor (HTTP ${e.status}): ${e.responseText.substring(0, 200)}`; // Limita o tamanho
            } else if (e.statusText) {
                errorMsg += ` Status: ${e.statusText}`;
            }
            alert(errorMsg);
        }
    });

    // Event listener para DELETAR comentário (usando delegação de evento)
    $('#comentarios-historico').on('click', '.delete-comment-btn', async function() {
        if (!sel) {
            alert('Nenhum cliente selecionado.');
            return;
        }

        const commentItem = $(this).closest('.comment-item');
        console.log('Comment item HTML:', commentItem.length ? commentItem[0].outerHTML : 'Comment item not found'); // Log o HTML do item
        const commentId = commentItem.data('comment-id');
        console.log('Extracted commentId from data attribute:', commentId, '(type: ' + typeof commentId + ')'); // Log o ID lido e seu tipo

        // Verificação mais precisa: permite 0 como ID válido para ser enviado, mas não null/undefined/string vazia
        if (commentId === null || commentId === undefined || commentId === '') {
            alert('ID do comentário não encontrado.');
            console.error('commentId is null, undefined, or empty string.');
            return;
        }

        if (!confirm('Tem certeza que deseja excluir este comentário?')) {
            return;
        }

        try {
            const resp = await $.post(
                '/modules/Cobranca/excluir_comentario.php',
                { comment_id: commentId }, // Enviando o ID do comentário
                null, 'json'
            );
            if (resp.status === 'OK') {
                carregaHistorico(); // Recarrega o histórico para refletir a exclusão
            } else {
                // Este bloco será alcançado se o servidor responder com 2xx mas com um JSON indicando erro
                alert(resp.error || 'Falha ao excluir o comentário.');
            }
        } catch (e) {
            console.error("Erro ao excluir comentário:", e);
            let errorMessage = 'Erro de comunicação ao tentar excluir o comentário.';
            if (e.responseJSON && e.responseJSON.error) {
                // Se o servidor enviou um JSON de erro (comum com 4xx/5xx)
                errorMessage = e.responseJSON.error;
            } else if (e.statusText && e.status) {
                // Se houver um statusText do jqXHR (ex: "Not Found", "Internal Server Error")
                errorMessage = `Erro ${e.status}: ${e.statusText}`;
            }
            alert(errorMessage);
        }
    });
});

function getDaysOverdueColor(days) {
    // 'days' é o número de dias vencidos, garantido como >= 1 pelo chamador (createdCell).

    // Define após quantos dias de atraso a cor estará na metade do caminho para o vermelho total.
    // Um valor menor (ex: 30) fará com que a cor fique vermelha mais rapidamente.
    // Um valor maior (ex: 90) tornará o degradê mais gradual.
    const halfwayIntensityDays = 60; 

    // Cores RGB
    // Começa com vermelho mais claro para poucos dias de atraso
    const startColor = { r: 229, g: 62, b: 62 };  // Vermelho mais claro (ex: #E53E3E)
    // E vai para um vermelho bem escuro para muitos dias de atraso
    const endColor   = { r: 153, g: 27,  b: 27 };  // Vermelho bem escuro (ex: #991B1B)

    // Calcula a proporção (ratio) para a interpolação da cor.
    // - Se 'days' = halfwayIntensityDays, ratio = 0.5 (cor intermediária).
    // - Conforme 'days' aumenta, 'ratio' se aproxima de 1 (cor final).
    // - Conforme 'days' se aproxima de 1 (mínimo), 'ratio' se aproxima de 0 (cor inicial).
    // A fórmula days / (days + K) garante que o ratio esteja entre 0 e 1 (exclusivo de 1).
    const ratio = days / (days + halfwayIntensityDays);

    // Interpola os componentes RGB
    const r = Math.round(startColor.r * (1 - ratio) + endColor.r * ratio);
    const g = Math.round(startColor.g * (1 - ratio) + endColor.g * ratio);
    const b = Math.round(startColor.b * (1 - ratio) + endColor.b * ratio);

    // Converte RGB para Hexadecimal
    return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1).toUpperCase();
}

function renderPedidos(id) {
    const c = dados[id]||{}, cont = $('#pedidos-e-parcelas-container').empty();
    if(!c.pedidos?.length) return cont.html('<p>Nenhum pedido em aberto.</p>');
    c.pedidos.forEach(p=>{
        const div = $(`
            <div class="detalhe-pedido">
                <h5>Pedido #${p.num} - R$ ${money(p.valor)}</h5>
                <div class="parcelas-lista"></div>
            </div>`);
        p.parcelas.forEach(pa=>{
            div.find('.parcelas-lista').append(`
                <div class="info-linha">
                    <span>Parcela ${pa.parcela}</span>
                    <span>R$ ${money(pa.valor)}</span>
                    <span class="dias ${pa.dias>0?'atrasado':''}">${pa.dias}d</span>
                    <span>${pa.venc}</span>
                </div>`);
        });
        cont.append(div);
    });
}

async function carregaHistorico() {
    const box = $('#comentarios-historico').html('<em>carregando…</em>');
    if (!sel || !sel.nome) {
        box.html('<em>Selecione um cliente para ver os comentários.</em>');
        return;
    }
    try {
        const d = await $.getJSON('/modules/Cobranca/comentarios_cliente.php', { cliente: sel.nome });
        if(d.error) return box.html(`<em>${d.error}</em>`);
        if(!d.length) return box.html('<em>(sem comentários)</em>');
        
        box.empty(); // Clear previous content (e.g., "carregando...")
        d.forEach(c => {
            const commentTextDiv = $('<div></div>').addClass('comment-content');
            
            commentTextDiv.append(
                $('<small></small>').text(`[${c.datahora_fmt}] `) // Add space after timestamp
            );
            commentTextDiv.append(
                $('<strong></strong>').text(`${c.usuario || 'Sistema'}: `) // Add space after user
            );
            commentTextDiv.append(
                $('<span></span>').text(c.comentario) // Safely sets text, escaping HTML
            );

            const deleteButton = $('<button class="delete-comment-btn" title="Excluir comentário">&times;</button>');

            const commentItemDiv = $('<div></div>')
                .addClass('comment-item')
                .attr('data-comment-id', c.id)
                .append(commentTextDiv)
                .append(deleteButton);
            box.append(commentItemDiv);
        });
    } catch(e) {
        console.error("Erro ao carregar histórico:", e);
        box.html('<em>Erro ao carregar histórico.</em>');
    }
}
</script>
</body>
</html>