<?php
require_once '../../config/db.php';

// Remover exibição de erros para evitar saída antes do redirecionamento
// As configurações de erro devem ser definidas no ambiente, não no código


// Verificar se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar e sanitizar os dados do formulário
    $ficha_id = filter_input(INPUT_POST, 'ficha_id', FILTER_VALIDATE_INT);
    $auditor = filter_input(INPUT_POST, 'auditor', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $data_auditoria = filter_input(INPUT_POST, 'data_auditoria', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $cozinheiro = filter_input(INPUT_POST, 'cozinheiro', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $status_auditoria = filter_input(INPUT_POST, 'status_auditoria', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $observacoes = filter_input(INPUT_POST, 'observacoes', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $periodicidade = filter_input(INPUT_POST, 'periodicidade', FILTER_VALIDATE_INT) ?: 30; // Padrão: 30 dias
    
    // Validar dados obrigatórios
    if (!$ficha_id || !$auditor || !$data_auditoria || !$cozinheiro || !$status_auditoria) {
        $erro = "Todos os campos obrigatórios devem ser preenchidos.";
        header("Location: auditoria.php?erro=" . urlencode($erro));
        exit;
    }
    
    try {
        // Iniciar transação
        $pdo->beginTransaction();
        
        // Inserir registro de auditoria
        $stmt = $pdo->prepare("INSERT INTO ficha_tecnica_auditoria 
                              (ficha_tecnica_id, auditor, data_auditoria, cozinheiro, 
                               status_auditoria, observacoes, periodicidade) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $ficha_id,
            $auditor,
            $data_auditoria,
            $cozinheiro,
            $status_auditoria,
            $observacoes,
            $periodicidade
        ]);
        
        // Commit da transação
        $pdo->commit();
        
        // Redirecionar com mensagem de sucesso
        header("Location: auditoria.php?sucesso=1");
        exit;
        
    } catch (PDOException $e) {
        // Rollback em caso de erro
        $pdo->rollBack();
        
        $erro = "Erro ao registrar auditoria: " . $e->getMessage();
        header("Location: auditoria.php?erro=" . urlencode($erro));
        exit;
    }
} else {
    // Redirecionar se acessado diretamente
    header("Location: auditoria.php");
    exit;
}