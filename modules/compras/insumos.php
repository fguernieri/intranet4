<?php
// === modules/compras/insumos.php ===

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
session_start();
if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

$filial  = $_SESSION['filial']       ?? null;
$usuario = $_SESSION['usuario_nome'] ?? '';
if (!$filial) {
    header('Location: select_filial.php');
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}
$stmt = $conn->prepare("
    SELECT INSUMO, CATEGORIA, UNIDADE
      FROM insumos
     WHERE FILIAL = ?
     ORDER BY CATEGORIA, INSUMO
");
$stmt->bind_param('s', $filial);
$stmt->execute();
$insumos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

$categorias = array_values(array_unique(array_column($insumos, 'CATEGORIA')));
$unidades   = array_values(array_unique(array_column($insumos, 'UNIDADE')));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Pedido de Insumos — <?= htmlspecialchars($filial, ENT_QUOTES) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="/assets/css/style.css" rel="stylesheet">
  <style>
    input[type=number]::-webkit-outer-spin-button,
    input[type=number]::-webkit-inner-spin-button { -webkit-appearance:none }
    input[type=number] { -moz-appearance:textfield }
    .qtd-input { width:3rem; text-align:center }
    .qty-btn { background:#4B5563;color:#FFF;width:1.5rem;height:1.5rem;
      line-height:1.5rem;text-align:center;border-radius:.25rem;
      cursor:pointer;user-select:none }
    .qty-btn:hover { background:#6B7280 }

    /* exibe todo texto em maiúsculas */
    input[type="text"] {
      text-transform: uppercase;
    }
  </style>
</head>
<body class="bg-gray-900 text-gray-100 flex min-h-screen">

  <!-- SIDEBAR -->
  <aside class="bg-gray-800 w-60 p-6 flex-shrink-0">
    <?php include __DIR__ . '/../../sidebar.php'; ?>
  </aside>

  <main class="flex-1 bg-gray-900 p-6 relative">
    <button onclick="location.href='select_filial.php'"
            class="absolute top-4 right-4 bg-gray-700 hover:bg-gray-600 text-white text-xs px-2 py-1 rounded">
      ← Outra filial
    </button>

    <h1 class="text-3xl font-bold text-yellow-400 text-center mb-6">
      Pedido de Insumos — <?= htmlspecialchars($filial, ENT_QUOTES) ?>
    </h1>

    <!-- filtros -->
    <div class="mb-4 flex items-center space-x-2">
      <span class="font-semibold text-xs">Filtrar Categoria:</span>
      <div class="relative">
        <button id="btn-filter" class="bg-gray-700 hover:bg-gray-600 text-white text-xs px-3 py-1 rounded">
          Selecionar
        </button>
        <div id="dropdown-menu"
             class="hidden absolute mt-2 w-64 bg-gray-700 rounded shadow-lg max-h-48 overflow-y-auto z-20">
          <?php foreach ($categorias as $cat): ?>
            <label class="flex items-center px-3 py-1 hover:bg-gray-600 cursor-pointer text-xs">
              <input type="checkbox"
                     class="mr-1 accent-white cat-checkbox"
                     value="<?= htmlspecialchars($cat, ENT_QUOTES) ?>">
              <?= htmlspecialchars($cat, ENT_QUOTES) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <input id="search-input"
             type="text"
             placeholder="Pesquisar insumo..."
             class="bg-gray-700 text-white text-xs p-2 rounded"
             style="width:12rem">
    </div>

    <form id="pedido-form">
      <!-- ADICIONADOS: mantém filial e usuário para o POST -->
      <input type="hidden" name="filial"  value="<?= htmlspecialchars($filial, ENT_QUOTES) ?>">
      <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario, ENT_QUOTES) ?>">

      <!-- EXISTENTES -->
      <div class="overflow-x-auto bg-gray-800 rounded-lg shadow mb-8">
        <table class="min-w-full text-xs mx-auto">
          <thead class="bg-gray-700 text-yellow-400">
            <tr>
              <th class="p-2 text-left">Insumo</th>
              <th class="p-2 text-left">Categoria</th>
              <th class="p-2 text-left">Unidade</th>
              <th class="p-2 text-center" style="width:8rem">QTDE</th>
              <th class="p-2 text-left">Observação</th>
            </tr>
          </thead>
          <tbody id="insumo-body" class="divide-y divide-gray-700">
            <?php foreach ($insumos as $row):
              $ins = htmlspecialchars($row['INSUMO'], ENT_QUOTES);
              $cat = htmlspecialchars($row['CATEGORIA'], ENT_QUOTES);
              $uni = htmlspecialchars($row['UNIDADE'], ENT_QUOTES);
            ?>
            <tr class="hover:bg-gray-700" data-cat="<?= $cat ?>">
              <td class="p-2"><?= $ins ?></td>
              <td class="p-2"><?= $cat ?></td>
              <td class="p-2"><?= $uni ?></td>
              <td class="p-2 text-center">
                <div class="inline-flex items-center space-x-1">
                  <div class="qty-btn decrement">−</div>
                  <input type="hidden" name="insumo[]"    value="<?= $ins ?>">
                  <input type="hidden" name="categoria[]" value="<?= $cat ?>">
                  <input type="hidden" name="unidade[]"   value="<?= $uni ?>">
                  <input type="number"
                         name="quantidade[]"
                         min="0" step="0.01" value="0"
                         class="qtd-input bg-gray-600 text-white text-xs p-1 rounded">
                  <div class="qty-btn increment">+</div>
                </div>
              </td>
              <td class="p-2">
                <input type="text"
                       name="observacao[]"
                       maxlength="200"
                       class="w-full bg-gray-600 text-white text-xs p-2 rounded">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- NOVOS -->
      <h2 class="text-xl font-semibold text-yellow-400 mb-2">Adicionar Novos Itens</h2>
      <button type="button"
              id="add-row"
              class="mb-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-2 py-1 rounded">
        + Adicionar linha
      </button>
      <div class="overflow-x-auto bg-gray-800 rounded-lg shadow mb-8">
        <table class="min-w-full text-xs mx-auto">
          <thead class="bg-gray-700 text-yellow-400">
            <tr>
              <th class="p-2 text-left">Insumo</th>
              <th class="p-2 text-left">Categoria</th>
              <th class="p-2 text-left">Unidade</th>
              <th class="p-2 text-center" style="width:8rem">QTDE</th>
              <th class="p-2 text-left">Observação</th>
              <th class="p-2 text-center">Excluir</th>
            </tr>
          </thead>
          <tbody id="new-items-body" class="divide-y divide-gray-700">
            <tr class="hover:bg-gray-700">
              <td class="p-2">
                <input type="text"
                       name="new_insumo[]"
                       class="w-full bg-gray-600 text-white text-xs p-2 rounded"
                       required>
              </td>
              <td class="p-2">
                <select name="new_categoria[]"
                        class="w-full bg-gray-600 text-white text-xs p-2 rounded"
                        required>
                  <option value="">Selecione</option>
                  <?php foreach ($categorias as $cat): ?>
                    <option value="<?= htmlspecialchars($cat, ENT_QUOTES) ?>"><?= htmlspecialchars($cat, ENT_QUOTES) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="p-2">
                <select name="new_unidade[]"
                        class="w-full bg-gray-600 text-white text-xs p-2 rounded"
                        required>
                  <option value="">Selecione</option>
                  <?php foreach ($unidades as $u): ?>
                    <option value="<?= htmlspecialchars($u, ENT_QUOTES) ?>"><?= htmlspecialchars($u, ENT_QUOTES) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="p-2 text-center">
                <div class="inline-flex items-center space-x-1">
                  <div class="qty-btn decrement">−</div>
                  <input type="number"
                         name="new_quantidade[]"
                         min="0" step="0.01" value="0"
                         class="qtd-input bg-gray-600 text-white text-xs p-1 rounded"
                         required>
                  <div class="qty-btn increment">+</div>
                </div>
              </td>
              <td class="p-2">
                <input type="text"
                       name="new_observacao[]"
                       maxlength="200"
                       class="w-full bg-gray-600 text-white text-xs p-2 rounded">
              </td>
              <td class="p-2 text-center">
                <button type="button"
                        class="delete-row text-red-500 hover:text-red-700 text-lg">
                  🗑️
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <button type="button"
              id="submit-all"
              class="mt-6 w-full bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold py-3 rounded">
        Enviar Pedido
      </button>
    </form>

    <!-- Modal de pré-visualização -->
    <div id="preview-modal"
         class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg w-11/12 max-w-2xl p-6">
        <h2 class="text-2xl font-bold text-yellow-400 mb-4">Revise seu Pedido</h2>

        <!-- área a ser exportada em PDF -->
        <div id="preview-content" class="overflow-y-auto max-h-64 mb-4">
          <table class="min-w-full text-xs">
            <thead class="bg-gray-700 text-yellow-400">
              <tr>
                <th class="p-2 text-left">Insumo</th>
                <th class="p-2 text-left">Unidade</th>
                <th class="p-2 text-center">Qtde</th>
                <th class="p-2 text-left">Observação</th>
              </tr>
            </thead>
            <tbody id="preview-body" class="divide-y divide-gray-600"></tbody>
          </table>
        </div>

        <div class="flex justify-end space-x-2">
          <button id="cancel-preview"
                  class="bg-gray-600 hover:bg-gray-500 text-white px-4 py-2 rounded">
            Cancelar
          </button>
          <button id="export-pdf"
                  class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
            Exportar PDF
          </button>
          <button id="confirm-preview"
                  class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
            Confirmar pedido
          </button>
        </div>
      </div>
    </div>

    <!-- Scroll flutuante -->
    <div class="fixed bottom-6 right-6 flex flex-col" style="gap:3px">
      <button id="btn-scroll-top"
              class="bg-yellow-500 hover:bg-yellow-600 text-gray-900 p-3 rounded-full shadow-lg text-xl"
              title="Ir para o topo">↑</button>
      <button id="btn-scroll-bottom"
              class="bg-yellow-500 hover:bg-yellow-600 text-gray-900 p-3 rounded-full shadow-lg text-xl"
              title="Ir para o final">↓</button>
    </div>
  </main>

  <!-- html2pdf.js para exportar em PDF -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
  <script>
    // filtro + pesquisa
    const rows = Array.from(document.querySelectorAll('#insumo-body tr'));
    document.getElementById('btn-filter').onclick = e => {
      e.preventDefault();
      document.getElementById('dropdown-menu').classList.toggle('hidden');
    };
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
    function attachQtyButtons(container = document) {
      container.querySelectorAll('.decrement').forEach(btn=>{
        btn.onclick = ()=>{
          const inp = btn.parentElement.querySelector('input[type=number]');
          let v = parseFloat(inp.value) || 0;
          inp.value = Math.max(0, v - 1).toFixed(2);
        };
      });
      container.querySelectorAll('.increment').forEach(btn=>{
        btn.onclick = ()=>{
          const inp = btn.parentElement.querySelector('input[type=number]');
          let v = parseFloat(inp.value) || 0;
          inp.value = (v + 1).toFixed(2);
        };
      });
    }
    attachQtyButtons();

    // template para novos itens
    const newItemsBody = document.getElementById('new-items-body');
    const templateHTML = newItemsBody.querySelector('tr').outerHTML;

    // add row novos
    document.getElementById('add-row').onclick = ()=>{
      newItemsBody.insertAdjacentHTML('beforeend', templateHTML);
      attachQtyButtons(newItemsBody.lastElementChild);
    };

    // delete novo item
    newItemsBody.addEventListener('click', e=>{
      if (e.target.classList.contains('delete-row')) {
        e.target.closest('tr').remove();
      }
    });

    // força maiúsculas no valor real do input
    document.querySelectorAll('input[type="text"]').forEach(input => {
      input.addEventListener('input', () => {
        input.value = input.value.toUpperCase();
      });
    });

    // preview popup
    document.getElementById('submit-all').onclick = e=>{
      e.preventDefault();
      const previewBody = document.getElementById('preview-body');
      previewBody.innerHTML = '';
      const lines = [];

      // existentes
      document.querySelectorAll('#insumo-body tr').forEach(r=>{
        const q = parseFloat(r.querySelector('input[name="quantidade[]"]').value);
        if (q > 0) lines.push({
          insumo: r.children[0].textContent.trim(),
          unidade: r.children[2].textContent.trim(),
          quantidade: q.toFixed(2),
          obs: r.querySelector('input[name="observacao[]"]').value.trim()
        });
      });
      // novos
      document.querySelectorAll('#new-items-body tr').forEach(r=>{
        const ins = r.querySelector('input[name="new_insumo[]"]').value.trim();
        const q   = parseFloat(r.querySelector('input[name="new_quantidade[]"]').value);
        const uni = r.querySelector('select[name="new_unidade[]"]').value;
        if (ins && q > 0) lines.push({
          insumo: ins, unidade: uni,
          quantidade: q.toFixed(2),
          obs: r.querySelector('input[name="new_observacao[]"]').value.trim()
        });
      });

      if (!lines.length) {
        alert('Nenhum item para enviar.');
        return;
      }
      lines.forEach(item=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="p-2 text-left">${item.insumo}</td>
          <td class="p-2 text-left">${item.unidade}</td>
          <td class="p-2 text-center">${item.quantidade}</td>
          <td class="p-2 text-left">${item.obs}</td>
        `;
        document.getElementById('preview-body').appendChild(tr);
      });
      document.getElementById('preview-modal').classList.remove('hidden');
      document.getElementById('preview-modal').classList.add('flex');
    };

    document.getElementById('cancel-preview').onclick = ()=>{
      document.getElementById('preview-modal').classList.add('hidden');
      document.getElementById('preview-modal').classList.remove('flex');
    };

    // exportar PDF com fonte preta
    document.getElementById('export-pdf').onclick = ()=>{
      const element = document.getElementById('preview-content');
      element.style.color = '#000';
      element.querySelectorAll('*').forEach(el => el.style.color = '#000');
      html2pdf().set({
        margin: 0.5,
        filename: 'pedido_insumos.pdf',
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
      }).from(element).save().then(() => {
        element.style.color = '';
        element.querySelectorAll('*').forEach(el => el.style.color = '');
      });
    };

    // ao confirmar, dispara os dois POSTs
    document.getElementById('confirm-preview').onclick = async ()=>{
      const form = document.getElementById('pedido-form');
      const fd1 = new FormData();
      ['filial','usuario'].forEach(n=> fd1.append(n, form.querySelector(`[name="${n}"]`).value));
      ['insumo','categoria','unidade','quantidade','observacao'].forEach(f=>{
        form.querySelectorAll(`input[name="${f}[]"]`).forEach(i=> fd1.append(f+'[]', i.value));
      });
      await fetch('salvar_pedido.php',{method:'POST',body:fd1});
      const fd2 = new FormData();
      ['filial','usuario'].forEach(n=> fd2.append(n, form.querySelector(`[name="${n}"]`).value));
      ['new_insumo','new_categoria','new_unidade','new_quantidade','new_observacao'].forEach(f=>{
        form.querySelectorAll(`[name="${f}[]"]`).forEach(i=> fd2.append(f+'[]', i.value));
      });
      await fetch('salvar_novos_insumos.php',{method:'POST',body:fd2});
      window.location.href = 'insumos.php?status=ok';
    };

    // scroll flutuante
    document.getElementById('btn-scroll-top').onclick = ()=> window.scrollTo({ top:0, behavior:'smooth' });
    document.getElementById('btn-scroll-bottom').onclick = ()=> window.scrollTo({ top:document.body.scrollHeight, behavior:'smooth' });
  </script>
</body>
</html>
