<?php
// Autenticação
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}
if (!isset($_SESSION['usuario_perfil']) || !in_array($_SESSION['usuario_perfil'], ['admin', 'supervisor'])) {
    echo "Acesso restrito.";
    exit;
}
require_once __DIR__ . '/../../sidebar.php';
require_once __DIR__ . '/../../config/db.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Simulador de metas financeiras - Bar da Fábrica</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Hierarquia visual DRE */
        table.dre-table {
            width: 92vw;
            max-width: 1300px;
            margin: 2rem auto;
            border-collapse: collapse;
            font-size: 0.80rem;
            background: #f9fafb;
            box-shadow: 0 2px 12px 0 rgba(0,0,0,0.04);
            border-radius: 0.5rem;
        }
        table.dre-table th, table.dre-table td {
            padding: 0.22rem 0.45rem;
            text-align: left;
            vertical-align: middle;
            font-size: 0.80rem;
            color: #374151;
            letter-spacing: 0.01em;
        }
        table.dre-table th {
            background-color: #f3f4f6;
            color: #1f2937;
            text-transform: uppercase;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.04em;
        }
        table.dre-table td:first-child,
        table.dre-table td:nth-child(2),
        table.dre-table td:nth-child(3),
        table.dre-table td:nth-child(6) {
            text-align: left;
        }
        .dre-toggle { cursor: pointer; }
        td span.icon-level1,
        td span.icon-level2,
        td span.icon-level3 {
            display: inline-flex;
            width: 1.1rem;
            justify-content: center;
            margin-right: 0.5rem;
        }
        td span.icon-level1 { color: #b45309; font-size: 1.15rem; }
        td span.icon-level2 { color: #ca8a04; font-size: 1.05rem; }
        td span.icon-level3 { color: #b45309; font-size: 1.02rem; }
        td span.text {
            display: inline-flex;
            align-items: center;
        }
        /* Nível 1: Categoria */
        .level-1 {
            background-color: rgba(234, 179, 8, 0.1);
            font-weight: 600;
        }
        /* Nível 2: Subcategoria */
        .level-2 {
            background-color: rgba(234, 179, 8, 0.05);
            font-weight: 500;
        }
        /* Nível 3: Descricao Conta */
        .level-3 {
            background-color: rgba(255,255,255,0.02);
            font-weight: 400;
        }
        /* Linhas calculadas */
        .line-calculated {
            background-color: rgba(75,85,99,0.1);
            font-weight: bold;
        }
        /* Destaque elegante para linhas calculadas */
        .table-success {
            background-color: rgba(34, 197, 94, 0.1) !important;
            border-left: 3px solid #22c55e;
        }

        /* Estilo suave para inputs da coluna Simulador */
        .simulador-valor-input {
            -moz-appearance: textfield;
            appearance: textfield;
            border: 1px solid rgba(15,23,42,0.06);
            background: linear-gradient(180deg, #ffffff, #fbfdff);
            padding: 0.25rem 0.4rem;
            border-radius: 0.35rem;
            width: 8.5rem;
            text-align: right;
            font-size: 0.85rem;
            color: #0f172a;
            box-shadow: 0 1px 0 rgba(16,24,40,0.02) inset;
        }
        /* Remove spinner controls in Webkit browsers */
        .simulador-valor-input::-webkit-outer-spin-button,
        .simulador-valor-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .simulador-valor-input:focus {
            outline: 2px solid rgba(59,130,246,0.18);
            box-shadow: 0 6px 18px rgba(59,130,246,0.06);
            border-color: rgba(59,130,246,0.3);
            background: #fff;
        }

    /* ...existing code... */

    /* Page-specific dark overrides: global page text light, and DRE table styled
       in dark mode to match the sidebar while preserving legibility. */
    body.bg-gray-800, body.bg-gray-800 main, #content, main {
        color: #ffffff !important;
    }
    body.bg-gray-800 a,
    body.bg-gray-800 label,
    body.bg-gray-800 .form-label,
    body.bg-gray-800 .small,
    body.bg-gray-800 .text-muted,
    body.bg-gray-800 h1, body.bg-gray-800 h2, body.bg-gray-800 h3, body.bg-gray-800 p {
        color: #ffffff !important;
    }

    /* DRE table: use light background with dark text when table is rendering as light
       (user requested dark text because the table background appears light). */
    body.bg-gray-800 .dre-table {
        background: rgba(255,255,255,0.98) !important; /* nearly white */
        color: #0f172a !important; /* dark slate for text */
        box-shadow: 0 6px 18px rgba(2,6,23,0.12);
    }
    body.bg-gray-800 .dre-table th,
    body.bg-gray-800 .dre-table td {
        color: #0f172a !important;
    }
    body.bg-gray-800 .dre-table th {
        background-color: #111827 !important; /* dark slate (corporate) */
        color: #ffffff !important; /* white titles */
        text-transform: uppercase;
        border-bottom: 1px solid rgba(255,255,255,0.06);
    text-align: left !important; /* Ensure left alignment */
        padding-left: 1rem;
        font-weight: 600;
    }
    body.bg-gray-800 .dre-table td:first-child,
    body.bg-gray-800 .dre-table td:nth-child(2),
    body.bg-gray-800 .dre-table td:nth-child(3),
    body.bg-gray-800 .dre-table td:nth-child(6) {
    text-align: left; /* Ensure left alignment */
        color: #0f172a !important;
    }

    /* Cards adapt to dark background */
    body.bg-gray-800 .card1 {
        background: rgba(30,41,59,0.6) !important;
        color: #e6eef8 !important;
    }
    /* Highlight Receita Operacional: uppercase and bold as requested */
    .receita-operacional {
        font-weight: 700;
        text-transform: uppercase;
    }
    /* Column color mapping (stronger, high-contrast colors)
       - Col 2 & 3: Mês atual (Valor, % Receita) - bold blue
       - Col 4 & 5: Média 3M (Valor, % Média) - bold amber
       - Col 6 & 7: Simulação (Simulador, % Simulador) - bold green
    */
    /* Current month (blue) */
    .dre-table th:nth-child(2), .dre-table th:nth-child(3) {
        background-color: #1e40af !important; /* blue-800 */
        color: #ffffff !important;
    }
    .dre-table td:nth-child(2), .dre-table td:nth-child(3) {
        background-color: rgba(30,64,175,0.12) !important; /* strong but translucent */

        /* Align simulator inputs to the left for easier reading */
        .simulador-valor-input, .simulador-percent-input {
            text-align: left;
        }
        color: #0b1220 !important;
        border-left: 4px solid rgba(30,64,175,0.22);
    }

    /* Média 3M (amber) */
    .dre-table th:nth-child(4), .dre-table th:nth-child(5) {
        background-color: #92400e !important; /* amber/dark orange */
        color: #ffffff !important;
    }
    .dre-table td:nth-child(4), .dre-table td:nth-child(5) {
        background-color: rgba(146,64,14,0.10) !important;
        color: #0b1220 !important;
        border-left: 4px solid rgba(146,64,14,0.20);
    }

    /* Simulação (green) */
    .dre-table th:nth-child(6), .dre-table th:nth-child(7) {
        background-color: #0f766e !important; /* teal/green strong */
        color: #ffffff !important;
    }
    .dre-table td:nth-child(6), .dre-table td:nth-child(7) {
        background-color: rgba(15,118,110,0.10) !important;
        color: #0b1220 !important;
        border-left: 4px solid rgba(15,118,110,0.20);
    }
    </style>
</head>
<body class="bg-gray-800">
<div class="flex min-h-screen flex-col md:flex-row">
    <!-- Sidebar já incluída via require_once no topo -->
    <div class="hidden md:block" style="width:220px; flex-shrink:0;"></div>
        <main class="p-4 w-full overflow-auto min-h-screen flex flex-col items-center">
        <?php
                // Prepare user name for greeting; prefer session value if available
                $usuario_nome = $_SESSION['usuario_nome'] ?? ($_SESSION['usuario_login'] ?? 'Usuário');
        ?>
    <header class="mb-2 w-full pl-4">
            <h2 class="text-base sm:text-lg font-semibold text-white text-left">
                Bem-vindo, <?= htmlspecialchars($usuario_nome); ?>
            </h2>
        </header>
                <h1 class="mb-4 text-xl font-bold text-gray-800 w-full text-center">Simulador de metas financeiras - Bar da Fábrica</h1>
                <form id="dre-filtro-form" class="flex flex-wrap gap-2 items-end mb-3 justify-center w-full">
            <div>
                <label for="dre-mes" class="form-label mb-0">Mês</label>
                <select id="dre-mes" class="form-select form-select-sm" name="mes" required></select>
            </div>
            <div>
                <label for="dre-ano" class="form-label mb-0">Ano</label>
                <select id="dre-ano" class="form-select form-select-sm" name="ano" required></select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary btn-sm">Aplicar</button>
            </div>
        </form>
        <div id="dre-loading" class="alert alert-info py-1 px-2 text-sm w-full text-center">Carregando dados...</div>
        <div id="dre-error" class="alert alert-danger d-none py-1 px-2 text-sm w-full text-center"></div>
        <div class="w-full text-center mb-2">
            <button id="btn-ponto-equilibrio" class="btn btn-success btn-sm">CALCULAR PONTO DE EQUILIBRIO</button>
        </div>
        <div id="dre-table-container" class="overflow-x-auto w-full flex justify-center"></div>
    <!-- Explanatory footer text removed per user request -->
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/modules/DRE/dre.js"></script>
<script src="/modules/DRE/dre-simulacao.js"></script>
<script src="/modules/DRE/dre-equilibrio.js"></script>
<script src="/modules/DRE/dre-salvar-metas.js"></script>
<script src="/modules/DRE/dre-salvar-simulacao.js"></script>
</body>
</html>