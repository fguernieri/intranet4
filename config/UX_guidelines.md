# Diretrizes de Desenvolvimento Mobile-First

Este documento estabelece padrões para geração de código de interfaces web usando uma abordagem mobile-first com Tailwind CSS. Siga estas diretrizes ao criar páginas para o sistema Bastards Brewery ou similares.

## Estrutura Básica de Documentos

### Cabeçalho HTML Padrão
```html
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>[Título da Página] - [Nome do Sistema]</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="[caminho-relativo]/assets/css/style.css">
</head>
```

### Estrutura Básica de Corpo
```html
<body class="bg-gray-900 flex items-center justify-center min-h-screen text-white p-4">
  <div class="bg-gray-800 p-4 sm:p-6 md:p-8 rounded-lg shadow-lg w-full [max-width-específico]">
    <!-- Conteúdo da página -->
  </div>
</body>
```

## Princípios Mobile-First

1. **Sempre parta do menor dispositivo para o maior**:
   - Defina estilos base para dispositivos móveis
   - Adicione media queries usando classes Tailwind com prefixos (`sm:`, `md:`, `lg:`, `xl:`)

2. **Meta Viewport é obrigatória**:
   - Sempre inclua: `<meta name="viewport" content="width=device-width, initial-scale=1.0">`

3. **Flexibilidade de layout**:
   - Use `flex-col` como padrão para mobile e `md:flex-row` para telas maiores
   - Dimensione elementos com `w-full` para ocupar 100% da largura disponível em mobile

## Componentes Padronizados

### Containers
- Página principal: `max-w-xs sm:max-w-sm md:max-w-md lg:max-w-lg`
- Páginas de conteúdo: `max-w-full sm:max-w-3xl md:max-w-6xl mx-auto px-4`

### Tipografia
- Títulos: `text-xl sm:text-2xl md:text-3xl font-bold text-[cor-primária] mb-4 sm:mb-6`
- Texto padrão: `text-sm sm:text-base text-gray-100`
- Texto secundário: `text-xs sm:text-sm text-gray-400`

### Formulários
```html
<form action="[action]" method="POST" class="space-y-3 sm:space-y-4">
  <div>
    <label class="block mb-1 text-sm sm:text-base">[Rótulo]</label>
    <input 
      type="[tipo]" 
      name="[nome]" 
      required
      class="border border-gray-600 rounded w-full px-3 py-2 bg-gray-700 placeholder-gray-400"
    >
  </div>
  
  <!-- Botão de submissão -->
  <button 
    type="submit"
    class="w-full bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold py-2 sm:py-3 px-4 rounded transition duration-200"
  >
    [Texto do Botão]
  </button>
</form>
```

### Botões
- Primário: `bg-yellow-500 hover:bg-yellow-600 text-gray-900`
- Secundário: `bg-cyan-500 hover:bg-cyan-600 text-white`
- Perigo: `bg-red-500 hover:bg-red-600 text-white`
- Sucesso: `bg-green-500 hover:bg-green-600 text-white`

### Links
- Padrão: `text-[cor-primária] hover:underline transition`
- Discreto: `text-gray-400 hover:text-[cor-primária] transition`

### Alertas e Mensagens
```html
<p class="bg-[cor-contexto] p-2 rounded mb-4 text-center text-white">
  [Mensagem]
</p>
```

## Visualização Condicional de Conteúdo

Para layouts distintos entre mobile e desktop:

```html
<!-- Versão Mobile -->
<div class="block md:hidden">
  <!-- Layout para dispositivos móveis -->
</div>

<!-- Versão Desktop -->
<div class="hidden md:block">
  <!-- Layout para desktop -->
</div>
```

## Paleta de Cores

- Fundo principal: `bg-gray-900`
- Fundo de componentes: `bg-gray-800`
- Cor de destaque primária: `text-yellow-400` / `bg-yellow-500`
- Cor de destaque secundária: `text-cyan-400` / `bg-cyan-500`
- Texto principal: `text-white` / `text-gray-100`
- Texto secundário: `text-gray-400`
- Alertas: Sucesso `bg-green-500`, Erro `bg-red-500`

## Padronização de Grid e Espaçamento

- Use `gap-2 sm:gap-3 md:gap-4` para espaçamento entre elementos
- Paddings: `p-4 sm:p-6 md:p-8` (aumentando com o tamanho da tela)
- Margens: `mb-4 sm:mb-6 md:mb-8` (aumentando com o tamanho da tela)

## Tabelas Responsivas

Para dados tabulares, use abordagem adaptativa:
- Cards em visualização mobile
- Tabela tradicional em desktop

```html
<!-- Mobile: Cards -->
<div class="space-y-4 md:hidden">
  <!-- Cards para dados -->
</div>

<!-- Desktop: Tabela -->
<div class="overflow-x-auto bg-gray-800 rounded shadow hidden md:block">
  <table class="min-w-full text-sm">
    <!-- Estrutura da tabela -->
  </table>
</div>
```

## Lembre-se

- Sempre teste o layout em múltiplos tamanhos de tela
- Priorize a usabilidade em dispositivos móveis acima de tudo
- Mantenha consistência visual entre todas as páginas do sistema
- Evite larguras e tamanhos fixos em pixels quando possível
