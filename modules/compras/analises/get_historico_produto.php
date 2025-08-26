<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Autenticação
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
    
    if ($conn->connect_error) {
        throw new Exception("Erro de conexão: " . $conn->connect_error);
    }

    // Receber dados via POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['produto'])) {
        throw new Exception("Dados inválidos");
    }
    
    $produto = $conn->real_escape_string($input['produto']);
    $fornecedor = $conn->real_escape_string($input['fornecedor'] ?? '');
    $base = $input['base'] ?? 'TAP';
    $periodo = intval($input['periodo'] ?? 6);
    
    if (!in_array($base, ['TAP', 'WAB'])) {
        $base = 'TAP';
    }
    
    if (!in_array($periodo, [3, 6, 12])) {
        $periodo = 6;
    }
    
    // Calcular meses fechados
    $hoje = new DateTime('now');
    $primeiroDiaMesAtual = new DateTime('first day of this month');
    $mesesFechados = [];
    
    for ($i = $periodo; $i >= 1; $i--) {
        $data = (clone $primeiroDiaMesAtual)->modify("-{$i} month");
        $ano = (int)$data->format('Y');
        $mes = (int)$data->format('n');
        $mesesFechados[] = ['ano' => $ano, 'mes' => $mes, 'key' => "$ano-$mes"];
    }
    
    // Buscar dados históricos
    $condicoes = [];
    foreach ($mesesFechados as $m) {
        $condicoes[] = "(YEAR(STR_TO_DATE(DATA, '%d/%m/%Y')) = {$m['ano']} AND MONTH(STR_TO_DATE(DATA, '%d/%m/%Y')) = {$m['mes']})";
    }
    $wherePeriodo = implode(' OR ', $condicoes);
    
    $tabela = $base === 'TAP' ? 'fComprasTAP' : 'fComprasWAB';
    
    $sql = "
        SELECT 
            custo_atual,
            YEAR(STR_TO_DATE(DATA, '%d/%m/%Y')) as ano,
            MONTH(STR_TO_DATE(DATA, '%d/%m/%Y')) as mes
        FROM {$tabela}
        WHERE produto = ? 
        AND ($wherePeriodo)
        AND custo_atual > 0
        ORDER BY ano, mes
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $produto);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Organizar dados por mês
    $precosPorMes = [];
    $meses = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    
    while ($row = $result->fetch_assoc()) {
        $chave = $row['ano'] . '-' . str_pad($row['mes'], 2, '0', STR_PAD_LEFT);
        
        if (!isset($precosPorMes[$chave])) {
            $precosPorMes[$chave] = [
                'total' => 0,
                'count' => 0,
                'ano' => $row['ano'],
                'mes' => $row['mes']
            ];
        }
        
        $precosPorMes[$chave]['total'] += floatval($row['custo_atual']);
        $precosPorMes[$chave]['count']++;
    }
    
    // Calcular médias e preparar dados para o gráfico
    $labels = [];
    $precos = [];
    
    foreach ($mesesFechados as $mesInfo) {
        $chave = $mesInfo['key'];
        
        if (isset($precosPorMes[$chave])) {
            $precoMedio = $precosPorMes[$chave]['total'] / $precosPorMes[$chave]['count'];
            $precos[] = round($precoMedio, 2);
        } else {
            // Se não há dados para este mês, interpolar ou usar último valor conhecido
            $precos[] = count($precos) > 0 ? end($precos) : 0;
        }
        
        $labels[] = $meses[$mesInfo['mes']] . '/' . substr($mesInfo['ano'], 2);
    }
    
    // Se não há dados suficientes, gerar dados básicos
    if (empty($precos) || array_sum($precos) == 0) {
        $precos = array_fill(0, count($labels), 0);
    }
    
    echo json_encode([
        'success' => true,
        'historico' => [
            'labels' => $labels,
            'precos' => $precos
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
