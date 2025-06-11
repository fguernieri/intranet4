<?php
require_once '../../config/db.php';
require_once '../../config/db_dw.php';

include '../../sidebar.php';


$id = $_GET['id'] ?? null;
if (!$id) {
    echo "ID inválido.";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM ficha_tecnica WHERE id = :id");
$stmt->execute([':id' => $id]);
$ficha = $stmt->fetch();

if (!$ficha) {
    echo "Ficha não encontrada.";
    exit;
}

// Ingredientes
$stmtIng = $pdo->prepare("SELECT * FROM ingredientes WHERE ficha_id = :id");
$stmtIng->execute([':id' => $id]);
$ingredientes = $stmtIng->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Editar: <?= htmlspecialchars($ficha['nome_prato']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex">

  <div class="max-w-5xl mx-auto bg-gray-800 rounded shadow p-8">

    <!-- Botão voltar -->
    <div class="mb-6">
      <a href="consulta.php"
         class="inline-block bg-gray-700 hover:bg-gray-600 text-white px-6 py-3 rounded shadow no-underline min-w-[170px] text-center font-semibold">
        ⬅️ Voltar para Consulta
      </a>
    </div>

    <h1 class="text-2xl font-bold text-cyan-400 text-center mb-8">
      Editar Ficha Técnica: <?= htmlspecialchars($ficha['nome_prato']) ?>
    </h1>

    <form action="salvar_edicao.php" method="POST" enctype="multipart/form-data" class="space-y-6">
      <input type="hidden" name="id" value="<?= $ficha['id'] ?>">

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <!-- Código Cloudify -->
      <div>
        <label class="block text-cyan-300 mb-1 font-medium">Cód Cloudify</label>
        <input type="text" name="codigo_cloudify" id="codigo_cloudify"
               value="<?= htmlspecialchars($ficha['codigo_cloudify'] ?? '') ?>"
               class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 focus:ring-2 focus:ring-cyan-500">
      </div>

      <!-- Nome do Prato -->
      <div class="md:col-span-3">
        <label class="block text-cyan-300 mb-1 font-medium">Nome do Prato</label>
        <input type="text" name="nome_prato" id="nome_prato" required
               value="<?= htmlspecialchars($ficha['nome_prato']) ?>"
               class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 focus:ring-2 focus:ring-cyan-500">
      </div>

          <!-- Status de ativação -->
          <!-- Toggle WAB -->
          <label class="custom-switch custom-switch-small mb-2">
            <input type="checkbox" name="ativo_wab" id="ativo_wab" class="custom-switch-input"
              <?php echo isset($ficha['ativo_wab']) && $ficha['ativo_wab'] == 1 ? 'checked' : ''; ?>>
            <span class="custom-switch-slider"></span>
            <span class="custom-switch-label">WAB</span>
          </label>

          <!-- Toggle BDF -->
          <label class="custom-switch custom-switch-small">
            <input type="checkbox" name="ativo_bdf_almoco" id="ativo_bdf_almoco" class="custom-switch-input"
              <?php echo isset($ficha['ativo_bdf_almoco']) && $ficha['ativo_bdf_almoco'] == 1 ? 'checked' : ''; ?>>
            <span class="custom-switch-slider"></span>
            <span class="custom-switch-label">BDF Almoço Semana</span>
          </label>
          <label class="custom-switch custom-switch-small">
            <input type="checkbox" name="ativo_bdf_almoco_fds" id="ativo_bdf_almoco_fds" class="custom-switch-input"
              <?php echo isset($ficha['ativo_bdf_almoco_fds']) && $ficha['ativo_bdf_almoco_fds'] == 1 ? 'checked' : ''; ?>>
            <span class="custom-switch-slider"></span>
            <span class="custom-switch-label">BDF Almoço FDS</span>
          </label>
          <label class="custom-switch custom-switch-small">
            <input type="checkbox" name="ativo_bdf_noite" id="ativo_bdf_noite" class="custom-switch-input"
              <?php echo isset($ficha['ativo_bdf_noite']) && $ficha['ativo_bdf_noite'] == 1 ? 'checked' : ''; ?>>
            <span class="custom-switch-slider"></span>
            <span class="custom-switch-label">BDF Noite</span>
          </label>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <!-- Rendimento -->
      <div>
        <label class="block text-cyan-300 mb-1 font-medium">Rendimento</label>
        <input type="text" name="rendimento" required
               value="<?= htmlspecialchars($ficha['rendimento']) ?>"
               class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 focus:ring-2 focus:ring-cyan-500">
      </div>

      <!-- Imagem -->
      <div class="md:col-span-2">
        <label class="block text-cyan-300 mb-1 font-medium">Imagem (opcional)</label>
        <input type="file" name="imagem" accept=".jpg,.jpeg,.png"
               class="w-full p-2 bg-gray-800 border border-gray-700 rounded-lg file:text-white file:bg-cyan-500 file:px-4 file:py-1 file:font-semibold">
      </div>

      <!-- Responsável -->
      <div>
        <label class="block text-cyan-300 mb-1 font-medium">Responsável</label>
        <input type="text" name="usuario" required
               value="<?= htmlspecialchars($ficha['usuario']) ?>"
               class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 focus:ring-2 focus:ring-cyan-500">
      </div>
    </div>

      <!-- Busca de Insumo -->
      <div class="col-span-1 md:col-span-2 bg-gray-700 p-4 rounded-lg">
        <label for="busca_insumo" class="block text-sm font-semibold text-white mb-2">
          Buscar insumo por nome:
        </label>
        <input id="busca_insumo" type="text" oninput="buscarInsumo()"
               class="w-full p-3 rounded bg-white text-gray-900 border border-gray-500"
               placeholder="Digite pelo menos 2 caracteres">

        <div id="tabela_resultados" class="mt-4 hidden overflow-x-auto">
          <table class="w-full bg-gray-500 border border-gray-400 rounded-lg">
            <thead>
              <tr class="bg-gray-200 text-gray-800">
                <th class="px-4 py-2 text-left">Descrição</th>
                <th class="px-4 py-2 text-left">Código</th>
                <th class="px-4 py-2 text-left">Unidade</th>
              </tr>
            </thead>
            <tbody id="corpo_tabela"></tbody>
          </table>
        </div>
      </div>

      <!-- Ingredientes -->
      <div class="col-span-1 md:col-span-2">
        <h2 class="text-xl font-bold text-cyan-300 mb-4">Ingredientes</h2>

        <div id="ingredientesContainer" class="space-y-4">
          <?php foreach ($ingredientes as $ing): ?>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
              <input type="hidden" name="ingrediente_id[]" value="<?= $ing['id'] ?>">
              <input type="hidden" name="excluir[]" value="0">

              <div>
                <label class="text-cyan-300 block mb-1">Código (opcional)</label>
                <input type="text" name="codigo[]" value="<?= htmlspecialchars($ing['codigo']) ?>" class="w-full p-2 rounded bg-gray-800 border border-gray-700">
              </div>
              <div>
                <label class="text-cyan-300 block mb-1">Descrição</label>
                <input type="text" name="descricao[]" value="<?= htmlspecialchars($ing['descricao']) ?>" class="w-full p-2 rounded bg-gray-800 border border-gray-700" required>
              </div>
              <div>
                <label class="text-cyan-300 block mb-1">Quantidade</label>
                <input type="number" step="0.001" name="quantidade[]" value="<?= $ing['quantidade'] ?>" class="w-full p-2 rounded bg-gray-800 border border-gray-700" required>
              </div>
              <div>
                <label class="text-cyan-300 block mb-1">Unidade</label>
                <select name="unidade[]" class="w-full p-2 rounded bg-gray-800 border border-gray-700 text-white" required>
                  <?php
                    $unidades = ['g', 'KG', 'ml', 'LT', 'UND'];
                    foreach ($unidades as $un) {
                      $selected = $un === $ing['unidade'] ? 'selected' : '';
                      echo "<option value=\"$un\" $selected>$un</option>";
                    }
                  ?>
                </select>
              </div>
              <div class="flex justify-center items-end pb-2">
                <button type="button" onclick="excluirIngrediente(this)"
                        class="text-red-400 hover:text-red-600 font-bold text-sm">🗑️</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Botão adicionar novo ingrediente -->
        <div class="mt-4">
          <button type="button" onclick="addIngrediente()"
                  class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded shadow font-semibold">
            ➕ Adicionar Ingrediente
          </button>
        </div>
      </div>

      
      <!-- Campo de Modo de Preparo com TinyMCE -->
      <div class="mt-6 ">
      <label for="modo_preparo" class="block mb-4 text-xl font-bold text-cyan-300">Modo de Preparo</label>
        <?php
          $editor_id = 'modo_preparo';
          $editor_name = 'modo_preparo';
          $editor_label = 'Modo de Preparo';
          $editor_value = $ficha['modo_preparo']; // ou valor vindo do banco
          include $_SERVER['DOCUMENT_ROOT'] . '/components/editor.php';
        ?>
      </div>     
      
      <!-- Botão salvar -->
      <div class="col-span-1 md:col-span-2 flex justify-center">
        <button type="submit"
                class="bg-cyan-500 hover:bg-cyan-600 text-white font-semibold px-8 py-3 rounded shadow min-w-[170px]">
          💾 Salvar Alterações
        </button>
      </div>
    </form>
  </div>

  <!-- Template de ingrediente vazio -->
  <template id="linhaIngredienteVazia">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end mt-4">
      <input type="hidden" name="ingrediente_id[]" value="">
      <input type="hidden" name="excluir[]" value="0">

      <div>
        <label class="text-cyan-300 block mb-1">Código (opcional)</label>
        <input type="text" name="codigo[]" class="w-full p-2 rounded bg-gray-800 border border-gray-700">
      </div>
      <div>
        <label class="text-cyan-300 block mb-1">Descrição</label>
        <input type="text" name="descricao[]" class="w-full p-2 rounded bg-gray-800 border border-gray-700" required>
      </div>
      <div>
        <label class="text-cyan-300 block mb-1">Quantidade</label>
        <input type="number" step="0.001" name="quantidade[]" class="w-full p-2 rounded bg-gray-800 border border-gray-700" required>
      </div>
      <div>
        <label class="text-cyan-300 block mb-1">Unidade</label>
        <select name="unidade[]" class="w-full p-2 rounded bg-gray-800 border border-gray-700 text-white" required>
                <option value="">Selecione</option>
                <option value="g">g</option>
                <option value="KG">KG</option>
                <option value="ml">ml</option>
                <option value="LT">LT</option>
                <option value="UND">UND</option>
        </select>
      </div>
      <div class="flex justify-center items-end pb-2">
        <button type="button" onclick="excluirIngrediente(this)"
                class="text-red-400 hover:text-red-600 font-bold text-sm">🗑️</button>
      </div>
    </div>
  </template>

  <script>
    function addIngrediente() {
      const template = document.getElementById('linhaIngredienteVazia');
      const container = document.getElementById('ingredientesContainer');
      const novaLinha = template.content.cloneNode(true);
      container.appendChild(novaLinha);
      aplicarBuscaPorCodigo();
    }

    function excluirIngrediente(botao) {
      const linha = botao.closest('.grid');
      linha.style.display = 'none';
      linha.querySelector('[name="excluir[]"]').value = "1";
    }
    
    // Função de busca por insumo
    function buscarInsumo() {
      const termo = document.getElementById('busca_insumo').value;
      const tabela = document.getElementById('tabela_resultados');
      const corpo = document.getElementById('corpo_tabela');

      if (termo.length < 2) {
        tabela.classList.add('hidden');
        return;
      }

      fetch('buscar_insumos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'termo=' + encodeURIComponent(termo)
      })
      .then(res => res.json())
      .then(dados => {
        const seen = new Set();
        const resultados = dados.filter(item => {
          if (seen.has(item.codigo)) return false;
          seen.add(item.codigo);
          return true;
        });

        corpo.innerHTML = '';
        if (resultados.length) {
          resultados.forEach(insumo => {
            const tr = document.createElement('tr');
            tr.className = 'border-t';
            tr.innerHTML = `
              <td class="px-4 py-2 text-gray-900">${insumo.Insumo}</td>
              <td class="px-4 py-2 text-gray-900">${insumo.codigo}</td>
              <td class="px-4 py-2 text-gray-900">${insumo.unidade}</td>
            `;
            corpo.appendChild(tr);
          });
          tabela.classList.remove('hidden');
        } else {
          tabela.classList.add('hidden');
        }
      });
    }
    
    // Busca por código de insumo em campos dinâmicos
    function aplicarBuscaPorCodigo() {
      document.querySelectorAll("input[name='codigo[]']").forEach(input => {
        if (!input.dataset.listener) {
          input.addEventListener('blur', function() {
            const codigoDiv   = this.parentElement;
            const codigoValor = this.value.trim();
            if (!codigoValor) return;

            fetch('buscar_insumos.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: 'codigo=' + encodeURIComponent(codigoValor)
            })
            .then(res => res.json())
            .then(dados => {
              if (!dados.length) return;

              const descDiv    = codigoDiv.nextElementSibling;
              const unidadeDiv = descDiv.nextElementSibling.nextElementSibling;

              descDiv.querySelector("input[name='descricao[]']").value = dados[0].Insumo;
              unidadeDiv.querySelector("select[name='unidade[]']").value = dados[0].unidade;
            });
          });
          input.dataset.listener = true;
        }
      });
    }
    
    document.addEventListener('DOMContentLoaded', () => {
      const codInput = document.getElementById('codigo_cloudify');
      const nomeInput = document.getElementById('nome_prato');

      if (codInput && nomeInput) {
        codInput.addEventListener('blur', function () {
          const codigo = this.value.trim();
          if (!codigo) return;

          fetch('buscar_prato.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'codigo_cloudify=' + encodeURIComponent(codigo)
          })
          .then(res => res.json())
          .then(data => {
            console.log("Retorno:", data); // Para depuração
            if (data && data.nome_prato) {
              nomeInput.value = data.nome_prato;
            }
          })
          .catch(err => console.error("Erro:", err));
        });
      }
      
      // 👇 Esta linha resolve o problema para os campos já carregados
      aplicarBuscaPorCodigo();
    });

  </script>

</body>
</html>
