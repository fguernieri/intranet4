<?php
declare(strict_types=1);

// 0) Inclusões obrigatórias
require __DIR__ . '/../../auth.php';
require __DIR__ . '/../../config/db.php';

// 0.1) Flash de sucesso do teste (utilizando sessão)
$flashTeste = '';
if (isset($_SESSION['sucesso_teste'])) {
    $flashTeste = $_SESSION['sucesso_teste'];
    unset($_SESSION['sucesso_teste']);
}

// Função para escapar caracteres especiais no Telegram Markdown
function escapeTelegramMarkdown(string $texto): string {
    $caracteres = ['\\', '_', '*', '`', '[', ']'];
    $escapados  = ['\\\\', '\\_', '\\*', '\\`', '\\[', '\\]'];
    return str_replace($caracteres, $escapados, $texto);
}

// 1) Mapeie suas quatro páginas de disponibilidade
$formKeys = [
    'disp_bdf_almoco'      => 'Disponibilidade BDF (Almoço)',
    'disp_bdf_almoco_fds'  => 'Disponibilidade BDF (Almoço - FDS)',
    'disp_bdf_noite'       => 'Disponibilidade BDF (Noite)',
    'disp_wab'             => 'Disponibilidade WAB'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $formKey = $_POST['form_key'] ?? '';

    // 2.1) Salvar ou atualizar o template Markdown
    if ($action === 'save_template' && isset($formKeys[$formKey])) {
        $raw = $_POST['template_md'][$formKey] ?? '';
        $md  = trim($raw);

        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
              FROM telegram_disp_templates 
             WHERE form_key = :form_key
        ");
        $stmt->execute([':form_key' => $formKey]);
        $exists = (bool)$stmt->fetchColumn();

        if ($exists) {
            $upd = $pdo->prepare("
                UPDATE telegram_disp_templates
                   SET template_md = :template_md
                 WHERE form_key = :form_key
            ");
            $upd->execute([
                ':template_md' => $md,
                ':form_key'    => $formKey
            ]);
        } else {
            $ins = $pdo->prepare("
                INSERT INTO telegram_disp_templates (form_key, template_md)
                VALUES (:form_key, :template_md)
            ");
            $ins->execute([
                ':form_key'    => $formKey,
                ':template_md' => $md
            ]);
        }

        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    // 2.2) Enviar Teste (puxa a última resposta e envia ao Telegram)
    if ($action === 'send_test' && isset($formKeys[$formKey])) {
        // Carrega o template Markdown salvo
        $tplStmt = $pdo->prepare("
            SELECT template_md
              FROM telegram_disp_templates
             WHERE form_key = :form_key
        ");
        $tplStmt->execute([':form_key' => $formKey]);
        $templateMd = $tplStmt->fetchColumn() ?: '';

        // Determina a tabela de respostas com base no form_key
        $respTable = match ($formKey) {
            'disp_bdf_almoco'     => 'disp_bdf_almoco',
            'disp_bdf_almoco_fds' => 'disp_bdf_almoco_fds',
            'disp_bdf_noite'      => 'disp_bdf_noite',
            'disp_wab'            => 'disp_wab',
            default               => ''
        };

        if ($respTable !== '') {
            $respStmt = $pdo->prepare("
                SELECT * 
                  FROM {$respTable} 
                 ORDER BY id DESC 
                 LIMIT 1
            ");
            $respStmt->execute();
            $lastResp = $respStmt->fetch(PDO::FETCH_ASSOC);

            if ($lastResp) {
                $dataEnvio       = $lastResp['data'];
                $usuario         = $lastResp['nome_usuario'];
                $comentarioGeral = trim((string)$lastResp['comentarios']);

                $allStmt = $pdo->prepare("
                    SELECT f.nome_prato, d.disponivel
                      FROM {$respTable} AS d
                      LEFT JOIN ficha_tecnica AS f 
                        ON f.codigo_cloudify = d.codigo_cloudify
                     WHERE d.data = :data_envio
                       AND d.nome_usuario = :usuario
                     ORDER BY d.id ASC
                ");
                $allStmt->execute([
                    ':data_envio' => $dataEnvio,
                    ':usuario'    => $usuario
                ]);
                $rows = $allStmt->fetchAll(PDO::FETCH_ASSOC);

                $assoc = [
                    'data'         => $dataEnvio,
                    'nome_usuario' => $usuario,
                    'comentarios'  => $comentarioGeral,
                ];

                $lista = [];
                foreach ($rows as $r) {
                    $nome = htmlspecialchars($r['nome_prato'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $disp = $r['disponivel'] ? '✅' : '❌';
                    $lista[] = "- {$nome} : {$disp}";
                }
                $assoc['lista_codigos'] = implode("\n", $lista);

                // Substitui placeholders no template
                $linhas = explode("\n", $templateMd);
                $saida  = [];

                foreach ($linhas as $linha) {
                    preg_match_all('/\{([^}]+)\}/', $linha, $matches);
                    $novaLinha = $linha;
                    $incluir   = true;

                    foreach ($matches[1] as $label) {
                        if (!isset($assoc[$label]) || trim($assoc[$label]) === '') {
                            $incluir = false;
                            break;
                        }
                        $novaLinha = str_replace(
                            "{" . $label . "}",
                            htmlspecialchars($assoc[$label], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                            $novaLinha
                        );
                    }

                    if ($incluir) {
                        $saida[] = $novaLinha;
                    }
                }

                $textoEnviar = implode("\n", $saida);

                // Salva no flash para exibir resultado na tela
                $_SESSION['sucesso_teste'] = $textoEnviar;

                // Envia ao Telegram (form_id = 3)
                $telegramToken  = '8013231460:AAEhGNGKvHmZz4F_Zc-krqmtogdhX8XR3Bk';
                $telegramApiUrl = "https://api.telegram.org/bot{$telegramToken}/sendMessage";

                $destStmt = $pdo->prepare("
                    SELECT chat_id
                      FROM telegram_recipient_forms
                     WHERE form_id = :form_id
                ");
                $destStmt->execute([':form_id' => 3]);
                $destRows = $destStmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($destRows as $chatId) {
                    $params = [
                        'chat_id'    => $chatId,
                        'text'       => escapeTelegramMarkdown($textoEnviar),
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

        // Redireciona para a mesma página (evita re-submission)
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    // 2.3) Teste Cron: processa novos lote_id e envia ao Telegram
    if ($action === 'cron_test' && isset($formKeys[$formKey])) {
        $tabela = match ($formKey) {
            'disp_bdf_almoco'     => 'disp_bdf_almoco',
            'disp_bdf_almoco_fds' => 'disp_bdf_almoco_fds',
            'disp_bdf_noite'      => 'disp_bdf_noite',
            'disp_wab'            => 'disp_wab',
            default               => null
        };

        if ($tabela) {
            // Se não houver nenhum registro, usar '1970-01-01 00:00:00' como fallback
            $lastStmt = $pdo->prepare("
                SELECT MAX(lote_id) 
                  FROM automation_disp 
                 WHERE form_key = :form_key
            ");
            $lastStmt->execute([':form_key' => $formKey]);
            $lastLote = $lastStmt->fetchColumn();

            $fallback = '1970-01-01 00:00:00';
            $compareLote = $lastLote !== null ? $lastLote : $fallback;

            $novosLotesStmt = $pdo->prepare("
                SELECT DISTINCT lote_id 
                  FROM {$tabela}
                 WHERE lote_id IS NOT NULL
                   AND lote_id > :last
                 ORDER BY lote_id ASC
            ");
            $novosLotesStmt->execute([':last' => $compareLote]);
            $novosLotes = $novosLotesStmt->fetchAll(PDO::FETCH_COLUMN);

            $tplStmt = $pdo->prepare("
                SELECT template_md 
                  FROM telegram_disp_templates 
                 WHERE form_key = :form_key
            ");
            $tplStmt->execute([':form_key' => $formKey]);
            $templateMd = $tplStmt->fetchColumn();

            foreach ($novosLotes as $loteId) {
                $dadosStmt = $pdo->prepare("
                    SELECT d.*, f.nome_prato 
                      FROM {$tabela} d
                 LEFT JOIN ficha_tecnica f 
                        ON f.codigo_cloudify = d.codigo_cloudify
                     WHERE d.lote_id = :lote_id
                ");
                $dadosStmt->execute([':lote_id' => $loteId]);
                $rows = $dadosStmt->fetchAll();

                if (!$rows) continue;

                $usuario   = $rows[0]['nome_usuario'];
                $dataEnvio = $rows[0]['data'];
                $comentarioGeral = trim((string)$rows[0]['comentarios']);

                $lista = [];
                foreach ($rows as $r) {
                $nome = htmlspecialchars($r['nome_prato'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $disp = $r['disponivel'] ? '✅' : '❌';
                    $lista[] = "- {$nome} : {$disp}";
                }

                $assoc = [
                    'data'         => $dataEnvio,
                    'nome_usuario' => $usuario,
                    'lista_codigos'=> implode("\n", $lista),
                    'comentarios'  => $comentarioGeral
                ];

                $linhas = explode("\n", $templateMd);
                $saida  = [];

                foreach ($linhas as $linha) {
                    preg_match_all('/\{([^}]+)\}/', $linha, $matches);
                    $novaLinha = $linha;
                    $incluir   = true;

                    foreach ($matches[1] as $label) {
                        if (!isset($assoc[$label]) || trim($assoc[$label]) === '') {
                            $incluir = false;
                            break;
                        }
                        $novaLinha = str_replace(
                            "{" . $label . "}",
                            htmlspecialchars($assoc[$label], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                            $novaLinha
                        );
                    }

                    if ($incluir) $saida[] = $novaLinha;
                }

                $textoEnviar = implode("\n", $saida);

                $telegramToken  = '8013231460:AAEhGNGKvHmZz4F_Zc-krqmtogdhX8XR3Bk';
                $telegramApiUrl = "https://api.telegram.org/bot{$telegramToken}/sendMessage";

                $destStmt = $pdo->prepare("
                    SELECT chat_id 
                      FROM telegram_recipient_forms 
                     WHERE form_id = :form_id
                ");
                $destStmt->execute([':form_id' => 3]);
                $destinos = $destStmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($destinos as $chatId) {
                    $params = [
                        'chat_id'    => $chatId,
                        'text'       => escapeTelegramMarkdown($textoEnviar),
                        'parse_mode' => 'Markdown'
                    ];
                    $ch = curl_init($telegramApiUrl);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_exec($ch);
                    curl_close($ch);
                }

                $logStmt = $pdo->prepare("
                    INSERT INTO automation_disp (form_key, lote_id, sent_at)
                    VALUES (:form_key, :lote_id, NOW())
                ");
                $logStmt->execute([
                    ':form_key' => $formKey,
                    ':lote_id'  => $loteId
                ]);
            }
        }

        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// 3) Carregue os templates atuais para exibir no formulário
$currentTemplates = [];
$inClause         = implode(',', array_fill(0, count($formKeys), '?'));
$stmt             = $pdo->prepare("
    SELECT form_key, template_md
      FROM telegram_disp_templates
     WHERE form_key IN ({$inClause})
");
$stmt->execute(array_keys($formKeys));
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $currentTemplates[$r['form_key']] = $r['template_md'];
}
unset($rows);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Configuração – Templates Telegram (Disponibilidade)</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-100 text-gray-900 min-h-screen flex flex-col sm:flex-row">
<?php require __DIR__ . '/../../sidebar.php'; ?>

  <main class="flex-1 p-4 sm:p-10 pt-20 sm:pt-10">
    <h1 class="text-3xl font-bold mb-6">Configuração de Templates – Telegram (Disponibilidade)</h1>
    <p class="mb-4 text-gray-700">
      Edite o Markdown de cada modelo e clique em “Salvar Modelo”.<br>
      Para testar imediatamente a última resposta, clique em “Enviar Teste”.<br>
      Ou use o “Teste Cron” para simular envios com base nos lote_id mais recentes.
    </p>

    <!-- Mensagem de sucesso ao enviar teste -->
    <?php if (!empty($flashTeste)): ?>
      <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded mb-6">
        <strong class="font-semibold">Teste enviado com sucesso!</strong>
        <p class="mt-2 text-sm">
          Abaixo você vê o Markdown completo que foi enviado ao Telegram.
        </p>
        <pre class="mt-2 bg-gray-50 border border-gray-200 rounded p-3 overflow-auto text-sm"><?= htmlspecialchars($flashTeste, ENT_QUOTES, 'UTF-8') ?></pre>
      </div>
    <?php endif; ?>

    <?php foreach ($formKeys as $key => $titulo): ?>
      <form method="POST" class="bg-white shadow rounded-lg p-6 mb-6">
        <h2 class="text-2xl font-semibold mb-4"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?></h2>

        <label class="block text-gray-700 mb-2 font-medium">
          Template Markdown para <em><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?></em>:
        </label>
        <textarea
          name="template_md[<?= $key; ?>]"
          class="w-full p-2 border border-gray-300 rounded h-48 font-mono text-sm"
          placeholder="Digite aqui o template para <?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?>…"
        ><?= htmlspecialchars($currentTemplates[$key] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>

        <p class="mt-2 text-sm text-gray-500">
          Exemplos de placeholders (copie exatamente do nome das colunas no BD):<br>
          <code class="bg-gray-100 px-1 rounded">{data}</code>,
          <code class="bg-gray-100 px-1 rounded">{nome_usuario}</code>,
          <code class="bg-gray-100 px-1 rounded">{lista_codigos}</code>,
          <code class="bg-gray-100 px-1 rounded">{comentarios}</code><br>
          *Cada `{campo}` deve bater exatamente com a coluna na tabela.*
        </p>

        <div class="mt-4 flex space-x-2">
          <!-- Botão Salvar Modelo -->
          <button
            type="submit"
            name="action"
            value="save_template"
            class="btn-acao-azul"
          >
            Salvar Modelo
          </button>

          <!-- Botão Enviar Teste -->
          <button
            type="submit"
            name="action"
            value="send_test"
            class="btn-acao-verde"
          >
            Enviar Teste
          </button>

          <!-- Botão Teste Cron -->
          <button
            type="submit"
            name="action"
            value="cron_test"
            class="btn-acao-azul"
          >
            Teste Cron
          </button>

          <!-- Passa junto o form_key para diferenciarmos qual template testar -->
          <input type="hidden" name="form_key" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" />
        </div>
      </form>
    <?php endforeach; ?>
  </main>
</body>
</html>
