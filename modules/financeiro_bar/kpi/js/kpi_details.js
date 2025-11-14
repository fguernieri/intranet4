/**
 * KPI Details Modal - Gerenciamento de modais de detalhamento de categorias
 * Author: Sistema Intranet
 * Date: 2025
 */

// Estado global do modal
const KPIDetailsModal = {
    currentCategory: null,
    currentPeriod: null,
    currentData: null,
    chart: null,
    sortColumn: null,
    sortDirection: 'desc', // 'asc' ou 'desc'
    
    /**
     * Inicializa event listeners
     */
    init: function() {
        // Fechar modal ao clicar no X ou fora do modal
        const closeBtn = document.querySelector('#categoryDetailModal .modal-close');
        const modal = document.getElementById('categoryDetailModal');
        
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.close());
        }
        
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.close();
                }
            });
        }
        
        // ESC para fechar
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                this.close();
            }
        });
        
        // Event delegation para sortable headers
        document.addEventListener('click', (e) => {
            if (e.target.closest('.sortable-header')) {
                const header = e.target.closest('.sortable-header');
                const column = header.getAttribute('data-sort');
                if (column) {
                    this.sortBy(column);
                }
            }
        });
    },
    
    /**
     * Abre modal para uma categoria específica
     */
    open: function(categoria, periodo = '') {
        this.currentCategory = categoria;
        this.currentPeriod = periodo;
        const modal = document.getElementById('categoryDetailModal');
        
        if (!modal) {
            console.error('Modal não encontrado no DOM');
            return;
        }
        
        // Mostrar modal e loading
        modal.classList.remove('hidden');
        this.showLoading();
        
        // Buscar dados via API
        this.fetchCategoryData(categoria, periodo);
    },
    
    /**
     * Fecha modal
     */
    close: function() {
        const modal = document.getElementById('categoryDetailModal');
        if (modal) {
            modal.classList.add('hidden');
        }
        
        // Destruir gráfico se existir
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
        
        this.currentCategory = null;
        this.currentData = null;
    },
    
    /**
     * Exibe loading no modal
     */
    showLoading: function() {
        const content = document.getElementById('modalDetailContent');
        if (content) {
            content.innerHTML = `
                <div style="text-align:center; padding:60px 20px;">
                    <div class="loading-spinner"></div>
                    <p style="margin-top:20px; color:#9ca3af;">Carregando análise detalhada...</p>
                </div>
            `;
        }
    },
    
    /**
     * Exibe erro no modal
     */
    showError: function(message) {
        const content = document.getElementById('modalDetailContent');
        if (content) {
            content.innerHTML = `
                <div style="text-align:center; padding:60px 20px;">
                    <div style="font-size:48px; color:#ef4444;">⚠️</div>
                    <p style="margin-top:20px; color:#ef4444; font-weight:600;">Erro ao carregar dados</p>
                    <p style="margin-top:10px; color:#9ca3af;">${message}</p>
                    <button onclick="KPIDetailsModal.close()" class="btn-secondary" style="margin-top:20px;">Fechar</button>
                </div>
            `;
        }
    },
    
    /**
     * Busca dados da API
     */
    fetchCategoryData: async function(categoria, periodo = '') {
        try {
            let url = `api/get_category_details.php?categoria=${encodeURIComponent(categoria)}`;
            if (periodo) {
                url += `&periodo=${encodeURIComponent(periodo)}`;
            }
            
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            // DEBUG: Ver dados recebidos
            console.log('=== DEBUG API Response ===');
            console.log('Labels (meses):', data.chart?.labels);
            console.log('Total registros:', data.chart?.total);
            console.log('Subcategorias:', Object.keys(data.chart?.subcategorias || {}));
            console.log('==========================');
            
            if (!data.success) {
                throw new Error(data.error || 'Erro desconhecido');
            }
            
            this.currentData = data;
            this.render();
            
        } catch (error) {
            console.error('Erro ao buscar dados:', error);
            this.showError(error.message);
        }
    },
    
    /**
     * Renderiza todo o conteúdo do modal
     */
    render: function() {
        if (!this.currentData) return;
        
        const content = document.getElementById('modalDetailContent');
        const title = document.getElementById('modalCategoryTitle');
        
        if (title) {
            const mesRef = this.currentData.mes_referencia || '';
            title.textContent = `${this.currentData.categoria} - Análise Detalhada - ${mesRef}`;
        }
        
        if (!content) return;
        
        const html = `
            ${this.renderResumo()}
            ${this.renderChartContainer()}
            ${this.renderAnalises()}
            ${this.renderTable()}
        `;
        
        content.innerHTML = html;
        
        // Renderizar gráfico após inserir HTML
        setTimeout(() => this.renderChart(), 100);
    },
    
    /**
     * Renderiza resumo executivo
     */
    renderResumo: function() {
        const resumo = this.currentData.resumo;
        const subcats = this.currentData.subcategorias || [];
        const mesReferencia = this.currentData.mes_referencia || '';
        
        // Garantir que tendencia_geral existe
        const tendencia_geral = resumo.tendencia_geral || 'Estável';
        const tendencia_geral_status = resumo.tendencia_geral_status || 'neutro';
        const tendencia_geral_valor = resumo.tendencia_geral_valor || 0;
        const tendenciaClass = this.getTendenciaClass(tendencia_geral_status);
        
        // Calcular classe de variação para o card de comparação mensal
        const variacaoMes = resumo.variacao_total_mes || 0;
        const variacaoClass = this.getVariacaoClass(variacaoMes);
        const variacaoIcone = variacaoMes > 0 ? '🔺' : (variacaoMes < 0 ? '🔻' : '➡️');
        
        return `
            <div class="detail-section">
                <h3 class="section-title">📊 RESUMO EXECUTIVO - ${this.currentData.categoria}</h3>
                <div class="resumo-cards">
                    <div class="resumo-card">
                        <div class="resumo-label">Total da Categoria</div>
                        <div class="resumo-value">${this.formatCurrency(resumo.total_atual)}</div>
                        <div style="font-size:11px;color:#9ca3af;margin-top:4px;">
                            ${resumo.pct_receita.toFixed(1)}% da Receita (${this.formatCurrency(resumo.receita_atual)})
                        </div>
                        <div style="font-size:10px;color:#6b7280;margin-top:2px;font-weight:600;">📅 ${mesReferencia}</div>
                    </div>
                    <div class="resumo-card">
                        <div class="resumo-label">Mês Anterior</div>
                        <div class="resumo-value">${this.formatCurrency(resumo.total_anterior)}</div>
                        <div style="font-size:11px;color:#9ca3af;margin-top:4px;">
                            ${variacaoMes > 0 ? '+' : ''}${variacaoMes.toFixed(1)}% vs Mês Atual ${variacaoIcone}
                        </div>
                        <div style="font-size:10px;color:#6b7280;margin-top:2px;font-weight:600;">📊 Comparação</div>
                    </div>
                    <div class="resumo-card">
                        <div class="resumo-label">Maior Subcategoria</div>
                        <div class="resumo-value" style="font-size:14px;">${resumo.maior_subcategoria}</div>
                        <div style="font-size:11px;color:#9ca3af;margin-top:4px;">
                            ${this.formatCurrency(resumo.maior_subcategoria_valor)} (${resumo.maior_subcategoria_pct.toFixed(1)}%)
                        </div>
                        <div style="font-size:10px;color:#6b7280;margin-top:2px;font-weight:600;">📅 ${mesReferencia}</div>
                    </div>
                    <div class="resumo-card">
                        <div class="resumo-label" title="Tendência baseada em Regressão Linear sobre os 12 meses. Calcula a inclinação percentual da linha de tendência. Threshold: ±2% ao mês (±24% ao ano)">
                            Tendência Geral 
                            <span style="font-size:9px;color:#9ca3af;font-weight:400;margin-left:3px;cursor:help;">💡</span>
                        </div>
                        <div class="resumo-value">
                            <span class="tendencia-badge ${tendenciaClass}">
                                ${tendencia_geral}
                            </span>
                        </div>
                        <div style="font-size:11px;color:#9ca3af;margin-top:4px;">
                            Média: ${tendencia_geral_valor > 0 ? '+' : ''}${tendencia_geral_valor.toFixed(1)}%
                        </div>
                    </div>
                </div>
            </div>
        `;
    },
    
    /**
     * Renderiza container do gráfico
     */
    renderChartContainer: function() {
        const subcats = this.currentData.subcategorias || [];
        
        // Criar checkboxes para cada subcategoria
        const checkboxes = subcats.map((sub, idx) => `
            <label class="subcat-filter-item">
                <input type="checkbox" 
                       class="subcat-checkbox" 
                       data-subcat="${sub.nome}" 
                       checked
                       onchange="KPIDetailsModal.toggleSubcategoria('${sub.nome.replace(/'/g, "\\'")}')">
                <span>${sub.nome}</span>
            </label>
        `).join('');
        
        return `
            <div class="detail-section">
                <h3 class="section-title">📈 EVOLUÇÃO DAS SUBCATEGORIAS (6 meses)</h3>
                <div class="chart-controls-wrapper">
                    <div class="chart-controls-header">
                        <button onclick="KPIDetailsModal.toggleFilterPanel()" 
                                class="filter-toggle-btn">
                            <span id="filterBtnIcon">▼</span> Filtrar Subcategorias (<span id="selectedCount">${subcats.length}</span>/${subcats.length})
                        </button>
                        <label class="chart-toggle-all">
                            <input type="checkbox" id="toggleAllSubcats" checked onchange="KPIDetailsModal.toggleAllSubcategorias()">
                            <span>Selecionar Todas</span>
                        </label>
                    </div>
                    <div id="subcatFilterPanel" class="subcat-filter-panel">
                        ${checkboxes}
                    </div>
                </div>
                <div id="detailChart" style="width:100%; height:450px;"></div>
                <div style="margin-top:12px;color:#9ca3af;font-size:12px;text-align:center;">
                    💡 Use os filtros acima para focar em subcategorias específicas
                </div>
            </div>
        `;
    },
    
    /**
     * Renderiza análises automáticas
     */
    renderAnalises: function() {
        const analises = this.currentData.analises;
        
        return `
            <div class="detail-section">
                <h3 class="section-title">🔍 ANÁLISES AUTOMÁTICAS</h3>
                <div class="analises-grid">
                    ${this.renderAnaliseCard('🔺 Maior Crescimento', analises.maior_crescimento, 'crescimento')}
                    ${this.renderAnaliseCard('🔻 Maior Redução', analises.maior_reducao, 'reducao')}
                    ${this.renderAnaliseCard('📈 Tendência de Alta', analises.tendencia_alta, 'tendencia_alta')}
                    ${this.renderAnaliseCard('� Tendência de Baixa', analises.tendencia_baixa, 'tendencia_baixa')}
                </div>
            </div>
        `;
    },
    
    /**
     * Renderiza card de análise individual
     */
    renderAnaliseCard: function(titulo, data, tipo) {
        if (!data) return '';
        
        let valorDestaque = '';
        let detalhe = '';
        
        switch(tipo) {
            case 'crescimento':
                valorDestaque = `+${data.variacao_mes.toFixed(1)}%`;
                detalhe = `${data.nome} (vs mês anterior)`;
                break;
            case 'reducao':
                valorDestaque = `${data.variacao_mes.toFixed(1)}%`;
                detalhe = `${data.nome} (vs mês anterior)`;
                break;
            case 'tendencia_alta':
                valorDestaque = `${data.tendencia} ${data.variacao_tendencia > 0 ? '+' : ''}${data.variacao_tendencia.toFixed(1)}%`;
                detalhe = `${data.nome} (regressão linear 12m)`;
                break;
            case 'tendencia_baixa':
                valorDestaque = `${data.tendencia} ${data.variacao_tendencia > 0 ? '+' : ''}${data.variacao_tendencia.toFixed(1)}%`;
                detalhe = `${data.nome} (regressão linear 12m)`;
                break;
        }
        
        return `
            <div class="analise-card">
                <div class="analise-titulo">${titulo}</div>
                <div class="analise-valor">${valorDestaque}</div>
                <div class="analise-detalhe">${detalhe}</div>
            </div>
        `;
    },
    
    /**
     * Renderiza tabela detalhada
     */
    renderTable: function() {
        let subcats = this.currentData.subcategorias;
        
        if (!subcats || subcats.length === 0) {
            return '<div class="detail-section"><p style="text-align:center;color:#9ca3af;">Nenhuma subcategoria encontrada.</p></div>';
        }
        
        // Ordenar se houver coluna selecionada
        if (this.sortColumn) {
            subcats = this.sortSubcategories([...subcats]);
        }
        
        const rows = subcats.map(sub => {
            // Calcular variação percentual: (Valor Atual - Média 3M) / Média 3M * 100
            const var_vs_media3m = sub.media_3m > 0 ? ((sub.valor_atual - sub.media_3m) / sub.media_3m) * 100 : 0;
            
            // Garantir que tendencia existe, senão usar valor padrão
            const tendencia = sub.tendencia || 'Estável';
            const tendencia_status = sub.tendencia_status || 'neutro';
            
            // Debug - remover depois
            if (!sub.tendencia) {
                console.warn('Subcategoria sem tendencia:', sub.nome, sub);
            }
            
            return `
            <tr>
                <td class="subcat-name">${sub.nome}</td>
                <td class="text-right">${this.formatCurrency(sub.media_6m)}</td>
                <td class="text-right sortable-cell" data-value="${sub.media_3m}">${this.formatCurrency(sub.media_3m)}</td>
                <td class="text-right">${this.formatCurrency(sub.valor_anterior)}</td>
                <td class="text-right sortable-cell" data-value="${sub.valor_atual}">${this.formatCurrency(sub.valor_atual)}</td>
                <td class="text-right sortable-cell ${this.getVariacaoClass(var_vs_media3m)}" data-value="${var_vs_media3m}">
                    ${this.formatVariacao(var_vs_media3m)}
                </td>
                <td class="text-right ${this.getVariacaoClass(sub.variacao_mes)}">
                    ${this.formatVariacao(sub.variacao_mes)}
                </td>
                <td class="text-right sortable-cell" data-value="${sub.pct_receita}">${sub.pct_receita.toFixed(1)}%</td>
                <td class="text-right">${sub.pct_categoria_pai.toFixed(1)}%</td>
                <td class="text-center sortable-cell" data-value="${sub.variacao_tendencia}">
                    <span class="tendencia-badge ${this.getTendenciaClass(tendencia_status)}">
                        ${tendencia}
                    </span>
                </td>
                <td class="text-center">
                    <div class="sparkline" data-values='${JSON.stringify(sub.valores_12m)}'></div>
                </td>
            </tr>
        `;
        }).join('');
        
        const getSortIcon = (col) => {
            if (this.sortColumn === col) {
                return this.sortDirection === 'asc' ? '▲' : '▼';
            }
            return '⇅';
        };
        
        const html = `
            <div class="detail-section">
                <h3 class="section-title">📋 TABELA DETALHADA</h3>
                <div class="table-container">
                    <table class="detail-table">
                        <thead>
                            <tr>
                                <th>Subcategoria</th>
                                <th class="text-right" title="Média dos últimos 6 meses">
                                    Média 6M
                                    <span style="font-size:9px;color:#9ca3af;font-weight:400;margin-left:3px;">💡</span>
                                </th>
                                <th class="text-right sortable-header" data-sort="media_3m" title="Média dos últimos 3 meses - Clique para ordenar">
                                    Média 3M <span class="sort-icon">${getSortIcon('media_3m')}</span>
                                </th>
                                <th class="text-right" title="Valor da subcategoria no mês anterior">
                                    Mês Ant.
                                    <span style="font-size:9px;color:#9ca3af;font-weight:400;margin-left:3px;">💡</span>
                                </th>
                                <th class="text-right sortable-header" data-sort="valor_atual" title="Valor da subcategoria no último mês fechado - Clique para ordenar">
                                    Valor Atual <span class="sort-icon">${getSortIcon('valor_atual')}</span>
                                </th>
                                <th class="text-right sortable-header" data-sort="vs_media_3m" title="Comparação: ((Valor Atual - Média 3M) / Média 3M) × 100 - Clique para ordenar">
                                    vs M3 <span class="sort-icon">${getSortIcon('vs_media_3m')}</span>
                                </th>
                                <th class="text-right" title="Fórmula: ((Valor Atual - Valor Mês Anterior) / Valor Mês Anterior) × 100">
                                    Var. M
                                    <span style="font-size:9px;color:#9ca3af;font-weight:400;margin-left:3px;">💡</span>
                                </th>
                                <th class="text-right sortable-header" data-sort="pct_receita" title="Fórmula: (Valor da Subcategoria / Receita Total) × 100 - Clique para ordenar">
                                    % Rec. <span class="sort-icon">${getSortIcon('pct_receita')}</span>
                                </th>
                                <th class="text-right" title="Fórmula: (Valor da Subcategoria / Total da Categoria Pai) × 100">
                                    % Cat.
                                    <span style="font-size:9px;color:#9ca3af;font-weight:400;margin-left:3px;">💡</span>
                                </th>
                                <th class="text-center sortable-header" data-sort="tendencia" title="Tendência baseada em Regressão Linear sobre os 12 meses.

Cálculo: Inclinação da linha de tendência convertida em percentual ao mês.

Decisão:
• Inclinação > +2%/mês → Subindo 🔺
• Inclinação < -2%/mês → Descendo 🔻  
• Entre -2% e +2% → Estável ➡️

Usa todos os 12 meses para análise robusta.

Clique para ordenar">
                                    Tend. <span class="sort-icon">${getSortIcon('tendencia')}</span>
                                </th>
                                <th class="text-center" title="Gráfico em miniatura mostrando a evolução dos últimos meses">
                                    Evol.
                                    <span style="font-size:9px;color:#9ca3af;font-weight:400;margin-left:3px;">💡</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        
        // Renderizar sparklines após inserir HTML
        setTimeout(() => this.renderSparklines(), 100);
        
        return html;
    },
    
    /**
     * Renderiza mini-gráficos sparkline
     */
    renderSparklines: function() {
        const sparklines = document.querySelectorAll('.sparkline');
        
        sparklines.forEach(el => {
            try {
                const valores = JSON.parse(el.getAttribute('data-values'));
                const max = Math.max(...valores);
                const min = Math.min(...valores);
                const range = max - min || 1;
                
                const width = 80;
                const height = 24;
                const points = valores.map((v, i) => {
                    const x = (i / (valores.length - 1)) * width;
                    const y = height - ((v - min) / range) * height;
                    return `${x},${y}`;
                }).join(' ');
                
                const svg = `
                    <svg width="${width}" height="${height}" style="display:block;">
                        <polyline 
                            points="${points}" 
                            fill="none" 
                            stroke="#6b7280" 
                            stroke-width="1.5"
                        />
                    </svg>
                `;
                
                el.innerHTML = svg;
            } catch (e) {
                el.innerHTML = '<span style="color:#9ca3af;">-</span>';
            }
        });
    },
    
    /**
     * Renderiza gráfico ApexCharts
     */
    renderChart: function() {
        const chartData = this.currentData.chart;
        
        // Preparar séries - APENAS SUBCATEGORIAS
        const series = [];
        
        // Cores para subcategorias
        const subcatColors = [
            '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', 
            '#06b6d4', '#84cc16', '#a855f7', '#ef4444', '#10b981',
            '#fb923c', '#c084fc', '#f472b6', '#2dd4bf', '#fb7185'
        ];
        
        let colorIndex = 0;
        let maxValue = 0;
        
        // Adicionar apenas subcategorias
        for (const [subcat, valores] of Object.entries(chartData.subcategorias)) {
            // Calcular máximo para escala
            const maxSubcat = Math.max(...valores.filter(v => v > 0));
            if (maxSubcat > maxValue) maxValue = maxSubcat;
            
            series.push({
                name: subcat,
                type: 'line',
                data: valores,
                color: subcatColors[colorIndex % subcatColors.length]
            });
            colorIndex++;
        }
        
        // Se não houver subcategorias, mostrar mensagem
        if (series.length === 0) {
            const chartEl = document.querySelector('#detailChart');
            if (chartEl) {
                chartEl.innerHTML = '<div style="text-align:center;padding:60px 20px;color:#9ca3af;"><p>Nenhuma subcategoria encontrada para esta categoria.</p></div>';
            }
            return;
        }
        
        const options = {
            chart: {
                height: 450,
                type: 'line',
                toolbar: { 
                    show: true,
                    tools: {
                        download: true,
                        selection: false,
                        zoom: false,
                        zoomin: false,
                        zoomout: false,
                        pan: false,
                        reset: false
                    }
                },
                zoom: { enabled: false },
                events: {
                    legendClick: function(chartContext, seriesIndex, config) {
                        // Impedir o comportamento padrão de ocultar
                        const chart = chartContext;
                        
                        // Se já está destacada, remover destaque
                        if (chart.w.globals.seriesClicked && chart.w.globals.seriesClicked[seriesIndex]) {
                            chart.w.globals.seriesClicked = null;
                        } else {
                            // Destacar a série clicada
                            chart.w.globals.seriesClicked = {};
                            chart.w.globals.seriesClicked[seriesIndex] = true;
                        }
                        
                        // Atualizar opacidade via CSS
                        const legendItems = document.querySelectorAll('#detailChart .apexcharts-legend-series');
                        const paths = document.querySelectorAll('#detailChart .apexcharts-line-series path');
                        const markers = document.querySelectorAll('#detailChart .apexcharts-line-series .apexcharts-marker');
                        
                        if (chart.w.globals.seriesClicked) {
                            // Destacar apenas a clicada
                            legendItems.forEach((item, i) => {
                                item.style.opacity = (i === seriesIndex) ? '1' : '0.3';
                            });
                            
                            paths.forEach((path) => {
                                const seriesIdx = parseInt(path.getAttribute('index'));
                                if (seriesIdx === seriesIndex) {
                                    path.style.opacity = '1';
                                    path.style.strokeWidth = '4';
                                } else {
                                    path.style.opacity = '0.2';
                                    path.style.strokeWidth = '2';
                                }
                            });
                            
                            markers.forEach((marker) => {
                                const seriesIdx = parseInt(marker.getAttribute('rel'));
                                marker.style.opacity = (seriesIdx === seriesIndex) ? '1' : '0.2';
                            });
                        } else {
                            // Reset: todas visíveis
                            legendItems.forEach((item) => item.style.opacity = '1');
                            paths.forEach((path) => {
                                path.style.opacity = '1';
                                path.style.strokeWidth = '3';
                            });
                            markers.forEach((marker) => marker.style.opacity = '1');
                        }
                        
                        return false; // Prevenir comportamento padrão
                    }
                }
            },
            series: series,
            xaxis: {
                categories: chartData.labels,
                labels: { 
                    style: { fontSize: '11px' },
                    rotate: -45,
                    rotateAlways: false
                }
            },
            yaxis: {
                title: { text: 'Valor (R$)', style: { fontSize: '13px', fontWeight: 600 } },
                labels: {
                    formatter: (val) => {
                        return val.toLocaleString('pt-BR', {
                            style: 'currency',
                            currency: 'BRL',
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 0
                        });
                    }
                },
                min: 0,
                max: Math.ceil(maxValue * 1.1)
            },
            stroke: {
                width: 3,
                curve: 'smooth'
            },
            markers: {
                size: 4,
                hover: { size: 6 }
            },
            legend: {
                position: 'bottom',
                horizontalAlign: 'center',
                fontSize: '12px',
                itemMargin: {
                    horizontal: 10,
                    vertical: 5
                },
                onItemClick: {
                    toggleDataSeries: false  // Desabilitar toggle (não ocultar)
                },
                onItemHover: {
                    highlightDataSeries: true
                }
            },
            tooltip: {
                shared: false,  // Mudado para false - mostra apenas a série sob o mouse
                intersect: true,  // Mudado para true - precisa passar exatamente sobre o ponto
                style: {
                    fontSize: '12px',
                    fontFamily: 'Arial, sans-serif'
                },
                x: {
                    show: true,
                    formatter: (val, opts) => {
                        return chartData.labels[opts.dataPointIndex] || val;
                    }
                },
                y: {
                    formatter: (val, opts) => {
                        return val.toLocaleString('pt-BR', {
                            style: 'currency',
                            currency: 'BRL'
                        });
                    },
                    title: {
                        formatter: (seriesName) => seriesName + ':'
                    }
                },
                marker: {
                    show: true
                }
            },
            grid: {
                borderColor: '#374151',
                strokeDashArray: 4
            },
            theme: {
                mode: 'dark'
            }
        };
        
        // Destruir gráfico anterior se existir
        if (this.chart) {
            this.chart.destroy();
        }
        
        // Criar novo gráfico
        const chartEl = document.querySelector('#detailChart');
        if (chartEl) {
            this.chart = new ApexCharts(chartEl, options);
            this.chart.render();
        }
    },
    
    /**
     * Toggle todas subcategorias
     */
    toggleAllSubcategorias: function() {
        if (!this.chart) return;
        
        const checkbox = document.getElementById('toggleAllSubcats');
        const show = checkbox.checked;
        
        // Atualizar todos os checkboxes individuais
        const checkboxes = document.querySelectorAll('.subcat-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = show;
        });
        
        // Mostrar ou ocultar todas as séries
        this.chart.w.config.series.forEach((serie) => {
            if (show) {
                this.chart.showSeries(serie.name);
            } else {
                this.chart.hideSeries(serie.name);
            }
        });
        
        // Atualizar eixo Y
        this.updateYAxis();
    },
    
    /**
     * Toggle painel de filtros
     */
    toggleFilterPanel: function() {
        const panel = document.getElementById('subcatFilterPanel');
        const icon = document.getElementById('filterBtnIcon');
        if (panel && icon) {
            const isOpen = panel.style.maxHeight && panel.style.maxHeight !== '0px';
            if (isOpen) {
                panel.style.maxHeight = '0px';
                icon.textContent = '▼';
            } else {
                panel.style.maxHeight = '300px';
                icon.textContent = '▲';
            }
        }
    },
    
    /**
     * Atualiza contador de selecionados
     */
    updateSelectedCount: function() {
        const checkboxes = document.querySelectorAll('.subcat-checkbox');
        const checked = Array.from(checkboxes).filter(cb => cb.checked).length;
        const total = checkboxes.length;
        const counter = document.getElementById('selectedCount');
        if (counter) {
            counter.textContent = checked;
        }
    },
    
    /**
     * Toggle subcategoria individual
     */
    toggleSubcategoria: function(subcatName) {
        if (!this.chart) return;
        
        const checkbox = document.querySelector(`.subcat-checkbox[data-subcat="${subcatName}"]`);
        if (!checkbox) return;
        
        if (checkbox.checked) {
            this.chart.showSeries(subcatName);
        } else {
            this.chart.hideSeries(subcatName);
        }
        
        // Atualizar checkbox "Selecionar Todas"
        const allCheckboxes = document.querySelectorAll('.subcat-checkbox');
        const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
        const toggleAll = document.getElementById('toggleAllSubcats');
        if (toggleAll) {
            toggleAll.checked = allChecked;
        }
        
        // Atualizar contador
        this.updateSelectedCount();
        
        // Atualizar eixo Y
        this.updateYAxis();
    },
    
    /**
     * Atualiza o eixo Y baseado nas séries visíveis
     */
    updateYAxis: function() {
        if (!this.chart) return;
        
        // Obter todas as séries visíveis
        const visibleSeries = this.chart.w.config.series.filter((serie, idx) => {
            return !this.chart.w.globals.collapsedSeriesIndices.includes(idx);
        });
        
        if (visibleSeries.length === 0) return;
        
        // Encontrar max dos valores visíveis
        let allValues = [];
        visibleSeries.forEach(serie => {
            allValues = allValues.concat(serie.data.filter(v => v !== null && v !== undefined));
        });
        
        if (allValues.length === 0) return;
        
        const maxValue = Math.max(...allValues);
        
        // Adicionar margem de 10% no topo
        const margin = maxValue * 0.1;
        const newMax = maxValue + margin;
        
        // Atualizar eixo Y - SEMPRE começando em 0
        this.chart.updateOptions({
            yaxis: {
                min: 0,
                max: newMax
            }
        });
    },
    
    /**
     * Ordena tabela por coluna
     */
    sortBy: function(column) {
        console.log('sortBy called:', column); // Debug
        
        // Toggle direction se mesma coluna, senão descending
        if (this.sortColumn === column) {
            this.sortDirection = this.sortDirection === 'desc' ? 'asc' : 'desc';
        } else {
            this.sortColumn = column;
            this.sortDirection = 'desc'; // Default para desc (maiores primeiro)
        }
        
        console.log('Sort state:', this.sortColumn, this.sortDirection); // Debug
        
        // Re-renderizar conteúdo do modal
        this.render();
    },
    
    /**
     * Ordena subcategorias pela coluna atual
     */
    sortSubcategories: function(subcats) {
        const col = this.sortColumn;
        const dir = this.sortDirection === 'asc' ? 1 : -1;
        
        return subcats.sort((a, b) => {
            let valA, valB;
            
            switch(col) {
                case 'media_3m':
                    valA = a.media_3m;
                    valB = b.media_3m;
                    break;
                case 'valor_atual':
                    valA = a.valor_atual;
                    valB = b.valor_atual;
                    break;
                case 'vs_media_3m':
                    valA = a.media_3m > 0 ? ((a.valor_atual - a.media_3m) / a.media_3m) * 100 : 0;
                    valB = b.media_3m > 0 ? ((b.valor_atual - b.media_3m) / b.media_3m) * 100 : 0;
                    break;
                case 'pct_receita':
                    valA = a.pct_receita;
                    valB = b.pct_receita;
                    break;
                case 'tendencia':
                    valA = a.variacao_tendencia || 0;
                    valB = b.variacao_tendencia || 0;
                    break;
                default:
                    return 0;
            }
            
            if (valA < valB) return -1 * dir;
            if (valA > valB) return 1 * dir;
            return 0;
        });
    },
    
    // Funções auxiliares de formatação
    formatCurrency: (val) => val.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }),
    
    formatVariacao: function(val) {
        const ehDespesa = this.currentData?.eh_despesa || false;
        let icon;
        
        if (ehDespesa) {
            // Para despesas: crescer é ruim, cair é bom
            icon = val > 0 ? '🔺' : (val < 0 ? '🔻' : '➡️'); // mantém ícones direcionais
        } else {
            // Para receitas: crescer é bom, cair é ruim
            icon = val > 0 ? '🔺' : (val < 0 ? '🔻' : '➡️');
        }
        
        const sign = val > 0 ? '+' : '';
        return `${sign}${val.toFixed(1)}% ${icon}`;
    },
    
    getVariacaoClass: function(val) {
        const ehDespesa = this.currentData?.eh_despesa || false;
        
        if (ehDespesa) {
            // Para despesas: crescer é ruim (vermelho), cair é bom (verde)
            if (val > 5) return 'negative'; // cresceu = vermelho
            if (val < -5) return 'positive'; // caiu = verde
        } else {
            // Para receitas: crescer é bom (verde), cair é ruim (vermelho)
            if (val > 5) return 'positive'; // cresceu = verde
            if (val < -5) return 'negative'; // caiu = vermelho
        }
        return 'neutral';
    },
    
    getTendenciaClass: function(status) {
        if (status === 'ruim') return 'tendencia-ruim';     // Subindo - vermelho
        if (status === 'bom') return 'tendencia-bom';        // Descendo - verde
        return 'tendencia-neutro';                            // Estável - cinza
    },
    
    getFlutuacaoClass: function(flutuacao) {
        if (flutuacao === 'Alta') return 'high';
        if (flutuacao === 'Média') return 'medium';
        return 'low';
    }
};

// Inicializar quando DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => KPIDetailsModal.init());
} else {
    KPIDetailsModal.init();
}
