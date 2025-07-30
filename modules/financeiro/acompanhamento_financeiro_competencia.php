<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sidebar e autenticação
require_once __DIR__ . '/../../sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

// Conexão com o banco
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Busca a data/hora da última atualização na tabela fAtualizacoes
$sqlUltimaAtualizacao = "SELECT data_hora FROM fAtualizacoes ORDER BY data_hora DESC LIMIT 1";
$resUltimaAtualizacao = $conn->query($sqlUltimaAtualizacao);
$ultimaAtualizacao = null;
if ($resUltimaAtualizacao && $row = $resUltimaAtualizacao->fetch_assoc()) {
    $ultimaAtualizacao = $row['data_hora'];
}

// Defina o ano e mês atual (ou pegue do GET)
$anoAtual = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
$mesAtual = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');

// ==================== [VERIFICAR SE O MÊS ESTÁ FECHADO AUTOMATICAMENTE] ====================
// Um mês é considerado fechado se não for o mês atual
$anoCorrente = (int)date('Y');
$mesCorrente = (int)date('n');

// Se estamos visualizando um mês anterior ao atual, considera fechado
$mesFechado = ($anoAtual < $anoCorrente) || ($anoAtual == $anoCorrente && $mesAtual < $mesCorrente);

// Carregue todas as parcelas do ano para totais do mês atual
// Se o mês estiver fechado, considera apenas as contas pagas
if ($mesFechado) {
    // Mês fechado: considera apenas contas pagas
$sql = "
    SELECT 
        ID_CONTA,
        CATEGORIA,
        SUBCATEGORIA,
        DESCRICAO_CONTA,
        VALOR,
        DATA_VENCIMENTO
    FROM fContasAPagar
    WHERE YEAR(DATA_VENCIMENTO) = $anoAtual AND MONTH(DATA_VENCIMENTO) = $mesAtual
    ORDER BY CATEGORIA, SUBCATEGORIA, DESCRICAO_CONTA
";
} else {
    // Mês aberto: considera todas as contas (comportamento original)
$sql = "
    SELECT 
        ID_CONTA,
        CATEGORIA,
        SUBCATEGORIA,
        DESCRICAO_CONTA,
        VALOR,
        DATA_VENCIMENTO
    FROM fContasAPagar
    WHERE YEAR(DATA_VENCIMENTO) = $anoAtual AND MONTH(DATA_VENCIMENTO) = $mesAtual
    ORDER BY CATEGORIA, SUBCATEGORIA, DESCRICAO_CONTA
";
}
$res = $conn->query($sql);
$linhasMesAtual = [];
while ($f = $res->fetch_assoc()) {
    $linhasMesAtual[] = [
        'ID_CONTA'        => $f['ID_CONTA'],
        'CATEGORIA'       => $f['CATEGORIA'],
        'SUBCATEGORIA'    => $f['SUBCATEGORIA'],
        'DESCRICAO_CONTA' => $f['DESCRICAO_CONTA'],
        'VALOR_EXIBIDO'   => $f['VALOR'],
        'DATA_VENCIMENTO' => $f['DATA_VENCIMENTO'],
    ];
}

$endDateChart = new DateTime('first day of ' . date('Y-m'));
$startDateChart = (clone $endDateChart)->modify('-12 months');
$startDateStringChart = $startDateChart->format('Y-m-d');
$endDateStringChart = $endDateChart->format('Y-m-d');
$periodChart = new DatePeriod($startDateChart, new DateInterval('P1M'), $endDateChart);


// Organiza em matriz hierárquica: categoria > subcategoria > descrição > conta
$matrizAtual = [];
$matrizHierarquica = [];
foreach ($linhasMesAtual as $linha) {
    $cat = $linha['CATEGORIA'] ?? 'SEM CATEGORIA';
    $sub = $linha['SUBCATEGORIA'] ?? 'SEM SUBCATEGORIA';
    $desc = $linha['DESCRICAO_CONTA'] ?? 'SEM DESCRIÇÃO';
    $idConta = $linha['ID_CONTA'];
    $valor = floatval($linha['VALOR_EXIBIDO']);

    // Matriz original para manter compatibilidade
    $matrizAtual[$cat][$sub][] = $valor;

    // Matriz hierárquica completa
    $matrizHierarquica[$cat][$sub][$desc][$idConta][] = [
        'parcela' => 1, // Não há parcela, então sempre 1
        'valor' => $valor,
    ];
}

// Calcule o total do mês atual para cada categoria, subcategoria, descrição
$atualCat = [];
$atualSub = [];
$atualDesc = [];
foreach ($matrizHierarquica as $cat => $subs) {
    $somaAtualCat = 0;
    foreach ($subs as $sub => $descs) {
        $somaAtualSub = 0;
        foreach ($descs as $desc => $contas) {
            $somaAtualDesc = 0;
            foreach ($contas as $parcelas) {
                foreach ($parcelas as $parcela) {
                    $somaAtualDesc += $parcela['valor'];
                }
            }
            $atualDesc[$cat][$sub][$desc] = $somaAtualDesc;
            $somaAtualSub += $somaAtualDesc;
        }
        $atualSub[$cat][$sub] = $somaAtualSub;
        $somaAtualCat += $somaAtualSub;
    }
    $atualCat[$cat] = $somaAtualCat;
}

// ==================== [ADICIONAR fOutrasReceitas À MATRIZ PRINCIPAL] ====================
// Buscar dados de fOutrasReceitas para adicionar "Z - ENTRADA DE REPASSE" como categoria normal
$sqlOutrasRecMatriz = "
    SELECT CATEGORIA, SUBCATEGORIA, SUM(VALOR) AS TOTAL
    FROM fOutrasReceitas
    WHERE YEAR(DATA_COMPETENCIA) = $anoAtual AND MONTH(DATA_COMPETENCIA) = $mesAtual
    GROUP BY CATEGORIA, SUBCATEGORIA
";
$resOutrasRecMatriz = $conn->query($sqlOutrasRecMatriz);
if ($resOutrasRecMatriz) {
    while ($row = $resOutrasRecMatriz->fetch_assoc()) {
        $catOR = !empty($row['CATEGORIA']) ? $row['CATEGORIA'] : 'NÃO CATEGORIZADO';
        $subOR = !empty($row['SUBCATEGORIA']) ? $row['SUBCATEGORIA'] : 'NÃO ESPECIFICADO';
        $valorOR = floatval($row['TOTAL']);
        
        // Se a categoria for "Z - ENTRADA DE REPASSE", adiciona à matriz principal
        if ($catOR === 'Z - ENTRADA DE REPASSE') {
            $atualSub[$catOR][$subOR] = ($atualSub[$catOR][$subOR] ?? 0) + $valorOR;
            $atualCat[$catOR] = ($atualCat[$catOR] ?? 0) + $valorOR;
        }
    }
}

// Array de meses para exibição
$meses = [
    1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
    7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'
];

// Receita operacional do mês atual - Valores já pagos
$atualRecPago = 0;
$resRecPago = $conn->query("
    SELECT SUM(VALOR_REAL) AS TOTAL
    FROM vw_receita_competencia
    WHERE YEAR(DATA_FATURAMENTO) = $anoAtual AND MONTH(DATA_FATURAMENTO) = $mesAtual
");
if ($row = $resRecPago->fetch_assoc()) {
    $atualRecPago = floatval($row['TOTAL'] ?? 0);
}

// Contas a receber do mês atual (vencidas + ainda não vencidas)
// Se o mês estiver fechado, não considera contas vencidas
$atualRecAReceber = 0;
// Se quiser separar o que está "a receber", ajuste a view ou lógica conforme necessário.
// Aqui, considera tudo como receita do mês (pago + a receber) pela competência.
if (!$mesFechado) {
    // Mês aberto: considera tudo da view
    $resRecAReceber = $conn->query("
        SELECT SUM(VALOR_REAL) AS TOTAL
        FROM vw_receita_competencia
        WHERE YEAR(DATA_FATURAMENTO) = $anoAtual AND MONTH(DATA_FATURAMENTO) = $mesAtual
    ");
    if ($row = $resRecAReceber->fetch_assoc()) {
        $atualRecAReceber = 0; // Não soma nada, pois tudo já está em $atualRecPago
    }
}
// Se o mês estiver fechado, $atualRecAReceber permanece 0

// Receita bruta total (pago + a receber)
$atualRec = $atualRecPago;

// Ordena as categorias para exibição
$categoriasDRE = [
    'RECEITA BRUTA', 'TRIBUTOS', 'RECEITA LÍQUIDA (RECEITA BRUTA - TRIBUTOS)',
    'CUSTO VARIÁVEL', 'LUCRO BRUTO (RECEITA LÍQUIDA - CUSTO VARIÁVEL)',
    'CUSTO FIXO', 'DESPESA FIXA', 'DESPESA VENDA', 'LUCRO LÍQUIDO',
    'RECEITAS NAO OPERACIONAIS', 'Z - ENTRADA DE REPASSE',
    'INVESTIMENTO INTERNO', 'INVESTIMENTO EXTERNO', 'AMORTIZAÇÃO',
    'Z - SAIDA DE REPASSE', 'FLUXO DE CAIXA'
];

$matrizOrdenadaParaExibicao = [];
$sqlTodasCategorias = "SELECT DISTINCT CATEGORIA FROM fContasAPagar ORDER BY CATEGORIA";
$resTodasCategorias = $conn->query($sqlTodasCategorias);
if ($resTodasCategorias) {
    while ($rowCat = $resTodasCategorias->fetch_assoc()) {
        $catNome = $rowCat['CATEGORIA'];
        if (!isset($matrizAtual[$catNome])) { // Adiciona categoria mesmo que não tenha valor no mês atual
            $matrizAtual[$catNome] = [];
        }
    }
}

$matrizOrdenada = [];
foreach ($matrizHierarquica as $cat => $subs) {
    if (mb_strtoupper(trim($cat)) !== 'Z - SAIDA DE REPASSE') {
        $matrizOrdenada[$cat] = $subs;
    }
}
if (isset($matrizHierarquica['Z - SAIDA DE REPASSE'])) {
    $matrizOrdenada['Z - SAIDA DE REPASSE'] = $matrizHierarquica['Z - SAIDA DE REPASSE'];
}


// Consulta para carregar a ÚLTIMA meta gravada para cada Categoria/Subcategoria
$sqlMetas = "
    SELECT 
        fm1.Categoria, 
        fm1.Subcategoria, 
        fm1.Meta
    FROM 
        fMetasFabrica fm1
    INNER JOIN (
        SELECT 
            Categoria, 
            Subcategoria, 
            MAX(Data) as MaxData
        FROM 
            fMetasFabrica
        GROUP BY 
            Categoria, Subcategoria
    ) fm2 
    ON fm1.Categoria = fm2.Categoria 
    AND IFNULL(fm1.Subcategoria, '') = IFNULL(fm2.Subcategoria, '')
    AND fm1.Data = fm2.MaxData
";
$resMetas = $conn->query($sqlMetas);
$metasArray = [];
if ($resMetas) {
    while ($m = $resMetas->fetch_assoc()){
        $cat = $m['Categoria'];
        $sub = $m['Subcategoria'] ?? '';
        $metasArray[$cat][$sub] = $m['Meta'];
    }
}

$metaReceita       = $metasArray['RECEITA BRUTA'][''] ?? 0;
$metaTributos      = $metasArray['TRIBUTOS'][''] ?? 0;
$metaCustoVariavel = $metasArray['CUSTO VARIÁVEL'][''] ?? 0;

$keyReceitaLiquida = "RECEITA LÍQUIDA (RECEITA BRUTA - TRIBUTOS)";
$keyLucroBruto     = "LUCRO BRUTO (RECEITA LÍQUIDA - CUSTO VARIÁVEL)";
$keyLucroLiquidoPHP= "LUCRO LÍQUIDO";
$keyFluxoCaixa     = "FLUXO DE CAIXA";

// Cálculos para colunas "Meta"
$metaReceitaLiquida = $metaReceita - $metaTributos;
$metaLucroBruto     = $metaReceitaLiquida - $metaCustoVariavel;

$metaCustoFixo      = $metasArray['CUSTO FIXO'][''] ?? 0;
$metaDespesaFixa    = $metasArray['DESPESA FIXA'][''] ?? 0;
$metaDespesaVenda   = $metasArray['DESPESA VENDA'][''] ?? 0;
$totalMetaDespesasOperacionais = $metaCustoFixo + $metaDespesaFixa + $metaDespesaVenda;
$metaLucroLiquidoCalculado = $metaLucroBruto - $totalMetaDespesasOperacionais;

// Prioriza meta salva para LL, senão usa a calculada
$metaLucroLiquidoDisplay = $metasArray[$keyLucroLiquidoPHP][''] ?? $metaLucroLiquidoCalculado;

// Cálculos para colunas "Realizado"
$atualReceitaLiquida = $atualRec - ($atualCat['TRIBUTOS'] ?? 0);
$atualLucroBruto     = $atualReceitaLiquida - ($atualCat['CUSTO VARIÁVEL'] ?? 0);

$atualCustoFixo    = $atualCat['CUSTO FIXO'] ?? 0;
$atualDespesaFixa  = $atualCat['DESPESA FIXA'] ?? 0;
$atualDespesaVenda = $atualCat['DESPESA VENDA'] ?? 0;
$totalAtualDespesasOperacionais = $atualCustoFixo + $atualDespesaFixa + $atualDespesaVenda;
$atualLucroLiquido = $atualLucroBruto - $totalAtualDespesasOperacionais;

// ==================== [DADOS DE fOutrasReceitas - MÊS ATUAL E METAS] ====================
$outrasReceitasPorCatSubMesAtual = [];
$totalAtualOutrasRecGlobal = 0;

// Definir quais subcategorias pertencem a RECEITAS NÃO OPERACIONAIS
$subcategoriasReceitasNaoOp = ['EMPRÉSTIMO', 'RETORNO DE INVESTIMENTO', 'OUTRAS RECEITAS'];

$sqlOutrasRecAtual = "
    SELECT CATEGORIA, SUBCATEGORIA, SUM(VALOR) AS TOTAL
    FROM fOutrasReceitas
    WHERE YEAR(DATA_COMPETENCIA) = $anoAtual AND MONTH(DATA_COMPETENCIA) = $mesAtual
    GROUP BY CATEGORIA, SUBCATEGORIA
";
$resOutrasRecAtual = $conn->query($sqlOutrasRecAtual);
if ($resOutrasRecAtual) {
    while ($row = $resOutrasRecAtual->fetch_assoc()) {
        $catOR = !empty($row['CATEGORIA']) ? $row['CATEGORIA'] : 'NÃO CATEGORIZADO';
        $subOR = !empty($row['SUBCATEGORIA']) ? $row['SUBCATEGORIA'] : 'NÃO ESPECIFICADO';
        $valorOR = floatval($row['TOTAL']);
        
        // Pular "Z - ENTRADA DE REPASSE" pois já foi processada na matriz principal
        if ($catOR === 'Z - ENTRADA DE REPASSE') {
            continue;
        }
        
        // Apenas processar RECEITAS NÃO OPERACIONAIS
        if (in_array($subOR, $subcategoriasReceitasNaoOp)) {
            $outrasReceitasPorCatSubMesAtual[$catOR][$subOR] = $valorOR;
            $totalAtualOutrasRecGlobal += $valorOR;
        }
    }
}
// Garantir que todas as categorias/subs de RNO que têm metas apareçam, mesmo sem valor atual
if (isset($metasArray['RECEITAS NAO OPERACIONAIS'])) { // Meta principal para RNO
    if (!isset($outrasReceitasPorCatSubMesAtual['RECEITAS NAO OPERACIONAIS'])) {
         // Não precisa adicionar explicitamente, o loop abaixo cuidará das subcategorias
    }
}

foreach ($metasArray as $metaCatKey => $metaSubArray) {
    // Verifica se a categoria da meta existe em $outrasReceitasPorCatSubMesAtual ou $entradaRepassePorCatSubMesAtual
    // E se não é uma categoria principal da DRE (ex: 'RECEITA BRUTA')
    // A ideia é pegar categorias como "OUTRAS RECEITAS", "JUROS APLICACAO" que são de RNO ou ER
    $isCategoriaRNO = true; // Assumir que é RNO se não for uma das principais DRE
    $isCategoriaER = true;  // Assumir que é ER se não for uma das principais DRE
    
    $categoriasPrincipaisDRE = ['RECEITA BRUTA', 'TRIBUTOS', 'CUSTO VARIÁVEL', 'CUSTO FIXO', 'DESPESA FIXA', 'DESPESA VENDA', 'INVESTIMENTO INTERNO', 'INVESTIMENTO EXTERNO', 'AMORTIZAÇÃO', 'Z - SAIDA DE REPASSE', 'Z - ENTRADA DE REPASSE'];
    if (in_array($metaCatKey, $categoriasPrincipaisDRE) || 
        $metaCatKey === $keyReceitaLiquida || 
        $metaCatKey === $keyLucroBruto ||
        $metaCatKey === $keyLucroLiquidoPHP ||
        $metaCatKey === $keyFluxoCaixa ||
        $metaCatKey === 'RECEITAS NAO OPERACIONAIS' || // A linha principal já é tratada
        $metaCatKey === 'ENTRADA DE REPASSE' // A linha principal já é tratada
        ) {
        $isCategoriaRNO = false;
        $isCategoriaER = false;
    }

    // Para RNO: verificar se alguma subcategoria está na lista de RNO
    if ($isCategoriaRNO) {
        $temSubcategoriaRNO = false;
        foreach ($metaSubArray as $metaSubKey => $metaValor) {
            if ($metaSubKey !== '' && in_array($metaSubKey, $subcategoriasReceitasNaoOp)) {
                $temSubcategoriaRNO = true;
                break;
            }
        }
        
        if ($temSubcategoriaRNO && !isset($outrasReceitasPorCatSubMesAtual[$metaCatKey])) {
            $outrasReceitasPorCatSubMesAtual[$metaCatKey] = [];
        }
        
        if ($temSubcategoriaRNO) {
            foreach ($metaSubArray as $metaSubKey => $metaValor) {
                if ($metaSubKey !== '' && in_array($metaSubKey, $subcategoriasReceitasNaoOp) && !isset($outrasReceitasPorCatSubMesAtual[$metaCatKey][$metaSubKey])) {
                     $outrasReceitasPorCatSubMesAtual[$metaCatKey][$metaSubKey] = 0; // Adiciona sub com valor 0 se não existir
                }
            }
        }
    }
    
    // Para ER: verificar se alguma subcategoria NÃO está na lista de RNO
    if ($isCategoriaER) {
        $temSubcategoriaER = false;
        foreach ($metaSubArray as $metaSubKey => $metaValor) {
            if ($metaSubKey !== '' && !in_array($metaSubKey, $subcategoriasReceitasNaoOp)) {
                $temSubcategoriaER = true;
                break;
            }
        }
        
        if ($temSubcategoriaER && !isset($entradaRepassePorCatSubMesAtual[$metaCatKey])) {
            $entradaRepassePorCatSubMesAtual[$metaCatKey] = [];
        }
        
        if ($temSubcategoriaER) {
            foreach ($metaSubArray as $metaSubKey => $metaValor) {
                if ($metaSubKey !== '' && !in_array($metaSubKey, $subcategoriasReceitasNaoOp) && !isset($entradaRepassePorCatSubMesAtual[$metaCatKey][$metaSubKey])) {
                     $entradaRepassePorCatSubMesAtual[$metaCatKey][$metaSubKey] = 0; // Adiciona sub com valor 0 se não existir
                }
            }
        }
    }
}


// ==================== [CÁLCULO FLUXO DE CAIXA - META E ATUAL] ====================
$metaInvestInterno = $metasArray['INVESTIMENTO INTERNO'][''] ?? 0;
$metaInvestExterno = $metasArray['INVESTIMENTO EXTERNO'][''] ?? 0;
$metaSaidaRepasse  = $metasArray['Z - SAIDA DE REPASSE'][''] ?? 0;
$metaAmortizacao   = $metasArray['AMORTIZAÇÃO'][''] ?? 0;

$metaTotalRNO = 0;
if (isset($metasArray['RECEITAS NAO OPERACIONAIS'])) {
    foreach ($metasArray['RECEITAS NAO OPERACIONAIS'] as $subcat => $metaValor) {
        if ($subcat !== '' && strtoupper(trim($subcat)) !== 'Z - ENTRADA DE REPASSE') {
            $metaTotalRNO += floatval($metaValor);
        }
    }
}
$metaEntradaRepasse = $metasArray['Z - ENTRADA DE REPASSE'][''] ?? 0; // Categoria normal

// DEBUG TEMPORÁRIO: Verificar valores usados no cálculo do fluxo de caixa da meta
error_log('DEBUG META FLUXO: ' . json_encode([
  'metaLucroLiquidoDisplay' => $metaLucroLiquidoDisplay,
  'metaTotalRNO' => $metaTotalRNO,
  'metaEntradaRepasse' => $metaEntradaRepasse,
  'metaInvestInterno' => $metaInvestInterno,
  'metaInvestExterno' => $metaInvestExterno,
  'metaSaidaRepasse' => $metaSaidaRepasse,
  'metaAmortizacao' => $metaAmortizacao,
]));

$metaFluxoCaixaCalculado = ($metaLucroLiquidoDisplay + $metaTotalRNO + $metaEntradaRepasse) - ($metaInvestInterno + $metaInvestExterno + $metaSaidaRepasse + $metaAmortizacao);
$metaFluxoCaixaDisplay = $metaFluxoCaixaCalculado;

$atualInvestInterno = $atualCat['INVESTIMENTO INTERNO'] ?? 0;
$atualInvestExterno = $atualCat['INVESTIMENTO EXTERNO'] ?? 0;
$atualSaidaRepasse  = $atualCat['Z - SAIDA DE REPASSE'] ?? 0;
$atualAmortizacao   = $atualCat['AMORTIZAÇÃO'] ?? 0;
$atualEntradaRepasse = $atualCat['Z - ENTRADA DE REPASSE'] ?? 0; // Categoria normal

// Cálculo correto: LUCRO + RNO + Z-ENTRADA - (INVEST + Z-SAIDA + AMORT)
$atualFluxoCaixa = ($atualLucroLiquido + $totalAtualOutrasRecGlobal + $atualEntradaRepasse) - ($atualInvestInterno + $atualInvestExterno + $atualSaidaRepasse + $atualAmortizacao);

// ==================== [SETUP DE DATAS] ====================
// Período para análise de metas excedidas (últimos 6 meses)
$endDateAnalyse = new DateTime('first day of this month');
$startDateAnalyse = (clone $endDateAnalyse)->modify('-6 months');
$startDateStringAnalyse = $startDateAnalyse->format('Y-m-d');
$endDateStringAnalyse = $endDateAnalyse->format('Y-m-d');

// ==================== [ANÁLISE DE METAS EXCEDIDAS - ÚLTIMOS 6 MESES] ====================
$dadosReaisMensais = [];

// 1. Buscar todos os gastos dos últimos 6 meses
$sqlGastos6Meses = "
    SELECT 
        YEAR(DATA_VENCIMENTO) AS ano, 
        MONTH(DATA_VENCIMENTO) AS mes, 
        CATEGORIA, 
        SUM(VALOR) AS total
    FROM fContasAPagar
    WHERE DATA_VENCIMENTO >= '$startDateStringAnalyse' AND DATA_VENCIMENTO < '$endDateStringAnalyse'
    GROUP BY ano, mes, CATEGORIA
";
$resGastos6Meses = $conn->query($sqlGastos6Meses);
if ($resGastos6Meses) {
    while ($row = $resGastos6Meses->fetch_assoc()) {
        $monthKey = $row['ano'] . '-' . str_pad($row['mes'], 2, '0', STR_PAD_LEFT);
        $dadosReaisMensais[$monthKey][$row['CATEGORIA']] = (float)$row['total'];
    }
}

// 2. Comparar os gastos mensais com a META ATUAL (já carregada em $metasArray)
$metasExcedidas = [];
$mesesAbreviadosAnalyse = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

foreach ($dadosReaisMensais as $mesKey => $gastosDoMes) {
    list($ano, $mes) = explode('-', $mesKey);
    $nomeMes = $mesesAbreviadosAnalyse[(int)$mes - 1] . '/' . substr($ano, -2);

    foreach ($gastosDoMes as $categoria => $gastoReal) {
        // Pega a meta ATUAL para a categoria (do array já carregado no início do script)
        $metaGasto = $metasArray[$categoria][''] ?? 0;

        if ($metaGasto > 0 && $gastoReal > $metaGasto) {
            $metasExcedidas[] = [
                'mes' => $nomeMes,
                'categoria' => $categoria,
                'meta' => $metaGasto,
                'realizado' => $gastoReal,
                'diferenca' => $gastoReal - $metaGasto
            ];
        }
    }
}


$chartData = [];
$chartLabels = [];
$mesesAbreviados = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

// Inicializar dados mensais para o gráfico
$monthlyData = [];
foreach ($periodChart as $dt) {
    $monthKey = $dt->format('Y-m');
    $chartLabels[] = $mesesAbreviados[$dt->format('n') - 1] . '/' . $dt->format('y');
    $monthlyData[$monthKey] = [
        'receita' => 0,
        'despesas' => []
    ];
}

// 1. Buscar Receitas Operacionais (exceto Outras Receitas) da view vw_receita_competencia
$sqlReceitas = "
    SELECT 
        YEAR(DATA_FATURAMENTO) AS ano, 
        MONTH(DATA_FATURAMENTO) AS mes, 
        SUM(VALOR_REAL) AS total
    FROM vw_receita_competencia
    WHERE DATA_FATURAMENTO >= '$startDateStringChart' AND DATA_FATURAMENTO < '$endDateStringChart'
    GROUP BY ano, mes
";
$resReceitas = $conn->query($sqlReceitas);
if($resReceitas) {
    while ($row = $resReceitas->fetch_assoc()) {
        $monthKey = $row['ano'] . '-' . str_pad($row['mes'], 2, '0', STR_PAD_LEFT);
        if (isset($monthlyData[$monthKey])) {
            $monthlyData[$monthKey]['receita'] = (float)$row['total'];
        }
    }
}

// 1.1 Buscar Contas a Receber do mês (vencidas + ainda não vencidas)
$sqlReceitasAReceber = "
    SELECT 
        YEAR(DATA_VENCIMENTO) AS ano, 
        MONTH(DATA_VENCIMENTO) AS mes, 
        SUM(VALOR) AS total
    FROM fContasAReceberDetalhes
    WHERE STATUS != 'Pago' 
    AND DATA_VENCIMENTO >= '$startDateStringChart' AND DATA_VENCIMENTO < '$endDateStringChart'
    GROUP BY ano, mes
";
$resReceitasAReceber = $conn->query($sqlReceitasAReceber);
if($resReceitasAReceber) {
    while ($row = $resReceitasAReceber->fetch_assoc()) {
        $monthKey = $row['ano'] . '-' . str_pad($row['mes'], 2, '0', STR_PAD_LEFT);
        if (isset($monthlyData[$monthKey])) {
            $monthlyData[$monthKey]['receita'] += (float)$row['total'];
        }
    }
}

// 2. Buscar Despesas e Outras Receitas (Contas a Pagar)
$sqlDespesas = "
SELECT 
        YEAR(DATA_VENCIMENTO) AS ano, 
        MONTH(DATA_VENCIMENTO) AS mes, 
        CATEGORIA, 
        SUM(VALOR) AS total
    FROM fContasAPagar
    WHERE DATA_VENCIMENTO >= '$startDateStringChart' AND DATA_VENCIMENTO < '$endDateStringChart'
    GROUP BY ano, mes, CATEGORIA
";
$resDespesas = $conn->query($sqlDespesas);
if($resDespesas) {
    while ($row = $resDespesas->fetch_assoc()) {
        $monthKey = $row['ano'] . '-' . str_pad($row['mes'], 2, '0', STR_PAD_LEFT);
        if (isset($monthlyData[$monthKey])) {
            $monthlyData[$monthKey]['despesas'][$row['CATEGORIA']] = (float)$row['total'];
        }
    }
}

// 3. Buscar dados de fOutrasReceitas para RNO e ER
$sqlOutrasRecChart = "
    SELECT 
        YEAR(DATA_COMPETENCIA) AS ano, 
        MONTH(DATA_COMPETENCIA) AS mes, 
        CATEGORIA,
        SUBCATEGORIA,
        SUM(VALOR) AS total
    FROM fOutrasReceitas
    WHERE DATA_COMPETENCIA >= '$startDateStringChart' AND DATA_COMPETENCIA < '$endDateStringChart'
    GROUP BY ano, mes, CATEGORIA, SUBCATEGORIA
";
$resOutrasRecChart = $conn->query($sqlOutrasRecChart);
if($resOutrasRecChart) {
    while ($row = $resOutrasRecChart->fetch_assoc()) {
        $monthKey = $row['ano'] . '-' . str_pad($row['mes'], 2, '0', STR_PAD_LEFT);
        $categoria = $row['CATEGORIA'] ?? '';
        $subCategoria = $row['SUBCATEGORIA'] ?? '';
        
        if (isset($monthlyData[$monthKey])) {
            // Se for "Z - ENTRADA DE REPASSE", tratar como categoria normal
            if ($categoria === 'Z - ENTRADA DE REPASSE') {
                $monthlyData[$monthKey]['despesas']['Z - ENTRADA DE REPASSE'] = 
                    ($monthlyData[$monthKey]['despesas']['Z - ENTRADA DE REPASSE'] ?? 0) + (float)$row['total'];
            }
            // Para outras categorias, apenas adicionar às RECEITAS NAO OPERACIONAIS
            elseif (in_array($subCategoria, $subcategoriasReceitasNaoOp)) {
                $monthlyData[$monthKey]['despesas']['RECEITAS NAO OPERACIONAIS'] = 
                    ($monthlyData[$monthKey]['despesas']['RECEITAS NAO OPERACIONAIS'] ?? 0) + (float)$row['total'];
            }
        }
    }
}

// 4. Calcular o Fluxo de Caixa para cada mês
foreach ($monthlyData as $monthKey => $data) {
    $receita = $data['receita'];
    $despesas = $data['despesas'];
    
    $outrasReceitas = $despesas['RECEITAS NAO OPERACIONAIS'] ?? 0;
    $zEntradaRepasse = $despesas['Z - ENTRADA DE REPASSE'] ?? 0; // Categoria normal

    // DRE
    $tributos = $despesas['TRIBUTOS'] ?? 0;
    $custoVariavel = $despesas['CUSTO VARIÁVEL'] ?? 0;
    $receitaLiquida = $receita - $tributos;
    $lucroBruto = $receitaLiquida - $custoVariavel;
    
    $custoFixo = $despesas['CUSTO FIXO'] ?? 0;
    $despesaFixa = $despesas['DESPESA FIXA'] ?? 0;
    $despesaVenda = $despesas['DESPESA VENDA'] ?? 0;
    $lucroLiquido = $lucroBruto - ($custoFixo + $despesaFixa + $despesaVenda);

    // Fluxo de Caixa - cálculo correto
    $investInterno = $despesas['INVESTIMENTO INTERNO'] ?? 0;
    $investExterno = $despesas['INVESTIMENTO EXTERNO'] ?? 0;
    $saidaRepasse = $despesas['Z - SAIDA DE REPASSE'] ?? 0;
    $amortizacao = $despesas['AMORTIZAÇÃO'] ?? 0;
    
    $fluxoDeCaixa = ($lucroLiquido + $outrasReceitas + $zEntradaRepasse) - ($investInterno + $investExterno + $saidaRepasse + $amortizacao);
    
    $chartData[] = round($fluxoDeCaixa, 2);
}

$jsonChartLabels = json_encode($chartLabels);
$jsonChartData = json_encode($chartData);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Acompanhamento DRE - Gestão</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="/assets/css/style.css" rel="stylesheet">
  <style>
    .dre-cat    { background: #22223b; font-weight: bold; cursor:pointer; }
    .dre-sub    { background: #383858; font-weight: 500; cursor:pointer; }
    .dre-desc   { background: #2c2c4a; font-weight: normal; cursor:pointer; }
    .dre-parcela { background: #232946; font-weight: normal; }
    .dre-subcat-l1 { background: #2c2c4a; font-weight: bold; cursor:pointer; }
    .dre-subcat-l2 { background: #383858; font-weight: 500; }
    .dre-hide   { display: none; }
    table, th, td {
      font-family: 'Segoe UI', Arial, sans-serif;
      font-size: 11px;
      border: 0.5px solid #111827; /* Cor da borda ajustada para a mesma do fundo */
      border-collapse: collapse;
    }
    .dre-cat, .dre-sub, .dre-desc, .dre-subcat-l1 {
      font-size: 12px;
    }
    .toggle-icon {
      display: inline-block;
      width: 12px;
      text-align: center;
      margin-right: 5px;
      font-family: monospace;
    }
    .dre-cat:hover, .dre-sub:hover, .dre-desc:hover, .dre-subcat-l1:hover {
      background-color: #4a5568;
    }
  </style>
</head>
<body class="bg-gray-900 text-gray-100 flex min-h-screen">
<main class="flex-1 bg-gray-900 p-6 relative">

  <!-- Data/hora atualização -->
  <?php if ($ultimaAtualizacao): ?>
    <div style="position:absolute;top:1.5rem;right:2.5rem;z-index:10;font-size:0.95rem;color:#ffe066;">
      Data/hora atualização: <?= date('d/m/Y H:i', strtotime($ultimaAtualizacao)) ?>
    </div>

<div style="position:absolute;top:7rem;right:2.5rem;z-index:10;">
  <a href="/modules/financeiro/acompanhamento_fincanceiro.php" 
     class="bg-yellow-400 hover:bg-yellow-300 text-black font-bold px-4 py-2 rounded shadow transition"
     style="display:inline-block;">
    Ir para Fluxo de Caixa
  </a>
</div>


  <?php endif; ?>

  <!-- Indicador de Status do Mês -->
  <?php /* 
if ($mesFechado): ?>
  <div style="position:absolute;top:4rem;right:2.5rem;z-index:10;font-size:0.9rem;color:#ff6b6b;font-weight:bold;background:#dc2626;padding:0.25rem 0.75rem;border-radius:0.25rem;">
    📌 MÊS FECHADO - Considerando apenas contas pagas
  </div>
<?php else: ?>
  <div style="position:absolute;top:4rem;right:2.5rem;z-index:10;font-size:0.9rem;color:#10b981;font-weight:bold;background:#059669;padding:0.25rem 0.75rem;border-radius:0.25rem;">
    🔓 MÊS ABERTO - Considerando contas pagas e vencidas
  </div>
<?php endif; ?>
*/ ?>


<form method="get" class="mb-4 flex gap-2 items-end">
    <label>
      Ano:
      <select name="ano" class="text-black rounded p-1">
        <?php for ($a = date('Y')-2; $a <= date('Y')+1; $a++): ?>
          <option value="<?=$a?>" <?=$a==$anoAtual?'selected':''?>><?=$a?></option>
        <?php endfor; ?>
      </select>
    </label>
    <label>
      Mês:
      <select name="mes" class="text-black rounded p-1">
        <?php foreach ($meses as $num => $nome): ?>
          <option value="<?=$num?>" <?=$num==$mesAtual?'selected':''?>><?=$nome?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit" class="bg-yellow-400 text-black px-3 py-1 rounded font-bold">Filtrar</button>
  </form>

  <h1 class="text-2xl font-bold text-yellow-400 mb-6">
    ACOMPANHAMENTO COMPETÊNCIA - <?=$anoAtual?> / <?=$meses[$mesAtual]?>
  </h1>

  <!-- Adicione este bloco antes da tabela principal DRE -->
  <div class="mb-4 flex items-center gap-2">
    <input 
      type="text" 
      id="dre-search" 
      placeholder="🔍 Pesquisar Descrição da Conta..." 
      class="rounded p-2 text-black w-72"
      oninput="filtrarDescricaoConta()"
    >
  </div>
  
  <table id="tabelaAcompanhamento" class="min-w-full text-xs mx-auto border border-gray-700 rounded">
    <thead>
      <tr>
        <th rowspan="2" class="p-2 text-center bg-gray-800">Categoria &gt; SubCategoria</th>
        <th colspan="2" class="p-2 text-center bg-red-900">Meta / % Meta s/ FAT.</th>
        <th colspan="3" class="p-2 text-center bg-purple-900">Realizado / % Realizado s/ FAT. / Comparação Meta</th>
      </tr>
      <tr>
        <th class="p-2 text-center bg-red-700">Meta</th>
        <th class="p-2 text-center bg-red-700">% Meta s/ FAT.</th>
        <th class="p-2 text-center bg-purple-700">Realizado</th>
        <th class="p-2 text-center bg-purple-700">% Realizado s/ Meta.</th>
        <th class="p-2 text-center bg-purple-700">Comparação Meta</th>
      </tr>
    </thead>
    <tbody>
      <!-- RECEITA BRUTA -->
      <tr class="dre-cat" style="background:#1a4c2b; cursor:pointer;" onclick="toggleCategory('receita_bruta')">
        <td class="p-2 text-left">
          <span class="toggle-icon" id="icon_receita_bruta">▶</span>
          RECEITA BRUTA
        </td>
        <td class="p-2 text-right"><?= isset($metasArray['RECEITA BRUTA']['']) ? 'R$ '.number_format($metasArray['RECEITA BRUTA'][''],2,',','.') : '-' ?></td>
        <td class="p-2 text-center">
          <?= ($metaReceita > 0 && isset($metasArray['RECEITA BRUTA'][''])) ? number_format(($metasArray['RECEITA BRUTA']['']/$metaReceita)*100,2,',','.') .'%' : '-' ?>
        </td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($atualRec,2,',','.') ?></td>
        <td class="p-2 text-center">
          <?php
            $meta_rb_val = $metasArray['RECEITA BRUTA'][''] ?? null;
            if (isset($meta_rb_val) && $meta_rb_val != 0) {
              echo number_format(($atualRec / $meta_rb_val) * 100, 2, ',', '.') . '%';
            } else { echo '-'; }
          ?>
        </td>
        <td class="p-2 text-center">
          <?php 
            $meta_val = $metasArray['RECEITA BRUTA'][''] ?? null;
            $realizado_val = $atualRec;
            if (isset($meta_val)) {
              $comparacao = $meta_val - $realizado_val; // Para receita, positivo é bom se meta > realizado (faltou atingir)
              $corComparacao = ($realizado_val >= $meta_val) ? 'text-green-400' : 'text-red-400'; // Verde se atingiu/superou
              echo '<span class="' . $corComparacao . '">R$ ' . number_format($realizado_val - $meta_val, 2, ',', '.') . '</span>';
            } else { echo '-'; }
          ?>
        </td>
      </tr>
      
      <!-- Detalhamento da Receita Bruta -->
      <tr class="dre-sub receita_bruta dre-hide" style="background:#2d5a3d;">
        <td class="p-2 text-left" style="padding-left:2em;">RECEBIDO</td>
        <td class="p-2 text-right">-</td>
        <td class="p-2 text-center">-</td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($atualRecPago,2,',','.') ?></td>
        <td class="p-2 text-center">
          <?php
            if ($atualRec > 0) {
              echo number_format(($atualRecPago / $atualRec) * 100, 2, ',', '.') . '%';
            } else { echo '-'; }
          ?>
        </td>
        <td class="p-2 text-center">-</td>
      </tr>
      
      <tr class="dre-sub receita_bruta dre-hide" style="background:#2d5a3d;">
        <td class="p-2 text-left" style="padding-left:2em;">A RECEBER</td>
        <td class="p-2 text-right">-</td>
        <td class="p-2 text-center">-</td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($atualRecAReceber,2,',','.') ?></td>
        <td class="p-2 text-center">
          <?php
            if ($atualRec > 0) {
              echo number_format(($atualRecAReceber / $atualRec) * 100, 2, ',', '.') . '%';
            } else { echo '-'; }
          ?>
        </td>
        <td class="p-2 text-center">-</td>
      </tr>

    <!-- TRIBUTOS -->
    <?php if(isset($matrizOrdenada['TRIBUTOS']) || isset($metasArray['TRIBUTOS'])): ?>
      <tr class="dre-cat cat_trib" onclick="toggleCategory('trib')">
        <td class="p-2 text-left">
          <span class="toggle-icon" id="icon_trib">▶</span>
          TRIBUTOS
        </td>
        <td class="p-2 text-right"><?= isset($metasArray['TRIBUTOS']['']) ? 'R$ '.number_format($metasArray['TRIBUTOS'][''],2,',','.') : '-' ?></td>
        <td class="p-2 text-center"><?= ($metaReceita > 0 && isset($metasArray['TRIBUTOS'][''])) ? number_format(($metasArray['TRIBUTOS']['']/$metaReceita)*100,2,',','.') .'%' : '-' ?></td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($atualCat['TRIBUTOS'] ?? 0,2,',','.') ?></td>
        <td class="p-2 text-center">
          <?php
            $meta_t_val = $metasArray['TRIBUTOS'][''] ?? null;
            $realizado_t_val = $atualCat['TRIBUTOS'] ?? 0;
            if (isset($meta_t_val) && $meta_t_val != 0) {
              echo number_format(($realizado_t_val / $meta_t_val) * 100, 2, ',', '.') . '%';
            } else { echo '-'; }
          ?>
        </td>
        <td class="p-2 text-center">
          <?php 
            $meta_val = $metasArray['TRIBUTOS'][''] ?? null;
            $realizado_val = $atualCat['TRIBUTOS'] ?? 0;
            if (isset($meta_val)) {
              $comparacao = $meta_val - $realizado_val; // Para despesa, positivo é bom (gastou menos que a meta)
              $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
              echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
            } else { echo '-'; }
          ?>
        </td>
      </tr>
      <?php foreach(($matrizOrdenada['TRIBUTOS'] ?? []) as $sub => $valores): ?>
        <?php if($sub): ?>
          <tr class="dre-sub trib dre-hide" onclick="toggleSubcategory('trib_<?= md5($sub) ?>')">
            <td class="p-2 text-left" style="padding-left:2em;">
              <span class="toggle-icon" id="icon_trib_<?= md5($sub) ?>">▶</span>
              <?= htmlspecialchars($sub) ?>
            </td>
            <td class="p-2 text-right"><?= isset($metasArray['TRIBUTOS'][$sub]) ? 'R$ '.number_format($metasArray['TRIBUTOS'][$sub],2,',','.') : '-' ?></td>
            <td class="p-2 text-center"><?= ($metaReceita > 0 && isset($metasArray['TRIBUTOS'][$sub])) ? number_format(($metasArray['TRIBUTOS'][$sub]/$metaReceita)*100,2,',','.') .'%' : '-' ?></td>
            <td class="p-2 text-right"><?= 'R$ '.number_format($atualSub['TRIBUTOS'][$sub] ?? 0,2,',','.') ?></td>
            <td class="p-2 text-center">
              <?php
                $meta_ts_val = $metasArray['TRIBUTOS'][$sub] ?? null;
                $realizado_ts_val = $atualSub['TRIBUTOS'][$sub] ?? 0;
                if (isset($meta_ts_val) && $meta_ts_val != 0) {
                  echo number_format(($realizado_ts_val / $meta_ts_val) * 100, 2, ',', '.') . '%';
                } else { echo '-'; }
              ?>
            </td>
            <td class="p-2 text-center">
              <?php 
                $meta_val = $metasArray['TRIBUTOS'][$sub] ?? null;
                $realizado_val = $atualSub['TRIBUTOS'][$sub] ?? 0;
                if (isset($meta_val)) {
                  $comparacao = $meta_val - $realizado_val;
                  $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
                  echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
                } else { echo '-'; }
              ?>
            </td>
          </tr>

          <?php if(isset($matrizHierarquica['TRIBUTOS'][$sub])): ?>
            <?php foreach($matrizHierarquica['TRIBUTOS'][$sub] as $desc => $contas): ?>
              <tr class="dre-desc trib_<?= md5($sub) ?> dre-hide" onclick="toggleDescription('trib_<?= md5($sub . $desc) ?>')">
                <td class="p-2 text-left" style="padding-left:3em;">
                  <span class="toggle-icon" id="icon_trib_<?= md5($sub . $desc) ?>">▶</span>
                  <?= htmlspecialchars($desc) ?>
                </td>
                <td class="p-2 text-right">-</td>
                <td class="p-2 text-center">-</td>
                <td class="p-2 text-right"><?= 'R$ '.number_format($atualDesc['TRIBUTOS'][$sub][$desc] ?? 0,2,',','.') ?></td>
                <td class="p-2 text-center">-</td>
                <td class="p-2 text-center">-</td>
              </tr>

              <?php foreach($contas as $idConta => $parcelas): ?>
                <?php foreach($parcelas as $parcela): ?>
                  <tr class="dre-parcela trib_<?= md5($sub . $desc) ?> dre-hide">
                    <td class="p-2 text-left" style="padding-left:4em;">
                      <span class="text-gray-400">Parcela:</span> <?= htmlspecialchars($parcela['parcela']) ?>
                      <span class="text-xs text-gray-500 ml-2">(ID: <?= $idConta ?>)</span>
                    </td>
                    <td class="p-2 text-right">-</td>
                    <td class="p-2 text-center">-</td>
                    <td class="p-2 text-right"><?= 'R$ '.number_format($parcela['valor'],2,',','.') ?></td>
                    <td class="p-2 text-center">-</td>
                    <td class="p-2 text-center">-</td>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            <?php endforeach; ?>
          <?php endif; ?>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- RECEITA LÍQUIDA -->
    <tr id="rowFatLiquido" class="dre-cat" style="background:#1a4c2b;">
      <td class="p-2 text-left">RECEITA LÍQUIDA (RECEITA BRUTA - TRIBUTOS)</td>
      <td class="p-2 text-right"><?= isset($metasArray[$keyReceitaLiquida]['']) ? 'R$ '.number_format($metasArray[$keyReceitaLiquida][''],2,',','.') : (isset($metaReceita) ? 'R$ '.number_format($metaReceitaLiquida,2,',','.') : '-') ?></td>
      <td class="p-2 text-center">
        <?php
          $valorMetaRL = $metasArray[$keyReceitaLiquida][''] ?? $metaReceitaLiquida;
          if ($metaReceita > 0 && (isset($metasArray[$keyReceitaLiquida]['']) || isset($metaReceita)) ) {
            echo number_format(($valorMetaRL / $metaReceita) * 100, 2, ',', '.') . '%';
          } else { echo '-'; }
        ?>
      </td>
      <td class="p-2 text-right"><?= 'R$ '.number_format($atualReceitaLiquida,2,',','.') ?></td>
      <td class="p-2 text-center">
        <?php
          $meta_rl_val = $metasArray[$keyReceitaLiquida][''] ?? ($metaReceitaLiquida ?? null);
          if (isset($meta_rl_val) && $meta_rl_val != 0) {
            echo number_format(($atualReceitaLiquida / $meta_rl_val) * 100, 2, ',', '.') . '%';
          } else { echo '-'; }
        ?>
      </td>
      <td class="p-2 text-center">
        <?php
          if (isset($meta_rl_val)) {
            $comparacaoRL = $atualReceitaLiquida - $meta_rl_val;
            $corComparacaoRL = ($comparacaoRL >= 0) ? 'text-green-400' : 'text-red-400';
            echo '<span class="' . $corComparacaoRL . '">R$ ' . number_format($comparacaoRL, 2, ',', '.') . '</span>';
          } else { echo '-'; }
        ?>
      </td>
    </tr>

    <!-- CUSTO VARIÁVEL -->
    <?php if(isset($matrizOrdenada['CUSTO VARIÁVEL']) || isset($metasArray['CUSTO VARIÁVEL'])): ?>
      <tr class="dre-cat cat_cvar" onclick="toggleCategory('cvar')">
        <td class="p-2 text-left">
          <span class="toggle-icon" id="icon_cvar">▶</span>
          CUSTO VARIÁVEL
        </td>
        <td class="p-2 text-right"><?= isset($metasArray['CUSTO VARIÁVEL']['']) ? 'R$ '.number_format($metasArray['CUSTO VARIÁVEL'][''],2,',','.') : '-' ?></td>
        <td class="p-2 text-center"><?= ($metaReceita > 0 && isset($metasArray['CUSTO VARIÁVEL'][''])) ? number_format(($metasArray['CUSTO VARIÁVEL']['']/$metaReceita)*100,2,',','.') .'%' : '-' ?></td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($atualCat['CUSTO VARIÁVEL'] ?? 0,2,',','.') ?></td>
        <td class="p-2 text-center">
          <?php
            $meta_cv_val = $metasArray['CUSTO VARIÁVEL'][''] ?? null;
            $realizado_cv_val = $atualCat['CUSTO VARIÁVEL'] ?? 0;
            if (isset($meta_cv_val) && $meta_cv_val != 0) {
              echo number_format(($realizado_cv_val / $meta_cv_val) * 100, 2, ',', '.') . '%';
            } else { echo '-'; }
          ?>
        </td>
        <td class="p-2 text-center">
          <?php 
            $meta_val = $metasArray['CUSTO VARIÁVEL'][''] ?? null;
            $realizado_val = $atualCat['CUSTO VARIÁVEL'] ?? 0;
            if (isset($meta_val)) {
              $comparacao = $meta_val - $realizado_val;
              $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
              echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
            } else { echo '-'; }
          ?>
        </td>
      </tr>
      <?php foreach(($matrizOrdenada['CUSTO VARIÁVEL'] ?? []) as $sub => $descricoes): ?>
        <?php if($sub): ?>
          <tr class="dre-sub cvar dre-hide" onclick="toggleSubcategory('cvar_<?= md5($sub) ?>')">
            <td class="p-2 text-left" style="padding-left:2em;">
              <span class="toggle-icon" id="icon_cvar_<?= md5($sub) ?>">▶</span>
              <?= htmlspecialchars($sub) ?>
            </td>
            <td class="p-2 text-right"><?= isset($metasArray['CUSTO VARIÁVEL'][$sub]) ? 'R$ '.number_format($metasArray['CUSTO VARIÁVEL'][$sub],2,',','.') : '-' ?></td>
            <td class="p-2 text-center"><?= ($metaReceita > 0 && isset($metasArray['CUSTO VARIÁVEL'][$sub])) ? number_format(($metasArray['CUSTO VARIÁVEL'][$sub]/$metaReceita)*100,2,',','.') .'%' : '-' ?></td>
            <td class="p-2 text-right"><?= 'R$ '.number_format($atualSub['CUSTO VARIÁVEL'][$sub] ?? 0,2,',','.') ?></td>
            <td class="p-2 text-center">
              <?php
                $meta_cvs_val = $metasArray['CUSTO VARIÁVEL'][$sub] ?? null;
                $realizado_cvs_val = $atualSub['CUSTO VARIÁVEL'][$sub] ?? 0;
                if (isset($meta_cvs_val) && $meta_cvs_val != 0) {
                  echo number_format(($realizado_cvs_val / $meta_cvs_val) * 100, 2, ',', '.') . '%';
                } else { echo '-'; }
              ?>
            </td>
            <td class="p-2 text-center">
              <?php 
                $meta_val = $metasArray['CUSTO VARIÁVEL'][$sub] ?? null;
                $realizado_val = $atualSub['CUSTO VARIÁVEL'][$sub] ?? 0;
                if (isset($meta_val)) {
                  $comparacao = $meta_val - $realizado_val;
                  $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
                  echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
                } else { echo '-'; }
              ?>
            </td>
          </tr>

          <?php foreach($descricoes as $desc => $contas): ?>
            <tr class="dre-desc cvar_<?= md5($sub) ?> dre-hide" onclick="toggleDescription('cvar_<?= md5($sub . $desc) ?>')">
              <td class="p-2 text-left" style="padding-left:3em;">
                <span class="toggle-icon" id="icon_cvar_<?= md5($sub . $desc) ?>">▶</span>
                <?= htmlspecialchars($desc) ?>
              </td>
              <td class="p-2 text-right">-</td>
              <td class="p-2 text-center">-</td>
              <td class="p-2 text-right"><?= 'R$ '.number_format($atualDesc['CUSTO VARIÁVEL'][$sub][$desc] ?? 0,2,',','.') ?></td>
              <td class="p-2 text-center">-</td>
              <td class="p-2 text-center">-</td>
            </tr>

            <?php foreach($contas as $idConta => $parcelas): ?>
              <?php foreach($parcelas as $parcela): ?>
                <tr class="dre-parcela cvar_<?= md5($sub . $desc) ?> dre-hide">
                  <td class="p-2 text-left" style="padding-left:4em;">
                    <span class="text-gray-400">Parcela:</span> <?= htmlspecialchars($parcela['parcela']) ?>
                    <span class="text-xs text-gray-500 ml-2">(ID: <?= $idConta ?>)</span>
                  </td>
                  <td class="p-2 text-right">-</td>
                  <td class="p-2 text-center">-</td>
                  <td class="p-2 text-right"><?= 'R$ '.number_format($parcela['valor'],2,',','.') ?></td>
                  <td class="p-2 text-center">-</td>
                  <td class="p-2 text-center">-</td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- LUCRO BRUTO -->
    <tr id="rowLucroBruto" class="dre-cat" style="background:#1a4c2b;">
      <td class="p-2 text-left">LUCRO BRUTO (RECEITA LÍQUIDA - CUSTO VARIÁVEL)</td>
      <td class="p-2 text-right"><?= isset($metasArray[$keyLucroBruto]['']) ? 'R$ '.number_format($metasArray[$keyLucroBruto][''],2,',','.') : (isset($metaReceita) ? 'R$ '.number_format($metaLucroBruto,2,',','.') : '-') ?></td>
      <td class="p-2 text-center">
        <?php
          $valorMetaLB = $metasArray[$keyLucroBruto][''] ?? $metaLucroBruto;
          if ($metaReceita > 0 && (isset($metasArray[$keyLucroBruto]['']) || isset($metaReceita)) ) {
            echo number_format(($valorMetaLB / $metaReceita) * 100, 2, ',', '.') . '%';
          } else { echo '-'; }
        ?>
      </td>
      <td class="p-2 text-right"><?= 'R$ '.number_format($atualLucroBruto,2,',','.') ?></td>
      <td class="p-2 text-center">
        <?php
          $meta_lb_val = $metasArray[$keyLucroBruto][''] ?? ($metaLucroBruto ?? null);
          if (isset($meta_lb_val) && $meta_lb_val != 0) {
            echo number_format(($atualLucroBruto / $meta_lb_val) * 100, 2, ',', '.') . '%';
          } else { echo '-'; }
        ?>
      </td>
      <td class="p-2 text-center">
        <?php
          if (isset($meta_lb_val)) {
            $comparacaoLB = $atualLucroBruto - $meta_lb_val;
            $corComparacaoLB = ($comparacaoLB >= 0) ? 'text-green-400' : 'text-red-400';
            echo '<span class="' . $corComparacaoLB . '">R$ ' . number_format($comparacaoLB, 2, ',', '.') . '</span>';
          } else { echo '-'; }
        ?>
      </td>
    </tr>

    <!-- CUSTO FIXO, DESPESA FIXA, DESPESA VENDA -->
    <?php 
      foreach(['CUSTO FIXO','DESPESA FIXA','DESPESA VENDA'] as $catName):
        if(isset($matrizOrdenada[$catName]) || isset($metasArray[$catName])):
        $catKey = strtolower(str_replace(' ', '_', $catName));
    ?>
      <tr class="dre-cat" onclick="toggleCategory('<?= $catKey ?>')">
        <td class="p-2 text-left">
          <span class="toggle-icon" id="icon_<?= $catKey ?>">▶</span>
          <?= $catName ?>
        </td>
        <td class="p-2 text-right"><?= isset($metasArray[$catName]['']) ? 'R$ '.number_format($metasArray[$catName][''],2,',','.') : '-' ?></td>
        <td class="p-2 text-center"><?= ($metaReceita > 0 && isset($metasArray[$catName][''])) ? number_format(($metasArray[$catName]['']/$metaReceita)*100,2,',','.') .'%' : '-' ?></td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($atualCat[$catName] ?? 0,2,',','.') ?></td>
        <td class="p-2 text-center">
          <?php
            $meta_c_val = $metasArray[$catName][''] ?? null;
            $realizado_c_val = $atualCat[$catName] ?? 0;
            if (isset($meta_c_val) && $meta_c_val != 0) {
              echo number_format(($realizado_c_val / $meta_c_val) * 100, 2, ',', '.') . '%';
            } else { echo '-'; }
          ?>
        </td>
        <td class="p-2 text-center">
          <?php 
            $meta_val = $metasArray[$catName][''] ?? null;
            $realizado_val = $atualCat[$catName] ?? 0;
            if (isset($meta_val)) {
              $comparacao = $meta_val - $realizado_val;
              $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
              echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
            } else { echo '-'; }
          ?>
        </td>
      </tr>
      <?php foreach(($matrizOrdenada[$catName] ?? []) as $sub => $descricoes): ?>
        <?php if($sub): ?>
         <tr class="dre-sub <?= $catKey ?> dre-hide" onclick="toggleSubcategory('<?= $catKey ?>_<?= md5($sub) ?>')">
            <td class="p-2 text-left" style="padding-left:2em;">
              <span class="toggle-icon" id="icon_<?= $catKey ?>_<?= md5($sub) ?>">▶</span>
              <?= htmlspecialchars($sub) ?>
            </td>
            <td class="p-2 text-right"><?= isset($metasArray[$catName][$sub]) ? 'R$ ' . number_format($metasArray[$catName][$sub], 2, ',', '.') : '-' ?></td>
            <td class="p-2 text-center"><?= ($metaReceita > 0 && isset($metasArray[$catName][$sub])) ? number_format(($metasArray[$catName][$sub] / $metaReceita) * 100, 2, ',', '.') . '%' : '-' ?></td>
            <td class="p-2 text-right"><?= 'R$ ' . number_format($atualSub[$catName][$sub] ?? 0, 2, ',', '.') ?></td>
            <td class="p-2 text-center">
              <?php
                $meta_cs_val = $metasArray[$catName][$sub] ?? null;
                $realizado_cs_val = $atualSub[$catName][$sub] ?? 0;
                if (isset($meta_cs_val) && $meta_cs_val != 0) {
                  echo number_format(($realizado_cs_val / $meta_cs_val) * 100, 2, ',', '.') . '%';
                } else { echo '-'; }
              ?>
            </td>
            <td class="p-2 text-center">
              <?php 
                $meta_val = $metasArray[$catName][$sub] ?? null;
                $realizado_val = $atualSub[$catName][$sub] ?? 0;
                if (isset($meta_val)) {
                  $comparacao = $meta_val - $realizado_val;
                  $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
                } else { echo '-'; }
              ?>
            </td>
          </tr>

          <?php foreach($descricoes as $desc => $contas): ?>
            <tr class="dre-desc <?= $catKey ?>_<?= md5($sub) ?> dre-hide" onclick="toggleDescription('<?= $catKey ?>_<?= md5($sub . $desc) ?>')">
              <td class="p-2 text-left" style="padding-left:3em;">
                <span class="toggle-icon" id="icon_<?= $catKey ?>_<?= md5($sub . $desc) ?>">▶</span>
                <?= htmlspecialchars($desc) ?>
              </td>
              <td class="p-2 text-right">-</td>
              <td class="p-2 text-center">-</td>
              <td class="p-2 text-right"><?= 'R$ '.number_format($atualDesc[$catName][$sub][$desc] ?? 0,2,',','.') ?></td>
              <td class="p-2 text-center">-</td>
              <td class="p-2 text-center">-</td>
            </tr>

            <?php foreach($contas as $idConta => $parcelas): ?>
              <?php foreach($parcelas as $parcela): ?>
                <tr class="dre-parcela <?= $catKey ?>_<?= md5($sub . $desc) ?> dre-hide">
                  <td class="p-2 text-left" style="padding-left:4em;">
                    <span class="text-gray-400">Parcela:</span> <?= htmlspecialchars($parcela['parcela']) ?>
                    <span class="text-xs text-gray-500 ml-2">(ID: <?= $idConta ?>)</span>
                  </td>
                  <td class="p-2 text-right">-</td>
                  <td class="p-2 text-center">-</td>
                  <td class="p-2 text-right"><?= 'R$ '.number_format($parcela['valor'],2,',','.') ?></td>
                  <td class="p-2 text-center">-</td>
                  <td class="p-2 text-center">-</td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php 
        endif;
      endforeach;
    ?>

    <!-- LUCRO LÍQUIDO -->
    <tr class="dre-cat" style="background:#1a4c2b;">
      <td class="p-2 text-left">LUCRO LÍQUIDO</td>
      <td class="p-2 text-right"><?= (isset($metasArray[$keyLucroLiquidoPHP]['']) || isset($metaReceita)) ? 'R$ '.number_format($metaLucroLiquidoDisplay,2,',','.') : '-' ?></td>
      <td class="p-2 text-center">
        <?php
          if ($metaReceita > 0 && (isset($metasArray[$keyLucroLiquidoPHP]['']) || isset($metaReceita)) ) {
            echo number_format(($metaLucroLiquidoDisplay / $metaReceita) * 100, 2, ',', '.') . '%';
          } else { echo '-'; }
        ?>
      </td>
      <td class="p-2 text-right"><?= 'R$ '.number_format($atualLucroLiquido,2,',','.') ?></td>
      <td class="p-2 text-center">
        <?php
          if (isset($metaLucroLiquidoDisplay) && $metaLucroLiquidoDisplay != 0 && (isset($metasArray[$keyLucroLiquidoPHP]['']) || isset($metaReceita))) {
            echo number_format(($atualLucroLiquido / $metaLucroLiquidoDisplay) * 100, 2, ',', '.') . '%';
          } else { echo '-'; }
        ?>
      </td>
      <td class="p-2 text-center">
        <?php 
          if (isset($metaLucroLiquidoDisplay) && (isset($metasArray[$keyLucroLiquidoPHP]['']) || isset($metaReceita))) {
            $comparacao = $atualLucroLiquido - $metaLucroLiquidoDisplay;
            $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
            echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
          } else { echo '-'; }
        ?>
      </td>
    </tr>

    <!-- RECEITAS NAO OPERACIONAIS -->
       <tr class="dre-cat-principal text-white font-bold" style="background:#1a4c2b;">
      <td class="p-2 text-left">RECEITAS NAO OPERACIONAIS</td>
      <td class="p-2 text-right"><?= isset($metasArray['RECEITAS NAO OPERACIONAIS']['']) ? 'R$ '.number_format($metasArray['RECEITAS NAO OPERACIONAIS'][''],2,',','.') : '-' ?></td>
      <td class="p-2 text-center">
          <?php
            if ($metaReceita > 0 && isset($metasArray['RECEITAS NAO OPERACIONAIS'][''])) {
              echo number_format(($metasArray['RECEITAS NAO OPERACIONAIS'][''] / $metaReceita) * 100, 2, ',', '.') . '%';
            } else { echo '-'; }
          ?>
      </td>
      <td class="p-2 text-right"><?= 'R$ '.number_format($totalAtualOutrasRecGlobal,2,',','.') ?></td>
      <td class="p-2 text-center">
          <?php
            $meta_rno_principal_val = $metasArray['RECEITAS NAO OPERACIONAIS'][''] ?? null;
            if(isset($meta_rno_principal_val) && $meta_rno_principal_val != 0) {
                echo number_format(($totalAtualOutrasRecGlobal / $meta_rno_principal_val) * 100, 2, ',', '.') . '%';
            } else { echo '-';}
          ?>
      </td>
      <td class="p-2 text-center">
          <?php
            if(isset($meta_rno_principal_val)) {
                $comparacaoRNO_Principal = $totalAtualOutrasRecGlobal - $meta_rno_principal_val;
                $corCompRNO_Principal = ($comparacaoRNO_Principal >=0) ? 'text-green-400' : 'text-red-400';
                echo '<span class="'.$corCompRNO_Principal.'">R$ '.number_format($comparacaoRNO_Principal, 2, ',', '.').'</span>';
            } else { echo '-';}
          ?>
      </td>
    </tr>

    <?php if (!empty($outrasReceitasPorCatSubMesAtual) || count(array_filter(array_keys($metasArray), function($k){ return strpos($k, "RECEITAS NAO OPERACIONAIS") === false && !in_array($k, ['RECEITA BRUTA', 'TRIBUTOS', 'CUSTO VARIÁVEL', 'CUSTO FIXO', 'DESPESA FIXA', 'DESPESA VENDA', 'INVESTIMENTO INTERNO', 'INVESTIMENTO EXTERNO', 'AMORTIZAÇÃO', 'Z - SAIDA DE REPASSE', 'Z - ENTRADA DE REPASSE', $GLOBALS['keyReceitaLiquida'], $GLOBALS['keyLucroBruto'], $GLOBALS['keyLucroLiquidoPHP'], $GLOBALS['keyFluxoCaixa']]); })) > 0 ): ?>
      <?php
        // Consolidar categorias de RNO (aquelas em $outrasReceitasPorCatSubMesAtual que não são DRE principal)
        $categoriasRNOExibiveis = [];
        foreach ($outrasReceitasPorCatSubMesAtual as $catNomeOR => $subcategoriasOR) {
            if ($catNomeOR === 'RECEITAS NAO OPERACIONAIS') continue; // Já exibida como principal
            $categoriasRNOExibiveis[$catNomeOR] = $subcategoriasOR;
        }
        // Adicionar categorias de RNO que só têm meta
        foreach ($metasArray as $metaCatKey => $metaSubArray) {
            $isCategoriaRNO = true;
            $categoriasPrincipaisDRE = ['RECEITA BRUTA', 'TRIBUTOS', 'CUSTO VARIÁVEL', 'CUSTO FIXO', 'DESPESA FIXA', 'DESPESA VENDA', 'INVESTIMENTO INTERNO', 'INVESTIMENTO EXTERNO', 'AMORTIZAÇÃO', 'Z - SAIDA DE REPASSE', 'Z - ENTRADA DE REPASSE', 'RECEITAS NAO OPERACIONAIS'];
            if (in_array($metaCatKey, $categoriasPrincipaisDRE) || $metaCatKey === $keyReceitaLiquida || $metaCatKey === $keyLucroBruto || $metaCatKey === $keyLucroLiquidoPHP || $metaCatKey === $keyFluxoCaixa) {
                $isCategoriaRNO = false;
            }
            if ($isCategoriaRNO && !isset($categoriasRNOExibiveis[$metaCatKey])) {
                $categoriasRNOExibiveis[$metaCatKey] = []; // Adiciona para garantir que apareça
            }
        }
        ksort($categoriasRNOExibiveis); // Ordenar alfabeticamente
      ?>

      <?php foreach ($categoriasRNOExibiveis as $catNomeOR => $subcategoriasOR): ?>
        <?php
          $totalAtualCatRNO = 0;
          if (isset($outrasReceitasPorCatSubMesAtual[$catNomeOR])) {
              foreach ($outrasReceitasPorCatSubMesAtual[$catNomeOR] as $atualSubValor) {
                  $totalAtualCatRNO += $atualSubValor;
              }
          }
        ?>
        <tr class="dre-subcat-l1">
          <td class="p-2 pl-6 text-left font-semibold"><?= htmlspecialchars($catNomeOR) ?></td>
          <td class="p-2 text-right"><?= isset($metasArray[$catNomeOR]['']) ? 'R$ '.number_format($metasArray[$catNomeOR][''],2,',','.') : '-' ?></td>
          <td class="p-2 text-center">
              <?php
                if ($metaReceita > 0 && isset($metasArray[$catNomeOR][''])) {
                  echo number_format(($metasArray[$catNomeOR][''] / $metaReceita) * 100, 2, ',', '.') . '%';
                } else { echo '-'; }
              ?>
          </td>
          <td class="p-2 text-right"><?= 'R$ '.number_format($totalAtualCatRNO,2,',','.') ?></td>
          <td class="p-2 text-center">
               <?php
                $meta_rno_l1_val = $metasArray[$catNomeOR][''] ?? null;
                if(isset($meta_rno_l1_val) && $meta_rno_l1_val != 0) {
                    echo number_format(($totalAtualCatRNO / $meta_rno_l1_val) * 100, 2, ',', '.') . '%';
                } else { echo '-';}
               ?>
          </td>
          <td class="p-2 text-center">
              <?php
                if(isset($meta_rno_l1_val)) {
                    $comparacaoRNO_L1 = $totalAtualCatRNO - $meta_rno_l1_val;
                    $corCompRNO_L1 = ($comparacaoRNO_L1 >=0) ? 'text-green-400' : 'text-red-400';
                    echo '<span class="'.$corCompRNO_L1.'">R$ '.number_format($comparacaoRNO_L1, 2, ',', '.').'</span>';
                } else { echo '-';}
              ?>
          </td>
        </tr>

        <?php
          // Garantir que todas as subcategorias com meta apareçam
          $subsParaExibirRNO = $subcategoriasOR; // Começa com as que têm valor atual
          if (isset($metasArray[$catNomeOR])) {
              foreach ($metasArray[$catNomeOR] as $metaSubKeyRNO => $metaValorRNO) {
                  if ($metaSubKeyRNO !== '' && !isset($subsParaExibirRNO[$metaSubKeyRNO])) {
                      $subsParaExibirRNO[$metaSubKeyRNO] = 0; // Adiciona sub com valor 0 se só tiver meta
                  }
              }
          }
          ksort($subsParaExibirRNO);
        ?>
        <?php foreach ($subsParaExibirRNO as $subNomeOR => $valorAtualSubOR): ?>
          <?php
            $atualSubOR = $outrasReceitasPorCatSubMesAtual[$catNomeOR][$subNomeOR] ?? 0;
          ?>
          <tr class="dre-subcat-l2">
            <td class="p-2 pl-10 text-left"><?= htmlspecialchars($subNomeOR) ?></td>
            <td class="p-2 text-right"><?= isset($metasArray[$catNomeOR][$subNomeOR]) ? 'R$ '.number_format($metasArray[$catNomeOR][$subNomeOR],2,',','.') : '-' ?></td>
            <td class="p-2 text-center">
              <?php
                if ($metaReceita > 0 && isset($metasArray[$catNomeOR][$subNomeOR])) {
                  echo number_format(($metasArray[$catNomeOR][$subNomeOR] / $metaReceita) * 100, 2, ',', '.') . '%';
                } else { echo '-'; }
              ?>
            </td>
            <td class="p-2 text-right"><?= 'R$ '.number_format($atualSubOR,2,',','.') ?></td>
            <td class="p-2 text-center">
              <?php
                $meta_or_val = $metasArray[$catNomeOR][$subNomeOR] ?? null;
                if (isset($meta_or_val) && $meta_or_val != 0) {
                  echo number_format(($atualSubOR / $meta_or_val) * 100, 2, ',', '.') . '%';
                } else { echo '-'; }
              ?>
            </td>
            <td class="p-2 text-center">
              <?php
                if(isset($metasArray[$catNomeOR][$subNomeOR])) {
                    $comparacaoRNO_L2 = $atualSubOR - ($metasArray[$catNomeOR][$subNomeOR] ?? 0);
                    $corCompRNO_L2 = ($comparacaoRNO_L2 >=0) ? 'text-green-400' : 'text-red-400';
                    echo '<span class="'.$corCompRNO_L2.'">R$ '.number_format($comparacaoRNO_L2, 2, ',', '.').'</span>';
                } else { echo '-';}
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- INVESTIMENTO INTERNO, EXTERNO, AMORTIZAÇÃO, Z - SAIDA DE REPASSE, Z - ENTRADA DE REPASSE -->
    <?php
      foreach(['INVESTIMENTO INTERNO','INVESTIMENTO EXTERNO','AMORTIZAÇÃO', 'Z - SAIDA DE REPASSE', 'Z - ENTRADA DE REPASSE'] as $catName):
        if(isset($matrizOrdenada[$catName]) || isset($metasArray[$catName])):
        $catKey = strtolower(str_replace([' ', '-'], '_', $catName));
    ?>
      <tr class="dre-cat" onclick="toggleCategory('<?= $catKey ?>')">
        <td class="p-2 text-left">
          <span class="toggle-icon" id="icon_<?= $catKey ?>">▶</span>
          <?= $catName ?>
        </td>
        <td class="p-2 text-right"><?= isset($metasArray[$catName]['']) ? 'R$ '.number_format($metasArray[$catName][''],2,',','.') : '-' ?></td>
        <td class="p-2 text-center"><?= ($metaReceita > 0 && isset($metasArray[$catName][''])) ? number_format(($metasArray[$catName]['']/$metaReceita)*100,2,',','.') .'%' : '-' ?></td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($atualCat[$catName] ?? 0,2,',','.') ?></td>
        <td class="p-2 text-center">
          <?php
            $meta_inv_val = $metasArray[$catName][''] ?? null;
            $realizado_inv_val = $atualCat[$catName] ?? 0;
            if (isset($meta_inv_val) && $meta_inv_val != 0) {
              echo number_format(($realizado_inv_val / $meta_inv_val) * 100, 2, ',', '.') . '%';
            } else { echo '-'; }
          ?>
        </td>
        <td class="p-2 text-center">
          <?php 
            $meta_val = $metasArray[$catName][''] ?? null;
            $realizado_val = $atualCat[$catName] ?? 0;
            if (isset($meta_val)) {
              $comparacao = $meta_val - $realizado_val;
              $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
              echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
            } else { echo '-'; }
          ?>
        </td>
      </tr>
      <?php if ($catName === 'Z - ENTRADA DE REPASSE' || $catName === 'RECEITAS NAO OPERACIONAIS'): ?>
        <?php
          // Buscar subcategorias e valores de fOutrasReceitas para Z - ENTRADA DE REPASSE ou RECEITAS NAO OPERACIONAIS
          $subcategoriasRepasse = [];
          $sqlSubRepasse = "SELECT SUBCATEGORIA, SUM(VALOR) as TOTAL FROM fOutrasReceitas WHERE CATEGORIA = '" . $conn->real_escape_string($catName) . "' AND YEAR(DATA_COMPETENCIA) = $anoAtual AND MONTH(DATA_COMPETENCIA) = $mesAtual GROUP BY SUBCATEGORIA ORDER BY SUBCATEGORIA";
          $resSubRepasse = $conn->query($sqlSubRepasse);
          if ($resSubRepasse) {
            while ($rowSub = $resSubRepasse->fetch_assoc()) {
              $sub = $rowSub['SUBCATEGORIA'] ?: 'NÃO ESPECIFICADO';
              $valor = floatval($rowSub['TOTAL']);
              $subcategoriasRepasse[] = ['sub' => $sub, 'valor' => $valor];
            }
          }
        ?>
        <?php foreach($subcategoriasRepasse as $item): ?>
          <tr class="dre-sub <?= $catKey ?> dre-hide">
            <td class="p-2 text-left" style="padding-left:2em;">
              <?= htmlspecialchars($item['sub']) ?>
            </td>
            <td class="p-2 text-right">-</td>
            <td class="p-2 text-center">-</td>
            <td class="p-2 text-right">R$ <?= number_format($item['valor'],2,',','.') ?></td>
            <td class="p-2 text-center">-</td>
            <td class="p-2 text-center">-</td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <?php foreach(($matrizOrdenada[$catName] ?? []) as $sub => $descricoes): ?>
          <?php if($sub): ?>
            <tr class="dre-sub <?= $catKey ?> dre-hide" onclick="toggleSubcategory('<?= $catKey ?>_<?= md5($sub) ?>')">
              <td class="p-2 text-left" style="padding-left:2em;">
                <span class="toggle-icon" id="icon_<?= $catKey ?>_<?= md5($sub) ?>">▶</span>
                <?= htmlspecialchars($sub) ?>
              </td>
              <td class="p-2 text-right"><?= isset($metasArray[$catName][$sub]) ? 'R$ ' . number_format($metasArray[$catName][$sub], 2, ',', '.') : '-' ?></td>
              <td class="p-2 text-center"><?= ($metaReceita > 0 && isset($metasArray[$catName][$sub])) ? number_format(($metasArray[$catName][$sub] / $metaReceita) * 100, 2, ',', '.') . '%' : '-' ?></td>
              <td class="p-2 text-right"><?= 'R$ ' . number_format($atualSub[$catName][$sub] ?? 0, 2, ',', '.') ?></td>
              <td class="p-2 text-center">
                <?php
                  $meta_invs_val = $metasArray[$catName][$sub] ?? null;
                  $realizado_invs_val = $atualSub[$catName][$sub] ?? 0;
                  if (isset($meta_invs_val) && $meta_invs_val != 0) {
                    echo number_format(($realizado_invs_val / $meta_invs_val) * 100, 2, ',', '.') . '%';
                  } else { echo '-'; }
                ?>
              </td>
              <td class="p-2 text-center">
                <?php 
                  $meta_val = $metasArray[$catName][$sub] ?? null;
                  $realizado_val = $atualSub[$catName][$sub] ?? 0;
                  if (isset($meta_val)) {
                    $comparacao = $meta_val - $realizado_val;
                    $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
                    echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
                  } else { echo '-'; }
                ?>
              </td>
            </tr>

            <?php foreach($descricoes as $desc => $contas): ?>
              <tr class="dre-desc <?= $catKey ?>_<?= md5($sub) ?> dre-hide" onclick="toggleDescription('<?= $catKey ?>_<?= md5($sub . $desc) ?>')">
                <td class="p-2 text-left" style="padding-left:3em;">
                  <span class="toggle-icon" id="icon_<?= $catKey ?>_<?= md5($sub . $desc) ?>">▶</span>
                  <?= htmlspecialchars($desc) ?>
                </td>
                <td class="p-2 text-right">-</td>
                <td class="p-2 text-center">-</td>
                <td class="p-2 text-right"><?= 'R$ '.number_format($atualDesc[$catName][$sub][$desc] ?? 0,2,',','.') ?></td>
                <td class="p-2 text-center">-</td>
                <td class="p-2 text-center">-</td>
              </tr>

              <?php foreach($contas as $idConta => $parcelas): ?>
                <?php foreach($parcelas as $parcela): ?>
                  <tr class="dre-parcela <?= $catKey ?>_<?= md5($sub . $desc) ?> dre-hide">
                    <td class="p-2 text-left" style="padding-left:4em;">
                      <span class="text-gray-400">Parcela:</span> <?= htmlspecialchars($parcela['parcela']) ?>
                      <span class="text-xs text-gray-500 ml-2">(ID: <?= $idConta ?>)</span>
                    </td>
                    <td class="p-2 text-right">-</td>
                    <td class="p-2 text-center">-</td>
                    <td class="p-2 text-right"><?= 'R$ '.number_format($parcela['valor'],2,',','.') ?></td>
                    <td class="p-2 text-center">-</td>
                    <td class="p-2 text-center">-</td>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            <?php endforeach; ?>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php endif; ?>
  <?php endforeach; ?>

    <!-- SALDO REPASSE (ENTRADA - SAIDA) -->
    <?php
      $metaSaldoRepasse = ($metasArray['Z - ENTRADA DE REPASSE'][''] ?? 0) - ($metasArray['Z - SAIDA DE REPASSE'][''] ?? 0);
      $realizadoSaldoRepasse = ($atualCat['Z - ENTRADA DE REPASSE'] ?? 0) - ($atualCat['Z - SAIDA DE REPASSE'] ?? 0);
    ?>
    <tr class="dre-cat" style="background:#3b3b3b; color: #ffe066; font-weight: bold;">
      <td class="p-2 text-left">SALDO REPASSE</td>
      <td class="p-2 text-right">R$ <?= number_format($metaSaldoRepasse,2,',','.') ?></td>
      <td class="p-2 text-center">
        <?php
          if ($metaReceita > 0) {
            echo number_format(($metaSaldoRepasse / $metaReceita) * 100, 2, ',', '.') . '%';
          } else { echo '-'; }
        ?>
      </td>
      <td class="p-2 text-right">R$ <?= number_format($realizadoSaldoRepasse,2,',','.') ?></td>
      <td class="p-2 text-center">
        <?php
          if ($metaSaldoRepasse != 0) {
            echo number_format(($realizadoSaldoRepasse / $metaSaldoRepasse) * 100, 2, ',', '.') . '%';
          } else { echo '-'; }
        ?>
      </td>
      <td class="p-2 text-center">
        <?php
          $comparacaoSaldo = $realizadoSaldoRepasse - $metaSaldoRepasse;
          $corComparacaoSaldo = ($comparacaoSaldo >= 0) ? 'text-green-400' : 'text-red-400';
          echo '<span class="' . $corComparacaoSaldo . '">R$ ' . number_format($comparacaoSaldo, 2, ',', '.') . '</span>';
        ?>
      </td>
    </tr>

    <!-- FLUXO DE CAIXA -->
    <tr id="rowFluxoCaixa" class="dre-cat" style="background:#082f49; color: #e0f2fe; font-weight: bold;">
      <td class="p-2 text-left">FLUXO DE CAIXA</td>
      <td class="p-2 text-right"><?= (isset($metasArray[$keyFluxoCaixa]['']) || isset($metaReceita)) ? 'R$ '.number_format($metaFluxoCaixaDisplay,2,',','.') : '-' ?></td>
      <td class="p-2 text-center">
          <?php
            if ($metaReceita > 0 && (isset($metasArray[$keyFluxoCaixa]['']) || isset($metaReceita)) ) {
              echo number_format(($metaFluxoCaixaDisplay / $metaReceita) * 100, 2, ',', '.') . '%';
            } else { echo '-'; }
          ?>
      </td>
      <td class="p-2 text-right"><?= 'R$ '.number_format($atualFluxoCaixa,2,',','.') ?></td>
      <td class="p-2 text-center">
          <?php
              if(isset($metaFluxoCaixaDisplay) && $metaFluxoCaixaDisplay != 0 && (isset($metasArray[$keyFluxoCaixa]['']) || isset($metaReceita))) {
                  echo number_format(($atualFluxoCaixa / $metaFluxoCaixaDisplay) * 100, 2, ',', '.') . '%';
              } else { echo '-';}
          ?>
      </td> 
      <td class="p-2 text-center">
        <?php 
          if (isset($metaFluxoCaixaDisplay) && (isset($metasArray[$keyFluxoCaixa]['']) || isset($metaReceita))) {
            $comparacao = $atualFluxoCaixa - $metaFluxoCaixaDisplay;
            $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
            echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
          } else { echo '-'; }
        ?>
      </td>
    </tr>
    </tbody>
  </table>


</main>
</body>
</html>

<script>
function initializeDREToggle() {
    // Inicializa todos os elementos como fechados (escondidos)
    document.querySelectorAll('tr.dre-sub, tr.dre-desc, tr.dre-parcela, tr.dre-subcat-l1, tr.dre-subcat-l2').forEach(subRow => {
       subRow.classList.add('dre-hide');
    });
}

// Função para alternar categorias
function toggleCategory(categoryKey) {
    const categoryElements = document.querySelectorAll('.' + categoryKey);
    const categoryIcon = document.getElementById('icon_' + categoryKey);
    
    // Verifica se a categoria está fechada (ícone ▶) ou aberta (ícone ▼)
    const isOpening = categoryIcon && categoryIcon.textContent === '▶';
    
    // Toggle das subcategorias diretas
    categoryElements.forEach(element => {
        element.classList.toggle('dre-hide');
    });
    
    if (!isOpening) {
        // Se estamos fechando a categoria (estava ▼, vai para ▶)
        // Fechar todas as subcategorias e descrições
        
        // Encontrar todas as subcategorias desta categoria (que começam com categoryKey_)
        const subcategoryPrefix = categoryKey + '_';
        const allSubElements = document.querySelectorAll('[class*="' + subcategoryPrefix + '"]');
        
        allSubElements.forEach(element => {
            if (element.classList.contains('dre-desc') || element.classList.contains('dre-parcela')) {
                element.classList.add('dre-hide');
            }
        });
        
        // Resetar todos os ícones das subcategorias e descrições
        document.querySelectorAll('[id^="icon_' + subcategoryPrefix + '"]').forEach(icon => {
            icon.textContent = '▶';
        });
    }
    
    // Alterar ícone da categoria
    if (categoryIcon) {
        if (categoryIcon.textContent === '▼') {
            categoryIcon.textContent = '▶';
        } else {
            categoryIcon.textContent = '▼';
        }
    }
    
    // Parar propagação do evento
    event.stopPropagation();
}

// Função para alternar subcategorias
function toggleSubcategory(subcategoryKey) {
    const subcategoryElements = document.querySelectorAll('.' + subcategoryKey);
    const subcategoryIcon = document.getElementById('icon_' + subcategoryKey);
    
    // Verifica se a subcategoria está fechada (ícone ▶) ou aberta (ícone ▼)
    const isOpening = subcategoryIcon && subcategoryIcon.textContent === '▶';
    
    // Toggle das descrições diretas desta subcategoria
    subcategoryElements.forEach(element => {
        element.classList.toggle('dre-hide');
    });
    
    if (!isOpening) {
        // Se estamos fechando a subcategoria (estava ▼, vai para ▶)
        // Fechar todas as descrições e parcelas
        
        // Encontrar todas as descrições desta subcategoria (que começam com subcategoryKey_)
        const descriptionPrefix = subcategoryKey + '_';
        const allDescElements = document.querySelectorAll('[class*="' + descriptionPrefix + '"]');
        
        allDescElements.forEach(element => {
            if (element.classList.contains('dre-desc') || element.classList.contains('dre-parcela')) {
                element.classList.add('dre-hide');
            }
        });
        
        // Resetar todos os ícones das descrições
        document.querySelectorAll('[id^="icon_' + descriptionPrefix + '"]').forEach(icon => {
            icon.textContent = '▶';
        });
    }
    
    // Alterar ícone da subcategoria
    if (subcategoryIcon) {
        if (subcategoryIcon.textContent === '▼') {
            subcategoryIcon.textContent = '▶';
        } else {
            subcategoryIcon.textContent = '▼';
        }
    }
    
    // Parar propagação do evento
    event.stopPropagation();
}

// Função para alternar descrições
function toggleDescription(descriptionKey) {
    const elements = document.querySelectorAll('.' + descriptionKey);
    const icon = document.getElementById('icon_' + descriptionKey);
    
    elements.forEach(element => {
        element.classList.toggle('dre-hide');
    });
    
    // Alterar ícone
    if (icon.textContent === '▼') {
        icon.textContent = '▶';
    } else {
        icon.textContent = '▼';
    }
    
    // Parar propagação do evento
    event.stopPropagation();
}

document.addEventListener('DOMContentLoaded', function() {
  initializeDREToggle();
  console.log('Página DRE carregada com hierarquia completa: categoria > subcategoria > descrição > parcela');
});

function filtrarDescricaoConta() {
  const termo = document.getElementById('dre-search').value.trim().toLowerCase();
  const linhas = Array.from(document.querySelectorAll('#tabelaAcompanhamento tbody tr'));

  if (!termo) {
    linhas.forEach(linha => {
      linha.style.display = '';
      if (
        linha.classList.contains('dre-sub') ||
        linha.classList.contains('dre-desc') ||
        linha.classList.contains('dre-parcela') ||
        linha.classList.contains('dre-subcat-l1') ||
        linha.classList.contains('dre-subcat-l2')
      ) {
        linha.classList.add('dre-hide');
      } else {
        linha.classList.remove('dre-hide');
      }
    });
    return;
  }

  linhas.forEach(linha => {
    linha.style.display = 'none';
    linha.classList.add('dre-hide');
  });

  linhas.forEach((linha, idx) => {
    if (linha.classList.contains('dre-desc') || linha.classList.contains('dre-parcela')) {
      const texto = linha.textContent.toLowerCase();
      if (texto.includes(termo)) {
        linha.style.display = '';
        linha.classList.remove('dre-hide');
        let i = idx - 1;
        while (i >= 0) {
          const pai = linhas[i];
          if (
            pai.classList.contains('dre-cat') ||
            pai.classList.contains('dre-sub') ||
            pai.classList.contains('dre-subcat-l1') ||
            pai.classList.contains('dre-subcat-l2')
          ) {
            pai.style.display = '';
            pai.classList.remove('dre-hide');
          }
          if (pai.classList.contains('dre-cat')) break;
          i--;
        }
      }
    }
  });

  linhas.forEach((linha, idx) => {
    if (
      linha.classList.contains('dre-sub') ||
      linha.classList.contains('dre-subcat-l1') ||
      linha.classList.contains('dre-subcat-l2')
    ) {
      let temFilhoVisivel = false;
      for (let j = idx + 1; j < linhas.length; j++) {
        const filho = linhas[j];
        if (
          filho.classList.contains('dre-cat') ||
          filho.classList.contains('dre-sub') ||
          filho.classList.contains('dre-subcat-l1') ||
          filho.classList.contains('dre-subcat-l2')
        ) break;
        if (filho.style.display !== 'none') {
          temFilhoVisivel = true;
          break;
        }
      }
      if (!temFilhoVisivel) {
        linha.style.display = 'none';
        linha.classList.add('dre-hide');
      }
    }
  });

  linhas.forEach((linha, idx) => {
    if (linha.classList.contains('dre-cat')) {
      let temFilhoVisivel = false;
      for (let j = idx + 1; j < linhas.length; j++) {
        const filho = linhas[j];
        if (filho.classList.contains('dre-cat')) break;
        if (filho.style.display !== 'none') {
          temFilhoVisivel = true;
          break;
        }
      }
      if (!temFilhoVisivel) {
        linha.style.display = 'none';
        linha.classList.add('dre-hide');
      }
    }
  });
}
</script>

<!-- Script para o gráfico de fluxo de caixa -->
<script>
  const ctx = document.getElementById('fluxoCaixaChart').getContext('2d');
  const fluxoCaixaChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= $jsonChartLabels ?>,
      datasets: [{
        label: 'Fluxo de Caixa',
        data: <?= $jsonChartData ?>,
        backgroundColor: 'rgba(234, 179, 8, 0.7)',
        borderColor: 'rgba(234, 179, 8, 1)',
        borderWidth: 2,
        fill: true,
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          position: 'top',
        },
        tooltip: {
          callbacks: {
            label: function(tooltipItem) {
              let label = tooltipItem.dataset.label || '';
              if (label) {
                label += ': ';
              }
              label += 'R$ ' + tooltipItem.raw.toFixed(2).replace('.', ',');
              return label;
            }
      },
      scales: {
        x: {
          title: {
            display: true,
            text: 'Meses',
            color: '#ffffff',
            font: {
              size: 14,
              weight: 'bold'
            }
          },
          ticks: {
            color: '#ffffff',
            font: {
              size: 12
            }
          },
          grid: {
            color: 'rgba(255, 255, 255, 0.1)'
          }
        },
        y: {
          title: {
            display: true,
            text: 'Valor (R$)',
            color: '#ffffff',
            font: {
              size: 14,
              weight: 'bold'
            }
          },
          ticks: {
            color: '#ffffff',
            font: {
              size: 12
            },
            callback: function(value) {
              return 'R$ ' + value;
            }
          },
          grid: {
            color: 'rgba(255, 255, 255, 0.1)'
          }
        }
      }
    }
  });
</script>
</body>
</html>