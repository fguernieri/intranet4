<?php
// Upload e parser do Excel para Engenharia de Cardápio
// Espera um arquivo (campo 'arquivo') com as colunas especificadas.

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../vendor/autoload.php';

function norm($s) {
    $s = trim((string)$s);
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9\s\.]/', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

function parseNumber($v) {
    if ($v === null) return 0.0;
    if (is_numeric($v)) return (float)$v;
    $s = trim((string)$v);
    if ($s === '') return 0.0;
    // Trata formatos brasileiros: 1.234,56
    $s = str_replace(['\u00A0', ' '], '', $s);
    $s = str_replace(['R$', 'r$'], '', $s);
    $s = str_replace('.', '', $s);
    $s = str_replace(',', '.', $s);
    return is_numeric($s) ? (float)$s : 0.0;
}

try {
    if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Arquivo não enviado ou inválido.');
    }

    $tmp = $_FILES['arquivo']['tmp_name'];
    // Usa o reader em modo "somente dados" para evitar parsing de fórmulas
    // e aproveitar valores pré-calculados armazenados no arquivo.
    $reader = IOFactory::createReaderForFile($tmp);
    if (method_exists($reader, 'setReadDataOnly')) {
        $reader->setReadDataOnly(true);
    }
    $spreadsheet = $reader->load($tmp);
    $sheet = $spreadsheet->getActiveSheet();
    // calculateFormulas=false evita estourar em fórmulas não suportadas
    $rows = $sheet->toArray(null, false, true, true);

    if (!$rows || count($rows) < 2) {
        throw new RuntimeException('Planilha vazia ou sem cabeçalho.');
    }

    // Mapeia cabeçalhos
    $headerRow = $rows[1];
    $map = [];
    foreach ($headerRow as $col => $val) {
        $h = norm($val);
        if ($h === '') continue;
        $map[$h] = $col; // coluna letra -> header normalizado
    }

    // Possíveis chaves
    $colData    = $map['data'] ?? null;
    $colCod     = $map['cod.'] ?? ($map['cód.'] ?? ($map['codigo'] ?? ($map['cod'] ?? null)));
    $colProd    = $map['produto'] ?? null;
    $colPreco   = $map['preco']   ?? ($map['preço'] ?? null);
    $colQtTotal = $map['qtde. total'] ?? ($map['qtde total'] ?? ($map['quantidade total'] ?? null));
    $colQtVista = $map['qtde. a vista'] ?? ($map['qtde. à vista'] ?? null);
    $colQtPrazo = $map['qtde. a prazo'] ?? null;

    if (!$colQtTotal && !$colQtVista && !$colQtPrazo) {
        throw new RuntimeException('Não encontrei colunas de quantidade (Qtde. total ou somatório das parciais).');
    }

    $agg = [];
    $minDate = null; $maxDate = null;

    // Processa linhas de dados (a partir da 2ª)
    for ($i = 2; $i <= count($rows); $i++) {
        $r = $rows[$i] ?? [];
        if (!$r) continue;

        $codigo  = $colCod  ? trim((string)($r[$colCod] ?? '')) : '';
        $produto = $colProd ? trim((string)($r[$colProd] ?? '')) : '';
        $preco   = $colPreco? parseNumber($r[$colPreco] ?? 0) : 0.0;

        $qt = 0.0;
        if ($colQtTotal) $qt = parseNumber($r[$colQtTotal] ?? 0);
        else $qt = parseNumber($r[$colQtVista] ?? 0) + parseNumber($r[$colQtPrazo] ?? 0);

        // Data
        if ($colData) {
            $d = trim((string)($r[$colData] ?? ''));
            if ($d !== '') {
                // Tenta dd/mm/aaaa
                if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $d, $m)) {
                    $ds = $m[3] . '-' . $m[2] . '-' . $m[1];
                } else {
                    // Se vier em formato Excel numérico, PhpSpreadsheet já converteu? best effort
                    $ts = strtotime($d);
                    $ds = $ts ? date('Y-m-d', $ts) : null;
                }
                if ($ds) {
                    if (!$minDate || $ds < $minDate) $minDate = $ds;
                    if (!$maxDate || $ds > $maxDate) $maxDate = $ds;
                }
            }
        }

        if ($qt <= 0) continue;

        $key = $codigo !== '' ? ('c:' . $codigo) : ('n:' . norm($produto));
        if (!isset($agg[$key])) {
            $agg[$key] = [
                'codigo' => $codigo !== '' ? $codigo : null,
                'nome'   => $produto,
                'quantidade' => 0.0,
                'preco'  => $preco > 0 ? $preco : null,
            ];
        }
        $agg[$key]['quantidade'] += $qt;
    }

    echo json_encode([
        'ok'     => true,
        'periodo'=> ['inicio' => $minDate, 'fim' => $maxDate],
        'vendas' => array_values($agg),
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}
?>
