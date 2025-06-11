<?php
declare(strict_types=1);

use Modules\Forms\Model\FormModel;
use Modules\Forms\Model\FieldModel;

require __DIR__ . '/../../config/db.php';       // expõe $pdo
require __DIR__ . '/Model/FormModel.php';
require __DIR__ . '/Model/FieldModel.php';
require __DIR__ . '/../../auth.php';


$formId = filter_input(INPUT_GET, 'form_id', FILTER_VALIDATE_INT);
if (!$formId) {
    http_response_code(400);
    exit('form_id é obrigatório');
}

// carrega dados do formulário
$formModel  = new FormModel($pdo);
$form       = $formModel->getById($formId);
if (!$form) {
    http_response_code(404);
    exit('Formulário não encontrado');
}

$fieldModel = new FieldModel($pdo);

// 1) ações POST: delete, move_up, move_down
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $fieldId = (int)($_POST['field_id'] ?? 0);

    if ($action === 'delete' && $fieldId) {
        $fieldModel->delete($fieldId);
    }

    if (($action === 'move_up' || $action === 'move_down') && $fieldId) {
        // busca vizinho
        $fields = $fieldModel->getByForm($formId);
        $index  = array_search($fieldId, array_column($fields, 'id'));
        if ($index !== false) {
            $offset = $action === 'move_up' ? -1 : +1;
            $neighborIndex = $index + $offset;
            if (isset($fields[$neighborIndex])) {
                $fieldModel->swapPositions(
                    $fieldId,
                    (int)$fields[$neighborIndex]['id']
                );
            }
        }
    }

    header("Location: builder.php?form_id={$formId}");
    exit;
}

// 2) busca campos para listar
$fields = $fieldModel->getByForm($formId);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Configurar Campos: <?= htmlspecialchars($form['title']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
<?php include __DIR__ . '/../../sidebar.php'; ?>

  <div class="max-w-4xl mx-auto bg-white p-6 rounded shadow">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">
        Campos do Formulário “<?= htmlspecialchars($form['title']) ?>”
      </h1>
      <div>
        <a href="list_forms.php" class="text-gray-600 hover:underline mr-4">← Voltar aos formulários</a>
        <a href="edit_field.php?form_id=<?= $formId ?>"
           class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
          + Novo Campo
        </a>
      </div>
    </div>

    <?php if (empty($fields)): ?>
      <div class="text-gray-600">Ainda não há campos para este formulário.</div>
    <?php else: ?>
      <table class="w-full table-auto border-collapse">
        <thead>
          <tr class="bg-gray-200">
            <th class="px-4 py-2 text-left">#</th>
            <th class="px-4 py-2 text-left">Label</th>
            <th class="px-4 py-2 text-left">Tipo</th>
            <th class="px-4 py-2 text-center">Obrig.</th>
            <th class="px-4 py-2 text-center">Ordem</th>
            <th class="px-4 py-2 text-left">Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($fields as $f): ?>
          <tr class="border-t">
            <td class="px-4 py-2"><?= $f['id'] ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($f['label']) ?></td>
            <td class="px-4 py-2"><?= $f['type'] ?></td>
            <td class="px-4 py-2 text-center"><?= $f['is_required'] ? '✅' : '' ?></td>
            <td class="px-4 py-2 text-center"><?= $f['position'] ?></td>
            <td class="px-4 py-2 space-x-2">
              <!-- Mover para cima -->
              <form method="post" class="inline">
                <input type="hidden" name="action" value="move_up">
                <input type="hidden" name="field_id" value="<?= $f['id'] ?>">
                <button title="Subir" type="submit">⬆️</button>
              </form>
              <!-- Mover para baixo -->
              <form method="post" class="inline">
                <input type="hidden" name="action" value="move_down">
                <input type="hidden" name="field_id" value="<?= $f['id'] ?>">
                <button title="Descer" type="submit">⬇️</button>
              </form>
              <!-- Editar -->
              <a href="edit_field.php?form_id=<?= $formId ?>&id=<?= $f['id'] ?>"
                 class="text-blue-600 hover:underline">Editar</a>
              <!-- Excluir -->
              <form method="post" class="inline"
                    onsubmit="return confirm('Excluir campo “<?= addslashes($f['label']) ?>”?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="field_id" value="<?= $f['id'] ?>">
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
