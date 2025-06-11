<?php
$senha_digitada = 'Senha123!';
$hash_no_banco = '$2y$10$HTM1HGYceTnmJ04rNm3K..tDJ0ObRqxTV0mYrR3XMyPy63ZC69o8u';

echo "<p>Senha digitada: $senha_digitada</p>";
echo "<p>Hash esperado: $hash_no_banco</p>";

if (password_verify($senha_digitada, $hash_no_banco)) {
    echo "<p style='color:green'>✅ Hash e senha conferem!</p>";
} else {
    echo "<p style='color:red'>❌ Hash e senha NÃO conferem!</p>";
}
