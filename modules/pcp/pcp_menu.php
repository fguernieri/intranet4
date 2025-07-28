<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../sidebar.php';

// Saudação igual ao painel de cobranças
session_start();
$usuario = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel PCP - Menu</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; }
        .icon { width: 48px; height: 48px; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex flex-row">
    <?php require_once __DIR__ . '/../../sidebar.php'; ?>
    <main class="flex-1">
        <!-- Saudação colada na sidebar, com mais espaço lateral -->
        <div class="pl-16 pr-10 pt-8 pb-4 border-b border-slate-800">
            <h1 class="text-2xl sm:text-3xl font-bold">
                Bem-vindo, <?= htmlspecialchars($usuario); ?>
            </h1>
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
        </div>
        <div class="w-full max-w-5xl mx-auto py-4 px-4">
            <h1 class="text-2xl md:text-3xl font-bold text-yellow-500 text-center mb-8">
                Menu de Acesso - PCP
            </h1>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Card Planejamento de Produções -->
                <a href="pcp_prod.php" class="block bg-slate-800 rounded-lg shadow-lg hover:shadow-2xl hover:bg-slate-700 transition-all border-2 border-yellow-500 hover:border-yellow-400 p-6">
                    <div class="flex justify-center mb-3">
                        <!-- Ícone Gráfico de Barras (representa planejamento/produção) -->
                        <svg class="icon" viewBox="0 0 24 24" fill="none">
                            <rect x="3" y="13" width="4" height="8" rx="1" fill="#fde047"/>
                            <rect x="9" y="9" width="4" height="12" rx="1" fill="#fbbf24"/>
                            <rect x="15" y="5" width="4" height="16" rx="1" fill="#a16207"/>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-yellow-400 mb-2 text-center">Painel de Planejamento de Produções</h2>
                    <p class="text-gray-300 mb-4 text-sm text-center">
                        Painel com gráficos e dados que mostram a estimativa de estoque, considerando o histórico de vendas e as produções ativas e planejadas.
                    </p>
                    <span class="block mx-auto mt-2 px-4 py-2 bg-yellow-500 text-slate-900 font-semibold rounded shadow text-xs w-max">Acessar</span>
                </a>
                <!-- Card Ordem de Envase -->
                <a href="ordem_envase.php" class="block bg-slate-800 rounded-lg shadow-lg hover:shadow-2xl hover:bg-slate-700 transition-all border-2 border-yellow-500 hover:border-yellow-400 p-6">
                    <div class="flex justify-center mb-3">
                        <!-- Ícone Caixa de Entrega (representa envase/distribuição) -->
                        <svg class="icon" viewBox="0 0 24 24" fill="none">
                            <rect x="3" y="7" width="18" height="13" rx="2" fill="#fde047" stroke="#a16207" stroke-width="1"/>
                            <rect x="7" y="3" width="10" height="4" rx="2" fill="#fbbf24" stroke="#a16207" stroke-width="1"/>
                            <rect x="9" y="17" width="6" height="3" rx="1.5" fill="#a16207"/>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-yellow-400 mb-2 text-center">Ordens de Envase</h2>
                    <p class="text-gray-300 mb-4 text-sm text-center">
                        Consulte e gerencie as ordens de envase de barris INOX e PET e também de LATAS com informações de estoque atual, estoque mínimo e necessidades de produção.
                    </p>
                    <span class="block mx-auto mt-2 px-4 py-2 bg-yellow-500 text-slate-900 font-semibold rounded shadow text-xs w-max">Acessar</span>
                </a>
                <!-- Card Chopeiras/Consumo Cliente -->
                <a href="chopeiras.php" class="block bg-slate-800 rounded-lg shadow-lg hover:shadow-2xl hover:bg-slate-700 transition-all border-2 border-yellow-500 hover:border-yellow-400 p-6">
                    <div class="flex justify-center mb-3">
                        <!-- Ícone de Chopeira/Torneira -->
                        <svg class="icon" viewBox="0 0 24 24" fill="none">
                            <rect x="7" y="14" width="10" height="6" rx="2" fill="#fde047"/>
                            <rect x="10" y="4" width="4" height="10" rx="1.5" fill="#fbbf24"/>
                            <circle cx="12" cy="3" r="2" fill="#a16207"/>
                            <rect x="11" y="20" width="2" height="2" rx="1" fill="#a16207"/>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-yellow-400 mb-2 text-center">Análise de Consumo por Cliente</h2>
                    <p class="text-gray-300 mb-4 text-sm text-center">
                        Compare o consumo de clientes com a quantidade de bicos de chopeira fornecidos.
                    </p>
                    <span class="block mx-auto mt-2 px-4 py-2 bg-yellow-500 text-slate-900 font-semibold rounded shadow text-xs w-max">Acessar</span>
                </a>
            </div>
            <!-- Informação de atualização dos dados -->
            <?php if (isset($atualizacao_recente) && $atualizacao_recente): ?>
                <div class="text-center text-xs text-gray-400 mb-2 mt-8">
                    Atualizado em: <span class="font-semibold">
                        <?= date('d/m/Y H:i', strtotime($atualizacao_recente)); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>