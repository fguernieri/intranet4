<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurar fuso horário do Brasil
date_default_timezone_set('America/Sao_Paulo');

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';

// Verificar autenticação
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

$usuario = $_SESSION['usuario_nome'] ?? '';

try {
    require_once __DIR__ . '/supabase_connection.php';
    $supabase = new SupabaseConnection();
    $conexao_ok = $supabase->testConnection();
    $erro_conexao = null;
} catch (Exception $e) {
    $conexao_ok = false;
    $erro_conexao = $e->getMessage();
}

require_once 'sidebar.php';
?>

<div id="marketing-content" class="p-6 ml-4">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl text-yellow-400 font-bold">📊 Painel de Marketing</h1>
        <a href="/" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded transition-colors flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
            </svg>
            Voltar ao Início
        </a>
    </div>

    <?php if (!$conexao_ok): ?>
        <div class="bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded mb-6">
            <strong>⚠️ Erro de conexão:</strong> <?= htmlspecialchars($erro_conexao ?? 'Não foi possível conectar ao banco de dados') ?>
        </div>
    <?php endif; ?>

    <!-- Card de Análise de Público -->
    <div class="max-w-2xl mx-auto">
        <a href="analise_publico/" class="block bg-gray-800 rounded-lg p-8 hover:bg-gray-700 transition-all transform hover:scale-105 shadow-lg">
            <div class="flex items-center mb-6">
                <div class="bg-yellow-600 rounded-full p-4 mr-6">
                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-2xl text-white font-bold mb-2">Análise de Público</h3>
                    <p class="text-gray-400">Análise detalhada do perfil dos clientes</p>
                </div>
            </div>
            <p class="text-gray-300 mb-6 leading-relaxed">
                Explore dados dos clientes como, faixas etárias, gênero, tempo de permanência e padrões de consumo. 
                Visualize tendências e tome decisões estratégicas baseadas em dados reais.
            </p>
            <div class="flex items-center justify-between">
                <div class="flex gap-4 text-sm text-gray-400">
                    <span>📊 Gráficos interativos</span>
                    <span>📈 Análises detalhadas</span>
                    <span>🎯 Insights</span>
                </div>
                <div class="text-yellow-400 text-sm font-semibold flex items-center">
                    Acessar módulo
                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>
            </div>
        </a>
    </div>
</div>

<style>
body {
    background-color: #0f172a;
}
</style>