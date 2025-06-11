<?php
require_once '../../config/db.php';
include '../../sidebar.php';

$filtro = $_GET['filtro'] ?? '';

$stmt = $pdo->prepare("
  SELECT h.*, f.nome_prato 
  FROM historico h
  JOIN ficha_tecnica f ON f.id = h.ficha_id
  WHERE f.nome_prato LIKE :filtro
  ORDER BY h.data_alteracao DESC
");
$stmt->execute([':filtro' => "%$filtro%"]);
$alteracoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Consulta de Alterações</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex">

  <div class="max-w-6xl mx-auto">
    <!-- Botão Voltar -->
    <div class="mb-6">
      <a href="consulta.php"
         class="inline-block bg-gray-700 hover:bg-gray-600 text-white px-6 py-3 rounded shadow no-underline min-w-[170px] text-center font-semibold">
        ⬅️ Voltar para Consulta
      </a>
    </div>

    <!-- Título -->
    <h1 class="text-3xl font-bold text-cyan-400 text-center mb-8">
      Histórico de Alterações
    </h1>

    <!-- Campo de Filtro -->
    <form method="GET" class="flex flex-col md:flex-row items-center justify-center gap-4 mb-8">
      <input type="text" name="filtro" value="<?= htmlspecialchars($filtro) ?>"
             placeholder="Filtrar por nome do prato..."
             class="w-full md:w-1/2 p-3 rounded bg-gray-800 border border-gray-700 focus:outline-none focus:ring-2 focus:ring-cyan-500">
      <button type="submit"
              class="bg-cyan-500 hover:bg-cyan-600 text-white font-semibold px-6 py-3 rounded shadow min-w-[170px]">
        Buscar
      </button>
    </form>

    <?php if (count($alteracoes) > 0): ?>

      <!-- 📱 Cards Mobile -->
      <div class="space-y-4 md:hidden">
        <?php foreach ($alteracoes as $alt): ?>
          <div class="bg-gray-800 p-4 rounded shadow-md">
            <h2 class="text-cyan-400 font-semibold mb-1"><?= htmlspecialchars($alt['nome_prato']) ?></h2>
            <div class="text-sm text-gray-300">
              <p><strong>Campo:</strong> <?= htmlspecialchars($alt['campo_alterado']) ?></p>
              <p><strong>De:</strong> <?= htmlspecialchars($alt['valor_antigo']) ?></p>
              <p><strong>Para:</strong> <?= htmlspecialchars($alt['valor_novo']) ?></p>
              <p><strong>Usuário:</strong> <?= htmlspecialchars($alt['usuario']) ?></p>
              <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($alt['data_alteracao'])) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- 💻 Tabela Desktop -->
      <div class="overflow-x-auto bg-gray-800 rounded shadow hidden md:block">
        <table class="min-w-full table-fixed text-sm text-center">
          <thead class="bg-gray-700 text-cyan-300">
            <tr>
              <th class="p-3 w-1/6">Prato</th>
              <th class="p-3 w-1/6">Campo</th>
              <th class="p-3 w-1/4">Valor Antigo</th>
              <th class="p-3 w-1/4">Valor Novo</th>
              <th class="p-3 w-1/6">Usuário</th>
              <th class="p-3 w-1/6">Data</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-700 text-gray-200">
            <?php foreach ($alteracoes as $alt): ?>
              <tr class="hover:bg-gray-700">
                <td class="p-2 break-words"><?= htmlspecialchars($alt['nome_prato']) ?></td>
                <td class="p-2 break-words"><?= htmlspecialchars($alt['campo_alterado']) ?></td>
                <td class="p-2 whitespace-pre-wrap break-words max-w-[250px]"><?= htmlspecialchars($alt['valor_antigo']) ?></td>
                <td class="p-2 whitespace-pre-wrap break-words max-w-[250px]"><?= htmlspecialchars($alt['valor_novo']) ?></td>
                <td class="p-2 break-words"><?= htmlspecialchars($alt['usuario']) ?></td>
                <td class="p-2"><?= date('d/m/Y H:i', strtotime($alt['data_alteracao'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php else: ?>
      <p class="text-center text-gray-400 mt-10">Nenhuma alteração encontrada.</p>
    <?php endif; ?>
  </div>

</body>
</html>
