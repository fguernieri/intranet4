<?php
require_once '../../config/db.php';

$id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    if ($id) {
        try {
            $stmt = $pdo->prepare("SELECT nome_prato FROM ficha_tecnica WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $ficha = $stmt->fetch();

            if ($ficha) {
                $nome = $ficha['nome_prato'];
                $stmtHist = $pdo->prepare("INSERT INTO historico (ficha_id, campo_alterado, valor_antigo, valor_novo, usuario)
                    VALUES (:ficha_id, 'exclusao_ficha', :valor_antigo, '', :usuario)");
                $stmtHist->execute([
                    ':ficha_id' => $id,
                    ':valor_antigo' => $nome,
                    ':usuario' => 'sistema'
                ]);
            }

            $pdo->prepare("DELETE FROM ingredientes WHERE ficha_id = :id")->execute([':id' => $id]);
            $pdo->prepare("DELETE FROM historico WHERE ficha_id = :id")->execute([':id' => $id]);
            $pdo->prepare("DELETE FROM ficha_tecnica WHERE id = :id")->execute([':id' => $id]);

            header("Location: consulta.php?excluido=1");
            exit;
        } catch (Exception $e) {
            die("Erro ao excluir ficha: " . $e->getMessage());
        }
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Excluir Ficha</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-black bg-opacity-90 min-h-screen flex items-center justify-center px-4">

  <!-- Modal -->
  <div class="bg-gray-800 text-white rounded-lg shadow-lg p-8 max-w-lg w-full space-y-6 border border-red-500">

    <h2 class="text-2xl font-bold text-red-400 text-center">⚠️ Confirmar Exclusão</h2>

    <p class="text-center text-gray-300">
      Tem certeza que deseja excluir esta ficha técnica?<br>
      Essa ação não poderá ser desfeita.
    </p>

    <form method="POST" class="flex flex-col sm:flex-row justify-center gap-4 mt-4">
      <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">

      <a href="consulta.php"
         class="min-w-[170px] text-center px-6 py-3 bg-gray-600 hover:bg-gray-500 text-white rounded shadow font-semibold transition">
        Cancelar
      </a>

      <button type="submit"
              class="min-w-[170px] px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded shadow transition">
        Confirmar Exclusão
      </button>
    </form>

  </div>

</body>
</html>
