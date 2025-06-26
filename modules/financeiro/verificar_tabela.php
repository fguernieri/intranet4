<?php
// Script para verificar e criar a tabela fSimulacoesFabrica se necessário
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Verificar se a tabela existe
$checkTable = $conn->query("SHOW TABLES LIKE 'fSimulacoesFabrica'");

if ($checkTable->num_rows == 0) {
    // Tabela não existe, vamos criá-la
    $createTableSQL = "
    CREATE TABLE `fSimulacoesFabrica` (
        `ID` int(11) NOT NULL AUTO_INCREMENT,
        `NomeSimulacao` varchar(255) NOT NULL,
        `UsuarioID` int(11) NOT NULL,
        `Categoria` varchar(255) NOT NULL,
        `Subcategoria` varchar(255) DEFAULT NULL,
        `SubSubcategoria` varchar(255) DEFAULT NULL,
        `ValorSimulacao` decimal(15,2) DEFAULT 0.00,
        `PercentualSimulacao` decimal(10,4) DEFAULT 0.0000,
        `DataCriacao` datetime NOT NULL,
        `Ativo` tinyint(1) DEFAULT 1,
        PRIMARY KEY (`ID`),
        KEY `idx_usuario_simulacao` (`UsuarioID`, `NomeSimulacao`),
        KEY `idx_ativo` (`Ativo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    if ($conn->query($createTableSQL)) {
        echo "✅ Tabela fSimulacoesFabrica criada com sucesso!<br>";
    } else {
        echo "❌ Erro ao criar tabela: " . $conn->error . "<br>";
    }
} else {
    echo "✅ Tabela fSimulacoesFabrica já existe!<br>";
    
    // Verificar estrutura da tabela
    $describeTable = $conn->query("DESCRIBE fSimulacoesFabrica");
    echo "<h3>Estrutura da tabela:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th><th>Padrão</th></tr>";
    while ($row = $describeTable->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Teste de conexão e permissões
echo "<h3>Teste de operações:</h3>";

// Teste INSERT
$testInsert = $conn->prepare("INSERT INTO fSimulacoesFabrica (NomeSimulacao, UsuarioID, Categoria, Subcategoria, SubSubcategoria, ValorSimulacao, PercentualSimulacao, DataCriacao, Ativo) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1)");
if ($testInsert) {
    $testNome = "TESTE_" . time();
    $testUsuario = 1;
    $testCat = "TESTE";
    $testSub = null;
    $testSubSub = null;
    $testValor = 100.50;
    $testPerc = 5.25;
    
    $testInsert->bind_param("sissdd", $testNome, $testUsuario, $testCat, $testSub, $testSubSub, $testValor, $testPerc);
    
    if ($testInsert->execute()) {
        echo "✅ Teste de INSERT realizado com sucesso!<br>";
        $insertId = $conn->insert_id;
        
        // Teste SELECT
        $testSelect = $conn->prepare("SELECT * FROM fSimulacoesFabrica WHERE ID = ?");
        $testSelect->bind_param("i", $insertId);
        $testSelect->execute();
        $result = $testSelect->get_result();
        
        if ($result->num_rows > 0) {
            echo "✅ Teste de SELECT realizado com sucesso!<br>";
            
            // Teste DELETE
            $testDelete = $conn->prepare("DELETE FROM fSimulacoesFabrica WHERE ID = ?");
            $testDelete->bind_param("i", $insertId);
            
            if ($testDelete->execute()) {
                echo "✅ Teste de DELETE realizado com sucesso!<br>";
            } else {
                echo "❌ Erro no teste de DELETE: " . $testDelete->error . "<br>";
            }
            $testDelete->close();
        } else {
            echo "❌ Erro no teste de SELECT: nenhum registro encontrado<br>";
        }
        $testSelect->close();
    } else {
        echo "❌ Erro no teste de INSERT: " . $testInsert->error . "<br>";
    }
    $testInsert->close();
} else {
    echo "❌ Erro ao preparar teste de INSERT: " . $conn->error . "<br>";
}

$conn->close();
echo "<br><strong>Teste concluído!</strong>";
?>
