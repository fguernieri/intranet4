function atualizarValorPorPercentual(input) {
    const percentual = parseFloat(input.value) || 0;
    const categoria = input.dataset.categoria;
    const faturamentoSimulacao = obterFaturamentoSimulacao();
    const novoValor = (percentual / 100) * faturamentoSimulacao; // Dividir por 100 para calcular corretamente

    const row = input.closest('tr');
    const valorDisplay = row.querySelector('.simulador-valor-display');


    row.dataset.simuladorValor = novoValor;

    const percentualDisplay = row.querySelector('.simulador-percentual-display');
    if (percentualDisplay) {
        percentualDisplay.textContent = formatarPercentual(percentual); // Exibir o percentual diretamente
    }
}

function atualizarPercentualPorValor(input) {
    const valor = parseFloat(input.value) || 0;
    const categoria = input.dataset.categoria;
    const faturamentoSimulacao = obterFaturamentoSimulacao();
    const novoPercentual = faturamentoSimulacao > 0 ? (valor / faturamentoSimulacao) * 100 : 0; // Multiplicar por 100 para calcular corretamente

    const row = input.closest('tr');
    const percentualDisplay = row.querySelector('.simulador-percentual-display');


    row.dataset.simuladorValor = valor;
}

function obterFaturamentoSimulacao() {
    const rows = document.querySelectorAll('tr');
    for (let row of rows) {
        if (row.textContent.toLowerCase().includes('receita operacional')) {
            // Prefer input value if present (editable simulador), fallback to display
            const simuladorInput = row.querySelector('.simulador-valor-input');
            if (simuladorInput) {
                const v = parseFloat(simuladorInput.value);
                return isNaN(v) ? 0 : v;
            }
            const simuladorCell = row.querySelector('td:last-child .simulador-valor-display');
            if (simuladorCell) {
                const valorText = simuladorCell.textContent.replace(/[^\d,.-]/g, '').replace(',', '.');
                return parseFloat(valorText) || 0;
            }
        }
    }
    return 0;
}

// Recalcula todos os % Simulador com base no valor atual da Receita Operacional (simulador)
function recalcAllSimPercentuais() {
    const faturSim = obterFaturamentoSimulacao();
    document.querySelectorAll('.simulador-valor-input').forEach(input => {
        const val = parseFloat(input.value) || 0;
        const pctEl = input.closest('tr').querySelector('.simulador-percentual-display');
        if (pctEl) {
            const pct = faturSim > 0 ? (val / faturSim) * 100 : 0;
            pctEl.textContent = formatPercent(pct);
        }
    });
}

// Soma valores simulador dos filhos diretos de um parentId (tr[data-parent=parentId])
function sumChildSimValues(parentId) {
    let sum = 0;
    const values = [];
    document.querySelectorAll(`tr[data-parent='${parentId}'] .simulador-valor-input`).forEach(inp => {
        const v = parseFloat(inp.value) || 0;
        values.push(v);
        sum += v;
    });
    console.debug(`[sumChildSimValues] parent=${parentId} values=`, values, 'sum=', sum);
    return sum;
}

// Propaga soma para o parent chain: atualiza o input do parent e recalcula seu %;
function recalcParentChainFromRow(row) {
    let parentId = row.getAttribute('data-parent');
    while (parentId) {
        const parentRow = document.querySelector(`tr[data-id='${parentId}']`);
        if (!parentRow) break;
        const parentInput = parentRow.querySelector('.simulador-valor-input');
        if (parentInput) {
            const sum = sumChildSimValues(parentId);
            console.debug(`[recalcParentChainFromRow] parent=${parentId} sumChildren=`, sum);
            // Atualiza valor do parent (categoria/subcategoria) com a soma dos filhos
            parentInput.value = sum.toFixed(2);
            // Atualiza o % do parent
            const faturSim = obterFaturamentoSimulacao();
            const pctEl = parentRow.querySelector('.simulador-percentual-display');
            if (pctEl) pctEl.textContent = formatPercent(faturSim > 0 ? (sum / faturSim) * 100 : 0);
        }
        // Sobe um nível
        parentId = parentRow.getAttribute('data-parent');
    }
}

// Retorna true se a row 'row' é descendente do ancestorId
function isDescendantOf(row, ancestorId) {
    let parent = row.getAttribute('data-parent');
    while (parent) {
        if (parent === ancestorId) return true;
        const parentRow = document.querySelector(`tr[data-id='${parent}']`);
        if (!parentRow) break;
        parent = parentRow.getAttribute('data-parent');
    }
    return false;
}

// Soma todos os simulador inputs que são descendentes (qualquer nível) de um ancestorId - implementação recursiva
function sumDescendantSimValues(ancestorId) {
    function sumRecursive(parentId) {
        let total = 0;
        const childRows = document.querySelectorAll(`tr[data-parent='${parentId}']`);
        childRows.forEach(child => {
            const childId = child.getAttribute('data-id');
            // Verifica se este child tem filhos próprios
            const hasGrandChildren = childId && document.querySelectorAll(`tr[data-parent='${childId}']`).length > 0;
            const inp = child.querySelector('.simulador-valor-input');
            if (hasGrandChildren) {
                // se tem filhos, somamos recursivamente os netos (não somamos o input deste nó para evitar double-count)
                total += sumRecursive(childId);
            } else {
                // nó folha: soma seu input (se existir)
                if (inp) total += parseFloat(inp.value) || 0;
            }
        });
        return total;
    }
    const result = sumRecursive(ancestorId);
    console.debug(`[sumDescendantSimValues - recursive fixed] ancestor=${ancestorId} sum=`, result);
    return result;
}

// Atualiza todas as linhas calculadas (class .line-calculada) usando soma dos descendentes
function updateAllCalculatedLines() {
    const faturSim = obterFaturamentoSimulacao();
    document.querySelectorAll('tr.line-calculated').forEach(calcRow => {
        const id = calcRow.getAttribute('data-id');
        if (!id) return;
        const sum = sumDescendantSimValues(id);
        console.debug(`[updateAllCalculatedLines] calcId=${id} sumDescendants=`, sum);
        const input = calcRow.querySelector('.simulador-valor-input');
        const pctEl = calcRow.querySelector('.simulador-percentual-display');
        if (input) input.value = sum.toFixed(2);
        if (pctEl) pctEl.textContent = formatPercent(faturSim > 0 ? (sum / faturSim) * 100 : 0);
    });
}

// Busca o elemento <tr> de uma categoria pelo texto da primeira célula (case-insensitive, token match fallback)
function findRowByCategoryLabel(label) {
    const rows = document.querySelectorAll('tr');
    const target = normalizeLabelText(label);
    // Primeira tentativa: igualdade
    for (const row of rows) {
        const firstTd = row.querySelector('td');
        if (!firstTd) continue;
        const text = normalizeLabelText(firstTd.textContent);
        if (text === target) return row;
    }
    // Segunda tentativa: token match (todas as palavras do target presentes no texto da célula)
    const tokens = target.split(' ').filter(Boolean);
    if (tokens.length === 0) return null;
    for (const row of rows) {
        const firstTd = row.querySelector('td');
        if (!firstTd) continue;
        const text = normalizeLabelText(firstTd.textContent);
        let all = true;
        for (const tok of tokens) {
            if (!text.includes(tok)) { all = false; break; }
        }
        if (all) return row;
    }
    // Não encontrou
    console.debug('[findRowByCategoryLabel] não encontrou label=', label);
    return null;
}

function getSimValueByLabel(label) {
    const row = findRowByCategoryLabel(label);
    if (!row) return 0;
    const inp = row.querySelector('.simulador-valor-input');
    if (inp) return parseFloat(inp.value) || 0;
    // se não houver input, tentar somar descendentes
    const id = row.getAttribute('data-id');
    if (id) return sumDescendantSimValues(id);
    return 0;
}

function setSimValueByLabel(label, value) {
    const row = findRowByCategoryLabel(label);
    if (!row) return;
    const inp = row.querySelector('.simulador-valor-input');
    if (inp) {
        inp.value = (value || 0).toFixed(2);
        const faturSim = obterFaturamentoSimulacao();
        const pctEl = row.querySelector('.simulador-percentual-display');
        if (pctEl) pctEl.textContent = formatPercent(faturSim > 0 ? ((value || 0) / faturSim) * 100 : 0);
    }
}

// Tenta múltiplas variações de label e retorna o primeiro valor não-nulo
function tryGetSimValue(labels) {
    for (const l of labels) {
        const v = getSimValueByLabel(l);
        if (v !== 0) {
            console.debug('[tryGetSimValue] found', l, v);
            return v;
        }
    }
    // se todos zero, retornar o primeiro (mesmo zero) e logar
    console.debug('[tryGetSimValue] nenhuma variação encontrou valor não-zero para', labels);
    return getSimValueByLabel(labels[0]);
}

// Usa set com tentativa em várias variações; grava apenas se encontrou a row
function trySetSimValue(labels, value) {
    for (const l of labels) {
        const row = findRowByCategoryLabel(l);
        if (row) {
            setSimValueByLabel(l, value);
            console.debug('[trySetSimValue] set', l, value);
            return true;
        }
    }
    console.debug('[trySetSimValue] não encontrou nenhuma row para labels', labels);
    return false;
}

// Atualiza computeAndApplyCalculatedSimulations para usar variáveis tolerant
function computeAndApplyCalculatedSimulations() {
    // Sinônimos para robustez
    const SYN = {
        TRIBUTOS: ['TRIBUTOS'],
        RECEITA_OPERACIONAL: ['RECEITA OPERACIONAL', 'RECEITAS OPERACIONAIS'],
        RECEITA_LIQUIDA: ['RECEITA LIQUIDA', 'RECEITA LÍQUIDA'],
        CUSTO_VARIAVEL: ['CUSTO VARIAVEL', 'CUSTO VARIÁVEL'],
        LUCRO_BRUTO: ['LUCRO BRUTO'],
        CUSTO_FIXO: ['CUSTO FIXO'],
        DESPESA_FIXA: ['DESPESA FIXA'],
        DESPESA_VENDA: ['DESPESA DE VENDA', 'DESPESA VENDA'],
        LUCRO_LIQUIDO: ['LUCRO LIQUIDO', 'LUCRO LÍQUIDO'],
        INVESTIMENTO_INTERNO: ['INVESTIMENTO INTERNO'],
        AMORTIZACAO: ['AMORTIZACAO', 'AMORTIZAÇÃO'],
        RECEITAS_NAO_OP: ['RECEITAS NAO OPERACIONAIS', 'RECEITAS NAO-OPERACIONAIS', 'RECEITAS NÃO OPERACIONAIS', 'RECEITA NAO OPERACIONAL'],
        SAIDAS_NAO_OP: ['SAIDAS NAO OPERACIONAIS', 'SAIDAS NÃO OPERACIONAIS', 'SAIDAS NAO-OPERACIONAIS'],
        RETIRADA_LUCRO: ['RETIRADA DE LUCRO', 'RETIRADA LUCRO'],
        SALDO_NAO_OP: ['SALDO NAO OPERACIONAL', 'SALDO NÃO OPERACIONAL'],
        FLUXO_CAIXA: ['FLUXO DE CAIXA']
    };

    const tributos = tryGetSimValue(SYN.TRIBUTOS);
    const receita_oper = tryGetSimValue(SYN.RECEITA_OPERACIONAL);
    const receita_liquida = receita_oper - tributos;

    const custo_variavel = tryGetSimValue(SYN.CUSTO_VARIAVEL);
    const lucro_bruto = receita_liquida - custo_variavel;

    const custo_fixo = tryGetSimValue(SYN.CUSTO_FIXO);
    const despesa_fixa = tryGetSimValue(SYN.DESPESA_FIXA);
    const despesa_venda = tryGetSimValue(SYN.DESPESA_VENDA);
    const lucro_liquido = lucro_bruto - (custo_fixo + despesa_fixa + despesa_venda);

    const investimento_interno = tryGetSimValue(SYN.INVESTIMENTO_INTERNO);
    const amortizacao = tryGetSimValue(SYN.AMORTIZACAO);
    const receitas_nao_op = tryGetSimValue(SYN.RECEITAS_NAO_OP);
    const saidas_nao_op = tryGetSimValue(SYN.SAIDAS_NAO_OP);
    const retirada_lucro = tryGetSimValue(SYN.RETIRADA_LUCRO);

    // SALDO NAO OPERACIONAL = RECEITAS NAO OPERACIONAIS - SAIDAS NAO OPERACIONAIS
    const saldo_nao_op = receitas_nao_op - saidas_nao_op;
    // FLUXO DE CAIXA = LUCRO LIQUIDO - INVESTIMENTO INTERNO + SALDO NAO OPERACIONAL
    const fluxo_caixa = lucro_liquido - investimento_interno + saldo_nao_op;

    // Aplicar com tentativas
    trySetSimValue(SYN.RECEITA_LIQUIDA, receita_liquida);
    trySetSimValue(SYN.LUCRO_BRUTO, lucro_bruto);
    trySetSimValue(SYN.LUCRO_LIQUIDO, lucro_liquido);
    trySetSimValue(SYN.SALDO_NAO_OP, saldo_nao_op);
    trySetSimValue(SYN.FLUXO_CAIXA, fluxo_caixa);

    console.debug('[computeAndApplyCalculatedSimulations - tolerant] computed', {tributos, receita_oper, receita_liquida, custo_variavel, lucro_bruto, custo_fixo, despesa_fixa, despesa_venda, lucro_liquido, investimento_interno, amortizacao, receitas_nao_op, saidas_nao_op, retirada_lucro, saldo_nao_op, fluxo_caixa});
}

// Atualiza todas as linhas calculadas (class .line-calculada) usando soma dos descendentes
function updateAllCalculatedLines() {
    const faturSim = obterFaturamentoSimulacao();
    document.querySelectorAll('tr.line-calculated').forEach(calcRow => {
        const id = calcRow.getAttribute('data-id');
        if (!id) return;
        const sum = sumDescendantSimValues(id);
        console.debug(`[updateAllCalculatedLines] calcId=${id} sumDescendants=`, sum);
        const input = calcRow.querySelector('.simulador-valor-input');
        const pctEl = calcRow.querySelector('.simulador-percentual-display');
        if (input) input.value = sum.toFixed(2);
        if (pctEl) pctEl.textContent = formatPercent(faturSim > 0 ? (sum / faturSim) * 100 : 0);
    });
}

function addSimuladorEvents() {
    // When any simulador value changes, update its % and (if it's Receita) update all
    document.querySelectorAll('.simulador-valor-input').forEach(input => {
        input.addEventListener('input', function() {
            const val = parseFloat(this.value) || 0;
            const pctEl = this.closest('tr').querySelector('.simulador-percentual-display');
            const faturSim = obterFaturamentoSimulacao();
            const row = this.closest('tr');
            // If this is the Receita Operacional row, after input we need to recalc all
            const firstCellText = row.querySelector('td') ? row.querySelector('td').textContent.toLowerCase() : '';
            const isReceita = firstCellText.includes('receita operacional');
            if (isReceita) {
                // Update all percentages based on new denominator
                recalcAllSimPercentuais();
            } else {
                const pct = faturSim > 0 ? (val / faturSim) * 100 : 0;
                if (pctEl) pctEl.textContent = formatPercent(pct);

                // Se este input tem um parent, propaga soma para o parent chain
                recalcParentChainFromRow(row);
            }

            // Após qualquer alteração, atualizar linhas calculadas para refletir os novos valores
            updateAllCalculatedLines();
            // E também aplicar os cálculos específicos (Receita Liquida, Lucro Bruto, etc.)
            computeAndApplyCalculatedSimulations();
        });

        // Optional: when finishing editing (blur), format value (keep numeric)
        input.addEventListener('blur', function() {
            // No-op for now
        });
    });
}

// dre.js - Renderização da tabela DRE sem coluna Simulador

async function fetchDREData(mes, ano) {
    const params = new URLSearchParams();
    if (mes) params.append('mes', mes);
    if (ano) params.append('ano', ano);
    const res = await fetch(`/modules/DRE/api.php?${params.toString()}`);
    const json = await res.json();
    if (json.code !== 200) throw new Error(json.message);
    return json.data;
}

function formatCurrency(valor) {
    return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatPercent(valor) {
    return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
}

function renderDRETable(dre) {
    let html = `<table class="dre-table table table-bordered table-striped">
        <thead>
            <tr>
                <th>Categoria</th>
                <th>Valor</th>
                <th>% Receita</th>
                <th>Média 3M</th>
                <th>% Média Receita</th>
                <th>Simulador</th>
                <th>% Simulador</th>
            </tr>
        </thead>
        <tbody>`;
    dre.forEach((cat, i) => {
        const catId = `cat-${i}`;
        const isCalc = cat.tipo_linha === 'linha_calculada';
        // If this category is Receita Operacional, emphasize it
        const isReceitaCat = String(cat.categoria || '').toLowerCase().includes('receita operacional');
        const catLabelHtml = isReceitaCat ? `<span class="receita-operacional">${(cat.categoria || '')}</span>` : (cat.categoria || '');
        html += `<tr class="level-1 dre-cat${isCalc ? ' line-calculated' : ''}" data-id="${catId}" data-level="1">
            <td><span class="dre-toggle" data-target="${catId}-subs">&#9654;</span> ${catLabelHtml}</td>
            <td>${formatCurrency(cat.valor)}</td>
            <td>${formatPercent(cat.percentual_receita_operacional ?? 0)}</td>
            <td>${formatCurrency(cat.media_3m ?? 0)}</td>
            <td>${formatPercent(cat.percentual_media_receita_operacional ?? 0)}</td>
            <td><input type="number" step="0.01" class="form-control simulador-valor-input" data-cat-id="${catId}" value="${(cat.media_3m ?? 0).toFixed(2)}" readonly /></td>
            <td><span class="simulador-percentual-display">${formatPercent(cat.percentual_media_receita_operacional ?? 0)}</span></td>
        </tr>`;
        if (cat.subcategorias && Array.isArray(cat.subcategorias)) {
            cat.subcategorias.forEach((sub, j) => {
                const subId = `${catId}-sub-${j}`;
                // Preserve previous behavior for subcategories; if the subcategory itself is labeled Receita Operacional, highlight too
                const isReceitaSub = String(sub.subcategoria || '').toLowerCase().includes('receita operacional');
                const subLabelHtml = isReceitaSub ? `<span class="receita-operacional">${(sub.subcategoria || '')}</span>` : (sub.subcategoria || '');
                html += `<tr class="level-2 dre-sub d-none" data-id="${subId}" data-parent="${catId}" data-level="2">
                    <td style="padding-left:2em;"><span class="dre-toggle" data-target="${subId}-contas">&#9654;</span> ${subLabelHtml}</td>
                    <td>${formatCurrency(sub.valor)}</td>
                    <td>${formatPercent(sub.percentual_receita_operacional ?? 0)}</td>
                    <td>${formatCurrency(sub.media_3m ?? 0)}</td>
                    <td>${formatPercent(sub.percentual_media_receita_operacional ?? 0)}</td>
                    <td><input type="number" step="0.01" class="form-control simulador-valor-input" data-parent="${catId}" data-sub-id="${subId}" value="${(sub.media_3m ?? 0).toFixed(2)}" /></td>
                    <td><span class="simulador-percentual-display">${formatPercent(sub.percentual_media_receita_operacional ?? 0)}</span></td>
                </tr>`;
                if (sub.contas && Array.isArray(sub.contas)) {
                    sub.contas.forEach((conta, k) => {
                        // Conta rows: also highlight if description contains Receita Operacional (unlikely but safe)
                        const isReceitaConta = String(conta.descricao_conta || '').toLowerCase().includes('receita operacional');
                        const contaLabelHtml = isReceitaConta ? `<span class="receita-operacional">${(conta.descricao_conta || '')}</span>` : (conta.descricao_conta || '');
                        html += `<tr class="level-3 dre-conta d-none" data-parent="${subId}" data-level="3">
                            <td style="padding-left:3em;">${contaLabelHtml}</td>
                            <td>${formatCurrency(conta.valor)}</td>
                            <td>${formatPercent(conta.percentual_receita_operacional ?? '')}</td>
                            <td>${formatCurrency(conta.media_3m ?? '')}</td>
                            <td>${formatPercent(conta.percentual_media_receita_operacional ?? '')}</td>
                            <td><input type="number" step="0.01" class="form-control simulador-valor-input" data-parent="${subId}" data-conta-id="${subId}-cont-${k}" value="${(conta.media_3m ?? 0).toFixed(2)}" /></td>
                            <td><span class="simulador-percentual-display">${formatPercent(conta.percentual_media_receita_operacional ?? 0)}</span></td>
                        </tr>`;
                    });
                }
            });
        }
    });
    html += '</tbody></table>';
    return html;
}

function addExpandCollapseEvents() {
    document.querySelectorAll('.dre-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const target = this.dataset.target;
            const icon = this;
            // Expand/collapse subcategorias
            if (target && target.endsWith('-subs')) {
                const catId = target.replace('-subs', '');
                const subs = document.querySelectorAll(`[data-parent='${catId}']`);
                const expanded = icon.textContent === '▼';
                subs.forEach(tr => {
                    if (expanded) {
                        tr.classList.add('d-none');
                        // Também recolhe contas
                        const subId = tr.getAttribute('data-id');
                        document.querySelectorAll(`[data-parent='${subId}']`).forEach(c => c.classList.add('d-none'));
                        const subToggle = tr.querySelector('.dre-toggle');
                        if (subToggle) subToggle.textContent = '▶';
                    } else {
                        tr.classList.remove('d-none');
                    }
                });
                icon.textContent = expanded ? '▶' : '▼';
            }
            // Expand/collapse contas
            if (target && target.endsWith('-contas')) {
                const subId = target.replace('-contas', '');
                const contas = document.querySelectorAll(`[data-parent='${subId}']`);
                const expanded = icon.textContent === '▼';
                contas.forEach(tr => {
                    if (expanded) {
                        tr.classList.add('d-none');
                    } else {
                        tr.classList.remove('d-none');
                    }
                });
                icon.textContent = expanded ? '▶' : '▼';
            }
        });
    });
    // Permitir expandir/recolher clicando em qualquer parte da célula da subcategoria
    document.querySelectorAll('.dre-sub td').forEach(td => {
        td.addEventListener('click', function(e) {
            if (!e.target.classList.contains('dre-toggle')) {
                const toggle = this.querySelector('.dre-toggle');
                if (toggle) toggle.click();
            }
        });
    });
}

async function atualizarTabelaDRE() {
    const loading = document.getElementById('dre-loading');
    const error = document.getElementById('dre-error');
    const container = document.getElementById('dre-table-container');
    loading.classList.remove('d-none');
    error.classList.add('d-none');
    container.innerHTML = '';
    try {
        const mes = document.getElementById('dre-mes').value;
        const ano = document.getElementById('dre-ano').value;
        fetchDREData(mes, ano).then(dre => {
            container.innerHTML = renderDRETable(dre);
            addExpandCollapseEvents();
            addSimuladorEvents();
            // After wiring events, recalc percentages to ensure correct values when Receita exists
            recalcAllSimPercentuais();
            // Also update calculated lines so totals reflect current simulator inputs
            updateAllCalculatedLines();
            // Apply computed formulas for calculated rows
            computeAndApplyCalculatedSimulations();
        }).catch(e => { throw e; });
    } catch (e) {
        error.textContent = 'Erro ao carregar dados: ' + e.message;
        error.classList.remove('d-none');
    } finally {
        loading.classList.add('d-none');
    }
}

document.getElementById('dre-filtro-form').addEventListener('submit', function(e) {
    e.preventDefault();
    atualizarTabelaDRE();
});

// Preencher selects de mês e ano
async function getUltimoMesAnoComRegistros() {
    // Busca todos os meses/anos disponíveis na API e retorna o maior
    const res = await fetch('/modules/DRE/api.php?listar_meses=1');
    const json = await res.json();
    if (json.code === 200 && Array.isArray(json.meses) && json.meses.length > 0) {
        // Espera [{mes: 8, ano: 2025}, ...]
        json.meses.sort((a, b) => (a.ano !== b.ano) ? a.ano - b.ano : a.mes - b.mes);
        return json.meses[json.meses.length - 1];
    }
    // fallback: mês/ano atual
    const now = new Date();
    return { mes: now.getMonth() + 1, ano: now.getFullYear() };
}

document.addEventListener('DOMContentLoaded', async () => {
    const mesSelect = document.getElementById('dre-mes');
    const anoSelect = document.getElementById('dre-ano');
    let ultimo = await getUltimoMesAnoComRegistros();
    for (let m = 1; m <= 12; m++) {
        const opt = document.createElement('option');
        opt.value = m;
        opt.textContent = m.toString().padStart(2, '0');
        if (m === ultimo.mes) opt.selected = true;
        mesSelect.appendChild(opt);
    }
    for (let a = ultimo.ano - 5; a <= ultimo.ano; a++) {
        const opt = document.createElement('option');
        opt.value = a;
        opt.textContent = a;
        if (a === ultimo.ano) opt.selected = true;
        anoSelect.appendChild(opt);
    }
    atualizarTabelaDRE();
});

function normalizeLabelText(s) {
    if (!s) return '';
    // remove ícones/toggles e colapsa espaços
    let t = s.replace(/[\u25B6\u25BA\u25B8\u25C0\u25C4\u2190-\u21FF]/g, '');
    // remover acentos
    t = t.normalize('NFD').replace(/\p{Diacritic}/gu, '');
    // manter apenas alfanum e espaços
    t = t.replace(/[^0-9a-zA-Z\s]/g, ' ');
    t = t.replace(/\s+/g, ' ').trim().toLowerCase();
    return t;
}