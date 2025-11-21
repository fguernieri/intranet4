<?php
// Página inicial de análises avançadas de público
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}
$usuario = $_SESSION['usuario_nome'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Análises Avançadas de Público</title>
    <link rel="stylesheet" href="/assets/css/tailwind.min.css">
</head>
<body class="bg-gray-900 text-gray-100">
    <?php require_once __DIR__ . '/../../../sidebar.php'; ?>
    <div class="p-8 ml-4">
        <h1 class="text-3xl text-yellow-400 font-bold mb-6">🔬 Análises Avançadas de Público</h1>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <a href="analise_retencao.php" class="block bg-gray-800 rounded-lg p-6 shadow-lg border border-gray-700 hover:border-yellow-400 transition-colors">
                <h2 class="text-xl text-yellow-300 font-bold mb-2">Retenção de Clientes</h2>
                <p class="text-gray-400">Veja quantos clientes retornam ao estabelecimento em diferentes períodos.</p>
            </a>
            <a href="analise_frequencia.php" class="block bg-gray-800 rounded-lg p-6 shadow-lg border border-gray-700 hover:border-yellow-400 transition-colors">
                <h2 class="text-xl text-yellow-300 font-bold mb-2">Frequência de Visitas</h2>
                <p class="text-gray-400">Analise a frequência média de visitas dos clientes.</p>
            </a>
            <!-- Adicione mais módulos conforme necessário -->
        </div>
    </div>
</body>
</html>
