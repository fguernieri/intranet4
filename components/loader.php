<!-- components/loader.php -->
<div id="loader-overlay" class="fixed inset-0 bg-black bg-opacity-80 z-50 flex items-center justify-center hidden">
  <div class="bg-gray-900 p-6 rounded shadow-lg text-green-400 font-mono text-sm space-y-3 text-center border border-green-500">
    <div class="animate-pulse text-lime-400">[LOADING] 🚦 Iniciando protocolo de verificação...</div>
    <div id="loader-text">☕ Preparando insumos com carinho...</div>
    <div class="flex justify-center">
      <svg class="animate-spin h-8 w-8 text-green-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
      </svg>
    </div>
  </div>
</div>

<script>
  const frasesLoader = [
    "☕ Preparando insumos com carinho...",
    "🧪 Analisando diferença molecular dos ingredientes...",
    "💾 Injetando dados no Cloudify...",
    "🧠 Calculando divergência lógica...",
    "🚨 Procurando fichas zumbis...",
    "🔍 Escaneando gramaturas...",
    "🥷 Invadindo banco de dados sigilosos...",
    "🤖 Farolizando as fichas técnicas..."
  ];

  function mostrarLoader() {
    const loader = document.getElementById('loader-overlay');
    const texto = document.getElementById('loader-text');
    let i = 0;
    texto.innerText = frasesLoader[i % frasesLoader.length];
    const interval = setInterval(() => {
      texto.innerText = frasesLoader[i % frasesLoader.length];
      i++;
    }, 1500);

    loader.classList.remove('hidden');
    return () => {
      clearInterval(interval);
      loader.classList.add('hidden');
    };
  }

  function ativarLoaderImport() {
    const stop = mostrarLoader();
    setTimeout(stop, 150000); // fallback
    return true;
  }

  function abrirModalImportacao() {
    const modal = document.getElementById('modal-importacao');
    const iframe = document.getElementById('iframe-importacao');
    iframe.src = 'import_csv.php';
    modal.classList.remove('hidden');
  }

  function fecharModalImportacao() {
    const modal = document.getElementById('modal-importacao');
    const iframe = document.getElementById('iframe-importacao');
    modal.classList.add('hidden');
    iframe.src = '';
    location.reload();
  }
</script>
