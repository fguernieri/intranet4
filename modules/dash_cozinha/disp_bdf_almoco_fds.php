<?php require_once '../../config/db.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Disp de Cardápio BDF Almoço FDS</title>
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
<body class="bg-primary bg-opacity-70 min-h-screen flex items-center justify-center p-4">
  <form class="p-6 space-y-6 max-w-3xl w-full bg-white rounded-2xl shadow-md border border-primary" method="post" action="salvar_disp_bdf_almoco_fds.php">
    <h1 class="text-2xl font-bold text-primary text-center">Disp de Cardápio BDF Almoço FDS</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-primary mb-1" for="data">Data *</label>
        <input type="date" id="data" name="data" required class="w-full border border-primary rounded-xl p-2 focus:outline-none focus:ring-2 focus:ring-primary">
      </div>

      <div>
        <label class="block text-sm font-medium text-primary mb-1" for="nome">Nome *</label>
        <input type="text" id="nome" name="nome" required class="w-full border border-primary rounded-xl p-2 focus:outline-none focus:ring-2 focus:ring-primary">
      </div>
    </div>

    <div class="grid grid-cols-1 gap-4">
      <?php
      try {
        $stmt = $pdo->query("SELECT codigo_cloudify, nome_prato FROM ficha_tecnica WHERE ativo_bdf_almoco_fds = 1 ORDER BY nome_prato");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
          $codigo = htmlspecialchars($row['codigo_cloudify']);
          $nome = htmlspecialchars($row['nome_prato']);
          echo "<div class=\"bg-gray-50 border border-gray-200 rounded-xl p-4 shadow-sm\">";
          echo "<fieldset>";
          echo "<legend class=\"font-semibold text-warning\">{$nome} ({$codigo}) *</legend>";
          echo "<div class=\"flex gap-4 mt-2\">";
          echo "<label><input type=\"radio\" name=\"{$codigo}\" value=\"1\" required class=\"mr-1\">Sim</label>";
          echo "<label><input type=\"radio\" name=\"{$codigo}\" value=\"0\" class=\"mr-1\">Não</label>";
          echo "</div>";
          echo "</fieldset>";
          echo "</div>";
        }
      } catch (PDOException $e) {
        echo "<p class='text-danger'>Erro ao carregar os produtos: " . $e->getMessage() . "</p>";
      }
      ?>
    </div>

    <div>
      <label class="block text-sm font-medium text-warning mb-1" for="comentarios">Comentários Gerais</label>
      <textarea id="comentarios" name="comentarios" rows="4" class="w-full border border-primary rounded-xl p-2 focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
    </div>

    <div class="flex justify-end">
      <button type="submit" class="bg-primary text-white px-6 py-2 rounded-xl hover:bg-opacity-80 transition duration-300">Enviar</button>
    </div>
  </form>
</body>
</html>
