<?php
// public/forms/show.php

declare(strict_types=1);

use Modules\Forms\Model\FormModel;
use Modules\Forms\Model\FieldModel;

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../modules/forms/Model/FormModel.php';
require __DIR__ . '/../../modules/forms/Model/FieldModel.php';

// 1) Validar form_id
$formId = filter_input(INPUT_GET, 'form_id', FILTER_VALIDATE_INT);
if (!$formId) {
    http_response_code(400);
    exit('form_id é obrigatório.');
}

// 2) Carregar form e campos
$formModel  = new FormModel($pdo);
$form       = $formModel->getById($formId);
if (!$form) {
    http_response_code(404);
    exit('Formulário não encontrado.');
}

$fieldModel = new FieldModel($pdo);
$fields     = $fieldModel->getByForm($formId);

// 3) Checar se foi enviado
$submitted = isset($_GET['submitted']) && $_GET['submitted'] == 1;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($form['title']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .rating .star { color: #ddd; transition: color .2s; }
    .rating input:checked ~ .star,
    .rating .star:hover,
    .rating .star:hover ~ .star { color: #f6b01e; }
  </style>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#309898',
            warning: '#FF9F00',
            alert: '#F4631E',
            danger: '#CB0404'
          }
        }
      }
    }
  </script>
</head>
<body class="bg-primary bg-opacity-70 min-h-screen flex items-center justify-center p-4">
  <div class="p-6 space-y-6 max-w-3xl w-full bg-white rounded-2xl shadow-md border border-primary">
    <h1 class="text-2xl font-bold text-primary text-center"><?= htmlspecialchars($form['title']) ?></h1>
    <?php if ($form['description']): ?>
      <p class="text-gray-700 mb-6"><?= nl2br(htmlspecialchars($form['description'])) ?></p>
    <?php endif; ?>

    <?php if ($submitted): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
        ✅ Formulário enviado com sucesso!
      </div>
    <?php else: ?>
      <form action="submit.php" method="post">
        <input type="hidden" name="form_id" value="<?= $formId ?>">
        <?php foreach ($fields as $f):
          $name = "field_{$f['id']}";
          $req  = $f['is_required'] ? 'required' : '';
          $cfg  = $f['settings'] ? json_decode($f['settings'], true) : [];
        ?>
          <div class="mb-4">
            <label class="block font-semibold text-warning mb-1">
              <?= htmlspecialchars($f['label']) ?> <?= $req ? '<span class="text-red-500">*</span>' : '' ?>
            </label>

            <?php switch ($f['type']):
              case 'text': ?>
                <input type="text" name="<?= $name ?>" <?= $req ?> class="w-full border rounded px-3 py-2">
              <?php break;

              case 'textarea': ?>
                <textarea name="<?= $name ?>" <?= $req ?> rows="4" class="w-full border rounded px-3 py-2"></textarea>
              <?php break;

              case 'number': ?>
                <input type="number" name="<?= $name ?>" <?= $req ?> class="w-full border rounded px-3 py-2">
              <?php break;

              case 'date': ?>
                <input type="date" name="<?= $name ?>" <?= $req ?> class="w-full border rounded px-3 py-2">
              <?php break;

              case 'checkbox': ?>
                <input type="checkbox" name="<?= $name ?>" value="1" <?= $req ?>>
              <?php break;

              case 'select': ?>
                <select name="<?= $name ?>" <?= $req ?> class="w-full border rounded px-3 py-2">
                  <option value="">-- selecione --</option>
                  <?php foreach ($cfg['options'] ?? [] as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php break;

              case 'radio': ?>
                <div class="space-y-1">
                  <?php foreach ($cfg['options'] ?? [] as $opt): ?>
                    <label class="inline-flex items-center">
                      <input type="radio" name="<?= $name ?>" value="<?= htmlspecialchars($opt) ?>" <?= $req ?>>
                      <span class="ml-2"><?= htmlspecialchars($opt) ?></span>
                    </label><br>
                  <?php endforeach; ?>
                </div>
              <?php break;

              case 'scale': ?>
                <input type="range" name="<?= $name ?>" <?= $req ?> min="<?= $cfg['min'] ?>" max="<?= $cfg['max'] ?>" class="w-full">
                <div class="text-sm text-gray-600">de <?= $cfg['min'] ?> até <?= $cfg['max'] ?></div>
              <?php break;

              case 'rating':
                $max = $cfg['max'] ?? 5;
                echo '<div class="rating inline-flex flex-row-reverse justify-end">';
                for ($i = $max; $i >= 1; $i--) {
                    $id = "{$name}_{$i}";
                    $isRequired = $i === 1 ? $req : '';
                    printf(
                        '<input type="radio" id="%s" name="%s" value="%d" %s class="hidden">', 
                        $id, $name, $i, $isRequired
                    );
                    printf(
                        '<label for="%s" title="%d estrelas" class="cursor-pointer text-3xl star">&#9733;</label>',
                        $id, $i
                    );
                }
                echo '</div>';
              break;

            endswitch; ?>
          </div>
        <?php endforeach; ?>

        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
          Enviar
        </button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
