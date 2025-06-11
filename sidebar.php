<?php
require_once 'auth.php';
require_once 'config/db.php';
require_once 'config/app.php';

// Busca módulos ativos para o usuário
$stmt = $pdo->prepare(
    "SELECT m.nome, m.link
     FROM modulos m
     JOIN modulos_usuarios mu ON m.id = mu.modulo_id
     WHERE mu.usuario_id = :uid AND m.ativo = 1
     ORDER BY m.nome"
);
$stmt->execute(['uid' => $_SESSION['usuario_id']]);
$modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Consulta última atualização via reflog Git
$logPath    = __DIR__ . '/.git/logs/HEAD';
$lastUpdate = 'data indisponível';
if (file_exists($logPath)) {
    $lines    = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lastLine = array_pop($lines);
    if (preg_match('/\s(\d{10})\s/', $lastLine, $m)) {
        $ts         = intval($m[1]);
        $lastUpdate = date('d/m/Y H:i', $ts);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @media (min-width: 640px) {
      /* Sidebar desktop fixo, fora do fluxo */
      #desktopSidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        transform: translateX(0);
        transition: transform 0.3s;
        z-index: 30;
      }
      body.sidebar-collapsed #desktopSidebar {
        transform: translateX(-100%);
      }
      /* Toggle button posicionado fora do fluxo */
      #desktopToggle {
        position: fixed;
        top: 1.75rem; /* top-7 */
        left: 13rem;
        transition: left 0.3s;
        z-index: 40;
      }
      body.sidebar-collapsed #desktopToggle {
        left: 0;
        @apply rounded-r
      }
      /* Conteúdo shift */
      #content {
        margin-left: 13rem;
        transition: margin-left 0.3s;
      }
      body.sidebar-collapsed #content {
        margin-left: 0;
      }
    }
  </style>
</head>
<body>
  <!-- Overlay mobile -->
  <div id="overlay" onclick="toggleSidebar()"
       class="hidden fixed inset-0 bg-black bg-opacity-50 z-40 sm:hidden"></div>

  <!-- Sidebar mobile -->
  <div id="mobileSidebar"
       class="hidden fixed top-0 left-0 h-full w-64 bg-gray-800 p-6 space-y-3 text-white
              transform -translate-x-full transition-transform duration-300 z-50 sm:hidden">
    <button onclick="toggleSidebar()"
            class="text-right w-full text-gray-400 hover:text-white">✕</button>
    <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Bastards Brewery"
           class="w-28 mx-auto mb-2">
    
    <a href="<?= BASE_URL ?>/painel.php" class="block text-yellow-400 font-bold">Painel</a>
    <?php foreach ($modulos as $m): ?>
      <a href="<?= htmlspecialchars($m['link']) ?>"
         class="block hover:text-yellow-400"><?= htmlspecialchars($m['nome']) ?></a>
    <?php endforeach; ?>
    <?php if ($_SESSION['usuario_perfil'] === 'admin'): ?>
      <a href="<?= BASE_URL ?>/modules/usuarios/admin_permissoes.php"
         class="block text-sm text-gray-400 hover:text-yellow-400">Admin</a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/modules/usuarios/alterar_senha.php"
       class="block text-sm text-gray-400 hover:text-yellow-400">Alterar Senha</a>
    <a href="<?= BASE_URL ?>/logout.php"
       class="block text-sm text-red-500 hover:underline">Sair</a>
    <p class="text-sm text-gray-400 mt-6">
      Última atualização:<br>
      <?= htmlspecialchars($lastUpdate) ?>
    </p>
  </div>

  <!-- Botão mobile -->
  <button onclick="toggleSidebar()"
          class="sm:hidden fixed top-7 left-0 -translate-y-1/2 bg-yellow-500 text-gray-900
                 p-3 rounded-r z-40 shadow hover:bg-yellow-600 transition">
    ☰
  </button>

  <!-- Botão desktop -->
  <button id="desktopToggle" onclick="toggleSidebar()"
          class="hidden sm:block p-3 bg-yellow-500 text-gray-900 rounded shadow
                 hover:bg-yellow-600 transition">
    ☰
  </button>

  <!-- Sidebar desktop -->
  <aside id="desktopSidebar"
         class="hidden sm:flex w-60 bg-gray-800 p-6 flex-col justify-between
                text-white">
    <div>
      <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Bastards Brewery"
           class="w-28 mx-auto mb-6">
      <nav class="space-y-4">
        <a href="<?= BASE_URL ?>/painel.php" class="block text-yellow-400 font-bold">Painel</a>
        <?php foreach ($modulos as $m): ?>
          <a href="<?= htmlspecialchars($m['link']) ?>"
             class="block hover:text-yellow-400"><?= htmlspecialchars($m['nome']) ?></a>
        <?php endforeach; ?>
        <?php if ($_SESSION['usuario_perfil'] === 'admin'): ?>
          <a href="<?= BASE_URL ?>/modules/usuarios/admin_permissoes.php"
             class="block text-sm text-gray-400 mt-6 hover:text-yellow-400">Admin</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/modules/usuarios/alterar_senha.php"
           class="block text-sm text-gray-400 hover:text-yellow-400">Alterar Senha</a>
        <a href="<?= BASE_URL ?>/logout.php"
           class="block text-sm text-red-500 hover:underline">Sair</a>
      </nav>
    </div>
    <footer>
      <p class="text-sm text-gray-400 mt-auto">
        db:<br>
        <?= htmlspecialchars($lastUpdate) ?>
      </p>
    </footer>
  </aside>

  <!-- Conteúdo da página -->
  <main id="content" class="p-3 pt-20 sm:pt-10">
    <!-- Aqui vai o conteúdo real da página -->
  </main>

  <script>
    function toggleSidebar() {
      if (window.matchMedia('(min-width:640px)').matches) {
        document.body.classList.toggle('sidebar-collapsed');
      } else {
        // Lógica mobile existente
        const sidebar = document.getElementById('mobileSidebar');
        const overlay = document.getElementById('overlay');
        const hidden  = sidebar.classList.contains('hidden');
        
        if (hidden) {
          sidebar.classList.remove('hidden');
          requestAnimationFrame(() => sidebar.classList.remove('-translate-x-full'));
          overlay.classList.remove('hidden');
        } else {
          sidebar.classList.add('-translate-x-full');
          overlay.classList.add('hidden');
          setTimeout(() => sidebar.classList.add('hidden'), 300);
        }
      }
    }
    // Estado inicial no desktop: sidebar aberta
    document.addEventListener('DOMContentLoaded', () => {
      if (window.matchMedia('(min-width:640px)').matches) {
        document.body.classList.remove('sidebar-collapsed');
      }
    });
  </script>
</body>
</html>
