<?php
require_once '../../auth.php';
$erro    = $_GET['erro'] ?? '';
$sucesso = isset($_GET['ok']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Alterar Senha - Bastards Brewery</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-900 flex items-center justify-center min-h-screen text-white p-4">
  <div class="bg-gray-800 p-4 sm:p-6 md:p-8 rounded-lg shadow-lg w-full max-w-xs sm:max-w-sm md:max-w-md">
    <h2 class="text-xl sm:text-2xl font-bold mb-4 sm:mb-6 text-yellow-400 text-center">Alterar Senha</h2>

    <?php if ($erro === 'atual'): ?>
      <p class="bg-red-500 p-2 rounded mb-4 text-center text-white">Senha atual incorreta.</p>
    <?php elseif ($erro === 'confirma'): ?>
      <p class="bg-red-500 p-2 rounded mb-4 text-center text-white">Nova senha e confirmação não conferem.</p>
    <?php elseif ($erro === 'igual'): ?>
      <p class="bg-red-500 p-2 rounded mb-4 text-center text-white">A nova senha não pode ser igual à anterior.</p>
    <?php elseif ($sucesso): ?>
      <p class="bg-green-500 p-2 rounded mb-4 text-center text-white">Senha atualizada com sucesso!</p>
    <?php endif; ?>

    <form action="atualizar_senha.php" method="POST" class="space-y-3 sm:space-y-4">
      <div>
        <label class="block mb-1 text-sm sm:text-base">Senha atual</label>
        <input type="password" name="senha_atual" required
               class="border border-gray-600 rounded w-full px-3 py-2 bg-gray-700 placeholder-gray-400">
      </div>

      <div>
        <label class="block mb-1 text-sm sm:text-base">Nova senha</label>
        <input type="password" name="senha_nova" required
               class="border border-gray-600 rounded w-full px-3 py-2 bg-gray-700 placeholder-gray-400">
      </div>

      <div>
        <label class="block mb-1 text-sm sm:text-base">Confirmar nova senha</label>
        <input type="password" name="senha_confirmar" required
               class="border border-gray-600 rounded w-full px-3 py-2 bg-gray-700 placeholder-gray-400">
      </div>

      <button type="submit"
              class="w-full bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold py-2 sm:py-3 px-4 rounded transition duration-200">
        Atualizar Senha
      </button>

      <div class="text-center mt-3 sm:mt-4">
        <a href="../../painel.php" class="text-gray-400 hover:text-yellow-400 transition text-sm sm:text-base">← Voltar para o painel</a>
      </div>
    </form>
  </div>
</body>
</html>