(function(){
    'use strict';

    // Categories whose subcategories will be percent-driven
    const PARENT_CATEGORIES = ['TRIBUTOS', 'CUSTO VARIAVEL', 'DESPESA DE VENDA'];

    // Debounce helper
    function debounce(fn, wait) {
        let t;
        return function(...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    // Fallback normalize if main script didn't expose it
    function _normalize(s) {
        if (!s) return '';
        try {
            if (typeof normalizeLabelText === 'function') return normalizeLabelText(s);
        } catch (e) {}
        // basic fallback: remove diacritics, non-alnum, collapse spaces
        let t = s.normalize ? s.normalize('NFD').replace(/\p{Diacritic}/gu, '') : s;
        t = t.replace(/[^0-9a-zA-Z\s]/g, ' ');
        t = t.replace(/\s+/g, ' ').trim().toLowerCase();
        return t;
    }

    // Update absolute inputs based on percent inputs and receita (percent-driven rows)
    function updateAbsoluteFromPercent(container) {
        const receita = (typeof obterFaturamentoSimulacao === 'function') ? obterFaturamentoSimulacao() : 0;
        container.querySelectorAll('tr[data-percent-driven="true"]').forEach(row => {
            const valInput = row.querySelector('.simulador-valor-input');
            const pctInput = row.querySelector('.simulador-percent-input');
            if (!pctInput || !valInput) return;
            const pct = parseFloat(pctInput.value) || 0;
            const newVal = receita > 0 ? (pct / 100) * receita : 0;
            const curVal = parseFloat(valInput.value) || 0;
            if (Math.abs(curVal - newVal) > 0.005) {
                valInput.value = newVal.toFixed(2);
                // dispatch input to trigger existing recalculation handlers
                try { valInput.dispatchEvent(new Event('input', { bubbles: true })); } catch(e) { }
            }
        });
    }

    // Initialize percent-driven inputs for subcategories under configured parents
    function initPercentDrivenOnce(container) {
        if (!container) container = document.getElementById('dre-table-container');
        if (!container) return;
        // find category rows (level-1)
        container.querySelectorAll('tr.level-1').forEach(catRow => {
            const td = catRow.querySelector('td');
            if (!td) return;
            const catText = td.textContent || '';
            const normCatText = _normalize(catText);
            for (const parentLabel of PARENT_CATEGORIES) {
                const normParent = _normalize(parentLabel);
                if (!normParent) continue;
                if (normCatText.includes(normParent)) {
                    const parentId = catRow.getAttribute('data-id');
                    if (!parentId) continue;
                    // mark and convert direct children (subcategories)
                    container.querySelectorAll(`tr[data-parent='${parentId}']`).forEach(subRow => {
                        // avoid double-init
                        if (subRow.getAttribute('data-percent-driven') === 'true') return;
                        const pctSpan = subRow.querySelector('.simulador-percentual-display');
                        const valInput = subRow.querySelector('.simulador-valor-input');
                        if (!pctSpan || !valInput) return;
                        // Prefer the already-rendered percent (e.g. percentual_media_receita_operacional) when present
                        let initialPct = null;
                        try {
                            const raw = (pctSpan.textContent || '').replace('%','').trim();
                            if (raw !== '') {
                                // normalize number: remove thousands separators (.) and convert comma to dot
                                const norm = raw.replace(/\./g, '').replace(/,/g, '.');
                                const parsed = parseFloat(norm);
                                if (!isNaN(parsed)) initialPct = parsed;
                            }
                        } catch(e) { initialPct = null; }
                        // fallback: compute from absolute value and receita
                        if (initialPct === null) {
                            const receita = (typeof obterFaturamentoSimulacao === 'function') ? obterFaturamentoSimulacao() : 0;
                            const val = parseFloat(valInput.value) || 0;
                            initialPct = receita > 0 ? (val / receita) * 100 : 0;
                        }
                        // make absolute input readonly so user can't edit absolute values for percent-driven rows
                        try { valInput.setAttribute('readonly', 'readonly'); valInput.classList.add('simulador-valor-readonly'); } catch(e){}
                        // create numeric input in place of span
                        const inp = document.createElement('input');
                        inp.type = 'number';
                        inp.step = '0.01';
                        inp.min = '0';
                        inp.className = 'form-control simulador-percent-input';
                        inp.value = initialPct.toFixed(2);

                        // replace span with input
                        pctSpan.parentNode.replaceChild(inp, pctSpan);
                        subRow.setAttribute('data-percent-driven', 'true');

                        // handler: when percent edited, update absolute value and propagate
                        const handler = debounce(function() {
                            const pct = parseFloat(this.value) || 0;
                            const receitaNow = (typeof obterFaturamentoSimulacao === 'function') ? obterFaturamentoSimulacao() : 0;
                            const newVal = receitaNow > 0 ? (pct / 100) * receitaNow : 0;
                            // update absolute input
                            valInput.value = newVal.toFixed(2);
                            // propagate upwards and recalc
                            try { if (typeof recalcParentChainFromRow === 'function') recalcParentChainFromRow(subRow); } catch(e){}
                            try { if (typeof updateAllCalculatedLines === 'function') updateAllCalculatedLines(); } catch(e){}
                            try { if (typeof computeAndApplyCalculatedSimulations === 'function') computeAndApplyCalculatedSimulations(); } catch(e){}
                        }, 150);

                        inp.addEventListener('input', handler);
                        inp.addEventListener('blur', function(){
                            // format to 2 decimals
                            const v = parseFloat(this.value) || 0;
                            this.value = v.toFixed(2);
                        });
                    });
                }
            }
        });
    // sync absolute inputs based on percent inputs initially
    updateAbsoluteFromPercent(container);
        // attach listener to receita input if present to update percents when receita changes
        try {
            const receitaRow = Array.from(container.querySelectorAll('tr')).find(r => (r.textContent||'').toLowerCase().includes('receita operacional'));
            if (receitaRow) {
                const rInput = receitaRow.querySelector('.simulador-valor-input');
                if (rInput) {
            rInput.addEventListener('input', debounce(() => updateAbsoluteFromPercent(container), 100));
                }
            }
        } catch (e) {}
    }

    // observe container for table injections and re-init when updated
    function observeContainer() {
        const container = document.getElementById('dre-table-container');
        if (!container) return;
        const mo = new MutationObserver((mutations) => {
            for (const m of mutations) {
                if (m.type === 'childList' && m.addedNodes.length > 0) {
                    // re-run init to create percent inputs for new table
                    setTimeout(() => initPercentDrivenOnce(container), 10);
                    break;
                }
            }
        });
        mo.observe(container, { childList: true, subtree: false });
        // initial run in case table already present
        setTimeout(() => initPercentDrivenOnce(container), 50);
    }

    // Start after DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', observeContainer);
    } else {
        observeContainer();
    }

})();
