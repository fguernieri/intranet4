// Dre: salvar toda a simulação como um named snapshot em fsimulacoestap
;(function(){
    function createSalvarSimButton() {
        const container = document.querySelector('.w-full.text-center.mb-2');
        if (!container) return;
        if (document.getElementById('btn-salvar-simulacao')) return;
        const btn = document.createElement('button');
        btn.id = 'btn-salvar-simulacao';
    btn.className = 'btn btn-primary btn-sm ms-2';
        btn.textContent = 'SALVAR SIMULAÇÃO';
        btn.addEventListener('click', onSalvarSim);
        container.appendChild(btn);
    }

    function onSalvarSim(e) {
        e.preventDefault();
        const nome = prompt('Nome para esta simulação (ex: Simulação Nov/2025):');
        if (!nome) return;
        const rows = collectMetas();
        if (!rows || rows.length === 0) {
            alert('Nenhuma linha para salvar.');
            return;
        }
        // Attach nome to payload wrapper
        const payload = { nome: nome, rows: rows };
        fetch('/modules/DRE/save_simulacao.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(r => r.json()).then(json => {
            if (json.code === 200) {
                alert('Simulação salva: ' + (json.inserted || 0) + ' linhas.');
            } else {
                alert('Erro ao salvar simulação: ' + (json.message || 'erro'));
            }
        }).catch(err => {
            console.error(err);
            alert('Erro ao conectar com o servidor. Veja console para detalhes.');
        });
    }

    // Reuse collectMetas from dre-salvar-metas.js logic but inline minimal copy to avoid dependency
    function collectMetas() {
        const metas = [];
        const rows = document.querySelectorAll('table.dre-table tbody tr');
        let currentCategoria = null;
        function parseCurrency(text) {
            if (!text) return 0;
            let t = text.replace(/[^0-9,.-]/g, '').trim();
            if (t === '') return 0;
            if (t.indexOf('.') !== -1 && t.indexOf(',') !== -1) {
                t = t.replace(/\./g, '').replace(/,/g, '.');
            } else if (t.indexOf(',') !== -1 && t.indexOf('.') === -1) {
                t = t.replace(/,/g, '.');
            }
            const n = parseFloat(t);
            return isNaN(n) ? 0 : n;
        }
        function parsePercent(text) {
            if (text === null || typeof text === 'undefined') return null;
            let t = String(text).replace(/[^0-9,.-]/g, '').trim();
            if (t === '') return null;
            if (t.indexOf('.') !== -1 && t.indexOf(',') !== -1) {
                t = t.replace(/\./g, '').replace(/,/g, '.');
            } else if (t.indexOf(',') !== -1 && t.indexOf('.') === -1) {
                t = t.replace(/,/g, '.');
            }
            const n = parseFloat(t);
            return isNaN(n) ? null : n;
        }
        rows.forEach(row => {
            const level = parseInt(row.getAttribute('data-level') || '1');
            const firstTd = row.querySelector('td');
            const label = firstTd ? firstTd.textContent.replace(/[\u25B6\u25BA\u25B8\u25C0\u25C4\u2190-\u21FF]/g, '').trim() : '';
            const simulInput = row.querySelector('.simulador-valor-input');
            let metaValue = null;
            if (simulInput && simulInput.value !== undefined && String(simulInput.value).trim() !== '') {
                metaValue = parseFloat(String(simulInput.value).replace(/\s+/g, '')) || 0;
            } else {
                const cells = row.querySelectorAll('td');
                if (cells && cells.length > 1) {
                    metaValue = parseCurrency(cells[1].textContent || '');
                } else {
                    metaValue = 0;
                }
            }
            const pctInput = row.querySelector('.simulador-percent-input');
            const pctDisplay = row.querySelector('.simulador-percentual-display');
            let pctValue = null;
            if (pctInput && pctInput.value !== undefined && String(pctInput.value).trim() !== '') {
                pctValue = parsePercent(pctInput.value);
            } else if (pctDisplay && pctDisplay.textContent) {
                pctValue = parsePercent(pctDisplay.textContent);
            } else {
                pctValue = null;
            }
            if (level === 1) {
                currentCategoria = label;
                metas.push({
                    NOME: '',
                    CATEGORIA: currentCategoria || '',
                    SUBCATEGORIA: '',
                    META: metaValue || 0,
                    PERCENTUAL: pctValue,
                });
            } else if (level === 2) {
                metas.push({
                    NOME: '',
                    CATEGORIA: currentCategoria || '',
                    SUBCATEGORIA: label,
                    META: metaValue || 0,
                    PERCENTUAL: pctValue,
                });
            }
        });
        return metas;
    }

    document.addEventListener('DOMContentLoaded', function(){
        createSalvarSimButton();
        const container = document.getElementById('dre-table-container');
        if (!container) return;
        const obs = new MutationObserver(() => createSalvarSimButton());
        obs.observe(container, { childList: true, subtree: true });
    });
})();
