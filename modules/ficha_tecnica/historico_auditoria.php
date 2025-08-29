<?php
require_once '../../config/db.php';
include '../../sidebar.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verificar se o ID da ficha foi fornecido
$ficha_id = $_GET['ficha_id'] ?? 0;

if (!$ficha_id) {
    header('Location: auditoria.php');
    exit;
}

// Buscar informações da ficha
$stmt = $pdo->prepare("SELECT * FROM ficha_tecnica WHERE id = ?");
$stmt->execute([$ficha_id]);
$ficha = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ficha) {
    header('Location: auditoria.php');
    exit;
}

// Buscar histórico de auditorias
$stmt = $pdo->prepare("SELECT * FROM ficha_tecnica_auditoria 
                      WHERE ficha_tecnica_id = ? 
                      ORDER BY data_auditoria DESC");
$stmt->execute([$ficha_id]);
$auditorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Histórico de Auditoria - <?= htmlspecialchars($ficha['nome_prato']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex">
  <div class="max-w-6xl mx-auto py-6">
    <h1 class="text-3xl font-bold text-cyan-400 text-center mb-8">Histórico de Auditoria</h1>
    
    <div class="bg-gray-800 p-4 rounded shadow mb-6">
      <h2 class="text-xl font-semibold mb-2"><?= htmlspecialchars($ficha['nome_prato']) ?></h2>
      <p class="text-gray-400">Código Cloudify: <?= $ficha['codigo_cloudify'] ?? 'N/A' ?></p>
    </div>
    
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-semibold">Auditorias Realizadas</h2>
      <a href="auditoria.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">Voltar</a>
    </div>
    
    <?php if (empty($auditorias)): ?>
      <div class="bg-gray-800 p-6 rounded shadow text-center">
        <p class="text-gray-400">Nenhuma auditoria registrada para esta ficha técnica.</p>
      </div>
    <?php else: ?>
      <div class="space-y-4">
        <?php foreach ($auditorias as $auditoria): ?>
          <div class="bg-gray-800 p-4 rounded shadow">
            <div class="flex flex-col md:flex-row justify-between mb-2">
              <div>
                <span class="text-gray-400">Data:</span> 
                <span class="font-semibold"><?= date('d/m/Y', strtotime($auditoria['data_auditoria'])) ?></span>
              </div>
              <div>
                <?php 
                  $st = isset($auditoria['status_auditoria']) ? trim($auditoria['status_auditoria']) : '';
                  $stClass = 'bg-gray-700';
                  if ($st === 'OK') { $stClass = 'bg-green-700'; }
                  elseif ($st === 'NOK') { $stClass = 'bg-red-700'; }
                  elseif (strcasecmp($st, 'Parcial') === 0) { $stClass = 'bg-yellow-700'; }
                ?>
                <span class="px-2 py-1 rounded text-xs <?= $stClass ?>">
                  <?= $st !== '' ? $st : 'Sem registro' ?>
                </span>
              </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-2">
              <div>
                <span class="text-gray-400">Auditor:</span> 
                <span><?= htmlspecialchars($auditoria['auditor']) ?></span>
              </div>
              <div>
                <span class="text-gray-400">Cozinheiro:</span> 
                <span><?= htmlspecialchars($auditoria['cozinheiro']) ?></span>
              </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-2">
              <div>
                <span class="text-gray-400">Periodicidade:</span> 
                <span><?= $auditoria['periodicidade'] ?> dias</span>
              </div>
              <div>
                <span class="text-gray-400">Próxima Auditoria:</span> 
                <span><?= date('d/m/Y', strtotime($auditoria['proxima_auditoria'])) ?></span>
              </div>
            </div>
            
            <?php if (!empty($auditoria['observacoes'])): ?>
              <div class="mt-2">
                <span class="text-gray-400">Observações:</span>
                <p class="mt-1 text-sm"><?= nl2br(htmlspecialchars($auditoria['observacoes'])) ?></p>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
