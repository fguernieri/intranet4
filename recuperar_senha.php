<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';

$sucesso = isset($_GET['ok']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email !== '') {
        $stmt = $pdo->prepare('SELECT id, nome FROM usuarios WHERE email = :email AND ativo = 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        if ($user) {
            $token = bin2hex(random_bytes(16));
            $hash  = hash('sha256', $token);
            $expira = date('Y-m-d H:i:s', time() + 3600);

            $pdo->prepare('DELETE FROM password_resets WHERE usuario_id = ?')->execute([$user['id']]);
            $ins = $pdo->prepare('INSERT INTO password_resets (usuario_id, token_hash, expires_at) VALUES (?, ?, ?)');
            $ins->execute([$user['id'], $hash, $expira]);

            $link = BASE_URL . '/redefinir_senha.php?token=' . $token;
            $assunto = 'Recuperação de senha';
            $mensagem = "Olá {$user['nome']},\n\n" .
                        "Clique no link abaixo para redefinir sua senha:\n" .
                        "$link\n\n" .
                        "Se você não solicitou, ignore este e-mail.";
            @mail($email, $assunto, $mensagem);
        }
    }
    header('Location: recuperar_senha.php?ok=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Recuperar Senha - Bastards Brewery</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-900 flex items-center justify-center min-h-screen text-white p-4">
  <div class="bg-gray-800 p-4 sm:p-6 md:p-8 rounded-lg shadow-lg w-full max-w-xs sm:max-w-sm">
    <h2 class="text-xl sm:text-2xl font-bold text-yellow-400 mb-4 sm:mb-6 text-center">Recuperar Senha</h2>
    <?php if ($sucesso): ?>
      <p class="bg-green-500 text-white text-sm p-2 rounded mb-4 text-center">Se o e-mail estiver cadastrado, enviaremos as instruções.</p>
    <?php endif; ?>
    <form action="" method="POST" class="space-y-3 sm:space-y-4">
      <div>
        <label class="block mb-1 text-sm sm:text-base">E-mail</label>
        <input type="email" name="email" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400">
      </div>
      <button type="submit" class="w-full bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold py-2 sm:py-3 px-4 rounded transition duration-200">Enviar</button>
      <div class="text-center mt-3 sm:mt-4">
        <a href="login.php" class="text-gray-400 hover:text-yellow-400 transition text-sm sm:text-base">← Voltar ao login</a>
      </div>
    </form>
  </div>
</body>
</html>
