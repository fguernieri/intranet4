<?php
// Ative exibição de erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db_dw.php';
$table = 'insumos_bastards';

// 1) Colunas-chave para identificar cada registro
$keyCols = ['Cód. ref.', 'CODIGO'];

// 2) Todas as colunas da tabela, na mesma ordem do CSV
$dbColumns = [
    'Cód. ref.',
    'Produto',
    'Unidade',
    'Tipo',
    'Grupo',
    'Rendimento',
    'Custo unit.',
    'Preço venda',
    'Markup atual(%)',
    'Markup desejado(%)',
    'Preço sugerido',
    'CODIGO',
    'Insumo',
    'Qtde',
    'Und.',
    'Custo unit..1',
    'Custo total',
    'Principal'
];

// 3) Colunas numéricas para conversão
$numericCols = [
    'Rendimento',
    'Custo unit.',
    'Preço venda',
    'Markup atual(%)',
    'Markup desejado(%)',
    'Preço sugerido',
    'Qtde',
    'Custo unit..1',
    'Custo total'
];

$logFile = __DIR__ . '/import_csv.log';

function paramName(string $col): string {
    return preg_replace('/[^a-zA-Z0-9_]/', '_', $col);
}

// Se não for POST ou sem arquivo, exibe formulário
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])): ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Importar CSV de Insumos</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 2rem; background: #f5f5f5; }
    form { background: #fff; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    input, button { font-size: 1rem; margin-top: 0.5rem; }
  </style>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../assets/css/style.css" />
</head>
<body>

  <h1>Importar CSV de Insumos</h1>
  <form method="POST" enctype="multipart/form-data" onsubmit="return ativarLoaderImport()">
    <label>
      Selecione o arquivo CSV:<br>
      <input type="file" name="csv_file" accept=".csv,.txt" required>
    </label><br><br>
    <button type="submit" class="btn-acao">Importar</button>
  </form>

  <?php include __DIR__ . '/../../components/loader.php'; ?>

</body>
</html>
<?php exit; endif;

// === PROCESSAMENTO ===
if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    die("Erro no upload do CSV: código {$_FILES['csv_file']['error']}");
}
$csvFile = $_FILES['csv_file']['tmp_name'];

file_put_contents($logFile, date('Y-m-d H:i:s')." - Início importação {$_FILES['csv_file']['name']}\n", LOCK_EX);

// Preparar statements de checagem, update e insert
$chkSql = "
  SELECT 1 FROM `$table`
   WHERE `{$keyCols[0]}` = :" . paramName($keyCols[0]) . "
     AND `{$keyCols[1]}` = :" . paramName($keyCols[1]) . "
   LIMIT 1
";
$chkStmt = $pdo_dw->prepare($chkSql);

$updCols    = array_diff($dbColumns, $keyCols);
$setParts   = array_map(function($c){ return "`$c` = :" . paramName($c); }, $updCols);
$whereParts = array_map(function($k){ return "`$k` = :" .	paramName($k); }, $keyCols);

$sqlUpdate  = "UPDATE `$table` SET " . implode(', ', $setParts) . " WHERE " . implode(' AND ', $whereParts);
$stmtUpdate = $pdo_dw->prepare($sqlUpdate);

$colList    = implode(', ', array_map(function($c){ return "`$c`"; }, $dbColumns));
$paramList  = implode(', ', array_map(function($c){ return ":" . paramName($c); }, $dbColumns));
$sqlInsert  = "INSERT INTO `$table` ($colList) VALUES ($paramList)";
$stmtInsert = $pdo_dw->prepare($sqlInsert);

$handle = fopen($csvFile, 'r');
// Usa ponto-e-vírgula como delimitador
$header = fgetcsv($handle, 0, "	", '"')  // usa tabulação como delimitador;

if (isset($header[0]) && $header[0] === 'Cód. ref.Produto') {
    array_splice($header, 0, 1, ['Cód. ref.', 'Produto']);
}
if (end($header) === '') {
    array_pop($header);
}
if (count($header) !== count($dbColumns)) {
    die("Colunas CSV (" . count($header) . ") diferentes do esperado (" . count($dbColumns) . ")");
}

$proc = $up = $in = $err = 0;
$line = 1;
$csvMap = [];

while (($cells = fgetcsv($handle, 0, "	", '"')  // usa tabulação como delimitador) !== false) {
    $line++;
    if (count($cells) < count($dbColumns)) { $err++; continue; }
    $cells = array_slice($cells, 0, count($dbColumns));
    $row   = array_combine($dbColumns, $cells);

    foreach ($keyCols as $k) {
        $row[$k] = trim(preg_replace('/\x{00a0}/u', '', $row[$k]));
    }

    $params = [];
    foreach ($dbColumns as $col) {
        $val = $row[$col];
        if (in_array($col, $numericCols, true)) {
            // Remove separador de milhar e ajusta decimal
            $clean = str_replace('.', '', $val);
            $clean = str_replace(',', '.', $clean);
            $params[paramName($col)] = ($clean === '' ? null : (float)$clean);
        } else {
            $params[paramName($col)] = ($val === '' ? null : $val);
        }
    }

    $codRef    = $params[paramName('Cód. ref.')];
    $codInsumo = $params[paramName('CODIGO')];
    $csvMap[$codRef][] = $codInsumo;

    // Checa existência no banco
    $chkStmt->execute([
        paramName($keyCols[0]) => $params[paramName($keyCols[0])],
        paramName($keyCols[1]) => $params[paramName($keyCols[1])],
    ]);
    $exists = $chkStmt->fetch();

    try {
        if ($exists) {
            $stmtUpdate->execute($params);
            if ($stmtUpdate->rowCount() > 0) {
                $up++;
                file_put_contents($logFile, "Linha $line: UPDATE | keys={$row[$keyCols[0]]},{$row[$keyCols[1]]}\n", FILE_APPEND | LOCK_EX);
            }
        } else {
            $stmtInsert->execute($params);
            $in++;
            file_put_contents($logFile, "Linha $line: INSERT | keys={$row[$keyCols[0]]},{$row[$keyCols[1]]}\n", FILE_APPEND | LOCK_EX);
        }
        $proc++;
    } catch (PDOException $e) {
        $err++;
        file_put_contents($logFile, "Erro na linha $line: {$e->getMessage()}\n", FILE_APPEND | LOCK_EX);
    }
}

fclose($handle);

// Remoção de registros não presentes no CSV
$delTotal = 0;
foreach ($csvMap as $codRef => $insumosCsv) {
    $placeholders = implode(',', array_fill(0, count($insumosCsv), '?'));
    $params = $insumosCsv;
    array_unshift($params, $codRef);

    $sqlDelete = "
        DELETE FROM `$table`
        WHERE `{$keyCols[0]}` = ?
          AND `{$keyCols[1]}` NOT IN ($placeholders)
    ";

    $stmtDel = $pdo_dw->prepare($sqlDelete);
    $stmtDel->execute($params);
    $delCount = $stmtDel->rowCount();
    $delTotal += $delCount;
    file_put_contents($logFile, "Cód. ref. $codRef: $delCount registros excluídos por ausência no CSV\n", FILE_APPEND | LOCK_EX);
}

file_put_contents($logFile, date('Y-m-d H:i:s')." - Fim: proc=$proc; upd=$up; ins=$in; del=$delTotal; err=$err\n", FILE_APPEND | LOCK_EX);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Importação Concluída</title>
</head>
<body>
  <h1>✅ Importação Finalizada</h1>
  <p>Processados: <?= $proc ?> | Atualizados: <?= $up ?> | Inseridos: <?= $in ?> | Deletados: <?= $delTotal ?> | Erros: <?= $err ?></p>
  <p>Log: <code><?= htmlspecialchars($logFile) ?></code></p>
  <p><a href="<?= $_SERVER['PHP_SELF'] ?>">📁 Importar outro CSV</a></p>
</body>
</html>
