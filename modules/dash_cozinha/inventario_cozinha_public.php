<?php
// modules/dash_cozinha/inventario_cozinha.php
require __DIR__ . '/../../config/db.php';



// Use a conexão Cloudify (não sobrescrever $pdo usado pelo sidebar)
require_once __DIR__ . '/../../config/db_dw.php';
// espera-se que config/db_dw.php defina $pdo_dw (PDO)

// Quando o formulário for submetido, gera o .txt para download
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $colaborador = trim($_POST['colaborador'] ?? '');
    $data_inventario = trim($_POST['data'] ?? date('Y-m-d'));

    $codigos = $_POST['codigo'] ?? [];
    $quantidades = $_POST['quantidade'] ?? [];

    $lines = [];
    // Cabeçalho conforme solicitado
    $lines[] = "Código de referência;Quantidade apurada";

    for ($i = 0; $i < count($codigos); $i++) {
        $cod = trim($codigos[$i]);
        $q = trim($quantidades[$i] ?? '');
        if ($cod === '' || $q === '') continue;

        // Assunção: preservamos a entrada do usuário, apenas normalizamos vírgula decimal para ponto
        // Substitui vírgula por ponto (ex: 1,5 -> 1.5). Se já usar ponto, preserva.
        $q_out = str_replace(',', '.', $q);

        // Escapa ponto-e-vírgula acidental no código ou quantidade
        $cod_sanit = str_replace([';', "\n", "\r"], [' ', ' ', ' '], $cod);
        $q_sanit = str_replace([';', "\n", "\r"], [' ', ' ', ' '], $q_out);

        $lines[] = $cod_sanit . ';' . $q_sanit;
    }

    $filename = 'inventario_cozinha_' . date('Ymd_His') . '.txt';
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    // Informação opcional no topo (comentada). Se preferir apenas colunas, manter o cabeçalho acima.
    // echo "Colaborador: $colaborador\nData: $data_inventario\n\n";
  echo implode("\n", $lines);
  exit;
}

// Envio por Telegram será feito pelo telefone do usuário via Web Share API (cliente).

// Endpoint AJAX: retorna lista atualizada de insumos em JSON (agora recebe `empresa`)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'refresh') {
  $empresa = $_GET['empresa'] ?? '';
  // Mapear empresa para o grupo correto
  if ($empresa === 'WAB') {
    $groupAjax = 'WAB - INSUMOS - WAB - INSUMO COZINHA';
  } elseif ($empresa === 'BDF') {
    $groupAjax = 'TAP - INSUMOS ESTOQUE - TAP - INSUMO COZINHA';
  } else {
    // Sem empresa válida: retorna array vazio
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $sqlAjax = "SELECT `Cód. Ref.` AS codigo, `Nome` AS nome, `Grupo` AS grupo, `Unidade` AS unidade FROM ProdutosBares WHERE `Grupo` = ? ORDER BY `Nome`";
  try {
    $stmtAjax = $pdo_dw->prepare($sqlAjax);
    $stmtAjax->execute([$groupAjax]);
    $insumosAjax = $stmtAjax->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    $insumosAjax = [];
  }
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($insumosAjax, JSON_UNESCAPED_UNICODE);
  exit;
}

// Busca insumos do grupo solicitado — inicialmente vazio; a listagem será carregada via AJAX após selecionar empresa e clicar Atualizar
$insumos = [];

// Bloco de diagnóstico acionável em produção: passe ?debug=1 na URL para ver informações.
$debug_info = '';
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
  try {
    // Informações de conexão/DB
    $dbName = $pdo_dw->query('SELECT DATABASE()')->fetchColumn();
    $lower = $pdo_dw->query('SELECT @@lower_case_table_names')->fetchColumn();
    $charset = $pdo_dw->query('SELECT @@character_set_database')->fetchColumn();
    $collation = $pdo_dw->query('SELECT @@collation_database')->fetchColumn();

    $debug_info .= "Database: $dbName\n";
    $debug_info .= "lower_case_table_names: $lower\n";
    $debug_info .= "character_set_database: $charset\n";
    $debug_info .= "collation_database: $collation\n\n";

    // Contagens e comparações para os dois grupos
    $groups = [
    'WAB' => 'WAB - INSUMOS - WAB - INSUMO COZINHA',
      'BDF' => 'TAP - INSUMOS ESTOQUE - TAP - INSUMO COZINHA',
    ];
    foreach ($groups as $k => $g) {
      $stmt = $pdo_dw->prepare('SELECT COUNT(*) FROM ProdutosBares WHERE `Grupo` = ?');
      $stmt->execute([$g]);
      $cnt_exact = $stmt->fetchColumn();

      $stmt = $pdo_dw->prepare('SELECT COUNT(*) FROM ProdutosBares WHERE TRIM(`Grupo`) = ?');
      $stmt->execute([$g]);
      $cnt_trim = $stmt->fetchColumn();

      $stmt = $pdo_dw->prepare('SELECT COUNT(*) FROM ProdutosBares WHERE `Grupo` LIKE ?');
      $stmt->execute(['%INSUMO%']);
      $cnt_like = $stmt->fetchColumn();

      $debug_info .= "$k -> group: $g\n exact=$cnt_exact trim=$cnt_trim likeINSUMO=$cnt_like\n\n";
    }

    // Valores distintos de Grupo com tamanho e HEX
    $stmt = $pdo_dw->query("SELECT `Grupo`, COUNT(*) AS cnt, CHAR_LENGTH(`Grupo`) AS len, HEX(`Grupo`) AS hexval FROM ProdutosBares GROUP BY `Grupo`, CHAR_LENGTH(`Grupo`), HEX(`Grupo`) ORDER BY cnt DESC LIMIT 50");
    $groupsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debug_info .= "Distinct Grupo (sample):\n";
    foreach ($groupsList as $gr) {
      $debug_info .= "{$gr['Grupo']} | cnt={$gr['cnt']} | len={$gr['len']} | hex={$gr['hexval']}\n";
    }

    // Amostra de linhas que contêm INSUMO
    $stmt = $pdo_dw->prepare("SELECT `Cód. Ref.`, `Nome`, `Grupo`, `Unidade`, CHAR_LENGTH(`Grupo`) AS len, HEX(`Grupo`) AS hexval FROM ProdutosBares WHERE `Grupo` LIKE ? LIMIT 200");
    $stmt->execute(['%INSUMO%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debug_info .= "\nSample rows (like %INSUMO%):\n";
    foreach (array_slice($rows, 0, 50) as $r) {
      $cr = $r['Cód. Ref.'] ?? $r['Cód. Ref.'];
      $debug_info .= "$cr | {$r['Nome']} | {$r['Grupo']} | len={$r['len']} | hex={$r['hexval']}\n";
    }

  } catch (PDOException $e) {
    $debug_info = 'DEBUG ERROR: ' . $e->getMessage();
  }
}

// Valores padrão
$usuario = $_SESSION['usuario_nome'] ?? '';
$hoje = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Inventário de Insumos - Cozinha</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="/assets/css/style.css" rel="stylesheet">
  <!-- jsPDF + autoTable (gera formulário PDF A4 para impressão) -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
  <style>
    input[type=number]::-webkit-outer-spin-button,
    input[type=number]::-webkit-inner-spin-button { -webkit-appearance:none }
    input[type=number] { -moz-appearance:textfield }
    .qtd-input { width:5rem; text-align:center }
  </style>
</head>
<body class="bg-gray-900 text-gray-100 flex flex-col p-4 sm:p-6 sm:flex-row">


  <main class="flex-1 p-6 sm:pt-10 sm:max-w-6xl mx-auto">
    <?php if (!empty($debug_info)): ?>
      <div class="mb-4 p-3 bg-gray-800 text-sm text-yellow-300 rounded"><pre style="white-space:pre-wrap;"><?= htmlspecialchars($debug_info, ENT_QUOTES | ENT_SUBSTITUTE) ?></pre></div>
    <?php endif; ?>
    <h1 class="text-2xl font-bold text-yellow-400 mb-4">Inventário Cozinha</h1>

    <form method="post" class="grid grid-cols-1">
      <?php if (!empty($notice)): ?>
        <div class="mb-4 p-3 bg-gray-700 text-yellow-300 rounded">
          <?= htmlspecialchars($notice, ENT_QUOTES) ?>
        </div>
      <?php endif; ?>
      <div class="mb-4 gap-4 grid">
        <div>
          <label class="text-xs">Colaborador</label>
          <input type="text" name="colaborador" value="<?= htmlspecialchars($usuario, ENT_QUOTES) ?>" class="w-full bg-gray-800 text-white p-2 rounded text-sm">
        </div>
        <div>
          <label class="text-xs">Data do inventário</label>
          <input type="date" name="data" value="<?= htmlspecialchars($hoje) ?>" class="w-full bg-gray-800 text-white p-2 rounded text-sm">
        </div>
              <!-- Seletor de empresa (checkboxes) -->
        <div class="mb-4 p-3 bg-gray-800 rounded flex items-center gap-6">
          <label class="inline-flex items-center text-sm"><input type="checkbox" id="empresa_wab"> <span class="ml-2">WAB</span></label>
          <label class="inline-flex items-center text-sm"><input type="checkbox" id="empresa_bdf"> <span class="ml-2">BDF</span></label>
          <div class="text-xs text-gray-400">Selecione a empresa e clique em "Atualizar listagem"</div>
          <button type="button" id="refresh-list" class="btn-acao-verde">Atualizar listagem</button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end">
            <button type="button" id="print-form" class="btn-acao-azul">Imprimir Formulário</button>
            <button type="button" id="clear-inputs" class="btn-acao-vermelho">Limpar preenchimento</button>
            <button type="submit" name="generate" value="1" class="btn-acao">Salvar e baixar arquivo</button>
      </div>
      </div>


      <!-- Filtro dinâmico -->
      <div class="mb-4">
        <input id="filtro-global" type="text" placeholder="Filtrar (digite 3+ caracteres)" class="w-full bg-gray-800 text-white p-2 rounded text-sm" />
      </div>
      
      <div class="overflow-x-auto bg-gray-800 rounded-lg shadow mb-8">
        <table class="min-w-full text-xs mx-auto">
          <thead class="bg-gray-700 text-yellow-400">
            <tr>
              <th class="p-2 text-left sortable" data-col="0">Código <span class="sort-indicator"></span></th>
              <th class="p-2 text-left sortable" data-col="1">Nome <span class="sort-indicator"></span></th>
              <th class="p-2 text-left sortable" data-col="2">Grupo <span class="sort-indicator"></span></th>
              <th class="p-2 text-left sortable" data-col="3">Unidade <span class="sort-indicator"></span></th>
               <th class="p-2 text-center" style="width:12rem">Quantidade apurada</th>
             </tr>
           </thead>
          <tbody class="divide-y divide-gray-700">
            <?php foreach ($insumos as $row):
              $cod = htmlspecialchars($row['codigo'] ?? '', ENT_QUOTES);
              $nome = htmlspecialchars($row['nome'] ?? '', ENT_QUOTES);
              $grupo = htmlspecialchars($row['grupo'] ?? '', ENT_QUOTES);
              $unidade = htmlspecialchars($row['unidade'] ?? '', ENT_QUOTES);
            ?>
            <tr class="hover:bg-gray-700">
              <td class="p-2"><?= $cod ?>
                <input type="hidden" name="codigo[]" value="<?= $cod ?>">
              </td>
              <td class="p-2"><?= $nome ?></td>
              <td class="p-2"><?= $grupo ?><input type="hidden" name="grupo[]" value="<?= $grupo ?>"></td>
              <td class="p-2"><?= $unidade ?><input type="hidden" name="unidade[]" value="<?= $unidade ?>"></td>
              <td class="p-2 text-center">
                <input type="text" name="quantidade[]" placeholder="0,00" class="qtd-input bg-gray-600 text-white text-xs p-1 rounded">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </form>
  </main>
  <script>
    // Gera o conteúdo do .txt com ; e ponto decimal a partir do formulário
    function generateTxtContent() {
      const rows = document.querySelectorAll('tbody tr');
      const lines = ['Código de referência;Quantidade apurada'];
      rows.forEach(r => {
        const codEl = r.querySelector('input[name="codigo[]"]');
        const qtdInput = r.querySelector('input[name="quantidade[]"]');
        if (!codEl || !qtdInput) return;
        const cod = codEl.value.trim();
        const qtd = qtdInput.value.trim();
        if (!cod || !qtd) return;
        const q = qtd.replace(',', '.');
        const codS = cod.replace(/[;\n\r]/g, ' ');
        const qS = q.replace(/[;\n\r]/g, ' ');
        lines.push(codS + ';' + qS);
      });
      return lines.join('\n');
    }

    // Controle mutual-exclusion para os checkboxes de empresa
    const cbWab = document.getElementById('empresa_wab');
    const cbBdf = document.getElementById('empresa_bdf');
    cbWab.addEventListener('change', () => { if (cbWab.checked) cbBdf.checked = false; });
    cbBdf.addEventListener('change', () => { if (cbBdf.checked) cbWab.checked = false; });

    // Atualiza a listagem a partir do banco via AJAX, enviando a empresa selecionada
    document.getElementById('refresh-list').addEventListener('click', async function () {
      const empresa = cbWab.checked ? 'WAB' : (cbBdf.checked ? 'BDF' : '');
      if (!empresa) { alert('Selecione a empresa (WAB ou BDF) antes de atualizar.'); return; }
      try {
        const resp = await fetch(window.location.pathname + '?action=refresh&empresa=' + empresa);
        if (!resp.ok) throw new Error('Falha ao buscar listagem');
        const data = await resp.json();
        const tbody = document.querySelector('tbody');
        tbody.innerHTML = '';
          data.forEach(row => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-700';
            tr.innerHTML = `
              <td class="p-2">${row.codigo}<input type="hidden" name="codigo[]" value="${row.codigo}"></td>
              <td class="p-2">${row.nome}</td>
              <td class="p-2">${row.grupo}<input type="hidden" name="grupo[]" value="${row.grupo}"></td>
              <td class="p-2">${row.unidade}<input type="hidden" name="unidade[]" value="${row.unidade}"></td>
              <td class="p-2 text-center"><input type="text" name="quantidade[]" placeholder="0,00" class="qtd-input bg-gray-600 text-white text-xs p-1 rounded"></td>
            `;
            tbody.appendChild(tr);
          });
          attachRowHighlight();
        } catch (err) {
          console.error(err);
          alert('Erro ao atualizar listagem');
        }
      });

    // Limpa todos os inputs de quantidade
    document.getElementById('clear-inputs').addEventListener('click', function () {
      const inputs = document.querySelectorAll('input[name="quantidade[]"]');
      inputs.forEach(i => i.value = '');
    });

    function attachRowHighlight() {
      document.querySelectorAll('input[name="quantidade[]"]').forEach(inp => {
        inp.addEventListener('focus', () => {
          inp.closest('tr')?.classList.add('bg-gray-700');
        });
        inp.addEventListener('blur', () => {
          inp.closest('tr')?.classList.remove('bg-gray-700');
        });
      });
    }

    attachRowHighlight();

    // --- Sortable and Filter logic ---
    function compareValues(a, b, asc) {
      // try numeric compare
      const na = parseFloat(a.replace(',', '.'));
      const nb = parseFloat(b.replace(',', '.'));
      if (!isNaN(na) && !isNaN(nb)) return asc ? na - nb : nb - na;
      a = a.toLowerCase(); b = b.toLowerCase();
      if (a < b) return asc ? -1 : 1;
      if (a > b) return asc ? 1 : -1;
      return 0;
    }

    function sortTable(colIndex) {
      const table = document.querySelector('table');
      const tbody = table.querySelector('tbody');
      const rows = Array.from(tbody.querySelectorAll('tr'));
      const th = table.querySelector('th.sortable[data-col="' + colIndex + '"]');
      const asc = !(th.dataset.sortOrder === 'asc');
      rows.sort((r1, r2) => {
        const c1 = (r1.children[colIndex].textContent || '').trim();
        const c2 = (r2.children[colIndex].textContent || '').trim();
        return compareValues(c1, c2, asc);
      });
      // apply order
      rows.forEach(r => tbody.appendChild(r));
      // update indicators
      document.querySelectorAll('th.sortable').forEach(h => { h.dataset.sortOrder = ''; h.querySelector('.sort-indicator').textContent = ''; });
      th.dataset.sortOrder = asc ? 'asc' : 'desc';
      th.querySelector('.sort-indicator').textContent = asc ? ' ▲' : ' ▼';
    }

    // attach click handlers to sortable headers
    document.querySelectorAll('th.sortable').forEach(th => {
      th.style.cursor = 'pointer';
      th.addEventListener('click', () => sortTable(parseInt(th.dataset.col, 10)));
    });

    // filter
    const filtroInput = document.getElementById('filtro-global');
    let filtroTimer = null;
    function applyFilterNow() {
      const q = (filtroInput.value || '').trim().toLowerCase();
      const tbody = document.querySelector('tbody');
      const rows = Array.from(tbody.querySelectorAll('tr'));
      if (q.length < 3) {
        // show all
        rows.forEach(r => r.style.display = '');
        return;
      }
      rows.forEach(r => {
        const text = Array.from(r.children).map(td => td.textContent || '').join(' ').toLowerCase();
        r.style.display = text.indexOf(q) !== -1 ? '' : 'none';
      });
    }
    filtroInput.addEventListener('input', function () {
      clearTimeout(filtroTimer);
      filtroTimer = setTimeout(applyFilterNow, 300);
    });

    // Apply filter after populating table on refresh
    const originalRefresh = document.getElementById('refresh-list').onclick;
    // no-op: we keep existing listener; after it finishes, we reapply filter (handled in fetch flow)

    // Gera PDF A4 (formulário para impressão) com cabeçalho repetido em cada página
    document.getElementById('print-form').addEventListener('click', function () {
      const rows = Array.from(document.querySelectorAll('tbody tr'));
      if (!rows || rows.length === 0) { alert('Tabela vazia. Atualize a listagem antes.'); return; }
      const colaborador = document.querySelector('input[name="colaborador"]').value || '';
      const dataInv = document.querySelector('input[name="data"]').value || '';

      const head = [['Código', 'Nome', 'Grupo', 'Unidade', 'Quantidade apurada']];
      const body = rows.map(r => {
        const tds = r.querySelectorAll('td');
        const codigo = (tds[0] && tds[0].textContent) ? tds[0].textContent.trim() : '';
        const nome = (tds[1] && tds[1].textContent) ? tds[1].textContent.trim() : '';
        const grupo = (tds[2] && tds[2].textContent) ? tds[2].textContent.trim() : '';
        const unidade = (tds[3] && tds[3].textContent) ? tds[3].textContent.trim() : '';
        const qtdInput = r.querySelector('input[name="quantidade[]"]');
        const quantidade = qtdInput ? qtdInput.value.trim() : '';
        return [codigo, nome, grupo, unidade, quantidade];
      });

      const { jsPDF } = window.jspdf;
      const doc = new jsPDF({ unit: 'mm', format: 'a4' });
      const pageWidth = doc.internal.pageSize.getWidth();
      const margin = 15;

      // Reservar espaço maior para o cabeçalho (em mm) para evitar sobreposição
      const headerHeight = 38; // ajuste: espaço reservado do topo até a linha separadora

      const drawHeader = function (pageNumber) {
        doc.setFontSize(12);
        doc.setFont(undefined, 'bold');
        doc.text('Inventário Cozinha - Formulário de Contagem', margin, 16);
        doc.setFontSize(9);
        doc.setFont(undefined, 'normal');
        doc.text('Colaborador: ' + colaborador, margin, 23);
        doc.text('Data: ' + dataInv, margin + 100, 23);
        doc.setFontSize(8);
        doc.text('Página ' + pageNumber, pageWidth - margin, 16, { align: 'right' });
        doc.setLineWidth(0.2);
        // linha abaixo do cabeçalho
        doc.line(margin, headerHeight - 4, pageWidth - margin, headerHeight - 4);
      };

      doc.autoTable({
        head: head,
        body: body,
        startY: headerHeight,
        margin: { left: margin, right: margin, top: headerHeight },
        styles: { fontSize: 8, cellPadding: 2 },
        headStyles: { fillColor: [55,65,81], textColor: 255 },
        didDrawPage: function (data) {
          // data.pageNumber é fornecido pelo autoTable e indica a página atual
          drawHeader(data.pageNumber);
        }
      });

      // abre em nova aba para o usuário imprimir/anotar
      const url = doc.output('bloburl');
      window.open(url, '_blank');
    });
  </script>
</body>
</html>
