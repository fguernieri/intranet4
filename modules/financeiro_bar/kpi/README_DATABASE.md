# 📊 Documentação de Banco de Dados - KPI TAP

## 📋 Índice
- [Visão Geral](#visão-geral)
- [Tabelas Utilizadas](#tabelas-utilizadas)
- [APIs e Endpoints](#apis-e-endpoints)
- [Estrutura de Dados](#estrutura-de-dados)
- [Fluxo de Dados](#fluxo-de-dados)

---

## 🎯 Visão Geral

Este sistema gerencia os **Indicadores de Performance (KPIs)** do TAP (The Apartment Bar), processando dados financeiros de receitas e despesas para gerar análises mensais, comparações temporais, tendências e relatórios DRE (Demonstrativo de Resultado do Exercício).

**Arquivos principais:**
- `kpitap.php` - Dashboard principal
- `api/get_category_details.php` - Detalhamento de categorias
- `api/get_dre_analysis.php` - Análise DRE com subcategorias
- `js/kpi_details.js` - Modal de detalhes interativo
- `css/kpi_modals.css` - Estilos dos componentes

---

## 📊 Tabelas Utilizadas

### 1️⃣ **freceitatap** (Receitas Agregadas)
Armazena receitas mensais consolidadas do TAP.

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `data_mes` | DATE | Mês de referência (formato: YYYY-MM-01) |
| `categoria` | VARCHAR | Nome da categoria de receita |
| `total_receita_mes` | DECIMAL | Valor total da receita no mês |

**Uso:**
- Cálculo de receita total por período
- Percentual de despesas sobre receita (% Receita)
- Identificação de receitas operacionais vs não operacionais
- Tendências de faturamento

**Categorias Especiais (Não Operacionais):**
- `ENTRADA DE REPASSE DE SALARIOS`
- `ENTRADA DE REPASSE EXTRA DE SALARIOS`
- `ENTRADA DE REPASSE`
- `ENTRADA DE REPASSE OUTROS`

**Consultas:**
```php
// kpitap.php (linha 40-45)
$todos_dados = $supabase->select('freceitatap', [
    'select' => 'data_mes',
    'order' => 'data_mes.desc',
    'limit' => 1
]);

// kpitap.php (linha 91-99)
$dados_receita = $supabase->select('freceitatap', [
    'select' => '*',
    'filters' => [
        'data_mes' => "eq.{$data_referencia}"
    ]
]);

// get_category_details.php (linha 88-96)
$all_receitas = $supabase->select('freceitatap', [
    'select' => 'data_mes,total_receita_mes',
    'filters' => [
        'data_mes' => "gte.{$start}"
    ],
    'order' => 'data_mes.asc',
    'limit' => 1000
]);

// get_dre_analysis.php (linha 45-52)
$receitas = $supabase->select('freceitatap', [
    'select' => '*',
    'filters' => [
        'data_mes' => "gte.$data_inicial",
        'data_mes' => "lte.$data_final"
    ],
    'order' => 'data_mes.asc'
]);
```

---

### 2️⃣ **fdespesastap** (Despesas Agregadas)
Armazena despesas mensais consolidadas do TAP por categoria pai e subcategoria.

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `data_mes` | DATE | Mês de referência (formato: YYYY-MM-01) |
| `categoria_pai` | VARCHAR | Categoria principal (ex: CUSTO FIXO, TRIBUTOS) |
| `categoria` | VARCHAR | Subcategoria específica |
| `total_receita_mes` | DECIMAL | Valor total da despesa no mês |

**Categorias Pai Principais:**
- `TRIBUTOS` - Impostos e taxas
- `CUSTO VARIÁVEL` / `CUSTO VARIAVEL` - Custos variáveis
- `CUSTO FIXO` - Custos fixos mensais
- `DESPESA FIXA` - Despesas fixas
- `DESPESA DE VENDA` / `DESPESA VENDA` - Despesas relacionadas a vendas
- `INVESTIMENTO INTERNO` - Investimentos internos
- `SAÍDA NÃO OPERACIONAL` / `SAIDA` - Saídas não operacionais

**Uso:**
- Agrupamento de despesas por categoria
- Cálculo de percentuais sobre receita
- Análise de estrutura de custos
- DRE detalhado com subcategorias

**Consultas:**
```php
// kpitap.php (linha 100-108)
$dados_despesa = $supabase->select('fdespesastap', [
    'select' => '*',
    'filters' => [
        'data_mes' => "eq.{$data_referencia}"
    ]
]);

// get_category_details.php (linha 116-124)
$all_despesas_detalhes = $supabase->select('fdespesastap', [
    'select' => 'data_mes,categoria_pai,categoria,total_receita_mes',
    'filters' => [
        'data_mes' => "gte.{$start}"
    ],
    'order' => 'data_mes.asc',
    'limit' => 10000
]);

// get_dre_analysis.php (linha 55-62)
$despesas = $supabase->select('fdespesastap', [
    'select' => '*',
    'filters' => [
        'data_mes' => "gte.$data_inicial",
        'data_mes' => "lte.$data_final"
    ],
    'order' => 'data_mes.asc'
]);
```

---

### 3️⃣ **fdespesastap_detalhes** (Despesas Detalhadas)
Tabela auxiliar com detalhamento adicional de despesas (uso específico no dashboard).

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `data_mes` | DATE | Mês de referência |
| `categoria_pai` | VARCHAR | Categoria principal |
| `categoria` | VARCHAR | Subcategoria |
| `valor` | DECIMAL | Valor da despesa |
| *(outras colunas)* | MIXED | Campos adicionais específicos |

**Uso:**
- Detalhamento de lançamentos individuais (opcional)
- Drill-down em análises específicas

**Consultas:**
```php
// kpitap.php (linha 109-117)
$dados_despesa_detalhes = $supabase->select('fdespesastap_detalhes', [
    'select' => '*',
    'filters' => [
        'data_mes' => "eq.{$data_referencia}"
    ]
]);
```

---

### 4️⃣ **fmetastap** (Metas e Percentuais)
Armazena metas financeiras por categoria e subcategoria.

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `CATEGORIA` | VARCHAR | Categoria principal |
| `SUBCATEGORIA` | VARCHAR | Subcategoria específica |
| `META` | DECIMAL | Valor da meta estabelecida |
| `PERCENTUAL` | DECIMAL | Percentual ideal sobre receita |
| `DATA_META` | DATE | Período da meta |

**Uso:**
- Comparação entre realizado vs meta
- Cálculo de atingimento de metas
- Benchmarking de percentuais ideais

**Consultas:**
```php
// kpitap.php (linha 143-151)
$resultado = $supabase->select('fmetastap', [
    'select' => 'CATEGORIA, SUBCATEGORIA, META, PERCENTUAL, DATA_META',
    'filters' => [
        'DATA_META' => "eq.{$data_referencia}"
    ]
]);

// kpitap.php (linha 292-298)
$resultado = $supabase->select('fmetastap', [
    'select' => 'META, PERCENTUAL',
    'filters' => [
        'CATEGORIA' => "eq.{$categoria_pai}",
        'SUBCATEGORIA' => "eq.{$categoria_nome}",
        'DATA_META' => "eq.{$data_referencia}"
    ]
]);
```

---

## 🔌 APIs e Endpoints

### 📍 **GET** `/api/get_category_details.php`
Retorna detalhamento completo de uma categoria específica com análise temporal.

**Parâmetros:**
- `categoria` (string, obrigatório) - Nome da categoria pai
- `periodo` (string, opcional) - Formato YYYY/MM (padrão: último mês fechado)

**Response:**
```json
{
  "success": true,
  "categoria": "CUSTO FIXO",
  "periodo": "2025/11",
  "months": {
    "2025-06": {
      "label": "Jun/2025",
      "revenue": 150000.00,
      "total": 45000.00,
      "subcategorias": [
        {
          "nome": "ALUGUEL",
          "valor_atual": 12000.00,
          "media_3m": 12000.00,
          "media_6m": 11800.00,
          "pct_receita": 8.0,
          "vs_media_3m": 0.0,
          "variacao_mes": 0.0,
          "tendencia": "Estável",
          "valor_anterior": 12000.00,
          "meses": [11500, 12000, 12000, 12000, 12000, 12000]
        }
      ]
    }
  },
  "resumo": {
    "total_atual": 45000.00,
    "total_anterior": 44500.00,
    "variacao_total_mes": 1.12,
    "media_3m": 44800.00,
    "n_subcategorias": 8,
    "maior_subcategoria": {
      "nome": "ALUGUEL",
      "pct": 26.67,
      "valor": 12000.00
    },
    "tendencia_geral": "Subindo",
    "receita_atual": 150000.00
  }
}
```

**Tabelas consultadas:**
- `freceitatap` - Receitas para cálculo de %
- `fdespesastap` - Despesas agregadas com subcategorias

---

### 📍 **GET** `/api/get_dre_analysis.php`
Retorna análise DRE completa com todas as linhas calculadas e subcategorias expansíveis.

**Parâmetros:**
- `periodo` (string, obrigatório) - Formato YYYY/MM

**Response:**
```json
{
  "success": true,
  "periodo": "2025/11",
  "data_final": "2025-11-01",
  "linhas": {
    "receita_operacional": {
      "nome": "RECEITA OPERACIONAL",
      "tipo": "receita",
      "media_6m": 148500.00,
      "media_3m": 151000.00,
      "valor_anterior": 149000.00,
      "valor_atual": 152000.00,
      "vs_media_3m": 0.66,
      "variacao_mes": 2.01,
      "subcategorias": [
        {
          "nome": "VENDA DE BEBIDAS",
          "media_6m": 95000.00,
          "media_3m": 97000.00,
          "valor_anterior": 96000.00,
          "valor_atual": 98000.00,
          "vs_media_3m": 1.03,
          "variacao_mes": 2.08
        }
      ]
    },
    "tributos": { ... },
    "receita_liquida": { ... },
    "custo_variavel": { ... },
    "lucro_bruto": { ... },
    "custo_fixo": { ... },
    "despesa_fixa": { ... },
    "despesa_venda": { ... },
    "lucro_liquido": { ... },
    "investimento_interno": { ... },
    "receita_nao_operacional": { ... },
    "saidas_nao_operacionais": { ... },
    "impacto_caixa": { ... }
  }
}
```

**Linhas DRE Calculadas:**
1. **Receita Operacional** (campo: `receita_operacional`)
2. **(-) Tributos** (campo: `tributos`)
3. **Receita Líquida** (cálculo: Receita Op - Tributos)
4. **(-) Custo Variável** (campo: `custo_variavel`)
5. **Lucro Bruto** (cálculo: Receita Líq - Custo Var)
6. **(-) Custo Fixo** (campo: `custo_fixo`)
7. **(-) Despesa Fixa** (campo: `despesa_fixa`)
8. **(-) Despesas de Venda** (campo: `despesa_venda`)
9. **Lucro Líquido** (cálculo: Lucro Bruto - CF - DF - DV)
10. **(-) Investimento Interno** (campo: `investimento_interno`)
11. **Receitas Não Operacionais** (campo: `receita_nao_operacional`)
12. **(-) Saídas Não Operacionais** (campo: `saidas_nao_operacionais`)
13. **(=) Impacto Caixa** (cálculo: LL - II + RNO - SNO)

**Tabelas consultadas:**
- `freceitatap` - Receitas operacionais e não operacionais
- `fdespesastap` - Todas as categorias de despesas com subcategorias

---

## 📐 Estrutura de Dados

### Métricas Calculadas

Todas as APIs retornam as seguintes métricas calculadas:

| Métrica | Descrição | Fórmula |
|---------|-----------|---------|
| **Valor Atual** | Valor do mês selecionado | Soma do mês atual |
| **Valor Anterior** | Valor do mês anterior | Soma do mês anterior |
| **Média 3M** | Média dos últimos 3 meses | (M-2 + M-1 + M-0) / 3 |
| **Média 6M** | Média dos últimos 6 meses | (M-5 + M-4 + M-3 + M-2 + M-1 + M-0) / 6 |
| **vs Média 3M** | Variação percentual vs média 3M | ((Atual - M3) / M3) × 100 |
| **Var. M** | Variação mês a mês | ((Atual - Anterior) / Anterior) × 100 |
| **% Receita** | Percentual sobre receita | (Valor / Receita) × 100 |

### Análise de Tendência (5 Fatores)

Sistema de pontuação ponderada para classificação de tendências:

| Fator | Peso | Critério | Pontuação |
|-------|------|----------|-----------|
| **Valor vs M6** | 2 | Variação > ±5% | +2 / -2 / 0 |
| **Valor vs M3** | 3 | Variação > ±5% | +3 / -3 / 0 |
| **Var. Mês** | 2 | Variação > ±5% | +2 / -2 / 0 |
| **Regressão Linear 3M** | 3 | Inclinação > ±3% | +3 / -3 / 0 |
| **Primeiros 3M vs Últimos 3M** | 1 | Variação > ±10% | +1 / -1 / 0 |

**Classificação:**
- **Pontuação ≥ 3**: 🔺 Subindo
- **Pontuação ≤ -3**: 🔻 Descendo
- **-2 a +2**: ➡️ Estável

**Total possível:** 11 pontos

---

## 🔄 Fluxo de Dados

### 1. Dashboard Principal (`kpitap.php`)

```
┌─────────────────────────────────────────────────┐
│ 1. Carrega período disponível mais recente      │
│    SELECT data_mes FROM freceitatap             │
│    ORDER BY data_mes DESC LIMIT 1               │
└─────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────┐
│ 2. Busca dados do período selecionado           │
│    - freceitatap (receitas)                     │
│    - fdespesastap (despesas agregadas)          │
│    - fdespesastap_detalhes (detalhes)           │
│    - fmetastap (metas do período)               │
└─────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────┐
│ 3. Processa e agrega dados                      │
│    - Agrupa por categoria_pai                   │
│    - Calcula totais e percentuais               │
│    - Compara com metas                          │
└─────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────┐
│ 4. Renderiza dashboard                          │
│    - Cards de categorias principais             │
│    - Tabela DRE analítica                       │
│    - Gráficos de evolução                       │
└─────────────────────────────────────────────────┘
```

### 2. Modal de Detalhes (Clique em Categoria)

```
User Click → categoria = "CUSTO FIXO"
                      ↓
┌─────────────────────────────────────────────────┐
│ GET /api/get_category_details.php               │
│ ?categoria=CUSTO FIXO&periodo=2025/11           │
└─────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────┐
│ API busca últimos 6 meses                       │
│ - freceitatap (para % receita)                  │
│ - fdespesastap (categoria_pai = CUSTO FIXO)     │
└─────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────┐
│ Processa subcategorias                          │
│ - Agrupa por categoria (subcategoria)           │
│ - Calcula métricas 6M, 3M, vs M3, Var M        │
│ - Análise de tendência (5 fatores)             │
│ - Regressão linear                              │
└─────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────┐
│ JavaScript renderiza modal                      │
│ - kpi_details.js (958 linhas)                   │
│ - Tabela sortable com 11 colunas               │
│ - Cards de resumo                               │
│ - Gráficos por subcategoria                     │
└─────────────────────────────────────────────────┘
```

### 3. Tabela DRE Analítica

```
Page Load → período = "2025/11"
                      ↓
┌─────────────────────────────────────────────────┐
│ loadDREAnalysis(periodo)                        │
│ GET /api/get_dre_analysis.php?periodo=2025/11  │
└─────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────┐
│ API calcula 6 meses de histórico                │
│ - freceitatap (todas as receitas)               │
│ - fdespesastap (todas as despesas)              │
└─────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────┐
│ Organiza por mês + categoriza                   │
│ ├─ Receita Op vs Não Op                         │
│ ├─ Categorias por categoria_pai                 │
│ └─ Subcategorias por categoria                  │
└─────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────┐
│ Calcula todas as 13 linhas DRE                  │
│ ├─ Campos diretos (receita_operacional, etc)    │
│ └─ Campos calculados (receita_liquida, etc)     │
└─────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────┐
│ JavaScript renderiza tabela expansível          │
│ - renderDRETable(linhas)                        │
│ - Linhas principais clicáveis                   │
│ - Subcategorias expandem/colapsam              │
│ - Cores invertidas para receita                │
└─────────────────────────────────────────────────┘
```

---

## 🎨 Lógica de Cores

### Variações de Despesas (Padrão)
- 🔴 **Vermelho**: Aumento de despesa (ruim) - valor positivo
- 🟢 **Verde**: Redução de despesa (bom) - valor negativo
- ⚪ **Cinza**: Estável - valor próximo de zero

### Variações de Receita (Invertido)
Apenas para **RECEITA OPERACIONAL**:
- 🟢 **Verde**: Aumento de receita (bom) - valor positivo
- 🔴 **Vermelho**: Redução de receita (ruim) - valor negativo
- ⚪ **Cinza**: Estável - valor próximo de zero

### Tipos de Linha DRE
- 🟢 **Verde**: Receitas (receita, receita_nao_operacional)
- 🔴 **Vermelho**: Despesas (tributos, custos, despesas)
- 🟡 **Amarelo**: Resultados (receita_liquida, lucro_bruto, lucro_liquido, impacto_caixa)

---

## 📝 Notas Importantes

### Filtros de Data
Todas as consultas usam período de **6 meses fechados**:
- Mês final: Último mês disponível nos dados OU período selecionado
- Mês inicial: 5 meses antes do mês final
- Filtro: `data_mes >= inicio AND data_mes <= fim`

### Normalização de Dados
- Nomes de categorias são convertidos para UPPERCASE
- Espaços extras são removidos com trim()
- Comparações são case-insensitive

### Performance
- Limite padrão de 1000-10000 registros por consulta
- Dados agregados em memória PHP
- Cache client-side via versão CSS/JS (v=4.0)

### Timezone
- Sistema configurado para `America/Sao_Paulo`
- Todas as datas em formato `YYYY-MM-DD`

---

## 🔧 Dependências

### Backend
- **PHP 7.4+**
- **Supabase** (conexão via `supabase_connection.php`)
- **Session Management** (`auth.php`)

### Frontend
- **Tailwind CSS** (classes utilitárias)
- **ApexCharts** (gráficos)
- **Vanilla JavaScript ES6+**

### Arquivos Core
```
modules/financeiro_bar/kpi/
├── kpitap.php                    # Dashboard principal (2150 linhas)
├── api/
│   ├── get_category_details.php  # Detalhes de categoria (576 linhas)
│   └── get_dre_analysis.php      # Análise DRE (328 linhas)
├── js/
│   └── kpi_details.js            # Modal interativo (958 linhas)
├── css/
│   └── kpi_modals.css            # Estilos (589 linhas)
└── README_DATABASE.md            # Este arquivo
```

---

## 📞 Suporte

Para dúvidas ou problemas:
1. Verificar logs de erro: `error_log` no PHP
2. Console do navegador: JavaScript errors
3. Testar endpoints via Postman/curl
4. Validar estrutura das tabelas no Supabase

---

**Versão:** 4.0  
**Última atualização:** 12 de Novembro de 2025  
**Desenvolvido para:** The Apartment Bar (TAP)
