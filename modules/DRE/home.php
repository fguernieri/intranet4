<?php
// Página inicial do módulo DRE - inclui sidebar e link para o simulador
require_once __DIR__ . '/../../sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DRE - Início do Módulo</title>
    <link href="/assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
    <style>
        /* Dark module theme to match DRE page */
        body.bg-gray-800 { background: #0b1220; color: #e6eef8; }
        .module-hero { max-width: 1100px; margin: 2.5rem auto; }
        .card-hero { padding: 1.25rem; border-radius: .6rem; box-shadow: 0 6px 20px rgba(2,6,23,0.12); background: rgba(30,41,59,0.6); color: #e6eef8; }
        .card-hero h3, .card-hero h2 { color: #ffffff; }
        .card-hero p.small { color: rgba(230,238,248,0.85); }
        /* Force all text inside the module hero to match the sidebar icon yellow (bg-yellow-500) */
        .module-hero, .module-hero * {
            color: #fbbf24 !important; /* slightly lighter yellow (tailwind yellow-400) */
        }
        /* Make the outline-warning button use the same sidebar yellow for consistency */
        .btn-outline-warning {
            color: #fbbf24 !important;
            border-color: #fbbf24 !important;
        }
        .btn-outline-warning:hover, .btn-outline-warning:focus {
            background-color: #fbbf24 !important;
            color: #ffffff !important;
            border-color: #f59e0b !important; /* previous yellow for contrast */
        }
    /* Greeting color to match the sidebar icon yellow */
    .module-greeting { color: #fbbf24 !important; }
        .nav-button { min-width: 220px; }
        /* Ensure regular links inside the module use the same yellow and don't appear as default blue */
        .module-hero a, .module-hero a:link, .module-hero a:visited {
            color: #fbbf24 !important;
            text-decoration: none !important;
        }
        .module-hero a:hover, .module-hero a:focus {
            color: #f59e0b !important; /* slightly darker on hover */
            text-decoration: underline !important;
        }
    </style>
</head>
<body class="bg-gray-800">
<div class="d-flex">
    <div class="d-none d-md-block" style="width:220px; flex-shrink:0;"></div>
    <main class="w-100 p-4 d-flex flex-column align-items-start">
        <?php $usuario_nome = $_SESSION['usuario_nome'] ?? ($_SESSION['usuario_login'] ?? 'Usuário'); ?>
        <header class="mb-2 w-100 pl-4">
            <h2 class="text-base sm:text-lg font-semibold module-greeting text-left">Bem-vindo, <?= htmlspecialchars($usuario_nome); ?></h2>
        </header>
        <div class="module-hero w-100 mx-auto">
            <div class="card-hero">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h2 class="h5 mb-0">Módulo financeiro</h2>
                    </div>
                </div>

                <div class="mt-4 d-flex justify-content-center">
                    <a href="/modules/DRE/dre.php" class="btn btn-outline-warning nav-button">Abrir Simulador</a>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>