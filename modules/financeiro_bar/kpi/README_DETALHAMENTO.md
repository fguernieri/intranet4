# Sistema de Detalhamento de Categorias - KPI TAP

## 📁 Estrutura de Arquivos

```
modules/financeiro_bar/kpi/
├── kpitap.php                          # Página principal com gráficos resumidos
├── api/
│   └── get_category_details.php        # Endpoint REST que retorna análises detalhadas
├── components/
│   └── category_detail_modal.php       # Template HTML do modal
├── js/
│   └── kpi_details.js                  # Lógica JavaScript (fetch, renderização, interação)
└── css/
    └── kpi_modals.css                  # Estilos do modal e componentes
```

## 🎯 Funcionalidades Implementadas

### 1. **Modal de Detalhamento**
- Abre sobre a página atual (sem reload)
- Fecha com X, ESC ou clique fora
- Layout responsivo

### 2. **Análises Automáticas**
- ✅ **Maior Crescimento**: subcategoria com maior % aumento vs mês anterior
- ✅ **Maior Redução**: subcategoria com maior % queda
- ✅ **Maior Flutuação**: baseado no Coeficiente de Variação (CV)
- ✅ **Mais Estável**: menor CV
- ✅ **Maior Participação**: % sobre categoria pai

### 3. **Gráfico de Evolução (12 meses)**
- Linha verde: Receita Operacional
- Linha vermelha: Total da Categoria
- Linha preta pontilhada: % sobre Receita
- Linhas coloridas: Cada subcategoria (toggle on/off)
- Controles: checkboxes para mostrar/ocultar séries
- Zoom e pan habilitados

### 4. **Tabela Detalhada**
Colunas:
- **Subcategoria**: nome
- **Valor Atual**: R$ do último mês fechado
- **% Receita**: percentual sobre receita total
- **% Cat. Pai**: percentual sobre total da categoria
- **Var. Mês**: variação vs mês anterior (🔺🔻➡️)
- **Flutuação**: classificação (Baixa/Média/Alta)
- **Evolução**: mini-gráfico sparkline dos 12 meses

### 5. **Resumo Executivo**
Cards com:
- Total Atual (R$)
- % sobre Receita
- Maior Subcategoria
- Flutuação Média (com badge colorido)

### 6. **Métricas Calculadas**
Para cada subcategoria:
- Valor atual e anterior
- Variação mês a mês (%)
- Média dos 12 meses
- Desvio padrão
- Coeficiente de Variação (CV)
- Crescimento acumulado (primeiro vs último)
- % sobre categoria pai
- % sobre receita
- Array com 12 valores mensais

## 🔧 Como Usar

### Na página principal (kpitap.php):
Cada gráfico possui um botão **"🔍 Detalhar"** ao lado do título.

### Ao clicar:
1. Modal abre com loading
2. Fetch assíncrono para `api/get_category_details.php`
3. Renderização dos componentes:
   - Resumo executivo
   - Gráfico interativo
   - Análises automáticas
   - Tabela com sparklines

### Interações:
- **Checkboxes**: mostrar/ocultar séries do gráfico
- **Hover**: tooltip com valores detalhados
- **Zoom**: scroll no gráfico
- **Ordenação**: clique nas colunas da tabela (futuro)

## 📊 Métricas de Análise

### Coeficiente de Variação (CV)
```
CV = (Desvio Padrão / Média) × 100
```
- **Baixa**: CV < 10% (estável)
- **Média**: 10% ≤ CV ≤ 20% (moderada)
- **Alta**: CV > 20% (volátil)

### Variação Mês a Mês
```
Var% = ((Atual - Anterior) / Anterior) × 100
```
- 🔺 Positiva: crescimento
- 🔻 Negativa: redução
- ➡️ Neutra: sem mudança significativa

### Crescimento Acumulado
```
Cresc% = ((Último - Primeiro) / Primeiro) × 100
```
Compara o último mês com o primeiro dos 12 meses analisados.

## 🎨 Categorias Suportadas

1. **CUSTO FIXO**
2. **DESPESA FIXA**
3. **CUSTO VARIÁVEL** (normalizado como "CUSTO VARIAVEL" na API)
4. **TRIBUTOS**
5. **DESPESAS DE VENDA**
6. **INVESTIMENTO INTERNO**

## 🔌 API Endpoint

### GET `api/get_category_details.php`

**Parâmetros:**
- `categoria` (required): nome da categoria (ex: "CUSTO FIXO")
- `periodo` (optional): formato YYYY/MM (default: último mês fechado)

**Resposta (JSON):**
```json
{
  "success": true,
  "categoria": "CUSTO FIXO",
  "periodo_analise": "01/2024 - 12/2024",
  "chart": {
    "labels": ["Jan/2024", "Fev/2024", ...],
    "revenue": [100000, 105000, ...],
    "total": [45000, 47000, ...],
    "pct": [45.0, 44.8, ...],
    "subcategorias": {
      "Salários": [18000, 18500, ...],
      "Aluguel": [12000, 12000, ...]
    }
  },
  "resumo": {
    "total_atual": 45000,
    "pct_receita": 12.5,
    "maior_subcategoria": "Salários",
    "flutuacao_geral": 15.3
  },
  "subcategorias": [
    {
      "nome": "Salários",
      "valor_atual": 18000,
      "valor_anterior": 18500,
      "variacao_mes": -2.7,
      "media_12m": 18200,
      "desvio_padrao": 350,
      "cv": 1.92,
      "flutuacao": "Baixa",
      "crescimento_acumulado": 5.2,
      "pct_categoria_pai": 40.0,
      "pct_receita": 15.0,
      "valores_12m": [17000, 17500, ...]
    }
  ],
  "analises": {
    "maior_crescimento": { ... },
    "maior_reducao": { ... },
    "maior_flutuacao": { ... },
    "mais_estavel": { ... },
    "maior_participacao": { ... }
  }
}
```

## 🚀 Melhorias Futuras

- [ ] Exportar dados para Excel/PDF
- [ ] Comparação com mesmo período ano anterior
- [ ] Alertas automáticos (quando ultrapassar thresholds)
- [ ] Drill-down em lançamentos individuais
- [ ] Previsão/forecast baseado em tendência
- [ ] Comentários/notas em subcategorias
- [ ] Gráficos de pizza para participação
- [ ] Histórico de mudanças nas subcategorias

## 📝 Notas Técnicas

- **Framework JS**: Vanilla JavaScript (ES6+)
- **Gráficos**: ApexCharts 3.x
- **Backend**: PHP 7+ com Supabase
- **CSS**: Custom (não usa Tailwind no modal para evitar conflitos)
- **Compatibilidade**: Chrome, Firefox, Safari, Edge (últimas versões)

---

**Desenvolvido para:** Bar da Fábrica (TAP) - Sistema Intranet
**Data:** 2025
