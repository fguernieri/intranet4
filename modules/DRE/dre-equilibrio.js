(function(){
    'use strict';

    function formatCurrencyBRL(v){
        try{ return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); } catch(e){ return v.toFixed(2); }
    }

    function showResult(msg, isError){
        const container = document.getElementById('dre-error');
        if (!container) {
            alert(msg);
            return;
        }
        container.textContent = msg;
        container.classList.remove('d-none');
        container.classList.toggle('alert-danger', !!isError);
        container.classList.toggle('alert-info', !isError);
    }

    function getParentPercent(parentLabel){
        try{
            const parentRow = (typeof findRowByCategoryLabel === 'function') ? findRowByCategoryLabel(parentLabel) : null;
            if (!parentRow) return 0;
            const parentId = parentRow.getAttribute('data-id');
            if (!parentId) return 0;
            const container = document.getElementById('dre-table-container') || document;
            // Aggregate child contributions: prefer explicit percent inputs, else absolute inputs or descendant sums
            const receitaNow = (typeof obterFaturamentoSimulacao === 'function') ? obterFaturamentoSimulacao() : 0;
            let totalFraction = 0; // fraction of receita (0.0 .. 1.0)
            const childRows = container.querySelectorAll(`tr[data-parent='${parentId}']`);
            childRows.forEach(child => {
                // 1) percent input (explicit)
                const pctInp = child.querySelector('.simulador-percent-input');
                if (pctInp) {
                    totalFraction += (parseFloat(pctInp.value) || 0) / 100.0;
                    return;
                }
                // 2) absolute input on the child
                const valInp = child.querySelector('.simulador-valor-input');
                if (valInp) {
                    const v = parseFloat(valInp.value) || 0;
                    if (receitaNow > 0) {
                        totalFraction += v / receitaNow;
                    } else {
                        // if receita unknown, try percent display as fallback
                        const pctSpan = child.querySelector('.simulador-percentual-display');
                        if (pctSpan) {
                            const txt = pctSpan.textContent.replace('%','').replace(',', '.').trim();
                            const p = parseFloat(txt) || 0;
                            totalFraction += p / 100.0;
                        }
                    }
                    return;
                }
                // 3) no direct inputs: try summing descendants
                const childId = child.getAttribute('data-id');
                if (childId && typeof sumDescendantSimValues === 'function') {
                    const sumDesc = sumDescendantSimValues(childId) || 0;
                    if (receitaNow > 0) totalFraction += sumDesc / receitaNow;
                }
            });

            // If we found nothing (totalFraction === 0), fallback to parent absolute / receita
            if (totalFraction === 0) {
                if (receitaNow > 0) {
                    const parentVal = (typeof getSimValueByLabel === 'function') ? getSimValueByLabel(parentLabel) : 0;
                    return (parseFloat(parentVal) || 0) / receitaNow;
                }
                return 0;
            }
            return totalFraction;
        } catch(e){ console.debug('getParentPercent err', e); return 0; }
    }

    function safeGet(labelVariants){
        if (typeof tryGetSimValue === 'function') return tryGetSimValue(labelVariants);
        if (typeof getSimValueByLabel === 'function') return getSimValueByLabel(labelVariants[0]);
        return 0;
    }

    function calculateRequiredRevenue(){
        const PARENTS = ['TRIBUTOS','CUSTO VARIAVEL','DESPESA DE VENDA'];
        // get percentages
        const t = getParentPercent('TRIBUTOS');
        const cv = getParentPercent('CUSTO VARIAVEL');
        const dv = getParentPercent('DESPESA DE VENDA');

        // constants
        const CF = safeGet(['CUSTO FIXO']);
        const DF = safeGet(['DESPESA FIXA']);
        const II = safeGet(['INVESTIMENTO INTERNO']);
        const AM = safeGet(['AMORTIZACAO', 'AMORTIZAÇÃO']);

        const receitas_nao_op = safeGet(['RECEITAS NAO OPERACIONAIS', 'RECEITA NAO OPERACIONAL']);
        const saidas_nao_op = safeGet(['SAIDAS NAO OPERACIONAIS', 'SAIDAS NAO OPERACIONAIS']);
        const saldo_nao_op = (receitas_nao_op || 0) - (saidas_nao_op || 0);

        // Fluxo(R) = alpha * R + gamma
        // alpha = 1 - (tributos% + custo_variavel% + despesa_venda%)
        // gamma = - (CF + DF + II + AM) + saldo_nao_op
        const alpha = (1 - t - cv - dv);
        const gamma = - ( (CF || 0) + (DF || 0) + (II || 0) + (AM || 0) ) + (saldo_nao_op || 0);

        if (Math.abs(alpha) < 1e-9) {
            return { ok: false, reason: 'Sem solução única (denominador próximo de zero). Reveja percentuais configurados.' };
        }
        const R = - gamma / alpha; // solve alpha*R + gamma = 0
        return { ok: true, R, t, cv, dv, CF, DF, II, AM, saldo_nao_op };
    }

    document.addEventListener('DOMContentLoaded', function(){
        const btn = document.getElementById('btn-ponto-equilibrio');
        if (!btn) return;
        btn.addEventListener('click', function(){
            const res = calculateRequiredRevenue();
            if (!res.ok) {
                showResult('Erro: ' + res.reason, true);
                return;
            }
            const R = res.R;
            if (!isFinite(R) || R <= 0) {
                showResult('Não existe receita operacional positiva que zere o fluxo com os parâmetros atuais (resultado: ' + String(R) + ')', true);
                return;
            }
            const msg = `Receita operacional necessária para Fluxo de Caixa = 0: ${formatCurrencyBRL(R)}`;
            showResult(msg, false);
            // Optionally: set Receita Operacional simulador input to this value to preview
            try {
                const rows = document.querySelectorAll('tr');
                for (const row of rows) {
                    if ((row.textContent||'').toLowerCase().includes('receita operacional')) {
                        const inp = row.querySelector('.simulador-valor-input');
                        if (inp) {
                            inp.value = R.toFixed(2);
                            // trigger input events to recalc
                            inp.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                        break;
                    }
                }
            } catch(e) { console.debug('could not set receita input', e); }
        });
    });

})();
