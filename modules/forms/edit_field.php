<?php
// modules/forms/edit_field.php

declare(strict_types=1);

use Modules\Forms\Model\FormModel;
use Modules\Forms\Model\FieldModel;

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/Model/FormModel.php';
require __DIR__ . '/Model/FieldModel.php';
require __DIR__ . '/../../auth.php';


$formId  = filter_input(INPUT_GET, 'form_id', FILTER_VALIDATE_INT) 
        ?? filter_input(INPUT_POST, 'form_id', FILTER_VALIDATE_INT);
$fieldId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? null;

if (!$formId) {
    http_response_code(400);
    exit('Parâmetro form_id obrigatório.');
}

$formModel  = new FormModel($pdo);
$fieldModel = new FieldModel($pdo);
$form       = $formModel->getById($formId);

if (!$form) {
    http_response_code(404);
    exit('Formulário não encontrado.');
}

$validTypes = ['text','textarea','radio','checkbox','date','number','select','scale','rating'];
$isEdit     = $fieldId !== null;

$fieldData = [
    'label'       => '',
    'type'        => 'text',
    'is_required' => 0,
    'options'     => [],
    'scale_min'   => '',
    'scale_max'   => '',
    'rating_max'  => 5,
];

if ($isEdit) {
    $field = $fieldModel->getById($fieldId);
    if (!$field) {
        http_response_code(404);
        exit('Campo não encontrado.');
    }

    $fieldData['label']       = $field['label'];
    $fieldData['type']        = $field['type'];
    $fieldData['is_required'] = (int)$field['is_required'];
    $cfg = json_decode($field['settings'] ?? '{}', true);

    if (in_array($fieldData['type'], ['select', 'radio', 'checkbox'], true)) {
        $fieldData['options'] = $cfg['options'] ?? [];
    } elseif ($fieldData['type'] === 'scale') {
        $fieldData['scale_min'] = $cfg['min'] ?? '';
        $fieldData['scale_max'] = $cfg['max'] ?? '';
    } elseif ($fieldData['type'] === 'rating') {
        $fieldData['rating_max'] = $cfg['max'] ?? 5;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $label      = trim($_POST['label'] ?? '');
    $type       = $_POST['type'] ?? 'text';
    $isRequired = isset($_POST['is_required']);
    $settings   = null;

    if ($label === '') {
        $error = 'O campo Label é obrigatório.';
    } elseif (!in_array($type, $validTypes, true)) {
        $error = 'Tipo inválido.';
    } else {
        if (in_array($type, ['select', 'radio', 'checkbox'], true)) {
            $raw = $_POST['options'] ?? [];
            $options = array_values(array_filter(array_map('trim', $raw)));
            $settings = ['options' => $options];
        } elseif ($type === 'scale') {
            $min = filter_input(INPUT_POST, 'scale_min', FILTER_VALIDATE_INT);
            $max = filter_input(INPUT_POST, 'scale_max', FILTER_VALIDATE_INT);
            if ($min === false || $max === false || $min >= $max) {
                $error = 'Escala inválida.';
            } else {
                $settings = ['min' => $min, 'max' => $max];
            }
        } elseif ($type === 'rating') {
            $max = filter_input(INPUT_POST, 'rating_max', FILTER_VALIDATE_INT);
            if ($max === false || $max < 1) {
                $error = 'Rating inválido.';
            } else {
                $settings = ['max' => $max];
            }
        }

        if (!$error) {
            if ($isEdit) {
                $fieldModel->update($fieldId, $label, $type, $isRequired, $settings);
            } else {
                $fieldModel->create($formId, $label, $type, $isRequired, $settings);
            }
            header("Location: builder.php?form_id={$formId}");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title><?= $isEdit ? 'Editar' : 'Novo' ?> Campo – <?= htmlspecialchars($form['title']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
<?php include __DIR__ . '/../../sidebar.php'; ?>

  <div class="max-w-lg mx-auto bg-white p-6 rounded shadow">
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold"><?= $isEdit ? 'Editar' : 'Novo' ?> Campo</h1>
      <a href="builder.php?form_id=<?= $formId ?>" class="text-gray-600">← Voltar</a>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-100 text-red-800 p-3 rounded mb-4"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="form_id" value="<?= $formId ?>">
      <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= $fieldId ?>">
      <?php endif; ?>

      <div class="mb-4">
        <label class="block mb-1">Label *</label>
        <input type="text" name="label" value="<?= htmlspecialchars($fieldData['label']) ?>" required class="w-full border rounded px-3 py-2">
      </div>

      <div class="mb-4">
        <label class="block mb-1">Tipo de campo</label>
        <select name="type" id="field-type" class="w-full border rounded px-3 py-2">
          <?php foreach ($validTypes as $t): ?>
            <option value="<?= $t ?>" <?= $fieldData['type'] === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-4">
        <label class="inline-flex items-center">
          <input type="checkbox" name="is_required" <?= $fieldData['is_required'] ? 'checked' : '' ?> class="form-checkbox">
          <span class="ml-2">Obrigatório</span>
        </label>
      </div>

      <div id="config-options" class="mb-4 hidden">
        <label class="block mb-1">Opções</label>
        <div id="options-container"></div>
        <button type="button" id="add-option" class="text-blue-600 hover:underline text-sm">+ Adicionar opção</button>
      </div>

      <div id="config-scale" class="mb-4 hidden">
        <label class="block mb-1">Escala</label>
        <div class="flex space-x-2">
          <input type="number" name="scale_min" placeholder="Mínimo" value="<?= htmlspecialchars((string)$fieldData['scale_min']) ?>" class="border rounded px-2 py-1 w-1/2">
          <input type="number" name="scale_max" placeholder="Máximo" value="<?= htmlspecialchars((string)$fieldData['scale_max']) ?>" class="border rounded px-2 py-1 w-1/2">
        </div>
      </div>

      <div id="config-rating" class="mb-4 hidden">
        <label class="block mb-1">Máximo de estrelas</label>
        <input type="number" name="rating_max" min="1" value="<?= htmlspecialchars((string)$fieldData['rating_max']) ?>" class="border rounded px-3 py-2 w-32">
      </div>

      <div class="flex justify-end space-x-2">
        <button type="button" onclick="location.href='builder.php?form_id=<?= $formId ?>'" class="px-4 py-2 border rounded">Cancelar</button>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded"><?= $isEdit ? 'Salvar' : 'Criar' ?></button>
      </div>
    </form>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const fieldType = document.getElementById('field-type');
      const optionsContainer = document.getElementById('options-container');
      const existingOptions = <?= json_encode($fieldData['options']) ?>;

      function refreshUI() {
        const type = fieldType.value;
        const isOptionField = ['select', 'radio', 'checkbox'].includes(type);
        document.getElementById('config-options').classList.toggle('hidden', !isOptionField);
        document.getElementById('config-scale').classList.toggle('hidden', type !== 'scale');
        document.getElementById('config-rating').classList.toggle('hidden', type !== 'rating');

        if (isOptionField && optionsContainer.children.length === 0 && existingOptions.length > 0) {
          existingOptions.forEach(opt => addOption(opt));
        }
      }

      function addOption(value = '') {
        const div = document.createElement('div');
        div.className = 'flex items-center mb-2';
        div.innerHTML = `
          <input type="text" name="options[]" value="${value}" class="flex-1 border rounded px-3 py-1 mr-2" placeholder="Texto da opção">
          <button type="button" class="remove-option text-red-500">✕</button>
        `;
        optionsContainer.appendChild(div);
        div.querySelector('.remove-option').addEventListener('click', () => div.remove());
      }

      document.getElementById('add-option').addEventListener('click', () => addOption());
      fieldType.addEventListener('change', refreshUI);
      refreshUI();
    });
  </script>
</body>
</html>
