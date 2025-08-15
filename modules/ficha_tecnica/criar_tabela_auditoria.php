<?php
require_once '../../config/db.php';

// Definir exibição de erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ler o arquivo SQL
$sql = file_get_contents(__DIR__ . '/criar_tabela_auditoria.sql');

// Executar as queries
try {
    $pdo->exec($sql);
    echo "<p style='color:green'>Tabela de auditoria criada com sucesso!</p>";
} catch (PDOException $e) {
    echo "<p style='color:red'>Erro ao criar tabela: " . $e->getMessage() . "</p>";
}
?>