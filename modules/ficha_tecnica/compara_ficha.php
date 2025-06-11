<?php
require_once '../../config/db.php';      // Intranet (ficha_tecnica)
require_once '../../config/db_dw.php';   // Cloudify (insumos_bastards)
include '../../sidebar.php';

$codigo_prato = isset($_GET['cod']) ? trim($_GET['cod']) : '';
$ficha_intranet = [];
$ficha_cloudify = [];
$nome_intranet = '-';
$nome_cloudify = '-';
$farol_status = 'gray';

if ($codigo_prato !== '') {
    $stmt = $pdo->prepare("SELECT nome_prato FROM ficha_tecnica WHERE codigo_cloudify = :codigo");
    $stmt->execute([':codigo' => $codigo_prato]);
    $nome_intranet = $stmt->fetchColumn() ?: '-';

    $stmt2 = $pdo_dw->prepare("SELECT DISTINCT `Produto` FROM insumos_bastards WHERE `Cód. ref.` = :codigo");
    $stmt2->execute([':codigo' => $codigo_prato]);
    $nome_cloudify = $stmt2->fetchColumn() ?: '-';

    $sql_intranet = "SELECT codigo AS codigo_insumo, descricao AS nome_insumo, unidade, quantidade
                     FROM ingredientes
                     WHERE ficha_id = (
                         SELECT id FROM ficha_tecnica WHERE codigo_cloudify = :codigo
                     )";
    $stmt = $pdo->prepare($sql_intranet);
    $stmt->execute([':codigo' => $codigo_prato]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ficha_intranet[$row['codigo_insumo']] = $row;
    }

    $sql_cloud = "SELECT `Cód. ref..1` AS codigo_insumo, `Insumo` AS nome_insumo, `Und.` AS unidade, `Qtde.` AS quantidade
                  FROM insumos_bastards
                  WHERE `Cód. ref.` = :codigo";
    $stmt2 = $pdo_dw->prepare($sql_cloud);
    $stmt2->execute([':codigo' => $codigo_prato]);
    foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ficha_cloudify[$row['codigo_insumo']] = $row;
    }

    $todos_codigos = array_unique(array_merge(
        array_keys($ficha_intranet),
        array_keys($ficha_cloudify)
    ));

    if (empty($ficha_intranet) || empty($ficha_cloudify)) {
        $farol_status = 'red';
    } else {
        $diferencas = false;
        foreach ($todos_codigos as $cod) {
            $a = $ficha_intranet[$cod] ?? null;
            $b = $ficha_cloudify[$cod] ?? null;

            if ($a && $b) {
                if (
                    trim($a['nome_insumo']) !== trim($b['nome_insumo']) ||
                    trim($a['unidade']) !== trim($b['unidade']) ||
                    floatval($a['quantidade']) != floatval($b['quantidade'])
                ) {
                    $diferencas = true;
                    break;
                }
            } else {
                $diferencas = true;
                break;
            }
        }
        $farol_status = $diferencas ? 'yellow' : 'green';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Comparar Fichas Técnicas</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../style.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gray-900 text-white">
  <main class="ml-64 p-6">
    <div class="max-w-7xl mx-auto relative">
      <h1 class="text-3xl font-bold mb-6 text-yellow-400">Comparação de Fichas Técnicas</h1>

      <div class="absolute top-0 right-0 mt-4 mr-4">
        <?php
          $icone = match($farol_status) {
              'red' => '<i data-lucide="x-circle" class="text-red-500 w-7 h-7"></i>',
              'yellow' => '<i data-lucide="alert-circle" class="text-yellow-400 w-7 h-7"></i>',
              'green' => '<i data-lucide="check-circle" class="text-green-500 w-7 h-7"></i>',
              default => '<i data-lucide="circle" class="text-gray-500 w-7 h-7"></i>'
          };
          echo $icone;
        ?>
      </div>

      <form method="get" class="mb-6">
        <input type="text" name="cod" value="<?= htmlspecialchars($codigo_prato) ?>" placeholder="Digite o código do prato"
               class="w-full md:w-1/3 p-2 bg-gray-800 border border-gray-700 text-white rounded-md" required>
        <button type="submit" class="btn-acao mt-2 md:mt-0 md:ml-2">Comparar</button>
      </form>

      <?php if ($codigo_prato !== ''): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h2 class="text-xl font-semibold mb-1 text-yellow-400">Intranet</h2>
            <p class="mb-2 text-gray-300 font-medium">Prato: <?= htmlspecialchars($nome_intranet) ?></p>
            <table class="w-full text-sm bg-zinc-900 text-white shadow rounded-lg">
              <thead class="bg-gray-700">
                <tr>
                  <th class="px-3 py-2">Cód. Insumo</th>
                  <th class="px-3 py-2">Nome</th>
                  <th class="px-3 py-2">Und</th>
                  <th class="px-3 py-2">Qtde</th>
                </tr>
              </thead>
              <tbody  class="bg-gray-800 divide-y divide-gray-700">
                <?php foreach ($todos_codigos as $cod): $i = $ficha_intranet[$cod] ?? ['nome_insumo'=>'-', 'unidade'=>'-', 'quantidade'=>'-']; ?>
                  <tr class="hover:bg-gray-700 border-t border-neutral-800">
                    <td class="px-3 py-2"><?= $cod ?></td>
                    <td class="px-3 py-2 <?= isset($ficha_cloudify[$cod]) && $i['nome_insumo'] !== $ficha_cloudify[$cod]['nome_insumo'] ? 'bg-yellow-600' : '' ?>"><?= $i['nome_insumo'] ?></td>
                    <td class="px-3 py-2 <?= isset($ficha_cloudify[$cod]) && $i['unidade'] !== $ficha_cloudify[$cod]['unidade'] ? 'bg-yellow-600' : '' ?>"><?= $i['unidade'] ?></td>
                    <td class="px-3 py-2 <?= isset($ficha_cloudify[$cod]) && $i['quantidade'] != $ficha_cloudify[$cod]['quantidade'] ? 'bg-yellow-600' : '' ?>"><?= $i['quantidade'] ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div>
            <h2 class="text-xl font-semibold mb-1 text-yellow-400">Cloudify</h2>
            <p class="mb-2 text-gray-300 font-medium">Prato: <?= htmlspecialchars($nome_cloudify) ?></p>
            <table class="w-full text-sm bg-zinc-900 text-white shadow rounded-lg">
              <thead class="bg-gray-700">
                <tr>
                  <th class="px-3 py-2">Cód. Insumo</th>
                  <th class="px-3 py-2">Nome</th>
                  <th class="px-3 py-2">Und</th>
                  <th class="px-3 py-2">Qtde</th>
                </tr>
              </thead>
              <tbody class="bg-gray-800 divide-y divide-gray-700">
                <?php foreach ($todos_codigos as $cod): $c = $ficha_cloudify[$cod] ?? ['nome_insumo'=>'-', 'unidade'=>'-', 'quantidade'=>'-']; ?>
                  <tr class="hover:bg-gray-700 border-t border-neutral-800">
                    <td class="px-3 py-2"><?= $cod ?></td>
                    <td class="px-3 py-2 <?= isset($ficha_intranet[$cod]) && $c['nome_insumo'] !== $ficha_intranet[$cod]['nome_insumo'] ? 'bg-yellow-600' : '' ?>"><?= $c['nome_insumo'] ?></td>
                    <td class="px-3 py-2 <?= isset($ficha_intranet[$cod]) && $c['unidade'] !== $ficha_intranet[$cod]['unidade'] ? 'bg-yellow-600' : '' ?>"><?= $c['unidade'] ?></td>
                    <td class="px-3 py-2 <?= isset($ficha_intranet[$cod]) && $c['quantidade'] != $ficha_intranet[$cod]['quantidade'] ? 'bg-yellow-600' : '' ?>"><?= $c['quantidade'] ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>
  <script>
    lucide.createIcons();
  </script>
</body>
</html>
