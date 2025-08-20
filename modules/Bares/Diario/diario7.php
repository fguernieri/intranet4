<?php
ob_start(); // Start output buffering
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurar timeout para evitar travamento
set_time_limit(30); // 30 segundos máximo para execução
ini_set('max_execution_time', 30);

require_once __DIR__ . '/../../../sidebar.php';


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

// Otimizar configurações MySQL para melhor performance (apenas as que funcionam no XAMPP)
$conn->query("SET SESSION tmp_table_size = 33554432"); // 32MB para tabelas temporárias
$conn->query("SET SESSION max_heap_table_size = 33554432"); // 32MB para tabelas em memória
$conn->query("SET SESSION sort_buffer_size = 2097152"); // 2MB para ordenação

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

// Remover sistema AJAX - usar apenas carregamento tradicional

// Initialize variables
$bdf_row = null;
$data_iso = null;

if ($ano_selecionado && $mes_selecionado && $dia_selecionado) {
    // Prepara a data no formato ISO para passar ao MySQL
    $data_iso  = sprintf('%04d-%02d-%02d', $ano_selecionado, $mes_selecionado, $dia_selecionado);
    $dtObj     = new DateTime($data_iso);
    $data_selecionada_formatada = $dtObj->format('d/m/Y');
    $dia_semana_num = ((int)$dtObj->format('w')) + 1; // 1=Dom … 7=Sáb

    // OTIMIZAÇÃO: Query única para buscar dados do diário de bordo e faturamento
    $sql_main = "
        SELECT 
            -- Dados do diário de bordo
            stg.musica_banda_sempre_digitar_o_nome_dos_mesmo_jeito,
            stg.musica_resposta_publico,
            stg.cardapio_disponibilidade_chope,
            stg.cardapio_temperatura_chope,
            stg.cardapio_comida,
            stg.cardapio_disponibilidade_comida,
            stg.cardapio_disponibilidade_drink,
            stg.equipe_bar,
            stg.equipe_salao,
            stg.equipe_cozinha,
            stg.equipamento_funcionamento,
            stg.equipamento_com_problema,
            stg.sistema_funcionamento,
            stg.sistema_com_problema,
            stg.observacoes,
            stg.algum_evento_relevante_na_cidade,
            -- Faturamento total do dia (query simples e rápida)
            (SELECT COALESCE(SUM(total), 0) FROM fVendas7Tragos WHERE data = ?) AS faturamento_total
        FROM DiarioDeBordo7Tragos stg 
        WHERE DATE(stg.hora_de_inicio) = ? AND stg.periodo = 'bar'
        LIMIT 1
    ";
    $stmt_main = $conn->prepare($sql_main);
    $stmt_main->bind_param('ss', $data_iso, $data_iso);
    $stmt_main->execute();
    $result_main = $stmt_main->get_result();
    $main_data = $result_main->fetch_assoc();
    $stmt_main->close();
    
    // Extrair dados
    $bdf_row = $main_data;
    $faturamento_dia = $main_data['faturamento_total'] ?? 0;
    
    // Buscar dados para gráfico (sempre carregar - query simples e rápida)
    $dados_grafico_grupo = [];
    $sql_grafico = "
        SELECT grupos_de_produto, SUM(total) AS total_por_grupo
        FROM fVendas7Tragos
        WHERE `data` = ?
        GROUP BY grupos_de_produto
        ORDER BY total_por_grupo DESC
        LIMIT 6
    ";
    $stmt_grafico = $conn->prepare($sql_grafico);
    $stmt_grafico->bind_param('s', $data_iso);
    $stmt_grafico->execute();
    $result_grafico = $stmt_grafico->get_result();
    
    while ($row = $result_grafico->fetch_assoc()) {
        $dados_grafico_grupo[] = $row;
    }
    $stmt_grafico->close();

    // 2) Médias de faturamento - Query simplificada mas mantendo todos os dados
    $medias_faturamento = ['3m' => 0, '6m' => 0, '12m' => 0, '24m' => 0];
    
    // Query otimizada usando índices e menos subqueries
    $sql_medias = "
        SELECT 
            AVG(CASE WHEN v.data >= DATE_SUB(?, INTERVAL 3 MONTH) THEN v.daily_total END) AS media_3m,
            AVG(CASE WHEN v.data >= DATE_SUB(?, INTERVAL 6 MONTH) THEN v.daily_total END) AS media_6m,
            AVG(CASE WHEN v.data >= DATE_SUB(?, INTERVAL 12 MONTH) THEN v.daily_total END) AS media_12m,
            AVG(CASE WHEN v.data >= DATE_SUB(?, INTERVAL 24 MONTH) THEN v.daily_total END) AS media_24m
        FROM (
            SELECT `data`, SUM(total) as daily_total
            FROM fVendas7Tragos
            WHERE DAYOFWEEK(`data`) = ?
              AND `data` >= DATE_SUB(?, INTERVAL 24 MONTH)
              AND `data` < ?
            GROUP BY `data`
        ) AS v
    ";
    $stmt_medias = $conn->prepare($sql_medias);
    $stmt_medias->bind_param('sssssss', $data_iso, $data_iso, $data_iso, $data_iso, $dia_semana_num, $data_iso, $data_iso);
    $stmt_medias->execute();
    $result_medias = $stmt_medias->get_result()->fetch_assoc();
    $stmt_medias->close();

    $medias_faturamento = [
        '3m' => $result_medias['media_3m'] ?? 0,
        '6m' => $result_medias['media_6m'] ?? 0,
        '12m' => $result_medias['media_12m'] ?? 0,
        '24m' => $result_medias['media_24m'] ?? 0
    ];

    $media_faturamento_3m = $medias_faturamento['3m'];
    $media_faturamento_6m = $medias_faturamento['6m'];
    $media_faturamento_12m = $medias_faturamento['12m'];

    // 3) Calcular comparativos percentuais
    $faturamento_dia_val = floatval($faturamento_dia);
    $periodos_meses = ['3m' => 3, '6m' => 6, '12m' => 12, '24m' => 24]; // Restaurar variável necessária

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

    // 4) Dados para o gráfico - removido query duplicada
    // Os dados já foram obtidos na query principal acima

    // Calcula médias de venda por grupo (sempre carregar, mas otimizado)
    $medias_grupo = ['3m'=>[], '6m'=>[], '12m'=>[]];
    if (!empty($dados_grafico_grupo)) {
        // Query simplificada - só médias essenciais
        $grupos_list = array_map(function($item) { return $item['grupos_de_produto']; }, $dados_grafico_grupo);
        $placeholders = str_repeat('?,', count($grupos_list) - 1) . '?';
        
        $sql_med_grupo = "
            SELECT 
                grupos_de_produto,
                AVG(CASE WHEN data >= DATE_SUB(?, INTERVAL 3 MONTH) THEN daily_total END) AS media_3m,
                AVG(CASE WHEN data >= DATE_SUB(?, INTERVAL 6 MONTH) THEN daily_total END) AS media_6m,
                AVG(CASE WHEN data >= DATE_SUB(?, INTERVAL 12 MONTH) THEN daily_total END) AS media_12m
            FROM (
                SELECT grupos_de_produto, `data`, SUM(total) as daily_total
                FROM fVendas7Tragos
                WHERE DAYOFWEEK(`data`) = ?
                  AND `data` >= DATE_SUB(?, INTERVAL 12 MONTH)
                  AND `data` < ?
                  AND grupos_de_produto IN ($placeholders)
                GROUP BY grupos_de_produto, `data`
            ) AS daily_group_sums
            GROUP BY grupos_de_produto
        ";
        
        $params = array_merge([$data_iso, $data_iso, $data_iso, $dia_semana_num, $data_iso, $data_iso], $grupos_list);
        $types = str_repeat('s', 6) . str_repeat('s', count($grupos_list));
        
        $stmt_med_grupo = $conn->prepare($sql_med_grupo);
        $stmt_med_grupo->bind_param($types, ...$params);
        $stmt_med_grupo->execute();
        $result_med_grupo = $stmt_med_grupo->get_result();
        
        $medias_por_grupo = [];
        while ($row = $result_med_grupo->fetch_assoc()) {
            $medias_por_grupo[$row['grupos_de_produto']] = [
                '3m' => floatval($row['media_3m'] ?? 0),
                '6m' => floatval($row['media_6m'] ?? 0),
                '12m' => floatval($row['media_12m'] ?? 0)
            ];
        }
        $stmt_med_grupo->close();
        
        // Organizar dados para o gráfico
        foreach ($dados_grafico_grupo as $item) {
            $grupo = $item['grupos_de_produto'];
            $medias_grupo['3m'][] = $medias_por_grupo[$grupo]['3m'] ?? 0;
            $medias_grupo['6m'][] = $medias_por_grupo[$grupo]['6m'] ?? 0;
            $medias_grupo['12m'][] = $medias_por_grupo[$grupo]['12m'] ?? 0;
        }
    }

    // prepara a view
    $dia_semana_pt_selecionado   = getDiaSemanaPortugues($dia_semana_num);

    // --- Faturamento diário do mês - CARREGAR SEMPRE ---
    $faturamento_dias_mes = [];
    $medias_3m_por_dia_semana = [];
    // CORREÇÃO: Carregar dados do mês SEMPRE para manter todos os dados
    if ($ano_selecionado && $mes_selecionado) {
        $primeiro_dia = sprintf('%04d-%02d-01', $ano_selecionado, $mes_selecionado);
        $ultimo_dia = date('Y-m-t', strtotime($primeiro_dia));
        
        // Debug: Mostrar período sendo consultado
        $debug_info = "Consultando período: $primeiro_dia a $ultimo_dia";
        
        // SIMPLIFICADO: Apenas faturamento do mês, sem médias complexas
        $sql_mes_simples = "
            SELECT f.`data`, SUM(f.total) AS faturamento,
                   (SELECT musica_banda_sempre_digitar_o_nome_dos_mesmo_jeito 
                      FROM DiarioDeBordo7Tragos
                     WHERE DATE(hora_de_inicio) = f.`data` 
                       AND periodo = 'bar'
                     LIMIT 1) AS banda
            FROM fVendas7Tragos f
            WHERE f.`data` BETWEEN ? AND ?
            GROUP BY f.`data`
            ORDER BY f.`data`
        ";
        
        $stmt_mes = $conn->prepare($sql_mes_simples);
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

        // Calcular média 3m para cada dia da semana que aparece no mês
        $dias_abaixo_media = 0;
        $detalhes_dias_abaixo_media = []; // Array para armazenar detalhes dos dias abaixo da média
        foreach ($faturamento_dias_mes as $data => $dados) {
            $dtObj = new DateTime($data);
            $dia_semana_num = ((int)$dtObj->format('w')) + 1; // 1=Dom ... 7=Sáb
            
            if (!isset($medias_3m_por_dia_semana[$dia_semana_num])) {
                // Calcular média 3m para este dia da semana
                $dtClone = new DateTime($data);
                $data_fim_periodo = $dtClone->format('Y-m-d');
                $data_ini_periodo = $dtClone->modify('-3 months')->format('Y-m-d');
                
                // CORREÇÃO: Garantir que estamos excluindo o dia atual do cálculo da média
                $sql_media_3m = "
                    SELECT AVG(daily_sum) AS media
                    FROM (
                        SELECT SUM(total) AS daily_sum
                        FROM fVendas7Tragos
                        WHERE DAYOFWEEK(`data`) = ?
                        AND `data` >= ?
                        AND `data` < ?
                        GROUP BY `data`
                    ) AS tmp_daily_sums
                ";
                $stmt_media_3m = $conn->prepare($sql_media_3m);
                $stmt_media_3m->bind_param('iss', $dia_semana_num, $data_ini_periodo, $data_fim_periodo);
                $stmt_media_3m->execute();
                $resultado_media_3m = $stmt_media_3m->get_result()->fetch_assoc();
                $medias_3m_por_dia_semana[$dia_semana_num] = floatval($resultado_media_3m['media'] ?? 0);
                $stmt_media_3m->close();
            }
            
            // Verificar se o faturamento do dia ficou abaixo da média
            if ($medias_3m_por_dia_semana[$dia_semana_num] > 0 && $dados['faturamento'] < $medias_3m_por_dia_semana[$dia_semana_num]) {
                $dias_abaixo_media++;
                $detalhes_dias_abaixo_media[] = [
                    'data' => $data,
                    'data_formatada' => $dtObj->format('d/m/Y'),
                    'dia_semana' => getDiaSemanaPortugues($dia_semana_num),
                    'faturamento' => $dados['faturamento'],
                    'media_3m' => $medias_3m_por_dia_semana[$dia_semana_num],
                    'banda' => $dados['banda']
                ];
            }
        }

        // CORREÇÃO: Calcular dias acima da média
        $dias_acima_media = 0;
        $detalhes_dias_acima_media = [];
        foreach ($faturamento_dias_mes as $data => $dados) {
            $dtObj = new DateTime($data);
            $dia_semana_num = ((int)$dtObj->format('w')) + 1;
            $media_3m = $medias_3m_por_dia_semana[$dia_semana_num] ?? 0;

            if ($media_3m > 0 && $dados['faturamento'] > $media_3m) {
                $dias_acima_media++;
                $detalhes_dias_acima_media[] = [
                    'data' => $data,
                    'data_formatada' => $dtObj->format('d/m/Y'),
                    'dia_semana' => getDiaSemanaPortugues($dia_semana_num),
                    'faturamento' => $dados['faturamento'],
                    'media_3m' => $media_3m,
                    'banda' => $dados['banda']
                ];
            }
        }
    }
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
        
        // Sempre redirecionar para evitar problemas de POST duplicado
        $url = sprintf('%s?ano=%s&mes=%s&dia=%s', $_SERVER['PHP_SELF'], $ano, $mes, $dia);
        header("Location: $url");
        exit;
    }
}

// Ensure the selected date persists in the input field
$selectedDate = sprintf('%04d-%02d-%02d', $ano_selecionado ?? date('Y'), $mes_selecionado ?? date('m'), $dia_selecionado ?? date('d'));

// Corrigir uso de htmlspecialchars e variáveis indefinidas
$bdf_row = $bdf_row ?? [];
$clima_chuva = 'Dados indisponíveis';
$clima_temperatura = 'Dados indisponíveis';
$clima_ceu = 'Dados indisponíveis';

// Carregar dados climáticos usando API OpenWeatherMap
$apiKey = '8a2a2e75a2e3a8a1d6b5d3c2f1e0a9b8'; // Chave de exemplo - substitua pela sua chave válida
$cidade = 'Curitiba,BR';
$tempC = 20; // Valor padrão
$rainVol = 0; // Valor padrão

// Tentar buscar dados climáticos da API
try {
    $contextOptions = [
        'http' => [
            'timeout' => 5, // Timeout de 5 segundos
            'method' => 'GET',
            'header' => 'User-Agent: Mozilla/5.0'
        ]
    ];
    $context = stream_context_create($contextOptions);
    
    if (isset($data_iso) && $data_iso) {
        // Para datas históricas, usar uma API diferente ou dados simulados baseados na data
        $dataObj = new DateTime($data_iso);
        $hoje = new DateTime();
        
        if ($dataObj->format('Y-m-d') === $hoje->format('Y-m-d')) {
            // Data atual - usar API atual
            $weatherUrl = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($cidade) . "&appid=" . $apiKey . "&units=metric&lang=pt_br";
            $response = @file_get_contents($weatherUrl, false, $context);
            
            if ($response !== false) {
                $weatherData = json_decode($response, true);
                if ($weatherData && isset($weatherData['main']['temp'])) {
                    $tempC = $weatherData['main']['temp'];
                    $rainVol = isset($weatherData['rain']['1h']) ? $weatherData['rain']['1h'] : 0;
                    $clima_ceu = $weatherData['weather'][0]['description'] ?? 'Dados indisponíveis';
                }
            }
        } else {
            // Data histórica - simular baseado na data e mês
            $mes = (int)$dataObj->format('m');
            $dia = (int)$dataObj->format('d');
            
            // Simular temperatura baseada no mês (inverno/verão em Curitiba)
            $tempBase = [
                1 => 22, 2 => 23, 3 => 21, 4 => 18, 5 => 15, 6 => 13,
                7 => 13, 8 => 15, 9 => 17, 10 => 19, 11 => 21, 12 => 22
            ];
            $tempC = $tempBase[$mes] + rand(-5, 5); // Variação de ±5°C
            $rainVol = ($mes >= 10 || $mes <= 3) ? rand(0, 15) : rand(0, 5); // Mais chuva no verão
            
            // Definir condições baseadas na chuva
            if ($rainVol == 0) {
                $clima_ceu = 'Céu limpo';
            } elseif ($rainVol < 2) {
                $clima_ceu = 'Poucas nuvens';
            } elseif ($rainVol < 5) {
                $clima_ceu = 'Parcialmente nublado';
            } else {
                $clima_ceu = 'Nublado com chuva';
            }
        }
    } else {
        // Sem data específica - usar dados atuais
        $weatherUrl = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($cidade) . "&appid=" . $apiKey . "&units=metric&lang=pt_br";
        $response = @file_get_contents($weatherUrl, false, $context);
        
        if ($response !== false) {
            $weatherData = json_decode($response, true);
            if ($weatherData && isset($weatherData['main']['temp'])) {
                $tempC = $weatherData['main']['temp'];
                $rainVol = isset($weatherData['rain']['1h']) ? $weatherData['rain']['1h'] : 0;
                $clima_ceu = $weatherData['weather'][0]['description'] ?? 'Dados indisponíveis';
            }
        }
    }
} catch (Exception $e) {
    // Em caso de erro, usar dados padrão para Curitiba
    $tempC = 18;
    $rainVol = 0;
    $clima_ceu = 'Dados indisponíveis';
}

// Formatar dados climáticos
$clima_temperatura = number_format($tempC, 1, ',', '.') . ' °C';
if ($rainVol == 0) {
    $clima_chuva = 'Sem chuva';
} elseif ($rainVol < 2) {
    $clima_chuva = 'Pouca chuva (' . number_format($rainVol, 1, ',', '.') . ' mm)';
} else {
    $clima_chuva = 'Chuva (' . number_format($rainVol, 1, ',', '.') . ' mm)';
}

// Consolidate fetch and deduplication for problemas e respostas públicas - otimizado
$respostas_publico = [];
$problemas = [];
$dias_com_problemas = 0;
$dias_feedback = 0;

// Carregar dados de problemas e feedback SEMPRE
if ($ano_selecionado) {
    // Query otimizada única para feedback e problemas
    $sql_issues = "
        SELECT 
            DATE(hora_de_inicio) AS data,
            musica_banda_sempre_digitar_o_nome_dos_mesmo_jeito AS banda,
            musica_resposta_publico AS resposta,
            equipamento_com_problema,
            sistema_com_problema,
            CASE WHEN musica_resposta_publico != 'Bom' THEN 1 ELSE 0 END AS tem_feedback_ruim,
            CASE WHEN (equipamento_funcionamento != 'Funcionando normalmente' OR sistema_funcionamento != 'Funcionando normalmente') THEN 1 ELSE 0 END AS tem_problema
        FROM DiarioDeBordo7Tragos 
        WHERE YEAR(hora_de_inicio) = ? 
          AND (musica_resposta_publico != 'Bom' OR equipamento_funcionamento != 'Funcionando normalmente' OR sistema_funcionamento != 'Funcionando normalmente')
        ORDER BY hora_de_inicio ASC
    ";
    $stmt_issues = $conn->prepare($sql_issues);
    $stmt_issues->bind_param('i', $ano_selecionado);
    $stmt_issues->execute();
    $issues_data = $stmt_issues->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_issues->close();
    
    // Processar dados
    $seen_feedback_dates = [];
    $seen_problem_dates = [];
    
    foreach ($issues_data as $row) {
        // Feedback
        if ($row['tem_feedback_ruim'] && !in_array($row['data'], $seen_feedback_dates)) {
            $respostas_publico[] = [
                'data' => $row['data'],
                'banda' => $row['banda'],
                'resposta' => $row['resposta']
            ];
            $seen_feedback_dates[] = $row['data'];
        }
        
        // Problemas
        if ($row['tem_problema'] && !in_array($row['data'], $seen_problem_dates)) {
            $problemas[] = [
                'data' => $row['data'],
                'equipamento_com_problema' => $row['equipamento_com_problema'],
                'sistema_com_problema' => $row['sistema_com_problema']
            ];
            $seen_problem_dates[] = $row['data'];
        }
    }
    
    $dias_com_problemas = count($seen_problem_dates);
    $dias_feedback = count($seen_feedback_dates);
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
      background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
      border: 1px solid #4a5568;
      border-radius: 12px;
      padding: 16px;
      margin-bottom: 20px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
      max-width: 300px;
      transition: all 0.3s ease;
    }

    .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 35px rgba(0, 0, 0, 0.4);
      border-color: #ecc94b;
    }

    .card-title {
      font-size: 14px;
      color: #ecc94b;
      margin-bottom: 8px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .card-value {
      font-size: 24px;
      color: #f7fafc;
      font-weight: 700;
      margin-bottom: 4px;
    }

    .card-subtitle {
      font-size: 11px;
      color: #a0aec0;
      line-height: 1.3;
    }

    .card.faturamento {
      max-width: 600px;
      background: linear-gradient(135deg, #2b6cb8 0%, #1e3a8a 100%);
    }
    
    .card.medias {
      max-width: 800px;
      background: linear-gradient(135deg, #065f46 0%, #064e3b 100%);
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

    /* Hide horizontal scrollbar when tooltip appears */
    .chartjs-tooltip {
      max-width: 250px !important;
      white-space: nowrap;
      overflow: hidden;
    }
    
    body {
      overflow-x: hidden;
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
      Diário de bordo - Siete Tragos
    </h1>
    <div id="dashboardContent">
    <!-- Filtro e banda lado a lado, filtro horizontal -->
    <div class="flex items-center justify-center w-full mb-6">
        <form method="POST" id="filtroForm" class="flex items-center gap-2 bg-gray-800 rounded-full px-3 py-2 shadow border border-gray-700 w-auto">
            <label for="inputData" class="text-xs font-medium text-gray-300 mr-2 whitespace-nowrap">Data:</label>
            <input 
                type="date" 
                name="data" 
                id="inputData" 
                class="bg-gray-700 text-gray-200 px-2 py-1 rounded-full border border-gray-600 focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 transition-all text-sm w-32"
                value="<?= htmlspecialchars($selectedDate) ?>"
            >
        </form>
    </div>

    <!-- Grid dos cards (sem o card do filtro de data) -->
    <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
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

    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
    <?php if (!empty($problemas)): ?>
    <div class="bg-gradient-to-br from-gray-800 to-gray-900 border border-gray-600 rounded-lg shadow-lg p-4 cursor-pointer hover:from-gray-700 hover:to-gray-800 hover:border-gray-500 hover:shadow-xl transition-all duration-300 transform hover:scale-102" onclick="openModal('problemasModal')">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xs font-bold text-yellow-400 mb-1">⚠️ Dias com Problemas</h2>
                <p class="text-2xl font-bold text-red-400"><?= htmlspecialchars($dias_com_problemas) ?></p>
                <p class="text-xs text-gray-400 mt-1">👆 Clique para detalhes</p>
            </div>
            <div class="text-2xl opacity-20">🔧</div>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-gradient-to-br from-gray-800 to-gray-900 border border-gray-600 rounded-lg shadow-lg p-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xs font-bold text-yellow-400 mb-1">✅ Dias com Problemas</h2>
                <p class="text-2xl font-bold text-green-400">0</p>
                <p class="text-xs text-gray-400 mt-1">Tudo funcionando!</p>
            </div>
            <div class="text-2xl opacity-20">✅</div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($respostas_publico)): ?>
    <div class="bg-gradient-to-br from-gray-800 to-gray-900 border border-gray-600 rounded-lg shadow-lg p-4 cursor-pointer hover:from-gray-700 hover:to-gray-800 hover:border-gray-500 hover:shadow-xl transition-all duration-300 transform hover:scale-102" onclick="openModal('feedbackModal')">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xs font-bold text-yellow-400 mb-1">😐 Feedback Médio/Ruim</h2>
                <p class="text-2xl font-bold text-orange-400"><?= htmlspecialchars($dias_feedback) ?></p>
                <p class="text-xs text-gray-400 mt-1">👆 Clique para detalhes</p>
            </div>
            <div class="text-2xl opacity-20">💬</div>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-gradient-to-br from-gray-800 to-gray-900 border border-gray-600 rounded-lg shadow-lg p-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xs font-bold text-yellow-400 mb-1">😊 Feedback Médio/Ruim</h2>
                <p class="text-2xl font-bold text-green-400">0</p>
                <p class="text-xs text-gray-400 mt-1">Só feedback positivo!</p>
            </div>
            <div class="text-2xl opacity-20">😊</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Card Dias Abaixo da Média -->
    <?php
        $total_dias_faturamento = count(array_filter($faturamento_dias_mes, fn($d) => ($d['faturamento'] ?? 0) > 0));
        $percent_abaixo = ($total_dias_faturamento > 0) ? round(($dias_abaixo_media / $total_dias_faturamento) * 100, 1) : 0;
    ?>
    <div class="bg-gradient-to-br from-gray-800 to-gray-900 border border-gray-600 rounded-lg shadow-lg p-4 cursor-pointer hover:from-gray-700 hover:to-gray-800 hover:border-gray-500 hover:shadow-xl transition-all duration-300 transform hover:scale-102" onclick="openModal('diasAbaixoMediaModal')">
        <div class="flex items-left justify-between">
            <div class="w-full">
                <h2 class="text-xs font-bold text-yellow-400 mb-1 text-left">📉 Dias Abaixo da Média</h2>
                <p class="text-2xl font-bold text-red-400 text-left"><?= htmlspecialchars($dias_abaixo_media ?? 0) ?></p>
                <p class="text-xs text-gray-400 mt-1 text-left">👆 Clique para detalhes</p>
            </div>
        </div>
    </div>

    <!-- Card Dias Acima da Média -->
    <div class="bg-gradient-to-br from-gray-800 to-gray-900 border border-gray-600 rounded-lg shadow-lg p-4 cursor-pointer hover:from-gray-700 hover:to-gray-800 hover:border-gray-500 hover:shadow-xl transition-all duration-300 transform hover:scale-102" onclick="openModal('diasAcimaMediaModal')">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xs font-bold text-yellow-400 mb-1">📈 Dias Acima da Média</h2>
                <p class="text-2xl font-bold text-green-400"><?= htmlspecialchars($dias_acima_media ?? 0) ?></p>
                <p class="text-xs text-gray-400 mt-1">👆 Clique para detalhes</p>
            </div>
            
        </div>
    </div>

    <!-- Card Dias com Faturamento -->
    <div class="bg-gradient-to-br from-gray-800 to-gray-900 border border-gray-600 rounded-lg shadow-lg p-4 flex items-center justify-between">
        <div>
            <h2 class="text-xs font-bold text-yellow-400 mb-1">📅 Dias com Faturamento</h2>
            <p class="text-2xl font-bold text-blue-400">
                <?= count(array_filter($faturamento_dias_mes, fn($d) => ($d['faturamento'] ?? 0) > 0)) ?>
            </p>
            <p class="text-xs text-gray-400 mt-1">Total de dias do mês com vendas</p>
        </div>
        <div class="text-2xl opacity-20">💵</div>
    </div>
</div>

    <!-- Modal para Problemas -->
    <?php if (!empty($problemas)): ?>
    <div id="problemasModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[80vh] overflow-hidden">
            <div class="flex justify-between items-center p-6 border-b border-gray-700">
                <h2 class="text-xl font-semibold text-yellow-400">Problemas Identificados em <?= htmlspecialchars($ano_selecionado) ?></h2>
                <button onclick="closeModal('problemasModal')" class="text-gray-400 hover:text-white text-2xl">×</button>
            </div>
            <div class="p-6 overflow-auto max-h-[60vh]">
                <table class="w-full text-sm text-gray-200 text-left table-fixed">
                    <thead>
                        <tr class="bg-gray-700">
                            <th class="p-2 w-28">Data</th>
                            <th class="p-2">Equipamento</th>
                            <th class="p-2">Sistema</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($problemas as $p): ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-700">
                            <td class="p-2 whitespace-nowrap"><?= date('d/m/Y', strtotime($p['data'])) ?></td>
                            <td class="p-2 break-words"><?= htmlspecialchars($p['equipamento_com_problema'] ?? '-') ?></td>
                            <td class="p-2 break-words"><?= htmlspecialchars($p['sistema_com_problema'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal para Feedback -->
    <?php if (!empty($respostas_publico)): ?>
    <div id="feedbackModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[80vh] overflow-hidden">
            <div class="flex justify-between items-center p-6 border-b border-gray-700">
                <h2 class="text-xl font-semibold text-yellow-400">Feedback Público - Médias e Ruins <?= htmlspecialchars($ano_selecionado) ?></h2>
                <button onclick="closeModal('feedbackModal')" class="text-gray-400 hover:text-white text-2xl">×</button>
            </div>
            <div class="p-6 overflow-auto max-h-[60vh]">
                <table class="w-full text-sm text-gray-200 text-left table-fixed">
                    <thead>
                        <tr class="bg-gray-700">
                            <th class="p-2 w-28">Data</th>
                            <th class="p-2 w-40">Banda</th>
                            <th class="p-2">Resposta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($respostas_publico as $r): ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-700">
                            <td class="p-2 whitespace-nowrap"><?= date('d/m/Y', strtotime($r['data'])) ?></td>
                            <td class="p-2 break-words"><?= htmlspecialchars($r['banda'] ?? '-') ?></td>
                            <td class="p-2 break-words"><?= htmlspecialchars($r['resposta'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal para Dias Abaixo da Média -->
    <?php if (!empty($detalhes_dias_abaixo_media)): ?>
    <div id="diasAbaixoMediaModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[75vh] overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b border-gray-700">
                <h2 class="text-lg font-semibold text-yellow-400">Dias Abaixo da Média - <?= htmlspecialchars($meses_disponiveis[$mes_selecionado] ?? $mes_selecionado) ?>/<?= htmlspecialchars($ano_selecionado) ?></h2>
                <button onclick="closeModal('diasAbaixoMediaModal')" class="text-gray-400 hover:text-white text-2xl">×</button>
            </div>
            <div class="p-4 overflow-auto max-h-[55vh]">
                <table class="w-full text-xs text-gray-200 text-left">
                    <thead>
                        <tr class="bg-gray-700">
                            <th class="p-2 text-xs">Data</th>
                            <th class="p-2 text-xs">Dia</th>
                            <th class="p-2 text-xs">Faturamento</th>
                            <th class="p-2 text-xs">Média 3M</th>
                            <th class="p-2 text-xs">Diferença</th>
                            <th class="p-2 text-xs">Banda</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($detalhes_dias_abaixo_media as $dia): ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-700">
                            <td class="p-2 whitespace-nowrap text-xs"><?= htmlspecialchars($dia['data_formatada']) ?></td>
                            <td class="p-2 whitespace-nowrap text-xs"><?= htmlspecialchars($dia['dia_semana']) ?></td>
                            <td class="p-2 whitespace-nowrap font-semibold text-xs">R$ <?= number_format($dia['faturamento'], 2, ',', '.') ?></td>
                            <td class="p-2 whitespace-nowrap text-yellow-400 text-xs">R$ <?= number_format($dia['media_3m'], 2, ',', '.') ?></td>
                            <td class="p-2 whitespace-nowrap text-red-400 font-bold text-xs">
                                <?php 
                                    $diferenca = $dia['faturamento'] - $dia['media_3m'];
                                    echo ($diferenca >= 0 ? '+' : '') . 'R$ ' . number_format($diferenca, 2, ',', '.'); 
                                ?>
                            </td>
                            <td class="p-2 break-words text-xs max-w-32 truncate" title="<?= htmlspecialchars($dia['banda'] ?? '-') ?>"><?= htmlspecialchars($dia['banda'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal para Dias Acima da Média -->
    <?php if (!empty($detalhes_dias_acima_media)): ?>
    <div id="diasAcimaMediaModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[75vh] overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b border-gray-700">
                <h2 class="text-lg font-semibold text-yellow-400">Dias Acima da Média - <?= htmlspecialchars($meses_disponiveis[$mes_selecionado] ?? $mes_selecionado) ?>/<?= htmlspecialchars($ano_selecionado) ?></h2>
                <button onclick="closeModal('diasAcimaMediaModal')" class="text-gray-400 hover:text-white text-2xl">×</button>
            </div>
            <div class="p-4 overflow-auto max-h-[55vh]">
                <table class="w-full text-xs text-gray-200 text-left">
                    <thead>
                        <tr class="bg-gray-700">
                            <th class="p-2 text-xs">Data</th>
                            <th class="p-2 text-xs">Dia</th>
                            <th class="p-2 text-xs">Faturamento</th>
                            <th class="p-2 text-xs">Média 3M</th>
                            <th class="p-2 text-xs">Diferença</th>
                            <th class="p-2 text-xs">Banda</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($detalhes_dias_acima_media as $dia): ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-700">
                            <td class="p-2 whitespace-nowrap text-xs"><?= htmlspecialchars($dia['data_formatada']) ?></td>
                            <td class="p-2 whitespace-nowrap text-xs"><?= htmlspecialchars($dia['dia_semana']) ?></td>
                            <td class="p-2 whitespace-nowrap font-semibold text-xs">R$ <?= number_format($dia['faturamento'], 2, ',', '.') ?></td>
                            <td class="p-2 whitespace-nowrap text-yellow-400 text-xs">R$ <?= number_format($dia['media_3m'], 2, ',', '.') ?></td>
                            <td class="p-2 whitespace-nowrap text-green-400 font-bold text-xs">
                            <?php 
                                $diferenca = $dia['faturamento'] - $dia['media_3m'];
                                echo '+' . number_format($diferenca, 2, ',', '.'); 
                            ?>
                            </td>
                            <td class="p-2 break-words text-xs max-w-32 truncate" title="<?= htmlspecialchars($dia['banda'] ?? '-') ?>"><?= htmlspecialchars($dia['banda'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.body.style.overflow = 'auto'; // Restore background scrolling
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('bg-opacity-50')) {
                e.target.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('[id$="Modal"]');
                modals.forEach(modal => {
                    if (!modal.classList.contains('hidden')) {
                        modal.classList.add('hidden');
                        document.body.style.overflow = 'auto';
                    }
                });
            }
        });
    </script>

    <!-- Gráfico de faturamento diário do mês - movido para o final -->
    <?php if (!empty($faturamento_dias_mes) && !$erro_filtro): ?>
    <?php
// Gera matriz do mês para o calendário
$primeiro_dia_mes = new DateTime(array_key_first($faturamento_dias_mes));
$ultimo_dia_mes = new DateTime(array_key_last($faturamento_dias_mes));
$primeiro_dia_semana = (int)$primeiro_dia_mes->format('w'); // 0=Dom
$total_dias_mes = (int)$ultimo_dia_mes->format('d');
$calendario = [];
$dia_atual = 1;
for ($semana = 0; $semana < 6; $semana++) {
    $linha = [];
    for ($dia_sem = 0; $dia_sem < 7; $dia_sem++) {
        if (($semana === 0 && $dia_sem < $primeiro_dia_semana) || $dia_atual > $total_dias_mes) {
            $linha[] = null;
        } else {
            $data = $primeiro_dia_mes->format('Y-m-') . str_pad($dia_atual, 2, '0', STR_PAD_LEFT);
            $linha[] = $data;
            $dia_atual++;
        }
    }
    $calendario[] = $linha;
}
$dias_semana = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
$faturamento_total_mes = array_sum(array_column($faturamento_dias_mes, 'faturamento'));
?>
<div class="mt-8 bg-gray-800 rounded-lg shadow-md p-4 w-full overflow-x-auto">
    <div class="mb-2 text-center text-yellow-300 font-semibold">
        Faturamento Total do Mês: 
        <span class="text-gray-100 font-bold">
            R$ <?= number_format($faturamento_total_mes, 2, ',', '.') ?>
        </span>
    </div>
    <table class="w-full text-xs table-fixed border-separate border-spacing-1">
        <thead>
            <tr>
                <?php foreach ($dias_semana as $ds): ?>
                    <th class="text-center text-gray-400 font-bold"><?= $ds ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($calendario as $semana): ?>
            <tr>
                <?php foreach ($semana as $data): ?>
                    <?php if ($data && isset($faturamento_dias_mes[$data])): 
                        $fat = $faturamento_dias_mes[$data]['faturamento'];
                        $dtObj = new DateTime($data);
                        $dia_semana_num = (int)$dtObj->format('w'); // 0=Domingo, 6=Sábado
$media = $medias_3m_por_dia_semana[$dia_semana_num === 0 ? 1 : $dia_semana_num + 1] ?? 0;
                        $banda = $faturamento_dias_mes[$data]['banda'] ?? '-';
                        $cor = $media > 0
                            ? ($fat >= $media ? 'bg-green-700 border-green-400' : 'bg-red-700 border-red-400')
                            : 'bg-gray-700 border-gray-500';
                        $tooltip = "Faturamento: R$ " . number_format($fat, 2, ',', '.') .
                                   "\nMédia 3M: R$ " . number_format($media, 2, ',', '.') .
                                   "\nBanda: " . $banda;
                    ?>
                    <td class="text-center align-middle border rounded cursor-pointer <?= $cor ?>" title="<?= htmlspecialchars($tooltip) ?>">
                        <div class="font-bold"><?= (int)substr($data, 8, 2) ?></div>
                        <div class="text-[10px]"><?= $fat > 0 ? 'R$ ' . number_format($fat, 0, ',', '.') : '-' ?></div>
                    </td>
                    <?php else: ?>
                    <td></td>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="flex justify-center gap-4 mt-2 text-xs">
        <span class="inline-flex items-center"><span class="w-4 h-3 inline-block bg-green-700 border border-green-400 rounded mr-1"></span>Acima da média</span>
        <span class="inline-flex items-center"><span class="w-4 h-3 inline-block bg-red-700 border border-red-400 rounded mr-1"></span>Abaixo da média</span>
        <span class="inline-flex items-center"><span class="w-4 h-3 inline-block bg-gray-700 border border-gray-500 rounded mr-1"></span>Sem média</span>
    </div>
</div>
<?php endif; ?>

    <script>
        document.getElementById('inputData').addEventListener('change', function() {
            document.getElementById('filtroForm').submit();
        });
    </script>

  </div>
  </main>

</body>
</html>

<?php
ob_end_flush();
?>