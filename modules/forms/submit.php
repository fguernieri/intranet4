<?php
declare(strict_types=1);

use Modules\Forms\Model\ResponseModel;
use Modules\Forms\Model\ResponseValueModel;

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../modules/forms/Model/ResponseModel.php';
require __DIR__ . '/../../modules/forms/Model/ResponseValueModel.php';

$pdo->beginTransaction();

// 1) Validar form_id
$formId = filter_input(INPUT_POST, 'form_id', FILTER_VALIDATE_INT);
if (!$formId) {
    http_response_code(400);
    exit('form_id inválido.');
}

// 2) Inserir resposta
$responseModel = new ResponseModel($pdo);
$responseId    = $responseModel->create($formId);

// 3) Inserir valores de cada campo
$valueModel = new ResponseValueModel($pdo);
foreach ($_POST as $key => $val) {
    if (strpos($key, 'field_') === 0) {
        $fieldId = (int) str_replace('field_', '', $key);
        // checkbox que não foi marcado virá sem key em $_POST,
        // mas como só iteramos as chaves existentes, OK.
        $valueModel->create($responseId, $fieldId, is_array($val) ? json_encode($val) : (string)$val);
    }
}

$pdo->commit();

// Redirecionar para “obrigado” ou mesmo para show.php com mensagem
header("Location: show.php?form_id={$formId}&submitted=1");
exit;
