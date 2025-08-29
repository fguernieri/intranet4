// Dre: salvar metas coletando valores do simulador e enviando para save_metas.php
(function(){
    function createSalvarButton() {
        const container = document.querySelector('.w-full.text-center.mb-2');
        if (!container) return;
        // Avoid duplicate button
        if (document.getElementById('btn-gravar-metas')) return;
        const btn = document.createElement('button');
        btn.id = 'btn-gravar-metas';
    btn.className = 'btn btn-warning btn-sm ms-2';
        btn.textContent = 'GRAVAR METAS';
        btn.addEventListener('click', onSalvarClick);
        container.appendChild(btn);
    }

    function onSalvarClick(e) {
        e.preventDefault();
        const payload = collectMetas();
        if (!payload || payload.length === 0) {
            alert('Nenhuma meta encontrada para gravar.');
            return;
        }
    // Confirm
    if (!confirm('Deseja gravar ' + payload.length + ' metas no banco?')) return;

        // Send
        fetch('/modules/DRE/save_metas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(r => r.json()).then(json => {
            if (json.code === 200) {
                alert('Metas gravadas: ' + (json.inserted || 0));
            } else {
                alert('Erro ao gravar metas: ' + (json.message || 'erro'));
            }
        }).catch(err => {
            console.error(err);
            alert('Erro ao conectar com o servidor. Veja console para detalhes.');
        });
    }

    // Note: percentuais are taken exactly from the simulation display/inputs. No automatic recompute.

    function collectMetas() {
        // Collect only CATEGORIA (level 1) and SUBCATEGORIA (level 2). Ignore level 3 (contas).
        const metas = [];
        const rows = document.querySelectorAll('table.dre-table tbody tr');
        let currentCategoria = null;

        function parseCurrency(text) {
            if (!text) return 0;
            // Remove non numeric except comma and dot and minus
            let t = text.replace(/[^0-9,.-]/g, '').trim();
            if (t === '') return 0;
            // If contains both '.' and ',' assume '.' thousands and ',' decimal
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

            // Try to read META from input first, then fallback to the formatted cell (2nd column)
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

            // Percent: prefer editable percent input (.simulador-percent-input) then display (.simulador-percentual-display)
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
                    CATEGORIA: currentCategoria || '',
                    SUBCATEGORIA: '',
                    META: metaValue || 0,
                    PERCENTUAL: pctValue,
                });
            } else if (level === 2) {
                metas.push({
                    CATEGORIA: currentCategoria || '',
                    SUBCATEGORIA: label,
                    META: metaValue || 0,
                    PERCENTUAL: pctValue,
                });
            }
            // ignore level 3
        });
        return metas;
    }

    // Initialize when DOM ready and after table is rendered
    document.addEventListener('DOMContentLoaded', function(){
        createSalvarButton();
        // Recreate after table updates (simple MutationObserver)
        const container = document.getElementById('dre-table-container');
        if (!container) return;
        const obs = new MutationObserver(() => createSalvarButton());
        obs.observe(container, { childList: true, subtree: true });
    });
})();
