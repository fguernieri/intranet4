<?php
// Defina seu token secreto
$seu_token_secreto = 'wearebastards';

// Verifica se o token enviado é válido
if (!isset($_GET['token']) || $_GET['token'] !== $seu_token_secreto) {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
}

// Caminho do repositório
$dir = '/home2/basta920/intra.bastardsbrewery.com.br/';

// Executa o Pull
exec("cd $dir && git pull 2>&1", $output);

// Exibe o resultado
echo "<pre>" . implode("\n", $output) . "</pre>";
?>
