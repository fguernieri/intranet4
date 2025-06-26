<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';

echo "<h2>Teste de Salvamento de Simulação</h2>";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Para teste, vou usar um usuário fake se não houver sessão
$usuarioID = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1;

echo "<p>Testando com usuário ID: $usuarioID</p>";

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
    
    if ($conn->connect_error) {
        die("Conexão falhou: " . $conn->connect_error);
    }

    // Dados de teste
    $nomeSimulacao = "Teste_" . date('Y-m-d_H-i-s');
    $dadosSimulacao = [
        'TRIBUTOS' => '5000.00',
        'CUSTO VARIÁVEL' => '3000.00',
        'CUSTO FIXO' => '2000.00'
    ];

    echo "<h3>Simulação de teste: $nomeSimulacao</h3>";
    echo "<p>Dados: " . json_encode($dadosSimulacao) . "</p>";

    // Verificar quantos registros existem antes
    $countBefore = $conn->query("SELECT COUNT(*) as total FROM fSimulacoesFabrica WHERE NomeSimulacao = '$nomeSimulacao' AND UsuarioID = $usuarioID")->fetch_assoc()['total'];
    echo "<p>Registros ANTES do salvamento: $countBefore</p>";

    // Iniciar transação
    $conn->autocommit(FALSE);
    
    // DELETE dos registros antigos
    $stmtDel = $conn->prepare("DELETE FROM fSimulacoesFabrica WHERE NomeSimulacao = ? AND UsuarioID = ?");
    $stmtDel->bind_param("si", $nomeSimulacao, $usuarioID);
    $stmtDel->execute();
    $registrosExcluidos = $stmtDel->affected_rows;
    $stmtDel->close();
    
    echo "<p>Registros EXCLUÍDOS: $registrosExcluidos</p>";

    // INSERT dos novos registros
    $stmt = $conn->prepare("INSERT INTO fSimulacoesFabrica (NomeSimulacao, UsuarioID, Categoria, Subcategoria, SubSubcategoria, ValorSimulacao, PercentualSimulacao, DataCriacao, Ativo) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1)");
    
    $insertCount = 0;
    foreach ($dadosSimulacao as $categoria => $valor) {
        $subcategoria = null;
        $subSubcategoria = null;
        $valorSimulacao = floatval(str_replace(['.', ','], ['', '.'], $valor));
        $percentualSimulacao = 0;
        
        $stmt->bind_param("sisssdd", $nomeSimulacao, $usuarioID, $categoria, $subcategoria, $subSubcategoria, $valorSimulacao, $percentualSimulacao);
        if ($stmt->execute()) {
            $insertCount++;
            echo "<p>✓ Inserido: $categoria = $valorSimulacao</p>";
        } else {
            echo "<p>✗ Erro ao inserir $categoria: " . $stmt->error . "</p>";
        }
    }
    
    $stmt->close();
    
    // Commit
    $conn->commit();
    $conn->autocommit(TRUE);
    
    echo "<p>Registros INSERIDOS: $insertCount</p>";

    // Verificar quantos registros existem depois
    $countAfter = $conn->query("SELECT COUNT(*) as total FROM fSimulacoesFabrica WHERE NomeSimulacao = '$nomeSimulacao' AND UsuarioID = $usuarioID")->fetch_assoc()['total'];
    echo "<p>Registros DEPOIS do salvamento: $countAfter</p>";

    // Verificar se há duplicatas para esta simulação
    $duplicatas = $conn->query("
        SELECT NomeSimulacao, UsuarioID, COUNT(*) as total 
        FROM fSimulacoesFabrica 
        WHERE NomeSimulacao = '$nomeSimulacao' AND UsuarioID = $usuarioID 
        GROUP BY NomeSimulacao, UsuarioID 
        HAVING COUNT(*) > 1
    ");
    
    if ($duplicatas->num_rows > 0) {
        echo "<p style='color: red;'><strong>ATENÇÃO: Ainda há duplicatas!</strong></p>";
    } else {
        echo "<p style='color: green;'><strong>✓ Sem duplicatas - funcionando corretamente!</strong></p>";
    }

    // Testar salvamento novamente com os mesmos dados
    echo "<h3>Teste 2: Salvando novamente com os mesmos dados</h3>";
    
    // Iniciar transação
    $conn->autocommit(FALSE);
    
    // DELETE dos registros antigos
    $stmtDel2 = $conn->prepare("DELETE FROM fSimulacoesFabrica WHERE NomeSimulacao = ? AND UsuarioID = ?");
    $stmtDel2->bind_param("si", $nomeSimulacao, $usuarioID);
    $stmtDel2->execute();
    $registrosExcluidos2 = $stmtDel2->affected_rows;
    $stmtDel2->close();
    
    echo "<p>Registros EXCLUÍDOS no 2º salvamento: $registrosExcluidos2</p>";

    // INSERT dos novos registros
    $stmt2 = $conn->prepare("INSERT INTO fSimulacoesFabrica (NomeSimulacao, UsuarioID, Categoria, Subcategoria, SubSubcategoria, ValorSimulacao, PercentualSimulacao, DataCriacao, Ativo) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1)");
    
    $insertCount2 = 0;
    foreach ($dadosSimulacao as $categoria => $valor) {
        $subcategoria = null;
        $subSubcategoria = null;
        $valorSimulacao = floatval(str_replace(['.', ','], ['', '.'], $valor));
        $percentualSimulacao = 0;
        
        $stmt2->bind_param("sisssdd", $nomeSimulacao, $usuarioID, $categoria, $subcategoria, $subSubcategoria, $valorSimulacao, $percentualSimulacao);
        if ($stmt2->execute()) {
            $insertCount2++;
        }
    }
    
    $stmt2->close();
    
    // Commit
    $conn->commit();
    $conn->autocommit(TRUE);
    
    echo "<p>Registros INSERIDOS no 2º salvamento: $insertCount2</p>";

    // Verificar final
    $countFinal = $conn->query("SELECT COUNT(*) as total FROM fSimulacoesFabrica WHERE NomeSimulacao = '$nomeSimulacao' AND UsuarioID = $usuarioID")->fetch_assoc()['total'];
    echo "<p>Registros FINAIS: $countFinal</p>";

    if ($countFinal == $insertCount2) {
        echo "<p style='color: green;'><strong>✓ TESTE PASSOU: Não há duplicatas após re-salvamento!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>✗ TESTE FALHOU: Há duplicatas após re-salvamento!</strong></p>";
    }

    // Limpar dados de teste
    $conn->query("DELETE FROM fSimulacoesFabrica WHERE NomeSimulacao = '$nomeSimulacao' AND UsuarioID = $usuarioID");
    echo "<p>Dados de teste removidos.</p>";

    $conn->close();

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
        $conn->autocommit(TRUE);
    }
    echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
}

echo "<p><a href='Home.php'>Voltar para o sistema</a></p>";
?>
