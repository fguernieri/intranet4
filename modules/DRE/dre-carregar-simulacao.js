;(function(){
    function createLoadUI() {
        const buttonsBlock = document.querySelector('.w-full.text-center.mb-2');
        if (!buttonsBlock) return;
        // create or reuse a sibling container placed immediately after the buttons block
        let target = buttonsBlock.nextElementSibling;
        if (!target || !target.classList || !target.classList.contains('dre-load-container')) {
            target = document.createElement('div');
            target.className = 'dre-load-container w-full text-center mb-3';
            buttonsBlock.parentNode.insertBefore(target, buttonsBlock.nextSibling);
        }
        if (target.querySelector('#dre-simulacao-select')) return;

        const select = document.createElement('select');
        select.id = 'dre-simulacao-select';
        select.className = 'form-select form-select-sm d-inline-block me-2';
        select.style.maxWidth = '340px';

        const btn = document.createElement('button');
        btn.id = 'btn-carregar-simulacao';
        btn.className = 'btn btn-secondary btn-sm me-2';
        btn.textContent = 'CARREGAR SIMULAÇÃO';
        btn.addEventListener('click', onCarregar);

        const refresh = document.createElement('button');
        refresh.id = 'btn-refresh-simulacoes';
        refresh.className = 'btn btn-outline-secondary btn-sm';
        refresh.textContent = 'Atualizar';
        refresh.addEventListener('click', e => { e.preventDefault(); loadList(); });

        target.appendChild(select);
        target.appendChild(btn);
        target.appendChild(refresh);

        loadList();
    }

    async function loadList() {
        const sel = document.getElementById('dre-simulacao-select');
        if (!sel) return;
        sel.innerHTML = '';
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = '--- selecione uma simulação ---';
        sel.appendChild(opt);
        try {
            console.debug('[dre-carregar] buscando lista de simulacoes...');
            const res = await fetch('/modules/DRE/load_simulacao.php');
            const text = await res.text();
            console.debug('[dre-carregar] resposta bruta:', text);
            const json = JSON.parse(text || '{}');
            if (json.code !== 200) throw new Error(json.message || 'Erro');
            const sims = json.simulacoes || [];
            console.debug('[dre-carregar] simulacoes retornadas:', sims);
            sims.forEach(s => {
                const o = document.createElement('option');
                o.value = s.nome;
                o.textContent = s.nome + (s.data ? ' — ' + s.data : '');
                sel.appendChild(o);
            });
        } catch (e) {
            console.error('Erro ao listar simulações', e);
            const errOpt = document.createElement('option');
            errOpt.value = '';
            errOpt.textContent = 'Erro ao listar simulações';
            sel.appendChild(errOpt);
        }
    }

    async function onCarregar(e) {
        e.preventDefault();
        const sel = document.getElementById('dre-simulacao-select');
        if (!sel) return;
        const nome = sel.value;
        if (!nome) return alert('Selecione uma simulação para carregar.');
        try {
            const res = await fetch('/modules/DRE/load_simulacao.php?nome=' + encodeURIComponent(nome));
            const json = await res.json();
            if (json.code !== 200) throw new Error(json.message || 'Erro');
            const rows = json.rows || [];
            // rows: [{NOME, CATEGORIA, SUBCATEGORIA, META, PERCENTUAL}, ...]
            // Apply them: for each row, find matching label and set values using existing helpers
            // Prefer applying by SUBCATEGORIA first, then CATEGORIA
            rows.forEach(r => {
                const categoria = r.CATEGORIA || '';
                const sub = r.SUBCATEGORIA || '';
                const meta = parseFloat(r.META) || 0;
                const pct = (r.PERCENTUAL === null || r.PERCENTUAL === '') ? null : parseFloat(r.PERCENTUAL);
                // Attempt matching strategies
                if (sub) {
                    // try exact subcategory label first
                    trySetSimValue([sub, categoria + ' ' + sub, categoria + ' - ' + sub], meta);
                } else if (categoria) {
                    trySetSimValue([categoria], meta);
                }
                // Additionally, if percentual is provided, try to set percent display by finding the percent input
                if (pct !== null) {
                    // set percentual display text where possible
                    const rowEl = findRowByCategoryLabel(sub || categoria);
                    if (rowEl) {
                        const pctEl = rowEl.querySelector('.simulador-percentual-display');
                        if (pctEl) {
                            // compute based on current faturamento or set text directly as percent
                            pctEl.textContent = formatPercent(pct);
                        }
                    }
                }
            });

            // After applying all, trigger recompute flows
            if (typeof recalcAllSimPercentuais === 'function') recalcAllSimPercentuais();
            if (typeof updateAllCalculatedLines === 'function') updateAllCalculatedLines();
            if (typeof computeAndApplyCalculatedSimulations === 'function') computeAndApplyCalculatedSimulations();

            alert('Simulação carregada: ' + nome);
        } catch (err) {
            console.error(err);
            alert('Erro ao carregar simulação. Veja console para detalhes.');
        }
    }

    document.addEventListener('DOMContentLoaded', function(){
        createLoadUI();
        const container = document.getElementById('dre-table-container');
        if (!container) return;
        const obs = new MutationObserver(() => createLoadUI());
        obs.observe(container, { childList: true, subtree: true });
    });
})();
