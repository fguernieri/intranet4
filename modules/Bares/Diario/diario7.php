<?php
ob_start(); // Start output buffering
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../sidebar.php'; // Ajustado para subir 4 níveis até a raiz htdocs// Caminho para sidebar.php a partir de modules/compras/


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Helper function to get day of the week in Portuguese
function getDiaSemanaPortugues($dia_semana_num) {
    $dias = [
        1 => 'Domingo', 2 => 'Segunda-feira', 3 => 'Terça-feira',
        4 => 'Quarta-feira', 5 => 'Quinta-feira', 6 => 'Sexta-feira',
        7 => 'Sábado'
    ];
    return $dias[$dia_semana_num] ?? 'Dia inválido';
}

$ano_selecionado = $_GET['ano'] ?? date('Y');
$mes_selecionado = $_GET['mes'] ?? date('m');
$dia_selecionado = $_GET['dia'] ?? date('d');

$dia_selecionado = $_GET['dia'] ?? date('d');

$faturamento_dia = null;
$dia_semana_pt_selecionado = '';
$media_faturamento_3m = null;
$media_faturamento_6m = null;
$media_faturamento_12m = null;
$comparativo_medias = [];
$data_selecionada_formatada = '';
$dados_grafico_grupo = []; // Para os dados do gráfico
$erro_filtro = '';

// --- FETCH BDF ROW EARLY SO IT'S AVAILABLE FOR FILTER/MUSIC CARD ---
$bdf_row = null;
if ($ano_selecionado && $mes_selecionado && $dia_selecionado) {
    // Prepara a data no formato ISO para passar ao MySQL
    $data_iso  = sprintf('%04d-%02d-%02d', $ano_selecionado, $mes_selecionado, $dia_selecionado);
    // Fetch DiarioDeBordo7Tragos row for this date
    require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
    $conn_bdf = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn_bdf->set_charset('utf8mb4');
    $conn_bdf->set_charset('utf8mb4');
    if (!$conn_bdf->connect_error) {
        $sql_bdf = "SELECT equipe_bar, equipe_salao, equipe_cozinha, hora_de_inicio, clima_chuva, clima_ceu, clima_temperatura, equipamento_funcionamento, equipamento_com_problema, sistema_funcionamento, sistema_com_problema, musica_banda_sempre_digitar_o_nome_dos_mesmo_jeito, musica_resposta_publico, cardapio_comida, cardapio_disponibilidade_comida, cardapio_disponibilidade_drink, cardapio_disponibilidade_chope, cardapio_temperatura_chope, observacoes, algum_evento_relevante_na_cidade FROM DiarioDeBordo7Tragos WHERE DATE(hora_de_inicio) = ? ORDER BY hora_de_inicio LIMIT 1";
        $stmt_bdf = $conn_bdf->prepare($sql_bdf);
        $stmt_bdf->bind_param('s', $data_iso);
        $stmt_bdf->execute();
        $bdf_row = $stmt_bdf->get_result()->fetch_assoc();
        $stmt_bdf->close();
        $conn_bdf->close();
    }
}

if ($ano_selecionado && $mes_selecionado && $dia_selecionado) {
    // Prepara a data no formato ISO para passar ao MySQL
    $data_iso  = sprintf('%04d-%02d-%02d', $ano_selecionado, $mes_selecionado, $dia_selecionado);
    $dtObj     = new DateTime($data_iso);
    $data_selecionada_formatada = $dtObj->format('d/m/Y');
    $dia_semana_num = ((int)$dtObj->format('w')) + 1; // 1=Dom … 7=Sáb

    // 1) Faturamento do dia
    // Se 'data' é DATE, não precisamos de STR_TO_DATE nela.
    $sql1 = "
      SELECT SUM(total) AS faturamento
        FROM fVendas7Tragos
       WHERE `data` = ?
    ";
    $stmt1 = $conn->prepare($sql1);
    $stmt1->bind_param('s', $data_iso);
    $stmt1->execute();
    $faturamento_dia = $stmt1->get_result()->fetch_assoc()['faturamento'] ?? 0;
    $stmt1->close();

    // 2) Médias de faturamento para o mesmo dia da semana (3, 6, 12 e 24 meses)
    $periodos_meses = [
        '3m'  => 3,
        '6m'  => 6,
        '12m' => 12,
        '24m' => 24
    ];
    $medias_faturamento = [];

    foreach ($periodos_meses as $key => $num_meses) {
        // Clonar $dtObj para não afetar o original nas modificações de data
        $dtObjClone = new DateTime($data_iso);
        $data_fim_periodo = $dtObjClone->format('Y-m-d'); // Data selecionada é o fim do período
        $data_ini_periodo = $dtObjClone->modify("-{$num_meses} months")->format('Y-m-d');

        $sql_media = "
          SELECT AVG(daily_sum) AS media
            FROM (
              SELECT SUM(total) AS daily_sum
                FROM fVendas7Tragos
               WHERE DAYOFWEEK(`data`) = ?
                 AND `data` >= ?
                 AND `data` < ? -- Usar '<' para não incluir o próprio dia selecionado na média
               GROUP BY `data`
            ) AS tmp_daily_sums
        ";
        $stmt_media = $conn->prepare($sql_media);
        $stmt_media->bind_param('iss', $dia_semana_num, $data_ini_periodo, $data_fim_periodo);
        $stmt_media->execute();
        $resultado_media = $stmt_media->get_result()->fetch_assoc();
        $medias_faturamento[$key] = $resultado_media['media'] ?? 0;
        $stmt_media->free_result();
        $stmt_media->close();
    }

    $media_faturamento_3m = $medias_faturamento['3m'];
    $media_faturamento_6m = $medias_faturamento['6m'];
    $media_faturamento_12m = $medias_faturamento['12m'];

    // 3) Calcular comparativos percentuais
    $faturamento_dia_val = floatval($faturamento_dia);

    foreach ($medias_faturamento as $periodo => $media_valor) {
        $media_val = floatval($media_valor);
        $comparativo_texto = "N/A"; 
        $comparativo_cor_classe = "text-gray-400";

        if ($media_val > 0) {
            $diferenca_percentual = (($faturamento_dia_val / $media_val) - 1) * 100;
            $comparativo_texto = sprintf("%+.2f%%", $diferenca_percentual);
            if ($diferenca_percentual > 0) {
                $comparativo_cor_classe = "text-green-400";
            } elseif ($diferenca_percentual < 0) {
                $comparativo_cor_classe = "text-red-400";
            }
            // Se for 0.00%, a cor padrão (text-gray-400) já está definida.
        } elseif ($faturamento_dia_val > 0 && $media_val == 0) {
            // Faturou algo, média era zero.
            $comparativo_texto = "N/A"; // Evitar divisão por zero ou "infinito"
            $comparativo_cor_classe = "text-green-400"; // Indicar positividade
        } elseif ($faturamento_dia_val == 0 && $media_val == 0) {
            $comparativo_texto = "+0.00%";
        } else { // $faturamento_dia_val == 0 && $media_val > 0
            $comparativo_texto = "-100.00%";
            $comparativo_cor_classe = "text-red-400";
        }
        $comparativo_medias[$periodo] = [
            'valor' => $media_valor,
            'comparativo_texto' => $comparativo_texto,
            'comparativo_cor_classe' => $comparativo_cor_classe
        ];
    }

    // 4) Dados para o gráfico de vendas por grupo de produto
    // A coluna sanitizada no banco se chama 'grupos_de_produto'
    $sql_grafico = "
        SELECT `grupos_de_produto`, SUM(`total`) AS total_por_grupo
        FROM `fVendas7Tragos`
        WHERE `data` = ?
        GROUP BY `grupos_de_produto`
        ORDER BY total_por_grupo DESC
    ";
    $stmt_grafico = $conn->prepare($sql_grafico);
    $stmt_grafico->bind_param('s', $data_iso);
    $stmt_grafico->execute();
    $result_grafico = $stmt_grafico->get_result();
    $count = 0;
    while ($row = $result_grafico->fetch_assoc()) {
        if ($count < 6) { // Limita aos top 5 grupos
            $dados_grafico_grupo[] = $row;
            $count++;
        } else {
            break;
        }
    }
    $stmt_grafico->close();

    // Calcula médias de venda por grupo para 3, 6 e 12 meses
    $medias_grupo = ['3m'=>[], '6m'=>[], '12m'=>[]];
    foreach ($dados_grafico_grupo as $item) {
        $grupo = $item['grupos_de_produto'];
        foreach ($periodos_meses as $key => $num_meses) {
            $dtClone = new DateTime($data_iso);
            $end_periodo = $dtClone->format('Y-m-d');
            $start_periodo = $dtClone->modify("-{$num_meses} months")->format('Y-m-d');
            $sql_med_gr = "
              SELECT AVG(daily_sum) AS media
                FROM (
                  SELECT SUM(total) AS daily_sum
                    FROM fVendas7Tragos
                   WHERE DAYOFWEEK(`data`) = ?
                     AND `data` >= ?
                     AND `data` < ?
                     AND `grupos_de_produto` = ?
                   GROUP BY `data`
                ) AS tmp_daily_sums
            ";
            $stmt_med_gr = $conn->prepare($sql_med_gr);
            $stmt_med_gr->bind_param('isss', $dia_semana_num, $start_periodo, $end_periodo, $grupo);
            $stmt_med_gr->execute();
            $res_med = $stmt_med_gr->get_result()->fetch_assoc();
            $medias_grupo[$key][] = floatval($res_med['media'] ?? 0);
            $stmt_med_gr->close();
        }
    }

    // prepara a view
    $dia_semana_pt_selecionado   = getDiaSemanaPortugues($dia_semana_num);
} elseif (isset($_GET['ano']) || isset($_GET['mes']) || isset($_GET['dia'])) {
    // If any part of the date is set but not all, or if form submitted with defaults
    if (empty($ano_selecionado) || empty($mes_selecionado) || empty($dia_selecionado)) {
      $erro_filtro = "Por favor, selecione Ano, Mês e Dia para a análise.";
    }
}

// Ensure the connection is not closed prematurely
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

$anos_disponiveis = range(date('Y'), date('Y') - 5);
$meses_disponiveis = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
    '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
    '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];
$dias_disponiveis = range(1, 31);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST['data'] ?? null;

    if ($data) {
        [$ano, $mes, $dia] = explode('-', $data);
        $url = sprintf('%s?ano=%s&mes=%s&dia=%s', $_SERVER['PHP_SELF'], $ano, $mes, $dia);
        header("Location: $url");
        exit;
    }
}

// Ensure the selected date persists in the input field
$selectedDate = sprintf('%04d-%02d-%02d', $ano_selecionado ?? date('Y'), $mes_selecionado ?? date('m'), $dia_selecionado ?? date('d'));

// Corrigir uso de htmlspecialchars e variáveis indefinidas
$bdf_row = $bdf_row ?? [];
$clima_chuva = $clima_chuva ?? 'Dados indisponíveis';
$clima_temperatura = $clima_temperatura ?? 'Dados indisponíveis';
$clima_ceu = $clima_ceu ?? 'Dados indisponíveis';
// Fallback para condição do céu

// Configuração da API WeatherAPI.com
$apiKey = 'bcfcc0aeda5d4e26a69142233250506'; // Substitua pela sua chave WeatherAPI.com
$weatherCity = 'Curitiba';
// Seleciona endpoint historico ou atual conforme data
$endpoint = isset($data_iso) ? 'history.json' : 'current.json';
$dtParam = isset($data_iso) ? '&dt=' . $data_iso : '';
$weatherUrl = "https://api.weatherapi.com/v1/{$endpoint}?key={$apiKey}&q=" . urlencode($weatherCity) . "{$dtParam}&lang=pt";
$response = @file_get_contents($weatherUrl);
if ($response !== false) {
    $weatherData = json_decode($response, true);
    // Obtém temperatura e chuva de histórico ou atual
    if (isset($weatherData['forecast']['forecastday'][0]['day'])) {
        $dayData = $weatherData['forecast']['forecastday'][0]['day'];
        $tempC = $dayData['avgtemp_c'] ?? 0;
        $rainVol = $dayData['totalprecip_mm'] ?? 0;
        $clima_ceu = $dayData['condition']['text'] ?? 'Dados indisponíveis';
    } elseif (isset($weatherData['current'])) {
        $tempC = $weatherData['current']['temp_c'] ?? 0;
        $rainVol = $weatherData['current']['precip_mm'] ?? 0;
        $clima_ceu = $weatherData['current']['condition']['text'] ?? 'Dados indisponíveis';
    } else {
        $tempC = 0;
        $rainVol = 0;
        $clima_ceu = 'Dados indisponíveis';
    }
    $clima_temperatura = number_format($tempC, 2, ',', '.') . ' °C';
    if ($rainVol == 0) {
        $clima_chuva = 'Chuva: Sem chuva';
    } elseif ($rainVol < 2) {
        $clima_chuva = 'Chuva: Pouca chuva';
    } else {
        $clima_chuva = 'Chuva: Muita chuva';
    }
}

// Consolidate fetch and deduplication for problemas e respostas públicas
$respostas_publico = [];
$problemas = [];
$dias_com_problemas = 0;
if ($ano_selecionado) {
    // Fetch public feedback
    $sql_resp = "SELECT DATE(hora_de_inicio) AS data, musica_banda_sempre_digitar_o_nome_dos_mesmo_jeito AS banda, musica_resposta_publico AS resposta FROM DiarioDeBordo7Tragos WHERE YEAR(hora_de_inicio)=? AND musica_resposta_publico<> 'Bom' ORDER BY data";
    $stmt_resp = $conn->prepare($sql_resp);
    $stmt_resp->bind_param('i', $ano_selecionado);
    $stmt_resp->execute();
    $respostas_publico = $stmt_resp->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_resp->close();
    // Deduplicate feedback entries
    $uniqueFeedback = [];
    $seenFeedback = [];
    foreach ($respostas_publico as $r) {
        $key = $r['data'] . '|' . $r['banda'] . '|' . $r['resposta'];
        if (!in_array($key, $seenFeedback, true)) {
            $uniqueFeedback[] = $r;
            $seenFeedback[] = $key;
        }
    }
    $respostas_publico = $uniqueFeedback;

    // Fetch problemas em equipamentos e sistema
    $sql_problemas = "SELECT DATE(hora_de_inicio) AS data, equipamento_com_problema, sistema_com_problema FROM DiarioDeBordo7Tragos WHERE YEAR(hora_de_inicio)=? AND (equipamento_funcionamento!='Funcionando normalmente' OR sistema_funcionamento!='Funcionando normalmente') ORDER BY hora_de_inicio ASC";
    $stmt_prob = $conn->prepare($sql_problemas);
    $stmt_prob->bind_param('i', $ano_selecionado);
    $stmt_prob->execute();
    $rows = $stmt_prob->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_prob->close();
    // Deduplicate problemas by date
    $uniqueProblemas = [];
    $seenDates = [];
    foreach ($rows as $p) {
        if (!in_array($p['data'], $seenDates, true)) {
            $uniqueProblemas[] = $p;
            $seenDates[] = $p['data'];
        }
    }
    $problemas = $uniqueProblemas;
    // Count unique days with problems
    $dias_com_problemas = count($seenDates);

    // Count unique days with feedback marcado como 'Média' ou 'Ruim'
    $datesFeedback = [];
    foreach ($respostas_publico as $r) {
        if (!in_array($r['data'], $datesFeedback, true)) {
            $datesFeedback[] = $r['data'];
        }
    }
    $dias_feedback = count($datesFeedback);
} 

// --- NOVO: Faturamento diário do mês selecionado ---
$faturamento_dias_mes = [];
if ($ano_selecionado && $mes_selecionado) {
    $primeiro_dia = sprintf('%04d-%02d-01', $ano_selecionado, $mes_selecionado);
    $ultimo_dia = date('Y-m-t', strtotime($primeiro_dia));
    $sql_mes = "
        SELECT f.`data`, SUM(f.total) AS faturamento, 
               (SELECT musica_banda_sempre_digitar_o_nome_dos_mesmo_jeito 
                  FROM DiarioDeBordo7Tragos 
                 WHERE DATE(hora_de_inicio) = f.`data` 
                 LIMIT 1) AS banda
        FROM fVendas7Tragos f
        WHERE f.`data` BETWEEN ? AND ?
        GROUP BY f.`data`
        ORDER BY f.`data`
    ";
    $stmt_mes = $conn->prepare($sql_mes);
    $stmt_mes->bind_param('ss', $primeiro_dia, $ultimo_dia);
    $stmt_mes->execute();
    $result_mes = $stmt_mes->get_result();
    while ($row = $result_mes->fetch_assoc()) {
        $faturamento_dias_mes[$row['data']] = [
            'faturamento' => floatval($row['faturamento']),
            'banda' => $row['banda'] ?? '-'
        ];
    }
    $stmt_mes->close();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Diário de bordo - Siete Tragos</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @keyframes spin { to { transform: rotate(360deg); } }
    .loader { border: 4px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; }

    body {
      font-family: Arial, sans-serif;
      background-color: #1a202c;
      color: #f7fafc;
      margin: 0;
      padding: 0;
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 20px;
    }

    .header {
      text-align: center;
      margin-bottom: 20px;
    }

    .card {
      background-color: #2d3748;
      border-radius: 8px;
      padding: 8px; /* Reduzido para compactar os cartões */
      margin-bottom: 20px; /* Aumentado para melhor espaçamento */
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      max-width: 300px; /* Aumentado para melhor visibilidade */
    }

    .card.faturamento {
      max-width: 600px; /* Increased width for faturamento cards */
    }
    .card.medias {
      max-width: 800px; /* Keep width for medias cards */
    }

    .card h2 {
      font-size: 18px;
      color: #ecc94b;
      margin-bottom: 10px;
    }

    .card p {
      font-size: 14px;
      color: #a0aec0;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 15px; /* Aumentado o espaço entre os itens da grade */
    }

    #filtroForm {
      max-width: 300px; /* Ajustado para alinhar com os cartões */
    }
  </style>
</head>
<body class="bg-gray-900 text-gray-100 flex min-h-screen">

  <main class="flex-1 bg-gray-900 p-6 relative">
    <header class="mb-6 sm:mb-8">
      <h1 class="text-xl sm:text-2xl font-bold">
        Bem-vindo, <?= htmlspecialchars($usuario_nome); ?>
      </h1>
      <p class="text-gray-400 text-sm">
        <?php
          $hoje = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
          $fmt = new IntlDateFormatter(
            'pt_BR',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            'America/Sao_Paulo',
            IntlDateFormatter::GREGORIAN
          );
          echo $fmt->format($hoje);
        ?>
      </p>
    </header>

    <h1 class="text-2xl font-bold text-yellow-400 text-center mb-4"> 
      Diário de bordo - SIETE TRAGOS
    </h1>
    <div id="dashboardContent">
    <!-- Filtro e banda lado a lado, filtro horizontal -->
    <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
      <div class="bg-gray-800 rounded-lg shadow-md p-1 flex flex-col justify-center w-full h-28">
        <form method="POST" id="filtroForm" class="flex flex-col w-full">
          <label class="text-xs font-medium text-gray-300 mb-1">Selecione a Data</label>
          <input type="date" name="data" id="inputData" class="w-full bg-gray-700 text-gray-200 px-2 py-1 rounded border border-gray-600 hover:bg-gray-600 focus:outline-none" value="<?= htmlspecialchars($selectedDate) ?>">
          <button type="submit" class="mt-1 bg-yellow-500 text-gray-900 px-2 py-1 rounded hover:bg-yellow-600 focus:outline-none">Filtrar</button>
        </form>
      </div>
      <?php if (isset($bdf_row['musica_banda_sempre_digitar_o_nome_dos_mesmo_jeito']) || isset($bdf_row['musica_resposta_publico'])): ?>
        <div class="card flex flex-col justify-center h-28">
          <h2 class="text-xs font-semibold text-yellow-400 mb-1">Música</h2>
          <div class="text-xs text-gray-300">
            <div><span class="font-semibold">Banda:</span> <?= htmlspecialchars($bdf_row['musica_banda_sempre_digitar_o_nome_dos_mesmo_jeito'] ?? '-') ?></div>
            <div><span class="font-semibold">Resposta do Público:</span> <?= htmlspecialchars($bdf_row['musica_resposta_publico'] ?? '-') ?></div>
          </div>
        </div>
      <?php endif; ?>
      <?php if (isset($bdf_row['cardapio_disponibilidade_chope']) || isset($bdf_row['cardapio_temperatura_chope'])): ?>
        <div class="card flex flex-col justify-center h-28">
          <h2 class="text-xs font-semibold text-yellow-400 mb-1">Chope</h2>
          <div class="text-xs text-gray-300">
            <div><span class="font-semibold">Disponibilidade:</span> <?= htmlspecialchars($bdf_row['cardapio_disponibilidade_chope'] ?? '-') ?></div>
            <div><span class="font-semibold">Temperatura:</span> <?= htmlspecialchars($bdf_row['cardapio_temperatura_chope'] ?? '-') ?></div>
          </div>
        </div>
      <?php endif; ?>
      <?php if (isset($bdf_row['cardapio_comida']) || isset($bdf_row['cardapio_disponibilidade_comida'])): ?>
        <div class="card flex flex-col justify-center h-28">
          <h2 class="text-xs font-semibold text-yellow-400 mb-1">Comida</h2>
          <div class="text-xs text-gray-300">
            <div><span class="font-semibold">Menu:</span> <?= htmlspecialchars($bdf_row['cardapio_comida'] ?? '-') ?></div>
            <div><span class="font-semibold">Disponibilidade:</span> <?= htmlspecialchars($bdf_row['cardapio_disponibilidade_comida'] ?? '-') ?></div>
          </div>
        </div>
      <?php endif; ?>
      <?php if (isset($bdf_row['cardapio_disponibilidade_drink'])): ?>
        <div class="card flex flex-col justify-center h-28">
          <h2 class="text-xs font-semibold text-yellow-400 mb-1">Drink</h2>
          <div class="text-xs text-gray-300">
            <div><span class="font-semibold">Disponibilidade:</span> <?= htmlspecialchars($bdf_row['cardapio_disponibilidade_drink'] ?? '-') ?></div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($erro_filtro): ?>
        <div class="mb-4 p-3 bg-red-700 text-white text-center rounded font-semibold text-xs shadow"> 
            <?= htmlspecialchars($erro_filtro) ?>
        </div>
    <?php endif; ?>

    <?php if ($data_selecionada_formatada && !$erro_filtro) { ?>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="card">
            <h2 class="card-title">Faturamento do Dia</h2>
            <p class="card-value">R$ <?= number_format($faturamento_dia ?? 0, 2, ',', '.') ?></p>
            <div class="mt-0.5">
                <span class="text-[10px] text-gray-400">Dia: </span>
                <span class="text-xs font-bold text-gray-100">
                    <?= htmlspecialchars($dia_semana_pt_selecionado) ?>
                </span>
            </div>
            <p class="card-subtitle mt-1">
                Data: <?= htmlspecialchars($data_selecionada_formatada) ?>
            </p>
        </div>
        <?php 
            $label_map = ['3m' => '3 Meses', '6m' => '6 Meses', '12m' => '12 Meses', '24m' => '24 Meses'];
            $periodos_para_cards = ['3m', '6m', '12m', '24m'];
            foreach ($periodos_para_cards as $periodo_key) {
                if (isset($comparativo_medias[$periodo_key])) {
                    $dados_media = $comparativo_medias[$periodo_key];
                    $artigo_ultimos = (in_array($dia_semana_pt_selecionado, ['Sábado', 'Domingo'])) ? 'últimos' : 'últimas'; ?>
                    <div class="card">
                        <h2 class="card-title">Média (<?= htmlspecialchars($label_map[$periodo_key]) ?>)</h2>
                        <p class="card-value">R$ <?= number_format($dados_media['valor'] ?? 0, 2, ',', '.') ?></p>
                        <div class="mt-0.5">
                            <span class="text-[10px] text-gray-400">vs Dia: </span>
                            <span class="text-xs font-bold <?= htmlspecialchars($dados_media['comparativo_cor_classe']) ?>">
                                <?= htmlspecialchars($dados_media['comparativo_texto']) ?>
                            </span>
                        </div>
                        <p class="card-subtitle mt-1">
                            Ref. <?= $artigo_ultimos ?> <?= htmlspecialchars($dia_semana_pt_selecionado) ?>s<br>
                            Anteriores a <?= htmlspecialchars($data_selecionada_formatada) ?>
                        </p>
                    </div>
        <?php       }
            }
        ?>
    </div>
<?php } ?>


<?php if (isset($bdf_row) && $bdf_row): ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
        <div class="card flex flex-col text-left p-3">
            <h2 class="text-xs font-semibold text-yellow-400 mb-1 text-left" style="font-size:0.95rem; letter-spacing:0.01em;">Equipe</h2>
            <table class="w-full text-xs text-gray-200 text-left align-top mt-0">
                <?php
                $equipe_labels = [
                    'Bar' => $bdf_row['equipe_bar'] ?? '-',
                    'Salão' => $bdf_row['equipe_salao'] ?? '-',
                    'Cozinha' => $bdf_row['equipe_cozinha'] ?? '-',
                ];
                foreach ($equipe_labels as $label => $valor) {
                    $valor_trim = trim((string)$valor);
                    $cor = 'text-gray-100';
                    if (strcasecmp($valor_trim, 'Faltando') === 0) {
                        $cor = 'text-red-400 font-bold';
                    } elseif (strcasecmp($valor_trim, 'Normal') === 0 || strcasecmp($valor_trim, 'Sobrando') === 0) {
                        $cor = 'text-green-400 font-bold';
                    }
                ?>
                <tr>
                    <td class="py-0.5 pr-2 text-left text-gray-400 align-top"><?php echo $label; ?>:</td>
                    <td class="py-0.5 pl-4 text-left font-semibold align-top <?php echo $cor; ?>"><?php echo nl2br(htmlspecialchars($valor_trim)); ?></td>
                </tr>
                <?php } ?>
            </table>
        </div>
        <div class="card flex flex-col text-left p-3">
            <h2 class="text-xs font-semibold text-yellow-400 mb-1 text-left" style="font-size:0.95rem; letter-spacing:0.01em;">Clima</h2>
            <table class="w-full text-xs text-gray-200 text-left align-top mt-0">
                <tr>
                    <td class="py-0.5 pr-2 text-left text-gray-400 align-top">Chuva:</td>
                    <td class="py-0.5 pl-2 text-left font-semibold text-gray-100 align-top"><?php echo htmlspecialchars($clima_chuva); ?></td>
                </tr>
                <tr>
                    <td class="py-0.5 pr-2 text-left text-gray-400 align-top">Temperatura:</td>
                    <td class="py-0.5 pl-2 text-left font-semibold text-gray-100 align-top"><?php echo htmlspecialchars($clima_temperatura); ?></td>
                </tr>
                <tr>
                    <td class="py-0.5 pr-2 text-left text-gray-400 align-top">Condição do Céu:</td>
                    <td class="py-0.5 pl-2 text-left font-semibold text-gray-100 align-top"><?php echo htmlspecialchars($clima_ceu); ?></td>
                </tr>
            </table>
        </div>
        <div class="card flex flex-col text-left p-3">
            <h2 class="text-xs font-semibold text-yellow-400 mb-1 text-left" style="font-size:0.95rem; letter-spacing:0.01em;">Equipamentos</h2>
            <table class="w-full text-xs text-gray-200 text-left align-top mt-0">
                <?php
                $funcionamento = isset($bdf_row['equipamento_funcionamento']) ? trim((string)$bdf_row['equipamento_funcionamento']) : '-';
                $cor_func = 'text-gray-100';
                if (stripos($funcionamento, 'parcial') !== false || stripos($funcionamento, 'problema') !== false) {
                    $cor_func = 'text-red-400 font-bold';
                } elseif (stripos($funcionamento, 'normal') !== false) {
                    $cor_func = 'text-green-400 font-bold';
                }
                ?>
                <tr>
                    <td class="py-0.5 pr-2 text-left text-gray-400 align-top">Funcionamento:</td>
                    <td class="py-0.5 pl-2 text-left font-semibold align-top <?php echo $cor_func; ?>"><?php echo nl2br(htmlspecialchars($funcionamento)); ?></td>
                </tr>
                <?php if (!empty($bdf_row['equipamento_com_problema'])): ?>
                <tr>
                    <td class="py-0.5 pr-2 text-left text-gray-400 align-top">Problemas:</td>
                    <td class="py-0.5 pl-2 text-left font-semibold text-red-300 align-top"><?php echo nl2br(htmlspecialchars((string)$bdf_row['equipamento_com_problema'])); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <div class="card flex flex-col text-left p-3">
            <h2 class="text-xs font-semibold text-yellow-400 mb-1 text-left" style="font-size:0.95rem; letter-spacing:0.01em;">Sistema</h2>
            <table class="w-full text-xs text-gray-200 text-left align-top mt-0">
                <?php
                $sistema_funcionamento = isset($bdf_row['sistema_funcionamento']) ? trim((string)$bdf_row['sistema_funcionamento']) : '-';
                $cor_sistema = 'text-gray-100';
                if (stripos($sistema_funcionamento, 'parcial') !== false || stripos($sistema_funcionamento, 'problema') !== false) {
                    $cor_sistema = 'text-red-400 font-bold';
                } elseif (stripos($sistema_funcionamento, 'normal') !== false) {
                    $cor_sistema = 'text-green-400 font-bold';
                }
                ?>
                <tr>
                    <td class="py-0.5 pr-2 text-left text-gray-400 align-top">Funcionamento:</td>
                    <td class="py-0.5 pl-2 text-left font-semibold align-top <?php echo $cor_sistema; ?>"><?php echo nl2br(htmlspecialchars($sistema_funcionamento)); ?></td>
                </tr>
                <?php if (!empty($bdf_row['sistema_com_problema'])): ?>
                <tr>
                    <td class="py-0.5 pr-2 text-left text-gray-400 align-top">Problemas:</td>
                    <td class="py-0.5 pl-2 text-left font-semibold text-red-300 align-top"><?php echo nl2br(htmlspecialchars((string)$bdf_row['sistema_com_problema'])); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <div class="card flex flex-col text-left p-3">
            <h2 class="text-xs font-semibold text-yellow-400 mb-1 text-left" style="font-size:0.95rem; letter-spacing:0.01em;">Observações & Evento na Cidade</h2>
            <div class="w-full text-xs text-gray-200 text-left align-top mt-0 overflow-y-auto" style="max-height:80px;">
                <?php
                $observacoes = isset($bdf_row['observacoes']) ? trim((string)$bdf_row['observacoes']) : '';
                $evento = isset($bdf_row['algum_evento_relevante_na_cidade']) ? trim((string)$bdf_row['algum_evento_relevante_na_cidade']) : '';
                if ($observacoes !== '' && $evento !== '') {
                    echo '<b>Observações:</b> ' . nl2br(htmlspecialchars($observacoes)) . '<br><b>Evento na Cidade:</b> ' . nl2br(htmlspecialchars($evento));
                } elseif ($observacoes !== '') {
                    echo '<b>Observações:</b> ' . nl2br(htmlspecialchars($observacoes));
                } elseif ($evento !== '') {
                    echo '<b>Evento na Cidade:</b> ' . nl2br(htmlspecialchars($evento));
                } else {
                    echo '-';
                }
                ?>
            </div>
        </div>
    </div>
<?php endif; ?>
    <?php if (!empty($dados_grafico_grupo) && !$erro_filtro): ?>
    <div class="mt-2 bg-gray-800 rounded-lg shadow-md p-4 flex flex-col justify-between h-[380px] w-full min-h-0">
      <h2 class="text-lg font-semibold text-yellow-400 mb-4 text-center">
        Vendas por Grupo de Produto (<?= htmlspecialchars($data_selecionada_formatada) ?>)
      </h2>
      <div class="flex-1 flex items-center justify-center min-h-0">
        <canvas id="graficoVendasGrupo" class="w-full h-full"></canvas>
      </div>
    </div>
    <script>
      // Dados PHP para médias por grupo
      const medias3m = <?= json_encode(array_map(fn($v) => round($v, 2), $medias_grupo['3m'])) ?>;
      const medias6m = <?= json_encode(array_map(fn($v) => round($v, 2), $medias_grupo['6m'])) ?>;
      const medias12m = <?= json_encode(array_map(fn($v) => round($v, 2), $medias_grupo['12m'])) ?>;
      const dadosGraficoPHP = <?= json_encode($dados_grafico_grupo) ?>;
      const labelsGrafico = dadosGraficoPHP.map(item => item.grupos_de_produto);
      const valoresGrafico = dadosGraficoPHP.map(item => item.total_por_grupo);

      const ctx = document.getElementById('graficoVendasGrupo').getContext('2d');
      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: labelsGrafico,
          datasets: [
            { label: 'Média 12m (R$)', data: medias12m, backgroundColor: 'rgba(37, 99, 235, 0.8)' },
            { label: 'Média 6m (R$)', data: medias6m, backgroundColor: 'rgba(59, 130, 246, 0.6)' },
            { label: 'Média 3m (R$)', data: medias3m, backgroundColor: 'rgba(191, 219, 254, 0.4)' },
            { label: 'Total Vendido (R$)', data: valoresGrafico, backgroundColor: 'rgba(250, 204, 21, 0.7)', borderColor: 'rgba(250, 204, 21, 1)', borderWidth: 1 }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: { beginAtZero: true }
          }
        }
      });
    </script>
    <?php endif; ?>

    <!-- Gráfico de faturamento diário do mês -->
<?php if (!empty($faturamento_dias_mes) && !$erro_filtro): ?>
<?php
  $faturamento_total_mes = array_sum(array_column($faturamento_dias_mes, 'faturamento'));
?>
<div class="mt-8 bg-gray-800 rounded-lg shadow-md p-4 flex flex-col items-stretch min-h-0 w-full">
  <div class="text-center mb-1 text-yellow-300 text-sm font-semibold">
    Faturamento Total do Mês: 
    <span class="text-gray-100 font-bold">
      R$ <?= number_format($faturamento_total_mes, 2, ',', '.') ?>
    </span>
    <span class="text-xs text-gray-400 ml-2">
      (<?= htmlspecialchars($meses_disponiveis[$mes_selecionado] ?? $mes_selecionado) ?>/<?= htmlspecialchars($ano_selecionado) ?>)
    </span>
  </div>
  <div class="flex-1 flex items-center justify-center min-h-0" style="height:160px;">
    <canvas id="graficoFaturamentoMes" class="w-full h-full"></canvas>
  </div>
</div>
<script>
  const dadosFaturamentoMes = <?= json_encode($faturamento_dias_mes) ?>;
  const diasSemana = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
  const labelsDiasMes = Object.keys(dadosFaturamentoMes).map(dt => {
    const d = new Date(dt);
    return d.getDate().toString().padStart(2, '0') + '/' + (d.getMonth()+1).toString().padStart(2, '0');
  });
  const valoresDiasMes = Object.values(dadosFaturamentoMes).map(obj => obj.faturamento);
  const bandasDiasMes = Object.values(dadosFaturamentoMes).map(obj => obj.banda);
  const diasSemanaMes = Object.keys(dadosFaturamentoMes).map(dt => {
    const d = new Date(dt + 'T00:00:00');
    return diasSemana[d.getDay()];
  });

  const ctxMes = document.getElementById('graficoFaturamentoMes').getContext('2d');
  new Chart(ctxMes, {
    type: 'bar',
    data: {
      labels: labelsDiasMes,
      datasets: [{
        label: 'Faturamento (R$)',
        data: valoresDiasMes,
        backgroundColor: 'rgba(250, 204, 21, 0.7)',
        borderColor: 'rgba(250, 204, 21, 1)',
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        tooltip: {
          callbacks: {
            title: function(context) {
              const idx = context[0].dataIndex;
              return labelsDiasMes[idx] + ' (' + diasSemanaMes[idx] + ')';
            },
            label: function(context) {
              const idx = context.dataIndex;
              const fat = valoresDiasMes[idx].toLocaleString('pt-BR', {style:'currency',currency:'BRL'});
              const banda = bandasDiasMes[idx] ? bandasDiasMes[idx] : '-';
              return [
                'Faturamento: ' + fat,
                'Banda: ' + banda
              ];
            }
          }
        }
      },
      scales: {
        y: { beginAtZero: true }
      }
    }
  });
</script>
<?php endif; ?>

    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-gray-800 rounded-lg shadow-md p-4">
            <h2 class="text-xs font-semibold text-yellow-400 mb-1">Dias com Problemas</h2>
            <p class="text-2xl font-bold text-gray-100"><?= htmlspecialchars($dias_com_problemas) ?></p>
        </div>
        <div class="bg-gray-800 rounded-lg shadow-md p-4">
            <h2 class="text-xs font-semibold text-yellow-400 mb-1">Dias com Feedback Médio ou Ruim</h2>
            <p class="text-2xl font-bold text-gray-100"><?= htmlspecialchars($dias_feedback) ?></p>
        </div>
    </div>

    <?php if (!empty($problemas)): ?>
    <div class="mt-4 bg-gray-800 rounded-lg shadow-md p-4">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-yellow-400">Problemas Identificados em <?= htmlspecialchars($ano_selecionado) ?></h2>
            <button id="toggleProblemas" class="bg-yellow-500 text-gray-900 px-3 py-2 rounded hover:bg-yellow-600 focus:outline-none">+ Mostrar Problemas</button>
        </div>
        <div id="problemasTable" class="hidden overflow-auto" style="max-height:200px;">
            <table class="w-full text-xs text-gray-200 text-left">
                <thead><tr class="bg-gray-700"><th>Data</th><th>Equipamento</th><th>Sistema</th></tr></thead>
                <tbody><?php foreach($problemas as $p): ?><tr><td><?= htmlspecialchars($p['data']) ?></td><td><?= htmlspecialchars($p['equipamento_com_problema'] ?? '-') ?></td><td><?= htmlspecialchars($p['sistema_com_problema'] ?? '-') ?></td></tr><?php endforeach; ?></td>
            </table>
        </div>
    </div>
    <script>document.addEventListener('DOMContentLoaded',()=>{const btn=document.getElementById('toggleProblemas'),tbl=document.getElementById('problemasTable');btn.onclick=()=>{tbl.classList.toggle('hidden');btn.textContent=tbl.classList.contains('hidden')?'+ Mostrar Problemas':'- Ocultar Problemas';};});</script>
    <?php endif; ?>

    <?php if (!empty($respostas_publico)): ?>
    <div class="mt-4 bg-gray-800 rounded-lg shadow-md p-4">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-yellow-400">Feedback Público - Médias e Ruins <?= htmlspecialchars($ano_selecionado) ?></h2>
            <button id="toggleFeedback" class="bg-yellow-500 text-gray-900 px-3 py-2 rounded hover:bg-yellow-600 focus:outline-none">+ Mostrar Feedback</button>
        </div>
        <div id="feedbackTable" class="hidden overflow-auto" style="max-height:200px;">
            <table class="w-full text-xs text-gray-200 text-left">
                <thead><tr class="bg-gray-700"><th>Data</th><th>Banda</th><th>Resposta</th></tr></thead>
                <tbody><?php foreach($respostas_publico as $r): ?><tr><td><?= htmlspecialchars($r['data']) ?></td><td><?= htmlspecialchars($r['banda'] ?? '-') ?></td><td><?= htmlspecialchars($r['resposta'] ?? '-') ?></td></tr><?php endforeach; ?></td>
            </table>
        </div>
    </div>
    <script>document.addEventListener('DOMContentLoaded',()=>{const btn=document.getElementById('toggleFeedback'),tbl=document.getElementById('feedbackTable');btn.onclick=()=>{tbl.classList.toggle('hidden');btn.textContent=tbl.classList.contains('hidden')?'+ Mostrar Feedback':'- Ocultar Feedback';};});</script>
    <?php endif; ?>
  </div> <!-- end #dashboardContent -->
  </main>
</body>
</html>