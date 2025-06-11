<?php
require_once __DIR__ . '/../../config/db.php';
require_once '../../sidebar.php';

// Verifica se admin
if ($_SESSION['usuario_perfil'] !== 'admin') {
    echo "Acesso restrito.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Novo Usuário - Intranet Bastards</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col">


  <!-- Conteúdo centralizado -->
  <main class="flex-1 flex items-center justify-center px-4 pb-8">
    <div class="w-full max-w-xs sm:max-w-sm md:max-w-md lg:max-w-lg bg-gray-800 p-6 rounded-lg shadow-lg">
      <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-yellow-400 mb-4 sm:mb-6">Cadastrar Novo Usuário</h2>

      <?php if (isset($_GET['ok'])): ?>
        <p class="bg-green-500 text-white p-2 rounded mb-4 text-center">
          Usuário cadastrado com sucesso!
        </p>
      <?php endif; ?>

      <form action="salvar.php" method="POST" class="space-y-3 sm:space-y-4">
        <div>
          <label class="block mb-1 text-sm sm:text-base">Nome completo</label>
          <input type="text" name="nome" placeholder="Nome" required
                 class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400">
        </div>
        <div>
          <label class="block mb-1 text-sm sm:text-base">E-mail</label>
          <input type="email" name="email" placeholder="E-mail" required
                 class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400">
        </div>
        <div>
          <label class="block mb-1 text-sm sm:text-base">Senha</label>
          <input type="password" name="senha" placeholder="Senha" required
                 class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400">
        </div>
        <div>
          <label class="block mb-1 text-sm sm:text-base">Cargo</label>
          <input type="text" name="cargo" placeholder="Cargo"
                 class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400">
        </div>
        <div>
          <label class="block mb-1 text-sm sm:text-base">Setor</label>
          <input type="text" name="setor" placeholder="Setor"
                 class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400">
        </div>
        <div>
          <label class="block mb-1 text-sm sm:text-base">Perfil</label>
          <select name="perfil"
                  class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white">
            <option value="user">Usuário</option>
            <option value="supervisor">Supervisor</option>
            <option value="admin">Administrador</option>
          </select>
        </div>
        <button type="submit"
                class="w-full bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold py-2 sm:py-3 px-4 rounded transition duration-200">
          Cadastrar
        </button>
		<div class="text-center mt-4">
        <a href="admin_permissoes.php" class="text-sm text-gray-400 hover:text-yellow-400 transition">← Voltar</a>
		</div>
      </form>
    </div>
  </main>
</body>
</html>
