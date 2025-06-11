<?php
// Inicia sessão, protege página e configura DB
require_once 'auth.php';
require_once 'config/db.php';
require_once 'sidebar.php';

// Busca módulos permitidos
$stmt = $pdo->prepare(
  "SELECT m.nome, m.descricao, m.link
   FROM modulos m
   INNER JOIN modulos_usuarios mu ON m.id = mu.modulo_id
   WHERE mu.usuario_id = :uid AND m.ativo = 1
   ORDER BY m.nome"
);
$stmt->execute(['uid' => $_SESSION['usuario_id']]);
$modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Painel - Intranet Bastards</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../assets/css/style.css">

</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col sm:flex-row">

  <!-- Conteúdo principal -->
  <main class="flex-1 p-4 sm:p-10 pt-20 sm:pt-10">
    <header class="mb-6 sm:mb-8">
      <h1 class="text-2xl sm:text-3xl font-bold">Bem-vindo, <?= htmlspecialchars($_SESSION['usuario_nome']); ?></h1>
      <p class="text-gray-400 text-sm">
        <?php
          $hoje = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
          $fmt = new IntlDateFormatter(
            'pt_BR',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            'America/Sao_Paulo',
            IntlDateFormatter::GREGORIAN
          );
          echo $fmt->format($hoje);
        ?>
      </p>
    </header>

    <section class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
      <?php foreach ($modulos as $modulo): ?>
        <a href="<?= htmlspecialchars($modulo['link']) ?>" class="block bg-gray-800 p-5 rounded-lg shadow hover:bg-gray-700 transition duration-200">
          <h2 class="text-xl font-semibold text-yellow-400 mb-1"><?= htmlspecialchars($modulo['nome']) ?></h2>
          <p class="text-gray-300 text-sm"><?= htmlspecialchars($modulo['descricao']) ?></p>
        </a>
      <?php endforeach; ?>
    </section>
  </main>

</body>
</html>
