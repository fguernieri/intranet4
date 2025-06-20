<?php
// Autenticação
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

// Restringe o acesso a admin e supervisor
if (!isset($_SESSION['usuario_perfil']) || !in_array($_SESSION['usuario_perfil'], ['admin', 'supervisor'])) {
    // Em vez de 'echo', que pode quebrar o layout, podemos redirecionar ou mostrar uma página de erro.
    // Por enquanto, vamos manter simples.
    die("Acesso restrito. Você não tem permissão para ver esta página.");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Módulo Financeiro - Gestão</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="/assets/css/style.css" rel="stylesheet">
</head>
<?php
require_once __DIR__ . '/../../sidebar.php';
?>
<body class="bg-gray-900 text-gray-100 flex min-h-screen">
<main class="flex-1 flex flex-col items-center p-6 pt-16">
  <div class="text-center w-full max-w-5xl">
    <h1 class="text-4xl font-bold text-yellow-400 mb-4">Módulo Financeiro</h1>
    <p class="text-gray-400 mb-12">Selecione uma das ferramentas abaixo para começar.</p>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
      
      <!-- Card Simulador Financeiro -->
      <a href="Home.php" class="group block bg-gray-800 border border-gray-700 rounded-xl shadow-lg hover:border-yellow-400 hover:scale-105 transition-all duration-300 p-6">
        <div class="flex items-start gap-6">
          <!-- Icon -->
          <div class="text-yellow-400 mt-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
          </div>
          <!-- Content -->
          <div class="text-left">
            <h2 class="text-xl font-bold text-white mb-2 group-hover:text-yellow-400 transition-colors">SIMULADOR FINANCEIRO</h2>
            <p class="text-gray-400">Módulo para simular cenários e definir metas financeiras.</p>
          </div>
        </div>
      </a>

      <!-- Card Acompanhamento Financeiro -->
      <a href="acompanhamento_financeiro.php" class="group block bg-gray-800 border border-gray-700 rounded-xl shadow-lg hover:border-yellow-400 hover:scale-105 transition-all duration-300 p-6">
        <div class="flex items-start gap-6">
          <!-- Icon -->
          <div class="text-yellow-400 mt-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
          </div>
          <!-- Content -->
          <div class="text-left">
            <h2 class="text-xl font-bold text-white mb-2 group-hover:text-yellow-400 transition-colors">ACOMPANHAMENTO FINANCEIRO</h2>
            <p class="text-gray-400">Módulo para acompanhamento diário das metas financeiras.</p>
          </div>
        </div>
      </a>

    </div>
  </div>
</main>
</body>
</html>