<?php
require_once '../../config/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Teste de Funcionalidade da Auditoria de Fichas Técnicas</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";

// Função para exibir mensagens formatadas
function printMessage($message, $type = 'info') {
    echo "<p class='{$type}'>{$message}</p>";
}

// Teste 1: Verificar se a tabela existe
printMessage("Teste 1: Verificando se a tabela ficha_tecnica_auditoria existe...", 'info');
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'ficha_tecnica_auditoria'");
    if ($stmt->rowCount() > 0) {
        printMessage("✓ Tabela ficha_tecnica_auditoria existe!", 'success');
    } else {
        printMessage("✗ Tabela ficha_tecnica_auditoria não existe!", 'error');
    }
} catch (PDOException $e) {
    printMessage("✗ Erro ao verificar tabela: " . $e->getMessage(), 'error');
}

// Teste 2: Verificar estrutura da tabela
printMessage("\nTeste 2: Verificando estrutura da tabela...", 'info');
try {
    $stmt = $pdo->query("DESCRIBE ficha_tecnica_auditoria");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $required_columns = ['id', 'ficha_tecnica_id', 'auditor', 'data_auditoria', 'cozinheiro', 'status_auditoria', 'observacoes', 'periodicidade', 'proxima_auditoria'];
    
    $missing_columns = array_diff($required_columns, $columns);
    
    if (empty($missing_columns)) {
        printMessage("✓ Estrutura da tabela está correta!", 'success');
    } else {
        printMessage("✗ Colunas ausentes: " . implode(', ', $missing_columns), 'error');
    }
} catch (PDOException $e) {
    printMessage("✗ Erro ao verificar estrutura: " . $e->getMessage(), 'error');
}

// Teste 3: Verificar fichas técnicas com status verde
printMessage("\nTeste 3: Verificando fichas técnicas com status verde...", 'info');
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM ficha_tecnica WHERE farol = 'verde'");
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        printMessage("✓ Existem {$count} fichas técnicas com status verde!", 'success');
    } else {
        printMessage("⚠ Não existem fichas técnicas com status verde. Crie algumas para testar a funcionalidade.", 'error');
    }
} catch (PDOException $e) {
    printMessage("✗ Erro ao verificar fichas: " . $e->getMessage(), 'error');
}

// Teste 4: Testar inserção de auditoria
printMessage("\nTeste 4: Testando inserção de auditoria...", 'info');
try {
    // Buscar uma ficha técnica verde para teste
    $stmt = $pdo->query("SELECT id FROM ficha_tecnica WHERE farol = 'verde' LIMIT 1");
    $ficha = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ficha) {
        $ficha_id = $ficha['id'];
        
        // Inserir auditoria de teste
        $stmt = $pdo->prepare("INSERT INTO ficha_tecnica_auditoria 
                              (ficha_tecnica_id, auditor, data_auditoria, cozinheiro, 
                               status_auditoria, observacoes, periodicidade) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $result = $stmt->execute([
            $ficha_id,
            'Teste Automatizado',
            date('Y-m-d'),
            'Cozinheiro Teste',
            'OK',
            'Auditoria de teste automatizado',
            30
        ]);
        
        if ($result) {
            $audit_id = $pdo->lastInsertId();
            printMessage("✓ Auditoria de teste inserida com sucesso! ID: {$audit_id}", 'success');
            
            // Verificar se a próxima_auditoria foi calculada corretamente
            $stmt = $pdo->prepare("SELECT proxima_auditoria FROM ficha_tecnica_auditoria WHERE id = ?");
            $stmt->execute([$audit_id]);
            $proxima = $stmt->fetchColumn();
            
            $expected_date = date('Y-m-d', strtotime('+30 days'));
            if ($proxima == $expected_date) {
                printMessage("✓ Data da próxima auditoria calculada corretamente: {$proxima}", 'success');
            } else {
                printMessage("⚠ Data da próxima auditoria não corresponde ao esperado. Esperado: {$expected_date}, Atual: {$proxima}", 'error');
            }
            
            // Limpar dados de teste
            $stmt = $pdo->prepare("DELETE FROM ficha_tecnica_auditoria WHERE id = ?");
            $stmt->execute([$audit_id]);
            printMessage("✓ Dados de teste removidos com sucesso!", 'success');
        } else {
            printMessage("✗ Falha ao inserir auditoria de teste!", 'error');
        }
    } else {
        printMessage("⚠ Não foi possível encontrar uma ficha técnica verde para teste.", 'error');
    }
} catch (PDOException $e) {
    printMessage("✗ Erro ao testar inserção: " . $e->getMessage(), 'error');
}

// Teste 5: Verificar arquivos criados
printMessage("\nTeste 5: Verificando arquivos criados...", 'info');
$required_files = [
    '../../modules/ficha_tecnica/auditoria.php',
    '../../modules/ficha_tecnica/processar_auditoria.php',
    '../../modules/ficha_tecnica/historico_auditoria.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        printMessage("✓ Arquivo {$file} existe!", 'success');
    } else {
        printMessage("✗ Arquivo {$file} não existe!", 'error');
    }
}

// Teste 6: Verificar botão no Dashboard
printMessage("\nTeste 6: Verificando botão no Dashboard...", 'info');
$dashboard_file = '../../modules/dash_cozinha/index.php';
if (file_exists($dashboard_file)) {
    $content = file_get_contents($dashboard_file);
    if (strpos($content, '../ficha_tecnica/auditoria.php') !== false) {
        printMessage("✓ Botão para a página de auditoria encontrado no Dashboard!", 'success');
    } else {
        printMessage("✗ Botão para a página de auditoria não encontrado no Dashboard!", 'error');
    }
} else {
    printMessage("✗ Arquivo do Dashboard não encontrado!", 'error');
}

// Teste 7: Verificar que NÃO existe link no sidebar
printMessage("\nTeste 7: Verificando que não existe link no sidebar...", 'info');
$sidebar_file = '../../sidebar.php';
if (file_exists($sidebar_file)) {
    $content = file_get_contents($sidebar_file);
    if (strpos($content, '/modules/ficha_tecnica/auditoria.php') === false) {
        printMessage("✓ Link para a página de auditoria NÃO encontrado no sidebar (correto)!", 'success');
    } else {
        printMessage("✗ Link para a página de auditoria encontrado no sidebar (incorreto)!", 'error');
    }
} else {
    printMessage("✗ Arquivo do sidebar não encontrado!", 'error');
}

printMessage("\nTestes concluídos!", 'info');

echo "<p><a href='auditoria.php'>Voltar para a página de auditoria</a></p>";