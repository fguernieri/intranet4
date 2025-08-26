<?php
ob_start();
require_once '../../config/db.php';
require_once '../../sidebar.php';


// Obter ID para edição
$id = $_GET['id'] ?? null;
$nome = '';
$codigo = '';
$regiao = '';
$ativo = 1;
$aliases = ['', '', ''];

// Se for edição, buscar dados existentes
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM vendedores WHERE id = ?");
    $stmt->execute([$id]);
    $v = $stmt->fetch();
    if ($v) {
        $nome   = $v['nome'];
        $codigo = $v['codigo'];
        $regiao = $v['regiao'];
        $ativo  = $v['ativo'];
    }
    // busca aliases existentes
    $stmtAlias = $pdo->prepare("SELECT alias FROM vendedores_alias WHERE vendedor_id = ? ORDER BY id");
    $stmtAlias->execute([$id]);
    $existentes = $stmtAlias->fetchAll(PDO::FETCH_COLUMN);
    foreach ($existentes as $i => $a) {
        if ($i < 3) {
            $aliases[$i] = $a;
        }
    }
}

// Processar submissão do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome   = trim($_POST['nome']);
    $codigo = trim($_POST['codigo']);
    $regiao = trim($_POST['regiao']);
    $ativo  = isset($_POST['ativo']) ? 1 : 0;
    $aliases = [
        trim($_POST['alias1'] ?? ''),
        trim($_POST['alias2'] ?? ''),
        trim($_POST['alias3'] ?? '')
    ];

    if ($id) {
        $sql = "UPDATE vendedores SET nome = ?, codigo = ?, regiao = ?, ativo = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $codigo, $regiao, $ativo, $id]);
    } else {
        $sql = "INSERT INTO vendedores (nome, codigo, regiao, ativo) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $codigo, $regiao, $ativo]);
        $id = (int)$pdo->lastInsertId();
    }
    // salva aliases
    $pdo->prepare("DELETE FROM vendedores_alias WHERE vendedor_id = ?")->execute([$id]);
    $stmtA = $pdo->prepare("INSERT INTO vendedores_alias (vendedor_id, alias) VALUES (?, ?)");
    foreach ($aliases as $a) {
        if ($a !== '') {
            $stmtA->execute([$id, $a]);
        }
    }

    // Redirecionar com flag de sucesso
    header('Location: admin_permissoes.php?ok=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title><?= $id ? 'Editar' : 'Cadastrar' ?> Vendedor - Intranet Bastards</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-900 text-white min-h-screen flex">
  <main class="flex-1 flex items-center justify-center px-4 pb-8">
    <div class="w-full max-w-xs sm:max-w-sm md:max-w-md lg:max-w-lg bg-gray-800 p-6 rounded-lg shadow-lg">
      <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-yellow-400 mb-4 sm:mb-6">
        <?= $id ? 'Editar' : 'Cadastrar' ?> Vendedor
      </h2>

      <?php if (isset($_GET['ok'])): ?>
      <p class="bg-green-500 text-white p-2 rounded mb-4 text-center">
        Vendedor <?= $id ? 'atualizado' : 'cadastrado' ?> com sucesso!
      </p>
      <?php endif; ?>

      <form method="POST" class="space-y-4 sm:space-y-5 w-full">
        <div>
          <label class="block mb-1 text-sm sm:text-base">Nome</label>
          <input type="text" name="nome" value="<?= htmlspecialchars($nome) ?>" required
                 class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400">
        </div>
        <div>
          <label class="block mb-1 text-sm sm:text-base">Código</label>
          <input type="text" name="codigo" value="<?= htmlspecialchars($codigo) ?>" required
                 class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400">
        </div>
        <div>
          <label class="block mb-1 text-sm sm:text-base">Região</label>
          <input type="text" name="regiao" value="<?= htmlspecialchars($regiao) ?>"
                 class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400">
        </div>
        <div>
          <label class="block mb-1 text-sm sm:text-base">Alias 1</label>
          <input type="text" name="alias1" value="<?= htmlspecialchars($aliases[0]) ?>"
                 class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400">
        </div>
        <div>
          <label class="block mb-1 text-sm sm:text-base">Alias 2</label>
          <input type="text" name="alias2" value="<?= htmlspecialchars($aliases[1]) ?>"
                 class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400">
        </div>
        <div>
          <label class="block mb-1 text-sm sm:text-base">Alias 3</label>
          <input type="text" name="alias3" value="<?= htmlspecialchars($aliases[2]) ?>"
                 class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white placeholder-gray-400">
        </div>
        <div class="flex items-center">
          <input id="ativo" type="checkbox" name="ativo" <?= $ativo ? 'checked' : '' ?>
                 class="h-4 w-4 text-yellow-500 bg-gray-700 border-gray-600 rounded">
          <label for="ativo" class="ml-2 text-sm sm:text-base">Ativo</label>
        </div>
        <div class="space-y-2 sm:flex sm:space-y-0 sm:space-x-2 w-full">
          <button type="submit"
                  class="w-full bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold py-3 px-4 rounded">
            <?= $id ? 'Salvar' : 'Cadastrar' ?>
          </button>
          <a href="admin_permissoes.php"
             class="w-full inline-block bg-gray-600 hover:bg-gray-500 text-white font-bold py-3 px-4 rounded text-center">
            Cancelar
          </a>
        </div>
      </form>
    </div>
  </main>
</body>
</html>
