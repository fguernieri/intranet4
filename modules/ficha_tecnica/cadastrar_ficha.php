<?php

include $_SERVER['DOCUMENT_ROOT'] . '/sidebar.php';

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Cadastrar Ficha Técnica</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-900 text-white min-h-screen flex">

  <div class="max-w-6xl mx-auto p-4 md:p-8">
    <div class="bg-gray-800 p-4 md:p-8 rounded-lg shadow-lg">

      <div class="mb-4">
        <a href="consulta.php"
           class="inline-block bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 md:px-6 md:py-3 mt-6 mb-4 rounded-lg shadow font-semibold">
          ⬅️ Voltar para Consulta
        </a>
      </div>

      <h1 class="text-2xl md:text-3xl font-bold text-cyan-400 mt-6 mb-4 text-center">
        Cadastrar Nova Ficha Técnica
      </h1>

      <form action="salvar_ficha.php" method="POST" enctype="multipart/form-data" class="grid gap-6">
        <div class="grid grid-cols-1 mt-6 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-cyan-300 mb-1 font-medium">Cód Cloudify</label>
            <input type="text" name="codigo_cloudify" id="codigo_cloudify"
                   class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 focus:ring-2 focus:ring-cyan-500">
          </div>
          <div class="md:col-span-2">
            <label class="block text-cyan-300 mb-1 font-medium">Nome do Prato</label>
            <input type="text" name="nome_prato" id="nome_prato" required
                   class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 focus:ring-2 focus:ring-cyan-500">
          </div>
          <div>
            <label class="block text-cyan-300 mb-1 font-medium">Integração</label>
            <input type="text" class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 focus:ring-2 focus:ring-cyan-500">
          </div>
          <div class="md:col-span-4">
            <span class="block text-cyan-300 mb-2 font-medium">Base de origem dos dados</span>
            <div class="flex items-center gap-6 text-white">
              <label class="inline-flex items-center gap-2">
                <input type="radio" name="base_origem" value="WAB" required checked class="text-cyan-500">
                <span>WAB</span>
              </label>
              <label class="inline-flex items-center gap-2">
                <input type="radio" name="base_origem" value="BDF" required class="text-cyan-500">
                <span>BDF</span>
              </label>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-cyan-300 mb-1 font-medium">Rendimento</label>
            <input type="text" name="rendimento" required
                   class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 focus:ring-2 focus:ring-cyan-500">
          </div>
          <div class="md:col-span-2">
            <label class="block text-cyan-300 mb-1 font-medium">Imagem (opcional)</label>
            <input type="file" name="imagem" accept=".jpg,.jpeg,.png"
                   class="w-full p-2 bg-gray-800 border border-gray-700 rounded-lg file:text-white file:bg-cyan-500 file:px-4 file:py-1 file:font-semibold">
          </div>
          <div>
          <label class="block text-cyan-300 mb-1 font-medium">Responsável</label>
          <input type="text" name="usuario" required
                 class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 focus:ring-2 focus:ring-cyan-500">
          </div>

        </div>


        <div class="bg-gray-700 p-4 rounded-lg">
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

        <div>
          <h2 class="text-xl font-bold text-cyan-300 mt-6 mb-4">Ingredientes</h2>

          <div id="ingredientesContainer" class="grid gap-4 md:grid-cols-5">
            <div>
              <label class="block text-cyan-300 mb-1">Código (opcional)</label>
              <input type="text" name="codigo[]" class="w-full p-2 rounded-lg bg-gray-800 border border-gray-700">
            </div>
            <div class="md:col-span-2">
              <label class="block text-cyan-300 mb-1">Descrição</label>
              <input type="text" name="descricao[]" required class="w-full p-2 rounded-lg bg-gray-800 border border-gray-700">
            </div>
            <div>
              <label class="block text-cyan-300 mb-1">Quantidade</label>
              <input type="number" step="0.001" name="quantidade[]" required class="w-full p-2 rounded-lg bg-gray-800 border border-gray-700">
            </div>
            <div>
              <label class="block text-cyan-300 mb-1">Unidade</label>
              <select name="unidade[]" required class="w-full p-2 rounded-lg bg-gray-800 border border-gray-700 text-white">
                <option value="">Selecione</option>
                <option value="g">g</option>
                <option value="KG">KG</option>
                <option value="ml">ml</option>
                <option value="LT">LT</option>
                <option value="UND">UND</option>
                <option value="pct">pct</option>
              </select>
            </div>
          </div>
          <template id="ingredienteTemplate">
            <div>
              <label class="block text-cyan-300 mb-1">Código (opcional)</label>
              <input type="text" name="codigo[]" class="w-full p-2 rounded-lg bg-gray-800 border border-gray-700">
            </div>
            <div class="md:col-span-2">
              <label class="block text-cyan-300 mb-1">Descrição</label>
              <input type="text" name="descricao[]" required class="w-full p-2 rounded-lg bg-gray-800 border border-gray-700">
            </div>
            <div>
              <label class="block text-cyan-300 mb-1">Quantidade</label>
              <input type="number" step="0.001" name="quantidade[]" required class="w-full p-2 rounded-lg bg-gray-800 border border-gray-700">
            </div>
            <div>
              <label class="block text-cyan-300 mb-1">Unidade</label>
              <select name="unidade[]" required class="w-full p-2 rounded-lg bg-gray-800 border border-gray-700 text-white">
                <option value="">Selecione</option>
                <option value="g">g</option>
                <option value="KG">KG</option>
                <option value="ml">ml</option>
                <option value="LT">LT</option>
                <option value="UND">UND</option>
                <option value="pct">pct</option>
              </select>
            </div>
          </template>

          <div class="mt-4">
            <button type="button" onclick="addIngrediente()"
                    class="bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded-lg shadow font-semibold">
              ➕ ingredientes
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
            $editor_value = ''; // ou valor vindo do banco
            include $_SERVER['DOCUMENT_ROOT'] . '/components/editor.php';
          ?>
        </div>     
        
        <div class="flex justify-center">
          <button type="submit"
                  class="bg-cyan-500 hover:bg-cyan-600 text-white px-8 py-3 mb-2 rounded-lg shadow font-semibold">
            📎 Cadastrar Ficha
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function obterBaseSelecionada() {
      const selecionado = document.querySelector('input[name="base_origem"]:checked');
      return selecionado ? selecionado.value : 'WAB';
    }

    function buscarInsumo() {
      const termo = document.getElementById('busca_insumo').value;
      const tabela = document.getElementById('tabela_resultados');
      const corpo = document.getElementById('corpo_tabela');

      if (termo.length < 2) {
        tabela.classList.add('hidden');
        return;
      }

      const base = obterBaseSelecionada();

      fetch('buscar_insumos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'termo=' + encodeURIComponent(termo) + '&base=' + encodeURIComponent(base)
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

    function addIngrediente() {
      const container = document.getElementById('ingredientesContainer');
      const tpl = document.getElementById('ingredienteTemplate');
      const clone = tpl.content.cloneNode(true);
      container.appendChild(clone);
      aplicarBuscaPorCodigo();
    }

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
              body: 'codigo=' + encodeURIComponent(codigoValor) + '&base=' + encodeURIComponent(obterBaseSelecionada())
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
      aplicarBuscaPorCodigo();

      const codInput = document.getElementById('codigo_cloudify');
      const nomeInput = document.getElementById('nome_prato');

      if (codInput && nomeInput) {
        codInput.addEventListener('blur', function () {
          const codigo = this.value.trim();
          if (!codigo) return;

          fetch('buscar_prato.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'codigo_cloudify=' + encodeURIComponent(codigo) + '&base=' + encodeURIComponent(obterBaseSelecionada())
          })
          .then(res => res.json())
          .then(data => {
            if (data && data.nome_prato) {
              nomeInput.value = data.nome_prato;
            }
          });
        });
      }

      document.querySelectorAll('input[name="base_origem"]').forEach(radio => {
        radio.addEventListener('change', () => {
          buscarInsumo();
        });
      });
    });
  </script>
</body>
</html>
