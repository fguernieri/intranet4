<?php
/**
 * Funções utilitárias para lidar com aliases de vendedores.
 */
function getVendedorAliasMap(PDO $pdo): array {
    $sql = "SELECT v.nome AS nome, va.alias FROM vendedores v LEFT JOIN vendedores_alias va ON va.vendedor_id = v.id";
    $stmt = $pdo->query($sql);
    $aliasToNome = [];
    $nomeToTodos = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $nome = $row['nome'];
        $alias = $row['alias'];
        if (!isset($nomeToTodos[$nome])) {
            $nomeToTodos[$nome] = [$nome];
        }
        if ($alias) {
            $aliasToNome[$alias] = $nome;
            $nomeToTodos[$nome][] = $alias;
        }
    }
    return ['alias_to_nome' => $aliasToNome, 'nome_to_todos' => $nomeToTodos];
}

function resolveVendedorNome(string $nome, array $aliasMap): string {
    return $aliasMap[$nome] ?? $nome;
}
?>
