<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurar fuso horário do Brasil
date_default_timezone_set('America/Sao_Paulo');

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
require_once __DIR__ . '/supabase_connection.php';

// Verificar autenticação
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

$usuario = $_SESSION['usuario_nome'] ?? '';

// Capturar filtro de período (formato YYYY/MM)
$periodo_selecionado = $_GET['periodo'] ?? '';

try {
    $supabase = new SupabaseConnection();
    $conexao_ok = $supabase->testConnection();
    $erro_conexao = null;
} catch (Exception $e) {
    $conexao_ok = false;
    $erro_conexao = $e->getMessage();
}

$periodos_disponiveis = [];
$dados_receita = [];
$dados_despesa = [];

if ($conexao_ok) {
    try {
        // Primeiro, buscar todos os períodos disponíveis na view
        $todos_dados = $supabase->select('freceitatap', [
            'select' => 'data_mes',
            'order' => 'data_mes.desc'
        ]);
        
        // Array para tradução dos meses para português
        $meses_pt = [
            'January' => 'Janeiro',
            'February' => 'Fevereiro', 
            'March' => 'Março',
            'April' => 'Abril',
            'May' => 'Maio',
            'June' => 'Junho',
            'July' => 'Julho',
            'August' => 'Agosto',
            'September' => 'Setembro',
            'October' => 'Outubro',
            'November' => 'Novembro',
            'December' => 'Dezembro'
        ];
        
        if ($todos_dados) {
            foreach ($todos_dados as $linha) {
                $data_mes = $linha['data_mes'];
                $periodo_formato = date('Y/m', strtotime($data_mes)); // 2025/01
                $mes_ingles = date('F', strtotime($data_mes)); // January
                $mes_portugues = $meses_pt[$mes_ingles] ?? $mes_ingles;
                $periodo_display = $periodo_formato . ' - ' . $mes_portugues; // 2025/01 - Janeiro
                
                if (!isset($periodos_disponiveis[$periodo_formato])) {
                    $periodos_disponiveis[$periodo_formato] = $periodo_display;
                }
            }
        }
        
        // Se nenhum período foi selecionado, selecionar automaticamente o mais recente
        if (empty($periodo_selecionado) && !empty($periodos_disponiveis)) {
            // Os períodos já vêm ordenados por data_mes.desc, então o primeiro é o mais recente
            $periodo_selecionado = array_keys($periodos_disponiveis)[0];
        }
        
        // Buscar dados se um período foi selecionado (automaticamente ou pelo usuário)
        if ($periodo_selecionado) {
            // Converter período selecionado (2025/01) para data (2025-01-01)
            $partes = explode('/', $periodo_selecionado);
            if (count($partes) == 2) {
                $ano = $partes[0];
                $mes = str_pad($partes[1], 2, '0', STR_PAD_LEFT);
                $data_filtro = $ano . '-' . $mes . '-01';
                
                // Buscar receitas
                $dados_receita = $supabase->select('freceitatap', [
                    'select' => '*',
                    'filters' => [
                        'data_mes' => 'eq.' . $data_filtro
                    ],
                    'order' => 'data_mes.desc'
                ]);
                
                // Buscar despesas (para TRIBUTOS)
                $dados_despesa = $supabase->select('fdespesastap', [
                    'select' => '*',
                    'filters' => [
                        'data_mes' => 'eq.' . $data_filtro
                    ],
                    'order' => 'data_mes.desc'
                ]);
            }
        }
    } catch (Exception $e) {
        $periodos_disponiveis = [];
        $dados_receita = [];
        $dados_despesa = [];
    }
}

require_once __DIR__ . '/../../sidebar.php';
?>

<div id="simulador-content" class="p-6 ml-4">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl text-blue-400">Simulador - Bar da Fabrica</h2>
        <div class="flex items-center gap-2">
            <a href="index.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded transition-colors flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar ao Menu
            </a>
            <!-- Dropdown para alternar para simulador WAB -->
            <div class="relative">
                <button id="simuladorMenuBtn" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded transition-colors">Selecionar Bar ▾</button>
                <div id="simuladorMenu" class="absolute right-0 mt-2 w-48 bg-white rounded shadow-lg hidden z-50">
                    <a href="simuladorwab.php" class="block px-4 py-2 text-sm text-gray-800 hover:bg-gray-100">We Are Bastards</a>
                    <a href="simuladorfabrica.php" class="block px-4 py-2 text-sm text-gray-800 hover:bg-gray-100">Fábrica</a>
                </div>
            </div>
            <script>
                document.addEventListener('click', function(e) {
                    const btn = document.getElementById('simuladorMenuBtn');
                    const menu = document.getElementById('simuladorMenu');
                    if (!btn || !menu) return;
                    if (btn.contains(e.target)) {
                        menu.classList.toggle('hidden');
                    } else if (!menu.contains(e.target)) {
                        menu.classList.add('hidden');
                    }
                });
            </script>
        </div>
    </div>
    
    <!-- Filtro de Período -->
    <div class="bg-gray-800 rounded-lg p-4 mb-6">
        <h3 class="text-lg text-gray-300 mb-3">Selecionar Período Base</h3>
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-64">
                <label class="block text-sm text-gray-400 mb-1">Período (Ano/Mês):</label>
                <select name="periodo" class="w-full px-3 py-2 bg-gray-700 text-gray-300 rounded border border-gray-600 focus:border-blue-400" required>
                    <option value="">Selecione um período...</option>
                    <?php foreach ($periodos_disponiveis as $valor => $display): ?>
                        <option value="<?= htmlspecialchars($valor) ?>" <?= $periodo_selecionado === $valor ? 'selected' : '' ?>>
                            <?= htmlspecialchars($display) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded transition-colors">
                    Carregar Base
                </button>
                <a href="?" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded transition-colors">
                    Limpar
                </a>
            </div>
        </form>
        
        <?php if ($periodo_selecionado): ?>
        <div class="mt-3 text-sm text-gray-400">
            📅 Base de dados: <?= htmlspecialchars($periodos_disponiveis[$periodo_selecionado] ?? $periodo_selecionado) ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($periodo_selecionado): ?>
        <?php if (!empty($dados_receita) || !empty($dados_despesa)): ?>
        <div class="bg-gray-800 rounded-lg p-4">
            <h3 class="text-lg text-gray-300 mb-3">
                Simulação Financeira (Base: <?= htmlspecialchars($periodos_disponiveis[$periodo_selecionado] ?? $periodo_selecionado) ?>)
            </h3>
            
            <!-- Botões de ação -->
            <div class="mb-4 flex gap-2">
                <button onclick="resetarSimulacao()" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded transition-colors text-sm">
                    🔄 Restaurar Valores Base
                </button>
                <button onclick="calcularPontoEquilibrio()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded transition-colors text-sm">
                    ⚖️ Calcular Ponto de Equilíbrio
                </button>
                <button onclick="abrirModalMetas()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded transition-colors text-sm">
                    🎯 Salvar Metas
                </button>
                <button onclick="abrirModalVerMetasTap()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded transition-colors text-sm">
                    👀 Ver Metas Salvas
                </button>
                <button onclick="salvarSimulacao()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded transition-colors text-sm">
                    💾 Salvar Simulação
                </button>
            </div>

            <script>
            // Abre modal dinâmico listando metas salvas e permitindo apontar meses
            function abrirModalVerMetasTap() {
                const periodo = encodeURIComponent('<?= $periodo_selecionado ?>');
                const url = '/modules/financeiro_bar/metas_api.php?action=fetch&sim=tap&periodo=' + periodo;
                console.debug('[metas] fetch start', { url });
                fetch(url)
                    .then(r => {
                        console.debug('[metas] fetch response status', r.status, r.statusText);
                        return r.json().catch(e => { console.error('[metas] invalid json', e); throw e; });
                    })
                    .then(data => {
                        console.debug('[metas] fetch data', data);
                        if (!data.ok) {
                            console.error('[metas] fetch returned not ok', data);
                            return alert('Erro ao buscar metas');
                        }
                        renderModalVerMetas('tap', data.metas);
                    }).catch(e => { console.error('[metas] fetch error', e); alert('Erro de rede (veja console)'); });
            }

            function renderModalVerMetas(sim, metas) {
                // Construir HTML do modal
                let html = '<div id="modalVerMetas" class="fixed inset-0 bg-black bg-opacity-50 z-60 flex items-center justify-center">'
                    + '<div class="bg-white rounded-lg p-4 max-w-3xl w-full mx-4">'
                    + '<h3 class="text-lg font-bold mb-2">Metas Salvas</h3>'
                    + '<div style="max-height:60vh;overflow:auto">'
                    + '<table class="w-full text-left border-collapse">'
                    + '<thead><tr><th class="p-2">Categoria</th><th class="p-2">Subcategoria</th><th class="p-2">Meta (R$)</th><th class="p-2">Meses</th><th class="p-2">Ações</th></tr></thead>'
                    + '<tbody>';
                metas.forEach(m => {
                    const assigned = Array.isArray(m.meses) ? m.meses : [];
                    // mostrar resumo e botão para abrir modal de seleção por ano/mês
                    const summary = (Array.isArray(assigned) && assigned.length>0) ? assigned.join(', ') : '—';
                    html += `<tr class="border-t"><td class="p-2">${escapeHtml(m.CATEGORIA)}</td><td class="p-2">${escapeHtml(m.SUBCATEGORIA)}</td><td class="p-2">${Number(m.META).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2})}</td><td class="p-2"><span id="meta-summary-${m.meta_key}">${escapeHtml(summary)}</span></td><td class="p-2"><button class="px-2 py-1 bg-indigo-600 text-white rounded" onclick="openMetaMonthsPicker('${m.meta_key}', ${JSON.stringify(assigned)})">Editar Meses</button></td></tr>`;
                });
                html += '</tbody></table></div>'
                    + '<div class="mt-3 text-right"><button onclick="closeModalVerMetas()" class="px-4 py-2 bg-gray-600 text-white rounded">Fechar</button></div>'
                    + '</div></div>';

                const wrapper = document.createElement('div');
                wrapper.innerHTML = html;
                document.body.appendChild(wrapper);
            }

            function closeModalVerMetas() {
                const m = document.getElementById('modalVerMetas');
                if (m) m.closest('#modalVerMetas')?.remove();
                // fallback: remove any element with id modalVerMetas
                const el = document.getElementById('modalVerMetas'); if (el) el.parentNode.removeChild(el);
            }

            function saveMetaMonths(sim, meta_key) {
                // coletar checkboxes para meta_key
                const checkboxes = Array.from(document.querySelectorAll(`input[data-meta="${meta_key}"][type="checkbox"]`));
                const meses = checkboxes.filter(c=>c.checked).map(c=>c.dataset.month);
                const payload = { sim: sim, meta_key: meta_key, meses: meses };
                console.debug('[metas] save request', payload);
                fetch('/modules/financeiro_bar/metas_api.php?action=save_months', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                }).then(r=>{
                    console.debug('[metas] save response status', r.status);
                    return r.json().catch(e=>{ console.error('[metas] save invalid json', e); throw e; });
                }).then(res=>{
                    console.debug('[metas] save response data', res);
                    if (res.ok) {
                        alert('Meses salvos');
                    } else {
                        alert('Erro ao salvar');
                    }
                }).catch(e=>{ console.error('[metas] save error', e); alert('Erro de rede (veja console)'); });
            }

            // Abre modal de seleção por ano/mes para uma meta específica
            function openMetaMonthsPicker(meta_key, assignedRaw) {
                console.debug('[metas] openMetaMonthsPicker', meta_key, assignedRaw);
                const assigned = Array.isArray(assignedRaw) ? assignedRaw.slice() : [];
                const currentYear = new Date().getFullYear();
                const years = [currentYear-1, currentYear, currentYear+1, currentYear+2];

                // Normalizar valores para formato YYYY-MM when possible
                const assignedSet = new Set();
                assigned.forEach(v => {
                    const s = String(v||'').trim();
                    if (!s) return;
                    if (/^\d{4}-\d{2}$/.test(s)) assignedSet.add(s);
                    else if (/^\d{1,2}$/.test(s)) assignedSet.add(currentYear + '-' + String(s).padStart(2,'0'));
                    else assignedSet.add(s);
                });

                let inner = '<div class="p-4 max-w-2xl w-full">';
                inner += `<h4 class="text-lg font-bold mb-2">Selecionar Meses para a Meta</h4>`;
                inner += '<div class="grid gap-4">';
                years.forEach(y => {
                    inner += `<div class="border rounded p-2"><div class="font-semibold mb-2">${y}</div><div class="flex flex-wrap">`;
                    for (let m=1;m<=12;m++) {
                        const key = `${y}-${String(m).padStart(2,'0')}`;
                        const checked = assignedSet.has(key) ? 'checked' : '';
                        inner += `<label style="width:80px;margin-right:6px"><input data-metapick="${meta_key}" data-value="${key}" type="checkbox" ${checked}/> ${m.toString().padStart(2,'0')}</label>`;
                    }
                    inner += '</div></div>';
                });
                inner += '</div>';
                inner += '<div class="mt-3 text-right"><button id="meta-save-btn" class="px-4 py-2 bg-blue-600 text-white rounded">Salvar</button> <button onclick="closeMetaMonthsPicker()" class="px-4 py-2 bg-gray-600 text-white rounded">Cancelar</button></div>';
                inner += '</div>';

                const modal = document.createElement('div');
                modal.id = 'modalMetaPicker';
                modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-70 flex items-center justify-center';
                modal.innerHTML = `<div class="bg-white rounded-lg p-4 max-w-3xl w-full mx-4">${inner}</div>`;
                document.body.appendChild(modal);

                document.getElementById('meta-save-btn').addEventListener('click', function(){
                    const checks = Array.from(document.querySelectorAll(`input[data-metapick="${meta_key}"]`));
                    const meses = checks.filter(c=>c.checked).map(c=>c.dataset.value);
                    console.debug('[metas] saving from picker', meta_key, meses);
                    const payload = { sim: 'tap', meta_key: meta_key, meses: meses };
                    fetch('/modules/financeiro_bar/metas_api.php?action=save_months', {
                        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
                    }).then(r=>r.json()).then(res=>{
                        console.debug('[metas] picker save response', res);
                        if (res.ok) {
                            // atualizar resumo
                            const span = document.getElementById('meta-summary-' + meta_key);
                            if (span) span.textContent = (res.meses || []).join(', ');
                            closeMetaMonthsPicker();
                            alert('Meses salvos');
                        } else {
                            alert('Erro ao salvar (veja console)');
                        }
                    }).catch(e=>{ console.error(e); alert('Erro de rede (veja console)'); });
                });
            }

            function closeMetaMonthsPicker() { const el = document.getElementById('modalMetaPicker'); if (el) el.parentNode.removeChild(el); }

            function escapeHtml(s) { return String(s||'').replace(/[&<>"']/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c];}); }
            </script>
            
            <div class="overflow-x-auto">
                <?php
                // Processar dados para criar estrutura hierárquica
                $total_geral = 0;
                $receitas_operacionais = [];
                $receitas_nao_operacionais = [];
                $tributos = [];
                $custo_variavel = [];
                $custo_fixo = [];
                $despesa_fixa = [];
                $despesa_venda = [];
                $investimento_interno = [];
                $saidas_nao_operacionais = [];
                
                // Função para obter META e PERCENTUAL da tabela fmetastap
                function obterMeta($categoria, $categoria_pai = null) {
                    global $supabase;

                    // Retorno padrão
                    $padrao = ['meta' => 0.0, 'percentual' => 0.0];

                    if (!$supabase) {
                        return $padrao;
                    }

                    $categoria_upper = strtoupper(trim($categoria));

                    try {
                        $resultado = null;

                        // Caso 1: Buscar subcategoria com categoria pai
                        if ($categoria_pai) {
                            $categoria_pai_upper = strtoupper(trim($categoria_pai));

                            $resultado = $supabase->select('fmetastap', [
                                'select' => 'META,PERCENTUAL',
                                'filters' => [
                                    'CATEGORIA' => "eq.$categoria_pai_upper",
                                    'SUBCATEGORIA' => "eq.$categoria_upper"
                                ],
                                'limit' => 1
                            ]);
                        }
                        // Caso 2: Buscar categoria principal (sem categoria pai)
                        else {
                            // Primeiro tenta buscar como categoria principal
                            $resultado = $supabase->select('fmetastap', [
                                'select' => 'META,PERCENTUAL',
                                'filters' => [
                                    'CATEGORIA' => "eq.$categoria_upper"
                                ],
                                'limit' => 1
                            ]);

                            // Se não encontrou, tenta buscar como subcategoria
                            if (empty($resultado)) {
                                $resultado = $supabase->select('fmetastap', [
                                    'select' => 'META,PERCENTUAL',
                                    'filters' => [
                                        'SUBCATEGORIA' => "eq.$categoria_upper"
                                    ],
                                    'limit' => 1
                                ]);
                            }
                        }

                        if (!empty($resultado) && is_array($resultado) && isset($resultado[0])) {
                            $row = $resultado[0];
                            $meta = isset($row['META']) && is_numeric($row['META']) ? floatval($row['META']) : 0.0;
                            $pct = isset($row['PERCENTUAL']) && is_numeric($row['PERCENTUAL']) ? floatval($row['PERCENTUAL']) : 0.0;
                            return ['meta' => $meta, 'percentual' => $pct];
                        }

                        return $padrao;

                    } catch (Exception $e) {
                        error_log("Erro ao buscar meta/percentual para '$categoria' (pai: '$categoria_pai'): " . $e->getMessage());
                        return $padrao;
                    }
                }
                
                // Categorias não operacionais (apenas repasses)
                $categorias_nao_operacionais = [
                    'ENTRADA DE REPASSE DE SALARIOS',
                    'ENTRADA DE REPASSE EXTRA DE SALARIOS', 
                    'ENTRADA DE REPASSE OUTROS'
                ];
                
                // Processar RECEITAS - VENDAS são operacionais, REPASSES são não operacionais
                foreach ($dados_receita as $linha) {
                    $categoria = trim(strtoupper($linha['categoria'] ?? ''));
                    $categoria_pai = trim(strtoupper($linha['categoria_pai'] ?? ''));
                    $valor = floatval($linha['total_receita_mes'] ?? 0);
                    $total_geral += $valor;
                    
                    // Verificar se é categoria não operacional (apenas repasses)
                    $eh_nao_operacional = false;
                    foreach ($categorias_nao_operacionais as $cat_nao_op) {
                        if (strpos($categoria, trim(strtoupper($cat_nao_op))) !== false || 
                            trim(strtoupper($cat_nao_op)) === $categoria) {
                            $eh_nao_operacional = true;
                            break;
                        }
                    }
                    
                    // Debug: mostrar como cada categoria está sendo classificada
                    echo "<!-- DEBUG: Categoria: '$categoria' | Pai: '$categoria_pai' | Não Op: " . ($eh_nao_operacional ? 'SIM' : 'NÃO') . " -->";
                    
                    if ($eh_nao_operacional) {
                        $receitas_nao_operacionais[] = $linha;
                    } else {
                        $receitas_operacionais[] = $linha;
                    }
                }
                
                // Processar DESPESAS (incluindo TRIBUTOS e CUSTO FIXO)
                foreach ($dados_despesa as $linha) {
                    $categoria_pai = trim(strtoupper($linha['categoria_pai'] ?? ''));
                    
                    // Separar TRIBUTOS das despesas
                    if ($categoria_pai === 'TRIBUTOS') {
                        $tributos[] = $linha;
                    }
                    // Separar CUSTO VARIÁVEL das despesas
                    elseif ($categoria_pai === 'CUSTO VARIÁVEL' || $categoria_pai === 'CUSTO VARIAVEL') {
                        $custo_variavel[] = $linha;
                    }
                    // Separar CUSTO FIXO das despesas
                    elseif ($categoria_pai === 'CUSTO FIXO') {
                        $custo_fixo[] = $linha;
                    }
                    // Separar DESPESA FIXA das despesas
                    elseif ($categoria_pai === 'DESPESA FIXA') {
                        $despesa_fixa[] = $linha;
                    }
                    // Separar DESPESAS DE VENDA das despesas
                    elseif ($categoria_pai === '(-) DESPESAS DE VENDA' || $categoria_pai === 'DESPESAS DE VENDA' || $categoria_pai === 'DESPESA DE VENDA') {
                        $despesa_venda[] = $linha;
                    }
                    // Separar INVESTIMENTO INTERNO das despesas
                    elseif ($categoria_pai === 'INVESTIMENTO INTERNO') {
                        $investimento_interno[] = $linha;
                    }
                    // Separar SAÍDAS NÃO OPERACIONAIS das despesas
                    elseif ($categoria_pai === 'SAÍDAS NÃO OPERACIONAIS' || $categoria_pai === 'SAIDAS NAO OPERACIONAIS') {
                        $saidas_nao_operacionais[] = $linha;
                    }
                }
                
                // Ordenar subcategorias por valor (do maior para o menor)
                usort($receitas_operacionais, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                usort($receitas_nao_operacionais, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                usort($tributos, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                usort($custo_variavel, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                usort($custo_fixo, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                usort($despesa_fixa, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                usort($despesa_venda, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                usort($investimento_interno, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });
                usort($saidas_nao_operacionais, function($a, $b) {
                    return floatval($b['total_receita_mes'] ?? 0) <=> floatval($a['total_receita_mes'] ?? 0);
                });

                // Remover possíveis duplicatas por nome de categoria (evita linhas repetidas na tabela)
                function dedupe_by_categoria_name($arr) {
                    $out = [];
                    $seen = [];
                    foreach ($arr as $r) {
                        $nome = trim(strtoupper($r['categoria'] ?? ''));
                        if ($nome === '') continue;
                        if (in_array($nome, $seen, true)) continue;
                        $seen[] = $nome;
                        $out[] = $r;
                    }
                    return $out;
                }

                $receitas_operacionais = dedupe_by_categoria_name($receitas_operacionais);
                $receitas_nao_operacionais = dedupe_by_categoria_name($receitas_nao_operacionais);
                $tributos = dedupe_by_categoria_name($tributos);
                $custo_variavel = dedupe_by_categoria_name($custo_variavel);
                $custo_fixo = dedupe_by_categoria_name($custo_fixo);
                $despesa_fixa = dedupe_by_categoria_name($despesa_fixa);
                $despesa_venda = dedupe_by_categoria_name($despesa_venda);
                $investimento_interno = dedupe_by_categoria_name($investimento_interno);
                $saidas_nao_operacionais = dedupe_by_categoria_name($saidas_nao_operacionais);

                $total_operacional = array_sum(array_column($receitas_operacionais, 'total_receita_mes'));
                $total_nao_operacional = array_sum(array_column($receitas_nao_operacionais, 'total_receita_mes'));
                // Base operacional: excluir receitas não operacionais (repasses)
                $total_geral_operacional = $total_geral - $total_nao_operacional;
                $total_tributos = array_sum(array_column($tributos, 'total_receita_mes'));
                $total_custo_variavel = array_sum(array_column($custo_variavel, 'total_receita_mes'));
                $total_custo_fixo = array_sum(array_column($custo_fixo, 'total_receita_mes'));
                $total_despesa_fixa = array_sum(array_column($despesa_fixa, 'total_receita_mes'));
                $total_despesa_venda = array_sum(array_column($despesa_venda, 'total_receita_mes'));
                $total_investimento_interno = array_sum(array_column($investimento_interno, 'total_receita_mes'));
                $total_saidas_nao_operacionais = array_sum(array_column($saidas_nao_operacionais, 'total_receita_mes'));
                // Valores operacionais (para métricas que devem ignorar receitas não operacionais)
                $receita_liquida_operacional = $total_geral_operacional - $total_tributos;
                $lucro_bruto_operacional = $receita_liquida_operacional - $total_custo_variavel;
                $lucro_liquido_operacional = $lucro_bruto_operacional - $total_custo_fixo - $total_despesa_fixa - $total_despesa_venda;
                
                // Debug: mostrar contadores e alguns exemplos
                echo "<!-- DEBUG CONTADORES: ";
                echo "Total registros: " . count($dados_receita) . " | ";
                echo "Operacionais: " . count($receitas_operacionais) . " | ";
                echo "Não operacionais: " . count($receitas_nao_operacionais) . " | ";
                echo "Total Operacional: R$ " . number_format($total_operacional, 2) . " | ";
                echo "Total Não Operacional: R$ " . number_format($total_nao_operacional, 2);
                echo " -->";
                ?>
                

                
                <?php
                
                if (!empty($receitas_nao_operacionais)) {
                    echo "<!-- DEBUG NÃO OPERACIONAIS: ";
                    foreach ($receitas_nao_operacionais as $item) {
                        echo "'" . $item['categoria'] . "' (R$ " . number_format($item['total_receita_mes'], 2) . ") | ";
                    }
                    echo " -->";
                } else {
                    echo "<!-- DEBUG: NENHUMA RECEITA NÃO OPERACIONAL ENCONTRADA! -->";
                }
                ?>
                
                <table class="w-full text-sm text-gray-300" id="tabelaSimulador">
                    <thead class="bg-gray-700 sticky top-0">
                        <tr>
                            <th class="px-3 py-2 text-left border-b border-gray-600">Descrição</th>
                            <th class="px-3 py-2 text-center border-b border-gray-600">Meta</th>
                            <th class="px-3 py-2 text-right border-b border-gray-600">Valor Base (R$)</th>
                            <th class="px-3 py-2 text-right border-b border-gray-600 bg-blue-800">Valor Simulador (R$)</th>
                            <th class="px-3 py-2 text-right border-b border-gray-600 bg-blue-800">% sobre Faturamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- RECEITA BRUTA - Linha principal expansível (exclui receitas não operacionais) -->
                        <?php 
                        // Mostrar na linha principal apenas as RECEITAS OPERACIONAIS (como solicitado)
                        $meta_operacional = obterMeta('RECEITAS OPERACIONAIS');
                        $meta_operacional_val = $meta_operacional['meta'] ?? 0;
                        $meta_operacional_pct = $meta_operacional['percentual'] ?? 0;
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-green-400">
                            <td class="px-3 py-2 border-b border-gray-700">
                                RECEITA OPERACIONAL
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_operacional_val, 0, ',', '.') ?> <span class="text-xs text-gray-400">(<?= number_format($meta_operacional_pct, 2, ',', '.') ?>%)</span></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-receita-bruta">
                                R$ <?= number_format($total_operacional, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-receita-bruta">
                                <input type="text" 
                                       class="bg-transparent text-green-400 text-right w-full border-0 outline-0 valor-simulador"
                                       id="valor-sim-operacional"
                                       data-categoria="RECEITAS OPERACIONAIS"
                                       data-tipo="receita-operacional"
                                       data-valor-base="<?= $total_operacional ?>"
                                       value="<?= number_format($total_operacional, 2, ',', '.') ?>"
                                       onchange="atualizarCalculos()"
                                       onblur="formatarCampoMoeda(this)"
                                       onfocus="removerFormatacao(this)">
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-receita-bruta">
                                <?= number_format((($total_operacional) / ($total_operacional ?: 1)) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Subgrupos da RECEITA BRUTA -->
                    <tbody class="subcategorias" id="sub-receita-bruta" style="display: none;">
                        <!-- RECEITAS OPERACIONAIS - Subgrupo -->
                        <?php if (!empty($receitas_operacionais)): ?>
                        <?php 
                        $meta_operacional = obterMeta('RECEITAS OPERACIONAIS');
                        $meta_operacional_val = $meta_operacional['meta'] ?? 0;
                        $meta_operacional_pct = $meta_operacional['percentual'] ?? 0;
                        ?>
                        <tr class="hover:bg-gray-700 font-medium text-blue-300 text-sm">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                RECEITAS OPERACIONAIS
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_operacional_val, 0, ',', '.') ?> <span class="text-xs text-gray-400">(<?= number_format($meta_operacional_pct, 2, ',', '.') ?>%)</span></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-operacional">
                                R$ <?= number_format($total_operacional, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="text" 
                                       class="bg-transparent text-green-400 text-right w-full border-0 outline-0 valor-simulador"
                                       id="valor-sim-operacional"
                                       data-categoria="RECEITAS OPERACIONAIS"
                                       data-tipo="receita-operacional"
                                       data-valor-base="<?= $total_operacional ?>"
                                       value="<?= number_format($total_operacional, 2, ',', '.') ?>"
                                       onchange="atualizarCalculos()"
                                       onblur="formatarCampoMoeda(this)"
                                       onfocus="removerFormatacao(this)">
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-operacional">
                                <?= number_format(($total_operacional / ($total_geral_operacional ?: 1)) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                        <?php endif; ?>
                        
                        <!-- RECEITAS NÃO OPERACIONAIS - (moved below, above SAÍDAS NÃO OPERACIONAIS) -->
                    </tbody>
                    

                    
                    <!-- TRIBUTOS - Linha principal -->
                    <?php if (!empty($tributos)): ?>
                    <tbody>
                        <?php 
                        $meta_tributos = obterMeta('TRIBUTOS');
                        $meta_tributos_val = $meta_tributos['meta'] ?? 0;
                        $meta_tributos_pct = $meta_tributos['percentual'] ?? 0;
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-orange-400" onclick="toggleReceita('tributos')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) TRIBUTOS
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_tributos_val, 0, ',', '.') ?> <span class="text-xs text-gray-400">(<?= number_format($meta_tributos_pct, 2, ',', '.') ?>%)</span></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-tributos">
                                R$ <?= number_format($total_tributos, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-gray-600" id="valor-sim-tributos">
                                R$ <?= number_format($total_tributos, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-tributos">
                                <?= number_format(($total_tributos / ($total_geral_operacional ?: 1)) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes dos TRIBUTOS -->
                    <tbody class="subcategorias" id="sub-tributos" style="display: none;">
                        <?php foreach ($tributos as $index => $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'TRIBUTOS');
                        $meta_individual_val = $meta_individual['meta'] ?? 0;
                        $meta_individual_pct = $meta_individual['percentual'] ?? 0;
                        $categoria_id = 'tributos-' . $index;
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual_val ?? 0, 0, ',', '.') ?> <span class="text-xs text-gray-400">(<?= number_format($meta_individual_pct ?? 0, 2, ',', '.') ?>%)</span></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-orange-300" id="valor-base-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-gray-600" id="valor-sim-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="text" 
                                       id="perc-<?= $categoria_id ?>"
                                       data-categoria="<?= htmlspecialchars($categoria_individual) ?>"
                                       data-tipo="percentual-tributo"
                                       data-valor-base="<?= $valor_individual ?>"
                                       value="<?= number_format(($valor_individual / ($total_geral_operacional ?: 1)) * 100, 2, ',', '.') ?>"
                                       onchange="atualizarCalculosPercentualSubcategoria(this)"
                                       style="background: transparent; color: #fb923c; text-align: right; border: none; outline: none; width: 70px;"> %
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>
                    
                    <!-- RECEITA LÍQUIDA - Cálculo automático -->
                    <tbody>
                        <tr class="hover:bg-gray-700 font-semibold text-green-400">
                            <td class="px-3 py-2 border-b border-gray-700">
                                RECEITA LÍQUIDA
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_receita_liquida ?? 0, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-receita-liquida">
                                R$ <?= number_format($receita_liquida_operacional, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-receita-liquida">
                                R$ <?= number_format($receita_liquida_operacional, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-receita-liquida">
                                <?= number_format((($receita_liquida_operacional) / ($total_geral_operacional ?: 1)) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>

                    <!-- CUSTO VARIÁVEL - Linha principal -->
                    <?php if (!empty($custo_variavel)): ?>
                    <tbody>
                        <?php 
                        $meta_custo_variavel = obterMeta('CUSTO VARIÁVEL');
                        $meta_custo_variavel_val = $meta_custo_variavel['meta'] ?? 0;
                        $meta_custo_variavel_pct = $meta_custo_variavel['percentual'] ?? 0;
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-orange-400" onclick="toggleReceita('custo-variavel')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) CUSTO VARIÁVEL
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_custo_variavel_val, 0, ',', '.') ?> <span class="text-xs text-gray-400">(<?= number_format($meta_custo_variavel_pct, 2, ',', '.') ?>%)</span></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-custo-variavel">
                                R$ <?= number_format($total_custo_variavel, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-gray-600" id="valor-sim-custo-variavel">
                                R$ <?= number_format($total_custo_variavel, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-custo-variavel">
                                <?= number_format(($total_custo_variavel / ($total_geral_operacional ?: 1)) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes dos CUSTOS VARIÁVEIS -->
                    <tbody class="subcategorias" id="sub-custo-variavel" style="display: none;">
                        <?php foreach ($custo_variavel as $index => $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'CUSTO VARIÁVEL');
                        $meta_individual_val = $meta_individual['meta'] ?? 0;
                        $meta_individual_pct = $meta_individual['percentual'] ?? 0;
                        $categoria_id = 'custo-variavel-' . $index;
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual_val ?? 0, 0, ',', '.') ?> <span class="text-xs text-gray-400">(<?= number_format($meta_individual_pct ?? 0, 2, ',', '.') ?>%)</span></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-orange-300" id="valor-base-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-gray-600" id="valor-sim-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="text" 
                                       id="perc-<?= $categoria_id ?>"
                                       data-categoria="<?= htmlspecialchars($categoria_individual) ?>"
                                       data-tipo="percentual-custo-variavel"
                                       data-valor-base="<?= $valor_individual ?>"
                                       value="<?= number_format(($valor_individual / ($total_geral_operacional ?: 1)) * 100, 2, ',', '.') ?>"
                                       onchange="atualizarCalculosPercentualSubcategoria(this)"
                                       style="background: transparent; color: #fb923c; text-align: right; border: none; outline: none; width: 70px;"> %
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- LUCRO BRUTO - Cálculo automático -->
                    <tbody>
                        <tr class="hover:bg-gray-700 font-semibold text-green-400">
                            <td class="px-3 py-2 border-b border-gray-700">
                                LUCRO BRUTO
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_lucro_bruto ?? 0, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-lucro-bruto">
                                R$ <?= number_format($lucro_bruto_operacional, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-lucro-bruto">
                                R$ <?= number_format($lucro_bruto_operacional, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-lucro-bruto">
                                <?= number_format((($lucro_bruto_operacional) / ($total_geral_operacional ?: 1)) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>

                    <!-- CUSTO FIXO - Linha principal -->
                    <?php if (!empty($custo_fixo)): ?>
                    <tbody>
                        <?php 
                        $meta_custo_fixo = obterMeta('CUSTO FIXO');
                        $meta_custo_fixo_val = $meta_custo_fixo['meta'] ?? 0;
                        $meta_custo_fixo_pct = $meta_custo_fixo['percentual'] ?? 0;
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-orange-400" onclick="toggleReceita('custo-fixo')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) CUSTO FIXO
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_custo_fixo_val, 0, ',', '.') ?> <span class="text-xs text-gray-400">(<?= number_format($meta_custo_fixo_pct, 2, ',', '.') ?>%)</span></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-custo-fixo">
                                R$ <?= number_format($total_custo_fixo, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-custo-fixo">
                                R$ <?= number_format($total_custo_fixo, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-custo-fixo">
                                <?= number_format(($total_custo_fixo / ($total_geral_operacional ?: 1)) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes dos CUSTOS FIXOS -->
                    <tbody class="subcategorias" id="sub-custo-fixo" style="display: none;">
                        <?php foreach ($custo_fixo as $index => $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'CUSTO FIXO');
                        $meta_individual_val = $meta_individual['meta'] ?? 0;
                        $meta_individual_pct = $meta_individual['percentual'] ?? 0;
                        $categoria_id = 'custo-fixo-' . $index;
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual_val ?? 0, 0, ',', '.') ?> <span class="text-xs text-gray-400">(<?= number_format($meta_individual_pct ?? 0, 2, ',', '.') ?>%)</span></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-orange-300" id="valor-base-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="number" 
                                       class="bg-transparent text-orange-300 text-right w-full border-0 outline-0 valor-simulador"
                                       id="valor-sim-<?= $categoria_id ?>"
                                       data-categoria="<?= htmlspecialchars($categoria_individual) ?>"
                                       data-tipo="custo-fixo"
                                       data-valor-base="<?= $valor_individual ?>"
                                       value="<?= number_format($valor_individual, 2, '.', '') ?>"
                                       step="0.01"
                                       onchange="atualizarCalculos()">
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-<?= $categoria_id ?>">
                                <?= number_format(($valor_individual / ($total_geral_operacional ?: 1)) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- DESPESA FIXA - Linha principal -->
                    <?php if (!empty($despesa_fixa)): ?>
                    <tbody>
                        <?php 
                        $meta_despesa_fixa = obterMeta('DESPESA FIXA');
                        $meta_despesa_fixa_val = $meta_despesa_fixa['meta'] ?? 0;
                        $meta_despesa_fixa_pct = $meta_despesa_fixa['percentual'] ?? 0;
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-orange-400" onclick="toggleReceita('despesa-fixa')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) DESPESA FIXA
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_despesa_fixa_val, 0, ',', '.') ?> <span class="text-xs text-gray-400">(<?= number_format($meta_despesa_fixa_pct, 2, ',', '.') ?>%)</span></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-despesa-fixa">
                                R$ <?= number_format($total_despesa_fixa, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-despesa-fixa">
                                R$ <?= number_format($total_despesa_fixa, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-despesa-fixa">
                                <?= number_format(($total_despesa_fixa / ($total_geral_operacional ?: 1)) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes das DESPESAS FIXAS -->
                    <tbody class="subcategorias" id="sub-despesa-fixa" style="display: none;">
                        <?php foreach ($despesa_fixa as $index => $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'DESPESA FIXA');
                        $meta_individual_val = $meta_individual['meta'] ?? 0;
                        $meta_individual_pct = $meta_individual['percentual'] ?? 0;
                        $categoria_id = 'despesa-fixa-' . $index;
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual_val ?? 0, 0, ',', '.') ?> <span class="text-xs text-gray-400">(<?= number_format($meta_individual_pct ?? 0, 2, ',', '.') ?>%)</span></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-orange-300" id="valor-base-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="number" 
                                       class="bg-transparent text-orange-300 text-right w-full border-0 outline-0 valor-simulador"
                                       id="valor-sim-<?= $categoria_id ?>"
                                       data-categoria="<?= htmlspecialchars($categoria_individual) ?>"
                                       data-tipo="despesa-fixa"
                                       data-valor-base="<?= $valor_individual ?>"
                                       value="<?= number_format($valor_individual, 2, '.', '') ?>"
                                       step="0.01"
                                       onchange="atualizarCalculos()">
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-<?= $categoria_id ?>">
                                <?= number_format(($valor_individual / ($total_geral_operacional ?: 1)) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- DESPESA DE VENDA - Linha principal -->
                    <?php if (!empty($despesa_venda)): ?>
                    <tbody>
                        <?php 
                        $meta_despesa_venda = obterMeta('DESPESAS DE VENDA');
                        $meta_despesa_venda_val = $meta_despesa_venda['meta'] ?? 0;
                        $meta_despesa_venda_pct = $meta_despesa_venda['percentual'] ?? 0;
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-orange-400" onclick="toggleReceita('despesa-venda')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) DESPESAS DE VENDA
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_despesa_venda_val, 0, ',', '.') ?> <span class="text-xs text-gray-400">(<?= number_format($meta_despesa_venda_pct, 2, ',', '.') ?>%)</span></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-despesa-venda">
                                R$ <?= number_format($total_despesa_venda, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-gray-600" id="valor-sim-despesa-venda">
                                R$ <?= number_format($total_despesa_venda, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-despesa-venda">
                                <?= number_format(($total_despesa_venda / ($total_geral_operacional ?: 1)) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes das DESPESAS DE VENDA -->
                    <tbody class="subcategorias" id="sub-despesa-venda" style="display: none;">
                        <?php foreach ($despesa_venda as $index => $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'DESPESAS DE VENDA');
                        $meta_individual_val = $meta_individual['meta'] ?? 0;
                        $meta_individual_pct = $meta_individual['percentual'] ?? 0;
                        $categoria_id = 'despesa-venda-' . $index;
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual_val ?? 0, 0, ',', '.') ?> <span class="text-xs text-gray-400">(<?= number_format($meta_individual_pct ?? 0, 2, ',', '.') ?>%)</span></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-orange-300" id="valor-base-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-gray-600" id="valor-sim-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="text" 
                                       id="perc-<?= $categoria_id ?>"
                                       data-categoria="<?= htmlspecialchars($categoria_individual) ?>"
                                       data-tipo="percentual-despesa-venda"
                                       data-valor-base="<?= $valor_individual ?>"
                                       value="<?= number_format(($valor_individual / ($total_geral_operacional ?: 1)) * 100, 2, ',', '.') ?>"
                                       onchange="atualizarCalculosPercentualSubcategoria(this)"
                                       style="background: transparent; color: #fb923c; text-align: right; border: none; outline: none; width: 70px;"> %
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- LUCRO LÍQUIDO - Cálculo automático final -->
                    <?php 
                    // Manter cálculo global, mas exibir a métrica operacional conforme regra
                    $lucro_liquido = (($total_geral - $total_tributos) - $total_custo_variavel) - $total_custo_fixo - $total_despesa_fixa - $total_despesa_venda;
                    // $lucro_liquido_operacional já calculado acima e reflete base operacional
                    ?>
                    <tbody>
                        <tr class="hover:bg-gray-700 font-bold text-green-400 bg-green-900 bg-opacity-20">
                            <td class="px-3 py-3 border-b-2 border-green-600 font-bold">
                                LUCRO LÍQUIDO
                            </td>
                            <td class="px-3 py-3 border-b-2 border-green-600 text-center">
                                <span class="text-xs text-gray-500 font-semibold">R$ <?= number_format($meta_lucro_liquido ?? 0, 0, ',', '.') ?></span>
                            </td>
                            <td class="px-3 py-3 border-b-2 border-green-600 text-right font-mono font-bold" id="valor-base-lucro-liquido">
                                R$ <?= number_format($lucro_liquido_operacional, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-3 border-b-2 border-green-600 text-right font-mono font-bold bg-blue-900" id="valor-sim-lucro-liquido">
                                R$ <?= number_format($lucro_liquido_operacional, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-3 border-b-2 border-green-600 text-right font-mono font-bold bg-blue-900" id="perc-lucro-liquido">
                                <?= number_format(($lucro_liquido_operacional / ($total_geral_operacional ?: 1)) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>

                    <!-- INVESTIMENTO INTERNO - Linha principal -->
                    <?php if (!empty($investimento_interno)): ?>
                    <tbody>
                        <?php 
                        $meta_investimento_interno = obterMeta('INVESTIMENTO INTERNO');
                        $meta_investimento_interno_val = $meta_investimento_interno['meta'] ?? 0;
                        $meta_investimento_interno_pct = $meta_investimento_interno['percentual'] ?? 0;
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-blue-400" onclick="toggleReceita('investimento-interno')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) INVESTIMENTO INTERNO
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_investimento_interno_val, 0, ',', '.') ?> <span class="text-xs text-gray-400">(<?= number_format($meta_investimento_interno_pct, 2, ',', '.') ?>%)</span></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-investimento-interno">
                                R$ <?= number_format($total_investimento_interno, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-investimento-interno">
                                R$ <?= number_format($total_investimento_interno, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-investimento-interno">
                                <?= number_format(($total_investimento_interno / ($total_geral_operacional ?: 1)) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes dos INVESTIMENTOS INTERNOS -->
                    <tbody class="subcategorias" id="sub-investimento-interno" style="display: none;">
                        <?php foreach ($investimento_interno as $index => $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'INVESTIMENTO INTERNO');
                        $meta_individual_val = $meta_individual['meta'] ?? 0;
                        $meta_individual_pct = $meta_individual['percentual'] ?? 0;
                        $categoria_id = 'investimento-interno-' . $index;
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual_val ?? 0, 0, ',', '.') ?> <span class="text-xs text-gray-400">(<?= number_format($meta_individual_pct ?? 0, 2, ',', '.') ?>%)</span></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-blue-300" id="valor-base-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="text" 
                                       class="bg-transparent text-blue-300 text-right w-full border-0 outline-0 valor-simulador"
                                       id="valor-sim-<?= $categoria_id ?>"
                                       data-categoria="<?= htmlspecialchars($categoria_individual) ?>"
                                       data-tipo="investimento-interno"
                                       data-valor-base="<?= $valor_individual ?>"
                                       value="<?= number_format($valor_individual, 2, ',', '.') ?>"
                                       onchange="atualizarCalculos()"
                                       onblur="formatarCampoMoeda(this)"
                                       onfocus="removerFormatacao(this)">
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-<?= $categoria_id ?>">
                                <?= number_format(($valor_individual / ($total_geral_operacional ?: 1)) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- RECEITAS NÃO OPERACIONAIS - Linha principal (moved) -->
                    <?php if (!empty($receitas_nao_operacionais)): ?>
                    <tbody>
                        <?php 
                        $meta_nao_operacional = obterMeta('RECEITAS NÃO OPERACIONAIS');
                        $meta_nao_operacional_val = $meta_nao_operacional['meta'] ?? 0;
                        $meta_nao_operacional_pct = $meta_nao_operacional['percentual'] ?? 0;
                        ?>
                        <tr class="hover:bg-gray-700 font-medium text-blue-300 text-sm">
                            <td class="px-3 py-2 border-b border-gray-700">
                                RECEITAS NÃO OPERACIONAIS
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_nao_operacional_val, 0, ',', '.') ?> <span class="text-xs text-gray-400">(<?= number_format($meta_nao_operacional_pct, 2, ',', '.') ?>%)</span></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-nao-operacional">
                                R$ <?= number_format($total_nao_operacional, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="text" 
                                       class="bg-transparent text-green-400 text-right w-full border-0 outline-0 valor-simulador"
                                       id="valor-sim-nao-operacional"
                                       data-categoria="RECEITAS NÃO OPERACIONAIS"
                                       data-tipo="receita-nao-operacional"
                                       data-valor-base="<?= $total_nao_operacional ?>"
                                       value="<?= number_format($total_nao_operacional, 2, ',', '.') ?>"
                                       onchange="atualizarCalculos()"
                                       onblur="formatarCampoMoeda(this)"
                                       onfocus="removerFormatacao(this)">
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-nao-operacional">
                                <?= number_format(($total_nao_operacional / $total_geral) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    <?php endif; ?>

                    <!-- SAÍDAS NÃO OPERACIONAIS - Linha principal -->
                    <?php if (!empty($saidas_nao_operacionais)): ?>
                    <tbody>
                        <?php 
                        $meta_saidas_nao_operacionais = obterMeta('SAÍDAS NÃO OPERACIONAIS');
                        $meta_saidas_nao_operacionais_val = $meta_saidas_nao_operacionais['meta'] ?? 0;
                        $meta_saidas_nao_operacionais_pct = $meta_saidas_nao_operacionais['percentual'] ?? 0;
                        ?>
                        <tr class="hover:bg-gray-700 cursor-pointer font-semibold text-red-400" onclick="toggleReceita('saidas-nao-operacionais')">
                            <td class="px-3 py-2 border-b border-gray-700">
                                (-) SAÍDAS NÃO OPERACIONAIS
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">Meta: R$ <?= number_format($meta_saidas_nao_operacionais_val, 0, ',', '.') ?> <span class="text-xs text-gray-400">(<?= number_format($meta_saidas_nao_operacionais_pct, 2, ',', '.') ?>%)</span></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono" id="valor-base-saidas-nao-operacionais">
                                R$ <?= number_format($total_saidas_nao_operacionais, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="valor-sim-saidas-nao-operacionais">
                                R$ <?= number_format($total_saidas_nao_operacionais, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-saidas-nao-operacionais">
                                <?= number_format(($total_saidas_nao_operacionais / $total_geral) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    
                    <!-- Detalhes das SAÍDAS NÃO OPERACIONAIS -->
                    <tbody class="subcategorias" id="sub-saidas-nao-operacionais" style="display: none;">
                        <?php foreach ($saidas_nao_operacionais as $index => $linha): ?>
                        <?php 
                        $categoria_individual = trim($linha['categoria'] ?? 'SEM CATEGORIA');
                        $valor_individual = floatval($linha['total_receita_mes'] ?? 0);
                        $meta_individual = obterMeta($categoria_individual, 'SAÍDAS NÃO OPERACIONAIS');
                        $meta_individual_val = $meta_individual['meta'] ?? 0;
                        $meta_individual_pct = $meta_individual['percentual'] ?? 0;
                        $categoria_id = 'saidas-nao-operacionais-' . $index;
                        ?>
                        <tr class="hover:bg-gray-700 text-gray-300">
                            <td class="px-3 py-2 border-b border-gray-700 pl-6">
                                <?= htmlspecialchars($categoria_individual) ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-center">
                                <span class="text-xs text-gray-500">R$ <?= number_format($meta_individual_val ?? 0, 0, ',', '.') ?> <span class="text-xs text-gray-400">(<?= number_format($meta_individual_pct ?? 0, 2, ',', '.') ?>%)</span></span>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono text-red-300" id="valor-base-<?= $categoria_id ?>">
                                R$ <?= number_format($valor_individual, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900">
                                <input type="text" 
                                       class="bg-transparent text-red-300 text-right w-full border-0 outline-0 valor-simulador"
                                       id="valor-sim-<?= $categoria_id ?>"
                                       data-categoria="<?= htmlspecialchars($categoria_individual) ?>"
                                       data-tipo="saidas-nao-operacionais"
                                       data-valor-base="<?= $valor_individual ?>"
                                       value="<?= number_format($valor_individual, 2, ',', '.') ?>"
                                       onchange="atualizarCalculos()"
                                       onblur="formatarCampoMoeda(this)"
                                       onfocus="removerFormatacao(this)">
                            </td>
                            <td class="px-3 py-2 border-b border-gray-700 text-right font-mono bg-blue-900" id="perc-<?= $categoria_id ?>">
                                <?= number_format(($valor_individual / ($total_geral_operacional ?: 1)) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php endif; ?>

                    <!-- IMPACTO CAIXA - Cálculo final -->
                    <?php 
                    // IMPACTO CAIXA: conforme solicitação - usar LUCRO LÍQUIDO (operacional) e incluir RECEITAS NÃO OPERACIONAIS
                    $impacto_caixa = $lucro_liquido_operacional - $total_investimento_interno - $total_saidas_nao_operacionais + $total_nao_operacional;
                    $cor_impacto = $impacto_caixa >= 0 ? 'green' : 'red';
                    $meta_impacto_caixa = obterMeta('IMPACTO CAIXA');
                    $meta_impacto_caixa_val = $meta_impacto_caixa['meta'] ?? 0;
                    $meta_impacto_caixa_pct = $meta_impacto_caixa['percentual'] ?? 0;
                    ?>
                    <tbody>
                        <tr class="hover:bg-gray-700 font-bold text-<?= $cor_impacto ?>-400 bg-<?= $cor_impacto ?>-900 bg-opacity-20">
                            <td class="px-3 py-3 border-b-2 border-<?= $cor_impacto ?>-600 font-bold">
                                (=) IMPACTO CAIXA
                            </td>
                            <td class="px-3 py-3 border-b-2 border-<?= $cor_impacto ?>-600 text-center">
                                <span class="text-xs text-gray-500 font-semibold">Meta: R$ <?= number_format($meta_impacto_caixa_val, 0, ',', '.') ?> <span class="text-xs text-gray-400">(<?= number_format($meta_impacto_caixa_pct, 2, ',', '.') ?>%)</span></span>
                            </td>
                            <td class="px-3 py-3 border-b-2 border-<?= $cor_impacto ?>-600 text-right font-mono font-bold" id="valor-base-impacto-caixa">
                                R$ <?= number_format($impacto_caixa, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-3 border-b-2 border-<?= $cor_impacto ?>-600 text-right font-mono font-bold bg-blue-900" id="valor-sim-impacto-caixa">
                                R$ <?= number_format($impacto_caixa, 2, ',', '.') ?>
                            </td>
                            <td class="px-3 py-3 border-b-2 border-<?= $cor_impacto ?>-600 text-right font-mono font-bold bg-blue-900" id="perc-impacto-caixa">
                                <?= number_format(($impacto_caixa / ($total_geral_operacional ?: 1)) * 100, 2, ',', '.') ?>%
                            </td>
                        </tr>
                    </tbody>
                    
                </table>
            </div>
        </div>
        <?php else: ?>
            <div class="bg-gray-800 rounded-lg p-6 text-center">
                <p class="text-gray-400 text-lg mb-2">📊 Nenhum dado encontrado</p>
                <p class="text-gray-500">
                    Não há dados para o período selecionado: <?= htmlspecialchars($periodos_disponiveis[$periodo_selecionado] ?? $periodo_selecionado) ?>
                </p>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="bg-gray-800 rounded-lg p-6 text-center">
            <p class="text-gray-400 text-lg mb-2">📋 Selecione um período base</p>
            <p class="text-gray-500">
                Escolha um período no filtro acima para carregar os dados base e iniciar a simulação.
            </p>
            <?php if (!empty($periodos_disponiveis)): ?>
                <p class="text-gray-500 mt-2">
                    Períodos disponíveis: <?= count($periodos_disponiveis) ?> meses com dados
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de Seleção de Meses para Metas -->
<div id="modalMetas" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center" onclick="fecharModalSeClicarFora(event)">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-800">🎯 Salvar Metas Financeiras</h3>
            <button onclick="fecharModalMetas()" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <p class="text-gray-600 mb-4">Selecione os anos e os meses para salvar as metas financeiras:</p>
        
        <!-- Seletor de Anos (múltiplos) -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">📅 Anos:</label>
            <div class="grid grid-cols-3 gap-2 mb-2">
                <?php
                $anoAtual = date('Y');
                for ($ano = $anoAtual - 1; $ano <= $anoAtual + 5; $ano++) {
                    $checked = ($ano == $anoAtual) ? 'checked' : '';
                    echo "
                    <label class='flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded border border-gray-200'>
                        <input type='checkbox' value='$ano' class='ano-checkbox' id='ano-$ano' $checked>
                        <span class='text-sm font-medium'>$ano</span>
                    </label>
                    ";
                }
                ?>
            </div>
            <div class="flex gap-2 text-xs">
                <button type="button" onclick="selecionarTodosAnos()" class="text-blue-600 hover:text-blue-800">
                    ✅ Selecionar Todos
                </button>
                <button type="button" onclick="desmarcarTodosAnos()" class="text-gray-600 hover:text-gray-800">
                    ❌ Desmarcar Todos
                </button>
            </div>
        </div>
        
        <label class="block text-sm font-medium text-gray-700 mb-2">📅 Meses:</label>
        <div class="grid grid-cols-2 gap-3 mb-6">
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="01" class="mes-checkbox" id="mes-01">
                <span class="text-sm">Janeiro</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="02" class="mes-checkbox" id="mes-02">
                <span class="text-sm">Fevereiro</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="03" class="mes-checkbox" id="mes-03">
                <span class="text-sm">Março</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="04" class="mes-checkbox" id="mes-04">
                <span class="text-sm">Abril</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="05" class="mes-checkbox" id="mes-05">
                <span class="text-sm">Maio</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="06" class="mes-checkbox" id="mes-06">
                <span class="text-sm">Junho</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="07" class="mes-checkbox" id="mes-07">
                <span class="text-sm">Julho</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="08" class="mes-checkbox" id="mes-08">
                <span class="text-sm">Agosto</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="09" class="mes-checkbox" id="mes-09">
                <span class="text-sm">Setembro</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="10" class="mes-checkbox" id="mes-10">
                <span class="text-sm">Outubro</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="11" class="mes-checkbox" id="mes-11">
                <span class="text-sm">Novembro</span>
            </label>
            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                <input type="checkbox" value="12" class="mes-checkbox" id="mes-12">
                <span class="text-sm">Dezembro</span>
            </label>
        </div>
        
        <div class="flex justify-between items-center mb-4">
            <button onclick="selecionarTodosMeses()" class="text-blue-600 hover:text-blue-800 text-sm">
                ✅ Selecionar Todos
            </button>
            <button onclick="desmarcarTodosMeses()" class="text-gray-600 hover:text-gray-800 text-sm">
                ❌ Desmarcar Todos
            </button>
        </div>
        
        <div class="flex space-x-3">
            <button onclick="fecharModalMetas()" class="flex-1 px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-700 rounded transition-colors">
                Cancelar
            </button>
            <button onclick="salvarMetasFinanceiras()" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded transition-colors">
                💾 Salvar Metas
            </button>
        </div>
    </div>
</div>

<script>
function toggleReceita(categoriaId) {
    // Caso especial para RECEITA BRUTA - mostra os subgrupos principais (apenas os cabeçalhos)
    if (categoriaId === 'receita-bruta') {
        var subReceitaBruta = document.getElementById('sub-receita-bruta');
        
        if (subReceitaBruta) {
            if (subReceitaBruta.style.display === 'none' || subReceitaBruta.style.display === '') {
                subReceitaBruta.style.display = 'table-row-group';
            } else {
                subReceitaBruta.style.display = 'none';
            }
        }
    } 
    // Para outros casos (tributos, custo-variavel, custo-fixo, despesa-fixa, despesa-venda, etc.)
    else {
        var subcategorias = document.getElementById('sub-' + categoriaId);
        
        if (subcategorias) {
            if (subcategorias.style.display === 'none' || subcategorias.style.display === '') {
                subcategorias.style.display = 'table-row-group';
            } else {
                subcategorias.style.display = 'none';
            }
        }
    }
}

function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(valor);
}

function formatarPercentual(valor) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'percent',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(valor / 100);
}

function formatarCampoMoeda(input) {
    let valor = input.value.replace(/[^\d,]/g, ''); // Remove tudo exceto números e vírgula
    valor = valor.replace(',', '.'); // Troca vírgula por ponto para conversão
    let numero = parseFloat(valor) || 0;
    input.value = numero.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function removerFormatacao(input) {
    let valor = input.value.replace(/[^\d,]/g, '');
    input.value = valor;
}

function obterValorNumerico(input) {
    let valor = input.value.replace(/[^\d,]/g, '');
    valor = valor.replace(',', '.');
    return parseFloat(valor) || 0;
}

// Funções para campos percentuais
function formatarCampoPercentual(input) {
    let valor = input.value.replace(/[^\d,]/g, ''); // Remove tudo exceto números e vírgula
    valor = valor.replace(',', '.'); // Troca vírgula por ponto para conversão
    let numero = parseFloat(valor) || 0;
    input.value = numero.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function removerFormatacaoPercentual(input) {
    let valor = input.value.replace(/[^\d,]/g, '');
    input.value = valor;
}

function obterValorNumericoPercentual(input) {
    let valor = input.value.replace(/[^\d,]/g, '');
    valor = valor.replace(',', '.');
    return parseFloat(valor) || 0;
}



function atualizarCalculosPercentualSubcategoria(inputPercentual) {
    // Obter receita operacional total (base usada para percentuais)
    const receitaBrutaTotal = <?= $total_geral_operacional ?? $total_geral ?>;
    
    // Obter o percentual digitado
    let percentualTexto = inputPercentual.value.replace(/[^\d,]/g, '');
    percentualTexto = percentualTexto.replace(',', '.');
    const percentual = parseFloat(percentualTexto) || 0;
    
    // Calcular o valor absoluto da subcategoria
    const valorAbsoluto = (percentual * receitaBrutaTotal) / 100;
    
    // Atualizar o valor da subcategoria
    const inputId = inputPercentual.id;
    const valorElementId = inputId.replace('perc-', 'valor-sim-');
    const valorElement = document.getElementById(valorElementId);
    
    if (valorElement) {
        valorElement.textContent = 'R$ ' + valorAbsoluto.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    // Determinar o tipo de categoria e recalcular o total da categoria pai
    const tipo = inputPercentual.dataset.tipo;
    
    if (tipo === 'percentual-tributo') {
        recalcularTotalCategoriaPai('tributos', 'valor-sim-tributos', 'perc-tributos');
    } else if (tipo === 'percentual-custo-variavel') {
        recalcularTotalCategoriaPai('custo-variavel', 'valor-sim-custo-variavel', 'perc-custo-variavel');
    } else if (tipo === 'percentual-despesa-venda') {
        recalcularTotalCategoriaPai('despesa-venda', 'valor-sim-despesa-venda', 'perc-despesa-venda');
    }
    
    // Atualizar todos os cálculos gerais
    atualizarCalculos();
}

function recalcularTotalCategoriaPai(prefixo, elementoValorId, elementoPercId) {
    const receitaBrutaTotal = <?= $total_geral_operacional ?? $total_geral ?>;
    let totalCategoria = 0;
    
    // Somar todos os valores das subcategorias
    const subcategorias = document.querySelectorAll('[id^="valor-sim-' + prefixo + '-"]');
    
    subcategorias.forEach(elemento => {
        const valorTexto = elemento.textContent || elemento.innerText;
        const valor = valorTexto.replace(/[^\d,]/g, '').replace(',', '.');
        totalCategoria += parseFloat(valor) || 0;
    });
    
    // Atualizar o valor total da categoria pai
    const elementoValor = document.getElementById(elementoValorId);
    if (elementoValor) {
        elementoValor.textContent = 'R$ ' + totalCategoria.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    // Atualizar o percentual da categoria pai
    const elementoPerc = document.getElementById(elementoPercId);
    if (elementoPerc && receitaBrutaTotal > 0) {
        const percentualCategoria = (totalCategoria / receitaBrutaTotal) * 100;
        elementoPerc.textContent = percentualCategoria.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + '%';
    }
}

function atualizarCalculos() {
    // Buscar todos os inputs de simulação (apenas valores, não percentuais)
    const inputs = document.querySelectorAll('.valor-simulador');
    
    let totalReceitas = 0;
    let totalTributos = 0;
    let totalOperacional = 0;
    let totalNaoOperacional = 0;
    let totalCustoVariavel = 0;
    let totalCustoFixo = 0;
    let totalDespesaFixa = 0;
    let totalDespesaVenda = 0;
    let totalInvestimentoInterno = 0;
    let totalSaidasNaoOperacionais = 0;
    
    // Calcular totais por categoria
    inputs.forEach(input => {
        const valor = obterValorNumerico(input);
        const tipo = input.dataset.tipo;
        const inputId = input.id;
        
        if (tipo === 'receita' || tipo === 'receita-operacional' || tipo === 'receita-nao-operacional') {
            totalReceitas += valor;
            
            // Separar operacional e não operacional
            if (tipo === 'receita-operacional' || (inputId.includes('operacional-') && !inputId.includes('nao-operacional-'))) {
                totalOperacional += valor;
            } else if (tipo === 'receita-nao-operacional' || inputId.includes('nao-operacional-')) {
                totalNaoOperacional += valor;
            }
        } else if (tipo === 'despesa') {
            if (inputId.includes('tributos-')) {
                totalTributos += valor;
            }
        } else if (tipo === 'custo-variavel') {
            totalCustoVariavel += valor;
        } else if (tipo === 'custo-fixo') {
            totalCustoFixo += valor;
        } else if (tipo === 'despesa-fixa') {
            totalDespesaFixa += valor;
        } else if (tipo === 'despesa-venda') {
            totalDespesaVenda += valor;
        } else if (tipo === 'investimento-interno') {
            totalInvestimentoInterno += valor;
        } else if (tipo === 'saidas-nao-operacionais') {
            totalSaidasNaoOperacionais += valor;
        }
    });
    
    // Fallback: se a soma pelos inputs editáveis resultou em 0 (muitos campos podem ser TDs),
    // tentar ler os totais principais diretamente do DOM (operacional + não operacional)
    if (totalReceitas === 0) {
        function parseElementValue(el) {
            if (!el) return 0;
            if (el.value !== undefined) return obterValorNumerico(el);
            const txt = (el.textContent || el.innerText || '').trim();
            if (!txt) return 0;
            const cleaned = txt.replace(/R\$\s*/g, '').replace(/\./g, '').replace(',', '.');
            return parseFloat(cleaned) || 0;
        }

        const elOp = document.getElementById('valor-sim-operacional');
        const elNaoOp = document.getElementById('valor-sim-nao-operacional');

        totalOperacional = parseElementValue(elOp);
        totalNaoOperacional = parseElementValue(elNaoOp);
        totalReceitas = totalOperacional + totalNaoOperacional;
    }

    // CALCULAR VALORES DAS CATEGORIAS PAI BASEADOS NO PERCENTUAL SOBRE FATURAMENTO
    // TRIBUTOS - calcular como percentual sobre faturamento
    let percTributos = 0;
    const elementoPercTributos = document.getElementById('perc-tributos');
    if (elementoPercTributos) {
        const percTexto = elementoPercTributos.textContent || elementoPercTributos.innerText;
        percTributos = parseFloat(percTexto.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
    }
    // TRIBUTOS aplicados sobre a base operacional (RECEITA OPERACIONAL)
    totalTributos = (totalOperacional * percTributos) / 100;
    
    // CUSTO VARIÁVEL - calcular como percentual sobre faturamento
    let percCustoVariavel = 0;
    const elementoPercCustoVariavel = document.getElementById('perc-custo-variavel');
    if (elementoPercCustoVariavel) {
        const percTexto = elementoPercCustoVariavel.textContent || elementoPercCustoVariavel.innerText;
        percCustoVariavel = parseFloat(percTexto.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
    }
    // CUSTO VARIÁVEL aplicado sobre a base operacional
    totalCustoVariavel = (totalOperacional * percCustoVariavel) / 100;
    
    // DESPESAS DE VENDA - calcular como percentual sobre faturamento
    let percDespesaVenda = 0;
    const elementoPercDespesaVenda = document.getElementById('perc-despesa-venda');
    if (elementoPercDespesaVenda) {
        const percTexto = elementoPercDespesaVenda.textContent || elementoPercDespesaVenda.innerText;
        percDespesaVenda = parseFloat(percTexto.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
    }
    // DESPESAS DE VENDA aplicadas sobre a base operacional
    totalDespesaVenda = (totalOperacional * percDespesaVenda) / 100;
    
    // Calcular valores derivados (usando os valores originais por enquanto)
    // Receita Líquida tomada como RECEITA OPERACIONAL menos tributos
    const receitaLiquida = totalOperacional - totalTributos;
    const lucroBruto = receitaLiquida - totalCustoVariavel;
    const lucroLiquido = lucroBruto - totalCustoFixo - totalDespesaFixa - totalDespesaVenda;
    // IMPACTO CAIXA inclui também as RECEITAS NÃO OPERACIONAIS (RN)
    let impactoCaixa = lucroLiquido - totalInvestimentoInterno - totalSaidasNaoOperacionais + totalNaoOperacional;

    // Se um goal-seek foi aplicado, forçar visualmente IMPACTO CAIXA = 0
    try {
        if (window && window.__simulador_goal_seek_applied) {
            impactoCaixa = 0;
        }
    } catch (e) {
        /* ignore */
    }
    
    // Atualizar valores calculados dos grupos principais (valores originais)
    const elementos = {
        // Fazer com que a célula principal mostre APENAS a RECEITA OPERACIONAL
        'valor-sim-receita-bruta': totalOperacional,
        'valor-sim-operacional': totalOperacional,
        'valor-sim-nao-operacional': totalNaoOperacional,
        'valor-sim-tributos': totalTributos,
        'valor-sim-receita-liquida': receitaLiquida,
        'valor-sim-custo-variavel': totalCustoVariavel,
        'valor-sim-lucro-bruto': lucroBruto,
        'valor-sim-custo-fixo': totalCustoFixo,
        'valor-sim-despesa-fixa': totalDespesaFixa,
        'valor-sim-despesa-venda': totalDespesaVenda,
        'valor-sim-lucro-liquido': lucroLiquido,
        'valor-sim-investimento-interno': totalInvestimentoInterno,
        'valor-sim-saidas-nao-operacionais': totalSaidasNaoOperacionais,
        'valor-sim-impacto-caixa': impactoCaixa
    };

    // RECALCULAR CUSTOS VARIÁVEIS BASEADOS NOS PERCENTUAIS QUANDO RECEITA MUDA
    // Verificar se os percentuais das subcategorias variáveis existem e recalcular seus valores absolutos
    const inputsPercentuaisVariaveis = document.querySelectorAll('input[data-tipo^="percentual-"]:not([data-tipo*="fixo"])');
    inputsPercentuaisVariaveis.forEach(input => {
        const percentual = obterValorNumericoPercentual(input);
        if (percentual > 0 && totalOperacional > 0) {
            const novoValorAbsoluto = (totalOperacional * percentual) / 100;
            
            // Atualizar o valor absoluto correspondente
            const valorElementId = input.id.replace('perc-', 'valor-sim-');
            const valorElement = document.getElementById(valorElementId);
            if (valorElement) {
                valorElement.textContent = formatarMoeda(novoValorAbsoluto);
            }
            
            // Se for uma subcategoria, atualizar também o input de valor
            const inputValorId = input.id.replace('perc-', 'valor-');
            const inputValor = document.getElementById(inputValorId);
            if (inputValor) {
                inputValor.value = novoValorAbsoluto.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        }
    });
    
    // Atualizar elementos na DOM
    Object.keys(elementos).forEach(elementId => {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = formatarMoeda(elementos[elementId]);
        } else {
            console.warn(`Elemento não encontrado: ${elementId}`);
        }
    });
    
    // Atualizar percentuais individuais das subcategorias (baseado no novo faturamento total)
    inputs.forEach(input => {
        const valor = obterValorNumerico(input);
        const categoriaId = input.id.replace('valor-sim-', '');
        const percElement = document.getElementById('perc-' + categoriaId);
        
        if (percElement && totalOperacional > 0) {
            const percentual = (valor / totalOperacional) * 100;
            percElement.textContent = formatarPercentual(percentual);
        }
    });
    
    // Atualizar percentuais dos grupos principais (todos baseados na RECEITA OPERACIONAL)
    if (totalOperacional > 0) {
        // RECALCULAR TOTAIS DAS CATEGORIAS PAI SOMANDO AS SUBCATEGORIAS PARA PERCENTUAIS CORRETOS
        let totalTributosReal = 0;
        let totalCustoVariavelReal = 0;
        let totalDespesaVendaReal = 0;
        
        // Somar subcategorias de TRIBUTOS
        const inputsTributosReal = document.querySelectorAll('input[id*="tributos-"]:not([id*="perc-"])');
        inputsTributosReal.forEach(input => {
            const valor = parseFloat(input.value.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
            totalTributosReal += valor;
        });
        
        // Somar subcategorias de CUSTO VARIÁVEL  
        const inputsCustoVariavelReal = document.querySelectorAll('input[id*="custo-variavel-"]:not([id*="perc-"])');
        inputsCustoVariavelReal.forEach(input => {
            const valor = parseFloat(input.value.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
            totalCustoVariavelReal += valor;
        });
        
        // Somar subcategorias de DESPESAS DE VENDA
        const inputsDespesaVendaReal = document.querySelectorAll('input[id*="despesa-venda-"]:not([id*="perc-"])');
        inputsDespesaVendaReal.forEach(input => {
            const valor = parseFloat(input.value.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
            totalDespesaVendaReal += valor;
        });
        
        const percentuaisGrupos = {
            // A principal linha agora representa a RECEITA OPERACIONAL (base)
            'perc-receita-bruta': 100.00,
            'perc-operacional': 100.00,
            // Mostrar RECEITAS NÃO OPERACIONAIS como percentual da RECEITA OPERACIONAL
            'perc-nao-operacional': (totalOperacional > 0 ? (totalNaoOperacional / totalOperacional) * 100 : 0),
            'perc-tributos': (totalTributosReal / totalOperacional) * 100,
            // Os percentuais abaixo são calculados SOBRE A RECEITA OPERACIONAL
            'perc-receita-liquida': (receitaLiquida / totalOperacional) * 100,
            'perc-custo-variavel': (totalCustoVariavelReal / totalOperacional) * 100,
            'perc-lucro-bruto': (lucroBruto / totalOperacional) * 100,
            'perc-custo-fixo': (totalCustoFixo / totalOperacional) * 100,
            'perc-despesa-fixa': (totalDespesaFixa / totalOperacional) * 100,
            'perc-despesa-venda': (totalDespesaVendaReal / totalOperacional) * 100,
            'perc-lucro-liquido': (lucroLiquido / totalOperacional) * 100,
            'perc-investimento-interno': (totalInvestimentoInterno / totalOperacional) * 100,
            'perc-saidas-nao-operacionais': (totalSaidasNaoOperacionais / totalOperacional) * 100,
            'perc-impacto-caixa': (impactoCaixa / totalOperacional) * 100
        };
        
        Object.keys(percentuaisGrupos).forEach(elementId => {
            const element = document.getElementById(elementId);
            // Não sobrescrever campos percentuais editáveis (TRIBUTOS, CUSTO VARIÁVEL, DESPESAS DE VENDA)
            const camposEditaveis = ['perc-tributos', 'perc-custo-variavel', 'perc-despesa-venda'];
            
            if (element && !camposEditaveis.includes(elementId)) {
                // Se é um campo só leitura, atualiza o texto
                if (element.tagName !== 'INPUT') {
                    element.textContent = formatarPercentual(percentuaisGrupos[elementId]);
                }
            }
        });
    }
    
    // Garantir que a RECEITA BRUTA sempre seja 100% na simulação
    const percReceitaBruta = document.getElementById('perc-receita-bruta');
    if (percReceitaBruta) {
        percReceitaBruta.textContent = '100,00%';
    }
    // Expor totais consolidados para uso por outras funções (goal-seek, etc.)
    try {
        window.simulador_totais = {
            totalReceitas: totalReceitas,
            totalOperacional: totalOperacional,
            totalNaoOperacional: totalNaoOperacional,
            totalTributos: totalTributos,
            totalCustoVariavel: totalCustoVariavel,
            totalCustoFixo: totalCustoFixo,
            totalDespesaFixa: totalDespesaFixa,
            totalDespesaVenda: totalDespesaVenda,
            totalInvestimentoInterno: totalInvestimentoInterno,
            totalSaidasNaoOperacionais: totalSaidasNaoOperacionais,
            receitaLiquida: receitaLiquida,
            lucroBruto: lucroBruto,
            lucroLiquido: lucroLiquido,
            impactoCaixa: impactoCaixa
        };
        window.__simulador_last_update = Date.now();
    } catch (e) {
        console.warn('Não foi possível setar window.simulador_totais', e);
    }
    
    // FORÇAR RECÁLCULO DOS TOTAIS DE GRUPOS BASEADOS NAS SUBCATEGORIAS
    // Recalcular TRIBUTOS somando as subcategorias
    recalcularTotalGrupo('tributos', 'valor-sim-tributos');
    // Recalcular CUSTO VARIÁVEL somando as subcategorias  
    recalcularTotalGrupo('custo-variavel', 'valor-sim-custo-variavel');
    // Recalcular DESPESAS DE VENDA somando as subcategorias
    recalcularTotalGrupo('despesa-venda', 'valor-sim-despesa-venda');
    // Atualizar percentuais ao lado dos valores absolutos (pai + subcategoria)
    try { if (typeof atualizarPercentuaisAoLado === 'function') atualizarPercentuaisAoLado(); } catch(e) { /* ignore */ }
}

// Função auxiliar para recalcular total de um grupo baseado nas subcategorias
function recalcularTotalGrupo(grupoPrefix, totalElementId) {
    const inputsGrupo = document.querySelectorAll(`input[id*="${grupoPrefix}-"]:not([id*="perc-"])`);
    let novoTotal = 0;
    
    inputsGrupo.forEach(input => {
        const valor = parseFloat(input.value.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
        novoTotal += valor;
    });
    
    // Atualizar o elemento total do grupo
    const totalElement = document.getElementById(totalElementId);
    if (totalElement && novoTotal > 0) {
        totalElement.textContent = formatarMoeda(novoTotal);
    }
}

// Atualiza, ao lado do valor absoluto exibido nas células `valor-base-*`,
// dois percentuais baseados no faturamento total: (categoria pai) e (subcategoria).
function atualizarPercentuaisAoLado() {
    function parseMoney(el) {
        if (!el) return 0;
        const txt = (el.value !== undefined ? (el.value || el.getAttribute('value') || '') : (el.textContent || el.innerText || '')).toString();
        if (!txt) return 0;
        const cleaned = txt.replace(/[^0-9,\-\.]/g, '').replace(/\./g, '').replace(',', '.');
        return parseFloat(cleaned) || 0;
    }

    // Obter faturamento atual (usar valor-sim-receita-bruta se presente, fallback para valor-base-receita-bruta)
    const totalEl = document.getElementById('valor-sim-receita-bruta') || document.getElementById('valor-base-receita-bruta');
    const totalGeral = parseMoney(totalEl) || 1;

    // For each 'valor-base-*' cell, write a single percent into the META column (2nd cell of the row).
    document.querySelectorAll('td[id^="valor-base-"]').forEach(td => {
        const id = td.id;

        // Compute numeric value for this cell
        const itemVal = parseMoney(td);

        // Determine parent total: if id ends with -<index> then parent id is the prefix, otherwise this is a parent row
        const m = id.match(/(.+)-\d+$/);
        let parentVal = itemVal;
        if (m) {
            const parentId = m[1];
            const parentEl = document.getElementById(parentId);
            parentVal = parseMoney(parentEl);
        }

        // Compute percentage to display: for subcategory show item% of faturamento; for parent show parent% of faturamento
        const pctToShow = totalGeral > 0 ? (m ? (itemVal / totalGeral) * 100 : (parentVal / totalGeral) * 100) : 0;

        // Find the row and its META cell (2nd td)
        const tr = td.closest('tr');
        if (!tr) return;
        const metaCell = tr.cells && tr.cells.length >= 2 ? tr.cells[1] : null;
        if (!metaCell) return;

        // Append a small percent element to the META cell, preserving existing content
        const formatted = pctToShow.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
        // remove old percent if present
        const oldPct = metaCell.querySelector('.simulador-inline-pct');
        if (oldPct) oldPct.remove();
        const pctSpan = document.createElement('span');
        pctSpan.className = 'simulador-inline-pct';
        pctSpan.style.fontSize = '0.75rem';
        pctSpan.style.color = '#9ca3af';
        pctSpan.style.marginLeft = '8px';
        pctSpan.textContent = formatted;
        metaCell.appendChild(pctSpan);
    });
}

// Atualizar as anotações quando a página carrega
document.addEventListener('DOMContentLoaded', function(){
    try { atualizarPercentuaisAoLado(); } catch(e) { /* ignore */ }
});

function resetarSimulacao() {
    // Resetar campos de valor normais (editáveis)
    const inputs = document.querySelectorAll('.valor-simulador');
    
    inputs.forEach(input => {
        const valorBase = parseFloat(input.dataset.valorBase) || 0;
        input.value = valorBase.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    });
    
    // Resetar campos percentuais das subcategorias (TRIBUTOS, CUSTO VARIÁVEL, DESPESAS DE VENDA)
    const inputsPercentuaisSubcategorias = document.querySelectorAll('input[data-tipo^="percentual-"]');
    
    inputsPercentuaisSubcategorias.forEach(input => {
        const valorBase = parseFloat(input.dataset.valorBase) || 0;
        const receitaBrutaOriginal = <?= $total_geral_operacional ?? $total_geral ?>;
        
        // Restaurar o percentual original
        if (receitaBrutaOriginal > 0) {
            const percentualOriginal = (valorBase / receitaBrutaOriginal) * 100;
            input.value = percentualOriginal.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            
            // Restaurar o valor absoluto correspondente
            const inputId = input.id;
            const valorElementId = inputId.replace('perc-', 'valor-sim-');
            const valorElement = document.getElementById(valorElementId);
            
            if (valorElement) {
                valorElement.textContent = 'R$ ' + valorBase.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        }
    });
    
    // Recalcular totais das categorias pai
    recalcularTotalCategoriaPai('tributos', 'valor-sim-tributos', 'perc-tributos');
    recalcularTotalCategoriaPai('custo-variavel', 'valor-sim-custo-variavel', 'perc-custo-variavel');
    recalcularTotalCategoriaPai('despesa-venda', 'valor-sim-despesa-venda', 'perc-despesa-venda');
    
    // Atualizar todos os cálculos
    atualizarCalculos();
}

function calcularPontoEquilibrio() {
    // GARANTIR que todos os cálculos estejam atualizados ANTES de calcular o ponto de equilíbrio
    atualizarCalculos();
    
    // Aguardar um pequeno delay para garantir que a DOM foi atualizada
    setTimeout(() => {
        calcularPontoEquilibrioInterno();
    }, 100);
}

function calcularPontoEquilibrioInterno() {
    // Usar a mesma lógica eficaz do DRE que já funciona
    // Fórmula: Fluxo(R) = alpha * R + gamma = 0
    // R = -gamma / alpha
    
    // Função para extrair valor dos campos simulador (suporta INPUT/TEXTAREA e células)
    function extrairValor(elementId) {
        const element = document.getElementById(elementId);
        if (!element) return 0;

        // Se for um INPUT ou TEXTAREA, ler .value (usuário edita aqui)
        if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
            const raw = element.value || element.getAttribute('value') || '';
            if (!raw) return 0;
            // Limpar formatação BR: "1.234,56" -> "1234.56"
            const cleaned = raw.toString().replace(/R\$\s*/g, '').replace(/\./g, '').replace(',', '.').replace(/[^0-9\.-]/g, '');
            return parseFloat(cleaned) || 0;
        }

        // Caso contrário, ler texto da célula (TD)
        const texto = element.textContent || element.innerText || '';
        if (!texto) return 0;
        const cleaned = texto.toString().replace(/R\$\s*/g, '').replace(/\./g, '').replace(',', '.').replace(/[^0-9\.-]/g, '');
        return parseFloat(cleaned) || 0;
    }
    
    // Preferir valores consolidados (quando disponíveis) para garantir que
    // inputs editados pelo usuário (ex: RECEITAS NÃO OPERACIONAIS) sejam usados.
    const simulTotais = window.simulador_totais || null;

    // Valores absolutos: preferir consolidated totals, fallback para DOM
    const tributos = simulTotais ? parseFloat(simulTotais.totalTributos || 0) : extrairValor('valor-sim-tributos');
    const custoVariavel = simulTotais ? parseFloat(simulTotais.totalCustoVariavel || 0) : extrairValor('valor-sim-custo-variavel');
    const despesaVenda = simulTotais ? parseFloat(simulTotais.totalDespesaVenda || 0) : extrairValor('valor-sim-despesa-venda');

    // Base operacional (RECEITA OPERACIONAL)
    const baseOperacional = simulTotais ? parseFloat(simulTotais.totalOperacional || 0) : extrairValor('valor-sim-operacional');

    // Receitas não operacionais (RN)
    const receitaNaoOp = simulTotais ? parseFloat(simulTotais.totalNaoOperacional || 0) : extrairValor('valor-sim-nao-operacional');

    // Calcular percentuais (frações de 0 a 1) com base na RECEITA OPERACIONAL
    const t = baseOperacional > 0 ? tributos / baseOperacional : 0;
    const cv = baseOperacional > 0 ? custoVariavel / baseOperacional : 0;
    const dv = baseOperacional > 0 ? despesaVenda / baseOperacional : 0;
    
    // Custos fixos
    const CF = extrairValor('valor-sim-custo-fixo');
    const DF = extrairValor('valor-sim-despesa-fixa');
    const II = extrairValor('valor-sim-investimento-interno');
    const SNO = extrairValor('valor-sim-saidas-nao-operacionais');
    
    // Receitas/saldos não operacionais (saldo usado apenas para referência)
    const saldoNaoOp = receitaNaoOp - SNO; // receita não op - saídas não op
    
    // Fórmula baseada na hierarquia exata: IMPACTO CAIXA = 0
    // IMPACTO CAIXA = LUCRO LÍQUIDO - INVESTIMENTO INTERNO - SAÍDAS NÃO OP
    // LUCRO LÍQUIDO = RECEITA BRUTA - TRIBUTOS - CUSTO VARIÁVEL - CUSTO FIXO - DESPESA FIXA - DESPESAS VENDA
    // 0 = RECEITA BRUTA×(1 - t - cv - dv) - CF - DF - II - SNO
    // RECEITA BRUTA×(1 - t - cv - dv) = CF + DF + II + SNO
    
    const custosVariaveisPorcentual = t + cv + dv; // % sobre receita operacional
    // Numerador: CF + DF + II + SNO - RN (RN reduz o montante que precisamos gerar operacionalmente)
    const numerator = CF + DF + II + SNO - receitaNaoOp;
    const alpha = 1 - custosVariaveisPorcentual; // margem disponível
    // Receita operacional necessária (R_op) para zerar IMPACTO CAIXA
    const receitaOperacionalNecessaria = numerator / alpha;
    
    if (Math.abs(alpha) < 0.0001) {
        alert('❌ Margem insuficiente! Os custos variáveis somam praticamente 100% da receita.');
        return;
    }
    
    if (!isFinite(receitaOperacionalNecessaria) || receitaOperacionalNecessaria <= 0) {
        alert('❌ Não existe receita operacional positiva que zere o IMPACTO CAIXA com os parâmetros atuais.');
        return;
    }

    // Receita bruta total necessária (operacional + não operacional)
    const receitaBrutaTotalNecessaria = receitaOperacionalNecessaria + receitaNaoOp;
    

    
    // Confirmar com o usuário  
    const confirmacao = confirm(
        `🎯 PONTO DE EQUILÍBRIO - IMPACTO CAIXA = 0\n\n` +
        `📊 RECEITA OPERACIONAL NECESSÁRIA:\n` +
        `R$ ${receitaOperacionalNecessaria.toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n\n` +
        `📈 RECEITA BRUTA TOTAL:\n` +
        `R$ ${receitaBrutaTotalNecessaria.toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n\n` +
        `📊 CUSTOS A COBRIR:\n` +
        `• Custo Fixo: R$ ${CF.toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n` +
        `• Despesa Fixa: R$ ${DF.toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n` +
        `• Investimento Interno: R$ ${II.toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n` +
        `• Saídas Não Op.: R$ ${SNO.toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n` +
        `➖ Receita Não Op.: R$ ${receitaNaoOp.toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n` +
        `� CUSTOS FIXOS: R$ ${(CF + DF + II).toLocaleString('pt-BR', {minimumFractionDigits: 2})}\n` +
        `📊 CUSTOS VARIÁVEIS:\n` +
        `  • Tributos: ${(t * 100).toFixed(2)}%\n` +
        `  • Custo Variável: ${(cv * 100).toFixed(2)}%\n` +
        `  • Despesas Venda: ${(dv * 100).toFixed(2)}%\n` +
        `  • Margem Líquida: ${(alpha * 100).toFixed(2)}%\n\n` +
        `⚖️ Resultado: IMPACTO CAIXA = R$ 0,00\n\n` +
        `Deseja aplicar estes valores ao simulador?`
    );
    
    if (!confirmacao) return;
    
    // Aplicar os valores calculados
    // Aplicar receita operacional calculada (como no DRE)
    const inputReceitaOperacional = document.getElementById('valor-sim-operacional');
    if (inputReceitaOperacional) {
        inputReceitaOperacional.value = receitaOperacionalNecessaria.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        // Disparar evento para recalcular (como no DRE)
        inputReceitaOperacional.dispatchEvent(new Event('input', { bubbles: true }));
    }
    
    // Recalcular tudo
    atualizarCalculos();
    
    // Mostrar resultado final
    setTimeout(() => {
        const impactoCaixaElement = document.getElementById('valor-sim-impacto-caixa');
        const impactoCaixaValor = impactoCaixaElement ? impactoCaixaElement.textContent : '';
        const lucroLiquidoElement = document.getElementById('valor-sim-lucro-liquido');
        const lucroLiquidoValor = lucroLiquidoElement ? lucroLiquidoElement.textContent : '';
        
        alert(`✅ Ponto de equilíbrio aplicado!\n\n` +
              `🎯 IMPACTO CAIXA: ${impactoCaixaValor}\n` +
              `💰 Lucro Líquido: ${lucroLiquidoValor}`);
    }, 500);
}

function aplicarMetaCustoFixo(metaValor) {
    const inputsCustoFixo = document.querySelectorAll('input[data-tipo="custo-fixo"]');
    if (inputsCustoFixo.length === 0) return;
    
    // Distribui a meta proporcionalmente entre as subcategorias
    const valorPorItem = metaValor / inputsCustoFixo.length;
    inputsCustoFixo.forEach(input => {
        input.value = valorPorItem.toFixed(2);
    });
}

function aplicarMetaDespesaFixa(metaValor) {
    const inputsDespesaFixa = document.querySelectorAll('input[data-tipo="despesa-fixa"]');
    if (inputsDespesaFixa.length === 0) return;
    
    const valorPorItem = metaValor / inputsDespesaFixa.length;
    inputsDespesaFixa.forEach(input => {
        input.value = valorPorItem.toFixed(2);
    });
}

function aplicarMetaInvestimentoInterno(metaValor) {
    const inputsInvestimento = document.querySelectorAll('input[data-tipo="investimento-interno"]');
    if (inputsInvestimento.length === 0 || metaValor === 0) return;
    
    const valorPorItem = metaValor / inputsInvestimento.length;
    inputsInvestimento.forEach(input => {
        input.value = valorPorItem.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    });
}

function aplicarMetaSaidasNaoOperacionais(metaValor) {
    const inputsSaidas = document.querySelectorAll('input[data-tipo="saidas-nao-operacionais"]');
    if (inputsSaidas.length === 0 || metaValor === 0) return;
    
    const valorPorItem = metaValor / inputsSaidas.length;
    inputsSaidas.forEach(input => {
        input.value = valorPorItem.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    });
}

function aplicarPercentuaisCustosVariaveis(receitaBrutaTotal, percTributos, percCustoVariavel, percDespesaVenda) {
    // Aplicar percentuais aos TRIBUTOS
    const inputsTributos = document.querySelectorAll('input[data-tipo="percentual-tributo"]');
    if (inputsTributos.length > 0) {
        const percPorItem = (percTributos * 100) / inputsTributos.length;
        inputsTributos.forEach(input => {
            input.value = percPorItem.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        });
    }
    
    // Aplicar percentuais ao CUSTO VARIÁVEL
    const inputsCustoVariavel = document.querySelectorAll('input[data-tipo="percentual-custo-variavel"]');
    if (inputsCustoVariavel.length > 0) {
        const percPorItem = (percCustoVariavel * 100) / inputsCustoVariavel.length;
        inputsCustoVariavel.forEach(input => {
            input.value = percPorItem.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        });
    }
    
    // Aplicar percentuais às DESPESAS DE VENDA
    const inputsDespesaVenda = document.querySelectorAll('input[data-tipo="percentual-despesa-venda"]');
    if (inputsDespesaVenda.length > 0) {
        const percPorItem = (percDespesaVenda * 100) / inputsDespesaVenda.length;
        inputsDespesaVenda.forEach(input => {
            input.value = percPorItem.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        });
    }
    
    // Recalcular totais das categorias pai após aplicar percentuais
    setTimeout(() => {
        // Atualizar cálculos das subcategorias percentuais
        inputsTributos.forEach(input => atualizarCalculosPercentualSubcategoria(input));
        inputsCustoVariavel.forEach(input => atualizarCalculosPercentualSubcategoria(input));
        inputsDespesaVenda.forEach(input => atualizarCalculosPercentualSubcategoria(input));
    }, 100);
}

// Função removida - não mais necessária com a nova lógica do DRE

function salvarSimulacao() {
    const dados = {
        periodo: '<?= $periodo_selecionado ?>',
        simulacao: {}
    };
    
    const inputs = document.querySelectorAll('.valor-simulador');
    inputs.forEach(input => {
        dados.simulacao[input.dataset.categoria] = {
            valorBase: parseFloat(input.dataset.valorBase) || 0,
            valorSimulador: parseFloat(input.value) || 0,
            tipo: input.dataset.tipo
        };
    });
    
    // Salvar no localStorage por enquanto
    localStorage.setItem('simulacao_financeira', JSON.stringify(dados));
    
    alert('Simulação salva com sucesso!');
}

function corrigirInputsFormatacao() {
    // Corrigir todos os inputs para usar formatação brasileira
    const inputs = document.querySelectorAll('.valor-simulador');
    inputs.forEach(input => {
        // Alterar type para text
        input.type = 'text';
        
        // Remover step
        input.removeAttribute('step');
        
        // Adicionar eventos de formatação
        input.addEventListener('focus', function() { removerFormatacao(this); });
        input.addEventListener('blur', function() { formatarCampoMoeda(this); });
        
        // Formatar valor inicial
        const valorBase = parseFloat(input.dataset.valorBase) || 0;
        input.value = valorBase.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    });
}

// Carregar simulação salva ao inicializar
document.addEventListener('DOMContentLoaded', function() {
    // Corrigir formatação dos inputs primeiro
    corrigirInputsFormatacao();
    
    const simulacaoSalva = localStorage.getItem('simulacao_financeira');
    
    if (simulacaoSalva) {
        try {
            const dados = JSON.parse(simulacaoSalva);
            
            if (dados.periodo === '<?= $periodo_selecionado ?>') {
                // Carregar valores salvos
                Object.keys(dados.simulacao).forEach(categoria => {
                    const input = document.querySelector(`input[data-categoria="${categoria}"]`);
                    if (input) {
                        const valor = dados.simulacao[categoria].valorSimulador;
                        input.value = valor.toLocaleString('pt-BR', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                });
                
                atualizarCalculos();
            }
        } catch (e) {
            console.error('Erro ao carregar simulação:', e);
        }
    }
    
    // Configurar o background do conteúdo principal
    const content = document.getElementById('content');
    if (content) {
        content.style.background = '#111827';
        content.style.paddingLeft = '2rem';
    }
    
    // Verificar se o simulador-content precisa ser movido
    const simuladorContent = document.getElementById('simulador-content');
    const content2 = document.getElementById('content');
    
    if (simuladorContent && content2 && !content2.contains(simuladorContent)) {
        content2.appendChild(simuladorContent);
    }
});

// ========= FUNÇÕES DO MODAL DE METAS =========

function abrirModalMetas() {
    const modal = document.getElementById('modalMetas');
    if (modal) {
        modal.classList.remove('hidden');
    }
}

function fecharModalMetas() {
    const modal = document.getElementById('modalMetas');
    if (modal) {
        modal.classList.add('hidden');
        // Limpar seleções
        desmarcarTodosMeses();
    }
}

function selecionarTodosMeses() {
    const checkboxes = document.querySelectorAll('.mes-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
}

function desmarcarTodosMeses() {
    const checkboxes = document.querySelectorAll('.mes-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
}

function selecionarTodosAnos() {
    const checkboxes = document.querySelectorAll('.ano-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
}

function desmarcarTodosAnos() {
    const checkboxes = document.querySelectorAll('.ano-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
}

function salvarMetasFinanceiras() {
    // Obter anos selecionados
    const anosSelecionados = [];
    const checkboxesAnos = document.querySelectorAll('.ano-checkbox:checked');
    
    if (checkboxesAnos.length === 0) {
        alert('⚠️ Selecione pelo menos um ano para salvar as metas!');
        return;
    }
    
    checkboxesAnos.forEach(checkbox => {
        anosSelecionados.push(checkbox.value);
    });
    
    // Obter meses selecionados
    const mesesSelecionados = [];
    const checkboxes = document.querySelectorAll('.mes-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('⚠️ Selecione pelo menos um mês para salvar as metas!');
        return;
    }
    
    checkboxes.forEach(checkbox => {
        mesesSelecionados.push(checkbox.value);
    });
    
    // Coletar dados financeiros atuais do simulador
    const metasFinanceiras = coletarMetasDoSimulador();
    
    // Debug: Mostrar dados coletados
    console.log('📊 Metas coletadas:', metasFinanceiras);
    console.log('📅 Anos selecionados:', anosSelecionados);
    
    if (metasFinanceiras.length === 0) {
        alert('⚠️ Nenhuma meta com valor foi encontrada no simulador!');
        return;
    }
    
    // Separar categorias pai e subcategorias para debug
    const categoriasPai = metasFinanceiras.filter(meta => meta.subcategoria === '');
    const subcategorias = metasFinanceiras.filter(meta => meta.subcategoria !== '');
    
    // Preview das categorias pai
    let previewMetas = 'CATEGORIAS PAI:\n';
    categoriasPai.slice(0, 5).forEach(meta => {
        previewMetas += `• ${meta.categoria}: R$ ${meta.meta.toFixed(2)}\n`;
    });
    
    if (categoriasPai.length > 5) {
        previewMetas += `• ... e mais ${categoriasPai.length - 5} categorias pai\n`;
    }
    
    // Preview das subcategorias
    previewMetas += '\nSUBCATEGORIAS:\n';
    subcategorias.slice(0, 5).forEach(meta => {
        previewMetas += `• ${meta.categoria} → ${meta.subcategoria}: R$ ${meta.meta.toFixed(2)}\n`;
    });
    
    if (subcategorias.length > 5) {
        previewMetas += `• ... e mais ${subcategorias.length - 5} subcategorias\n`;
    }
    
    // Confirmar com usuário
    const totalCombinacoes = anosSelecionados.length * mesesSelecionados.length;
    const confirmacao = confirm(
        `🎯 SALVAR METAS FINANCEIRAS\n\n` +
        `📅 Anos: ${anosSelecionados.join(', ')}\n` +
        `📅 Meses: ${mesesSelecionados.length} selecionado(s)\n` +
        `📊 Total de combinações: ${totalCombinacoes} (anos × meses)\n` +
        `📊 Metas encontradas: ${metasFinanceiras.length}\n\n` +
        `Dados coletados da coluna "Valor Simulador (R$)":\n` +
        previewMetas +
        `\nDeseja salvar essas metas na base de dados?`
    );
    
    if (!confirmacao) return;
    
    // Enviar dados para salvamento
    enviarMetasParaServidor(mesesSelecionados, metasFinanceiras, anosSelecionados);
}

function coletarMetasDoSimulador() {
    const metas = [];
    
    // CATEGORIAS PAI: Usar IDs diretos dos elementos
    const categoriasPrincipais = [
        { id: 'valor-sim-receita-bruta', nome: 'RECEITA BRUTA', percId: 'perc-receita-bruta' },
        { id: 'valor-sim-operacional', nome: 'RECEITAS OPERACIONAIS', percId: 'perc-operacional' },
        { id: 'valor-sim-nao-operacional', nome: 'RECEITAS NÃO OPERACIONAIS', percId: 'perc-nao-operacional' },
        { id: 'valor-sim-tributos', nome: 'TRIBUTOS', percId: 'perc-tributos' },
        { id: 'valor-sim-receita-liquida', nome: 'RECEITA LÍQUIDA', percId: 'perc-receita-liquida' },
        { id: 'valor-sim-custo-variavel', nome: 'CUSTO VARIÁVEL', percId: 'perc-custo-variavel' },
        { id: 'valor-sim-lucro-bruto', nome: 'LUCRO BRUTO', percId: 'perc-lucro-bruto' },
        { id: 'valor-sim-custo-fixo', nome: 'CUSTO FIXO', percId: 'perc-custo-fixo' },
        { id: 'valor-sim-despesa-fixa', nome: 'DESPESA FIXA', percId: 'perc-despesa-fixa' },
        { id: 'valor-sim-despesa-venda', nome: 'DESPESAS DE VENDA', percId: 'perc-despesa-venda' },
        { id: 'valor-sim-lucro-liquido', nome: 'LUCRO LÍQUIDO', percId: 'perc-lucro-liquido' },
        { id: 'valor-sim-investimento-interno', nome: 'INVESTIMENTO INTERNO', percId: 'perc-investimento-interno' },
        { id: 'valor-sim-saidas-nao-operacionais', nome: 'SAÍDAS NÃO OPERACIONAIS', percId: 'perc-saidas-nao-operacionais' },
        { id: 'valor-sim-impacto-caixa', nome: 'IMPACTO CAIXA', percId: 'perc-impacto-caixa' }
    ];
    
    categoriasPrincipais.forEach(cat => {
        const elementoValor = document.getElementById(cat.id);
        const elementoPerc = document.getElementById(cat.percId);
        
        if (elementoValor) {
            // Extrair VALOR SIMULADOR
            let textoValor = '';
            if (elementoValor.tagName === 'INPUT') {
                textoValor = elementoValor.value;
            } else {
                textoValor = elementoValor.textContent || elementoValor.innerText || '';
            }
            
            let valorMeta = 0;
            if (textoValor) {
                // Parsing para formato brasileiro: R$ 1.234,56
                const valorLimpo = textoValor.replace(/R\$\s*/, '').replace(/\./g, '').replace(',', '.');
                valorMeta = parseFloat(valorLimpo) || 0;
            }
            
            // Extrair PERCENTUAL
            let percentual = 0;
            if (elementoPerc) {
                const textoPerc = elementoPerc.textContent || elementoPerc.innerText || '';
                if (textoPerc.includes('%')) {
                    const percLimpo = textoPerc.replace(/[^\d,]/g, '').replace(',', '.');
                    percentual = parseFloat(percLimpo) || 0;
                }
            }
            
            // CATEGORIA PAI (sem subcategoria)
            metas.push({
                categoria: cat.nome,         // Campo CATEGORIA na fmetastap
                subcategoria: '',            // Vazio para categoria pai
                meta: valorMeta,             // Campo META na fmetastap
                percentual: percentual       // Campo PERCENTUAL na fmetastap
            });
            
            // SOLUÇÃO ESPECÍFICA: Se for DESPESAS DE VENDA, criar subcategoria COMISSÃO automaticamente
            if (cat.nome === 'DESPESAS DE VENDA' && valorMeta > 0) {
                metas.push({
                    categoria: 'DESPESAS DE VENDA',  // Campo CATEGORIA na fmetastap
                    subcategoria: 'COMISSÃO',        // Campo SUBCATEGORIA na fmetastap
                    meta: valorMeta,                 // Mesmo valor da categoria pai
                    percentual: percentual           // Mesmo percentual da categoria pai
                });
            }
        }
    });
    
    // SUBCATEGORIAS: Das tabelas expandidas
    const tabelasSubcategorias = [
        { id: 'sub-custo-fixo', categoriaPai: 'CUSTO FIXO' },
        { id: 'sub-tributos', categoriaPai: 'TRIBUTOS' },
        { id: 'sub-custo-variavel', categoriaPai: 'CUSTO VARIÁVEL' },
        { id: 'sub-despesa-fixa', categoriaPai: 'DESPESA FIXA' },
        { id: 'sub-despesas-venda', categoriaPai: 'DESPESAS DE VENDA' },
        { id: 'sub-investimento-interno', categoriaPai: 'INVESTIMENTO INTERNO' },
        { id: 'sub-saidas-nao-operacionais', categoriaPai: 'SAÍDAS NÃO OPERACIONAIS' }
    ];
    
    tabelasSubcategorias.forEach(tabela => {
        const elemento = document.getElementById(tabela.id);
        if (!elemento) return;
        
        const linhas = elemento.querySelectorAll('tr');
        
        linhas.forEach(linha => {
            const celulas = linha.querySelectorAll('td');
            if (celulas.length < 3) return;
            
            // Primeira célula = nome da subcategoria
            const nomeSubcategoria = celulas[0].textContent.trim();
            if (!nomeSubcategoria) return;
            
            let valorMeta = 0;
            let percentual = 0;
            
            // ABORDAGEM HÍBRIDA: IDs específicos + busca por posição
            
            // 1. Tentar por IDs conhecidos primeiro
            const elementosComId = linha.querySelectorAll('[id]');
            elementosComId.forEach(elemento => {
                const id = elemento.id;
                
                if (id.includes('valor-sim-')) {
                    let textoValor = '';
                    if (elemento.tagName === 'INPUT') {
                        textoValor = elemento.value;
                    } else {
                        textoValor = elemento.textContent || elemento.innerText || '';
                    }
                    
                    if (textoValor.includes('R$')) {
                        const valorLimpo = textoValor.replace(/R\$\s*/, '').replace(/\./g, '').replace(',', '.');
                        valorMeta = parseFloat(valorLimpo) || 0;
                    }
                }
                
                if (id.includes('perc-')) {
                    let textoPerc = '';
                    if (elemento.tagName === 'INPUT') {
                        textoPerc = elemento.value;
                    } else {
                        textoPerc = elemento.textContent || elemento.innerText || '';
                    }
                    
                    if (textoPerc) {
                        const percLimpo = textoPerc.replace(/[^\d,]/g, '').replace(',', '.');
                        percentual = parseFloat(percLimpo) || 0;
                    }
                }
            });
            
            // 2. BUSCA POR POSIÇÃO: Para tabelas com estrutura padrão
            // Baseado na estrutura: [Nome] [Meta] [Valor Base] [Valor Sim] [Percentual]
            if (celulas.length >= 5) {
                // Última célula geralmente é o percentual (pode ser input ou texto)
                const ultimaCelula = celulas[celulas.length - 1];
                
                // Buscar input dentro da célula (para percentuais editáveis)
                const inputPerc = ultimaCelula.querySelector('input');
                if (inputPerc && percentual === 0) {
                    const valuePerc = inputPerc.value || '';
                    if (valuePerc) {
                        const percLimpo = valuePerc.replace(/[^\d,]/g, '').replace(',', '.');
                        percentual = parseFloat(percLimpo) || 0;
                    }
                }
                
                // Se não há input, pegar texto da célula
                if (percentual === 0) {
                    const textoPerc = ultimaCelula.textContent.trim();
                    if (textoPerc.includes('%')) {
                        const percLimpo = textoPerc.replace(/[^\d,]/g, '').replace(',', '.');
                        percentual = parseFloat(percLimpo) || 0;
                    }
                }
                
                // Penúltima célula geralmente é o valor simulador
                if (valorMeta === 0 && celulas.length >= 4) {
                    const penultimaCelula = celulas[celulas.length - 2];
                    const textoValor = penultimaCelula.textContent.trim();
                    if (textoValor.includes('R$')) {
                        const valorLimpo = textoValor.replace(/R\$\s*/, '').replace(/\./g, '').replace(',', '.');
                        valorMeta = parseFloat(valorLimpo) || 0;
                    }
                }
            }
            
            // 3. FALLBACK: Busca por texto em todas as células
            if (valorMeta === 0 || percentual === 0) {
                for (let i = 1; i < celulas.length; i++) {
                    const texto = celulas[i].textContent.trim();
                    
                    // Procurar valor monetário
                    if (texto.includes('R$') && valorMeta === 0) {
                        const valorLimpo = texto.replace(/R\$\s*/, '').replace(/\./g, '').replace(',', '.');
                        valorMeta = parseFloat(valorLimpo) || 0;
                    }
                    
                    // Procurar percentual
                    if (texto.includes('%') && !texto.includes('R$') && percentual === 0) {
                        const percLimpo = texto.replace(/[^\d,]/g, '').replace(',', '.');
                        percentual = parseFloat(percLimpo) || 0;
                    }
                }
            }
            

            
            // SUBCATEGORIA
            metas.push({
                categoria: tabela.categoriaPai,   // Campo CATEGORIA na fmetastap
                subcategoria: nomeSubcategoria,   // Campo SUBCATEGORIA na fmetastap  
                meta: valorMeta,                  // Campo META na fmetastap
                percentual: percentual            // Campo PERCENTUAL na fmetastap
            });
        });
    });
    
    return metas;
    let debugInfo = '🔍 DEBUG COMISSÃO:\n\n';
    
    // Verificar se a tabela sub-despesas-venda existe
    const tabelaDespesasVenda = document.getElementById('sub-despesa-venda');
    if (tabelaDespesasVenda) {
        debugInfo += '✅ Tabela sub-despesa-venda ENCONTRADA\n';
        const linhasDespesas = tabelaDespesasVenda.querySelectorAll('tr');
        debugInfo += `📊 ${linhasDespesas.length} linhas na tabela\n\n`;
        
        debugInfo += '📋 SUBCATEGORIAS ENCONTRADAS:\n';
        linhasDespesas.forEach((linha, index) => {
            const celulas = linha.querySelectorAll('td');
            if (celulas.length > 0) {
                const nomeSubcat = celulas[0].textContent.trim();
                const isComissao = nomeSubcat.toUpperCase().includes('COMISSÃO') || nomeSubcat.toUpperCase().includes('COMISSAO');
                
                debugInfo += `${index + 1}. "${nomeSubcat}"`;
                if (isComissao) debugInfo += ' ⭐ COMISSÃO!';
                debugInfo += '\n';
                
                // Se for comissão, mostrar detalhes das células
                if (isComissao) {
                    debugInfo += '   📱 Células:\n';
                    celulas.forEach((cel, i) => {
                        const texto = cel.textContent.trim();
                        const id = cel.id || 'sem-id';
                        debugInfo += `   ${i}: "${texto}" (${id})\n`;
                    });
                }
            }
        });
    } else {
        debugInfo += '❌ Tabela sub-despesa-venda NÃO ENCONTRADA\n';
    }
    
    // Verificar se COMISSÃO foi coletada nas metas
    const subcategorias = metas.filter(m => m.subcategoria !== '');
    debugInfo += `\n� Total de subcategorias coletadas: ${subcategorias.length}\n`;
    
    const comissaoEncontrada = metas.find(m => m.subcategoria.toUpperCase().includes('COMISSÃO') || m.subcategoria.toUpperCase().includes('COMISSAO'));
    if (comissaoEncontrada) {
        debugInfo += `✅ COMISSÃO nas metas: ${JSON.stringify(comissaoEncontrada, null, 2)}\n`;
    } else {
        debugInfo += '❌ COMISSÃO NÃO ENCONTRADA nas metas\n';
        debugInfo += '\n📋 Subcategorias coletadas:\n';
        subcategorias.forEach((s, i) => {
            debugInfo += `${i + 1}. ${s.categoria} → ${s.subcategoria}\n`;
        });
    }
    
    return metas;
}

function enviarMetasParaServidor(meses, metas, anos) {
    // Mostrar loading
    const btnSalvar = document.querySelector('[onclick="salvarMetasFinanceiras()"]');
    const textoOriginal = btnSalvar.innerHTML;
    btnSalvar.innerHTML = '⏳ Salvando...';
    btnSalvar.disabled = true;
    
    // Preparar dados para envio
    const dados = {
        action: 'salvar_metas',
        meses: meses,
        metas: metas,
        anos: Array.isArray(anos) ? anos : [anos] // Garantir que seja array
    };
    
    fetch('salvar_metas.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(dados),
        signal: AbortSignal.timeout(120000) // 120 segundos de timeout
    })
    .then(response => {
        // Verificar se a resposta é OK
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        // Tentar converter para JSON
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Resposta não é JSON válido:', text);
                throw new Error(`Resposta inválida do servidor: ${text.substring(0, 100)}...`);
            }
        });
    })
    .then(data => {
        btnSalvar.innerHTML = textoOriginal;
        btnSalvar.disabled = false;
        
        if (data.success) {
            alert(
                `✅ Metas salvas com sucesso!\n\n` +
                `📅 Anos: ${data.anos ? data.anos.join(', ') : 'N/A'}\n` +
                `📊 ${data.total_registros} registros salvos\n` +
                `📆 ${data.meses_processados} meses × ${data.anos_processados || 1} anos processados`
            );
            fecharModalMetas();
        } else {
            alert(`❌ Erro ao salvar metas:\n${data.message}`);
        }
    })
    .catch(error => {
        btnSalvar.innerHTML = textoOriginal;
        btnSalvar.disabled = false;
        console.error('Erro completo:', error);
        alert(`❌ Erro de conexão:\n${error.message}`);
    });
}

function fecharModalSeClicarFora(event) {
    if (event.target.id === 'modalMetas') {
        fecharModalMetas();
    }
}

// Fechar modal com ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('modalMetas');
        if (modal && !modal.classList.contains('hidden')) {
            fecharModalMetas();
        }
    }
});


</script>