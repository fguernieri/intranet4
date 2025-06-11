<?php
include '../../config/db.php'; // deve fornecer $pdo (PDO)

try {
  // Sanitiza todos os dados recebidos
  $dados = array_map('trim', $_POST);

  // Campos obrigatórios mínimos
  if (empty($dados['cpf']) || empty($dados['nome_completo'])) {
    throw new Exception('Nome completo e CPF são obrigatórios.');
  }

  // Verifica se funcionário com o mesmo CPF já existe
  $stmt = $pdo->prepare("SELECT id FROM funcionarios WHERE cpf = :cpf LIMIT 1");
  $stmt->execute([':cpf' => $dados['cpf']]);
  $existe = $stmt->fetchColumn();

  // Prepara os dados para bind
  $campos = [
    'nome_completo', 'cpf', 'rg', 'data_nascimento',
    'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'cep',
    'telefone', 'email', 'empresa_contratante', 'cargo', 'departamento',
    'data_admissao', 'numero_folha', 'numero_pis', 'tipo_contrato', 'salario',
    'cnpj', 'data_demissao', 'banco', 'agencia', 'conta', 'codigo_banco', 'chave_pix',
    'nome_contato', 'telefone_contato', 'grau_parentesco'
  ];

    foreach ($campos as $campo) {
      if ($campo === 'salario') {
        $valores[$campo] = isset($dados[$campo]) && $dados[$campo] !== ''
          ? str_replace(['.', ','], ['', '.'], $dados[$campo])
          : null;
      } else {
        $valores[$campo] = isset($dados[$campo]) && $dados[$campo] !== '' ? $dados[$campo] : null;
      }
    }

  if ($existe) {
    // Atualizar
    $set = implode(', ', array_map(fn($campo) => "$campo = :$campo", array_keys($valores)));
    $sql = "UPDATE funcionarios SET $set, atualizado_em = NOW() WHERE cpf = :cpf";
  } else {
    // Inserir
    $colunas = implode(', ', array_keys($valores));
    $placeholders = implode(', ', array_map(fn($c) => ":$c", array_keys($valores)));
    $sql = "INSERT INTO funcionarios ($colunas) VALUES ($placeholders)";
  }

  $stmt = $pdo->prepare($sql);
  $stmt->execute($valores);

  // Redireciona ou responde
    header('Location: listar_funcionarios.php?sucesso=1');
    exit;

} catch (Exception $e) {
  // Para debug, exibir erro:
  echo "<p style='color:red;'>Erro: " . $e->getMessage() . "</p>";
}
