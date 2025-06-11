#!/usr/bin/env php
<?php
declare(strict_types=1);

// 0) Inclusões obrigatórias
require __DIR__ . '/../../config/db.php';

// Função para escapar caracteres especiais no Telegram Markdown
function escapeTelegramMarkdown(string $texto): string {
    $caracteres = ['\\', '_', '*', '`', '[', ']'];
    $escapados  = ['\\\\', '\\_', '\\*', '\\`', '\\[', '\\]'];
    return str_replace($caracteres, $escapados, $texto);
}

// 1) Mapeie suas quatro páginas de disponibilidade
$formKeys = [
    'disp_bdf_almoco'      => 'disp_bdf_almoco',
    'disp_bdf_almoco_fds'  => 'disp_bdf_almoco_fds',
    'disp_bdf_noite'       => 'disp_bdf_noite',
    'disp_wab'             => 'disp_wab'
];

// Token do seu bot Telegram (já existente no seu código original)
$telegramToken  = '8013231460:AAEhGNGKvHmZz4F_Zc-krqmtogdhX8XR3Bk';
$telegramApiUrl = "https://api.telegram.org/bot{$telegramToken}/sendMessage";

// 2) Percorre cada formKey para processar “Teste Cron”
foreach ($formKeys as $formKey => $tabela) {
    // 2.1) Busca o último lote_id enviado para esse form_key
    $lastStmt = $pdo->prepare("
        SELECT MAX(lote_id)
          FROM automation_disp
         WHERE form_key = :form_key
    ");
    $lastStmt->execute([':form_key' => $formKey]);
    $lastLote = $lastStmt->fetchColumn();

    // Se não houver nenhum registro, usar '1970-01-01 00:00:00' como fallback
    $fallback     = '1970-01-01 00:00:00';
    $compareLote  = $lastLote !== null ? $lastLote : $fallback;

    // 2.2) Busca todos os lote_id novos (maiores que $compareLote)
    $novosLotesStmt = $pdo->prepare("
        SELECT DISTINCT lote_id
          FROM {$tabela}
         WHERE lote_id IS NOT NULL
           AND lote_id > :last
         ORDER BY lote_id ASC
    ");
    $novosLotesStmt->execute([':last' => $compareLote]);
    $novosLotes = $novosLotesStmt->fetchAll(PDO::FETCH_COLUMN);

    // 2.3) Carrega o template Markdown salvo
    $tplStmt = $pdo->prepare("
        SELECT template_md
          FROM telegram_disp_templates
         WHERE form_key = :form_key
    ");
    $tplStmt->execute([':form_key' => $formKey]);
    $templateMd = $tplStmt->fetchColumn() ?: '';

    // 2.4) Para cada lote_id novo, agrupa e envia
    foreach ($novosLotes as $loteId) {
        // 2.4.1) Recupera todas as respostas desse lote_id
        $dadosStmt = $pdo->prepare("
            SELECT d.*, f.nome_prato
              FROM {$tabela} d
         LEFT JOIN ficha_tecnica f
                ON f.codigo_cloudify = d.codigo_cloudify
             WHERE d.lote_id = :lote_id
        ");
        $dadosStmt->execute([':lote_id' => $loteId]);
        $rows = $dadosStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            continue;
        }

        // 2.4.2) Prepara os placeholders
        $usuario         = $rows[0]['nome_usuario'];
        $dataEnvio       = $rows[0]['data'];
        $comentarioGeral = trim((string)$rows[0]['comentarios']);

        $lista = [];
        foreach ($rows as $r) {
            // Se nome_prato for null, transformar em string vazia
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

        // 2.4.3) Monta o texto final substituindo placeholders
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

        // 2.4.4) Busca todos os chat_id para form_id = 3
        $destStmt = $pdo->prepare("
            SELECT chat_id
              FROM telegram_recipient_forms
             WHERE form_id = :form_id
        ");
        $destStmt->execute([':form_id' => 3]);
        $destinos = $destStmt->fetchAll(PDO::FETCH_COLUMN);

        // 2.4.5) Envia cada mensagem ao Telegram
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

        // 2.4.6) Registra o envio na automation_disp (sem response_id)
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

// Fim do “Teste Cron”
