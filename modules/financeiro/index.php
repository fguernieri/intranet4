<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sidebar e autenticação
require_once __DIR__ . '/../../sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

// Conexão com o banco
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// --- Filtro de ANO ---
$anoAtual = date('Y');
$anoSelecionado = isset($_GET['ano']) ? intval($_GET['ano']) : $anoAtual;

// Buscar anos disponíveis na view
$anos = [];
$resAnos = $conn->query("SELECT DISTINCT YEAR(DATA_EXIBIDA) AS ano FROM vw_financeiro_dre_fabrica ORDER BY ano DESC");
if ($resAnos) {
    while ($row = $resAnos->fetch_assoc()) {
        $anos[] = $row['ano'];
    }
    $resAnos->free();
}

// Buscar dados filtrados pelo ano selecionado
$sql = "
    SELECT 
        CATEGORIA, 
        SUBCATEGORIA, 
        MONTH(DATA_EXIBIDA) AS MES,
        SUM(VALOR_EXIBIDO) AS VALOR
    FROM vw_financeiro_dre_fabrica
    WHERE YEAR(DATA_EXIBIDA) = $anoSelecionado
    GROUP BY CATEGORIA, SUBCATEGORIA, MES
    ORDER BY CATEGORIA, SUBCATEGORIA, MES
";
$resDados = $conn->query($sql);
if (!$resDados) {
    die('<div style="color:red">Erro na consulta SQL: ' . $conn->error . '</div>');
}
$dados = [];
$categorias = [];
$subcategorias = [];
while ($row = $resDados->fetch_assoc()) {
    $cat = $row['CATEGORIA'] ?? 'SEM CATEGORIA';
    $sub = $row['SUBCATEGORIA'] ?? 'SEM SUBCATEGORIA';
    $mes = (int)$row['MES'];
    $valor = (float)$row['VALOR'];
    $dados[$cat][$sub][$mes] = $valor;
    $categorias[$cat] = true;
    $subcategorias[$cat][$sub] = true;
}
$resDados->free();

// Meses do ano
$meses = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>DRE Financeiro</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="/assets/css/style.css" rel="stylesheet">
  <style>
    .dre-cat    { background: #22223b; font-weight: bold; cursor:pointer; }
    .dre-sub    { background: #383858; font-weight: 500; cursor:pointer; }
    .dre-detalhe{ background: #232946; }
    .dre-hide   { display: none; }
  </style>
</head>
<body class="bg-gray-900 text-gray-100 flex min-h-screen">
<main class="flex-1 bg-gray-900 p-6 relative">
  <h1 class="text-2xl font-bold text-yellow-400 mb-6">DRE Financeiro</h1>

  <!-- Filtro de Ano -->
  <form method="get" class="mb-6">
    <label for="ano" class="font-semibold text-xs mr-2">Ano:</label>
    <select name="ano" id="ano" class="bg-gray-700 text-white text-xs p-2 rounded" onchange="this.form.submit()">
      <?php foreach ($anos as $ano): ?>
        <option value="<?=$ano?>" <?=$ano==$anoSelecionado?'selected':''?>><?=$ano?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <table class="min-w-full text-xs mx-auto border border-gray-700 rounded">
    <thead class="bg-gray-700 text-yellow-400">
      <tr>
        <th class="p-2 text-left">Categoria</th>
        <th class="p-2 text-left">Subcategoria</th>
        <?php foreach ($meses as $num => $nome): ?>
          <th class="p-2 text-right"><?=$nome?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($categorias as $cat => $_): ?>
        <?php $subs = $subcategorias[$cat]; ?>
        <?php foreach ($subs as $sub => $_): ?>
          <tr>
            <td class="p-2"><?=$cat?></td>
            <td class="p-2"><?=$sub?></td>
            <?php foreach ($meses as $num => $nome): ?>
              <td class="p-2 text-right">
                <?= isset($dados[$cat][$sub][$num]) ? number_format($dados[$cat][$sub][$num],2,',','.') : '-' ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
</main>
</body>
</html>