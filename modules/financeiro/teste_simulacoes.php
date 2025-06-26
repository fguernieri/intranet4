<?php
// Script para testar e criar a tabela fSimulacoesFabrica se necessário
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Verificar se a tabela existe
$result = $conn->query("SHOW TABLES LIKE 'fSimulacoesFabrica'");

if ($result->num_rows == 0) {
    echo "Tabela fSimulacoesFabrica não existe. Criando...\n";
    
    $createTable = "
    CREATE TABLE `fSimulacoesFabrica` (
        `ID` int(11) NOT NULL AUTO_INCREMENT,
        `NomeSimulacao` varchar(255) NOT NULL,
        `UsuarioID` int(11) NOT NULL,
        `Categoria` varchar(255) NOT NULL,
        `Subcategoria` varchar(255) DEFAULT NULL,
        `SubSubcategoria` varchar(255) DEFAULT NULL,
        `ValorSimulacao` decimal(15,2) DEFAULT 0.00,
        `PercentualSimulacao` decimal(8,4) DEFAULT 0.0000,
        `DataCriacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `Ativo` tinyint(1) DEFAULT 1,
        PRIMARY KEY (`ID`),
        KEY `idx_usuario_nome` (`UsuarioID`, `NomeSimulacao`),
        KEY `idx_categoria` (`Categoria`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    if ($conn->query($createTable)) {
        echo "Tabela fSimulacoesFabrica criada com sucesso!\n";
    } else {
        echo "Erro ao criar tabela: " . $conn->error . "\n";
    }
} else {
    echo "Tabela fSimulacoesFabrica já existe.\n";
}

// Verificar a estrutura da tabela
echo "\nEstrutura da tabela:\n";
$result = $conn->query("DESCRIBE fSimulacoesFabrica");
while ($row = $result->fetch_assoc()) {
    echo "- {$row['Field']}: {$row['Type']} {$row['Null']} {$row['Key']} {$row['Default']}\n";
}

$conn->close();
?>
