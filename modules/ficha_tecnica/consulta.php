<?php
require_once '../../config/db.php';       // Conexão principal (intranet)
require_once '../../config/db_dw.php';    // Conexão secundária (cloud)
include '../../sidebar.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 🔍 Filtro de busca
$filtro = $_GET['filtro'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM ficha_tecnica WHERE nome_prato LIKE :filtro ORDER BY id DESC");
$stmt->execute([':filtro' => "%$filtro%"]);
$fichas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Consulta de Fichas Técnicas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../assets/css/style.css">

<!-- jQuery e DataTables -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex">
<style>
/* Remove os ícones de ordenação em todos os <th> do DataTable */
table.dataTable thead th.sorting:before,
table.dataTable thead th.sorting:after,
table.dataTable thead th.sorting_asc:before,
table.dataTable thead th.sorting_asc:after,
table.dataTable thead th.sorting_desc:before,
table.dataTable thead th.sorting_desc:after {
  display: none !important;
}
</style>

  <!-- Alerta de exclusão -->
  <?php if (isset($_GET['excluido'])): ?>
    <div id="alert-box" class="fixed top-6 left-1/2 transform -translate-x-1/2 z-50 animate-slideDown transition-all duration-300">
      <div class="bg-green-600 text-white px-6 py-4 rounded shadow text-center font-semibold">
        ✅ Ficha excluída com sucesso!
      </div>
    </div>
    <script>
      setTimeout(() => {
        const alert = document.getElementById('alert-box');
        if (alert) {
          alert.classList.add('opacity-0');
          alert.classList.remove('animate-slideDown');
          setTimeout(() => alert.remove(), 500);
        }
      }, 3000);
    </script>
    <style>
      @keyframes slideDown {
        0% { opacity: 0; transform: translateY(-20px) translateX(-50%); }
        100% { opacity: 1; transform: translateY(0) translateX(-50%); }
      }
      .animate-slideDown {
        animation: slideDown 0.4s ease-out forwards;
      }
    </style>
  <?php endif; ?>

  <div class="max-w-6xl mx-auto py-6">
    <h1 class="text-3xl font-bold text-cyan-400 text-center mb-8">Consulta de Fichas Técnicas</h1>

    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
      <form class="w-full flex flex-col sm:flex-row items-stretch sm:items-center sm:justify-start gap-2">
        <input 
          type="text" 
          name="filtro"
          id="searchBox"
          value="<?= htmlspecialchars($filtro) ?>" 
          placeholder="Buscar por nome do prato..."
          class="w-full sm:w-96 p-3 rounded bg-gray-800 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-cyan-500"
        >
      </form>

      <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
        <a href="consultar_alteracoes.php"
           class="w-full sm:w-auto text-center bg-purple-500 hover:bg-purple-600 text-white px-6 py-3 rounded shadow font-semibold">
          📜 Histórico
        </a>
        <a href="cadastrar_ficha.php"
           class="w-full sm:w-auto text-center bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded shadow font-semibold">
          ➕ Nova Ficha
        </a>
      </div>
    </div>

    <?php if (!empty($fichas)): ?>
      <!-- 💻 Desktop: Tabela -->
      <div class="overflow-x-auto bg-gray-800 rounded shadow hidden md:block">
        <table class="min-w-full text-sm text-center" id="tabela-consulta">
          <thead class="bg-gray-700 text-cyan-300">
            <tr>
              <th class="p-3">Farol</th>
              <th class="p-3">Cód Cloudify</th>
              <th class="p-3">Nome do Prato</th>
              <th class="p-3">Rendimento</th>
              <th class="p-3">Data</th>
              <th class="p-3">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-700">
            <?php foreach ($fichas as $ficha): ?>
              <tr class="hover:bg-gray-700">
                <td class="p-2">
                  <?php
                    $cores_farol = [
                      'cinza'    => 'bg-gray-400',
                      'verde'    => 'bg-green-500',
                      'amarelo'  => 'bg-yellow-400',
                      'vermelho' => 'bg-red-500',
                    ];
                    $cor_classe = $cores_farol[$ficha['farol']] ?? 'bg-gray-400'; // default: cinza
                  ?>
                  <div id="farol-<?= $ficha['codigo_cloudify'] ?>" class="w-4 h-4 rounded-full mx-auto <?= $cor_classe ?>"></div>
                </td>
                <td class="p-2"><?= $ficha['codigo_cloudify'] ?></td>
                <td class="p-2"><?= htmlspecialchars($ficha['nome_prato']) ?></td>
                <td class="p-2"><?= $ficha['rendimento'] ?></td>
                <td class="p-2"><?= date('d/m/Y', strtotime($ficha['data_criacao'])) ?></td>
                <td class="p-2 space-x-2">
                  <a href="visualizar_ficha.php?id=<?= $ficha['id'] ?>" class="text-cyan-400 hover:underline">Ver</a>
                  <a href="editar_ficha_form.php?id=<?= $ficha['id'] ?>" class="text-yellow-400 hover:underline">Editar</a>
                  <a href="compara_ficha.php?cod=<?= $ficha['codigo_cloudify'] ?>" class="text-green-600 hover:underline text-sm">Comparar</a>
                  <a href="historico.php?id=<?= $ficha['id'] ?>" class="text-purple-400 hover:underline">Histórico</a>
                  <a href="excluir_ficha.php?id=<?= $ficha['id'] ?>" class="text-red-500 hover:underline">Excluir</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <button id="btn-farol" onclick="rodarFarol()" class="bg-blue-500 hover:bg-blue-600 text-white font-semibold px-4 py-2 rounded mt-4">
        🚦 Rodar Farol
      </button>
      <button 
        onclick="abrirModalImportacao()"
        class="bg-orange-500 hover:bg-orange-600 text-white font-semibold px-4 py-2 rounded"
      >
        📥 Importar CSV Insumos
      </button>
      <button 
        onclick="abrirImportProdutos()"
        class="bg-orange-500 hover:bg-green-600 text-white font-semibold px-4 py-2 rounded"
      >
        📥 Importar XLSX Produtos
      </button>

    <?php else: ?>
      <p class="text-center text-gray-400 mt-10">Nenhuma ficha encontrada com o filtro: <strong><?= htmlspecialchars($filtro) ?></strong></p>
    <?php endif; ?>
  </div>

<script>
const frasesLoader = [
  "☕ Preparando insumos com carinho...",
  "🧪 Analisando diferença molecular dos ingredientes...",
  "💾 Injetando dados no Cloudify...",
  "🧠 Calculando divergência lógica...",
  "🚨 Procurando fichas zumbis...",
  "🔍 Escaneando gramaturas...",
  "🥷 Invadindo banco de dados sigilosos...",
  "🤖 Farolizando as fichas técnicas..."
];

async function rodarFarol() {
  const btn = document.getElementById('btn-farol');
  const loader = document.getElementById('loader-overlay');
  const texto = document.getElementById('loader-text');

  loader.classList.remove('hidden');
  btn.disabled = true;
  btn.classList.add('opacity-50', 'cursor-not-allowed');

  const farois = document.querySelectorAll('[id^="farol-"]');

  let i = 0;
  const fraseInterval = setInterval(() => {
    texto.innerText = frasesLoader[i % frasesLoader.length];
    i++;
  }, 1500);

  for (const el of farois) {
    const cod = el.id.replace('farol-', '');

    try {
      const res = await fetch('./consulta_farol.php?cod_cloudify=' + encodeURIComponent(cod));
      const data = await res.json();

      let cor = 'bg-gray-400';
      if (data.status === 'verde') cor = 'bg-green-500';
      else if (data.status === 'amarelo') cor = 'bg-yellow-400';
      else if (data.status === 'vermelho') cor = 'bg-red-500';

      el.className = `w-4 h-4 rounded-full ${cor} mx-auto`;
    } catch (e) {
      console.error(`Erro no farol para código ${cod}`, e);
    }
  }

  clearInterval(fraseInterval);
  loader.classList.add('hidden');
  btn.disabled = false;
  btn.classList.remove('opacity-50', 'cursor-not-allowed');
}

function abrirModalImportacao() {
  const modal = document.getElementById('modal-importacao');
  const iframe = document.getElementById('iframe-importacao');
  iframe.src = 'import_csv.php';
  modal.classList.remove('hidden');
}

function fecharModalImportacao() {
  const modal = document.getElementById('modal-importacao');
  const iframe = document.getElementById('iframe-importacao');
  modal.classList.add('hidden');
  iframe.src = ''; // limpa o conteúdo
  location.reload(); // 🔄 força o refresh da página principal
}

function abrirImportProdutos() {
  const modal = document.getElementById('modal-importacao');
  const iframe = document.getElementById('iframe-importacao');
  iframe.src = 'import_produtos.php';
  modal.classList.remove('hidden');
}

function fecharImportProdutos() {
  const modal = document.getElementById('modal-importacao');
  const iframe = document.getElementById('iframe-importacao');
  modal.classList.add('hidden');
  iframe.src = ''; // limpa o conteúdo
  location.reload(); // 🔄 força o refresh da página principal
}


</script>

<!-- Terminal-style funny loader -->
<div id="loader-overlay" class="fixed inset-0 bg-black bg-opacity-80 z-50 flex items-center justify-center hidden">
  <div class="bg-gray-900 p-6 rounded shadow-lg text-green-400 font-mono text-sm space-y-3 text-center border border-green-500">
    <div class="animate-pulse text-lime-400">[LOADING] 🚦 Iniciando protocolo de verificação...</div>
    <div id="loader-text">☕ Preparando insumos com carinho...</div>
    <div class="flex justify-center">
      <svg class="animate-spin h-8 w-8 text-green-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
      </svg>
    </div>
  </div>
</div>

<!-- Modal Overlay Importação -->
<div id="modal-importacao" class="fixed inset-0 bg-black bg-opacity-70 z-50 hidden flex items-center justify-center">
  <div class="bg-white rounded-lg overflow-hidden shadow-lg max-w-3xl w-full h-[80%] flex flex-col">
    
    <!-- Header -->
    <div class="bg-gray-800 text-white p-4 flex justify-between items-center">
      <h2 class="text-lg font-semibold">📥 Importar Dados</h2>
      <button onclick="fecharModalImportacao()" class="text-red-400 hover:text-red-600 text-2xl leading-none">&times;</button>
    </div>
    
    <!-- Iframe -->
    <iframe 
      src="" 
      id="iframe-importacao" 
      class="flex-1 w-full border-none"
    ></iframe>
  </div>
</div>


<script>
$(document).ready(function() {
  // Inicializa o DataTable e guarda na variável "table"
  const table = $('#tabela-consulta').DataTable({
    language: {
      url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
    },
    pageLength: -1,      // exibe todos os registros
    ordering: true,
    order: [],           // sem ordenação inicial
    columnDefs: [
      { targets: [0,5], orderable: false }, // Farol e Ações não ordenáveis
      { targets: 5, searchable: false } // Ações fora da procura

    ],
    initComplete: function () {
      // Esconde os controles padrão
      $('.dataTables_length').hide();
      $('.dataTables_filter').hide();
    }
  });

  // Ao digitar no input, chama table.search() e redesenha a tabela
  $('#searchBox').on('keyup', function() {
    table.search(this.value).draw();
  });
});
</script>

</body>
</html>
