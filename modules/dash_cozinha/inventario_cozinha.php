<?php
// modules/dash_cozinha/inventario_cozinha.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';


require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

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

// Função utilitária para escapar caracteres no Markdown do Telegram
function escapeTelegramMarkdown(string $texto): string {
  $map = ['\\' => '\\\\', '_' => '\\_', '*' => '\\*', '`' => '\\`', '[' => '\\[', ']' => '\\]'];
  return str_replace(array_keys($map), array_values($map), $texto);
}

// Nota: envio por Telegram passa a ser feito pelo telefone do usuário via Web Share API.

// Busca insumos do grupo solicitado
$group = 'W - INSUMOS - W - INSUMO COZINHA';
$sql = "SELECT `Cód. Ref.` AS codigo, `Nome` AS nome, `Grupo` AS grupo, `Unidade` AS unidade FROM ProdutosBares WHERE `Grupo` = ? ORDER BY `Nome`";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $group);
$stmt->execute();
$res = $stmt->get_result();
$insumos = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

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

  <aside class="bg-gray-800 w-60 p-6 flex-shrink-0">
    <?php include __DIR__ . '/../../sidebar.php'; ?>
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
          </div>
        </div>
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
        const cod = r.querySelector('input[name="codigo[]"]').value.trim();
        const qtdInput = r.querySelector('input[name="quantidade[]"]');
        const qtd = qtdInput ? qtdInput.value.trim() : '';
        if (!cod || !qtd) return;
        const q = qtd.replace('.', ',');
        // sanitiza
        const codS = cod.replace(/[;\n\r]/g, ' ');
        const qS = q.replace(/[;\n\r]/g, ' ');
        lines.push(codS + ';' + qS);
      });
      return lines.join('\n');
    }

    document.getElementById('share-telegram').addEventListener('click', async function () {
      const content = generateTxtContent();
      if (!content || content.split('\n').length <= 1) {
        alert('Preencha ao menos uma quantidade antes de compartilhar.');
        return;
      }

      const filename = 'inventario_cozinha_' + new Date().toISOString().replace(/[:.]/g,'') + '.txt';

      // Se Web Share API for suportada com arquivos
      if (navigator.canShare && navigator.canShare({ files: [] })) {
        const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
        const filesArray = [new File([blob], filename, { type: 'text/plain' })];
        try {
          await navigator.share({ files: filesArray, title: 'Inventário de Insumos', text: 'Inventário gerado' });
          return;
        } catch (err) {
          console.warn('Share failed', err);
        }
      }

      // Fallback: gerar e forçar download (o usuário pode então abrir o Telegram e enviar o arquivo manualmente)
      const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
      alert('Arquivo gerado para download. Use o app do Telegram para compartilhar o arquivo.');
    });
  </script>
</body>
</html>
