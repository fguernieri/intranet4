<?php
require_once __DIR__ . '/../config/db.php';

$sql = "CREATE TABLE IF NOT EXISTS vendedores_alias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendedor_id INT NOT NULL,
    alias VARCHAR(100) NOT NULL,
    UNIQUE KEY unique_alias (vendedor_id, alias),
    CONSTRAINT fk_vendedor_alias FOREIGN KEY (vendedor_id) REFERENCES vendedores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

$pdo->exec($sql);

echo "Tabela vendedores_alias criada ou já existente.\n";
