<?php
// modules/dash_cozinha/inventario_cozinha.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';


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

        // Assunção: preservamos a entrada do usuário, apenas normalizamos ponto decimal para vírgula
        // Substitui ponto por vírgula (ex: 1.5 -> 1,5). Se já usar vírgula, preserva.
        $q_out = str_replace('.', ',', $q);

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
    $groupAjax = 'W - INSUMOS - W - INSUMO COZINHA';
  } elseif ($empresa === 'BDF') {
    $groupAjax = 'T - PRODUTOS INTERMEDIARIOS - T - INSUMO COZINHA';
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
  <style>
    input[type=number]::-webkit-outer-spin-button,
    input[type=number]::-webkit-inner-spin-button { -webkit-appearance:none }
    input[type=number] { -moz-appearance:textfield }
    .qtd-input { width:5rem; text-align:center }
  </style>
</head>
<body class="bg-gray-900 text-gray-100 flex min-h-screen">

  <aside>
    <?php
    // Recarrega a conexão principal (intranet) para garantir que $pdo aponte para o DB correto
    require __DIR__ . '/../../config/db.php';
    include __DIR__ . '/../../sidebar.php';
    ?>
  </aside>

  <main class="flex-1 bg-gray-900 p-6 relative">
    <h1 class="text-2xl font-bold text-yellow-400 mb-4">Inventário de Insumos — Cozinha</h1>

    <form method="post">
      <?php if (!empty($notice)): ?>
        <div class="mb-4 p-3 bg-gray-700 text-yellow-300 rounded">
          <?= htmlspecialchars($notice, ENT_QUOTES) ?>
        </div>
      <?php endif; ?>
      <div class="mb-4 grid grid-cols-3 gap-4">
        <div>
          <label class="text-xs">Colaborador</label>
          <input type="text" name="colaborador" value="<?= htmlspecialchars($usuario, ENT_QUOTES) ?>" class="w-full bg-gray-800 text-white p-2 rounded text-sm">
        </div>
        <div>
          <label class="text-xs">Data do inventário</label>
          <input type="date" name="data" value="<?= htmlspecialchars($hoje) ?>" class="w-full bg-gray-800 text-white p-2 rounded text-sm">
        </div>
        <div class="flex items-end">
          <div class="space-y-2 w-full">
            <button type="submit" name="generate" value="1" class="w-full bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold py-2 rounded">Salvar e baixar .txt</button>
            <button type="button" id="share-telegram" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded">Compartilhar via Telegram (telefone)</button>
            <div class="flex gap-2">
              <button type="button" id="refresh-list" class="flex-1 bg-gray-600 hover:bg-gray-500 text-white font-bold py-2 rounded text-sm">Atualizar listagem</button>
              <button type="button" id="clear-inputs" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2 rounded text-sm">Limpar preenchimento</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Seletor de empresa (checkboxes) -->
      <div class="mb-4 p-3 bg-gray-800 rounded flex items-center gap-6">
        <label class="inline-flex items-center text-sm"><input type="checkbox" id="empresa_wab"> <span class="ml-2">WAB</span></label>
        <label class="inline-flex items-center text-sm"><input type="checkbox" id="empresa_bdf"> <span class="ml-2">BDF</span></label>
        <div class="text-xs text-gray-400">Selecione a empresa e clique em "Atualizar listagem"</div>
      </div>

      <div class="overflow-x-auto bg-gray-800 rounded-lg shadow mb-8">
        <table class="min-w-full text-xs mx-auto">
          <thead class="bg-gray-700 text-yellow-400">
            <tr>
              <th class="p-2 text-left">Código</th>
              <th class="p-2 text-left">Nome</th>
              <th class="p-2 text-left">Grupo</th>
              <th class="p-2 text-left">Unidade</th>
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
    // Gera o conteúdo do .txt com ; e vírgula decimal a partir do formulário
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
        const q = qtd.replace('.', ',');
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

    document.getElementById('share-telegram').addEventListener('click', async function () {
      const content = generateTxtContent();
      if (!content || content.split('\n').length <= 1) {
        alert('Preencha ao menos uma quantidade antes de compartilhar.');
        return;
      }
      const filename = 'inventario_cozinha_' + new Date().toISOString().replace(/[:.]/g,'') + '.txt';
      if (navigator.canShare && navigator.canShare({ files: [] })) {
        const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
        const filesArray = [new File([blob], filename, { type: 'text/plain' })];
        try { await navigator.share({ files: filesArray, title: 'Inventário de Insumos', text: 'Inventário gerado' }); return; } catch (err) {}
      }
      const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a'); a.href = url; a.download = filename; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
      alert('Arquivo gerado para download. Use o app do Telegram para compartilhar o arquivo.');
    });

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
  </script>
</body>
</html>
