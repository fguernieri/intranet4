<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sidebar igual ao acompanhamento financeiro
require_once __DIR__ . '../../../../sidebar.php';

// Autenticação igual ao acompanhamento financeiro
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Menu Análises de Compras</title>
    <link href="/assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 flex min-h-screen">
    <!-- Sidebar já incluída no topo do arquivo -->
    <main class="flex-1 bg-gray-900 p-6 relative">
        <!-- Saudação BEM VINDO -->
        <header class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-bold">
                Bem-vindo, <?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário'); ?>
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
        </header>

        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold text-yellow-400 mb-8 text-center">📊 Análises de Compras</h1>
            
            <p class="text-gray-300 text-center mb-12 text-lg">
                Selecione o tipo de análise de compras que deseja visualizar
            </p>

            <div class="grid md:grid-cols-2 gap-8 max-w-2xl mx-auto">
                <!-- Card TAP -->
                <div class="card-hover bg-gradient-to-br from-blue-800 to-blue-900 rounded-xl p-8 text-center border border-blue-700">
                    <div class="mb-6">
                        <div class="w-20 h-20 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                            <span class="text-3xl">🍺</span>
                        </div>
                        <h2 class="text-2xl font-bold text-white mb-3">TAP</h2>
                        <p class="text-blue-200 text-sm mb-6">
                            Análise de compras da filial TAP com curva ABC, 
                            filtros por período e busca por produtos
                        </p>
                    </div>
                    
                    <a href="analisecomprastap.php" 
                       class="inline-block w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200 shadow-lg">
                        🔍 Acessar Análise TAP
                    </a>
                </div>

                <!-- Card WAB -->
                <div class="card-hover bg-gradient-to-br from-red-800 to-red-900 rounded-xl p-8 text-center border border-red-700">
                    <div class="mb-6">
                        <div class="w-20 h-20 bg-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
                            <span class="text-3xl">🥃</span>
                        </div>
                        <h2 class="text-2xl font-bold text-white mb-3">WAB</h2>
                        <p class="text-red-200 text-sm mb-6">
                            Análise de compras da filial WAB com curva ABC, 
                            filtros por período e busca por produtos
                        </p>
                    </div>
                    
                    <a href="analisecompraswab.php" 
                       class="inline-block w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200 shadow-lg">
                        🔍 Acessar Análise WAB
                    </a>
                </div>
            </div>

            <!-- Informações adicionais -->
            <div class="mt-12 bg-gray-800 rounded-lg p-6 text-center">
                <h3 class="text-lg font-semibold text-yellow-400 mb-3">ℹ️ Sobre as Análises</h3>
                <div class="grid md:grid-cols-3 gap-6 text-sm text-gray-300">
                    <div>
                        <h4 class="font-semibold text-blue-400 mb-2">📈 Curva ABC</h4>
                        <p>Classificação dos produtos por importância nos gastos</p>
                    </div>
                    <div>
                        <h4 class="font-semibold text-yellow-400 mb-2">📅 Filtros</h4>
                        <p>Análise por períodos de 3, 6 ou 12 meses</p>
                    </div>
                    <div>
                        <h4 class="font-semibold text-green-400 mb-2">🔍 Busca</h4>
                        <p>Pesquisa em tempo real por grupos e produtos</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botão de voltar -->
        <div class="fixed bottom-4 left-4">
            <a href="../select_filial.php" 
               class="bg-gray-700 hover:bg-gray-600 text-white font-semibold py-2 px-4 rounded-lg shadow-lg transition duration-200 flex items-center gap-2">
                ← Voltar
            </a>
        </div>
    </main>
</body>
</html>