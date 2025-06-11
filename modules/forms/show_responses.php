<?php
declare(strict_types=1);

require __DIR__ . '/../../auth.php';
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/Model/FormModel.php';
require __DIR__ . '/Model/FieldModel.php';

use Modules\Forms\Model\FormModel;
use Modules\Forms\Model\FieldModel;

// 1) Captura e validação do form_id
$formId = filter_input(INPUT_GET, 'form_id', FILTER_VALIDATE_INT);
if (!$formId) {
    http_response_code(400);
    exit('form_id inválido.');
}

// 2) Carrega dados do formulário
$formModel = new FormModel($pdo);
$form = $formModel->getById($formId);
if (!$form) {
    http_response_code(404);
    exit('Formulário não encontrado.');
}

// 3) Carrega campos do formulário, ordenados pela posição
$fieldsStmt = $pdo->prepare('
    SELECT id, label, type
    FROM form_fields
    WHERE form_id = :form_id
    ORDER BY position
');
$fieldsStmt->execute([':form_id' => $formId]);
$fields = $fieldsStmt->fetchAll(PDO::FETCH_ASSOC);

// 4) Carrega respostas (metadados) do formulário
$responsesStmt = $pdo->prepare('
    SELECT id, employee_id, submitted_at
    FROM form_responses
    WHERE form_id = :form_id
    ORDER BY submitted_at DESC
');
$responsesStmt->execute([':form_id' => $formId]);
$responses = $responsesStmt->fetchAll(PDO::FETCH_ASSOC);

// 5) Carrega todos os valores de resposta de uma só vez
$valsStmt = $pdo->prepare('
    SELECT rv.response_id, rv.field_id, rv.value
    FROM form_response_values AS rv
    JOIN form_responses AS r ON rv.response_id = r.id
    WHERE r.form_id = :form_id
');
$valsStmt->execute([':form_id' => $formId]);
$allValues = $valsStmt->fetchAll(PDO::FETCH_ASSOC);

// 6) Monta matriz [response_id][field_id] => value
$valuesByResponse = [];
foreach ($allValues as $row) {
    $rid = (int)$row['response_id'];
    $fid = (int)$row['field_id'];
    $valuesByResponse[$rid][$fid] = $row['value'];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Respostas – <?= htmlspecialchars($form['title'], ENT_QUOTES, 'UTF-8') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 mt-12 mb-8 flex">
<?php include __DIR__ . '/../../sidebar.php'; ?>

  <main class= "px-6">
    <h1 class="text-2xl font-bold mb-4"><?= htmlspecialchars($form['title'], ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="overflow-x-auto">
      <table class="min-w-full bg-white text-gray-900">
        <thead class="bg-gray-200">
          <tr>
            <th class="px-4 py-2">#</th>
            <th class="px-4 py-2">Data de envio</th>
            <th class="px-4 py-2">Funcionário</th>
            <?php foreach ($fields as $f): ?>
              <th class="px-4 py-2"><?= htmlspecialchars($f['label'], ENT_QUOTES, 'UTF-8') ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-300 text-sm">
          <?php foreach ($responses as $idx => $resp): ?>
            <tr>
              <td class="px-4 py-2"><?= $idx + 1 ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($resp['submitted_at'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="px-4 py-2">
                <?= ($resp['employee_id'] !== null) 
                      ? htmlspecialchars((string)$resp['employee_id'], ENT_QUOTES, 'UTF-8') 
                      : '-' 
                ?>
              </td>
              <?php foreach ($fields as $f): ?>
                <?php
                  $rid = (int)$resp['id'];
                  $fid = (int)$f['id'];
                  $valor = $valuesByResponse[$rid][$fid] ?? '';
                ?>
                <td class="px-4 py-2"><?= htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8') ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>
