<?php
require_once '../../config/db.php';
include '../../sidebar.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Buscar fichas técnicas com status 'verde'
$stmt = $pdo->query("SELECT ft.*, 
                      (SELECT MAX(data_auditoria) FROM ficha_tecnica_auditoria WHERE ficha_tecnica_id = ft.id) as ultima_auditoria,
                      (SELECT periodicidade FROM ficha_tecnica_auditoria WHERE ficha_tecnica_id = ft.id ORDER BY data_auditoria DESC LIMIT 1) as periodicidade,
                      (SELECT DATE_ADD(
                          (SELECT MAX(data_auditoria) FROM ficha_tecnica_auditoria WHERE ficha_tecnica_id = ft.id), 
                          INTERVAL (SELECT periodicidade FROM ficha_tecnica_auditoria WHERE ficha_tecnica_id = ft.id ORDER BY data_auditoria DESC LIMIT 1) DAY
                      )) as proxima_auditoria
                    FROM ficha_tecnica ft 
                    WHERE ft.farol = 'verde'
                    ORDER BY ft.nome_prato ASC");
$fichas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estatísticas
$total_fichas = count($fichas);
$fichas_em_dia = 0;
$fichas_atrasadas = 0;
$fichas_nao_auditadas = 0;
$hoje = date('Y-m-d');

foreach ($fichas as &$ficha) {
    if (is_null($ficha['ultima_auditoria'])) {
        $ficha['status_auditoria'] = 'nao_auditada';
        $fichas_nao_auditadas++;
    } else {
        if (is_null($ficha['proxima_auditoria']) || $ficha['proxima_auditoria'] >= $hoje) {
            $ficha['status_auditoria'] = 'em_dia';
            $fichas_em_dia++;
        } else {
            $ficha['status_auditoria'] = 'atrasada';
            $fichas_atrasadas++;
        }
    }
}

// Processar mensagens de erro/sucesso
$mensagem = '';
$tipo_mensagem = '';

if (isset($_GET['sucesso'])) {
    $mensagem = 'Auditoria registrada com sucesso!';
    $tipo_mensagem = 'sucesso';
} elseif (isset($_GET['erro'])) {
    $mensagem = $_GET['erro'];
    $tipo_mensagem = 'erro';
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Auditoria de Fichas Técnicas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="../../assets/css/style.css">
  <style>
    .status-em-dia { background-color: #10B981; }
    .status-atrasada { background-color: #EF4444; }
    .status-nao-auditada { background-color: #F59E0B; }
  </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex">
  <div class="max-w-6xl mx-auto py-6">
    <h1 class="text-3xl font-bold text-cyan-400 text-center mb-8">Auditoria de Fichas Técnicas</h1>
    
    <?php if ($mensagem): ?>
      <div class="mb-4 p-4 rounded <?= $tipo_mensagem === 'sucesso' ? 'bg-green-700' : 'bg-red-700' ?>">
        <?= htmlspecialchars($mensagem) ?>
      </div>
    <?php endif; ?>
    
    <!-- Painel de Status -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-gray-800 p-4 rounded shadow text-center">
        <h3 class="text-lg font-semibold mb-2">Total de Fichas</h3>
        <p class="text-3xl font-bold"><?= $total_fichas ?></p>
      </div>
      <div class="bg-green-800 p-4 rounded shadow text-center">
        <h3 class="text-lg font-semibold mb-2">Em Dia</h3>
        <p class="text-3xl font-bold"><?= $fichas_em_dia ?></p>
      </div>
      <div class="bg-red-800 p-4 rounded shadow text-center">
        <h3 class="text-lg font-semibold mb-2">Atrasadas</h3>
        <p class="text-3xl font-bold"><?= $fichas_atrasadas ?></p>
      </div>
      <div class="bg-yellow-700 p-4 rounded shadow text-center">
        <h3 class="text-lg font-semibold mb-2">Não Auditadas</h3>
        <p class="text-3xl font-bold"><?= $fichas_nao_auditadas ?></p>
      </div>
    </div>
    
    <!-- Formulário de Auditoria -->
    <div class="bg-gray-800 p-6 rounded shadow mb-6">
      <h2 class="text-xl font-semibold mb-4">Registrar Nova Auditoria</h2>
      <form action="processar_auditoria.php" method="post" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="ficha_id" class="block text-sm font-medium text-gray-400 mb-1">Ficha Técnica</label>
            <select id="ficha_id" name="ficha_id" required class="w-full bg-gray-700 border border-gray-600 rounded py-2 px-3 text-white">
              <option value="">Selecione uma ficha técnica</option>
              <?php foreach ($fichas as $ficha): ?>
                <option value="<?= $ficha['id'] ?>"><?= htmlspecialchars($ficha['nome_prato']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="auditor" class="block text-sm font-medium text-gray-400 mb-1">Auditor</label>
            <input type="text" id="auditor" name="auditor" required class="w-full bg-gray-700 border border-gray-600 rounded py-2 px-3 text-white">
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label for="data_auditoria" class="block text-sm font-medium text-gray-400 mb-1">Data da Auditoria</label>
            <input type="date" id="data_auditoria" name="data_auditoria" value="<?= date('Y-m-d') ?>" required class="w-full bg-gray-700 border border-gray-600 rounded py-2 px-3 text-white">
          </div>
          <div>
            <label for="cozinheiro" class="block text-sm font-medium text-gray-400 mb-1">Cozinheiro</label>
            <input type="text" id="cozinheiro" name="cozinheiro" required class="w-full bg-gray-700 border border-gray-600 rounded py-2 px-3 text-white">
          </div>
          <div>
            <label for="status_auditoria" class="block text-sm font-medium text-gray-400 mb-1">Status</label>
            <select id="status_auditoria" name="status_auditoria" required class="w-full bg-gray-700 border border-gray-600 rounded py-2 px-3 text-white">
              <option value="OK">OK</option>
              <option value="NOK">NOK</option>
            </select>
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="periodicidade" class="block text-sm font-medium text-gray-400 mb-1">Periodicidade (dias)</label>
            <input type="number" id="periodicidade" name="periodicidade" value="30" min="1" required class="w-full bg-gray-700 border border-gray-600 rounded py-2 px-3 text-white">
          </div>
          <div>
            <label for="observacoes" class="block text-sm font-medium text-gray-400 mb-1">Observações</label>
            <textarea id="observacoes" name="observacoes" rows="1" class="w-full bg-gray-700 border border-gray-600 rounded py-2 px-3 text-white"></textarea>
          </div>
        </div>
        
        <div class="flex justify-end">
          <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded">
            Registrar Auditoria
          </button>
        </div>
      </form>
    </div>
    
    <!-- Tabela de Fichas Técnicas -->
    <div class="bg-gray-800 p-6 rounded shadow">
      <h2 class="text-xl font-semibold mb-4">Fichas Técnicas com Status Verde</h2>
      <table id="tabela-auditoria" class="w-full">
        <thead>
          <tr>
            <th>Nome do Prato</th>
            <th>Última Auditoria</th>
            <th>Próxima Auditoria</th>
            <th>Status</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($fichas as $ficha): ?>
            <tr>
              <td><?= htmlspecialchars($ficha['nome_prato']) ?></td>
              <td>
                <?= $ficha['ultima_auditoria'] ? date('d/m/Y', strtotime($ficha['ultima_auditoria'])) : 'Nunca auditada' ?>
              </td>
              <td>
                <?= $ficha['proxima_auditoria'] ? date('d/m/Y', strtotime($ficha['proxima_auditoria'])) : 'N/A' ?>
              </td>
              <td>
                <?php if ($ficha['status_auditoria'] === 'em_dia'): ?>
                  <span class="px-2 py-1 rounded text-xs bg-green-700">Em dia</span>
                <?php elseif ($ficha['status_auditoria'] === 'atrasada'): ?>
                  <span class="px-2 py-1 rounded text-xs bg-red-700">Atrasada</span>
                <?php else: ?>
                  <span class="px-2 py-1 rounded text-xs bg-yellow-700">Não auditada</span>
                <?php endif; ?>
              </td>
              <td>
                <a href="historico_auditoria.php?ficha_id=<?= $ficha['id'] ?>" class="text-cyan-400 hover:text-cyan-300">Histórico</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
  <script>
    $(document).ready(function() {
      $('#tabela-auditoria').DataTable({
        language: {
          url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
        },
        order: [[3, 'asc']],
        columnDefs: [
          { orderable: false, targets: 4 }
        ],
        pageLength: 25
      });
    });
  </script>
</body>
</html>