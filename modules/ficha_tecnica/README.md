## 🆕 Novidades da versão 1.5.2

## 🆕 Divisão das bases WAB/BDF

- Todas as importações (XLSX de produtos e CSV de insumos) agora pedem que você escolha entre as bases **WAB** ou **BDF** antes de enviar o arquivo.
- O cadastro e a edição de fichas técnicas incluem a seleção da base de origem, garantindo que consultas, comparações e cálculos usem os dados corretos.
- As telas de consulta, comparação e visualização passam a respeitar a base armazenada na ficha técnica ao buscar informações no DW.

### ⚙️ Preparação do banco de dados

Antes de usar os novos recursos, execute uma única vez o script abaixo para criar as tabelas espelhadas no DW, copiar os dados atuais e adicionar o campo `base_origem` na tabela `ficha_tecnica`:

```bash
php scripts/create_dw_split_tables.php
```

> O script é idempotente: ele cria as tabelas `ProdutosBares_WAB/BDF` e `insumos_bastards_wab/bdf` caso não existam, replica os dados apenas quando a tabela está vazia e adiciona a coluna `base_origem` com valor padrão `WAB`.

A versão **v1.5.2** aplica o **UX Layout Guide v1.6** em todo o sistema com foco em responsividade e acessibilidade. Nenhuma funcionalidade foi alterada — apenas o layout foi melhorado para oferecer melhor experiência em diferentes dispositivos.

### 🎨 Ajustes visuais aplicados:

- 📱 **Responsividade completa**:
  - Tabelas adaptadas para desktop (`hidden md:block`)
  - Novos **cards empilháveis para mobile** (`md:hidden`)
  - Visualização mobile amigável em todos os módulos

- ⚙️ **Componentes UI padronizados**:
  - Cores seguindo o guia (`gray`, `cyan`, `red`, `green`, `purple`)
  - Botões com padding, hover e largura mínima (`min-w-[170px]`)
  - Labels com `text-cyan-300`, inputs com `focus:ring`, contrastes otimizados

- 🖨️ **Melhoria na tela de impressão (`visualizar_ficha.php`)**:
  - Ajustes no CSS para exibição A4
  - Remoção de botões em `@media print`
  - Estilo limpo com foco no conteúdo

- 🧠 **Arquivos atualizados**:
  - `consulta.php`
  - `consultar_alteracoes.php`
  - `cadastrar_ficha.php`
  - `editar_ficha_form.php`
  - `historico.php`
  - `visualizar_ficha.php`
  - `excluir_ficha.php`

> ⚠️ Nenhuma alteração no banco de dados foi necessária.

---

### ✅ Status

Esta versão está 100% compatível com:
- 🌐 Navegadores modernos (Chrome, Edge, Firefox, Safari)
- 📱 Dispositivos móveis e tablets
- 🖨️ Impressão A4

---



# 📋 Sistema de Ficha Técnica de Cozinha – Versão 1.5.1

Este é um sistema web completo para cadastro, consulta, edição e controle de fichas técnicas culinárias. A versão v1.5.1 consolida o deploy em ambiente real (HostGator), corrige o sistema de upload de imagens e aprimora a documentação e segurança de deploy.

---

## 🧾 Funcionalidades Gerais

- 📄 Cadastro de fichas com:
  - Nome do prato
  - Rendimento
  - Ingredientes (código, descrição, quantidade, unidade)
  - Modo de preparo
  - Upload de imagem
  - Responsável pela ficha

- ✍️ Edição avançada:
  - Atualização de qualquer campo da ficha
  - Inserção, edição e exclusão de ingredientes
  - Registro automático de todas as alterações

- 📜 Histórico:
  - Página individual de histórico por ficha
  - Página geral para consulta de todas as alterações (`consultar_alteracoes.php`)
  - Campos alterados, valores antigos/novos, responsável, data

- 🔍 Consulta:
  - Busca por nome do prato
  - Ações rápidas: Ver, Editar, Histórico, Excluir

- 🖨️ Visualização otimizada para impressão:
  - Estilo A4 responsivo com ocultação de botões
  - Exibição limpa com imagem e modo de preparo

- 🗑️ Exclusão com confirmação:
  - Modal visual com Tailwind
  - Log de exclusão salvo automaticamente no histórico
  - Ingredientes e alterações são removidos

- ⚠️ Alertas visuais:
  - Notificação de sucesso após exclusão
  - Botões destacados, alinhados e responsivos

---

## 🧠 Novidades da versão 1.5.1

- ✅ Deploy validado em ambiente real (HostGator)
- ✅ Correção de paths para `require_once('conexao.php')`
- ✅ Sistema de upload robusto com:
  - Validação de tipo de imagem (JPG, PNG, WEBP)
  - Criação automática da pasta `uploads/` se não existir
  - Verificação de erros de upload
- ✅ Atualização da base de dados: `basta920_bastards_cozinha`
- ✅ Conexão via usuário seguro: `cozinha_admin`
- ✅ Documentação e instruções de deploy atualizadas
- ✅ Permissões corrigidas (uploads `755`)
- ✅ Mensagens de erro mais claras em caso de falha no upload

---

## 📁 Estrutura do Projeto

```
/cozinha/
├── cadastrar_ficha.php
├── editar_ficha_form.php
├── excluir_ficha.php
├── salvar_edicao.php
├── salvar_ficha.php
├── consulta.php
├── historico.php
├── consultar_alteracoes.php
├── visualizar_ficha.php
├── conexao.php
├── uploads/                ← pasta criada automaticamente
├── init_bastards_cozinha.sql
└── README.md
```

---

## ⚙️ Requisitos e Instalação

### Requisitos:
- PHP 7.4+
- MySQL 5.7+ ou MariaDB
- HostGator ou qualquer servidor Apache com PHP

### Instalação:
1. Crie o banco de dados no cPanel: `basta920_bastards_cozinha`
2. Crie o usuário `cozinha_admin` e dê permissão total
3. Importe o `init_bastards_cozinha.sql` no phpMyAdmin
4. Edite `conexao.php` com os dados reais do banco
5. Acesse via navegador:
   ```
   https://bastardsbrewery.com.br/cozinha/consulta.php
   ```

---

## ✅ Observações

- A pasta `/uploads/` será criada automaticamente caso não exista
- O sistema lida com erros de upload e exibe mensagens informativas
- Histórico de alterações é mantido automaticamente
- Impressão limpa e adaptada para folha A4
- Todos os caminhos internos são relativos (`require_once('...')`)

---

## 📌 Versões

- **v1.5.2 – Abril/2025** — Atualização visual baseada no UX Layout Guide v1.6
- **v1.5.1 – Abril/2025** — Deploy real + sistema de upload corrigido
- **v1.5 – Abril/2025** — Tailwind, histórico completo, página de alterações
- **v1.4 – Abril/2025** — Versão local com funcionalidades CRUD e histórico inicial

---

Desenvolvido por Xico 🚀  
Documentado e mantido por Jake, seu dev ninja AI 🧑‍💻🥷
