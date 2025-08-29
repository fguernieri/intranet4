<?php
// Página inicial do módulo DRE - inclui sidebar e link para o simulador
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}
// Sidebar está na raiz do projeto
require_once __DIR__ . '/../../sidebar.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DRE - Início do Módulo</title>
    <link href="/assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; }
        .module-hero { max-width: 1100px; margin: 2.5rem auto; }
        .card-hero { padding: 1.25rem; border-radius: .6rem; box-shadow: 0 6px 20px rgba(2,6,23,0.06); background: #ffffff; }
        .nav-button { min-width: 220px; }
    </style>
</head>
<body>
<div class="d-flex">
    <div class="d-none d-md-block" style="width:220px; flex-shrink:0;"></div>
    <main class="w-100 p-4 d-flex justify-content-center">
        <div class="module-hero w-100">
            <?php $usuario_nome = $_SESSION['usuario_nome'] ?? ($_SESSION['usuario_login'] ?? 'Usuário'); ?>
            <div class="card-hero">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h2 class="h5 mb-0">Módulo DRE</h2>
                        <p class="small text-muted mb-0">Bem-vindo, <?= htmlspecialchars($usuario_nome); ?></p>
                    </div>
                </div>

                <div class="row g-3 mt-4">
                    <div class="col-12 col-md-6">
                        <div class="p-3 border rounded h-100 d-flex flex-column justify-content-between">
                            <div>
                                <h3 class="h6">Simulador de metas financeiras</h3>
                                <p class="small text-muted">Abra o simulador DRE para editar metas e simular resultados.</p>
                            </div>
                            <div class="mt-3">
                                <a href="/modules/DRE/dre.php" class="btn btn-primary nav-button">Abrir Simulador</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="p-3 border rounded h-100 d-flex flex-column justify-content-between">
                            <div>
                                <h3 class="h6">Salvar / Consultar Simulações</h3>
                                <p class="small text-muted">Acesse suas simulações salvas e gerencie metas.</p>
                            </div>
                            <div class="mt-3">
                                <a href="#" class="btn btn-outline-secondary nav-button">Acessar Simulações</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
