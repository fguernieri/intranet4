<?php
// modules/forms/edit_form.php
declare(strict_types=1);

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../auth.php';


use Modules\Forms\Model\FormModel;

require __DIR__ . '/../../config/db.php';     // deve expor $pdo (PDO)
require __DIR__ . '/Model/FormModel.php';

$formModel = new FormModel($pdo);
$isEdit = isset($_GET['id']) && is_numeric($_GET['id']);
$form   = ['title' => '', 'description' => ''];

if ($isEdit) {
    $formData = $formModel->getById((int)$_GET['id']);
    if (!$formData) {
        http_response_code(404);
        exit('Formulário não encontrado');
    }
    $form = $formData;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // validação básica
    if ($title === '') {
        $error = 'O título é obrigatório.';
    } else {
        if ($isEdit) {
            $formModel->update((int)$_GET['id'], $title, $description);
        } else {
            $newId = $formModel->create($title, $description);
            header("Location: list_forms.php?created={$newId}");
            exit;
        }
        header("Location: list_forms.php?updated={$isEdit}");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title><?= $isEdit ? 'Editar' : 'Criar' ?> Formulário</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
<?php include __DIR__ . '/../../sidebar.php'; ?>

  <div class="max-w-lg mx-auto bg-white p-6 rounded shadow">
    <h1 class="text-2xl font-bold mb-4"><?= $isEdit ? 'Editar' : 'Criar' ?> Formulário</h1>

    <?php if (!empty($error)): ?>
      <div class="bg-red-100 text-red-700 p-2 mb-4 rounded"><?= $error ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="mb-4">
        <label class="block mb-1 font-medium">Título <span class="text-red-500">*</span></label>
        <input type="text" name="title"
               value="<?= htmlspecialchars($form['title']) ?>"
               required
               class="w-full border rounded px-3 py-2 focus:outline-none focus:ring">
      </div>

      <div class="mb-4">
        <label class="block mb-1 font-medium">Descrição</label>
        <textarea name="description" rows="4"
                  class="w-full border rounded px-3 py-2 focus:outline-none focus:ring"><?= htmlspecialchars($form['description']) ?></textarea>
      </div>

      <div class="flex justify-end">
        <a href="list_forms.php" class="mr-2 text-gray-600 hover:underline">Voltar</a>
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
          <?= $isEdit ? 'Salvar alterações' : 'Criar formulário' ?>
        </button>
      </div>
    </form>
  </div>
</body>
</html>
