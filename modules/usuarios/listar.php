<?php
require_once '../../auth.php';
require_once '../../config/db.php';

// Apenas admins podem ver
if ($_SESSION['usuario_perfil'] !== 'admin') {
    echo "Acesso restrito.";
    exit;
}

$stmt = $pdo->query("SELECT * FROM usuarios ORDER BY nome");

$usuarios = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Usuários</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-5xl mx-auto bg-white p-6 rounded shadow">
        <h2 class="text-2xl font-bold mb-4">Usuários Cadastrados</h2>
        <table class="w-full border">
            <thead>
                <tr class="bg-gray-200">
                    <th class="p-2 text-left">Nome</th>
                    <th class="p-2 text-left">E-mail</th>
                    <th class="p-2 text-left">Perfil</th>
                    <th class="p-2 text-left">Setor</th>
                    <th class="p-2 text-left">Status</th>
                    <th class="p-2 text-left">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr class="border-t hover:bg-gray-50">
                        <td class="p-2"><?= htmlspecialchars($usuario['nome']) ?></td>
                        <td class="p-2"><?= htmlspecialchars($usuario['email']) ?></td>
                        <td class="p-2"><?= $usuario['perfil'] ?></td>
                        <td class="p-2"><?= $usuario['setor'] ?></td>
                        <td class="p-2"><?= $usuario['ativo'] ? 'Ativo' : 'Inativo' ?></td>
                        <td class="p-2">
    			  <a href="editar.php?id=<?= $usuario['id'] ?>" class="text-blue-600">Editar</a>
			  <?php if ($usuario['ativo']): ?>
			    | <a href="desativar.php?id=<?= $usuario['id'] ?>" class="text-red-600" onclick="return confirm('Deseja realmente desativar este usuário?');">Desativar</a>
			  <?php else: ?>
        		    | <a href="ativar.php?id=<?= $usuario['id'] ?>" class="text-green-600" onclick="return confirm('Deseja reativar este usuário?');">Ativar</a>
    			  <?php endif; ?>
		    </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="mt-4">
            <a href="novo.php" class="text-green-600 underline">+ Cadastrar novo usuário</a>
        </div>
    </div>
</body>
</html>
