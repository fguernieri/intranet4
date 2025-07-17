<?php
// === modules/compras/select_filial.php ===
// ATENÇÃO: não deve haver nenhum output antes deste <?php
require_once __DIR__ . '/../../sidebar.php';
// 1) Autenticação
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
session_start();
if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

// 2) Busca filiais distintas
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}
$res     = $conn->query("SELECT DISTINCT FILIAL FROM insumos ORDER BY FILIAL");
$filiais = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$conn->close();

if (empty($filiais)) {
    die("Nenhuma filial encontrada.");
}

// Helper para normalizar sem acentos e em maiúsculas
function normalize($s) {
    return mb_strtoupper(
        strtr($s,
            'ÁÀÂÃÄÉÈÊËÍÌÎÏÓÒÔÕÖÚÙÛÜÇ',
            'AAAAAEEEEIIIIOOOOOUUUUC'
        ), 'UTF-8'
    );
}

// Mapeamento filial → script de insumos
$map = [
    '7TRAGOS'        => 'insumos_7tragos.php',
    'BAR DA FABRICA' => 'insumos_bardafabrica.php',
    'WE ARE BASTARDS' => 'insumos_wearebastards.php',
    'CROSS' => 'insumos_cross.php',
    // caso tenha mais filiais personalize aqui...
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Selecione a Filial</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="/assets/css/style.css" rel="stylesheet">
  <style>
    /* Espaço extra para o botão fixo */
    main { padding-bottom: 4rem; }
  </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex">

  <main class="flex-1 p-6 bg-gray-900">

  <header class="mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold">
      Bem-vindo, <?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário'); ?>
    </h1>
    <p class="text-gray-400 text-sm">
      <?php
        $hoje = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
        $fmt = new IntlDateFormatter(
          'pt_BR',
          IntlDateFormatter::FULL,
          IntlDateFormatter::NONE,
          'America/Sao_Paulo',
          IntlDateFormatter::GREGORIAN
        );
        echo $fmt->format($hoje);
      ?>
    </p>
  </header>

  <h3 class="text-xl font-bold text-yellow-400 text-center mb-8">
    PEDIDOS POR MÉDIA
  </h3>

  <div class="max-w-md mx-auto flex flex-wrap gap-2 justify-center">
    <?php foreach ($filiais as $row):
      $f    = $row['FILIAL'];
      $norm = normalize($f);
      $target = $map[$norm] ?? 'insumos.php';
      $base = 'text-sm font-semibold py-2 px-4 rounded shadow';
      if ($norm === '7TRAGOS') {
          $cls = 'bg-black hover:bg-gray-800 text-white';
      } elseif ($norm === 'BAR DA FABRICA') {
          $cls = 'bg-blue-800 hover:bg-blue-900 text-white';
      } elseif ($norm === 'CROSS') {
          $cls = 'bg-black hover:bg-gray-800 text-white';
      } elseif ($norm === 'WE ARE BASTARDS') {
          $cls = 'bg-red-800 hover:bg-red-900 text-white';
      } else {
          $cls = 'bg-yellow-500 hover:bg-yellow-600 text-gray-900';
      }
    ?>
      <a href="<?= htmlspecialchars($target) ?>"
         class="<?= "$cls $base" ?>"
      ><?= htmlspecialchars($f) ?></a>
    <?php endforeach; ?>
  </div>

  <hr class="my-8 border-yellow-400">

  <h3 class="text-lg font-bold text-yellow-400 text-center mb-4">PEDIDOS MANUAIS</h3>

  <div class="space-y-2">
    <div class="max-w-md mx-auto flex justify-center">
      <a href="insumosbdf_htf.php"
         class="text-sm font-semibold py-2 px-4 bg-blue-800 hover:bg-blue-900 text-white rounded shadow whitespace-nowrap"
         title="Acesso exclusivo para Gerência/Chefe de Bar da Fábrica"
      >BAR DA FÁBRICA | COZINHA - BAR - GERÊNCIA - EVENTOS</a>
    </div>
    <div class="max-w-md mx-auto flex justify-center">
      <a href="insumoswab_htf.php"
         class="text-sm font-semibold py-2 px-4 bg-red-800 hover:bg-red-900 text-white rounded shadow whitespace-nowrap"
         title="Acesso exclusivo para Gerência/Chefe de Bar do WE ARE BASTARDS"
      >WE ARE BASTARDS | COZINHA - BAR - GERÊNCIA - EVENTOS</a>
    </div>
    <div class="max-w-md mx-auto flex justify-center">
      <a href="insumoscross_htf.php"
         class="text-sm font-semibold py-2 px-4 bg-black hover:bg-gray-800 text-white rounded shadow whitespace-nowrap"
         title="Acesso exclusivo para Gerência/Chefe de Bar do CROSS"
      >CROSS | COZINHA - BAR - GERÊNCIA - EVENTOS</a>
    </div>
    <div class="max-w-md mx-auto flex justify-center">
      <a href="insumos7tragos_htf.php"
         class="text-sm font-semibold py-2 px-4 bg-black hover:bg-gray-800 text-white rounded shadow whitespace-nowrap"
         title="Acesso exclusivo para Gerência/Chefe de Bar do 7TRAGOS"
      >7TRAGOS | COZINHA - BAR - GERÊNCIA - EVENTOS</a>
    </div>
  </div>

</main>

  <!-- Botões fixos no canto inferior direito -->
  <div class="fixed bottom-4 right-4 flex gap-2">
    <a href="analises/menu_analises_compras.php"
       class="text-xs font-semibold py-1 px-3 bg-blue-600 hover:bg-blue-700 text-white rounded shadow-lg"
    >Análise de Compras</a>
    <a href="exportar_pedido.php"
       class="text-xs font-semibold py-1 px-3 bg-yellow-500 hover:bg-yellow-600 text-gray-900 rounded shadow-lg"
    >Exportar Pedidos</a>
  </div>

</body>
</html>