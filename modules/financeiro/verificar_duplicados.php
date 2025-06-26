<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';

echo "<h2>Verificação de Simulações Duplicadas</h2>";

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

    // Listar todas as simulações e contar duplicatas
    echo "<h3>Simulações por Nome e Usuário:</h3>";
    $query = "SELECT NomeSimulacao, UsuarioID, COUNT(*) as total, MIN(DataCriacao) as primeira, MAX(DataCriacao) as ultima 
              FROM fSimulacoesFabrica 
              WHERE Ativo = 1 
              GROUP BY NomeSimulacao, UsuarioID 
              ORDER BY total DESC, NomeSimulacao";
    
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Nome Simulação</th><th>Usuário ID</th><th>Total Registros</th><th>Primeira Data</th><th>Última Data</th><th>Status</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            $status = ($row['total'] > 1) ? "<strong style='color: red;'>DUPLICADO</strong>" : "<span style='color: green;'>OK</span>";
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['NomeSimulacao']) . "</td>";
            echo "<td>" . $row['UsuarioID'] . "</td>";
            echo "<td>" . $row['total'] . "</td>";
            echo "<td>" . $row['primeira'] . "</td>";
            echo "<td>" . $row['ultima'] . "</td>";
            echo "<td>" . $status . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nenhuma simulação encontrada.</p>";
    }

    // Listar simulações duplicadas detalhadamente
    echo "<h3>Detalhes das Simulações Duplicadas:</h3>";
    $duplicadas = $conn->query("
        SELECT NomeSimulacao, UsuarioID, COUNT(*) as total 
        FROM fSimulacoesFabrica 
        WHERE Ativo = 1 
        GROUP BY NomeSimulacao, UsuarioID 
        HAVING COUNT(*) > 1
    ");

    if ($duplicadas->num_rows > 0) {
        while ($dup = $duplicadas->fetch_assoc()) {
            echo "<h4>Simulação: " . htmlspecialchars($dup['NomeSimulacao']) . " (Usuário: " . $dup['UsuarioID'] . ") - " . $dup['total'] . " registros</h4>";
            
            $detalhes = $conn->prepare("SELECT ID, Categoria, Subcategoria, SubSubcategoria, ValorSimulacao, PercentualSimulacao, DataCriacao 
                                       FROM fSimulacoesFabrica 
                                       WHERE NomeSimulacao = ? AND UsuarioID = ? AND Ativo = 1 
                                       ORDER BY DataCriacao DESC");
            $detalhes->bind_param("si", $dup['NomeSimulacao'], $dup['UsuarioID']);
            $detalhes->execute();
            $result_det = $detalhes->get_result();
            
            echo "<table border='1' style='border-collapse: collapse; margin-left: 20px;'>";
            echo "<tr><th>ID</th><th>Categoria</th><th>Subcategoria</th><th>SubSubcategoria</th><th>Valor</th><th>Percentual</th><th>Data Criação</th></tr>";
            
            while ($det = $result_det->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $det['ID'] . "</td>";
                echo "<td>" . htmlspecialchars($det['Categoria']) . "</td>";
                echo "<td>" . htmlspecialchars($det['Subcategoria']) . "</td>";
                echo "<td>" . htmlspecialchars($det['SubSubcategoria']) . "</td>";
                echo "<td>" . number_format($det['ValorSimulacao'], 2, ',', '.') . "</td>";
                echo "<td>" . number_format($det['PercentualSimulacao'], 2, ',', '.') . "</td>";
                echo "<td>" . $det['DataCriacao'] . "</td>";
                echo "</tr>";
            }
            echo "</table><br>";
            $detalhes->close();
        }
    } else {
        echo "<p style='color: green;'>Nenhuma simulação duplicada encontrada!</p>";
    }

    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
}
?>
