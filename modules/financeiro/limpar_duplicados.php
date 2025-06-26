<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';

echo "<h2>Limpeza de Simulações Duplicadas</h2>";

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
    
    if ($conn->connect_error) {
        die("Conexão falhou: " . $conn->connect_error);
    }

    // Verificar se a tabela existe
    $tableCheck = $conn->query("SHOW TABLES LIKE 'fSimulacoesFabrica'");
    if ($tableCheck->num_rows == 0) {
        echo "<p>Tabela fSimulacoesFabrica não existe.</p>";
        exit;
    }

    echo "<h3>ANTES da limpeza:</h3>";
    
    // Verificar simulações duplicadas
    $duplicadas = $conn->query("
        SELECT NomeSimulacao, UsuarioID, COUNT(*) as total 
        FROM fSimulacoesFabrica 
        WHERE Ativo = 1 
        GROUP BY NomeSimulacao, UsuarioID 
        HAVING COUNT(*) > 1
        ORDER BY total DESC
    ");

    if ($duplicadas->num_rows > 0) {
        echo "<p style='color: red;'><strong>Encontradas " . $duplicadas->num_rows . " simulações duplicadas:</strong></p>";
        
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Nome Simulação</th><th>Usuário ID</th><th>Total Registros</th><th>Ação</th></tr>";
        
        $totalRemovidos = 0;
        
        while ($row = $duplicadas->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['NomeSimulacao']) . "</td>";
            echo "<td>" . $row['UsuarioID'] . "</td>";
            echo "<td>" . $row['total'] . "</td>";
            
            // Para cada simulação duplicada, manter apenas o registro mais recente
            $stmt = $conn->prepare("
                DELETE FROM fSimulacoesFabrica 
                WHERE NomeSimulacao = ? AND UsuarioID = ? AND Ativo = 1 
                AND ID NOT IN (
                    SELECT * FROM (
                        SELECT MAX(ID) 
                        FROM fSimulacoesFabrica 
                        WHERE NomeSimulacao = ? AND UsuarioID = ? AND Ativo = 1
                    ) as temp
                )
            ");
            
            $stmt->bind_param("sisi", $row['NomeSimulacao'], $row['UsuarioID'], $row['NomeSimulacao'], $row['UsuarioID']);
            
            if ($stmt->execute()) {
                $removidos = $stmt->affected_rows;
                $totalRemovidos += $removidos;
                echo "<td style='color: green;'>Removidos $removidos registros antigos</td>";
            } else {
                echo "<td style='color: red;'>Erro: " . $stmt->error . "</td>";
            }
            
            $stmt->close();
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<p style='color: blue;'><strong>Total de registros duplicados removidos: $totalRemovidos</strong></p>";
        
    } else {
        echo "<p style='color: green;'>Nenhuma simulação duplicada encontrada!</p>";
    }

    echo "<h3>DEPOIS da limpeza:</h3>";
    
    // Verificar novamente após limpeza
    $verificacao = $conn->query("
        SELECT NomeSimulacao, UsuarioID, COUNT(*) as total 
        FROM fSimulacoesFabrica 
        WHERE Ativo = 1 
        GROUP BY NomeSimulacao, UsuarioID 
        ORDER BY NomeSimulacao
    ");

    if ($verificacao->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Nome Simulação</th><th>Usuário ID</th><th>Total Registros</th><th>Status</th></tr>";
        
        while ($row = $verificacao->fetch_assoc()) {
            $status = ($row['total'] > 1) ? "<strong style='color: red;'>AINDA DUPLICADO</strong>" : "<span style='color: green;'>OK</span>";
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['NomeSimulacao']) . "</td>";
            echo "<td>" . $row['UsuarioID'] . "</td>";
            echo "<td>" . $row['total'] . "</td>";
            echo "<td>" . $status . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    $conn->close();
    
    echo "<h3>Limpeza concluída!</h3>";
    echo "<p><a href='Home.php'>Voltar para o sistema</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
}
?>
