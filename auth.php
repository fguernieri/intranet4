<?php
declare(strict_types=1);

require_once __DIR__ . '/config/session_config.php';

session_start();

// ⚠️ Verifica inatividade real por tempo de acesso
$tempoMaximoInatividade = 10800; // 3 horas

if (isset($_SESSION['ultimo_acesso']) && (time() - $_SESSION['ultimo_acesso']) > $tempoMaximoInatividade) {
    session_unset();
    session_destroy();
    header('Location: login.php?msg=sessao_expirada');
    exit;
}
$_SESSION['ultimo_acesso'] = time();

// 1) Inclui a conexão principal e configurações
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';

// 2) Função para obter IDs de vendedores autorizados para um usuário e ativos
function getAuthorizedVendors(PDO $pdo, int $userId): array {
    $sql = "
        SELECT uvp.vendedor_id
          FROM user_vendedor_permissoes AS uvp
     INNER JOIN vendedores            AS v
             ON v.id             = uvp.vendedor_id
         WHERE uvp.usuario_id    = ?
           AND v.ativo           = 1
        ORDER BY uvp.vendedor_id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// 3) Verifica se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . '/login.php?msg=sessao_expirada');
    exit;
}

// 4) Carrega e armazena em sessão os vendedores permitidos
$_SESSION['vendedores_permitidos'] = getAuthorizedVendors(
    $pdo,
    (int) $_SESSION['usuario_id']
);
