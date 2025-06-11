<?php
// listar_funcionarios.php
require_once '../../config/db.php';
require_once '../../auth.php';


// Busca todos os funcionários
$stmt = $pdo->query(
    'SELECT nome_completo, cpf, rg, data_nascimento, empresa_contratante, cargo, data_admissao, email, telefone FROM funcionarios ORDER BY nome_completo'
);
$funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <title>Lista de Funcionários</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../assets/css/style.css">

</head>
<body class="bg-gray-100 mt-12 mb-8 flex">


<?php include '../../sidebar.php';?>
  <div class="mx-auto bg-white p-6 rounded-2xl shadow-md">
  <?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == 1): ?>
    <div id="mensagemSucesso" class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded shadow text-sm">
      ✅ Alterações salvas com sucesso!
    </div>
    <script>
      // Esconde após 3 segundos
      setTimeout(() => {
        const msg = document.getElementById('mensagemSucesso');
        if (msg) msg.style.display = 'none';
      }, 3000);
    </script>
    <?php endif; ?>

    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold">Lista de Funcionários</h1>
      <a href="form_funcionario.php" class="btn-acao py-2 px-4">+ Novo Funcionário</a>
    </div>
    <div>
    <input 
      type="text" 
      id="searchBox" 
      placeholder="Digite para filtrar..." 
      class="px-3 py-2 border rounded-lg mb-4"
    />
    </div>
    <div class="overflow-x-auto">
      <table id="lista_funcionario" class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="p-3 text-left text-sm font-medium uppercase tracking-wider">Nome</th>
            <th class="p-3 text-left text-sm font-medium uppercase tracking-wider">CPF</th>
            <th class="p-3 text-left text-sm font-medium uppercase tracking-wider">Cargo</th>
            <th class="p-3 text-left text-sm font-medium uppercase tracking-wider">Telefone</th>
            <th class="p-3 text-left text-sm font-medium uppercase tracking-wider">E-mail</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <?php if (count($funcionarios) === 0): ?>
            <tr>
              <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">Nenhum funcionário encontrado.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($funcionarios as $func): ?>
              <tr>
                <td class="p-3 whitespace-nowrap">
                  <a href="form_funcionario.php?nome=<?php echo urlencode($func['nome_completo']); ?>" class="text-blue-600 text-sm hover:underline">
                    <?php echo htmlspecialchars($func['nome_completo']); ?>
                  </a>
                </td>
                <td class="p-3 text-sm whitespace-nowrap"><?php echo htmlspecialchars($func['cpf']); ?></td>
                <td class="p-3 text-sm whitespace-nowrap"><?php echo htmlspecialchars($func['cargo']); ?></td>
                <td class="p-3 text-sm whitespace-nowrap"><?php echo htmlspecialchars($func['telefone'] ?? ''); ?></td>
                <td class="p-3 text-sm whitespace-nowrap"><?php echo htmlspecialchars($func['email'] ?? ''); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('searchBox');
  if (!input) {
    console.error('Não foi possível encontrar o elemento #searchBox');
    return;
  }

  let timer = null;
  input.addEventListener('keyup', function() {
    clearTimeout(timer);
    timer = setTimeout(() => {
      const termo = this.value.toLowerCase().trim();
      filtrarTabela(termo);
    }, 100);
  });

  function filtrarTabela(termo) {
    const linhas = document.querySelectorAll('#lista_funcionario tbody tr');
    if (!linhas.length) {
      console.warn('Nenhuma linha encontrada em #lista_funcionario');
      return;
    }

    linhas.forEach(linha => {
      // Concatena o texto de todas as <td> daquela linha
      const textoLinha = Array
        .from(linha.cells)
        .map(td => td.innerText.toLowerCase())
        .join(' ');

      // Se o termo existir em qualquer coluna, mostra a linha; senão oculta
      if (textoLinha.includes(termo)) {
        linha.style.display = '';
      } else {
        linha.style.display = 'none';
      }
    });
  }

  // Chama uma vez para, inicialmente, deixar tudo visível
  filtrarTabela('');
});
</script>

</body>
</html>
