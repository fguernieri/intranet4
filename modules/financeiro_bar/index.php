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

require_once __DIR__ . '/../../sidebar.php';
?>

<div id="financeiro-content" class="p-6 ml-4">
    <h2 class="text-xl text-blue-400 mb-4">Menu Financeiro - Bares</h2>
    
    <div class="bg-gray-800 rounded-lg p-6">
        <h3 class="text-lg text-gray-300 mb-6">Sistema de Gestão Financeira</h3>
        
        <div class="grid md:grid-cols-2 gap-6">
            <!-- Card Simulador -->
            <div class="bg-gray-700 rounded-lg p-6 hover:bg-gray-600 transition-colors">
                <div class="text-center">
                    <div class="text-4xl mb-4">🎯</div>
                    <h4 class="text-lg font-semibold text-blue-400 mb-3">Simulador Financeiro</h4>
                    <p class="text-gray-300 text-sm mb-4">
                        Simule cenários financeiros, calcule pontos de equilíbrio e defina metas estratégicas para o negócio.
                    </p>
                    <ul class="text-xs text-gray-400 mb-6 space-y-1">
                        <li>✓ Simulação de receitas e despesas</li>
                        <li>✓ Cálculo automático de ponto de equilíbrio</li>
                        <li>✓ Salvamento de metas financeiras</li>
                        <li>✓ Análise de impacto no caixa</li>
                    </ul>
                    <a href="simulador.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded transition-colors">
                        Acessar Simulador
                    </a>
                </div>
            </div>

            <!-- Card Dashboard -->
            <div class="bg-gray-700 rounded-lg p-6 hover:bg-gray-600 transition-colors">
                <div class="text-center">
                    <div class="text-4xl mb-4">📊</div>
                    <h4 class="text-lg font-semibold text-green-400 mb-3">Acompanhamento Financeiro</h4>
                    <p class="text-gray-300 text-sm mb-4">
                        Monitore o desempenho financeiro real, compare com metas e analise tendências históricas.
                    </p>
                    <ul class="text-xs text-gray-400 mb-6 space-y-1">
                        <li>✓ Dashboard com métricas em tempo real</li>
                        <li>✓ Comparação com metas estabelecidas</li>
                        <li>✓ Análise de tendências mensais</li>
                        <li>✓ Relatórios detalhados por categoria</li>
                    </ul>
                    <a href="index2.php" class="inline-block bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-6 rounded transition-colors">
                        Acessar Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Fluxo recomendado -->
        <div class="mt-8 bg-gray-600 rounded-lg p-4">
            <h5 class="text-sm font-medium text-gray-300 mb-3 text-center">Fluxo de Trabalho Recomendado</h5>
            <div class="flex flex-col md:flex-row items-center justify-center space-y-2 md:space-y-0 md:space-x-6 text-sm">
                <div class="flex items-center text-gray-400">
                    <span class="bg-blue-600 rounded-full w-6 h-6 flex items-center justify-center text-white text-xs mr-2">1</span>
                    Use o Simulador para planejar
                </div>
                <div class="text-gray-500 hidden md:block">→</div>
                <div class="flex items-center text-gray-400">
                    <span class="bg-green-600 rounded-full w-6 h-6 flex items-center justify-center text-white text-xs mr-2">2</span>
                    Monitore no Dashboard
                </div>
                <div class="text-gray-500 hidden md:block">→</div>
                <div class="flex items-center text-gray-400">
                    <span class="bg-yellow-600 rounded-full w-6 h-6 flex items-center justify-center text-white text-xs mr-2">3</span>
                    Ajuste conforme necessário
                </div>
            </div>
        </div>

        <!-- Info do usuário -->
        <div class="mt-6 text-center text-xs text-gray-500">
            Logado como: <strong><?= htmlspecialchars($usuario) ?></strong> | Acesso em: <?= date('d/m/Y H:i') ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configurar o background em todos os elementos para evitar espaços brancos
    document.body.style.background = 'linear-gradient(135deg, #1f2937 0%, #111827 100%)';
    document.body.style.minHeight = '100vh';
    document.documentElement.style.background = 'linear-gradient(135deg, #1f2937 0%, #111827 100%)';
    
    const content = document.getElementById('content');
    if (content) {
        content.style.background = 'linear-gradient(135deg, #1f2937 0%, #111827 100%)';
        content.style.minHeight = '100vh';
    }
    
    // Verificar se o financeiro-content precisa ser movido para dentro do content
    const financeiroContent = document.getElementById('financeiro-content');
    const content2 = document.getElementById('content');
    
    if (financeiroContent && content2 && !content2.contains(financeiroContent)) {
        content2.appendChild(financeiroContent);
    }
});
</script>

<style>
/* Garantir que não haja espaços brancos */
html, body {
    background: linear-gradient(135deg, #1f2937 0%, #111827 100%) !important;
    min-height: 100vh !important;
    margin: 0 !important;
    padding: 0 !important;
}

#content {
    background: linear-gradient(135deg, #1f2937 0%, #111827 100%) !important;
    min-height: 100vh !important;
}
</style>