<?php
ob_start(); // inicia buffer de saída

require_once __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../sidebar.php';

// CRUD de módulos com nome, descricao e link
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    $action = $_POST['crud_action'];

    if ($action === 'create_module') {
        $nome = trim($_POST['nome_modulo'] ?? '');
        $descricao = trim($_POST['descricao_modulo'] ?? '');
        $link = trim($_POST['link_modulo'] ?? '');

        if ($nome && $descricao && $link) {
            $stmt = $pdo->prepare("INSERT INTO modulos (nome, descricao, link, ativo) VALUES (:nome, :descricao, :link, 1)");
            $stmt->execute([
                'nome' => $nome,
                'descricao' => $descricao,
                'link' => $link
            ]);
        }
        header("Location: ".$_SERVER['PHP_SELF']."?user_id=$user_id&ok=1&modulo=criado#modulos");
        exit;
    }

    if ($action === 'update_module') {
        $id = $_POST['modulo_id'] ?? null;
        $nome = trim($_POST['novo_nome'] ?? '');
        $descricao = trim($_POST['nova_descricao'] ?? '');
        $link = trim($_POST['novo_link'] ?? '');

        if ($id && $nome && $descricao && $link) {
            $stmt = $pdo->prepare("UPDATE modulos SET nome = :nome, descricao = :descricao, link = :link WHERE id = :id");
            $stmt->execute([
                'id' => $id,
                'nome' => $nome,
                'descricao' => $descricao,
                'link' => $link
            ]);
        }
        header("Location: ".$_SERVER['PHP_SELF']."?user_id=$user_id&ok=1&modulo=editado#modulos");
        exit;
    }

    if ($action === 'delete_module') {
        $id = $_POST['modulo_id'] ?? null;
        if ($id) {
            $stmt = $pdo->prepare("UPDATE modulos SET ativo = 0 WHERE id = :id");
            $stmt->execute(['id' => $id]);
        }
        header("Location: ".$_SERVER['PHP_SELF']."?user_id=$user_id&ok=1&modulo=excluido#modulos");
        exit;
    }
}

// … já dentro de admin_permissoes.php, depois de carregar $usuarios e $vendedores …
if (isset($_GET['user_id'])) {
  // buscar permissões existentes
  $stmt = $pdo->prepare(
    "SELECT vendedor_id 
       FROM user_vendedor_permissoes 
      WHERE usuario_id = :uid"
  );
  $stmt->execute(['uid'=>$_GET['user_id']]);
  $perms_v = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Somente admin
if ($_SESSION['usuario_perfil'] !== 'admin') {
    echo "Acesso restrito.";
    exit;
}

// Processa submissão de permissões
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_perms'])) {
    $user_id  = $_POST['user_id'];
    $selected = $_POST['modulos'] ?? [];

    $pdo->prepare("DELETE FROM modulos_usuarios WHERE usuario_id = :uid")
        ->execute(['uid' => $user_id]);

    $ins = $pdo->prepare("INSERT INTO modulos_usuarios (usuario_id, modulo_id) VALUES (:uid, :mid)");
    foreach ($selected as $mid) {
        $ins->execute(['uid' => $user_id, 'mid' => $mid]);
    }
    header("Location: admin_permissoes.php?user_id=$user_id&ok=1");
    exit;
}
// Verifica se o checkbox "show_inativos" veio marcado
$show_inativos = isset($_GET['show_inativos']);
// Define o filtro: 0 = inativos, 1 = ativos (padrão)
$status = $show_inativos ? 0 : 1;

// Prepara e executa a query com filtro dinâmico
$stmt = $pdo->prepare("SELECT * FROM vendedores WHERE ativo = :status ORDER BY nome");
$stmt->execute(['status' => $status]);
$vendedores = $stmt->fetchAll();

// Dados para listagem
$usuarios = $pdo->query("SELECT id, nome, email, perfil FROM usuarios ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$modulos  = $pdo->query("SELECT id, nome, descricao, link FROM modulos WHERE ativo = 1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$user_id  = $_GET['user_id'] ?? null;
$perms    = [];

if ($user_id) {
    $stmt = $pdo->prepare("SELECT modulo_id FROM modulos_usuarios WHERE usuario_id = :uid");
    $stmt->execute(['uid' => $user_id]);
    $perms = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$ok = isset($_GET['ok']);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta charset="UTF-8">
  <title>Admin - Gerência de Usuários & Permissões</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-900 text-white min-h-screen flex">

<main class="flex-1 p-4 sm:p-10">

  <!-- Usuários -->
  <section class="mb-10">
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold">Gestão de Usuários</h1>
      <a href="novo.php" class="btn-acao py-2 px-4">+ Novo Usuário</a>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm text-left border-collapse">
        <thead>
          <tr class="bg-gray-800">
            <th class="p-2">Nome</th>
            <th class="p-2">E-mail</th>
            <th class="p-2">Perfil</th>
            <th class="p-2">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($usuarios as $u): ?>
          <tr class="border-t hover:bg-gray-700">
            <td class="p-2"><?= htmlspecialchars($u['nome']) ?></td>
            <td class="p-2"><?= htmlspecialchars($u['email']) ?></td>
            <td class="p-2"><?= htmlspecialchars($u['perfil']) ?></td>
            <td class="p-2 space-x-2">
              <a href="editar.php?id=<?= $u['id'] ?>" class="text-blue-400 hover:underline">Editar</a>
              <?php if ($u['id'] != $_SESSION['usuario_id']): ?>
              <a href="desativar.php?id=<?= $u['id'] ?>" class="text-red-400 hover:underline" onclick="return confirm('Desativar usuário?');">Excluir</a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  
  <hr class="divider_yellow">
	
  <!-- Permissões -->
  <section>
    <h2 class="text-2xl font-bold mb-4">Definir Permissões</h2>
    <form method="GET" class="mb-6">
      <label class="block mb-2">Selecione o usuário:</label>
      <select name="user_id" onchange="this.form.submit()" class="border rounded px-3 py-2 bg-gray-700">
        <option value="">-- selecione --</option>
        <?php foreach ($usuarios as $u): ?>
          <option value="<?= $u['id'] ?>" <?= $u['id'] == $user_id ? 'selected' : '' ?>>
            <?= htmlspecialchars($u['nome'].' ('.$u['email'].')') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>

    <?php if ($user_id): ?>
      <?php if ($ok): ?>
        <p class="bg-green-500 p-2 rounded mb-4">Permissões atualizadas com sucesso!</p>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="user_id" value="<?= $user_id ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          <?php foreach ($modulos as $m): ?>
            <label class="flex items-center space-x-2">
              <input type="checkbox" name="modulos[]" value="<?= $m['id'] ?>" <?= in_array($m['id'], $perms) ? 'checked' : '' ?> class="form-checkbox h-5 w-5 text-yellow-500">
              <span><?= htmlspecialchars($m['nome']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <button type="submit" name="save_perms" class="btn-acao">
          Salvar Permissões
        </button>
      </form>
    <?php endif; ?>
  </section>
  <hr class="divider_yellow">

  <!-- Módulos -->
  <?php if ($_SESSION['usuario_perfil'] === 'admin'): ?>
  <section id="modulos" class="mt-8">
    <h2 class="text-2xl font-bold mb-4">Gerenciar Módulos</h2>
	<?php if (isset($_GET['modulo'])): ?>
	  <?php if ($_GET['modulo'] === 'criado'): ?>
		<p class="bg-green-500 p-2 rounded mb-4">Módulo adicionado com sucesso!</p>
	  <?php elseif ($_GET['modulo'] === 'editado'): ?>
		<p class="bg-green-500 p-2 rounded mb-4">Módulo atualizado com sucesso!</p>
	  <?php elseif ($_GET['modulo'] === 'excluido'): ?>
		<p class="bg-green-500 p-2 rounded mb-4">Módulo removido com sucesso!</p>
	  <?php endif; ?>
	<?php endif; ?>
	
    <!-- Criar -->
    <form method="POST" class="space-y-4 mb-8">
      <input type="hidden" name="crud_action" value="create_module">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <input type="text" name="nome_modulo" placeholder="Nome do módulo" required class="w-full px-3 py-2 rounded bg-gray-800 border border-gray-600 text-white" />
        <input type="text" name="descricao_modulo" placeholder="Descrição" required class="w-full px-3 py-2 rounded bg-gray-800 border border-gray-600 text-white" />
        <input type="text" name="link_modulo" placeholder="Link (ex: /financeiro.php)" required class="w-full px-3 py-2 rounded bg-gray-800 border border-gray-600 text-white" />
      </div>
      <button type="submit" class="btn-acao">
        + Adicionar Módulo
      </button>
    </form>

    <!-- Lista -->
    <div class="overflow-x-auto">
      <table class="w-full text-sm text-left border-collapse">
        <thead class="bg-gray-800">
          <tr>
            <th class="p-2">Nome</th>
            <th class="p-2">Descrição</th>
            <th class="p-2">Link</th>
            <th class="p-2">Ações</th>
          </tr>
        </thead>
        <tbody>
		  <?php foreach ($modulos as $mod): ?>
			<tr class="border-t border-gray-700 hover:bg-gray-800">
			  <td class="p-2">
				<input form="form-<?= $mod['id'] ?>" type="text" name="novo_nome" value="<?= htmlspecialchars($mod['nome']) ?>" class="w-full px-2 py-1 rounded bg-gray-800 border border-gray-600 text-white">
			  </td>
			  <td class="p-2">
				<input form="form-<?= $mod['id'] ?>" type="text" name="nova_descricao" value="<?= htmlspecialchars($mod['descricao'] ?? '') ?>" class="w-full px-2 py-1 rounded bg-gray-800 border border-gray-600 text-white">
			  </td>
			  <td class="p-2">
				<input form="form-<?= $mod['id'] ?>" type="text" name="novo_link" value="<?= htmlspecialchars($mod['link'] ?? '') ?>" class="w-full px-2 py-1 rounded bg-gray-800 border border-gray-600 text-white">
			  </td>
			  <td class="items-center p-2 gap-4 flex">
				<form id="form-<?= $mod['id'] ?>" method="POST">
				  <input type="hidden" name="crud_action" value="update_module">
				  <input type="hidden" name="modulo_id" value="<?= $mod['id'] ?>">
				  <button type="submit" class="btn-acao">Salvar</button>
				</form>
				<form method="POST" onsubmit="return confirm('Remover este módulo?');">
				  <input type="hidden" name="crud_action" value="delete_module">
				  <input type="hidden" name="modulo_id" value="<?= $mod['id'] ?>">
				  <button type="submit" class="btn-acao-vermelho">Excluir</button>
				</form>
			  </td>
			</tr>
		  <?php endforeach; ?>
		</tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>
  <hr class="divider_yellow">

<!-- Vendedores (mesmo layout de Usuários, com desktop table + mobile cards) -->
<section class="mt-8">
  <div class="flex justify-between items-center mb-4">
    <h2 class="text-2xl font-bold mb-4">Vendedores</h2>
    <!-- Toggle para mostrar inativos -->
    <div>
    <form method="GET" ">
      <label class="custom-switch">
        <input
          type="checkbox"
          name="show_inativos"
          value="1"
          onchange="this.form.submit()"
          class="custom-switch-input"
          <?= isset($_GET['show_inativos']) ? 'checked' : '' ?>
        >
        <span class="custom-switch-slider"></span>
        <span class="custom-switch-label">Mostrar inativos</span>
      </label>
    </form>
    </div>  
    <a href="vendedor_form.php" class="btn-acao">
      + Novo Vendedor
    </a>
  </div>

  <!-- Desktop: tabela oculta em telas pequenas -->
  <div class="overflow-x-auto bg-gray-800 rounded shadow hidden md:block">
    <table class="min-w-full text-sm text-left border-collapse">
      <thead>
        <tr class="bg-gray-800">
          <th class="p-2">ID</th>
          <th class="p-2">Nome</th>
          <th class="p-2">Código</th>
          <th class="p-2">Região</th>
          <th class="p-2">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($vendedores as $v): ?>
        <tr class="border-t hover:bg-gray-700">
          <td class="p-2"><?= $v['id'] ?></td>
          <td class="p-2"><?= htmlspecialchars($v['nome']) ?></td>
          <td class="p-2"><?= htmlspecialchars($v['codigo']) ?></td>
          <td class="p-2"><?= htmlspecialchars($v['regiao']) ?></td>
          <td class="p-2 space-x-2">
            <a href="vendedor_form.php?id=<?= $v['id'] ?>"
               class="text-yellow-400 hover:underline text-sm">Editar</a>
            <a href="vendedor_delete.php?id=<?= $v['id'] ?>"
               class="text-red-500 hover:underline text-sm"
               onclick="return confirm('Deseja desativar este vendedor?');">
              Desativar
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Mobile: cards visíveis apenas em telas pequenas -->
  <div class="grid grid-cols-1 gap-4 md:hidden">
    <?php foreach ($vendedores as $v): ?>
    <div class="bg-gray-800 p-4 rounded shadow">
      <p class="text-sm"><strong>ID:</strong> <?= $v['id'] ?></p>
      <p class="text-sm"><strong>Nome:</strong> <?= htmlspecialchars($v['nome']) ?></p>
      <p class="text-sm"><strong>Código:</strong> <?= htmlspecialchars($v['codigo']) ?></p>
      <p class="text-sm"><strong>Região:</strong> <?= htmlspecialchars($v['regiao']) ?></p>
      <div class="mt-2 space-x-2">
        <a href="vendedor_form.php?id=<?= $v['id'] ?>"
           class="btn-acao">Editar</a>
        <a href="vendedor_delete.php?id=<?= $v['id'] ?>"
           class="btn-acao bg-red-600 hover:bg-red-700 text-white"
           onclick="return confirm('Deseja desativar este vendedor?');">Desativar</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<hr class="divider_yellow">

<section class="mt-8 pb-8">
  <h2 class="text-2xl font-bold pb-6">Permissões de Vendedores</h2>
  <form method="GET" class="pb-4">
    <label>Selecione o usuário:</label>
    <select name="user_id" onchange="this.form.submit()"
            class="border rounded px-1 py-1 bg-gray-700 text-white">
      <option value="">-- selecione --</option>
      <?php foreach($usuarios as $u): ?>
      <option value="<?= $u['id']?>" 
              <?= (isset($_GET['user_id']) && $_GET['user_id']==$u['id'])?'selected':''?>>
        <?= htmlspecialchars($u['nome'].' ('.$u['email'].')')?>
      </option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if (!empty($_GET['user_id'])): ?>
  <form method="POST" action="salvar_permissoes_vendedor.php">
    <input type="hidden" name="usuario_id" value="<?= $_GET['user_id'] ?>">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?php foreach($vendedores as $v): ?>
      <label class="flex items-center space-x-2">
        <input type="checkbox" name="vendedores[]"
               value="<?= $v['id'] ?>"
               <?= in_array($v['id'],$perms_v)?'checked':'' ?>
               class="h-4 w-4 text-yellow-500 bg-gray-700 border-gray-600 rounded">
        <span class="text-white"><?= htmlspecialchars($v['nome'].' ('.$v['codigo'].')') ?></span>
      </label>
      <?php endforeach; ?>
    </div>
    <button type="submit" 
            class="btn-acao">
      Salvar permissões
    </button>
  </form>
  <?php endif; ?>
</section>

</main>
</body>
</html>
<?php
// 3) No final do arquivo, quando todo o HTML já foi "imprimido" no buffer:
$htmlCompleto = ob_get_clean();  // pega tudo e limpa o buffer

// (opcional) você pode manipular $htmlCompleto: str_replace, minify, etc.
// $htmlCompleto = str_replace('foo', 'bar', $htmlCompleto);

// finalmente envia ao cliente:
echo $htmlCompleto;
?>