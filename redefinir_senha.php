<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';

$token = $_GET['token'] ?? '';
$erro  = $_GET['erro'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $conf  = $_POST['confirmar'] ?? '';
    if ($senha !== $conf) {
        header('Location: redefinir_senha.php?token=' . urlencode($token) . '&erro=confirma');
        exit;
    }
    $stmt = $pdo->prepare('SELECT usuario_id FROM password_resets WHERE token_hash = :t AND expires_at > NOW()');
    $stmt->execute(['t' => hash('sha256', $token)]);
    $row = $stmt->fetch();
    if (!$row) {
        header('Location: recuperar_senha.php?erro=token');
        exit;
    }
    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE usuarios SET senha_hash = :h WHERE id = :id')->execute(['h' => $hash, 'id' => $row['usuario_id']]);
    $pdo->prepare('DELETE FROM password_resets WHERE usuario_id = :id')->execute(['id' => $row['usuario_id']]);
    header('Location: login.php?msg=senha_redefinida');
    exit;
}

// Validação inicial do token
$stmt = $pdo->prepare('SELECT usuario_id FROM password_resets WHERE token_hash = :t AND expires_at > NOW()');
$stmt->execute(['t' => hash('sha256', $token)]);
if (!$stmt->fetch()) {
    header('Location: recuperar_senha.php?erro=token');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Redefinir Senha - Bastards Brewery</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-900 flex items-center justify-center min-h-screen text-white p-4">
  <div class="bg-gray-800 p-4 sm:p-6 md:p-8 rounded-lg shadow-lg w-full max-w-xs sm:max-w-sm">
    <h2 class="text-xl sm:text-2xl font-bold text-yellow-400 mb-4 sm:mb-6 text-center">Redefinir Senha</h2>
    <?php if ($erro === 'confirma'): ?>
      <p class="bg-red-500 text-white text-sm p-2 rounded mb-4 text-center">A confirmação não confere.</p>
    <?php endif; ?>
    <form action="" method="POST" class="space-y-3 sm:space-y-4">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <div>
        <label class="block mb-1 text-sm sm:text-base">Nova senha</label>
        <input type="password" name="senha" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400">
      </div>
      <div>
        <label class="block mb-1 text-sm sm:text-base">Confirmar senha</label>
        <input type="password" name="confirmar" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400">
      </div>
      <button type="submit" class="w-full bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold py-2 sm:py-3 px-4 rounded transition duration-200">Atualizar Senha</button>
    </form>
  </div>
</body>
</html>
