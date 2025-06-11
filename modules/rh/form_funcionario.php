<?php

include $_SERVER['DOCUMENT_ROOT'] . '/sidebar.php';

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <title>Cadastro de Funcionário</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/cleave.js@1.6.0/dist/cleave.min.js"></script>
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-400 mt-12 mb-8 min-h-screen flex">

  <div class="max-w-3xl mx-auto bg-white p-6 rounded-2xl shadow-md">
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
      <h1 class="text-2xl font-bold">Cadastro de Funcionários</h1>
      <a href="form_funcionario.php" class="btn-acao py-2 px-4">+ Novo Funcionário</a>
    </div>

    <form action="salvar_funcionario.php" class="space-y-6" id="formFuncionario" method="POST">
    
      <div class="relative">
        <label class="block text-sm font-semibold">Buscar Funcionário</label>
        <input type="text" id="buscaNome" class="mt-1 p-2 w-full border rounded" placeholder="Digite 3 letras ou mais..." autocomplete="off" />
        <ul id="listaSugestoes" class="absolute z-10 w-full bg-white border rounded shadow max-h-60 overflow-auto hidden"></ul>
      </div>
      <hr class="divider_yellow">
      <!-- Dados Pessoais -->
      <fieldset>
        <legend class="text-lg font-semibold mb-2">Dados Pessoais</legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm">Nome completo (*)
              <input type="text" class="mt-1 p-2 w-full border rounded" name="nome_completo" required />
            </label>
          </div>
          <div>
            <label class="block text-sm">CPF (*)
              <input type="text" class="mt-1 p-2 w-full border rounded" id="cpf" name="cpf" placeholder="000.000.000-00" required />
              <small class="text-red-600 hidden" id="cpfErro">CPF inválido</small>
            </label>
          </div>
          <div>
            <label class="block text-sm">RG
              <input type="text" class="mt-1 p-2 w-full border rounded" name="rg" />
            </label>
          </div>
          <div>
            <label class="block text-sm">Data de nascimento
              <input type="date" class="mt-1 p-2 w-full border rounded" name="data_nascimento" />
            </label>
          </div>
          <hr class="divider_yellow col-span-2">
          <!-- Endereço Detalhado -->
          <fieldset class="col-span-2">
            <legend class="text-lg font-semibold mb-2">Endereço</legend>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="md:col-span-2">
                <label class="block text-sm">Logradouro
                  <input type="text" class="mt-1 p-2 w-full border rounded" name="logradouro" placeholder="Rua, avenida, etc." required />
                </label>
              </div>
              <div>
                <label class="block text-sm">Número
                  <input type="text" class="mt-1 p-2 w-full border rounded" name="numero" placeholder="Ex: 123" required />
                </label>
              </div>
              <div>
                <label class="block text-sm">Complemento
                  <input type="text" class="mt-1 p-2 w-full border rounded" name="complemento" placeholder="Apto, Bloco, etc." />
                </label>
              </div>
              <div>
                <label class="block text-sm">Bairro
                  <input type="text" class="mt-1 p-2 w-full border rounded" name="bairro" required />
                </label>
              </div>
              <div>
                <label class="block text-sm">Cidade
                  <input type="text" class="mt-1 p-2 w-full border rounded" name="cidade" required />
                </label>
              </div>
              <div>
                <label class="block text-sm">Estado
                  <input type="text" class="mt-1 p-2 w-full border rounded uppercase" name="estado" maxlength="2" placeholder="SP" required />
                </label>
              </div>
              <div>
                <label class="block text-sm">CEP (*)
                  <input type="text" class="mt-1 p-2 w-full border rounded" id="cep" name="cep" placeholder="00000-000" required />
                </label>
              </div>
            </div>
          </fieldset>
          <div>
            <label class="block text-sm">Telefone
              <input type="text" class="mt-1 p-2 w-full border rounded" id="telefone" name="telefone" placeholder="(00) 00000-0000" />
              <small class="text-red-600 hidden" id="telefoneErro">Telefone inválido</small>
            </label>
          </div>
          <div>
            <label class="block text-sm">E-mail
              <input type="email" class="mt-1 p-2 w-full border rounded" name="email" />
            </label>
          </div>
        </div>
      </fieldset>
      <hr class="divider_yellow">
      

      <!-- Dados para contato -->
      <fieldset>
        <legend class="text-lg font-semibold mb-2">Dados para Contato</legend>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm">Nome
              <input type="text" class="mt-1 p-2 w-full border rounded" name="nome_contato" />
            </label>
          </div>
          <div>
            <label class="block text-sm">Telefone
              <input type="text" class="mt-1 p-2 w-full border rounded" name="telefone_contato" placeholder="(00) 00000-0000" />
            </label>
          </div>
          <div>
          <label class="block text-sm mb=2" for="grau_parentesco">Grau de parentesco:</label>
            <select class="mt-1 p-2 w-full border rounded text-sm" name="grau_parentesco">
              <option value="">Selecione</option>
              <option value="pai_mae">Pai/Mãe</option>
              <option value="irmaos">Irmãos</option>
              <option value="filho">Filho(a)</option>
              <option value="esposo">Esposo(a)</option>
              <option value="outro">Outros</option>
            </select>
          </div>
        </div>
      </fieldset>
      <hr class="divider_yellow">

      <!-- Informações Profissionais -->
      <fieldset>
        <legend class="text-lg font-semibold mb-2">Informações Profissionais</legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm">Empresa contratante
              <input type="text" class="mt-1 p-2 w-full border rounded" name="empresa_contratante" placeholder="Ex: BAW, HRX, Varejista" />
            </label>
          </div>
          <div>
            <label class="block text-sm">Cargo
              <input type="text" class="mt-1 p-2 w-full border rounded" name="cargo" />
            </label>
          </div>
          <div>
            <label class="block text-sm">Departamento
              <input type="text" class="mt-1 p-2 w-full border rounded" name="departamento" />
            </label>
          </div>
          <div>
            <label class="block text-sm">Data de admissão
              <input type="date" class="mt-1 p-2 w-full border rounded" name="data_admissao" />
            </label>
          </div>
          <div>
            <label class="block text-sm">Nº Folha
              <input type="text" class="mt-1 p-2 w-full border rounded" name="numero_folha" placeholder="Ex: 12" />
            </label>
          </div>
          <div>
            <label class="block text-sm">Nº PIS/PASEP
              <input type="text" class="mt-1 p-2 w-full border rounded" name="numero_pis" placeholder="Ex: 123.45678.90-1" />
            </label>
          </div>
          <div>
            <label class="block text-sm">Tipo de contrato
              <select class="mt-1 p-2 w-full border rounded" id="tipo_contrato" name="tipo_contrato">
                <option value="">Selecione</option>
                <option value="CLT">CLT</option>
                <option value="PJ">PJ</option>
                <option value="Estágio">Estágio</option>
                <option value="Outros">Outros</option>
              </select>
            </label>
          </div>
          <div>
            <label class="block text-sm">Salário
              <input type="text" class="mt-1 p-2 w-full border rounded" name="salario" placeholder="Ex: 1.500,00" />
            </label>
          </div>
          <div id="cnpjContainer">
            <label class="block text-sm">CNPJ
              <input type="text" class="mt-1 p-2 w-full border rounded bg-gray-100" id="cnpj" name="cnpj" placeholder="00.000.000/0000-00" disabled />
              <small class="text-red-600 hidden" id="cnpjErro">CNPJ inválido</small>
            </label>
          </div>
        </div>
        <div>
          <label class="block text-sm">Data da demissão
            <input type="date" class="mt-1 p-2 w-full border rounded" name="data_demissao" />
          </label>
        </div>
      </fieldset>
      <hr class="divider_yellow">

      <!-- Dados Bancários -->
      <fieldset>
        <legend class="text-lg font-semibold mb-2">Dados Bancários</legend>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm">Banco
              <input type="text" class="mt-1 p-2 w-full border rounded" name="banco" placeholder="" />
            </label>
          </div>
          <div>
            <label class="block text-sm">Agência
              <input type="text" class="mt-1 p-2 w-full border rounded" name="agencia" placeholder="" />
            </label>
          </div>
          <div>
            <label class="block text-sm">Conta
              <input type="text" class="mt-1 p-2 w-full border rounded" name="conta" placeholder="" />
            </label>
          </div>
          <div>
            <label class="block text-sm">Código do Banco
              <input type="text" class="mt-1 p-2 w-full border rounded" name="codigo_banco" placeholder="Ex: 001" />
            </label>
          </div>
          <div>
            <label class="block text-sm">Chave Pix
              <input type="text" class="mt-1 p-2 w-full border rounded" name="chave_pix" placeholder="CPF, e-mail, telefone ou aleatória" />
            </label>
          </div>
        </div>
      </fieldset>

      <button class="btn-acao" type="submit">Salvar</button>
      <a href="listar_funcionarios.php" class="btn-acao-vermelho">Cancelar</a>

    </form>
  </div>

  <!-- Scripts -->
  <script>
    new Cleave('#cpf', {
      delimiters: ['.', '.', '-'],
      blocks: [3, 3, 3, 2],
      numericOnly: true
    });

    new Cleave('#telefone', {
      delimiters: ['(', ') ', '-', ''],
      blocks: [0, 2, 5, 4],
      numericOnly: true
    });

    function validarCPF(cpf) {
      cpf = cpf.replace(/\D/g, '');
      if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
      let soma = 0, resto;
      for (let i = 1; i <= 9; i++) soma += parseInt(cpf.charAt(i - 1)) * (11 - i);
      resto = (soma * 10) % 11;
      if (resto === 10 || resto === 11) resto = 0;
      if (resto !== parseInt(cpf.charAt(9))) return false;
      soma = 0;
      for (let i = 1; i <= 10; i++) soma += parseInt(cpf.charAt(i - 1)) * (12 - i);
      resto = (soma * 10) % 11;
      if (resto === 10 || resto === 11) resto = 0;
      return resto === parseInt(cpf.charAt(10));
    }

    document.querySelector("#cpf").addEventListener('blur', function () {
      const valido = validarCPF(this.value);
      document.getElementById("cpfErro").classList.toggle('hidden', valido);
      this.classList.toggle('border-red-500', !valido);
    });

    new Cleave('[name="salario"]', {
      numeral: true,
      numeralThousandsGroupStyle: 'thousand',
      numeralDecimalMark: ',',
      delimiter: '.'
    });

    function validarTelefone(tel) {
      tel = tel.replace(/\D/g, '');
      return tel.length === 11 && tel[2] === '9';
    }

    function validarEmail(email) {
      return /^\S+@\S+\.\S+$/.test(email);
    }

    function validarCodigoBanco(codigo) {
      return /^\d{3}$/.test(codigo);
    }

    document.querySelector("#telefone").addEventListener('blur', function () {
      const valido = validarTelefone(this.value);
      document.getElementById("telefoneErro").classList.toggle('hidden', valido);
      this.classList.toggle('border-red-500', !valido);
    });

    document.querySelector("input[name='email']").addEventListener('blur', function () {
      const valido = validarEmail(this.value);
      if (!document.getElementById("emailErro")) {
        const erro = document.createElement('small');
        erro.id = 'emailErro';
        erro.className = 'text-red-600 mt-1 block';
        erro.textContent = 'E-mail inválido';
        this.parentNode.appendChild(erro);
      }
      const erroEl = document.getElementById("emailErro");
      erroEl.classList.toggle('hidden', valido);
      this.classList.toggle('border-red-500', !valido);
    });

    document.querySelector("input[name='codigo_banco']").addEventListener('blur', function () {
      const valido = validarCodigoBanco(this.value);
      if (!document.getElementById("codigoBancoErro")) {
        const erro = document.createElement('small');
        erro.id = 'codigoBancoErro';
        erro.className = 'text-red-600 mt-1 block';
        erro.textContent = 'Código inválido (3 dígitos)';
        this.parentNode.appendChild(erro);
      }
      const erroEl = document.getElementById("codigoBancoErro");
      erroEl.classList.toggle('hidden', valido);
      this.classList.toggle('border-red-500', !valido);
    });

    document.getElementById('tipo_contrato').addEventListener('change', function () {
      const cnpjInput = document.getElementById('cnpj');
      if (this.value === 'PJ') {
        cnpjInput.disabled = false;
        cnpjInput.classList.remove('bg-gray-100');
      } else {
        cnpjInput.disabled = true;
        cnpjInput.value = '';
        cnpjInput.classList.add('bg-gray-100');
      }
    });

    new Cleave('#cnpj', {
      delimiters: ['.', '.', '/', '-'],
      blocks: [2, 3, 3, 4, 2],
      numericOnly: true
    });

    function validarCNPJ(cnpj) {
      cnpj = cnpj.replace(/\D/g, '');
      if (cnpj.length !== 14 || /^(\d)\1+$/.test(cnpj)) return false;
      let tamanho = cnpj.length - 2;
      let numeros = cnpj.substring(0, tamanho);
      let digitos = cnpj.substring(tamanho);
      let soma = 0, pos = tamanho - 7;
      for (let i = tamanho; i >= 1; i--) {
        soma += numeros.charAt(tamanho - i) * pos--;
        if (pos < 2) pos = 9;
      }
      let resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
      if (resultado != digitos.charAt(0)) return false;
      tamanho += 1;
      numeros = cnpj.substring(0, tamanho);
      soma = 0;
      pos = tamanho - 7;
      for (let i = tamanho; i >= 1; i--) {
        soma += numeros.charAt(tamanho - i) * pos--;
        if (pos < 2) pos = 9;
      }
      resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
      return resultado == digitos.charAt(1);
    }

    document.getElementById('cnpj').addEventListener('blur', function () {
      const valido = validarCNPJ(this.value);
      document.getElementById('cnpjErro').classList.toggle('hidden', valido);
      this.classList.toggle('border-red-500', !valido);
    });

    new Cleave('#cep', {
      delimiters: ['-'],
      blocks: [5, 3],
      numericOnly: true
    });

    // 🔎 Auto completar e preencher o form
    document.getElementById('buscaNome').addEventListener('input', function () {
      const termo = this.value.trim();
      const lista = document.getElementById('listaSugestoes');

      if (termo.length < 3) {
        lista.classList.add('hidden');
        return;
      }

      fetch('buscar_funcionarios.php?nome=' + encodeURIComponent(termo))
        .then(response => response.json())
        .then(data => {
          lista.innerHTML = '';
          if (data.length > 0) {
            data.forEach(func => {
              const li = document.createElement('li');
              li.textContent = func.nome_completo;
              li.className = 'p-2 hover:bg-gray-100 cursor-pointer';
              li.addEventListener('click', () => {
                document.getElementById('buscaNome').value = func.nome_completo;
                lista.classList.add('hidden');

                fetch('get_funcionario.php?nome=' + encodeURIComponent(func.nome_completo))
                  .then(res => res.json())
                  .then(data => {
                    if (!data || data.erro) return;
                    for (const campo in data) {
                      const input = document.querySelector(`[name="${campo}"]`);
                      if (input) {
                        input.value = input.type === 'date'
                          ? (data[campo]?.split('T')[0] || '')
                          : (data[campo] ?? '');
                        if (campo === 'tipo_contrato' && data[campo] === 'PJ') {
                          const cnpjInput = document.getElementById('cnpj');
                          cnpjInput.disabled = false;
                          cnpjInput.classList.remove('bg-gray-100');
                        }
                      }
                    }
                  });
              });
              lista.appendChild(li);
            });
            lista.classList.remove('hidden');
          } else {
            lista.classList.add('hidden');
          }
        });
    });

    // ✅ Converte campos de texto para maiúsculas em tempo real
    function aplicarMaiusculas() {
      document.querySelectorAll('input[type="text"]:not([name="email"])').forEach(input => {
        input.addEventListener('input', () => {
          const cursorPos = input.selectionStart;
          input.value = input.value.toUpperCase();
          input.setSelectionRange(cursorPos, cursorPos); // mantém cursor
        });
      });
    }

    // 🔁 Aplica ao carregar
    document.addEventListener('DOMContentLoaded', aplicarMaiusculas);

    // 🔒 Backup: também força maiúsculas no submit
    document.getElementById('formFuncionario').addEventListener('submit', () => {
      document.querySelectorAll('input[type="text"]:not([name="email"])').forEach(input => {
        input.value = input.value.toUpperCase();
      });
    });
    // Preenche o form quando há parâmetro ?nome=…
    document.addEventListener('DOMContentLoaded', () => {
      const params = new URLSearchParams(window.location.search);
      const nome = params.get('nome');
      if (nome) {
        // define o campo de busca e dispara a requisição
        document.getElementById('buscaNome').value = nome;
        fetch('get_funcionario.php?nome=' + encodeURIComponent(nome))
          .then(res => res.json())
          .then(data => {
            if (!data || data.erro) return;
            for (const campo in data) {
              const input = document.querySelector(`[name="${campo}"]`);
              if (input) {
                input.value = input.type === 'date'
                  ? (data[campo]?.split('T')[0] || '')
                  : (data[campo] ?? '');
                // reativa CNPJ se for PJ
                if (campo === 'tipo_contrato' && data[campo] === 'PJ') {
                  const cnpjInput = document.getElementById('cnpj');
                  cnpjInput.disabled = false;
                  cnpjInput.classList.remove('bg-gray-100');
                }
              }
            }
          });
      }
    });

// Ao sair do campo CEP, busca no ViaCEP e preenche tudo em maiúsculas
document.getElementById('cep').addEventListener('blur', function() {
  const valorCep = this.value.replace(/\D/g, ''); // deixa só números
  if (valorCep.length !== 8) return; // só continua se tiver 8 dígitos

  fetch(`https://viacep.com.br/ws/${valorCep}/json/`)
    .then(res => res.json())
    .then(data => {
      if (!data || data.erro) {
        console.warn('CEP não encontrado');
        // Caso queira, você pode limpar os campos ou mostrar uma mensagem aqui
        return;
      }
      // Preenche os inputs de endereço em MAIÚSCULAS
      document.querySelector('input[name="logradouro"]').value = (data.logradouro || '').toUpperCase();
      document.querySelector('input[name="bairro"]').value    = (data.bairro    || '').toUpperCase();
      document.querySelector('input[name="cidade"]').value    = (data.localidade|| '').toUpperCase();
      document.querySelector('input[name="estado"]').value    = (data.uf        || '').toUpperCase();
    })
    .catch(err => console.error('Erro ao buscar endereço:', err));
});
  </script>
</body>
</html>
