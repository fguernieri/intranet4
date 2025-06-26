<?php
// Teste simples para verificar o POST de simulações
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simular usuário logado para teste
if (empty($_SESSION['usuario_id'])) {
    $_SESSION['usuario_id'] = 1; // Usuário de teste
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $jsonData = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['sucesso' => false, 'erro' => 'Erro JSON: ' . json_last_error_msg(), 'input_recebido' => $input]);
        exit;
    }
    
    if (isset($jsonData['simulacao'])) {
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Dados recebidos com sucesso!',
            'usuario_id' => $_SESSION['usuario_id'],
            'nome_simulacao' => $jsonData['nomeSimulacao'] ?? 'não informado',
            'total_itens' => count($jsonData['simulacao'] ?? []),
            'dados_recebidos' => $jsonData
        ]);
    } else {
        echo json_encode(['sucesso' => false, 'erro' => 'Chave simulacao não encontrada', 'dados' => $jsonData]);
    }
} else {
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido: ' . $_SERVER['REQUEST_METHOD']]);
}
?>
