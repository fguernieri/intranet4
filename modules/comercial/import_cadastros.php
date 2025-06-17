<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Método inválido.');
    }
    if (!isset($_FILES['arquivo_metas']) || $_FILES['arquivo_metas']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erro no upload do arquivo.');
    }

    // carrega Excel
    $spreadsheet = IOFactory::load($_FILES['arquivo_metas']['tmp_name']);
    $sheet       = $spreadsheet->getActiveSheet();

    // prepara upsert na tabela ajustada
    $sql = <<<SQL
INSERT INTO cadastro_clientes
  (data_cadastro, nome_fantasia, codigo, vendedor_nome)
VALUES
  (:data, :nome, :codigo, :vendedor)
ON DUPLICATE KEY UPDATE
  nome_fantasia  = VALUES(nome_fantasia),
  vendedor_nome  = VALUES(vendedor_nome),
  atualizado_em  = CURRENT_TIMESTAMP
SQL;
    $stmt = $pdo->prepare($sql);

    $totLido     = 0;
    $totInserido = 0;
    $erros       = [];

    // loop: linha 4 até o final
    for ($r = 4; $r <= $sheet->getHighestRow(); $r++) {
        $rawCodigo   = trim((string)$sheet->getCell("A{$r}")->getValue());
        $rawNome     = trim((string)$sheet->getCell("C{$r}")->getValue());
        $rawVendedor= trim((string)$sheet->getCell("S{$r}")->getValue());
        $cellDate    = $sheet->getCell("U{$r}");
        $rawData     = $cellDate->getValue();

        // pula vazios
        if ($rawCodigo === '' || $rawNome === '' || $rawVendedor === '' || $rawData === null) {
            continue;
        }

        // parse data d/m/Y H:i:s ou Excel serial
        if (ExcelDate::isDateTime($cellDate)) {
            $dt = ExcelDate::excelToDateTimeObject($rawData);
        } else {
            $dt = DateTime::createFromFormat('d/m/Y H:i:s', $rawData);
            if (! $dt) {
                $erros[] = "Linha {$r}: data inválida “{$rawData}”.";
                continue;
            }
        }

        $totLido++;

        // executa upsert
        $stmt->execute([
            ':data'      => $dt->format('Y-m-d H:i:s'),
            ':nome'      => $rawNome,
            ':codigo'    => $rawCodigo,
            ':vendedor'  => $rawVendedor,
        ]);

        if ($stmt->rowCount() > 0) {
            $totInserido++;
        }
    }

    if ($totLido === 0) {
        throw new RuntimeException('Nenhuma linha válida: verifique colunas A,C,S,U.');
    }

    // redireciona com feedback
    header("Location: dash_comercial.php?import=sucesso&lido={$totLido}&inserido={$totInserido}");
    exit;

} catch (Throwable $e) {
    echo '<h2>Erro na importação</h2>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    if ($erros) {
        echo '<h3>Detalhes:</h3><ul>';
        foreach ($erros as $msg) {
            echo '<li>' . htmlspecialchars($msg) . '</li>';
        }
        echo '</ul>';
    }
    exit;
}
