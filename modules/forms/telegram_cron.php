#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/Model/FormModel.php';

use Modules\Forms\Model\FormModel;

// 1) Obtenha todos os formulários
$formModel = new FormModel($pdo);
$forms     = $formModel->getAll();

foreach ($forms as $f) {
    $formId = (int)$f['id'];

    // 2) Busque o last_sent_response para este form_id (ou crie um registro se não existir)
    $stateStmt = $pdo->prepare(
        "SELECT last_sent_response 
           FROM automation_state 
          WHERE form_id = :form_id"
    );
    $stateStmt->execute([':form_id' => $formId]);
    $row = $stateStmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $lastSent = (int)$row['last_sent_response'];
    } else {
        // Se não existe, inicializamos com zero (nenhuma resposta ainda enviada)
        $lastSent = 0;
        $initStmt = $pdo->prepare(
            "INSERT INTO automation_state (form_id, last_sent_response) 
             VALUES (:form_id, 0)"
        );
        $initStmt->execute([':form_id' => $formId]);
    }

    // 3) Agora busque todas as respostas NOVAS (id > lastSent), em ordem crescente
    $respStmt = $pdo->prepare(
        "SELECT id AS response_id, submitted_at, employee_id
           FROM form_responses
          WHERE form_id = :form_id
            AND id > :last_sent
          ORDER BY id ASC"
    );
    $respStmt->execute([
        ':form_id'   => $formId,
        ':last_sent' => $lastSent
    ]);

    $newResponses = $respStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($newResponses)) {
        // não há novas respostas, passe para o próximo form
        continue;
    }

    // 4) Para cada nova resposta, envie ao Telegram e depois atualize last_sent_response
    foreach ($newResponses as $resp) {
        $responseId = (int)$resp['response_id'];

        // – Busque campos e valores dessa resposta
        $valsStmt = $pdo->prepare(
            "SELECT f.label, rv.value
               FROM form_response_values rv
               JOIN form_fields f ON f.id = rv.field_id
              WHERE rv.response_id = :response_id
              ORDER BY f.position"
        );
        $valsStmt->execute([':response_id' => $responseId]);
        $fields = $valsStmt->fetchAll(PDO::FETCH_ASSOC);

        // – Monte [$label => $value]
        $assoc = [];
        foreach ($fields as $fld) {
            $assoc[(string)$fld['label']] = (string)$fld['value'];
        }
        $assoc['response_id']  = (string)$resp['response_id'];
        $assoc['submitted_at'] = (string)$resp['submitted_at'];
        $assoc['employee_id']  = (string)($resp['employee_id'] ?? '-');

        // – Carregue o template Markdown (já salvo em telegram_templates)
        $tplStmt = $pdo->prepare(
            "SELECT template_html
               FROM telegram_templates
              WHERE form_id = :form_id"
        );
        $tplStmt->execute([':form_id' => $formId]);
        $tplRow = $tplStmt->fetch(PDO::FETCH_ASSOC);
        $templateMd = $tplRow['template_html'] ?? '';

        // – Monte a mensagem (reutilize exatamente a lógica de filtragem/substituição)
        $linhas = explode("\n", $templateMd);
        $saida  = [];
        foreach ($linhas as $linha) {
            preg_match_all('/\{([^}]+)\}/', $linha, $matches);
            $placeholders = $matches[1];

            $novaLinha = $linha;
            $incluir   = true;

            foreach ($placeholders as $label) {
                $valor = (string)($assoc[$label] ?? '');
                if ($valor === '') {
                    $incluir = false; // se quiser descartar quando vazio
                    break;
                }
                $novaLinha = str_replace('{' . $label . '}', $valor, $novaLinha);
            }

            if ($incluir) {
                $saida[] = $novaLinha;
            }
        }
        $texto = implode("\n", $saida);

        // – Busque destinatários
        $destStmt = $pdo->prepare(
            "SELECT chat_id 
               FROM telegram_recipient_forms 
              WHERE form_id = :form_id"
        );
        $destStmt->execute([':form_id' => $formId]);
        $destRows = $destStmt->fetchAll(PDO::FETCH_COLUMN, 0);

        // – Envie ao Telegram
        $telegramToken  = '8013231460:AAEhGNGKvHmZz4F_Zc-krqmtogdhX8XR3Bk';
        $telegramApiUrl = "https://api.telegram.org/bot{$telegramToken}/sendMessage";
        foreach ($destRows as $chatId) {
            $params = [
                'chat_id'    => $chatId,
                'text'       => $texto,
                'parse_mode' => 'Markdown'
            ];
            $ch = curl_init($telegramApiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        }

        // – Depois de enviar cada resposta, MARQUE o último ID enviado
        $updState = $pdo->prepare(
            "UPDATE automation_state
                SET last_sent_response = :response_id
              WHERE form_id = :form_id"
        );
        $updState->execute([
            ':response_id' => $responseId,
            ':form_id'     => $formId
        ]);
    }
}
