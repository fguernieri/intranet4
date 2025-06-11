<?php
session_start();
$erro = isset($_GET['erro']) ? true : false;
$msg = $_GET['msg'] ?? null;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Login - Bastards Brewery</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-900 flex items-center justify-center min-h-screen p-4">

  <div class="bg-gray-800 p-4 sm:p-6 md:p-8 rounded-lg shadow-lg w-full max-w-xs sm:max-w-sm text-white">

    <div class="flex justify-center mb-2 sm:mb-4">
      <img src="assets/img/logo.png" alt="Bastards Brewery" class="w-60 sm:w-80">
    </div>

    <h1 class="text-xl sm:text-2xl font-bold text-center text-yellow-400 mb-4 sm:mb-6">Área Restrita</h1>

    <?php if ($erro): ?>
      <p class="bg-red-500 text-white text-sm p-2 rounded mb-4 text-center">E-mail ou senha inválidos.</p>
    <?php endif; ?>

    <form action="verifica_login.php" method="POST" class="space-y-3 sm:space-y-4">
      <div>
        <input
          type="email"
          name="email"
          placeholder="E-mail"
          required
          class="w-full p-2 sm:p-3 rounded bg-gray-700 border border-gray-600 text-white placeholder-gray-400"
        >
      </div>
      
      <div>
        <input
          type="password"
          name="senha"
          placeholder="Senha"
          required
          class="w-full p-2 sm:p-3 rounded bg-gray-700 border border-gray-600 text-white placeholder-gray-400"
        >
      </div>
      
      <button
        type="submit"
        class="w-full bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold py-2 sm:py-3 px-4 rounded transition duration-200"
      >
        Entrar
      </button>
    </form>
    <div class="mt-3 sm:mt-4 text-center">
      <a href="#" class="text-gray-400 hover:text-yellow-400 transition text-sm sm:text-base">Esqueceu a senha?.</a>
    </div>
  </div>
    <?php if ($msg === 'sessao_expirada'): ?>
    <div id="toast-msg" class="fixed top-4 right-4 bg-yellow-500 text-gray-900 px-4 py-3 rounded shadow-lg z-50 transition-opacity duration-300 opacity-0">
      Sua sessão expirou por inatividade.
    </div>
    <script>
      const toast = document.getElementById('toast-msg');
      if (toast) {
        toast.classList.remove('opacity-0');
        setTimeout(() => {
          toast.classList.add('opacity-0');
        }, 4000); // Some após 4 segundos
      }
    </script>
  <?php endif; ?>
</body>
</html>
