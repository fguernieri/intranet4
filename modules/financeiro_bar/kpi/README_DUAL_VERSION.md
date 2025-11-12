# 🔄 Sistema Dual KPI - TAP e WAB

## 📋 Visão Geral

O sistema KPI agora possui **duas versões paralelas** para diferentes unidades de negócio:
- **TAP** - The Apartment Bar
- **WAB** - We Are Bastards

Ambas as versões compartilham a mesma estrutura de código, mas utilizam **tabelas de banco de dados diferentes**.

---

## 📂 Estrutura de Arquivos

### Versão TAP (Original)
```
modules/financeiro_bar/kpi/
├── kpitap.php                           # Dashboard TAP
├── api/
│   ├── get_category_details.php         # API detalhes TAP
│   └── get_dre_analysis.php             # API DRE TAP
├── js/
│   └── kpi_details.js                   # JavaScript TAP
└── README_DATABASE.md                   # Documentação TAP
```

### Versão WAB (Nova)
```
modules/financeiro_bar/kpi/
├── kpiwab.php                           # Dashboard WAB
├── api/
│   ├── get_category_details_wab.php     # API detalhes WAB
│   └── get_dre_analysis_wab.php         # API DRE WAB
├── js/
│   └── kpi_details_wab.js               # JavaScript WAB
└── README_DATABASE_WAB.md               # Documentação WAB
```

### Arquivos Compartilhados
```
modules/financeiro_bar/kpi/
├── css/
│   └── kpi_modals.css                   # CSS comum (v=4.0)
└── README_DUAL_VERSION.md               # Este arquivo
```

---

## 🗄️ Mapeamento de Tabelas

| Entidade | Tabela TAP | Tabela WAB |
|----------|------------|------------|
| **Receitas Agregadas** | `freceitatap` | `freceitawab` |
| **Despesas Agregadas** | `fdespesastap` | `fdespesaswab` |
| **Despesas Detalhadas** | `fdespesastap_detalhes` | `fdespesaswab_detalhes` |
| **Metas** | `fmetastap` | `fmetaswab` |

### Estrutura Idêntica

Todas as tabelas WAB possuem **exatamente a mesma estrutura** que as tabelas TAP:

#### freceitawab / freceitatap
- `data_mes` (DATE)
- `categoria` (VARCHAR)
- `total_receita_mes` (DECIMAL)

#### fdespesaswab / fdespesastap
- `data_mes` (DATE)
- `categoria_pai` (VARCHAR)
- `categoria` (VARCHAR)
- `total_receita_mes` (DECIMAL)

#### fmetaswab / fmetastap
- `CATEGORIA` (VARCHAR)
- `SUBCATEGORIA` (VARCHAR)
- `META` (DECIMAL)
- `PERCENTUAL` (DECIMAL)
- `DATA_META` (DATE)

---

## 🔗 Navegação Entre Versões

### No Dashboard TAP (kpitap.php)
```html
<button>Selecionar Bar ▾</button>
<div>
  <a href="kpiwab.php">WAB (We Are Bastards)</a>
</div>
```

### No Dashboard WAB (kpiwab.php)
```html
<button>Selecionar Bar ▾</button>
<div>
  <a href="kpitap.php">TAP (The Apartment Bar)</a>
</div>
```

---

## 🎯 URLs de Acesso

### TAP - The Apartment Bar
- **Dashboard:** `/modules/financeiro_bar/kpi/kpitap.php`
- **API Categorias:** `/modules/financeiro_bar/kpi/api/get_category_details.php`
- **API DRE:** `/modules/financeiro_bar/kpi/api/get_dre_analysis.php`

### WAB - We Are Bastards
- **Dashboard:** `/modules/financeiro_bar/kpi/kpiwab.php`
- **API Categorias:** `/modules/financeiro_bar/kpi/api/get_category_details_wab.php`
- **API DRE:** `/modules/financeiro_bar/kpi/api/get_dre_analysis_wab.php`

---

## ⚙️ Funcionalidades Idênticas

Ambas as versões possuem **exatamente as mesmas funcionalidades**:

### ✅ Dashboard Principal
- Seleção de período mensal
- Cards de categorias principais
- Tabela DRE analítica com subcategorias expansíveis
- Gráficos de evolução (ApexCharts)

### ✅ Modal de Detalhes
- Análise temporal de 6 meses
- Tabela sortable com 11 colunas
- Cards de resumo com métricas
- Sistema de tendências (5 fatores)
- Gráficos interativos por subcategoria

### ✅ Análise DRE
- 13 linhas calculadas
- Subcategorias expansíveis
- Lógica de cores invertida para receita
- Métricas: Média 6M, Média 3M, Mês Ant., Valor Atual, vs M3, Var. M

### ✅ Métricas Calculadas
- Valor Atual / Anterior
- Médias 3M e 6M
- Variação vs Média 3M
- Variação Mês a Mês
- % sobre Receita
- Análise de Tendência

---

## 🔧 Diferenças Técnicas

### Nomes de Tabelas
- **TAP:** Usa sufixo `tap` (freceitatap, fdespesastap, fmetastap)
- **WAB:** Usa sufixo `wab` (freceitawab, fdespesaswab, fmetaswab)

### Títulos e Labels
- **TAP:** "TAP (The Apartment Bar)"
- **WAB:** "WAB (We Are Bastards)"

### Arquivos JavaScript
- **TAP:** `kpi_details.js` → chama `get_category_details.php`
- **WAB:** `kpi_details_wab.js` → chama `get_category_details_wab.php`

### Arquivos PHP Dashboard
- **TAP:** `kpitap.php` → chama `get_dre_analysis.php`
- **WAB:** `kpiwab.php` → chama `get_dre_analysis_wab.php`

---

## 📊 Fluxo de Requisições

### TAP
```
kpitap.php
  │
  ├─→ freceitatap (receitas)
  ├─→ fdespesastap (despesas)
  ├─→ fmetastap (metas)
  │
  └─→ JavaScript
      │
      ├─→ kpi_details.js
      │   └─→ api/get_category_details.php
      │       ├─→ freceitatap
      │       └─→ fdespesastap
      │
      └─→ loadDREAnalysis()
          └─→ api/get_dre_analysis.php
              ├─→ freceitatap
              └─→ fdespesastap
```

### WAB
```
kpiwab.php
  │
  ├─→ freceitawab (receitas)
  ├─→ fdespesaswab (despesas)
  ├─→ fmetaswab (metas)
  │
  └─→ JavaScript
      │
      ├─→ kpi_details_wab.js
      │   └─→ api/get_category_details_wab.php
      │       ├─→ freceitawab
      │       └─→ fdespesaswab
      │
      └─→ loadDREAnalysis()
          └─→ api/get_dre_analysis_wab.php
              ├─→ freceitawab
              └─→ fdespesaswab
```

---

## 🚀 Deployment

### Arquivos Criados para WAB
1. ✅ `kpiwab.php` - Dashboard principal
2. ✅ `api/get_category_details_wab.php` - API de detalhes
3. ✅ `api/get_dre_analysis_wab.php` - API DRE
4. ✅ `js/kpi_details_wab.js` - JavaScript modal
5. ✅ `README_DATABASE_WAB.md` - Documentação

### Arquivos Modificados
1. ✅ `kpitap.php` - Adicionado link para WAB no dropdown
2. ✅ Título TAP atualizado para "TAP (The Apartment Bar)"

### Arquivos Compartilhados (Não Modificados)
1. ✅ `css/kpi_modals.css` - Usado por ambos (v=4.0)
2. ✅ `README_DETALHAMENTO.md` - Documentação geral

---

## 🧪 Checklist de Testes

### TAP (Regressão)
- [ ] Dashboard carrega corretamente
- [ ] Períodos disponíveis listados
- [ ] Cards de categorias exibidos
- [ ] Modal de detalhes abre e carrega dados
- [ ] Tabela sortable funciona
- [ ] DRE expansível funciona
- [ ] Navegação para WAB funciona

### WAB (Novo)
- [ ] Dashboard carrega corretamente
- [ ] Períodos disponíveis listados (WAB)
- [ ] Cards de categorias exibidos (WAB)
- [ ] Modal de detalhes abre e carrega dados (WAB)
- [ ] Tabela sortable funciona
- [ ] DRE expansível funciona
- [ ] Navegação para TAP funciona

---

## 🔍 Troubleshooting

### Problema: "Tabela não encontrada"
**Causa:** Tabelas WAB não existem no banco  
**Solução:** Criar tabelas `freceitawab`, `fdespesaswab`, `fmetaswab` com mesma estrutura das TAP

### Problema: "API retorna erro 404"
**Causa:** Arquivos API WAB não foram criados  
**Solução:** Verificar se `get_category_details_wab.php` e `get_dre_analysis_wab.php` existem

### Problema: "JavaScript não carrega dados"
**Causa:** Referência incorreta ao arquivo JS  
**Solução:** Verificar se `kpiwab.php` referencia `kpi_details_wab.js?v=4.0`

### Problema: "Cache antigo"
**Causa:** Browser cache dos arquivos JS/CSS  
**Solução:** Limpar cache ou incrementar versão (v=4.0 → v=4.1)

---

## 📝 Manutenção

### Atualizações Futuras

Quando adicionar novas funcionalidades, lembre-se de:

1. **Duplicar mudanças** para ambas as versões (TAP e WAB)
2. **Atualizar versão** do cache CSS/JS
3. **Testar ambas** as páginas
4. **Documentar** nos README específicos

### Arquivos que Devem Ser Sincronizados

Ao modificar:
- `kpitap.php` → atualizar `kpiwab.php`
- `get_category_details.php` → atualizar `get_category_details_wab.php`
- `get_dre_analysis.php` → atualizar `get_dre_analysis_wab.php`
- `kpi_details.js` → atualizar `kpi_details_wab.js`

### Arquivo Único (Não Duplicar)
- `css/kpi_modals.css` - Compartilhado por ambos

---

## 🎨 Customizações Futuras

Se precisar diferenciar visualmente TAP e WAB:

### Cores
```css
/* TAP - Amarelo */
.tap-theme { color: #fbbf24; }

/* WAB - Azul (exemplo) */
.wab-theme { color: #3b82f6; }
```

### Logos
```html
<!-- TAP -->
<img src="assets/logo-tap.png" alt="TAP">

<!-- WAB -->
<img src="assets/logo-wab.png" alt="WAB">
```

---

## 📞 Suporte

Para dúvidas específicas:
- **Estrutura TAP:** Ver `README_DATABASE.md`
- **Estrutura WAB:** Ver `README_DATABASE_WAB.md`
- **Sistema Dual:** Este arquivo

---

**Versão:** 4.0  
**Data de Criação:** 12 de Novembro de 2025  
**Desenvolvido para:** The Apartment Bar (TAP) & We Are Bastards (WAB)
