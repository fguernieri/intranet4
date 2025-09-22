<?php
require_once __DIR__ . '/../../sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id'])) {
  header('Location: /login.php');
  exit;
}

// load config (prefer user-created config.php)
if (file_exists(__DIR__ . '/config.php')) {
  require_once __DIR__ . '/config.php';
} else {
  require_once __DIR__ . '/config.example.php';
}

$usuario = $_SESSION['usuario_nome'] ?? '';
$filial = isset($_GET['filial']) ? urldecode($_GET['filial']) : '';

$insumos = [];
$medias_consumo = [];
$estoques = [];

if (defined('SUPABASE_URL') && defined('SUPABASE_KEY') && $filial !== '') {
  $base = rtrim(SUPABASE_URL, '/');
  
  // Carrega insumos
  $sel = 'codigo,insumo,categoria,unidade,filial';
  // order by category first, then insumo
  $url = "{$base}/rest/v1/insumos?select={$sel}&filial=eq." . urlencode($filial) . "&order=categoria,insumo";
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . SUPABASE_KEY,
    'Authorization: Bearer ' . SUPABASE_KEY,
    'Content-Type: application/json'
  ]);
  $resp = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if (!$err && $code >= 200 && $code < 300) {
    $rows = json_decode($resp, true) ?: [];
    foreach ($rows as $r) {
      if (isset($r['filial']) && $r['filial'] === $filial) {
        $insumos[] = $r;
      }
    }
  }
  
  // Define as tabelas baseadas na filial
  $view_media = 'vw_media_consumo_simples';  // TAP por padrão
  $tabela_estoque = 'destoquetap';            // TAP por padrão
  
  if ($filial === 'WE ARE BASTARDS') {
    $view_media = 'vw_media_consumo_wab';
    $tabela_estoque = 'destoquewab';
  }
  
  // Carrega médias de consumo da view (dinâmica baseada na filial)
  $sel_media = 'cod_ref,media_consumo,filial,produto';
  $url_media = "{$base}/rest/v1/{$view_media}?select={$sel_media}&filial=eq." . urlencode($filial);
  $ch_media = curl_init($url_media);
  curl_setopt($ch_media, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch_media, CURLOPT_HTTPHEADER, [
    'apikey: ' . SUPABASE_KEY,
    'Authorization: Bearer ' . SUPABASE_KEY,
    'Content-Type: application/json'
  ]);
  $resp_media = curl_exec($ch_media);
  $err_media = curl_error($ch_media);
  $code_media = curl_getinfo($ch_media, CURLINFO_HTTP_CODE);
  curl_close($ch_media);
  
  if (!$err_media && $code_media >= 200 && $code_media < 300) {
    $rows_media = json_decode($resp_media, true) ?: [];
    foreach ($rows_media as $rm) {
      if (isset($rm['cod_ref']) && isset($rm['media_consumo'])) {
        $medias_consumo[$rm['cod_ref']] = number_format($rm['media_consumo'], 2, ',', '.');
      }
    }
  }
  
  // Se não encontrou médias com filtro de filial, tenta sem filtro
  if (empty($medias_consumo)) {
    $url_media_all = "{$base}/rest/v1/{$view_media}?select={$sel_media}";
    $ch_media_all = curl_init($url_media_all);
    curl_setopt($ch_media_all, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_media_all, CURLOPT_HTTPHEADER, [
      'apikey: ' . SUPABASE_KEY,
      'Authorization: Bearer ' . SUPABASE_KEY,
      'Content-Type: application/json'
    ]);
    $resp_media_all = curl_exec($ch_media_all);
    $err_media_all = curl_error($ch_media_all);
    $code_media_all = curl_getinfo($ch_media_all, CURLINFO_HTTP_CODE);
    curl_close($ch_media_all);
    
    if (!$err_media_all && $code_media_all >= 200 && $code_media_all < 300) {
      $rows_media_all = json_decode($resp_media_all, true) ?: [];
      foreach ($rows_media_all as $rm) {
        if (isset($rm['cod_ref']) && isset($rm['media_consumo'])) {
          // Filtra apenas os códigos que existem nos insumos desta filial
          $codigo_existe = false;
          foreach ($insumos as $insumo) {
            if ($insumo['codigo'] == $rm['cod_ref']) {
              $codigo_existe = true;
              break;
            }
          }
          if ($codigo_existe) {
            $medias_consumo[$rm['cod_ref']] = number_format($rm['media_consumo'], 2, ',', '.');
          }
        }
      }
    }
  }
  
  // Carrega dados de estoque da tabela (dinâmica baseada na filial)
  $sel_estoque = 'cod_ref,estoque_total,nome';
  $url_estoque = "{$base}/rest/v1/{$tabela_estoque}?select={$sel_estoque}";
  $ch_estoque = curl_init($url_estoque);
  curl_setopt($ch_estoque, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch_estoque, CURLOPT_HTTPHEADER, [
    'apikey: ' . SUPABASE_KEY,
    'Authorization: Bearer ' . SUPABASE_KEY,
    'Content-Type: application/json'
  ]);
  $resp_estoque = curl_exec($ch_estoque);
  $err_estoque = curl_error($ch_estoque);
  $code_estoque = curl_getinfo($ch_estoque, CURLINFO_HTTP_CODE);
  curl_close($ch_estoque);
  
  if (!$err_estoque && $code_estoque >= 200 && $code_estoque < 300) {
    $rows_estoque = json_decode($resp_estoque, true) ?: [];
    foreach ($rows_estoque as $re) {
      if (isset($re['cod_ref']) && isset($re['estoque_total'])) {
        $estoques[$re['cod_ref']] = floatval($re['estoque_total']);
      }
    }
  }
  
  // Debug temporário - remova após confirmar funcionamento
  if ($err_media) {
    error_log("Erro na consulta de médias: " . $err_media);
  }
  if ($code_media < 200 || $code_media >= 300) {
    error_log("HTTP Code médias: " . $code_media . " - Response: " . $resp_media);
  }
  if ($err_estoque) {
    error_log("Erro na consulta de estoques: " . $err_estoque);
  }
  if ($code_estoque < 200 || $code_estoque >= 300) {
    error_log("HTTP Code estoques: " . $code_estoque . " - Response: " . $resp_estoque);
  }
  error_log("Filial: {$filial} - View: {$view_media} - Tabela: {$tabela_estoque}");
  error_log("Total médias carregadas: " . count($medias_consumo) . " para filial: " . $filial);
  error_log("Total estoques carregados: " . count($estoques));
}

?>
<!doctype html>
    <html lang="pt-BR">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <title>Pedido - <?= htmlspecialchars($filial, ENT_QUOTES) ?></title>
      <link href="/assets/css/style.css" rel="stylesheet">
      <style>
        /* visually uppercase text inputs and selects; JS will ensure submitted values are uppercased */
        input[type="text"], textarea, select { text-transform: uppercase; }
      </style>
      <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-900 text-gray-100 flex min-h-screen">
      <!-- sidebar.php should render the left navigation -->
      <main class="flex-1 p-6">
        <div class="max-w-7xl mx-auto w-full">
          <header class="mb-6">
            <a href="index.php" class="text-sm text-gray-400">&larr; Voltar</a>
            <h1 class="text-2xl font-bold">Pedido — <?= htmlspecialchars($filial) ?></h1>
            <p class="text-gray-400 text-sm">Usuário: <?= htmlspecialchars($usuario) ?></p>
          </header>

          <!-- Setor selection modal (shown before user can interact with form) -->
          <div id="setor-modal" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center">
            <div class="bg-gray-900 w-[95%] max-w-lg p-6 rounded shadow-lg text-center">
              <h2 class="text-lg font-semibold mb-3">Selecione o setor deste pedido</h2>
              <p class="text-sm text-gray-400 mb-4">Escolha um dos setores abaixo antes de preencher o pedido.</p>
              <div class="grid grid-cols-2 gap-3">
                <button type="button" class="setor-btn bg-yellow-500 text-gray-900 px-3 py-2 rounded font-medium" data-setor="COZINHA">COZINHA</button>
                <button type="button" class="setor-btn bg-yellow-500 text-gray-900 px-3 py-2 rounded font-medium" data-setor="BAR">BAR</button>
                <button type="button" class="setor-btn bg-yellow-500 text-gray-900 px-3 py-2 rounded font-medium" data-setor="GERENCIA">GERENCIA</button>
                <button type="button" class="setor-btn bg-yellow-500 text-gray-900 px-3 py-2 rounded font-medium" data-setor="EVENTOS">EVENTOS</button>
              </div>
              <div class="mt-4 text-sm text-gray-400">Você pode alterar o setor antes de enviar se necessário.</div>
            </div>
          </div>
          <!-- end setor modal -->

          <?php if (isset($_GET['status']) && $_GET['status'] === 'partial_error'): ?>
            <div class="mb-4 px-4 py-3 bg-red-700 text-white rounded">
              Pedido parcialmente processado. Alguns batches falharam ao enviar.
              <a class="underline font-semibold" href="failed/" target="_blank">Ver batches falhos</a>
              — depois de resolver, execute <code>reprocess_failed.php</code> para reenviar.
            </div>
          <?php endif; ?>

          <?php if (empty($insumos)): ?>
            <div class="bg-yellow-700 text-white p-3 rounded mb-4">Nenhum insumo carregado. Verifique a conexão com o Supabase e as credenciais em modules/bar_orders/config.php</div>
          <?php endif; ?>

          <?php if (empty($medias_consumo) && !empty($insumos)): ?>
            <div class="bg-blue-700 text-white p-3 rounded mb-4">
              <strong>Aviso:</strong> Médias de consumo não carregadas (<?= count($medias_consumo) ?> encontradas). 
              <br><small>Filial: <?= htmlspecialchars($filial) ?></small>
              <br><small>Verifique se a view <code><?= htmlspecialchars($view_media ?? 'N/A') ?></code> existe no Supabase e se há dados de movimentação para esta filial.</small>
              <br><small>URL consultada: <?= htmlspecialchars($url_media ?? 'N/A') ?></small>
            </div>
          <?php elseif (!empty($medias_consumo)): ?>
            <div class="bg-green-700 text-white p-3 rounded mb-4">
              <strong>Sucesso:</strong> <?= count($medias_consumo) ?> médias de consumo e <?= count($estoques) ?> estoques carregados para a filial <?= htmlspecialchars($filial) ?>.
              <br><small>Tabelas: <code><?= htmlspecialchars($view_media) ?></code> e <code><?= htmlspecialchars($tabela_estoque) ?></code></small>
              <br><small>Sugestões de compra calculadas automaticamente: <strong>Média - Estoque</strong></small>
            </div>
          <?php endif; ?>

          <form id="bar-order-form" method="post" action="save_order.php" autocomplete="off">
            <input type="hidden" name="filial" value="<?= htmlspecialchars($filial, ENT_QUOTES) ?>">
            <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario, ENT_QUOTES) ?>">
            <!-- setor chosen by user (populated by modal) -->
            <input type="hidden" name="setor" id="setor-input" value="">
            <!-- Packed items JSON (populated by JS before submit) to avoid PHP max_input_vars issues on very large orders -->
            <input type="hidden" name="items_json" id="items-json" value="">

            <?php
              // build a list of categories from the loaded insumos
              $categories = [];
              foreach ($insumos as $r) {
                $c = trim($r['categoria'] ?? '');
                if ($c !== '') $categories[] = $c;
              }
              $categories = array_values(array_unique($categories));
              // sort categories alphabetically (case-insensitive)
              sort($categories, SORT_STRING | SORT_FLAG_CASE);
              // build a list of units from the loaded insumos for new-item unit select
              $units = [];
              foreach ($insumos as $r) {
                $u = trim($r['unidade'] ?? '');
                if ($u !== '') $units[] = $u;
              }
              $units = array_values(array_unique($units));
              // sort units alphabetically (case-insensitive)
              sort($units, SORT_STRING | SORT_FLAG_CASE);
            ?>

            <div class="mb-4">
              <label class="block text-sm mb-1">Pesquisar insumo</label>
              <input id="search-in" type="text" class="w-full p-2 bg-gray-800 rounded text-sm" placeholder="Digite o nome do insumo">
            </div>

            <?php if (!empty($categories)): ?>
            <div class="mb-4">
              <label class="block text-sm mb-2">Filtrar por categoria</label>
              <div id="category-filter" class="flex flex-wrap gap-2">
                <?php foreach ($categories as $cat):
                  $safe = htmlspecialchars($cat, ENT_QUOTES);
                ?>
                <button type="button" class="category-chip px-3 py-1 rounded-full text-sm bg-gray-800 text-gray-200 hover:bg-yellow-500 hover:text-gray-900 transition" data-category="<?= $safe ?>" aria-pressed="true"><?= $safe ?></button>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>

  <div class="mb-4 overflow-y-auto max-h-[63vh] bg-gray-800 p-2 rounded">
              <table class="min-w-full text-sm">
                <thead class="text-left text-yellow-400">
      <tr><th class="p-1">Código</th><th class="p-1">Insumo</th><th class="p-1">Categoria</th><th class="p-1">Unidade</th><th class="p-1" title="Média de consumo baseada nos últimos 90 dias: (soma saídas / 90) * 9">Média ⓘ</th><th class="p-1" title="Estoque atual do produto">Estoque ⓘ</th><th class="p-1" title="Sugestão de compra: Média - Estoque">Sugestão ⓘ</th><th class="p-1">Qtde</th><th class="p-1">Obs</th></tr>
                </thead>
                <tbody id="insumo-list">

                  <?php foreach ($insumos as $it):
                    $cod = htmlspecialchars($it['codigo'] ?? '', ENT_QUOTES);
                    $ins = htmlspecialchars($it['insumo'] ?? '', ENT_QUOTES);
                    $uni = htmlspecialchars($it['unidade'] ?? '', ENT_QUOTES);
                    
                    // Média
                    $media_val = isset($medias_consumo[$cod]) ? $medias_consumo[$cod] : '';
                    $media_display = $media_val !== '' ? $media_val : '<span class="text-gray-500">N/A</span>';
                    $media_float = $media_val !== '' ? floatval(str_replace(['.', ','], ['', '.'], $media_val)) : 0;
                    
                    // Estoque
                    $estoque_val = isset($estoques[$cod]) ? $estoques[$cod] : 0;
                    $estoque_display = number_format($estoque_val, 2, ',', '.');
                    
                    // Sugestão de Compra = Média - Estoque (mínimo 0)
                    $sugestao_val = max(0, $media_float - $estoque_val);
                    $sugestao_display = number_format($sugestao_val, 2, ',', '.');
                    
                    // Para preencher o campo quantidade, usa a sugestão
                    $qtde_input = $sugestao_val > 0 ? number_format($sugestao_val, 2, '.', '') : '';
                  ?>
                  <tr class="insumo-row bg-gray-900 hover:bg-gray-700" data-insumo="<?= $ins ?>" data-categoria="<?= htmlspecialchars($it['categoria'] ?? '', ENT_QUOTES) ?>">
                    <td class="p-1"><?= $cod ?></td>
                    <td class="p-1"><?= $ins ?></td>
                    <td class="p-1"><?= htmlspecialchars($it['categoria'] ?? '') ?></td>
                    <td class="p-1"><?= $uni ?></td>
                    <td class="p-1 text-green-400 font-mono text-xs"><?= $media_display ?></td>
                    <td class="p-1 text-blue-400 font-mono text-xs"><?= $estoque_display ?></td>
                    <td class="p-1 text-orange-400 font-mono text-xs font-bold"><?= $sugestao_display ?></td>
                    <td class="p-1">
                      <input type="number" name="quantidade[<?= $cod ?>]" step="0.01" min="0"
                        value="<?= $qtde_input ?>"
                        autocomplete="off" class="w-20 p-1 bg-gray-800 rounded text-sm">
                    </td>
                    <td class="p-1"><input type="text" name="observacao[<?= $cod ?>]" maxlength="200" class="w-full p-1 bg-gray-800 rounded text-sm"></td>

                    <input type="hidden" name="produto_codigo[<?= $cod ?>]" value="<?= $cod ?>">
                    <input type="hidden" name="produto_nome[<?= $cod ?>]" value="<?= $ins ?>">
                    <input type="hidden" name="produto_unidade[<?= $cod ?>]" value="<?= $uni ?>">
                    <input type="hidden" name="produto_categoria[<?= $cod ?>]" value="<?= htmlspecialchars($it['categoria'] ?? '') ?>">

                  </tr>

                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="flex space-x-2">
              <button id="submit-btn" type="button" class="bg-yellow-500 px-4 py-2 rounded text-gray-900 font-bold">Enviar Pedido</button>
              <a href="index.php" class="bg-gray-700 px-4 py-2 rounded">Cancelar</a>
            </div>
            <!-- New items area -->
            <hr class="my-6 border-gray-700">
            <section class="mb-6">
              <h3 class="text-lg font-semibold mb-2">Adicionar itens novos</h3>
              <p class="text-sm text-gray-400 mb-3">Preencha os campos abaixo. Todos os campos são obrigatórios. Quantidade deve ser número inteiro.</p>

              <div id="new-items" class="space-y-2">
                <!-- template row will be cloned -->
              </div>

              <div class="mt-3 flex items-center gap-2">
                <button id="add-new" type="button" class="bg-green-600 px-3 py-2 rounded text-sm font-medium hover:bg-green-500">+ Adicionar linha</button>
                <span class="text-sm text-gray-400">Pode adicionar quantas linhas precisar antes de enviar.</span>
              </div>
            </section>

          </form>
        </div>
      </main>

      <!-- template for new item row (hidden) -->
      <template id="new-item-template">
        <div class="grid grid-cols-12 gap-2 items-center bg-gray-900 p-2 rounded">
          <div class="col-span-3">
            <input type="text" name="new_insumo[]" class="w-full p-2 bg-gray-800 rounded text-sm" required placeholder="Insumo">
          </div>
          <div class="col-span-2">
            <select name="new_categoria[]" class="w-full p-2 bg-gray-800 rounded text-sm" required>
              <option value="">-- Categoria --</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat, ENT_QUOTES) ?>"><?= htmlspecialchars($cat) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-span-2">
            <select name="new_unidade[]" class="w-full p-2 bg-gray-800 rounded text-sm" required>
              <option value="">-- Unidade --</option>
              <?php foreach ($units as $u): ?>
                <option value="<?= htmlspecialchars($u, ENT_QUOTES) ?>"><?= htmlspecialchars($u) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-span-2">
            <input type="number" name="new_qtde[]" class="w-full p-2 bg-gray-800 rounded text-sm" required step="1" min="1" placeholder="Qtde">
          </div>
          <div class="col-span-2">
            <input type="text" name="new_obs[]" class="w-full p-2 bg-gray-800 rounded text-sm" required placeholder="Obs">
          </div>
          <div class="col-span-1 text-right">
            <button type="button" class="remove-new inline-block p-1 rounded text-red-400 hover:bg-red-700">✕</button>
          </div>
        </div>
      </template>

      <script>
        // New items management
        const addNewBtn = document.getElementById('add-new');
        const newItemsContainer = document.getElementById('new-items');
        const newTemplate = document.getElementById('new-item-template');

        function addNewRow(){
          const clone = newTemplate.content.firstElementChild.cloneNode(true);
          const rem = clone.querySelector('.remove-new');
          rem.addEventListener('click', ()=> clone.remove());
          newItemsContainer.appendChild(clone);
          // focus the insumo field
          const ins = clone.querySelector('input[name="new_insumo[]"]');
          if(ins) ins.focus();
          // ensure quantity field starts empty (avoid browser autofill defaulting to 1)
          const newQt = clone.querySelector('input[name="new_qtde[]"]');
          if (newQt) newQt.value = '';
        }

        addNewBtn.addEventListener('click', addNewRow);

  // Reset apenas os campos quantidade que não têm valor pré-definido (sem média) na inicialização
  document.querySelectorAll('input[name^="quantidade"]').forEach(i => { 
    try { 
      // Se o campo já tem um valor (média), mantém. Se não, limpa para evitar autofill indesejado
      if (!i.value || i.value === '') {
        i.value = ''; 
      }
      i.autocomplete = 'off'; 
    } catch(e){} 
  });
  // Add one empty new-item row by default
  addNewRow();

        // Before submit, validate new rows: all required and numeric qtde
        document.getElementById('bar-order-form').addEventListener('submit', function(e){
          const qFields = Array.from(this.querySelectorAll('input[name="new_qtde[]"]'));
          for (const q of qFields){
            const val = Number(q.value);
            if (!Number.isInteger(val) || val <= 0){
              e.preventDefault();
              q.focus();
              alert('Quantidade inválida em um dos itens novos. Informe um número inteiro maior que zero.');
              return false;
            }
          }
          // ensure all required new_* fields are filled
          const requireds = Array.from(this.querySelectorAll('[name^="new_"]'));
          for (const f of requireds){
            if (!f.value || f.value.trim() === ''){
              e.preventDefault();
              f.focus();
              alert('Por favor, preencha todos os campos dos itens novos.');
              return false;
            }
          }
          return true;
        });

        // Inicialização dos filtros - Mover para depois que o DOM estiver pronto
        document.addEventListener('DOMContentLoaded', function() {
          const searchInput = document.getElementById('search-in');
          const categoryChips = Array.from(document.querySelectorAll('.category-chip'));

          // track selected categories (initially all)
          const selectedCats = new Set(categoryChips.map(b => b.dataset.category));

          function applyFilters(){
            const term = searchInput.value.toLowerCase();
            document.querySelectorAll('.insumo-row').forEach(r=>{
              const name = (r.dataset.insumo || '').toLowerCase();
              const cat = (r.dataset.categoria || '');
              const matchesText = name.includes(term);
              const matchesCat = selectedCats.size === 0 || selectedCats.has(cat);
              r.style.display = (matchesText && matchesCat) ? '' : 'none';
            });
          }

          // search input
          if (searchInput) {
            searchInput.addEventListener('input', applyFilters);
          }

          // category chips toggle
          categoryChips.forEach(btn => {
            btn.addEventListener('click', () => {
              const cat = btn.dataset.category;
              if (selectedCats.has(cat)) {
                selectedCats.delete(cat);
                btn.classList.remove('bg-yellow-500','text-gray-900');
                btn.classList.add('bg-gray-800','text-gray-200');
                btn.setAttribute('aria-pressed','false');
              } else {
                selectedCats.add(cat);
                btn.classList.add('bg-yellow-500','text-gray-900');
                btn.classList.remove('bg-gray-800','text-gray-200');
                btn.setAttribute('aria-pressed','true');
              }
              applyFilters();
            });
            // set initial visual state to 'selected'
            btn.classList.add('bg-yellow-500','text-gray-900');
            btn.classList.remove('bg-gray-800','text-gray-200');
          });

          // initial apply
          applyFilters();
        });

        // --- setor modal handling ---
        (function(){
          const modal = document.getElementById('setor-modal');
          const setorInput = document.getElementById('setor-input');
          const setorButtons = Array.from(document.querySelectorAll('.setor-btn'));
          // show modal on load if setor not prefilled
          function showIfNeeded(){
            if (setorInput && (!setorInput.value || setorInput.value.trim() === '')){
              modal.classList.remove('hidden');
              modal.classList.add('flex');
            }
          }
          // set selected visual state and value
          setorButtons.forEach(b => {
            b.addEventListener('click', () => {
              const v = b.dataset.setor || '';
              if (setorInput) setorInput.value = v;
              // small visual feedback
              setorButtons.forEach(x => x.classList.remove('ring','ring-2','ring-yellow-400'));
              b.classList.add('ring','ring-2','ring-yellow-400');
              // hide modal after short delay so user sees selection
              setTimeout(()=>{ modal.classList.add('hidden'); modal.classList.remove('flex'); }, 200);
            });
          });
          // ensure modal is visible initially
          // add hidden class fallback if missing
          if (modal && !modal.classList.contains('hidden')){
            // already visible: ok
          } else if (modal) {
            // ensure modal is hidden in DOM initially (some themes may hide by default)
            modal.classList.add('hidden');
          }
          // show on next tick
          setTimeout(showIfNeeded, 80);
        })();
      </script>
      <!-- Preview modal -->
      <div id="preview-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden overflow-auto items-start sm:items-center justify-center py-8">
        <div class="bg-gray-900 w-[90%] max-w-3xl p-4 rounded shadow-lg">
          <header class="flex items-center justify-between mb-3">
            <h2 class="text-xl font-semibold">Pré-visualização do Pedido</h2>
            <button id="close-preview" class="text-gray-400 hover:text-white">✕</button>
          </header>
          <div id="preview-body" class="text-sm" style="max-height:75vh; overflow:auto;">
            <!-- populated by JS -->
          </div>
          <div class="mt-4 flex justify-end gap-2">
            <button id="confirm-send" class="bg-yellow-500 px-4 py-2 rounded text-gray-900 font-bold">Confirmar e Enviar</button>
            <button id="cancel-preview" class="bg-gray-700 px-4 py-2 rounded">Cancelar</button>
          </div>
        </div>
      </div>

      <script>
  function gatherPreviewItems(){
          const rows = [];
          // existing items: iterate rows directly to avoid issues when codigo is empty
                  const rowEls = document.querySelectorAll('.insumo-row');
                  rowEls.forEach(r => {
                    // REMOVED: do not skip hidden/filtered rows — collect all filled items so filters
                    // don't prevent items from being sent. We still only include rows with qtde > 0.
                    const nameHidden = r.querySelector('input[name^="produto_nome"]');
                    const uniHidden = r.querySelector('input[name^="produto_unidade"]');
                    const catHidden = r.querySelector('input[name^="produto_categoria"]');
                    const qt = r.querySelector('input[name^="quantidade"]');
                    const obs = r.querySelector('input[name^="observacao"]');
                    const name = nameHidden ? nameHidden.value : (r.dataset.insumo || '');
                    const uni = uniHidden ? uniHidden.value : '';
                    const cat = catHidden ? catHidden.value : (r.dataset.categoria || '');
                    const qv = qt ? parseFloat(qt.value) || 0 : 0;
                    const obv = obs ? obs.value : '';
                    if (qv > 0) {
                      rows.push({produto: name, categoria: cat, und: uni, qtde: qv, observacao: obv});
                    }
                  });

          // new items
          const newIns = Array.from(document.querySelectorAll('input[name="new_insumo[]"]'));
          const newCats = Array.from(document.querySelectorAll('select[name="new_categoria[]"]'));
          const newUnis = Array.from(document.querySelectorAll('select[name="new_unidade[]"]'));
          const newQtds = Array.from(document.querySelectorAll('input[name="new_qtde[]"]'));
          const newObss = Array.from(document.querySelectorAll('input[name="new_obs[]"]'));
          newIns.forEach((el, idx) => {
            const nome = (el.value || '').trim();
            const cat = (newCats[idx] || {}).value || '';
            const uni = (newUnis[idx] || {}).value || '';
            const qt = parseFloat((newQtds[idx] || {}).value) || 0;
            const obs = (newObss[idx] || {}).value || '';
            if (nome && qt > 0){
              rows.push({produto: nome, categoria: cat, und: uni, qtde: qt, observacao: obs});
            }
          });
          return rows;
        }

  const previewModal = document.getElementById('preview-modal');
        const previewBody = document.getElementById('preview-body');
        const closePreview = document.getElementById('close-preview');
        const cancelPreview = document.getElementById('cancel-preview');
        const confirmSend = document.getElementById('confirm-send');
        const submitBtn = document.getElementById('submit-btn');

        function renderPreview(){
          const items = gatherPreviewItems();
          if (items.length === 0){
            previewBody.innerHTML = '<div class="p-3 bg-yellow-700 text-black rounded">Nenhum item adicionado para este pedido.</div>';
            return;
          }
          let html = '<table class="w-full"><thead class="text-left text-yellow-400"><tr><th>Produto</th><th>Categoria</th><th>Unidade</th><th>Qtde</th><th>Obs</th></tr></thead><tbody>';
          items.forEach(it => {
            html += '<tr class="border-b border-gray-700"><td class="py-2">'+escapeHtml((it.produto||''))+'</td><td>'+escapeHtml(it.categoria||'')+'</td><td>'+escapeHtml(it.und||'')+'</td><td>'+escapeHtml(String(it.qtde))+'</td><td>'+escapeHtml(it.observacao||'')+'</td></tr>';
          });
          html += '</tbody></table>';
          previewBody.innerHTML = html;
        }

        function escapeHtml(s){
          return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        submitBtn.addEventListener('click', ()=>{
          renderPreview();
          previewModal.classList.remove('hidden');
          previewModal.classList.add('flex');
        });
        closePreview.addEventListener('click', ()=>{ previewModal.classList.add('hidden'); previewModal.classList.remove('flex'); });
        cancelPreview.addEventListener('click', ()=>{ previewModal.classList.add('hidden'); previewModal.classList.remove('flex'); });

        // pack items into hidden JSON field to handle very large forms and ensure new items are included
        function packItemsToJson(){
          const items = gatherPreviewItems();
          const hidden = document.getElementById('items-json');
          if (hidden) hidden.value = JSON.stringify(items);
        }

        // block submit if setor not selected (defense-in-depth)
        document.getElementById('bar-order-form').addEventListener('submit', function(e){
          const setorInput = document.getElementById('setor-input');
          if (!setorInput || !setorInput.value || setorInput.value.trim() === ''){
            // show modal and prevent submit
            const modal = document.getElementById('setor-modal');
            if (modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); }
            e.preventDefault();
            alert('Selecione o setor do pedido antes de continuar.');
            return false;
          }
          // pack items as fallback
          packItemsToJson();
          return true;
        });

        // show loading on confirm and submit the form
        confirmSend.addEventListener('click', ()=>{
          // pack items
          ensureUppercaseAll();
          packItemsToJson();
          // disable buttons and show spinner
          confirmSend.disabled = true;
          confirmSend.innerHTML = 'Enviando...';
          // submit the form normally
          document.getElementById('bar-order-form').submit();
        });

        // fallback: if user submits the form without using confirm button, pack items on submit event
        document.getElementById('bar-order-form').addEventListener('submit', function(e){
          ensureUppercaseAll();
          packItemsToJson();
          return true;
        });

        // ensure text inputs/selects are uppercase both visually (CSS) and value-wise before submit
        function ensureUppercaseAll(){
          // inputs and textareas
          Array.from(document.querySelectorAll('input[type="text"], textarea')).forEach(el=>{
            if (el.value) el.value = String(el.value).toUpperCase();
          });
          // select elements: uppercase their selected value if present
          Array.from(document.querySelectorAll('select')).forEach(el=>{
            try { if (el.value) el.value = String(el.value).toUpperCase(); } catch(e){}
          });
          // also uppercase hidden product fields
          Array.from(document.querySelectorAll('input[name^="produto_nome"], input[name^="produto_unidade"], input[name^="produto_categoria"]')).forEach(el=>{ if (el.value) el.value = String(el.value).toUpperCase(); });
        }

        // live uppercase on new item inputs for better UX
        document.addEventListener('input', function(e){
          const t = e.target;
          if (!t) return;
          if ((t.tagName === 'INPUT' && t.type === 'text') || t.tagName === 'TEXTAREA'){
            // avoid changing caret position complexity: replace value with uppercase
            const pos = t.selectionStart; t.value = t.value.toUpperCase(); try{ t.setSelectionRange(pos,pos); }catch(e){}
          }
        });

        // uppercase selects immediately on change (so values are uppercase in UI)
        document.querySelectorAll('select').forEach(s => {
          s.addEventListener('change', function(){ try{ this.value = String(this.value).toUpperCase(); }catch(e){} });
        });

        // Uppercase existing items in the table and hidden fields immediately on load
        function uppercaseExistingItems(){
          // hidden meta fields
          document.querySelectorAll('input[name^="produto_nome"], input[name^="produto_unidade"], input[name^="produto_categoria"]').forEach(el=>{ if (el.value) el.value = String(el.value).toUpperCase(); });
          // table rows: uppercase displayed insumo, categoria, unidade
          document.querySelectorAll('.insumo-row').forEach(r => {
            const tds = r.querySelectorAll('td');
            if (tds[1]) tds[1].innerText = String(tds[1].innerText || '').toUpperCase();
            if (tds[2]) tds[2].innerText = String(tds[2].innerText || '').toUpperCase();
            if (tds[3]) tds[3].innerText = String(tds[3].innerText || '').toUpperCase();
            // observation input
            const obs = r.querySelector('input[name^="observacao"]');
            if (obs && obs.value) obs.value = String(obs.value).toUpperCase();
          });
        }
        // run once on DOM ready
        setTimeout(uppercaseExistingItems, 50);
        
      </script>
    </body>
    </html>