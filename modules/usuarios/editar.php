<?php
// modules/usuarios/editar.php
require_once __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../sidebar.php';



// Verifica se é admin
if ($_SESSION['usuario_perfil'] !== 'admin') {
    echo "Acesso restrito.";
    exit;
}

// Obtém ID do usuário
$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: listar.php'); exit;
}

// Busca dados do usuário
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id");
$stmt->execute(['id' => $id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$usuario) {
    echo "Usuário não encontrado.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <meta charset="UTF-8">
  <title>Editar Usuário - Intranet Bastards</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col sm:flex-row p-4">


  <!-- Conteúdo principal -->
  <main class="flex-1 p-4 sm:p-10 pt-20 sm:pt-10">
    <div class="bg-gray-800 p-8 rounded-lg shadow-md max-w-lg mx-auto">
      <h2 class="text-2xl font-bold mb-6 text-yellow-400">Editar Usuário</h2>
      <form action="atualizar.php" method="POST" class="space-y-6">
        <input type="hidden" name="id" value="<?= htmlspecialchars($usuario['id']) ?>">

        <div>
          <label class="block text-sm font-medium mb-1">Nome</label>
          <input type="text" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>"
                 class="w-full p-3 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400" required>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">E-mail</label>
          <input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>"
                 class="w-full p-3 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400" required>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Cargo</label>
          <input type="text" name="cargo" value="<?= htmlspecialchars($usuario['cargo']) ?>"
                 class="w-full p-3 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Setor</label>
          <input type="text" name="setor" value="<?= htmlspecialchars($usuario['setor']) ?>"
                 class="w-full p-3 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Perfil</label>
          <select name="perfil"
                  class="w-full p-3 bg-gray-700 border border-gray-600 rounded text-white">
            <option value="user" <?= $usuario['perfil'] === 'user' ? 'selected' : '' ?>>Usuário</option>
            <option value="supervisor" <?= $usuario['perfil'] === 'supervisor' ? 'selected' : '' ?>>Supervisor</option>
            <option value="admin" <?= $usuario['perfil'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
          </select>
        </div>

        <button type="submit" class="w-full bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold py-3 rounded">
          Atualizar
        </button>
		<div class="text-center mt-4">
        <a href="admin_permissoes.php" class="text-sm text-gray-400 hover:text-yellow-400 transition">← Voltar</a>
		</div>
      </form>
     
    </div>
  </main>
</body>
</html>
