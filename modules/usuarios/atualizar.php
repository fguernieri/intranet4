<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config/db.php';

// Só admin
if ($_SESSION['usuario_perfil'] !== 'admin') {
    echo "Acesso restrito.";
    exit;
}

// Captura dados
$id     = $_POST['id']     ?? null;
$nome   = $_POST['nome']   ?? '';
$email  = $_POST['email']  ?? '';
$cargo  = $_POST['cargo']  ?? '';
$setor  = $_POST['setor']  ?? '';
$perfil = $_POST['perfil'] ?? 'user';

if (!$id) {
    echo "ID inválido.";
    exit;
}

// Atualiza no banco
$stmt = $pdo->prepare("
    UPDATE usuarios SET
      nome   = :nome,
      email  = :email,
      cargo  = :cargo,
      setor  = :setor,
      perfil = :perfil
    WHERE id = :id
");
$stmt->execute([
    'nome'   => $nome,
    'email'  => $email,
    'cargo'  => $cargo,
    'setor'  => $setor,
    'perfil' => $perfil,
    'id'     => $id,
]);

// ID do usuário que editamos, para voltar na seleção do Admin
$user_id = $id;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Usuário Atualizado</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../assets/css/style.css">
  <meta http-equiv="refresh" content="1;url=admin_permissoes.php?user_id=<?= $user_id ?>&ok=1">
</head>
<body class="bg-gray-900 text-white flex items-center justify-center min-h-screen">

  <div class="bg-gray-800 p-8 rounded-lg shadow-md text-center">
    <h2 class="text-2xl font-bold mb-4 text-green-400">✔️ Usuário atualizado com sucesso!</h2>
    <p class="text-gray-300">Você será redirecionado de volta em instantes…</p>
    <p class="mt-6">
      <a href="admin_permissoes.php?user_id=<?= $user_id ?>&ok=1"
         class="text-yellow-400 hover:underline">
        Caso não seja redirecionado, clique aqui.
      </a>
    </p>
  </div>

</body>
</html>
