<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// === modules/compras/insumos_bardafabrica.php ===
// Página de pedido de insumos para a filial BAR DA FABRICA (totalmente standalone).
require_once __DIR__ . '/../../sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

// fixa a filial e obtém o usuário conectado
$filial  = 'WE ARE BASTARDS';
$usuario = $_SESSION['usuario_nome'] ?? '';

// conexão com o banco
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Buscando os insumos desta filial com a lógica de consumo em 9 dias e sugestão de compra
$stmt = $conn->prepare("
    SELECT
        i.INSUMO,
        i.CATEGORIA,
        i.UNIDADE,
        i.CODIGO,
        COALESCE(e.ESTOQUE_ATUAL, 0) AS ESTOQUE_ATUAL,
        COALESCE(vw.total_insumo_usado_9dias, 0) AS CONSUMO_9DIAS,
        COALESCE(vw.sugestao_compra, 0) AS SUGESTAO_COMPRA
    FROM insumos i
    LEFT JOIN (
        SELECT CODIGO, SUM(Estoquetotal) AS ESTOQUE_ATUAL
        FROM EstoqueWAB
        GROUP BY CODIGO
    ) e ON i.CODIGO = e.CODIGO
    LEFT JOIN (
        SELECT cod_insumo, 
               SUM(total_insumo_usado_9dias) AS total_insumo_usado_9dias,
               SUM(sugestao_compra) AS sugestao_compra
        FROM vw_consumo_insumosWab_3m
        GROUP BY cod_insumo
    ) vw ON i.CODIGO = vw.cod_insumo
    WHERE i.FILIAL = ?
    GROUP BY i.INSUMO, i.CATEGORIA, i.UNIDADE, i.CODIGO, e.ESTOQUE_ATUAL, vw.total_insumo_usado_9dias, vw.sugestao_compra
    ORDER BY i.CATEGORIA, i.INSUMO
");
if (!$stmt) {
    die('<div style="color:red;font-weight:bold">Erro ao preparar statement: ' . $conn->error . '</div>');
}
if (!$stmt->bind_param('s', $filial)) {
    die('<div style="color:red;font-weight:bold">Erro ao fazer bind_param: ' . $stmt->error . '</div>');
}
if (!$stmt->execute()) {
    die('<div style="color:red;font-weight:bold">Erro ao executar statement: ' . $stmt->error . '</div>');
}
$result = $stmt->get_result();
if (!$result) {
    die('<div style="color:red;font-weight:bold">Erro ao obter resultado: ' . $stmt->error . '</div>');
}
$insumosFromDb = $result->fetch_all(MYSQLI_ASSOC);

// Recalcula a SUGESTAO_COMPRA e garante que não seja negativa.
$insumos = [];
foreach ($insumosFromDb as $row) {
    $consumo9dias = (float)($row['CONSUMO_9DIAS'] ?? 0);
    $estoqueAtual = (float)($row['ESTOQUE_ATUAL'] ?? 0);
    $row['SUGESTAO_COMPRA'] = max(0, ($consumo9dias - $estoqueAtual));
    $insumos[] = $row;
}

if (empty($insumos)) {
    // A mensagem de aviso será exibida se $insumos continuar vazio após o recálculo.
}
$stmt->close();
$conn->close();

// Obtém as categorias e unidades para os filtros (se necessário)
$categorias = array_values(array_unique(array_column($insumos, 'CATEGORIA')));
$unidades   = array_values(array_unique(array_column($insumos, 'UNIDADE')));

// BLOQUEIO POR DIA/HORA
date_default_timezone_set('America/Sao_Paulo');
$now = new DateTime();
$dia = (int)$now->format('w'); // 0=domingo, 1=segunda, ..., 6=sábado
$hora = (int)$now->format('H');
$min  = (int)$now->format('i');
$bloqueado = false;
// Permitido: sábado 00:00 até quarta 00:00
if (
    ($dia == 6) || // sábado qualquer hora
    ($dia == 0) || // domingo qualquer hora
    ($dia == 1) || // segunda qualquer hora
    ($dia == 2) || // terça qualquer hora
    ($dia == 3 && $hora == 0 && $min == 0) // quarta exatamente 00:00
) {
    $bloqueado = false;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>PEDIDO DE COMPRAS — <?= htmlspecialchars($filial, ENT_QUOTES) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="/assets/css/style.css" rel="stylesheet">
  <style>
    input[type=number]::-webkit-outer-spin-button,
    input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; }
    input[type=number] { -moz-appearance: textfield; }
    .qtd-input { width: 3rem; text-align: center; }
    .qty-btn { background: #4B5563; color: #FFF; width: 1.5rem; height: 1.5rem;
      line-height: 1.5rem; text-align: center; border-radius: .25rem;
      cursor: pointer; user-select: none; }
    .qty-btn:hover { background: #6B7280; }
    input[type="text"] { text-transform: uppercase; }
  </style>
</head>
<body class="bg-gray-900 text-gray-100 flex min-h-screen">
  <main class="flex-1 bg-gray-900 p-6 relative">
    <?php if ($bloqueado): ?>
      <div class="mx-auto mb-4 mt-2 px-4 py-2 bg-red-700 text-white text-center rounded font-semibold text-sm shadow w-fit max-w-full">
        PREENCHIMENTO SOMENTE DAS 00:00 DE SÁBADO ATÉ AS 09:00 DE SEGUNDA.
      </div>
    <?php endif; ?>

    <button onclick="location.href='select_filial.php'"
            class="absolute top-4 right-4 bg-gray-700 hover:bg-gray-600 text-white text-xs px-2 py-1 rounded">
      ← Outra filial
    </button>

    <header class="mb-6 sm:mb-8">
      <h1 class="text-2xl sm:text-3xl font-bold">
        Bem-vindo, <?= htmlspecialchars($usuario); ?>
      </h1>
      <p class="text-gray-400 text-sm">
        <?php
          $hoje = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
          $fmt = new IntlDateFormatter(
            'pt_BR',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            'America/Sao_Paulo',
            IntlDateFormatter::GREGORIAN
          );
          echo $fmt->format($hoje);
        ?>
      </p>
    </header>

    <h1 class="text-3xl font-bold text-yellow-400 text-center mb-6">
      PEDIDO DE COMPRA — <?= htmlspecialchars($filial, ENT_QUOTES) ?>
    </h1>

    <!-- Filtro de categoria e pesquisa (opcional) -->
    <div class="mb-4 flex items-center space-x-2">
      <span class="font-semibold text-xs">Filtrar Categoria:</span>
      <div class="relative">
        <button id="btn-filter"
                class="bg-gray-700 hover:bg-gray-600 text-white text-xs px-3 py-1 rounded">
          Selecionar
        </button>
        <div id="dropdown-menu"
             class="hidden absolute mt-2 w-64 bg-gray-700 rounded shadow-lg max-h-48 overflow-y-auto z-20">
          <?php foreach ($categorias as $cat): ?>
            <label class="flex items-center px-3 py-1 hover:bg-gray-600 cursor-pointer text-xs">
              <input type="checkbox" class="mr-1 accent-white cat-checkbox" value="<?=htmlspecialchars($cat,ENT_QUOTES)?>">
              <?=htmlspecialchars($cat,ENT_QUOTES)?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <input id="search-input" type="text" placeholder="Pesquisar insumo..."
             class="bg-gray-700 text-white text-xs p-2 rounded" style="width:12rem">
    </div>

    <!-- Formulário de pedido -->
    <form id="pedido-form" action="salvar_pedido_wearebastards.php" method="post">
      <input type="hidden" name="filial" value="<?=htmlspecialchars($filial,ENT_QUOTES)?>">
      <input type="hidden" name="usuario" value="<?=htmlspecialchars($usuario,ENT_QUOTES)?>">

      <!-- Tabela de insumos existentes -->
      <div class="overflow-x-auto bg-gray-800 rounded-lg shadow mb-8">
        <table class="min-w-full text-xs mx-auto">
          <thead class="bg-gray-700 text-yellow-400">
            <tr>
              <th class="p-2 text-left">Insumo</th>
              <th class="p-2 text-center" style="width:8rem">QTDE</th>
              <th class="p-2 text-center" style="width:7rem;">Estoque Atual</th>
              <th class="p-2 text-center" style="width:7rem;">Consumo 9 dias</th>
              <th class="p-2 text-center" style="width:7rem;">Sugestão Compra</th>
              <th class="p-2 text-left">Unidade</th>
              <th class="p-2 text-left">Categoria</th>
              <th class="p-2 text-left">Observação</th>
            </tr>
          </thead>
          <tbody id="insumo-body" class="divide-y divide-gray-700">
            <?php foreach ($insumos as $row):
              $ins = htmlspecialchars($row['INSUMO'], ENT_QUOTES);
              $cat = htmlspecialchars($row['CATEGORIA'], ENT_QUOTES);
              $uni = htmlspecialchars($row['UNIDADE'], ENT_QUOTES);
              $estoqueAtualNum = (float)($row['ESTOQUE_ATUAL'] ?? 0);
              $estoqueAtualDisplay = number_format($estoqueAtualNum, 2, ',', '.');
              
              $estoqueColorClass = '';
              if ($estoqueAtualNum > 0) {
                $estoqueColorClass = 'text-green-400';
              } elseif ($estoqueAtualNum < 0) {
                $estoqueColorClass = 'text-red-400';
              }
            ?>
            <tr class="hover:bg-gray-700" data-cat="<?=$cat?>">
              <td class="p-2"><?=$ins?></td>
              <td class="p-2 text-center">
                <div class="inline-flex items-center space-x-1">
                  <div class="qty-btn decrement">−</div>
                  <input type="hidden" name="insumo[]" value="<?=$ins?>">
                  <input type="hidden" name="categoria[]" value="<?=$cat?>">
                  <input type="hidden" name="unidade[]" value="<?=$uni?>">
                  <input type="number" name="quantidade[]" min="0" step="0.01"
                         value="<?= number_format((float)($row['SUGESTAO_COMPRA'] ?? 0), 2, '.', '') ?>"
                         class="qtd-input bg-gray-600 text-white text-xs p-1 rounded">
                  <div class="qty-btn increment">+</div>
                </div>
              </td>
              <td class="p-2 text-center font-semibold"><?=$estoqueAtualDisplay?></td>
              <td class="p-2 text-center font-semibold">
                <?= number_format((float)($row['CONSUMO_9DIAS'] ?? 0), 2, ',', '.') ?>
              </td>
              <td class="p-2 text-center font-semibold">
                <?= number_format($row['SUGESTAO_COMPRA'], 2, ',', '.') ?>
              </td>
              <td class="p-2"><?=$uni?></td> <!-- Coluna 5 (índice) -->
              <td class="p-2"><?=$cat?></td>
              <td class="p-2">
                <input type="text" name="observacao[]" maxlength="200"
                       class="w-full bg-gray-600 text-white text-xs p-2 rounded">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <?php if (empty($insumos)): ?>
            <tr><td colspan="8" class="p-4 text-center text-orange-400 font-semibold">Aviso: Nenhum insumo encontrado para esta filial ou os dados de consumo/estoque não estão disponíveis.</td></tr>
          <?php endif; ?>
        </table>
      </div>

      <!-- Seção para Adicionar Novos Itens -->
      <h2 class="text-xl font-semibold text-yellow-400 mb-2">Adicionar Novos Itens</h2>
      <button type="button" id="add-row"
              class="mb-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-2 py-1 rounded">
        + Adicionar linha
      </button>
      <div class="overflow-x-auto bg-gray-800 rounded-lg shadow mb-8">
        <table class="min-w-full text-xs mx-auto">
          <thead class="bg-gray-700 text-yellow-400">
            <tr>
              <th class="p-2 text-left">Insumo</th>
              <th class="p-2 text-center" style="width:8rem">QTDE</th>
              <th class="p-2 text-left">Unidade</th>
              <th class="p-2 text-left">Categoria</th>
              <th class="p-2 text-left">Observação</th>
              <th class="p-2 text-center">Excluir</th>
            </tr>
          </thead>
          <tbody id="new-items-body" class="divide-y divide-gray-700">
            <tr class="hover:bg-gray-700">
              <td class="p-2">
                <input type="text" name="new_insumo[]" required
                       class="w-full bg-gray-600 text-white text-xs p-2 rounded">
              </td>
              <td class="p-2 text-center">
                <div class="inline-flex items-center space-x-1">
                  <div class="qty-btn decrement">−</div>
                  <input type="number" name="new_quantidade[]" min="0" step="0.01" value="0" required
                         class="qtd-input bg-gray-600 text-white text-xs p-1 rounded">
                  <div class="qty-btn increment">+</div>
                </div>
              </td>
              <td class="p-2">
                <select name="new_unidade[]" required
                        class="w-full bg-gray-600 text-white text-xs p-2 rounded">
                  <option value="">Selecione</option>
                  <?php foreach ($unidades as $u): ?>
                    <option value="<?=htmlspecialchars($u,ENT_QUOTES)?>"><?=htmlspecialchars($u,ENT_QUOTES)?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="p-2">
                <select name="new_categoria[]" required
                        class="w-full bg-gray-600 text-white text-xs p-2 rounded">
                  <option value="">Selecione</option>
                  <?php foreach ($categorias as $cat): ?>
                    <option value="<?=htmlspecialchars($cat,ENT_QUOTES)?>"><?=htmlspecialchars($cat,ENT_QUOTES)?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="p-2">
                <input type="text" name="new_observacao[]" maxlength="200"
                       class="w-full bg-gray-600 text-white text-xs p-2 rounded">
              </td>
              <td class="p-2 text-center">
                <button type="button" class="delete-row text-red-500 hover:text-red-700 text-lg">🗑️</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <button type="button" id="submit-all"
              class="mt-6 w-full bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold py-3 rounded">
        Enviar Pedido
      </button>
    </form>

    <!-- Modal de pré-visualização -->
    <div id="preview-modal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg w-11/12 max-w-2xl p-6">
        <h2 class="text-2xl font-bold text-yellow-400 mb-4">Revise seu Pedido</h2>
        <div id="preview-content" class="overflow-y-auto max-h-64 mb-4">
          <table class="min-w-full text-xs">
            <thead class="bg-gray-700 text-yellow-400">
              <tr>
                <th class="p-2 text-left">Insumo</th>
                <th class="p-2 text-center">Qtde</th>
                <th class="p-2 text-left">Unidade</th>
                <th class="p-2 text-left">Categoria</th>
                <th class="p-2 text-left">Observação</th>
              </tr>
            </thead>
            <tbody id="preview-body" class="divide-y divide-gray-600"></tbody>
          </table>
        </div>
        <div class="flex justify-end space-x-2">
          <button id="cancel-preview" class="bg-gray-600 hover:bg-gray-500 text-white px-4 py-2 rounded">Cancelar</button>
          <button id="export-pdf"    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Exportar PDF</button>
          <button id="confirm-preview" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Confirmar pedido</button>
        </div>
      </div>
    </div>

    <div class="fixed bottom-6 right-6 flex flex-col" style="gap:3px">
      <button id="btn-scroll-top"    class="bg-yellow-500 hover:bg-yellow-600 text-gray-900 p-3 rounded-full shadow-lg text-xl" title="Ir para o topo">↑</button>
      <button id="btn-scroll-bottom" class="bg-yellow-500 hover:bg-yellow-600 text-gray-900 p-3 rounded-full shadow-lg text-xl" title="Ir para o final">↓</button>
    </div>
  </main>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
  <script>
    // BLOQUEIO DE CAMPOS (front-end)
    const bloqueado = <?php echo $bloqueado ? 'true' : 'false'; ?>;
    document.addEventListener('DOMContentLoaded', ()=>{
      if(bloqueado){
        // Desabilita todos os campos do formulário
        document.querySelectorAll('#pedido-form input, #pedido-form select, #pedido-form textarea, #pedido-form button').forEach(el=>{
          el.disabled = true;
        });
        // Reabilita os botões de scroll
        document.getElementById('btn-scroll-top').disabled = false;
        document.getElementById('btn-scroll-bottom').disabled = false;
      }
    });

    // filtro + pesquisa
    const rows = Array.from(document.querySelectorAll('#insumo-body tr'));
    const btnFilter = document.getElementById('btn-filter');
    const dropdownMenu = document.getElementById('dropdown-menu');
    let dropdownTimeout;
    function showDropdown() {
      clearTimeout(dropdownTimeout);
      dropdownMenu.classList.remove('hidden');
    }
    function hideDropdown() {
      dropdownTimeout = setTimeout(() => {
        dropdownMenu.classList.add('hidden');
      }, 150);
    }
    btnFilter.addEventListener('mouseenter', showDropdown);
    btnFilter.addEventListener('mouseleave', hideDropdown);
    dropdownMenu.addEventListener('mouseenter', showDropdown);
    dropdownMenu.addEventListener('mouseleave', hideDropdown);
    document.querySelectorAll('.cat-checkbox').forEach(chk => chk.onchange = filterRows);
    document.getElementById('search-input').oninput = filterRows;
    function filterRows() {
      const cats = Array.from(document.querySelectorAll('.cat-checkbox:checked')).map(c=>c.value);
      const term = document.getElementById('search-input').value.trim().toLowerCase();
      rows.forEach(r=>{
        const catOK = !cats.length || cats.includes(r.dataset.cat);
        const txtOK = !term || r.children[0].textContent.toLowerCase().includes(term);
        r.style.display = (catOK && txtOK) ? '' : 'none';
      });
    }

    // +/- buttons
    function attachQtyButtons(container=document) {
      container.querySelectorAll('.decrement').forEach(btn=>{
        btn.onclick = ()=>{
          if (bloqueado) return;
          const inp = btn.parentElement.querySelector('input[type=number]');
          let v = parseFloat(inp.value)||0; inp.value = Math.max(0,v-1).toFixed(2);
        };
      });
      container.querySelectorAll('.increment').forEach(btn=>{
        btn.onclick = ()=>{
          if (bloqueado) return;
          const inp = btn.parentElement.querySelector('input[type=number]');
          let v = parseFloat(inp.value)||0; inp.value = (v+1).toFixed(2);
        };
      });
    }
    attachQtyButtons();

    // adiciona nova linha
    const newBody = document.getElementById('new-items-body');
    const template = newBody.querySelector('tr').outerHTML;
    document.getElementById('add-row').onclick = ()=>{
      newBody.insertAdjacentHTML('beforeend', template);
      attachQtyButtons(newBody.lastElementChild);
    };

    // deleta linha nova
    newBody.addEventListener('click', e=>{
      if(e.target.classList.contains('delete-row')){
        e.target.closest('tr').remove();
      }
    });

    // forçar maiúscula
    document.querySelectorAll('input[type="text"]').forEach(i=>{
      i.addEventListener('input', ()=> i.value = i.value.toUpperCase());
    });

    // abrir modal de preview
    document.getElementById('submit-all').onclick = e=>{
      e.preventDefault();
      const previewBody = document.getElementById('preview-body');
      previewBody.innerHTML = '';
      const lines = [];
      // existentes
      document.querySelectorAll('#insumo-body tr').forEach(r=>{
        const q = parseFloat(r.querySelector('input[name="quantidade[]"]').value);
        if(q>0) lines.push({
          insumo:     r.children[0].textContent.trim(),
          quantidade: q.toFixed(2),
          unidade:    r.children[5].textContent.trim(), // Unidade (índice corrigido para 5)
          categoria:  r.children[6].textContent.trim(), // Categoria (índice corrigido para 6)
          obs:        r.querySelector('input[name="observacao[]"]').value.trim()
        });
      });
      // novos
      document.querySelectorAll('#new-items-body tr').forEach(r=>{
        const ins = r.querySelector('input[name="new_insumo[]"]').value.trim();
        const q   = parseFloat(r.querySelector('input[name="new_quantidade[]"]').value);
        const uni = r.querySelector('select[name="new_unidade[]"]').value;
        const cat = r.querySelector('select[name="new_categoria[]"]').value;
        if(ins&&q>0) lines.push({
          insumo: ins,
          quantidade: q.toFixed(2),
          unidade: uni,
          categoria: cat,
          obs: r.querySelector('input[name="new_observacao[]"]').value.trim()
        });
      });
      if(!lines.length){
        alert('Nenhum item para enviar.');
        return;
      }
      lines.forEach(item=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `
  <td class="p-2 text-left">${item.insumo}</td>
  <td class="p-2 text-center">${item.quantidade}</td>
  <td class="p-2 text-left">${item.unidade}</td>
  <td class="p-2 text-left">${item.categoria}</td>
  <td class="p-2 text-left">${item.obs || ''}</td>
`;
        previewBody.appendChild(tr);
      });
      document.getElementById('preview-modal').classList.remove('hidden');
      document.getElementById('preview-modal').classList.add('flex');
    };

    // fecha modal
    document.getElementById('cancel-preview').onclick = ()=>{
      document.getElementById('preview-modal').classList.add('hidden');
      document.getElementById('preview-modal').classList.remove('flex');
    };

    // exporta PDF
    document.getElementById('export-pdf').onclick = ()=>{
      const el = document.getElementById('preview-content');
      el.style.color = '#000';
      el.querySelectorAll('*').forEach(x=> x.style.color='#000');
      html2pdf().set({
        margin:0.5, filename:'pedido_insumos_wearebastards.pdf',
        html2canvas:{scale:2},
        jsPDF:{unit:'in',format:'letter',orientation:'portrait'}
      }).from(el).save().then(()=>{
        el.style.color='';
        el.querySelectorAll('*').forEach(x=> x.style.color='');
      });
    };

    // envia pedido via fetch
    document.getElementById('confirm-preview').onclick = async ()=>{
      const btn = document.getElementById('confirm-preview');
      btn.disabled = true;
      btn.innerText = 'Enviando...';
      const todosOsItens = [];
      document.querySelectorAll('#insumo-body tr').forEach(row => {
        const quantidadeInput = row.querySelector('input[name="quantidade[]"]');
        const quantidade = parseFloat(quantidadeInput.value);
        if (quantidade > 0) {
          todosOsItens.push({
            insumo: row.querySelector('input[name="insumo[]"]').value,
            categoria: row.querySelector('input[name="categoria[]"]').value,
            unidade: row.querySelector('input[name="unidade[]"]').value,
            quantidade: quantidade.toFixed(2),
            observacao: row.querySelector('input[name="observacao[]"]').value.trim()
          });
        }
      });
      document.querySelectorAll('#new-items-body tr').forEach(row => {
        const insumoInput = row.querySelector('input[name="new_insumo[]"]');
        const quantidadeInput = row.querySelector('input[name="new_quantidade[]"]');
        if (insumoInput && quantidadeInput) {
            const insumo = insumoInput.value.trim();
            const quantidade = parseFloat(quantidadeInput.value);
            if (insumo && quantidade > 0) {
                todosOsItens.push({
                    insumo: insumo,
                    categoria: row.querySelector('select[name="new_categoria[]"]').value,
                    unidade: row.querySelector('select[name="new_unidade[]"]').value,
                    quantidade: quantidade.toFixed(2),
                    observacao: row.querySelector('input[name="new_observacao[]"]').value.trim()
                });
            }
        }
      });
      if (todosOsItens.length === 0) {
        alert('Nenhum item com quantidade maior que zero para enviar.');
        btn.disabled = false;
        btn.innerText = 'Confirmar pedido';
        document.getElementById('preview-modal').classList.add('hidden');
        document.getElementById('preview-modal').classList.remove('flex');
        return;
      }
      const formData = new FormData();
      formData.append('itensJson', JSON.stringify(todosOsItens));
      try {
        const response = await fetch('salvar_pedido_wearebastards.php', {
          method: 'POST',
          body: formData
        });
        if (response.ok) {
          window.location.href = 'insumos_wearebastards.php?status=ok';
        } else {
          const errorText = await response.text();
          alert(`Erro ao enviar o pedido: ${response.status} ${response.statusText}\n${errorText}`);
          btn.disabled = false;
          btn.innerText = 'Confirmar pedido';
        }
      } catch (error) {
        alert('Erro de comunicação ao enviar o pedido. Verifique sua conexão.');
        console.error('Erro no fetch:', error);
        btn.disabled = false;
        btn.innerText = 'Confirmar pedido';
      }
    };

    // Scroll flutuante
    document.getElementById('btn-scroll-top').onclick = ()=> window.scrollTo({top:0,behavior:'smooth'});
    document.getElementById('btn-scroll-bottom').onclick = ()=> window.scrollTo({top:document.body.scrollHeight,behavior:'smooth'});
  </script>
</body>
</html>