<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db_dw.php';
require_once __DIR__ . '/../config/db.php';

$dwTables = [
    ['source' => 'ProdutosBares', 'target' => 'ProdutosBares_WAB'],
    ['source' => 'ProdutosBares', 'target' => 'ProdutosBares_BDF'],
    ['source' => 'insumos_bastards', 'target' => 'insumos_bastards_wab'],
    ['source' => 'insumos_bastards', 'target' => 'insumos_bastards_bdf'],
];

try {
    foreach ($dwTables as $pair) {
        $source = $pair['source'];
        $target = $pair['target'];

        $pdo_dw->exec("CREATE TABLE IF NOT EXISTS `$target` LIKE `$source`");

        $stmt = $pdo_dw->query("SELECT COUNT(*) FROM `$target`");
        $rowCount = (int) $stmt->fetchColumn();

        if ($rowCount === 0) {
            $copied = $pdo_dw->exec("INSERT INTO `$target` SELECT * FROM `$source`");
            echo "Tabela `$target` criada e preenchida com $copied registros de `$source`.\n";
        } else {
            echo "Tabela `$target` já possui $rowCount registros. Nenhuma cópia necessária.\n";
        }
    }

    $colCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ficha_tecnica' AND COLUMN_NAME = 'base_origem'"
    );
    $colCheck->execute();

    if ((int) $colCheck->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE ficha_tecnica ADD COLUMN base_origem ENUM('WAB','BDF') NOT NULL DEFAULT 'WAB' AFTER codigo_cloudify");
        $pdo->exec("UPDATE ficha_tecnica SET base_origem = 'WAB' WHERE base_origem IS NULL OR base_origem = ''");
        echo "Coluna base_origem criada em ficha_tecnica e preenchida com 'WAB'.\n";
    } else {
        echo "Coluna base_origem já existe em ficha_tecnica.\n";
    }

    echo "✅ Estruturas do DW e tabela ficha_tecnica verificadas com sucesso.\n";
} catch (PDOException $exception) {
    fwrite(STDERR, "Erro ao preparar tabelas divididas: " . $exception->getMessage() . "\n");
    exit(1);
}
