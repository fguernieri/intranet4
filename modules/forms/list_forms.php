<?php
// modules/forms/list_forms.php
declare(strict_types=1);
include __DIR__ . '/../../sidebar.php';

use Modules\Forms\Model\FormModel;

require __DIR__ . '/../../config/db.php';      // expõe $pdo
require __DIR__ . '/Model/FormModel.php';

$formModel = new FormModel($pdo);

// 1. Tratar exclusão via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int) $_POST['delete_id'];
    $formModel->delete($deleteId);
    header('Location: list_forms.php?deleted=1');
    exit;
}

// 2. Buscar todos os formulários
$forms = $formModel->getAll();

// 3. Mensagens de alerta
$alerts = [];
if (isset($_GET['created']))  $alerts[] = 'Formulário criado com sucesso!';
if (isset($_GET['updated']))  $alerts[] = 'Formulário atualizado com sucesso!';
if (isset($_GET['deleted']))  $alerts[] = 'Formulário excluído com sucesso!';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Formulários</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 mt-12 mb-8 flex">
<?php include __DIR__ . '/../../sidebar.php'; ?>

  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-3xl font-bold">Formulários</h1>
            <a href="telegram_config.php" class="bg-blue-400 hover:bg-blue-700 text-white px-6 py-2 rounded">
        Telegram
      </a>

      <a href="edit_form.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
        + Novo formulário
      </a>
    </div>

    <?php foreach ($alerts as $msg): ?>
      <div class="bg-green-100 text-green-800 p-3 rounded mb-4">
        <?= htmlspecialchars($msg) ?>
      </div>
    <?php endforeach; ?>

    <?php if (empty($forms)): ?>
      <div class="text-gray-600">Nenhum formulário cadastrado.</div>
    <?php else: ?>
      <table class="min-w-full bg-white rounded shadow overflow-hidden">
        <thead class="bg-gray-200">
          <tr>
            <th class="px-4 py-2 text-left">ID</th>
            <th class="px-4 py-2 text-left">Título</th>
            <th class="px-4 py-2 text-left">Criado em</th>
            <th class="px-4 py-2 text-left">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($forms as $f): ?>
            <tr class="border-t">
              <td class="px-4 py-2"><?= $f['id'] ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($f['title']) ?></td>
              <td class="px-4 py-2">
                <?= (new DateTime($f['created_at']))->format('d/m/Y H:i') ?>
              </td>
              <td class="px-4 py-2 space-x-2">
                <a href="show.php?form_id=<?= $f['id'] ?>"
                   class="text-green-600 hover:underline">Visualizar</a>
                <a href="edit_form.php?id=<?= $f['id'] ?>"
                   class="text-blue-600 hover:underline">Editar</a>
                <a href="builder.php?form_id=<?= $f['id'] ?>"
                   class="text-cyan-600 hover:underline">Campos</a>
                <a href="show_responses.php?form_id=<?= $f['id'] ?>"
                   class="text-blue-600 hover:underline ml-2">Ver respostas</a>
                <form method="post" class="inline" onsubmit="return confirm('Excluir formulário “<?= addslashes($f['title']) ?>”?');">
                  <input type="hidden" name="delete_id" value="<?= $f['id'] ?>">
                  <button type="submit" class="text-red-600 hover:underline">Excluir</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
