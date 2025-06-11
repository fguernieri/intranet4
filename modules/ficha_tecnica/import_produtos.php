<?php
require '../../vendor/autoload.php';
require_once '../../config/db_dw.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function normalizeParam($col) {
    return preg_replace('/[^a-zA-Z0-9_]/', '_', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $col));
}

function sanitizeExcelValue($value) {
    if (is_string($value)) {
        $value = trim($value);
        if (preg_match('/^==\s*"(.+)"$/s', $value, $matches)) {
            $value = $matches[1];
        } elseif (preg_match('/^="\s*(.+?)\s*"$/s', $value, $matches)) {
            $value = $matches[1];
        } elseif (str_starts_with($value, '="')) {
            $value = substr($value, 2);
        } elseif (str_starts_with($value, '=')) {
            $value = ltrim($value, '=');
        }
        if (preg_match('/^-?\d+,\d+$/', $value)) {
            $value = str_replace(',', '.', $value);
        }
        $value = trim($value);
    }
    return $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file']['tmp_name'];
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $data = $sheet->toArray(null, false, false, true);

    $header = array_shift($data);
    $columns = array_map('trim', $header);
    $colKeys = array_keys($columns);

    $atualizados = 0;
    $novos = 0;
    $erros = 0;

    foreach ($data as $row) {
        try {
            $rowAssoc = [];
            foreach ($colKeys as $key) {
                $colName = $columns[$key];
                $valorBruto = $row[$key] ?? null;
                $rowAssoc[$colName] = sanitizeExcelValue($valorBruto);
            }

            if (empty($rowAssoc['Cód. Ref.'])) continue;

            $stmt = $pdo_dw->prepare("SELECT COUNT(*) FROM ProdutosBares WHERE `Cód. Ref.` = :cod");
            $stmt->execute(['cod' => $rowAssoc['Cód. Ref.']]);
            $exists = $stmt->fetchColumn();

            if ($exists) {
                $set = [];
                $params = [];
                foreach ($rowAssoc as $col => $val) {
                    if ($col === 'Cód. Ref.') continue;
                    $param = normalizeParam($col);
                    $set[] = "`$col` = :$param";
                    $params[$param] = $val;
                }
                $params['cod_ref'] = $rowAssoc['Cód. Ref.'];
                $sql = "UPDATE ProdutosBares SET " . implode(', ', $set) . " WHERE `Cód. Ref.` = :cod_ref";
                $stmt = $pdo_dw->prepare($sql);
                $stmt->execute($params);
                $atualizados++;
            } else {
                $cols = array_keys($rowAssoc);
                $placeholders = [];
                $params = [];
                foreach ($rowAssoc as $col => $val) {
                    $param = normalizeParam($col);
                    $placeholders[] = ":$param";
                    $params[$param] = $val;
                }
                $sql = "INSERT INTO ProdutosBares (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $pdo_dw->prepare($sql);
                $stmt->execute($params);
                $novos++;
            }
        } catch (Exception $e) {
            $erros++;
        }
    }

    echo "<h1>✅ Importação Concluída</h1>";
    echo "<p>🔁 Atualizados: <strong>$atualizados</strong></p>";
    echo "<p>➕ Inseridos: <strong>$novos</strong></p>";
    echo "<p>❌ Erros: <strong>$erros</strong></p>";
    echo "<p><a href=\"{$_SERVER['PHP_SELF']}\">⬅️ Importar outro arquivo</a></p>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Importar Produtos</title>
  <style>
    body { font-family: Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 2rem; }
    .container { background: #fff; padding: 2rem; border-radius: 12px; max-width: 500px; margin: auto; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    #resultado-upload { margin-top: 2rem; }
  </style>
</head>
<body>

<div class="container" id="form-upload">
  <h1 class="text-xl font-bold mb-4">📦 Importar Produtos do Excel</h1>
  <form id="upload-form" onsubmit="return ativarLoaderImport()">
    <input type="file" name="excel_file" id="excel_file" accept=".xlsx" required class="mb-4">
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">🚀 Importar</button>
  </form>
</div>

<div id="resultado-upload" class="container hidden"></div>

<?php include __DIR__ . '/../../components/loader.php'; ?>

<script>
  document.getElementById('upload-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const stopLoader = mostrarLoader();
    const form = document.getElementById('upload-form');
    const formData = new FormData(form);

    const res = await fetch('', {
      method: 'POST',
      body: formData
    });
    const html = await res.text();

    document.getElementById('form-upload').style.display = 'none';
    const resultado = document.getElementById('resultado-upload');
    resultado.innerHTML = html;
    resultado.classList.remove('hidden');
    if (typeof stopLoader === 'function') stopLoader();
  });
</script>

</body>
</html>
