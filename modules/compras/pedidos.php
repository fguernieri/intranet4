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

  <h3 class="text-lg font-bold text-yellow-400 text-center mb-4">SELECIONE A FILIAL E REALIZE SEU PEDIDO</h3>

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

  <!-- Modal de Seleção de Setor -->
  <div id="setor-modal" class="fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-50">
    <div class="bg-gray-800 rounded-lg p-6 w-96 max-w-md">
      <h2 class="text-2xl font-bold text-yellow-400 mb-4 text-center">Selecione o Setor</h2>
      <p class="text-gray-300 mb-6 text-center">Qual setor está fazendo este pedido?</p>
      
      <div class="space-y-3">
        <button type="button" class="setor-btn w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded transition-colors" data-setor="COZINHA">
          🍳 COZINHA
        </button>
        <button type="button" class="setor-btn w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded transition-colors" data-setor="BAR">
          🍹 BAR
        </button>
        <button type="button" class="setor-btn w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-4 rounded transition-colors" data-setor="GERENCIA">
          👔 GERÊNCIA
        </button>
        <!-- Adicione este botão -->
        <button type="button" class="setor-btn w-full bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-3 px-4 rounded transition-colors" data-setor="EVENTO">
          🎉 EVENTO
        </button>
      </div>
      
      <p class="text-xs text-gray-400 mt-4 text-center">Esta seleção é obrigatória para prosseguir</p>
    </div>
  </div>

</body>
</html>