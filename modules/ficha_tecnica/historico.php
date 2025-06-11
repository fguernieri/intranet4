<?php
require_once '../../config/db.php';
include '../../sidebar.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "ID inválido.";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM historico WHERE ficha_id = :id ORDER BY data_alteracao DESC");
$stmt->execute([':id' => $id]);
$historico = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Histórico de Alterações</title>
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
    <h1 class="text-3xl font-bold text-cyan-400 text-center mb-6">
      Histórico de Alterações
    </h1>

    <?php if (count($historico) > 0): ?>

      <!-- 📱 Cards para Mobile -->
      <div class="space-y-4 md:hidden">
        <?php foreach ($historico as $h): ?>
          <div class="bg-gray-800 p-4 rounded shadow-md">
            <div class="text-sm text-gray-300">
              <p><strong>Campo:</strong> <?= htmlspecialchars($h['campo_alterado']) ?></p>
              <p><strong>De:</strong> <?= htmlspecialchars($h['valor_antigo']) ?></p>
              <p><strong>Para:</strong> <?= htmlspecialchars($h['valor_novo']) ?></p>
              <p><strong>Responsável:</strong> <?= htmlspecialchars($h['usuario']) ?></p>
              <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($h['data_alteracao'])) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- 💻 Tabela para Desktop -->
      <div class="overflow-x-auto bg-gray-800 rounded shadow hidden md:block">
        <table class="min-w-full text-sm text-center">
          <thead class="bg-gray-700 text-cyan-300">
            <tr>
              <th class="p-3">Campo</th>
              <th class="p-3">Valor Antigo</th>
              <th class="p-3">Valor Novo</th>
              <th class="p-3">Responsável</th>
              <th class="p-3">Data</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-700">
            <?php foreach ($historico as $h): ?>
              <tr class="hover:bg-gray-700">
                <td class="p-2"><?= htmlspecialchars($h['campo_alterado']) ?></td>
                <td class="p-2"><?= htmlspecialchars($h['valor_antigo']) ?></td>
                <td class="p-2"><?= htmlspecialchars($h['valor_novo']) ?></td>
                <td class="p-2"><?= htmlspecialchars($h['usuario']) ?></td>
                <td class="p-2"><?= date('d/m/Y H:i', strtotime($h['data_alteracao'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php else: ?>
      <p class="text-center text-gray-400 mt-10">Nenhuma alteração registrada para esta ficha.</p>
    <?php endif; ?>
  </div>

</body>
</html>
