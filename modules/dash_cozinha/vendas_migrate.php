<?php
// modules/dash_cozinha/vendas_migrate.php
// Simple migration to ensure required tables exist.
include __DIR__ . '/../../config/db.php';

function cozinha_vendas_ensure_tables(PDO $pdo): void {
    $sql = "
        CREATE TABLE IF NOT EXISTS cozinha_vendas_diarias (
            codigo VARCHAR(50) NOT NULL,
            data DATE NOT NULL,
            produto VARCHAR(255) NULL,
            grupo VARCHAR(120) NULL,
            preco DECIMAL(10,2) NULL,
            qtde DECIMAL(12,3) NOT NULL DEFAULT 0,
            total DECIMAL(14,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (codigo, data),
            INDEX idx_data (data),
            INDEX idx_grupo (grupo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql);
}

// Allow running directly
if (php_sapi_name() === 'cli-server' || (isset($_GET['run']) && $_GET['run'] == '1')) {
    try {
        cozinha_vendas_ensure_tables($pdo);
        echo 'OK';
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'Erro: ' . $e->getMessage();
    }
}
?>

