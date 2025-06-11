<?php
require_once '../../config/db.php';
$data = $_POST['data'] ?? null;
$nome = $_POST['nome'] ?? null;
$comentarios = $_POST['comentarios'] ?? null;

// Gera um lote_id único com timestamp
$loteId = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resposta - Disp WAB</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#309898',
            warning: '#FF9F00',
            alert: '#F4631E',
            danger: '#CB0404'
          }
        }
      }
    }
  </script>
</head>
<body class="bg-primary bg-opacity-10 min-h-screen flex items-center justify-center">
  <main class="p-4">
<?php
if (!$data || !$nome) {
    echo "<div class='max-w-xl mx-auto mt-12 bg-white border-l-4 border-danger p-6 rounded-xl shadow-md'>
            <h2 class='text-xl font-semibold text-danger mb-2'>Erro</h2>
            <p class='text-sm text-gray-700'>Data e nome são obrigatórios.</p>
          </div></main></body></html>";
    exit;
}
try {
    $pdo->beginTransaction();

    // Prepara a query incluindo lote_id
    $stmt = $pdo->prepare("
        INSERT INTO disp_wab (
          lote_id,
          data,
          nome_usuario,
          codigo_cloudify,
          disponivel,
          comentarios
        ) VALUES (
          :lote_id,
          :data,
          :nome,
          :codigo,
          :disponivel,
          :comentarios
        )
        ON DUPLICATE KEY UPDATE
          disponivel = VALUES(disponivel),
          comentarios = VALUES(comentarios)
    ");

    foreach ($_POST as $key => $value) {
        if (in_array($key, ['data', 'nome', 'comentarios'], true)) continue;
        if (!in_array($value, ['0', '1'], true)) continue;

        $stmt->execute([
            ':lote_id'     => $loteId,
            ':data'        => $data,
            ':nome'        => $nome,
            ':codigo'      => $key,
            ':disponivel'  => (int)$value,
            ':comentarios' => $comentarios
        ]);
    }

    $pdo->commit();
    echo "<div class='max-w-xl mx-auto mt-12 bg-white border-l-4 border-primary p-6 rounded-xl shadow-md'>
            <h2 class='text-xl font-semibold text-primary mb-2'>Formulário salvo com sucesso!</h2>
            <p class='text-sm text-gray-700'>Lote ID: " . htmlspecialchars($loteId) . "</p>
          </div>";
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "<div class='max-w-xl mx-auto mt-12 bg-white border-l-4 border-danger p-6 rounded-xl shadow-md'>
            <h2 class='text-xl font-semibold text-danger mb-2'>Erro ao salvar</h2>
            <p class='text-sm text-gray-700'>" . htmlspecialchars($e->getMessage()) . "</p>
          </div>";
}
?>
  </main>
</body>
</html>