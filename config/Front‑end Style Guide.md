# Bastards Intranet – Front‑end Style Guide

**Versão:** 1.0 · **Última atualização:** 26 mai 2025

---

## Índice

1. Visão Geral
2. Convenções de Código

   1. PHP
   2. JavaScript
   3. CSS / Tailwind
3. Padrão de Tabelas Sortable *(implementado)*
4. Acessibilidade
5. Performance e Assets
6. Segurança Front‑end
7. Processo de Pull Request
8. Glossário
9. Changelog Resumido

---

## 1 Visão Geral *(em construção)*

> *Descreva aqui o propósito do guia, a stack usada (PHP + JS + Tailwind) e o público‑alvo.*

---

## 2 Convenções de Código *(esqueleto)*

### 2.1 PHP (PSR-12 + Boas Práticas)

* **Coding Style**: seguir PSR‑12 (4 espaços, `declare(strict_types=1);`, nomes claros).
* **PDO padrão**: sempre `PDO::ATTR_ERRMODE => ERRMODE_EXCEPTION`, `PDO::ATTR_DEFAULT_FETCH_MODE => FETCH_ASSOC`, `PDO::ATTR_EMULATE_PREPARES => false`.
* **Prepared statements obrigatórios**: jamais concatenar parâmetros; use placeholders `?` ou nomeados `:param`.
* **Aliases legíveis**: `NumeroPedido AS numero_pedido` → snake\_case na aplicação.
* **Funções utilitárias**: crie `db()->prepare()` wrapper para reduzir repetição.

### 2.2 Padrão de Filtros & Sanitização (`$_GET`)

* **Datas**: validar com `DateTime::createFromFormat('Y-m-d', $date)`; fallback para `date('Y-m-01')`.
* **Listas múltiplas**: certificar‑se de que `$_GET['vendedores']` é `array`, senão converter para `[string]`.
* **Whitelist** parâmetros permitidos; rejeitar extras.
* **Casting seguro**: sempre `intval`, `floatval`, ou `filter_var`.

### 2.3 JavaScript (ES modules, naming, lint rules pendentes)

 JavaScript *(ES modules, naming, lint rules pendentes)*

### 2.3 CSS / Tailwind *(naming layers, design tokens pendentes)*

---

## 3 Padrão de Tabelas Sortable

### 3.1 Quando usar

Use sempre que uma tabela precisar de ordenação cliente‑side e o volume de linhas for até \~2 000.

### 3.2 Passo‑a‑passo de implementação

1. **JavaScript nativo, sem plugins**
2. **Cabeçalhos chamam `sortTable()` via `onclick`**

   ```html
   <th onclick="sortTable(3)">🧑‍💼 Vendedor</th>
   ```
3. **Indique “clicável” no CSS** (`cursor-pointer`).
4. **Direção de ordenação** no atributo `data-sort-dir` do `<tbody>`.
5. **Detecção de tipo automática** dentro de `sortTable()`.
6. **Normalização de valores** (moedas, datas).
7. **Reinserção de linhas** removendo e readicionando `<tr>`.
8. **Feedback visual opcional** com pseudo‑elemento ▲▼.
9. **Acessibilidade mínima** – sem `tabindex="-1"`.
10. **Reutilização** – armazenar `sortTable()` em `/assets/js/sortable.js` ou inline.

### 3.3 Exemplo completo

*\[colar snippet final de tabela aqui quando necessário]*

### 3.4 Checklist rápido

* [ ] Todos `<th>` têm `onclick` correto?
* [ ] `<tbody>` começa com `data-sort-dir="asc"`?
* [ ] `sortTable()` normaliza números, moedas e datas?
* [ ] Testado em Chrome/Firefox/Safari?

---

## 4 Componentes PHP & Layout

### 4.1 Estrutura base de Cards KPI

* **Classe padrão**: `.card1` com padding médio, borda arredondada, sombra leve.
* **Conteúdo mínimo**: título (emoji opcional) + valor formatado.
* **Grid responsivo**: use `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5`.
* **Cores**: texto principal branco, ícone/título cinza claro, fundo `bg-white/5` no modo dark.

### 4.2 Sidebar (inclusão padrão)

* Sempre incluir via:

  ```php
  include __DIR__ . '/../../sidebar.php';
  ```
* **Um único arquivo** `sidebar.php` centraliza menu e estilos; não duplicar em módulos.
* Sidebar deve usar Tailwind utilitários; largura fixa (`w-64`) e scroll interno.

### 4.3 Paleta de Cores

| Uso                | Cor base                 | Comentário             |
| ------------------ | ------------------------ | ---------------------- |
| Primário           | `#eab308`                | amarelo (btn-acao)     |
| Primário \:hover   | `#ca8a04`                | amarelo mais escuro    |
| Azul primário      | `#2563EB`                | btn-acao-azul          |
| Azul \:hover       | `#1D4ED8`                | azul mais escuro       |
| Verde primário     | `#16A34A`                | btn-acao-verde         |
| Verde \:hover      | `#15803D`                | verde mais escuro      |
| Vermelho primário  | `#DC2626`                | btn-acao-vermelho      |
| Vermelho \:hover   | `#B91C1C`                | vermelho mais escuro   |
| Fundo card default | `rgba(255,255,255,0.05)` | 5 % branco (modo dark) |

**Diretrizes**

* Não introduzir novas cores direto no módulo; primeiro adicionar aqui e no `style.css`.
* Garantir contraste mínimo 4.5:1 sobre fundo `#111827`.
* Usar translucidez (`white/5`, `white/10`) p/ painéis em modo dark.

### 4.4 Botões e Emojis/Ícones

**Botões padrão**

* `.btn-acao` (amarelo), `.btn-acao-azul`, `.btn-acao-verde`, `.btn-acao-vermelho`.
* Altura 33 px, `min-width: 90px`, borda 4 px, transição `background-color .2s`.
* `:hover` escurece \~10 %; `:disabled` opacidade 50 % + cursor `not-allowed`.

**Emojis**

* Máx. 1 emoji por rótulo ou KPI, sempre antes do texto.
* Devem ter valor semântico (📦, 💰, 📅). Caso texto seja escondido, usar `aria-label`.

**Ícones**

* Biblioteca padrão: Lucide (React ou SVG). Tamanho 20 px, cor `currentColor`.
* Não misturar FontAwesome.

---

## 5 Acessibilidade *(em branco)* *(em branco)*

## 5 Performance e Assets

### 5.1 Diretrizes de ApexCharts

* **Biblioteca padrão**: Utilize **ApexCharts** para todos os gráficos e visualizações de dados.
* **Configuração livre**: Tema, dimensões, tooltips, animações e séries (incluindo série “Meta”) ficam a critério do desenvolvedor, adequando‑se às necessidades de cada tela.
* **Boas práticas**: Prefira datasets enxutos, aplique lazy‑load em dashboards pesados e reutilize instâncias quando possível.

*(Demais tópicos de performance serão detalhados futuramente.)*

## 6 Segurança Front‑end *(em branco)*

## 7 Processo de Pull Request *(em branco)*

## 8 Glossário *(em branco)*

## 9 Changelog Resumido *(em branco)*
