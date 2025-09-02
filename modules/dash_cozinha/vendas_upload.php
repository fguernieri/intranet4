<?php
// modules/dash_cozinha/vendas_upload.php
include __DIR__ . '/../../auth.php';
include __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/vendas_migrate.php';

cozinha_vendas_ensure_tables($pdo);

header('Content-Type: application/json; charset=utf-8');

function parse_number_ptbr($val): float {
    if ($val === null) return 0.0;
    if (is_numeric($val)) return (float)$val;
    $s = trim((string)$val);
    // Remove currency and spaces
    $s = preg_replace('/[^0-9,.-]/', '', $s);
    // If comma is decimal separator, swap
    if (substr_count($s, ',') === 1 && (substr_count($s, '.') > 1 || (strpos($s, ',') > strrpos($s, '.')))) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } else {
        $s = str_replace(',', '', $s);
    }
    return is_numeric($s) ? (float)$s : 0.0;
}

try {
    if (!isset($_FILES['arquivo'])) {
        throw new RuntimeException('Arquivo não enviado');
    }

    $file = $_FILES['arquivo'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no upload: ' . $file['error']);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
        throw new RuntimeException('Formato não suportado');
    }

    $destDir = __DIR__ . '/uploads';
    if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
    $destPath = $destDir . '/vendas_' . date('Ymd_His') . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException('Não foi possível mover o arquivo');
    }

    // Read spreadsheet
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($destPath);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($destPath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true); // keys as column letters

    $linhasProcessadas = 0;
    $linhasUpsert = 0;
    $pdo->beginTransaction();

    // Expect headers in line 1
    foreach ($rows as $idx => $row) {
        if ($idx == 1) continue; // skip header
        // Expected columns per provided screenshot
        $rawData = $row['A'] ?? null;
        $codigo  = trim((string)($row['B'] ?? ''));
        if ($codigo === '') continue; // skip empty
        $produto = trim((string)($row['C'] ?? ''));
        $grupo   = trim((string)($row['D'] ?? ''));
        $preco   = parse_number_ptbr($row['E'] ?? 0);
        $qtde    = parse_number_ptbr($row['M'] ?? 0); // Coluna M: Qtde. total
        $total   = $preco * $qtde; // fallback if planilha não tiver total

        // Parse data (pt-BR dd/mm/yyyy or Excel serial)
        $data = null;
        if ($rawData instanceof \DateTimeInterface) {
            $data = $rawData->format('Y-m-d');
        } else {
            $s = trim((string)$rawData);
            if ($s !== '') {
                // Try Excel serial
                if (is_numeric($s)) {
                    try {
                        $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$s);
                        $data = $dt->format('Y-m-d');
                    } catch (\Throwable $e) {
                        $data = null;
                    }
                }
                if ($data === null) {
                    $parts = preg_split('#[/\\.-]#', $s);
                    if (count($parts) >= 3) {
                        [$d,$m,$y] = [$parts[0],$parts[1],$parts[2]];
                        if (strlen($y) === 2) $y = '20'.$y;
                        $data = sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
                    }
                }
            }
        }
        if (!$data) continue;

        $linhasProcessadas++;

        $sql = "INSERT INTO cozinha_vendas_diarias
                    (codigo, data, produto, grupo, preco, qtde, total)
                VALUES
                    (:codigo, :data, :produto, :grupo, :preco, :qtde, :total)
                ON DUPLICATE KEY UPDATE
                    produto = VALUES(produto),
                    grupo   = VALUES(grupo),
                    preco   = VALUES(preco),
                    qtde    = VALUES(qtde),
                    total   = VALUES(total)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':codigo'  => $codigo,
            ':data'    => $data,
            ':produto' => $produto,
            ':grupo'   => $grupo,
            ':preco'   => $preco,
            ':qtde'    => $qtde,
            ':total'   => $total,
        ]);
        $linhasUpsert += $stmt->rowCount() > 0 ? 1 : 0;
    }

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'arquivo' => basename($destPath),
        'linhas_processadas' => $linhasProcessadas,
        'linhas_upsert' => $linhasUpsert,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
}
?>

