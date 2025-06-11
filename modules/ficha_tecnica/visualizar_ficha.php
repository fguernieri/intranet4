<?php
declare(strict_types=1);

// 1) Includes e inicialização do PDO principal e data warehouse
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/db_dw.php';

require_once __DIR__ . '/../../auth.php';


// 2) Captura e validação do ID do parâmetro GET
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    exit('ID inválido.');
}

// 3) Fetch da ficha técnica
$stmtFicha = $pdo->prepare('SELECT * FROM ficha_tecnica WHERE id = :id');
$stmtFicha->execute([':id' => $id]);
$ficha = $stmtFicha->fetch(PDO::FETCH_ASSOC);
if (!$ficha) {
    exit('Ficha não encontrada.');
}

// 4) Fetch dos ingredientes
$stmtIngs = $pdo->prepare('SELECT codigo, descricao, quantidade, unidade FROM ingredientes WHERE ficha_id = :id');
$stmtIngs->execute([':id' => $id]);
$ingredientes = $stmtIngs->fetchAll(PDO::FETCH_ASSOC);

// 5) Se houver códigos, busque custo médio no DW e calcule subtotal
$codigos = array_column($ingredientes, 'codigo');
if ($codigos) {
    $placeholders = implode(',', array_fill(0, count($codigos), '?'));
    $sqlCusto = "SELECT `Cód. Ref.` AS codigo, COALESCE(`Custo médio`,0) AS custo_unitario
                 FROM ProdutosBares
                 WHERE `Cód. Ref.` IN ($placeholders)";
    $stmtC = $pdo_dw->prepare($sqlCusto);
    $stmtC->execute($codigos);

    $custosMap = [];
    foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $custosMap[$row['codigo']] = (float)$row['custo_unitario'];
    }

    foreach ($ingredientes as &$ing) {
        $ing['custo_unitario'] = $custosMap[$ing['codigo']] ?? 0;
        $ing['subtotal'] = $ing['custo_unitario'] * (float)$ing['quantidade'];
    }
    unset($ing);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Ficha Técnica – <?= htmlspecialchars($ficha['nome_prato'], ENT_QUOTES) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <style media="print">
    @page { size: A4 portrait; margin: 10mm; }
    body { background: #fff !important; color: #000 !important; }
    .no-print { display: none !important; }
    .print-container { width: auto !important; margin: 0 !important; padding: 0 !important; }
    
    /* Garante que a foto não ultrapasse a largura da página impressa */
    img {
    max-width: 100%;
    height: auto;
    /* opcional: limite máximo em altura */
    max-height: 120mm;
  }

    /* Evita quebras dentro das seções */
    .section-ingredientes,
    .section-preparo {
      page-break-inside: avoid;
      break-inside: avoid-page;
    }
    /* Força Modo de Preparo em nova página se necessário */
    .section-preparo {
      page-break-before: always;
      break-before: page;
    }
    /* Tabelas: repete cabeçalho e evita cortes */
    thead { display: table-header-group; }
    tbody { display: table-row-group; }
    table, tr { page-break-inside: avoid; break-inside: avoid-page; }

    /* Blocos que não devem ser divididos */
    img, ul, ol, .prose { page-break-inside: avoid; break-inside: avoid-page; }
  </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex">

  <!-- Sidebar e controles de navegação (apenas na tela) -->
  <div class="no-print">
    <?php include __DIR__ . '/../../sidebar.php'; ?>
  </div>

  <main class="print-container bg-gray-900 text-gray-100 p-6 mx-auto space-y-6">

    <!-- Mensagem de sucesso (apenas na tela) -->
    <?php if (!empty($_GET['sucesso']) && $_GET['sucesso'] === '1'): ?>
      <div id="alerta" class="no-print fixed right-4 top-4 bg-green-500 text-white px-4 py-2 rounded shadow-lg z-50">
        Ficha salva com sucesso!
      </div>
      <script>
        setTimeout(() => document.getElementById('alerta')?.style.opacity = '0', 4000);
      </script>
    <?php endif; ?>

    <!-- Botão Voltar (apenas na tela) -->
    <div class="no-print">
      <a href="consulta.php" class="inline-block bg-gray-700 hover:bg-gray-600 text-white px-6 py-3 rounded shadow font-semibold">
        ⬅️ Voltar para Consulta
      </a>
    </div>

    <!-- Título da Ficha -->
    <h1 class="text-3xl font-bold text-cyan-400 text-center">
      <?= htmlspecialchars($ficha['nome_prato'], ENT_QUOTES) ?>
    </h1>

    <!-- Imagem do prato -->
    <?php if (!empty($ficha['imagem'])): ?>
      <div class="flex justify-center">
        <img src="uploads/<?= htmlspecialchars($ficha['imagem'], ENT_QUOTES) ?>"
             alt="Imagem do prato"
             class="max-w-full md:max-w-lg mx-auto rounded shadow-lg border border-gray-700">
      </div>
    <?php endif; ?>

    <!-- Detalhes Gerais -->
    <div class="bg-gray-800 rounded shadow p-6 space-y-2">
      <p><strong>Prato:</strong> <?= htmlspecialchars($ficha['nome_prato'], ENT_QUOTES) ?></p>
      <p><strong>Rendimento:</strong> <?= htmlspecialchars($ficha['rendimento'], ENT_QUOTES) ?></p>
      <p><strong>Responsável:</strong> <?= htmlspecialchars($ficha['usuario'], ENT_QUOTES) ?></p>
      <p><strong>Código Cloudify:</strong> <?= htmlspecialchars($ficha['codigo_cloudify'], ENT_QUOTES) ?></p>
      <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($ficha['data_criacao'])) ?></p>
    </div>

    <!-- Ingredientes em Tabela -->
    <section class="section-ingredientes bg-gray-800 rounded shadow p-6">
      <h2 class="text-xl font-bold text-cyan-300 mb-4">Ingredientes</h2>
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-center border border-gray-700">
          <thead class="bg-gray-700 text-cyan-200">
            <tr>
              <th class="p-2">Código</th>
              <th class="p-2">Descrição</th>
              <th class="p-2">Quantidade</th>
              <th class="p-2">Unidade</th>
              <th class="p-2">Custo Unitário</th>
              <th class="p-2">Subtotal</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-700">
            <?php foreach ($ingredientes as $ing): ?>
              <tr>
                <td class="p-2"><?= htmlspecialchars($ing['codigo'], ENT_QUOTES) ?></td>
                <td class="p-2"><?= htmlspecialchars($ing['descricao'], ENT_QUOTES) ?></td>
                <td class="p-2"><?= number_format((float)$ing['quantidade'], 3, ',', '.') ?></td>
                <td class="p-2"><?= htmlspecialchars($ing['unidade'], ENT_QUOTES) ?></td>
                <td class="p-2"><?= number_format($ing['custo_unitario'], 2, ',', '.') ?></td>
                <td class="p-2"><?= number_format($ing['subtotal'], 2, ',', '.') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Modo de Preparo -->
    <section class="section-preparo bg-gray-800 rounded shadow p-6 prose print:prose-sm">
      <h2 class="text-xl font-bold text-cyan-300 mb-4">Modo de Preparo</h2>
      <?= $ficha['modo_preparo'] ?>
    </section>

    <!-- Botão Imprimir (apenas na tela) -->
    <button onclick="window.print()"
            class="no-print fixed bottom-6 right-6 bg-cyan-500 hover:bg-cyan-600 text-white font-semibold py-3 px-5 rounded-full shadow-lg">
      🖨️ Imprimir
    </button>

  </main>
</body>
</html>
