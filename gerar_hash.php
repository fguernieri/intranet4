<?php
$senha = '1234'; // Altere aqui se quiser outro valor

$hash = password_hash($senha, PASSWORD_DEFAULT);

echo "<h2>Hash gerado com password_hash()</h2>";
echo "<p><strong>Senha original:</strong> {$senha}</p>";
echo "<p><strong>Hash gerado:</strong><br><code>{$hash}</code></p>";
echo "<p><strong>Versão do PHP:</strong> " . phpversion() . "</p>";
