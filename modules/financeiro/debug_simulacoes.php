<?php
// Debug para testar a funcionalidade de simulações
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';

// Testar conexão
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

echo "<h2>Debug - Funcionalidade de Simulações</h2>";

// 1. Verificar se a tabela existe
echo "<h3>1. Verificação da Tabela</h3>";
$result = $conn->query("SHOW TABLES LIKE 'fSimulacoesFabrica'");
if ($result->num_rows == 0) {
    echo "<p style='color: red;'>❌ Tabela fSimulacoesFabrica NÃO EXISTE</p>";
    echo "<p>Criando tabela...</p>";
    
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
        echo "<p style='color: green;'>✅ Tabela criada com sucesso!</p>";
    } else {
        echo "<p style='color: red;'>❌ Erro ao criar tabela: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: green;'>✅ Tabela fSimulacoesFabrica existe</p>";
}

// 2. Verificar estrutura da tabela
echo "<h3>2. Estrutura da Tabela</h3>";
$result = $conn->query("DESCRIBE fSimulacoesFabrica");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ Erro ao verificar estrutura: " . $conn->error . "</p>";
}

// 3. Verificar sessão
echo "<h3>3. Verificação da Sessão</h3>";
if (isset($_SESSION['usuario_id'])) {
    echo "<p style='color: green;'>✅ Usuário autenticado: ID = " . $_SESSION['usuario_id'] . "</p>";
} else {
    echo "<p style='color: red;'>❌ Usuário NÃO autenticado</p>";
    echo "<p>Definindo usuário de teste para debug...</p>";
    $_SESSION['usuario_id'] = 1; // Define um usuário de teste
    echo "<p style='color: blue;'>ℹ️ Usuário de teste definido: ID = 1</p>";
}

// 4. Testar INSERT simples
echo "<h3>4. Teste de INSERT</h3>";
try {
    $stmt = $conn->prepare("INSERT INTO fSimulacoesFabrica (NomeSimulacao, UsuarioID, Categoria, Subcategoria, SubSubcategoria, ValorSimulacao, PercentualSimulacao, DataCriacao, Ativo) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1)");
    if (!$stmt) {
        throw new Exception("Erro ao preparar statement: " . $conn->error);
    }
    
    $nomeSimulacao = "TESTE_DEBUG_" . date('His');
    $usuarioID = $_SESSION['usuario_id'];
    $categoria = "RECEITA BRUTA";
    $subcategoria = null;
    $subSubcategoria = null;
    $valorSimulacao = 1000.00;
    $percentualSimulacao = 0.0000;
    
    $stmt->bind_param("sissdd", $nomeSimulacao, $usuarioID, $categoria, $subcategoria, $subSubcategoria, $valorSimulacao, $percentualSimulacao);
    
    if ($stmt->execute()) {
        echo "<p style='color: green;'>✅ INSERT executado com sucesso! ID: " . $conn->insert_id . "</p>";
        
        // Verificar se foi inserido
        $checkStmt = $conn->prepare("SELECT * FROM fSimulacoesFabrica WHERE NomeSimulacao = ? AND UsuarioID = ?");
        $checkStmt->bind_param("si", $nomeSimulacao, $usuarioID);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "<p style='color: green;'>✅ Registro confirmado no banco</p>";
            $row = $result->fetch_assoc();
            echo "<pre>" . print_r($row, true) . "</pre>";
        } else {
            echo "<p style='color: red;'>❌ Registro não encontrado após INSERT</p>";
        }
        $checkStmt->close();
    } else {
        echo "<p style='color: red;'>❌ Erro ao executar INSERT: " . $stmt->error . "</p>";
    }
    $stmt->close();
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exceção: " . $e->getMessage() . "</p>";
}

// 5. Testar simulação de dados complexos
echo "<h3>5. Teste de Dados Simulação Complexos</h3>";
$simulacaoTeste = [
    'RECEITA BRUTA' => '10000,00',
    'TRIBUTOS___ICMS' => '8,50',
    'CUSTO VARIÁVEL___Material' => '2500,00',
    'RNO_Outras Receitas___Juros Aplicação' => '150,00'
];

echo "<p>Dados de teste:</p>";
echo "<pre>" . print_r($simulacaoTeste, true) . "</pre>";

try {
    $nomeSimulacaoCompleta = "TESTE_COMPLETO_" . date('His');
    $stmt = $conn->prepare("INSERT INTO fSimulacoesFabrica (NomeSimulacao, UsuarioID, Categoria, Subcategoria, SubSubcategoria, ValorSimulacao, PercentualSimulacao, DataCriacao, Ativo) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1)");
    
    $insertCount = 0;
    foreach ($simulacaoTeste as $key => $valor) {
        $categoria = '';
        $subcategoria = null;
        $subSubcategoria = null;
        $valorSimulacao = 0;
        $percentualSimulacao = 0;
        $isPercentual = false;
        
        // Parse do key
        if (strpos($key, 'RNO_') === 0) {
            $categoria = 'RECEITAS NAO OPERACIONAIS';
            $parts = explode('___', substr($key, 4));
            if (count($parts) >= 2) {
                $subcategoria = $parts[0];
                $subSubcategoria = $parts[1];
            }
        } elseif (strpos($key, '___') !== false) {
            $parts = explode('___', $key);
            $categoria = $parts[0];
            $subcategoria = $parts[1];
        } else {
            $categoria = $key;
        }
        
        // Converter valor
        $valorLimpo = str_replace(['.', ','], ['', '.'], $valor);
        if ($isPercentual) {
            $percentualSimulacao = floatval($valorLimpo);
        } else {
            $valorSimulacao = floatval($valorLimpo);
        }
        
        $stmt->bind_param("sissdd", $nomeSimulacaoCompleta, $usuarioID, $categoria, $subcategoria, $subSubcategoria, $valorSimulacao, $percentualSimulacao);
        
        if ($stmt->execute()) {
            $insertCount++;
            echo "<p style='color: green;'>✅ Inserido: $key = $valor (Cat: $categoria, Sub: $subcategoria, SubSub: $subSubcategoria)</p>";
        } else {
            echo "<p style='color: red;'>❌ Erro ao inserir $key: " . $stmt->error . "</p>";
        }
    }
    
    echo "<p><strong>Total inserido: $insertCount registros</strong></p>";
    $stmt->close();
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exceção no teste complexo: " . $e->getMessage() . "</p>";
}

// 6. Listar simulações existentes
echo "<h3>6. Simulações Existentes</h3>";
$result = $conn->query("SELECT NomeSimulacao, UsuarioID, COUNT(*) as Total, DataCriacao FROM fSimulacoesFabrica WHERE Ativo = 1 GROUP BY NomeSimulacao, UsuarioID ORDER BY DataCriacao DESC LIMIT 10");
if ($result && $result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>Nome</th><th>Usuário ID</th><th>Total Itens</th><th>Data Criação</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['NomeSimulacao']}</td>";
        echo "<td>{$row['UsuarioID']}</td>";
        echo "<td>{$row['Total']}</td>";
        echo "<td>{$row['DataCriacao']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Nenhuma simulação encontrada.</p>";
}

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
pre { background-color: #f8f8f8; padding: 10px; border-radius: 4px; }
</style>
