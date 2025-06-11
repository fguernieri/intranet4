<?php
declare(strict_types=1);

require __DIR__ . '/../../auth.php';
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/Model/FormModel.php';

use Modules\Forms\Model\FormModel;

// 1) Handle POST actions: adicionar, excluir destinatário, salvar template, enviar teste
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

    // 1.1) Adicionar ou atualizar destinatário
    if ($action === 'save_recipient') {
        $chatId      = filter_input(INPUT_POST, 'chat_id', FILTER_SANITIZE_NUMBER_INT);
        $description = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING));
        $assigned    = filter_input(INPUT_POST, 'forms', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?: [];

        if ($chatId && $description !== '') {
            // Verifica se já existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM telegram_recipients WHERE chat_id = :chat_id");
            $stmt->execute([':chat_id' => $chatId]);
            $exists = (bool)$stmt->fetchColumn();

            if ($exists) {
                // Atualiza descrição
                $upd = $pdo->prepare(
                    "UPDATE telegram_recipients
                     SET description = :desc
                     WHERE chat_id = :chat_id"
                );
                $upd->execute([
                    ':desc'    => $description,
                    ':chat_id' => $chatId
                ]);
            } else {
                // Insere novo destinatário
                $ins = $pdo->prepare(
                    "INSERT INTO telegram_recipients (chat_id, description)
                     VALUES (:chat_id, :desc)"
                );
                $ins->execute([
                    ':chat_id' => $chatId,
                    ':desc'    => $description
                ]);
            }

            // Remove atribuições antigas e insere as novas
            $delForms = $pdo->prepare(
                "DELETE FROM telegram_recipient_forms
                 WHERE chat_id = :chat_id"
            );
            $delForms->execute([':chat_id' => $chatId]);

            $insAssign = $pdo->prepare(
                "INSERT INTO telegram_recipient_forms (chat_id, form_id)
                 VALUES (:chat_id, :form_id)"
            );
            foreach ($assigned as $fid) {
                $insAssign->execute([
                    ':chat_id' => $chatId,
                    ':form_id' => (int)$fid
                ]);
            }
        }

    // 1.2) Excluir destinatário
    } elseif ($action === 'delete_recipient') {
        $chatId = filter_input(INPUT_POST, 'chat_id', FILTER_SANITIZE_NUMBER_INT);
        if ($chatId) {
            // ON DELETE CASCADE cuida das assignments
            $del = $pdo->prepare(
                "DELETE FROM telegram_recipients
                 WHERE chat_id = :chat_id"
            );
            $del->execute([':chat_id' => $chatId]);
        }

    // 1.3) Salvar template de mensagem para um formulário
    } elseif ($action === 'save_template') {
        $formId       = filter_input(INPUT_POST, 'form_id', FILTER_VALIDATE_INT);
        $templateHtml = trim($_POST['template_html'] ?? '');
        if ($formId && $templateHtml !== '') {
            // Verifica se já há template
            $stmtTpl = $pdo->prepare(
                "SELECT COUNT(*) FROM telegram_templates
                 WHERE form_id = :form_id"
            );
            $stmtTpl->execute([':form_id' => $formId]);
            $existsTpl = (bool)$stmtTpl->fetchColumn();

            if ($existsTpl) {
                $updTpl = $pdo->prepare(
                    "UPDATE telegram_templates
                     SET template_html = :tpl
                     WHERE form_id = :form_id"
                );
                $updTpl->execute([
                    ':tpl'     => $templateHtml,
                    ':form_id' => $formId
                ]);
            } else {
                $insTpl = $pdo->prepare(
                    "INSERT INTO telegram_templates (form_id, template_html)
                     VALUES (:form_id, :tpl)"
                );
                $insTpl->execute([
                    ':form_id' => $formId,
                    ':tpl'     => $templateHtml
                ]);
            }
        }

} elseif ($action === 'send_test') {
    $formId = filter_input(INPUT_POST, 'form_id', FILTER_VALIDATE_INT);
    if ($formId) {
        // 1) Busca última resposta
        $respStmt = $pdo->prepare(
            "SELECT id AS response_id, submitted_at, employee_id
             FROM form_responses
             WHERE form_id = :form_id
             ORDER BY id DESC LIMIT 1"
        );
        $respStmt->execute([':form_id' => $formId]);
        $lastResp = $respStmt->fetch(PDO::FETCH_ASSOC);

        if ($lastResp) {
            // 2) Busca campos e valores dessa resposta
            $valsStmt = $pdo->prepare(
                "SELECT f.label, rv.value
                 FROM form_response_values rv
                 JOIN form_fields f ON f.id = rv.field_id
                 WHERE rv.response_id = :response_id
                 ORDER BY f.position"
            );
            $valsStmt->execute([':response_id' => $lastResp['response_id']]);
            $fields = $valsStmt->fetchAll(PDO::FETCH_ASSOC);

            // 3) Monta array associativo [label => value]
            $assoc = [];
            foreach ($fields as $fld) {
                // converte sempre para string
                $assoc[(string)$fld['label']] = (string)$fld['value'];
            }
            $assoc['response_id']  = (string)$lastResp['response_id'];
            $assoc['submitted_at'] = (string)$lastResp['submitted_at'];
            $assoc['employee_id']  = (string)($lastResp['employee_id'] ?? '-');

            // 4) Carrega template Markdown do banco
            $tplStmt = $pdo->prepare(
                "SELECT template_html
                 FROM telegram_templates
                 WHERE form_id = :form_id"
            );
            $tplStmt->execute([':form_id' => $formId]);
            $tplRow = $tplStmt->fetch(PDO::FETCH_ASSOC);
            $templateMd = $tplRow['template_html'] ?? '';

            // 5) Explode por "\n" para obter cada linha
            $linhas = explode("\n", $templateMd);
            $saida  = [];

            foreach ($linhas as $linha) {
                // 5.1) Encontra todos os placeholders {Label} na linha
                preg_match_all('/\{([^}]+)\}/', $linha, $matches);
                $placeholders = $matches[1]; // lista de labels

                $incluir = true;
                $novaLinha = $linha;

                foreach ($placeholders as $label) {
                    // Se não existe ou é string vazia, descarta a linha
                    if (!isset($assoc[$label]) || $assoc[$label] === '') {
                        $incluir = false;
                        break;
                    }
                    // Caso exista valor, substitui
                    $valor = $assoc[$label];
                    $novaLinha = str_replace('{' . $label . '}', $valor, $novaLinha);
                }

                if ($incluir) {
                    $saida[] = $novaLinha;
                }
            }

            // 6) Junta as linhas válidas com "\n"
            $texto = implode("\n", $saida);

            // 7) Busca destinatários e envia
            $destStmt = $pdo->prepare(
                "SELECT chat_id FROM telegram_recipient_forms WHERE form_id = :form_id"
            );
            $destStmt->execute([':form_id' => $formId]);
            $destRows = $destStmt->fetchAll(PDO::FETCH_COLUMN, 0);

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
        }
    }
}

    // Redireciona para evitar re-submissão
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// 2) Carrega dados para exibir no page load

// 2.1) Lista de formulários
$formModel = new FormModel($pdo);
$forms     = $formModel->getAll();

// 2.2) Destinatários existentes
$recStmt = $pdo->query("SELECT chat_id, description FROM telegram_recipients ORDER BY description");
$recipients = $recStmt->fetchAll(PDO::FETCH_ASSOC);

// 2.3) Atribuições form ↔ destinatário
$assignStmt = $pdo->query("SELECT chat_id, form_id FROM telegram_recipient_forms");
$rawAssign = $assignStmt->fetchAll(PDO::FETCH_ASSOC);
$assignMap = [];
foreach ($rawAssign as $row) {
    $assignMap[$row['chat_id']][] = (int)$row['form_id'];
}

// 2.4) Templates de mensagem por formulário
$tplStmt = $pdo->query("SELECT form_id, template_html FROM telegram_templates");
$rawTpl = $tplStmt->fetchAll(PDO::FETCH_ASSOC);
$templateMap = [];
foreach ($rawTpl as $row) {
    $templateMap[$row['form_id']] = $row['template_html'];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Configuração – Envio via Telegram</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-100 mt-12 mb-8 flex">
<?php include __DIR__ . '/../../sidebar.php'; ?>

  <main class="p-6 md:ml-64">
    <h1 class="text-3xl font-bold mb-6">Configuração de Envio via Telegram</h1>

    <!-- 3) Seção 1: Gerenciar Destinatários -->
    <section class="mb-10">
      <h2 class="text-2xl font-semibold mb-4">Destinatários</h2>
      <div class="bg-white shadow rounded-lg p-4 mb-6">
        <!-- Formulário para adicionar/editar destinatário -->
        <form method="POST" class="space-y-4">
          <input type="hidden" name="action" value="save_recipient" />
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-gray-700 mb-1">Chat ID 📱</label>
              <input type="text" name="chat_id" class="w-full p-2 border border-gray-300 rounded" placeholder="Ex: 123456789 ou -100987654321" required />
            </div>
            <div>
              <label class="block text-gray-700 mb-1">Descrição ✏️</label>
              <input type="text" name="description" class="w-full p-2 border border-gray-300 rounded" placeholder="Ex: Xico pessoal, Grupo Cozinha" required />
            </div>
            <div>
              <label class="block text-gray-700 mb-1">Formulários 🎯</label>
              <select name="forms[]" multiple class="w-full p-2 border border-gray-300 rounded h-32">
                <?php foreach ($forms as $f): ?>
                  <option value="<?= $f['id'] ?>"><?php echo htmlspecialchars($f['title'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
              <p class="text-sm text-gray-500 mt-1">Segure <kbd class="px-1 bg-gray-200 rounded">Ctrl</kbd> (ou ⌘) para selecionar vários.</p>
            </div>
          </div>
          <button type="submit" class="mt-4 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Salvar Destinatário</button>
        </form>
      </div>

      <!-- Lista de destinatários existentes -->
      <div class="bg-white shadow rounded-lg p-4">
        <h3 class="text-xl font-medium mb-3">Destinatários Cadastrados</h3>
        <?php if (empty($recipients)): ?>
          <p class="text-gray-600">Nenhum destinatário cadastrado.</p>
        <?php else: ?>
          <table class="min-w-full bg-white">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left">Descrição</th>
                <th class="px-4 py-2 text-left">Chat ID</th>
                <th class="px-4 py-2 text-left">Formulários</th>
                <th class="px-4 py-2 text-left">Ações</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <?php foreach ($recipients as $rec): 
                $cid = (int)$rec['chat_id'];
                $assignedList = $assignMap[$cid] ?? [];
              ?>
                <tr>
                  <td class="px-4 py-2"><?php echo htmlspecialchars($rec['description'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="px-4 py-2"><?php echo $cid; ?></td>
                  <td class="px-4 py-2">
                    <?php if (empty($assignedList)): ?>
                      <span class="text-gray-500">–</span>
                    <?php else: ?>
                      <ul class="list-disc pl-5 space-y-1">
                        <?php foreach ($assignedList as $fid): 
                          foreach ($forms as $f) {
                            if ($f['id'] === $fid) {
                              echo '<li>' . htmlspecialchars($f['title'], ENT_QUOTES, 'UTF-8') . '</li>';
                              break;
                            }
                          }
                        endforeach; ?>
                      </ul>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-2">
                    <form method="POST" class="inline" onsubmit="return confirm('Excluir destinatário “<?php echo addslashes(htmlspecialchars($rec['description'], ENT_QUOTES)); ?>”?');">
                      <input type="hidden" name="action" value="delete_recipient" />
                      <input type="hidden" name="chat_id" value="<?php echo $cid; ?>" />
                      <button type="submit" class="text-red-600 hover:underline ml-2">Excluir</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </section>

    <!-- 4) Seção 2: Modelos de Mensagem por Formulário -->
    <section>
      <h2 class="text-2xl font-semibold mb-4">Modelos de Mensagem</h2>
      <?php if (empty($forms)): ?>
        <p class="text-gray-600">Nenhum formulário cadastrado.</p>
      <?php else: ?>
        <div class="space-y-8">
          <?php foreach ($forms as $f):
            $fid      = (int)$f['id'];
            $title    = $f['title'];
            $tplValue = $templateMap[$fid] ?? '';
          ?>
            <div class="bg-white shadow rounded-lg p-4">
              <h3 class="text-xl font-medium mb-2">🚀 <?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h3>
              <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="save_template" />
                <input type="hidden" name="form_id" value="<?php echo $fid; ?>" />

                <label class="block text-gray-700 mb-1">
                  Modelo de mensagem (HTML) para este formulário:
                </label>
                <textarea name="template_html"
                          class="w-full p-2 border border-gray-300 rounded h-40 font-mono text-sm"
                          placeholder="Use HTML e placeholders como {label} e {value}…"><?php echo htmlspecialchars($tplValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <div class="flex space-x-2">
                  <button type="submit"
                          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    Salvar Modelo
                  </button>
                  <button type="submit" name="action" value="send_test" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded">
                    Enviar Teste
                  </button>
                </div>
                <p class="text-sm text-gray-500">
                  Você pode usar variáveis:<br>
                  <code class="bg-gray-100 px-1 rounded">{response_id}</code>,
                  <code class="bg-gray-100 px-1 rounded">{submitted_at}</code>,
                  <code class="bg-gray-100 px-1 rounded">{employee_id}</code>,
                  <code class="bg-gray-100 px-1 rounded">{fields_html}</code>.
                </p>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
