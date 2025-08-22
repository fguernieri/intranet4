<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==================== [ ADIÇÃO 1: TRATAMENTO POST ] ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Iniciar sessão para verificar autenticação
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// ==================== [ NOVO: CONEXÃO PARA fcontasapagartap ] ====================
require_once __DIR__ . '/db_config_financeiro.php';
$connFinanceiro = pg_connect(
    "host=" . DB_RELATORIO_HOST .
    " port=" . DB_RELATORIO_PORT .
    " dbname=" . DB_RELATORIO_NAME .
    " user=" . DB_RELATORIO_USER .
    " password=" . DB_RELATORIO_PASS
);

if (!$connFinanceiro) {
    die("Conexão financeiro falhou: " . pg_last_error());
}



    $jsonData = json_decode(file_get_contents('php://input'), true);
    
    // Verificar se o JSON foi decodificado corretamente
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['sucesso' => false, 'erro' => 'Erro ao decodificar JSON: ' . json_last_error_msg()]);
        exit;
    }
    
    if (empty($jsonData)) {
        echo json_encode(['sucesso' => false, 'erro' => 'Dados JSON vazios ou inválidos']);
        exit;
    }
    
    // Verificar se é uma requisição de metas
    if (isset($jsonData['metas'])) {
        $metas = $jsonData['metas'];
        $dataMeta = $jsonData['data'] ?? date('Y-m-d');

        if (empty($metas)) {
            echo json_encode(['sucesso' => false, 'erro' => 'Dados ausentes (metas vazias).']);
            exit;
        }

        // Exclui previamente as metas da data escolhida (para sobrescrever)
        $stmtDel = $connPost->prepare("DELETE FROM fMetasTap WHERE Data = ?");
        $stmtDel->bind_param("s", $dataMeta);
        $stmtDel->execute();
        $stmtDel->close();

        $stmt = $connPost->prepare("INSERT INTO fMetasTap (Categoria, Subcategoria, Meta, Percentual, Data) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            echo json_encode(['sucesso' => false, 'erro' => $connPost->error]);
            exit;
        }
        foreach ($metas as $m) {
            $cat  = $m['categoria']    ?? '';
            $sub  = $m['subcategoria'] ?? '';
            $meta = floatval($m['valor']) ?? 0;
            $percentual = floatval($m['percentual']) ?? 0;
            $stmt->bind_param("ssdds", $cat, $sub, $meta, $percentual, $dataMeta);
            $stmt->execute();
        }
        $stmt->close();
        $connPost->close();

        echo json_encode(['sucesso' => true]);
        exit;
    }
    
    // Verificar se é uma requisição de simulação
    if (isset($jsonData['simulacao'])) {
        if (empty($_SESSION['usuario_id'])) {
            echo json_encode(['sucesso' => false, 'erro' => 'Usuário não autenticado.']);
            exit;
        }
        
        $nomeSimulacao = $jsonData['nomeSimulacao'] ?? '';
        $simulacaoData = $jsonData['simulacao'] ?? [];
        $usuarioID = $_SESSION['usuario_id'];
        
        if (empty($nomeSimulacao) || empty($simulacaoData)) {
            echo json_encode(['sucesso' => false, 'erro' => 'Nome da simulação ou dados ausentes.']);
            exit;
        }

        try {
            // Verificar se a tabela existe
            $tableCheck = $connPost->query("SHOW TABLES LIKE 'fSimulacoesFabrica'");
            if ($tableCheck->num_rows == 0) {
                // Criar tabela se não existir
                $createTable = "
                CREATE TABLE `fSimulacoesTap` (
                    `ID` int(11) NOT NULL AUTO_INCREMENT,
                    `NomeSimulacao` varchar(255) NOT NULL,
                    `UsuarioID` int(11) NOT NULL,
                    `Categoria` varchar(255) NOT NULL,
                    `Subcategoria` varchar(255) DEFAULT NULL,
                    `SubSubcategoria` varchar(255) DEFAULT NULL,
                    `ValorSimulacao` decimal(15,2) DEFAULT 0.00,
                    `PercentualSimulacao` decimal(8,4) DEFAULT 0.0000,
                    `DataCriacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `Ativo` tinyint(1) DEFAULT 1,
                    PRIMARY KEY (`ID`),
                    KEY `idx_usuario_nome` (`UsuarioID`, `NomeSimulacao`),
                    KEY `idx_categoria` (`Categoria`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ";
                
                if (!$connPost->query($createTable)) {
                    throw new Exception("Erro ao criar tabela fSimulacoesTap: " . $connPost->error);
                }
            }

            // Iniciar transação para garantir atomicidade
            $connPost->autocommit(FALSE);
            
            // Exclui previamente a simulação do usuário com o mesmo nome
            $stmtDel = $connPost->prepare("DELETE FROM fSimulacoesTap WHERE NomeSimulacao = ? AND UsuarioID = ?");
            if (!$stmtDel) {
                throw new Exception("Erro ao preparar DELETE: " . $connPost->error);
            }
            $stmtDel->bind_param("si", $nomeSimulacao, $usuarioID);
            if (!$stmtDel->execute()) {
                throw new Exception("Erro ao executar DELETE: " . $stmtDel->error);
            }
            $registrosExcluidos = $stmtDel->affected_rows;
            $stmtDel->close();

            $stmt = $connPost->prepare("INSERT INTO fSimulacoesTap (NomeSimulacao, UsuarioID, Categoria, Subcategoria, SubSubcategoria, ValorSimulacao, PercentualSimulacao, DataCriacao, Ativo) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1)");
            if (!$stmt) {
                throw new Exception("Erro ao preparar INSERT: " . $connPost->error);
            }
            
            $insertCount = 0;
            foreach ($simulacaoData as $key => $valor) {
                if (empty($valor) || $valor === '0' || $valor === '0,00') {
                    continue; // Pula valores vazios ou zero
                }
                
                $categoria = '';
                $subcategoria = null;
                $subSubcategoria = null;
                $valorSimulacao = 0;
                $percentualSimulacao = 0;
                $isPercentual = false;
                
                // Verificar se é percentual
                if (strpos($key, '_perc') !== false) {
                    $isPercentual = true;
                    $key = str_replace('_perc', '', $key); // Remove o sufixo para fazer o parse
                }
                
                // Parse do key para extrair categoria/subcategoria
                if (strpos($key, 'RNO_') === 0) {
                    // Receitas Não Operacionais
                    $categoria = 'RECEITAS NAO OPERACIONAIS';
                    $parts = explode('___', substr($key, 4));
                    if (count($parts) >= 2) {
                        $subcategoria = trim($parts[0]);
                        $subSubcategoria = trim($parts[1]);
                    }
                } elseif (strpos($key, 'ER_') === 0) {
                    // Entrada de Repasse
                    $categoria = 'ENTRADA DE REPASSE';
                    $parts = explode('___', substr($key, 3));
                    if (count($parts) >= 2) {
                        $subcategoria = trim($parts[0]);
                        $subSubcategoria = trim($parts[1]);
                    }
                } elseif (strpos($key, '___') !== false) {
                    // Categoria com subcategoria
                    $parts = explode('___', $key);
                    $categoria = trim($parts[0]);
                    $subcategoria = trim($parts[1]);
                } else {
                    // Categoria principal
                    $categoria = trim($key);
                }
                
                // Converter valor
                $valorLimpo = str_replace(['.', ','], ['', '.'], $valor);
                $valorLimpo = preg_replace('/[^0-9.-]/', '', $valorLimpo); // Remove caracteres não numéricos
                
                if ($isPercentual) {
                    $percentualSimulacao = floatval($valorLimpo);
                } else {
                    $valorSimulacao = floatval($valorLimpo);
                }
                
                $stmt->bind_param("sisssdd", $nomeSimulacao, $usuarioID, $categoria, $subcategoria, $subSubcategoria, $valorSimulacao, $percentualSimulacao);
                if (!$stmt->execute()) {
                    throw new Exception("Erro ao executar INSERT para $key: " . $stmt->error);
                }
                $insertCount++;
            }
            
            $stmt->close();
            
            // Commit da transação se tudo deu certo
            $connPost->commit();
            $connPost->autocommit(TRUE);
            $connPost->close();

            if ($insertCount > 0) {
                echo json_encode(['sucesso' => true, 'mensagem' => "Simulação '$nomeSimulacao' salva com sucesso! ($insertCount registros inseridos, $registrosExcluidos registros antigos removidos)"]);
            } else {
                echo json_encode(['sucesso' => false, 'erro' => 'Nenhum dado válido encontrado para salvar.']);
            }
        } catch (Exception $e) {
            // Rollback em caso de erro
            if (isset($connPost)) {
                $connPost->rollback();
                $connPost->autocommit(TRUE);
                $connPost->close();
            }
            echo json_encode(['sucesso' => false, 'erro' => 'Erro interno: ' . $e->getMessage()]);
        }
        exit;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['usuario_id'])) {
        echo json_encode(['sucesso' => false, 'erro' => 'Usuário não autenticado.']);
        exit;
    }
    
    require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
    
    if ($conn->connect_error) {
        echo json_encode(['sucesso' => false, 'erro' => "Conexão falhou: " . $conn->connect_error]);
        exit;
    }
    
    $usuarioID = $_SESSION['usuario_id'];
    
    // Listar simulações
    if ($_GET['action'] === 'listar_simulacoes') {
        // Verificar se a tabela existe
        $tableCheck = $conn->query("SHOW TABLES LIKE 'fSimulacoesTap'");
        if ($tableCheck->num_rows == 0) {
            echo json_encode(['sucesso' => true, 'simulacoes' => []]);
            exit;
        }
        
        $stmt = $conn->prepare("SELECT DISTINCT NomeSimulacao, MAX(DataCriacao) as DataCriacao FROM fSimulacoesTap WHERE UsuarioID = ? AND Ativo = 1 GROUP BY NomeSimulacao ORDER BY DataCriacao DESC");
        $stmt->bind_param("i", $usuarioID);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $simulacoes = [];
        while ($row = $result->fetch_assoc()) {
            $simulacoes[] = [
                'nome' => $row['NomeSimulacao'],
                'data' => $row['DataCriacao']
            ];
        }
        
        $stmt->close();
        $conn->close();
        
        echo json_encode(['sucesso' => true, 'simulacoes' => $simulacoes]);
        exit;
    }
    
    // Carregar simulação específica
    if ($_GET['action'] === 'carregar_simulacao' && isset($_GET['nome'])) {
        $nomeSimulacao = $_GET['nome'];
        
        $stmt = $conn->prepare("SELECT Categoria, Subcategoria, SubSubcategoria, ValorSimulacao, PercentualSimulacao FROM fSimulacoesTap WHERE UsuarioID = ? AND NomeSimulacao = ? AND Ativo = 1");
        $stmt->bind_param("is", $usuarioID, $nomeSimulacao);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $simulacaoData = [];
        while ($row = $result->fetch_assoc()) {
            $key = '';
            if ($row['Categoria'] === 'RECEITAS NAO OPERACIONAIS' && !empty($row['Subcategoria']) && !empty($row['SubSubcategoria'])) {
                $key = 'RNO_' . $row['Subcategoria'] . '___' . $row['SubSubcategoria'];
            } elseif ($row['Categoria'] === 'ENTRADA DE REPASSE' && !empty($row['Subcategoria']) && !empty($row['SubSubcategoria'])) {
                $key = 'ER_' . $row['Subcategoria'] . '___' . $row['SubSubcategoria'];
            } elseif (!empty($row['Subcategoria'])) {
                $key = $row['Categoria'] . '___' . $row['Subcategoria'];
            } else {
                $key = $row['Categoria'];
            }
            
            if ($row['ValorSimulacao'] > 0) {
                $simulacaoData[$key] = number_format($row['ValorSimulacao'], 2, ',', '.');
            }
            if ($row['PercentualSimulacao'] > 0) {
                $simulacaoData[$key . '_perc'] = number_format($row['PercentualSimulacao'], 2, ',', '.');
            }
        }
        
        $stmt->close();
        $conn->close();
        
        echo json_encode(['sucesso' => true, 'simulacao' => $simulacaoData]);
        exit;
    }
    
    // Excluir simulação
    if ($_GET['action'] === 'excluir_simulacao' && isset($_GET['nome'])) {
        $nomeSimulacao = $_GET['nome'];
        
        $stmt = $conn->prepare("UPDATE fSimulacoesTap  SET Ativo = 0 WHERE UsuarioID = ? AND NomeSimulacao = ?");
        $stmt->bind_param("is", $usuarioID, $nomeSimulacao);
        $sucesso = $stmt->execute();
        
        $stmt->close();
        $conn->close();
        
        echo json_encode(['sucesso' => $sucesso]);
        exit;
    }
}
// ==================== [ FIM DO TRATAMENTO GET ] ====================

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

if (!isset($_SESSION['usuario_perfil']) || !in_array($_SESSION['usuario_perfil'], ['admin', 'supervisor'])) {
    echo "Acesso restrito.";
    exit;
}

// Conexão com o banco
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

require_once __DIR__ . '/db_config_financeiro.php';
$connFinanceiro = pg_connect(
    "host=" . DB_RELATORIO_HOST .
    " port=" . DB_RELATORIO_PORT .
    " dbname=" . DB_RELATORIO_NAME .
    " user=" . DB_RELATORIO_USER .
    " password=" . DB_RELATORIO_PASS
);
if (!$connFinanceiro) {
    die("Conexão financeiro falhou: " . pg_last_error());
}

// Defina o ano e mês atual (ou pegue do GET)
$anoAtual = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
$mesAtual = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');

// Parâmetro de ordenação das subcategorias (mantido para compatibilidade, mas não usado)
$ordenacao = isset($_GET['ordenacao']) ? $_GET['ordenacao'] : 'nome';
if (!in_array($ordenacao, ['nome', 'percentual', 'valor'])) {
    $ordenacao = 'nome';
}

// Carregue todas as parcelas do ano para médias e totais
$sql = "
    SELECT 
        c.ID_CONTA,
        c.CATEGORIA,
        c.SUBCATEGORIA,
        c.DESCRICAO_CONTA,
        d.PARCELA,
        d.VALOR,
        d.DATA_PAGAMENTO,
        c.STATUS
    FROM fcontasapagartap AS c
    INNER JOIN fcontasapagardetalhestap AS d ON c.ID_CONTA = d.ID_CONTA
    WHERE YEAR(d.DATA_PAGAMENTO) = $anoAtual
";
$res = pg_query($connFinanceiro, $sql); // <-- ALTERADO para usar $connFinanceiro
$linhas = [];
while ($f = pg_fetch_assoc($res)) {
    $linhas[] = [
        'ID_CONTA'        => $f['ID_CONTA'],
        'CATEGORIA'       => $f['CATEGORIA'],
        'SUBCATEGORIA'    => $f['SUBCATEGORIA'],
        'DESCRICAO_CONTA' => $f['DESCRICAO_CONTA'],
        'PARCELA'         => $f['PARCELA'],
        'VALOR_EXIBIDO'   => $f['VALOR'],
        'DATA_PAGAMENTO' => $f['DATA_PAGAMENTO'],
        'STATUS'          => $f['STATUS'],
    ];
}

// Organiza em matriz por categoria, subcategoria e mês (nível de descrição removido)
$meses = [
    1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
    7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'
];
$matriz = [];
foreach ($linhas as $linha) {
    $cat = $linha['CATEGORIA'] ?? 'SEM CATEGORIA';
    $sub = $linha['SUBCATEGORIA'] ?? 'SEM SUBCATEGORIA';
    $mes = (int) date('n', strtotime($linha['DATA_PAGAMENTO']));
    $id  = $linha['ID_CONTA'];
    $matriz[$cat][$sub][$mes][$id][] = floatval($linha['VALOR_EXIBIDO']);
}

// ==================== [CARREGAR DADOS DE fOutrasReceitas PARA A MATRIZ PRINCIPAL] ====================
// IMPORTANTE: Fazer ANTES do cálculo das médias para que "Z - ENTRADA DE REPASSE" seja considerada
$sqlOutrasRec = "
    SELECT CATEGORIA, SUBCATEGORIA, DATA_COMPETENCIA, SUM(VALOR) AS TOTAL
    FROM foutrasreceitastap
    WHERE YEAR(DATA_COMPETENCIA) = $anoAtual
    GROUP BY CATEGORIA, SUBCATEGORIA, DATA_COMPETENCIA
    ORDER BY CATEGORIA, SUBCATEGORIA, DATA_COMPETENCIA
";
$resOutrasRec = $conn->query($sqlOutrasRec);

// DEBUG: Verificar quantos registros foram encontrados
echo "<!-- DEBUG: fOutrasReceitas query returned " . ($resOutrasRec ? $resOutrasRec->num_rows : 0) . " rows -->";

if ($resOutrasRec) {
    while ($row = $resOutrasRec->fetch_assoc()) {
        $catOR = !empty($row['CATEGORIA']) ? $row['CATEGORIA'] : 'NÃO CATEGORIZADO';
        $subOR = !empty($row['SUBCATEGORIA']) ? $row['SUBCATEGORIA'] : 'NÃO ESPECIFICADO';
        $mesOR = (int)date('n', strtotime($row['DATA_COMPETENCIA']));
        $valor = floatval($row['TOTAL']);
        
        // DEBUG para categoria Z - ENTRADA DE REPASSE
        if ($catOR === 'Z - ENTRADA DE REPASSE') {
            echo "<!-- DEBUG: Found Z - ENTRADA DE REPASSE: $subOR, month $mesOR, value $valor -->";
        }
        
        // Se a categoria for "Z - ENTRADA DE REPASSE", adiciona à matriz principal
        if ($catOR === 'Z - ENTRADA DE REPASSE') {
            // Usar um ID único baseado na data para simular estrutura da matriz principal
            $id_simulado = 'OR_' . $row['DATA_COMPETENCIA'] . '_' . $catOR . '_' . $subOR;
            $matriz[$catOR][$subOR][$mesOR][$id_simulado][] = $valor;
        }
    }
}

// Calcule os 3 últimos meses fechados a partir do mês selecionado
$mesesUltimos3 = [];
for ($i = 1; $i <= 3; $i++) {
    $m = $mesAtual - $i;
    if ($m < 1) $m += 12;
    $mesesUltimos3[] = $m;
}

// Calcule média e mês atual para cada categoria e subcategoria
$media3Cat = [];
$atualCat   = [];
$media3Sub = [];
$atualSub   = [];
foreach ($matriz as $cat => $subs) {
    $soma3Cat = 0;
    $somaAtualCat = 0;
    foreach ($subs as $sub => $mesValores) {
        $soma3Sub    = 0;
        $somaAtualSub = 0;
        foreach ($mesesUltimos3 as $m) {
            if (isset($mesValores[$m])) {
                foreach ($mesValores[$m] as $ids) {
                    $soma3Cat += array_sum($ids);
                    $soma3Sub += array_sum($ids);
                }
            }
        }
        if (isset($mesValores[$mesAtual])) {
            foreach ($mesValores[$mesAtual] as $ids) {
                $somaAtualCat += array_sum($ids);
                $somaAtualSub += array_sum($ids);
            }
        }
        $media3Sub[$cat][$sub] = $soma3Sub / 3;
        $atualSub[$cat][$sub] = $somaAtualSub;
        
        // DEBUG para Z - ENTRADA DE REPASSE
        if ($cat === 'Z - ENTRADA DE REPASSE') {
            echo "<!-- DEBUG: $cat -> $sub: média3=$soma3Sub/3=" . ($soma3Sub/3) . ", atual=$somaAtualSub -->";
        }
    }
    $media3Cat[$cat] = $soma3Cat / 3;
    $atualCat[$cat]   = $somaAtualCat;
    
    // DEBUG para Z - ENTRADA DE REPASSE total
    if ($cat === 'Z - ENTRADA DE REPASSE') {
        echo "<!-- DEBUG: $cat TOTAL: média3=$soma3Cat/3=" . ($soma3Cat/3) . ", atual=$somaAtualCat -->";
    }
}

// Receita operacional (exemplo simplificado)
$receitaPorMes = [];
$resRec = $conn->query("
    SELECT DATA_PAGAMENTO, SUM(VALOR_PAGO) AS TOTAL
    FROM fcontasareceberdetalhestap
    WHERE STATUS = 'Pago' AND YEAR(DATA_PAGAMENTO) = $anoAtual
    GROUP BY DATA_PAGAMENTO
");
while ($row = $resRec->fetch_assoc()) {
    $mes = (int)date('n', strtotime($row['DATA_PAGAMENTO']));
    $receitaPorMes[$mes] = ($receitaPorMes[$mes] ?? 0) + floatval($row['TOTAL']);
}
$soma3Rec = 0;
foreach ($mesesUltimos3 as $m) {
    $soma3Rec += $receitaPorMes[$m] ?? 0;
}
$media3Rec = $soma3Rec / 3;
$atualRec   = $receitaPorMes[$mesAtual] ?? 0;

// Ordena as categorias, colocando "OPERACOES EXTERNAS" por último
$matrizOrdenada = [];
foreach ($matriz as $cat => $subs) {
    if (mb_strtoupper(trim($cat)) !== 'Z - SAIDA DE REPASSE') {
        $matrizOrdenada[$cat] = $subs;
    }
}
if (isset($matriz['Z - SAIDA DE REPASSE'])) {
    $matrizOrdenada['Z - SAIDA DE REPASSE'] = $matriz['Z - SAIDA DE REPASSE'];
}

// Função para ordenar subcategorias na exibição (não utilizada - ordenação via JavaScript)
// function obterSubcategoriasOrdenadas($subcategorias, $categoria, $ordenacao, $metasArray, $metaReceita) {
//     // Função removida - ordenação agora é feita via JavaScript
// }

// Consulta para carregar a ÚLTIMA meta gravada para cada Categoria/Subcategoria
$sqlMetas = "
    SELECT 
        fm1.Categoria, 
        fm1.Subcategoria, 
        fm1.Meta
    FROM 
        fMetasTap fm1
    INNER JOIN (
        SELECT 
            Categoria, 
            Subcategoria, 
            MAX(Data) as MaxData
        FROM 
            fMetasTap
        GROUP BY 
            Categoria, Subcategoria
    ) fm2 
    ON fm1.Categoria = fm2.Categoria 
    AND IFNULL(fm1.Subcategoria, '') = IFNULL(fm2.Subcategoria, '') -- Trata Subcategoria NULL ou vazia de forma consistente
    AND fm1.Data = fm2.MaxData
";
$resMetas = $conn->query($sqlMetas);
$metasArray = [];
if ($resMetas) {
    while ($m = $resMetas->fetch_assoc()){
        $cat = $m['Categoria'];
        $sub = $m['Subcategoria'] ?? ''; // Usar string vazia se Subcategoria for NULL/vazia
        $metasArray[$cat][$sub] = $m['Meta'];
    }
}

// Certifique-se de que as metas da Receita, Tributos e Custo Variável estejam definidas,
// utilizando 0 como valor padrão se não existirem.
$metaReceita         = $metasArray['RECEITA BRUTA']['']      ?? 0; // Ajustado para 'RECEITA BRUTA'
$metaTributos        = $metasArray['TRIBUTOS']['']           ?? 0;
$metaCustoVariavel   = $metasArray['CUSTO VARIÁVEL']['']      ?? 0;
// Calcula o Lucro Bruto da meta: Receita - Tributos - Custo Variável
$metaLucro = $metaReceita - $metaTributos - $metaCustoVariavel;

// Para as despesas, use 0 caso não existam
$metaCustoFixo      = $metasArray['CUSTO FIXO']['']      ?? 0;
$metaDespesaFixa    = $metasArray['DESPESA FIXA']['']     ?? 0;
$metaDespesaVenda   = $metasArray['DESPESA VENDA']['']    ?? 0;
$totalMetaDespesas  = $metaCustoFixo + $metaDespesaFixa + $metaDespesaVenda;

// Lucro Líquido da meta: Lucro Bruto - (Custo Fixo + Despesa Fixa + Despesa Venda)
$metaLucroLiquido = $metaLucro - $totalMetaDespesas;

// Chaves para buscar metas de linhas calculadas
$keyReceitaLiquida = "RECEITA LÍQUIDA (RECEITA BRUTA - TRIBUTOS)";
$keyLucroBruto = "LUCRO BRUTO (RECEITA LÍQUIDA - CUSTO VARIÁVEL)";
$keyLucroLiquidoPHP = "LUCRO LÍQUIDO"; // Como o JS salva
$keyFluxoCaixa = "FLUXO DE CAIXA";

// Calcule médias e totais para FAT LIQUIDO e CUSTO VARIÁVEL
$mediaFATLiquido = ($media3Rec - ($media3Cat['TRIBUTOS'] ?? 0));
$atualFATLiquido = ($atualRec - ($atualCat['TRIBUTOS'] ?? 0));

$mediaCustoVariavel = $media3Cat['CUSTO VARIÁVEL'] ?? 0;
$atualCustoVariavel = $atualCat['CUSTO VARIÁVEL'] ?? 0;

// ==================== [NOVA SEÇÃO: BUSCAR DADOS DE fOutrasReceitas PARA ESTRUTURAS ESPECIAIS] ====================
$outrasReceitasPorCatSubMes = []; // Estrutura: [categoria][subcategoria][mes] = valor
$entradaRepassePorCatSubMes = []; // Nova estrutura para ENTRADA DE REPASSE (categorias que não são Z - ENTRADA DE REPASSE)

// Definir quais subcategorias pertencem a RECEITAS NÃO OPERACIONAIS
$subcategoriasReceitasNaoOp = ['EMPRÉSTIMO', 'RETORNO DE INVESTIMENTO', 'OUTRAS RECEITAS'];

// Reprocessar apenas para as estruturas especiais (RNO e ER complexas)
if ($resOutrasRec) {
    $resOutrasRec->data_seek(0); // Reset do cursor para reler os dados
    while ($row = $resOutrasRec->fetch_assoc()) {
        $catOR = !empty($row['CATEGORIA']) ? $row['CATEGORIA'] : 'NÃO CATEGORIZADO';
        $subOR = !empty($row['SUBCATEGORIA']) ? $row['SUBCATEGORIA'] : 'NÃO ESPECIFICADO';
        $mesOR = (int)date('n', strtotime($row['DATA_COMPETENCIA']));
        $valor = floatval($row['TOTAL']);
        
        // Pular "Z - ENTRADA DE REPASSE" pois já foi processada na matriz principal
        if ($catOR === 'Z - ENTRADA DE REPASSE') {
            continue;
        }
        
        // Verificar se a subcategoria pertence a RECEITAS NÃO OPERACIONAIS
        if (in_array($subOR, $subcategoriasReceitasNaoOp)) {
            // Manter em RECEITAS NÃO OPERACIONAIS
            $outrasReceitasPorCatSubMes[$catOR][$subOR][$mesOR] = ($outrasReceitasPorCatSubMes[$catOR][$subOR][$mesOR] ?? 0) + $valor;
        } else {
            // Outras categorias vão para ENTRADA DE REPASSE (estrutura complexa)
            $entradaRepassePorCatSubMes[$catOR][$subOR][$mesOR] = ($entradaRepassePorCatSubMes[$catOR][$subOR][$mesOR] ?? 0) + $valor;
        }
    }
}

// Calcular médias e atuais para cada categoria e subcategoria de fOutrasReceitas
$media3OutrasRecSub = []; // [cat][sub] = media
$atualOutrasRecSub   = []; // [cat][sub] = atual
$totalMedia3OutrasRecGlobal = 0; // Total geral da média para a linha principal "RECEITAS NAO OPERACIONAIS"
$totalAtualOutrasRecGlobal  = 0; // Total geral atual para a linha principal "RECEITAS NAO OPERACIONAIS"

// Calcular médias e atuais para ENTRADA DE REPASSE
$media3EntradaRepasseSub = []; // [cat][sub] = media
$atualEntradaRepasseSub   = []; // [cat][sub] = atual
$totalMedia3EntradaRepasseGlobal = 0; // Total geral da média para a linha principal "ENTRADA DE REPASSE"
$totalAtualEntradaRepasseGlobal  = 0; // Total geral atual para a linha principal "ENTRADA DE REPASSE"

if (!empty($outrasReceitasPorCatSubMes)) {
    foreach ($outrasReceitasPorCatSubMes as $catOR => $subcategoriasOR) {
        foreach ($subcategoriasOR as $subOR => $valoresMensaisOR) {
            $soma3 = 0;
            foreach ($mesesUltimos3 as $m) {
                $soma3 += $valoresMensaisOR[$m] ?? 0;
            }
            $media3OutrasRecSub[$catOR][$subOR] = (count($mesesUltimos3) > 0) ? $soma3 / count($mesesUltimos3) : 0;
             if (count($mesesUltimos3) == 0) $media3OutrasRecSub[$catOR][$subOR] = 0;

            $atualOutrasRecSub[$catOR][$subOR] = $valoresMensaisOR[$mesAtual] ?? 0;

            $totalMedia3OutrasRecGlobal += $media3OutrasRecSub[$catOR][$subOR];
            $totalAtualOutrasRecGlobal  += $atualOutrasRecSub[$catOR][$subOR];
        }
    }
}

if (!empty($entradaRepassePorCatSubMes)) {
    foreach ($entradaRepassePorCatSubMes as $catER => $subcategoriasER) {
        foreach ($subcategoriasER as $subER => $valoresMensaisER) {
            $soma3 = 0;
            foreach ($mesesUltimos3 as $m) {
                $soma3 += $valoresMensaisER[$m] ?? 0;
            }
            $media3EntradaRepasseSub[$catER][$subER] = (count($mesesUltimos3) > 0) ? $soma3 / count($mesesUltimos3) : 0;
             if (count($mesesUltimos3) == 0) $media3EntradaRepasseSub[$catER][$subER] = 0;

            $atualEntradaRepasseSub[$catER][$subER] = $valoresMensaisER[$mesAtual] ?? 0;

            $totalMedia3EntradaRepasseGlobal += $media3EntradaRepasseSub[$catER][$subER];
            $totalAtualEntradaRepasseGlobal  += $atualEntradaRepasseSub[$catER][$subER];
        }
    }
}

// ==================== [CÁLCULO LUCRO LÍQUIDO - PHP (ANTECIPADO)] ====================
$mediaReceitaLiquida = $media3Rec - ($media3Cat['TRIBUTOS'] ?? 0);
$atualReceitaLiquida = $atualRec - ($atualCat['TRIBUTOS'] ?? 0);

$mediaLucroBruto = $mediaReceitaLiquida - ($media3Cat['CUSTO VARIÁVEL'] ?? 0);
$atualLucroBruto = $atualReceitaLiquida - ($atualCat['CUSTO VARIÁVEL'] ?? 0);

$mediaCustoFixo    = $media3Cat['CUSTO FIXO']   ?? 0;
$mediaDespesaFixa  = $media3Cat['DESPESA FIXA']  ?? 0;
$mediaDespesaVenda = $media3Cat['DESPESA VENDA'] ?? 0;
$totalMediaDespesas = $mediaCustoFixo + $mediaDespesaFixa + $mediaDespesaVenda;
$mediaLucroLiquido = $mediaLucroBruto - $totalMediaDespesas;

$atualCustoFixo    = $atualCat['CUSTO FIXO']   ?? 0;
$atualDespesaFixa  = $atualCat['DESPESA FIXA']  ?? 0;
$atualDespesaVenda = $atualCat['DESPESA VENDA'] ?? 0;
$totalAtualDespesas = $atualCustoFixo + $atualDespesaFixa + $atualDespesaVenda;
$atualLucroLiquido = $atualLucroBruto - $totalAtualDespesas;
// ==================== [FIM CÁLCULO LUCRO LÍQUIDO - PHP (ANTECIPADO)] ====================

// ==================== [CÁLCULO FLUXO DE CAIXA - PHP] ====================
$mediaInvestInterno = $media3Cat['INVESTIMENTO INTERNO'] ?? 0;
$mediaInvestExterno = $media3Cat['INVESTIMENTO EXTERNO'] ?? 0;
$mediaSaidaRepasse  = $media3Cat['Z - SAIDA DE REPASSE'] ?? 0;
$mediaAmortizacao   = $media3Cat['AMORTIZAÇÃO'] ?? 0;
$mediaEntradaRepasse = $media3Cat['Z - ENTRADA DE REPASSE'] ?? 0; // Usar diretamente da matriz principal

// DEBUG do cálculo do fluxo
echo "<!-- DEBUG FLUXO: LucroLiq=$mediaLucroLiquido, RNO=$totalMedia3OutrasRecGlobal, EntradaRepasse=$mediaEntradaRepasse -->";
echo "<!-- DEBUG FLUXO: InvInt=$mediaInvestInterno, InvExt=$mediaInvestExterno, SaidaRep=$mediaSaidaRepasse, Amort=$mediaAmortizacao -->";

// Incluir "Z - ENTRADA DE REPASSE" diretamente + outras receitas não operacionais
$mediaFluxoCaixa = ($mediaLucroLiquido + $totalMedia3OutrasRecGlobal + $mediaEntradaRepasse) - ($mediaInvestInterno + $mediaInvestExterno + $mediaSaidaRepasse + $mediaAmortizacao);

$atualInvestInterno = $atualCat['INVESTIMENTO INTERNO'] ?? 0;
$atualInvestExterno = $atualCat['INVESTIMENTO EXTERNO'] ?? 0;
$atualSaidaRepasse  = $atualCat['Z - SAIDA DE REPASSE'] ?? 0;
$atualAmortizacao   = $atualCat['AMORTIZAÇÃO'] ?? 0;
$atualEntradaRepasse = $atualCat['Z - ENTRADA DE REPASSE'] ?? 0; // Usar diretamente da matriz principal

// Incluir "Z - ENTRADA DE REPASSE" diretamente + outras receitas não operacionais
$atualFluxoCaixa = ($atualLucroLiquido + $totalAtualOutrasRecGlobal + $atualEntradaRepasse) - ($atualInvestInterno + $atualInvestExterno + $atualSaidaRepasse + $atualAmortizacao);
// ==================== [FIM CÁLCULO FLUXO DE CAIXA - PHP] ====================


?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>DRE Financeiro</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="/assets/css/style.css" rel="stylesheet">
  <style>
    .dre-cat    { background: #22223b; font-weight: bold; cursor:pointer; }
    .dre-sub    { background: #383858; font-weight: 500; cursor:pointer; }
    .dre-subcat-l1 { background: #2c2c4a; font-weight: bold; cursor:pointer; } /* Estilo para subcategorias L1 de RNO */
    .dre-subcat-l2 { background: #383858; font-weight: 500; } /* Estilo para subcategorias L2 de RNO */
    .dre-detalhe{ background: #232946; }
    .dre-hide   { display: none; }
    table, th, td {
      /* vertical-align: middle; */ /* Descomente se quiser alinhar verticalmente ao centro */
      font-family: 'Segoe UI', Arial, sans-serif;
      font-size: 11px;
      border: 0.5px solid #111827; /* Cor da borda ajustada para a mesma do fundo */
      border-collapse: collapse;
    }
    .dre-cat, .dre-sub {
      font-size: 12px;
    }
    .col-atual {
      background-color: #1e293b !important;
      color: #ffb703 !important;
      border-left: 2px solid #ffb703;
      border-right: 2px solid #ffb703;
    }
    
    /* Estilos para ordenação de subcategorias */
    #ordenacaoSelect {
      font-size: 12px;
      padding: 4px 8px;
      width: auto;
      min-width: 180px;
      transition: all 0.3s ease;
    }
    
    #ordenacaoSelect:focus {
      outline: 2px solid #ffb703;
      background-color: #f3f4f6;
    }
    
    .ordenacao-container {
      background: rgba(31, 41, 55, 0.8);
      border: 1px solid #374151;
      border-radius: 6px;
      padding: 8px 12px;
      margin-bottom: 12px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
    }
    
    .ordenacao-container label {
      margin: 0;
      font-weight: 500;
      color: #9CA3AF;
    }
    
    .subcategoria-ordenada {
      transition: all 0.5s ease;
    }
    
    .subcategoria-highlight {
      background-color: rgba(255, 183, 3, 0.1) !important;
      border-left: 3px solid #ffb703;
    }
  </style>
</head>
<body class="bg-gray-900 text-gray-100 flex min-h-screen">
<main class="flex-1 bg-gray-900 p-6 relative">

  <!-- Controles para salvar metas -->
  <div class="mb-6 flex items-end gap-4">
    <div>
      <label for="dataMetaInput" class="block text-sm font-medium text-gray-300 mb-1">Data para Salvar Metas:</label>
      <input type="date" id="dataMetaInput" name="dataMetaInput"
             class="bg-gray-700 border border-gray-600 text-gray-100 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
             value="<?= date('Y-m-d') ?>">
    </div>
    <button id="pontoEquilibrioBtn" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-4 rounded text-xs">Ponto Equilíbrio</button>
    <button id="salvarMetasBtn" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-4 rounded text-xs">Salvar Metas</button>
    <button id="carregarMetasOficiaisBtn" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-4 rounded text-xs">Carregar Metas</button>
  </div>

  <div class="mb-6 flex items-end gap-4">
    <div>
      <label for="nomeSimulacaoLocalInput" class="block text-sm font-medium text-gray-300 mb-1">Nome da simulação a ser salva:</label>
      <input type="text" id="nomeSimulacaoLocalInput" placeholder="Ex: Cenário Otimista"
             class="bg-gray-700 border border-gray-600 text-gray-100 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
    </div>
    <button id="salvarSimulacaoLocalBtn" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-4 rounded text-xs self-end">Salvar Simulação</button>
    <div>
      <label for="listaSimulacoesSalvas" class="block text-sm font-medium text-gray-300 mb-1">Carregar Simulação Salva:</label>
      <select id="listaSimulacoesSalvas" class="bg-gray-700 border border-gray-600 text-gray-100 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
        <option value="">-- Selecione --</option>
      </select>
    </div>
    <button id="carregarSimulacaoLocalBtn" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-4 rounded text-xs self-end">Carregar</button>
    <button id="excluirSimulacaoLocalBtn" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-4 rounded text-xs self-end">Excluir</button>
  </div>

  <!-- Filtro de Ano/Mês -->
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
    <label>
      Ordenar Subcategorias:
      <select id="ordenacaoSelect" class="text-black rounded p-1">
        <option value="nome">Nome (A-Z)</option>
        <option value="nome-desc">Nome (Z-A)</option>
        <option value="meta-valor">Meta - Valor (↓)</option>
        <option value="meta-valor-asc">Meta - Valor (↑)</option>
        <option value="meta-perc">Meta - % (↓)</option>
        <option value="meta-perc-asc">Meta - % (↑)</option>
      </select>
    </label>
    <button type="submit" class="bg-yellow-400 text-black px-3 py-1 rounded font-bold">Filtrar</button>
  </form>

  <h1 class="text-2xl font-bold text-yellow-400 mb-6">
    DRE Financeiro - <?=$anoAtual?> / <?=$meses[$mesAtual]?>
  </h1>
  <span id="msg-atualiza-json" class="ml-2 text-xs"></span>
  
  <!-- Tabela de Simulação com Blocos -->
  <table id="tabelaSimulacao" class="min-w-full text-xs mx-auto border border-gray-700 rounded">
    <thead>
      <tr>
        <th rowspan="2" class="p-2 text-center bg-gray-800">Categoria &gt; SubCategoria</th>
        <th colspan="2" class="p-2 text-center bg-blue-900">Média -3m / % Média-3m S/ FAT.</th>
        <th colspan="3" class="p-2 text-center bg-green-900">Simulação / % Simulação S/ FAT. / Diferença (R$)</th>
        <th colspan="2" class="p-2 text-center bg-red-900">Meta / % Meta s/ FAT.</th>
        <th colspan="3" class="p-2 text-center bg-purple-900">Realizado / % Realizado s/ FAT. / Comparação Meta</th>
      </tr>
      <tr>
        <th class="p-2 text-center bg-blue-700">Média -3m</th>
        <th class="p-2 text-center bg-blue-700">% Média-3m S/ FAT.</th>
        <th class="p-2 text-center bg-green-700">Simulação</th>
        <th class="p-2 text-center bg-green-700">% Simulação S/ FAT.</th>
        <th class="p-2 text-center bg-green-700">Diferença (R$)</th>
        <th class="p-2 text-center bg-red-700">Meta</th>
        <th class="p-2 text-center bg-red-700">% Meta s/ FAT.</th>
        <th class="p-2 text-center bg-purple-700">Realizado</th>
        <th class="p-2 text-center bg-purple-700">% Realizado s/ Meta.</th>
        <th class="p-2 text-center bg-purple-700">Comparação Meta</th>
      </tr>
    </thead>
    <tbody>
      <!-- 1. RECEITA OPERACIONAL (continua editável) -->
      <tr class="dre-cat" style="background:#1a4c2b;">
        <td class="p-2 text-left">RECEITA BRUTA</td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($media3Rec,2,',','.') ?></td>
        <td class="p-2 text-center">100,00%</td>
        <td class="p-2 text-right">
          <input type="text" data-receita="1" class="simul-valor font-bold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
                 value="<?= number_format($media3Rec,2,',','.') ?>">
        </td>
        <td class="p-2 text-center">
          <input type="text" class="simul-perc font-bold bg-gray-800 text-yellow-400 text-center rounded px-1" style="width:60px;"
                 value="100,00%">
        </td>
        <td class="p-2 text-right"><?= 'R$ '.number_format(0,2,',','.') ?></td>
        <td class="p-2 text-right"><?= isset($metasArray['RECEITA BRUTA']['']) ? 'R$ '.number_format($metasArray['RECEITA BRUTA'][''],2,',','.') : '' ?></td>
        <td class="p-2 text-center">
          <?= ($metaReceita> 0 && isset($metasArray['RECEITA BRUTA'][''])) ? number_format(($metasArray['RECEITA BRUTA']['']/$metaReceita)*100,2,',','.') .'%' : '' ?>
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
              $comparacao = $meta_val - $realizado_val;
              $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
              echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
            } else { echo '-'; }
          ?>
        </td>
      </tr>
      

<!-- TRIBUTOS (ajustado para seguir o padrão das demais linhas principais) -->
<?php if(isset($matrizOrdenada['TRIBUTOS'])): ?>
  <tr class="dre-cat cat_trib">
    <td class="p-2 text-left">TRIBUTOS</td>
    <td class="p-2 text-right"><?= 'R$ '.number_format($media3Cat['TRIBUTOS'] ?? 0,2,',','.') ?></td>
    <td class="p-2 text-center"><?= $media3Rec>0 ? number_format((($media3Cat['TRIBUTOS'] ?? 0)/$media3Rec)*100,2,',','.') .'%' : '-' ?></td>
    <td class="p-2 text-right">
      <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
             value="<?= number_format($media3Cat['TRIBUTOS'] ?? 0,2,',','.') ?>">
    </td>
    <td class="p-2 text-center">
      <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
             style="width:60px;" value="<?= $media3Rec>0 ? number_format((($media3Cat['TRIBUTOS'] ?? 0)/$media3Rec)*100,2,',','.') : '-' ?>">
    </td>
    <td class="p-2 text-right">-</td> <!-- Diferença -->
    <td class="p-2 text-right"><?= isset($metasArray['TRIBUTOS']['']) ? 'R$ '.number_format($metasArray['TRIBUTOS'][''],2,',','.') : '' ?></td>
    <td class="p-2 text-center"><?= ($metaReceita>0 && isset($metasArray['TRIBUTOS'][''])) ? number_format(($metasArray['TRIBUTOS']['']/$metaReceita)*100,2,',','.') .'%' : '' ?></td>
    <td class="p-2 text-right"><?= 'R$ '.number_format($atualCat['TRIBUTOS'] ?? 0,2,',','.') ?></td>
    <td class="p-2 text-center">
      <?php
        $meta_t_val = $metasArray['TRIBUTOS'][''] ?? null; // Meta da categoria principal TRIBUTOS
        $realizado_t_val = $atualCat['TRIBUTOS'] ?? 0;
        if (isset($meta_t_val) && $meta_t_val != 0) {
          echo number_format(($realizado_t_val / $meta_t_val) * 100, 2, ',', '.') . '%';
        } else { echo '-'; }
      ?>
    </td>
    <td class="p-2 text-center">
      <?php 
        // $meta_val já é $meta_t_val para esta linha
        $realizado_val = $atualCat['TRIBUTOS'] ?? 0;
        if (isset($meta_t_val)) {
          $comparacao = $meta_t_val - $realizado_val;
          $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
          echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
        } else { echo '-'; } ?>
    </td>
  </tr>
  <?php foreach($matrizOrdenada['TRIBUTOS'] as $sub => $mesValores): ?>
    <?php if($sub): ?>
      <tr class="dre-sub">
        <td class="p-2 text-left" style="padding-left:2em;"><?= htmlspecialchars($sub) ?></td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($media3Sub['TRIBUTOS'][$sub] ?? 0,2,',','.') ?></td>
        <td class="p-2 text-center">
          <?= $media3Rec>0 ? number_format((($media3Sub['TRIBUTOS'][$sub] ?? 0)/$media3Rec)*100,2,',','.') .'%' : '-' ?>
        </td>
        <td class="p-2 text-right">
          <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
                 value="<?= number_format($media3Sub['TRIBUTOS'][$sub] ?? 0,2,',','.') ?>">
        </td>
        <td class="p-2 text-center">
          <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
                 style="width:60px;" value="<?= $media3Rec>0 ? number_format((($media3Sub['TRIBUTOS'][$sub] ?? 0)/$media3Rec)*100,2,',','.') : '-' ?>">
        </td>
        <td class="p-2 text-right">-</td>
        <td class="p-2 text-right"><?= isset($metasArray['TRIBUTOS'][$sub]) ? 'R$ '.number_format($metasArray['TRIBUTOS'][$sub],2,',','.') : '' ?></td>
        <td class="p-2 text-center"><?= ($metaReceita>0 && isset($metasArray['TRIBUTOS'][$sub])) ? number_format(($metasArray['TRIBUTOS'][$sub]/$metaReceita)*100,2,',','.') .'%' : '' ?></td>
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
            } else { echo '-'; } ?>
        </td>
      </tr>
    <?php endif; ?>
  <?php endforeach; ?>
<?php endif; ?>

<!-- RECEITA LÍQUIDA (RECEITA BRUTA - TRIBUTOS) -->
<?php
  $mediaReceitaLiquida = $media3Rec - ($media3Cat['TRIBUTOS'] ?? 0);
  $atualReceitaLiquida = $atualRec - ($atualCat['TRIBUTOS'] ?? 0);
  // As variáveis de simulação ($simReceitaLiquida, $simLucroBruto, $simLucroLiquido)
  // são calculadas e preenchidas pelo JavaScript.
?>
<tr id="rowFatLiquido" class="dre-cat" style="background:#1a4c2b;">
  <td class="p-2 text-left">RECEITA LÍQUIDA (RECEITA BRUTA - TRIBUTOS)</td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($mediaReceitaLiquida,2,',','.') ?></td>
  <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format(($mediaReceitaLiquida / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?></td>
  <td class="p-2 text-right">
    <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1" readonly>
  </td>
  <td class="p-2 text-center">
    <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1" style="width:60px;" readonly>
  </td>
  <td class="p-2 text-right">-</td> <!-- Diferença -->
  <td class="p-2 text-right">
    <?= isset($metasArray[$keyReceitaLiquida]['']) ? 'R$ '.number_format($metasArray[$keyReceitaLiquida][''],2,',','.') : '-' ?>
  </td>
  <td class="p-2 text-center">
    <?php
      // % Meta s/ FAT. para RECEITA LÍQUIDA
      if ($metaReceita > 0 && isset($metasArray[$keyReceitaLiquida][''])) {
        echo number_format(($metasArray[$keyReceitaLiquida][''] / $metaReceita) * 100, 2, ',', '.') . '%';
      } else { echo '-'; }
    ?>
  </td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($atualReceitaLiquida,2,',','.') ?></td>
  <td class="p-2 text-center">
    <?php
      $meta_rl_val = $metasArray[$keyReceitaLiquida][''] ?? null;
      if (isset($meta_rl_val) && $meta_rl_val != 0) {
        echo number_format(($atualReceitaLiquida / $meta_rl_val) * 100, 2, ',', '.') . '%';
      } else { echo '-'; }
    ?>
  </td>
  <td class="p-2 text-center">
    <?php
      if (isset($meta_rl_val)) {
        $comparacaoRL = $meta_rl_val - $atualReceitaLiquida;
        $corComparacaoRL = ($comparacaoRL >= 0) ? 'text-green-400' : 'text-red-400';
        echo '<span class="' . $corComparacaoRL . '">R$ ' . number_format($comparacaoRL, 2, ',', '.') . '</span>';
      } else {
        echo '-';
      }
    ?>
  </td>
</tr>

<!-- CUSTO VARIÁVEL (categoria e subcategorias) -->
<?php if(isset($matrizOrdenada['CUSTO VARIÁVEL'])): ?>
  <tr class="dre-cat cat_cvar">
    <td class="p-2 text-left">CUSTO VARIÁVEL</td>
    <td class="p-2 text-right"><?= 'R$ '.number_format($media3Cat['CUSTO VARIÁVEL'] ?? 0,2,',','.') ?></td>
    <td class="p-2 text-center"><?= $media3Rec>0 ? number_format((($media3Cat['CUSTO VARIÁVEL'] ?? 0)/$media3Rec)*100,2,',','.') .'%' : '-' ?></td>
    <td class="p-2 text-right">
      <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
             value="<?= number_format($media3Cat['CUSTO VARIÁVEL'] ?? 0,2,',','.') ?>">
    </td>
    <td class="p-2 text-center">
      <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
             style="width:60px;" value="<?= $media3Rec>0 ? number_format((($media3Cat['CUSTO VARIÁVEL'] ?? 0)/$media3Rec)*100,2,',','.') : '-' ?>">
    </td>
    <td class="p-2 text-right">-</td> <!-- Diferença -->
    <td class="p-2 text-right"><?= isset($metasArray['CUSTO VARIÁVEL']['']) ? 'R$ '.number_format($metasArray['CUSTO VARIÁVEL'][''],2,',','.') : '' ?></td>
    <td class="p-2 text-center"><?= ($metaReceita>0 && isset($metasArray['CUSTO VARIÁVEL'][''])) ? number_format(($metasArray['CUSTO VARIÁVEL']['']/$metaReceita)*100,2,',','.') .'%' : '' ?></td>
    <td class="p-2 text-right"><?= 'R$ '.number_format($atualCat['CUSTO VARIÁVEL'] ?? 0,2,',','.') ?></td>
    <td class="p-2 text-center">
      <?php
        $meta_cv_val = $metasArray['CUSTO VARIÁVEL'][''] ?? null; // Meta da categoria principal CUSTO VARIÁVEL
        $realizado_cv_val = $atualCat['CUSTO VARIÁVEL'] ?? 0;
        if (isset($meta_cv_val) && $meta_cv_val != 0) {
          echo number_format(($realizado_cv_val / $meta_cv_val) * 100, 2, ',', '.') . '%';
        } else { echo '-'; }
      ?>
    </td>
    <td class="p-2 text-center">
      <?php 
        // $meta_val já é $meta_cv_val para esta linha
        $realizado_val = $atualCat['CUSTO VARIÁVEL'] ?? 0;
        if (isset($meta_cv_val)) {
          $comparacao = $meta_cv_val - $realizado_val;
          $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
          echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
        } else { echo '-'; } ?>
    </td>
</tr>

<?php endif; ?>
  <?php foreach($matrizOrdenada['CUSTO VARIÁVEL'] as $sub => $mesValores): ?>
    <?php if($sub): ?>
      <tr class="dre-sub">
  <td class="p-2 text-left" style="padding-left:2em;"><?= htmlspecialchars($sub) ?></td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($media3Sub['CUSTO VARIÁVEL'][$sub] ?? 0,2,',','.') ?></td>
  <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format((($media3Sub['CUSTO VARIÁVEL'][$sub] ?? 0)/$media3Rec)*100,2,',','.') .'%' : '-' ?></td>
  <td class="p-2 text-right">
    <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
           value="<?= number_format($media3Sub['CUSTO VARIÁVEL'][$sub] ?? 0,2,',','.') ?>">
  </td>
  <td class="p-2 text-center">
    <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
           style="width:60px;" value="<?= $media3Rec > 0 ? number_format((($media3Sub['CUSTO VARIÁVEL'][$sub] ?? 0)/$media3Rec)*100,2,',','.') : '-' ?>">
  </td>
  <td class="p-2 text-right">-</td>
  <td class="p-2 text-right"><?= isset($metasArray['CUSTO VARIÁVEL'][$sub]) ? 'R$ '.number_format($metasArray['CUSTO VARIÁVEL'][$sub],2,',','.') : '' ?></td>
  <td class="p-2 text-center"><?= ($metaReceita > 0 && isset($metasArray['CUSTO VARIÁVEL'][$sub])) ? number_format(($metasArray['CUSTO VARIÁVEL'][$sub]/$metaReceita)*100,2,',','.') .'%' : '' ?></td>
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
      } else { echo '-'; } ?>
  </td>
</tr>

    <?php endif; ?>
  <?php endforeach; ?>

<!-- LUCRO BRUTO (RECEITA LÍQUIDA - CUSTO VARIÁVEL) -->
<?php
  $mediaLucroBruto = $mediaReceitaLiquida - ($media3Cat['CUSTO VARIÁVEL'] ?? 0);
  $atualLucroBruto = $atualReceitaLiquida - ($atualCat['CUSTO VARIÁVEL'] ?? 0);
?>
<tr id="rowLucroBruto" class="dre-cat" style="background:#1a4c2b;">
  <td class="p-2 text-left">LUCRO BRUTO (RECEITA LÍQUIDA - CUSTO VARIÁVEL)</td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($mediaLucroBruto,2,',','.') ?></td>
  <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format(($mediaLucroBruto / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?></td>
  <td class="p-2 text-right">
    <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1" readonly>
  </td>
  <td class="p-2 text-center">
    <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1" style="width:60px;" readonly>
  </td>
  <td class="p-2 text-right">-</td> <!-- Diferença -->
  <td class="p-2 text-right">
    <?= isset($metasArray[$keyLucroBruto]['']) ? 'R$ '.number_format($metasArray[$keyLucroBruto][''],2,',','.') : '-' ?>
  </td>
  <td class="p-2 text-center">
    <?php
      // % Meta s/ FAT. para LUCRO BRUTO
      if ($metaReceita > 0 && isset($metasArray[$keyLucroBruto][''])) {
        echo number_format(($metasArray[$keyLucroBruto][''] / $metaReceita) * 100, 2, ',', '.') . '%';
      } else { echo '-'; }
    ?>
  </td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($atualLucroBruto,2,',','.') ?></td>
  <td class="p-2 text-center">
    <?php
      $meta_lb_val = $metasArray[$keyLucroBruto][''] ?? null;
      if (isset($meta_lb_val) && $meta_lb_val != 0) {
        echo number_format(($atualLucroBruto / $meta_lb_val) * 100, 2, ',', '.') . '%';
      } else { echo '-'; }
    ?>
  </td>
  <td class="p-2 text-center">
    <?php
      if (isset($meta_lb_val)) {
        $comparacaoLB = $meta_lb_val - $atualLucroBruto;
        $corComparacaoLB = ($comparacaoLB >= 0) ? 'text-green-400' : 'text-red-400';
        echo '<span class="' . $corComparacaoLB . '">R$ ' . number_format($comparacaoLB, 2, ',', '.') . '</span>';
      } else {
        echo '-';
      }
    ?>
  </td>
</tr>


      <!-- 6. CUSTO FIXO, 7. DESPESA FIXA e 8. DESPESA VENDA (permanecem editáveis) -->
      <?php 
        foreach(['CUSTO FIXO','DESPESA FIXA','DESPESA VENDA'] as $catName): // Loop para CUSTO FIXO, DESPESA FIXA, DESPESA VENDA
          if(isset($matrizOrdenada[$catName])):
      ?>
        <tr class="dre-cat">
  <td class="p-2 text-left"><?= $catName ?></td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($media3Cat[$catName] ?? 0,2,',','.') ?></td>
  <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format((($media3Cat[$catName] ?? 0)/$media3Rec)*100,2,',','.') .'%' : '-' ?></td>
  <td class="p-2 text-right">
    <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
           value="<?= number_format($media3Cat[$catName] ?? 0,2,',','.') ?>">
  </td>
  <td class="p-2 text-center">
    <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
           style="width:60px;" value="<?= $media3Rec > 0 ? number_format((($media3Cat[$catName] ?? 0)/$media3Rec)*100,2,',','.') : '-' ?>">
  </td>
  <td class="p-2 text-right">-</td> <!-- Diferença -->
  <td class="p-2 text-right"><?= isset($metasArray[$catName]['']) ? 'R$ '.number_format($metasArray[$catName][''],2,',','.') : '' ?></td>
  <td class="p-2 text-center"><?= ($metaReceita > 0 && isset($metasArray[$catName][''])) ? number_format(($metasArray[$catName]['']/$metaReceita)*100,2,',','.') .'%' : '' ?></td>
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
      } else { echo '-'; } ?>
  </td>
</tr>

        <?php foreach($matrizOrdenada[$catName] as $sub => $mesValores): ?>
          <?php if($sub): ?>
           <tr class="dre-sub">
  <td class="p-2 text-left" style="padding-left:2em;"><?= htmlspecialchars($sub) ?></td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($media3Sub[$catName][$sub] ?? 0,2,',','.') ?></td>
  <td class="p-2 text-center">
    <?= $media3Rec > 0 ? number_format((($media3Sub[$catName][$sub] ?? 0) / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?>
  </td>
  <td class="p-2 text-right">
    <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
           value="<?= number_format($media3Sub[$catName][$sub] ?? 0, 2, ',', '.') ?>">
  </td>
  <td class="p-2 text-center">
    <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
           style="width:60px;" value="<?= $media3Rec > 0 ? number_format((($media3Sub[$catName][$sub] ?? 0) / $media3Rec) * 100, 2, ',', '.') : '-' ?>">
  </td>
  <td class="p-2 text-right">-</td>
  <td class="p-2 text-right"><?= isset($metasArray[$catName][$sub]) ? 'R$ ' . number_format($metasArray[$catName][$sub], 2, ',', '.') : '' ?></td>
  <td class="p-2 text-center"><?= ($metaReceita > 0 && isset($metasArray[$catName][$sub])) ? number_format(($metasArray[$catName][$sub] / $metaReceita) * 100, 2, ',', '.') . '%' : '' ?></td>
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
        echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
      } else { echo '-'; } ?>
  </td>
</tr>

          <?php endif; ?>
        <?php endforeach; ?>
      <?php 
          endif;
        endforeach;
      ?>

      <!-- 9. LUCRO LIQUIDO = LUCRO BRUTO - (CUSTO FIXO + DESPESA FIXA + DESPESA VENDA) (DINÂMICO, não editável) -->
      <?php 
?>
<tr class="dre-cat" style="background:#1a4c2b;">
  <td class="p-2 text-left">LUCRO LÍQUIDO</td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($mediaLucroLiquido,2,',','.') ?></td>
  <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format(($mediaLucroLiquido / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?></td>
  <td class="p-2 text-right">
    <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1" readonly>
  </td>
  <td class="p-2 text-center">
    <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1" style="width:60px;" readonly>
  </td>
  <td class="p-2 text-right">-</td>
  <td class="p-2 text-right">
    <?php 
        // Prioriza meta salva diretamente para "LUCRO LÍQUIDO", senão usa a calculada em PHP
        $metaLucroLiquidoDisplay = $metasArray[$keyLucroLiquidoPHP][''] ?? $metaLucroLiquido;
        echo isset($metasArray['RECEITA BRUTA']['']) || isset($metasArray[$keyLucroLiquidoPHP]['']) ? 'R$ '.number_format($metaLucroLiquidoDisplay ?? 0,2,',','.') : '';
    ?>
  </td>
  <td class="p-2 text-center">
    <?php // % Meta s/ FAT. para LUCRO LÍQUIDO
      if ($metaReceita > 0 && (isset($metasArray[$keyLucroLiquidoPHP]['']) || isset($metasArray['RECEITA BRUTA']['']))) { // Considera se a meta de LL foi salva ou se a de RB existe para calcular
        echo number_format(($metaLucroLiquidoDisplay / $metaReceita) * 100, 2, ',', '.') . '%';
      } else { echo '-'; }
    ?>
  </td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($atualLucroLiquido,2,',','.') ?></td>
  <td class="p-2 text-center">
    <?php
      $meta_ll_val = $metaLucroLiquidoDisplay ?? null;
      if (isset($meta_ll_val) && $meta_ll_val != 0 && (isset($metasArray[$keyLucroLiquidoPHP]['']) || isset($metasArray['RECEITA BRUTA']['']))) {
        echo number_format(($atualLucroLiquido / $meta_ll_val) * 100, 2, ',', '.') . '%';
      } else { echo '-'; }
    ?>
  </td>
  <td class="p-2 text-center">
    <?php 
      $meta_val = $metaLucroLiquido ?? null; // Meta para Lucro Líquido já calculada
      $realizado_val = $atualLucroLiquido;
      if (isset($meta_val) && (isset($metasArray[$keyLucroLiquidoPHP]['']) || isset($metasArray['RECEITA BRUTA']['']))) {
        $comparacao = $meta_val - $realizado_val;
        $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
        echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
      } else { echo '-'; } ?>
  </td>
</tr>

      <!-- ==================== [NOVA SEÇÃO: RECEITAS NAO OPERACIONAIS] ==================== -->
      <tr class="dre-cat-principal text-white font-bold" style="background:#1a4c2b;">
        <td class="p-2 text-left" colspan="1">RECEITAS NAO OPERACIONAIS</td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($totalMedia3OutrasRecGlobal,2,',','.') ?></td>
        <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format(($totalMedia3OutrasRecGlobal / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?></td>
        <td class="p-2 text-right simul-total-cat" data-cat-total-simul="RECEITAS NAO OPERACIONAIS">
            <?= 'R$ '.number_format($totalMedia3OutrasRecGlobal,2,',','.') ?>
        </td> <!-- Total Simulado (será atualizado por JS) -->
        <td class="p-2 text-center simul-perc-cat" data-cat-perc-simul="RECEITAS NAO OPERACIONAIS">
            <?= $media3Rec > 0 ? number_format(($totalMedia3OutrasRecGlobal / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?>
        </td> <!-- % Total Simulado s/ FAT. (será atualizado por JS) -->
        <td class="p-2 text-right">-</td> <!-- Diferença (R$) -->
        <td class="p-2 text-right">
            <?= isset($metasArray['RECEITAS NAO OPERACIONAIS']['']) ? 'R$ '.number_format($metasArray['RECEITAS NAO OPERACIONAIS'][''],2,',','.') : '' ?>
         </td> <!-- Meta para a linha principal RNO -->
        <td class="p-2 text-center">
            <?php // % Meta s/ FAT. para linha principal RNO
              if ($metaReceita > 0 && isset($metasArray['RECEITAS NAO OPERACIONAIS'][''])) {
                echo number_format(($metasArray['RECEITAS NAO OPERACIONAIS'][''] / $metaReceita) * 100, 2, ',', '.') . '%';
              } else { echo '-'; }
            ?>
        </td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($totalAtualOutrasRecGlobal,2,',','.') ?></td>
        <td class="p-2 text-center">
            <?php
              // % Realizado s/ Meta para a linha principal de RNO
              if(isset($metasArray['RECEITAS NAO OPERACIONAIS']['']) && $metasArray['RECEITAS NAO OPERACIONAIS'][''] != 0) {
                  echo number_format(($totalAtualOutrasRecGlobal / $metasArray['RECEITAS NAO OPERACIONAIS']['']) * 100, 2, ',', '.') . '%';
              } else { echo '-';}
            ?>
        </td>
        <td class="p-2 text-center">
            <?php
              $meta_rno_principal_val = $metasArray['RECEITAS NAO OPERACIONAIS'][''] ?? null;
              if(isset($meta_rno_principal_val)) {
                  $comparacaoRNO_Principal = $meta_rno_principal_val - $totalAtualOutrasRecGlobal;
                  $corCompRNO_Principal = ($comparacaoRNO_Principal >=0) ? 'text-green-400' : 'text-red-400';
                  echo '<span class="'.$corCompRNO_Principal.'">R$ '.number_format($comparacaoRNO_Principal, 2, ',', '.').'</span>';
              } else { echo '-';}
            ?>
        </td>
      </tr>

      <?php if (!empty($outrasReceitasPorCatSubMes)): ?>
        <?php foreach ($outrasReceitasPorCatSubMes as $catNomeOR => $subcategoriasOR): ?>
          <?php
            // Calcular totais de Média e Atual para esta Categoria de RNO ($catNomeOR)
            $totalMedia3CatRNO = 0;
            $totalAtualCatRNO = 0;
            if (isset($media3OutrasRecSub[$catNomeOR])) {
                foreach ($media3OutrasRecSub[$catNomeOR] as $mediaSubValor) {
                    $totalMedia3CatRNO += $mediaSubValor;
                }
            }
            if (isset($atualOutrasRecSub[$catNomeOR])) {
                foreach ($atualOutrasRecSub[$catNomeOR] as $atualSubValor) {
                    $totalAtualCatRNO += $atualSubValor;
                }
            }
            $dataCatKeyRNO = htmlspecialchars(str_replace(' ', '_', $catNomeOR));
          ?>
          <!-- Linha da Categoria de Outras Receitas -->
          <tr class="dre-subcat-l1">
            <td class="p-2 pl-6 text-left font-semibold"><?= htmlspecialchars($catNomeOR) ?></td>
            <td class="p-2 text-right"><?= 'R$ '.number_format($totalMedia3CatRNO,2,',','.') ?></td>
            <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format(($totalMedia3CatRNO / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?></td>
            <td class="p-2 text-right" data-simul-valor-rno-cat="<?= $dataCatKeyRNO ?>"></td> <!-- JS Preenche Simulação Valor -->
            <td class="p-2 text-center" data-simul-perc-rno-cat="<?= $dataCatKeyRNO ?>"></td> <!-- JS Preenche Simulação % -->
            <td class="p-2 text-right">-</td> <!-- Diferença (R$) -->
            <td class="p-2 text-right">
                               <?= isset($metasArray[$catNomeOR]['']) ? 'R$ '.number_format($metasArray[$catNomeOR][''],2,',','.') : '-' ?>
            </td>
            <td class="p-2 text-center">
                <?php // % Meta s/ FAT. para Categoria L1 de RNO
                  if ($metaReceita > 0 && isset($metasArray[$catNomeOR][''])) {
                    echo number_format(($metasArray[$catNomeOR][''] / $metaReceita) * 100, 2, ',', '.') . '%';
                  } else { echo '-'; }
                ?>
            </td>
            <td class="p-2 text-right"><?= 'R$ '.number_format($totalAtualCatRNO,2,',','.') ?></td>
            <td class="p-2 text-center">
                 <?php
                  if(isset($metasArray[$catNomeOR]['']) && $metasArray[$catNomeOR][''] != 0) {
                      echo number_format(($totalAtualCatRNO / $metasArray[$catNomeOR]['']) * 100, 2, ',', '.') . '%';
                  } else { echo '-';}
                 ?>
            </td>
            <td class="p-2 text-center">
                <?php
                  $meta_rno_l1_val = $metasArray[$catNomeOR][''] ?? null;
                  if(isset($meta_rno_l1_val)) {
                      $comparacaoRNO_L1 = $meta_rno_l1_val - $totalAtualCatRNO;
                      $corCompRNO_L1 = ($comparacaoRNO_L1 >=0) ? 'text-green-400' : 'text-red-400';
                      echo '<span class="'.$corCompRNO_L1.'">R$ '.number_format($comparacaoRNO_L1, 2, ',', '.').'</span>';
                  } else { echo '-';}
                ?>
            </td>
          </tr>

          <?php foreach ($subcategoriasOR as $subNomeOR => $valoresMensaisOR): ?>
            <?php
              $mediaSubOR = $media3OutrasRecSub[$catNomeOR][$subNomeOR] ?? 0;
              $atualSubOR = $atualOutrasRecSub[$catNomeOR][$subNomeOR] ?? 0;
              // Usar nomes de categoria e subcategoria para data attributes, normalizando-os para JS
              // $dataCatKeyRNO já definido acima
              $dataSubCatKeyRNO = htmlspecialchars(str_replace(' ', '_', $subNomeOR));
            ?>
            <tr class="dre-subcat-l2">
              <td class="p-2 pl-10 text-left"><?= htmlspecialchars($subNomeOR) ?></td>
              <td class="p-2 text-right"><?= 'R$ '.number_format($mediaSubOR,2,',','.') ?></td>
              <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format(($mediaSubOR / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?></td>
              <td class="p-2 text-right">
                <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
                       data-cat="RECEITAS NAO OPERACIONAIS" data-sub-cat="<?= $dataCatKeyRNO ?>" data-sub-sub-cat="<?= $dataSubCatKeyRNO ?>"
                       value="<?= number_format($mediaSubOR,2,',','.') ?>">
              </td>
              <td class="p-2 text-center">
                <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
                       style="width:60px;" data-cat="RECEITAS NAO OPERACIONAIS" data-sub-cat="<?= $dataCatKeyRNO ?>" data-sub-sub-cat="<?= $dataSubCatKeyRNO ?>"
                       value="<?= $media3Rec > 0 ? number_format(($mediaSubOR / $media3Rec) * 100, 2, ',', '.') : '-' ?>">
              </td>
              <td class="p-2 text-right">-</td> <!-- Diferença (R$) -->
              <td class="p-2 text-right">
                                <?= isset($metasArray[$catNomeOR][$subNomeOR]) ? 'R$ '.number_format($metasArray[$catNomeOR][$subNomeOR],2,',','.') : '-' ?>
              </td>
              <td class="p-2 text-center">
                <?php // % Meta s/ FAT. para Subcategoria L2 de RNO
                  if ($metaReceita > 0 && isset($metasArray[$catNomeOR][$subNomeOR])) {
                    echo number_format(($metasArray[$catNomeOR][$subNomeOR] / $metaReceita) * 100, 2, ',', '.') . '%';
                  } else { echo '-'; }
                ?>
              </td>
              <td class="p-2 text-right"><?= 'R$ '.number_format($atualSubOR,2,',','.') ?></td>
              <td class="p-2 text-center">
                <?php
                  // Para Outras Receitas, a meta é salva com Categoria = $catNomeOR e Subcategoria = $subNomeOR
                  $meta_or_val = $metasArray[$catNomeOR][$subNomeOR] ?? null;
                  if (isset($meta_or_val) && $meta_or_val != 0) {
                    echo number_format(($atualSubOR / $meta_or_val) * 100, 2, ',', '.') . '%';
                  } else { echo '-'; }
                ?>
              </td>
              <td class="p-2 text-center">
                <?php
                  if(isset($metasArray[$catNomeOR][$subNomeOR])) {
                      $comparacaoRNO_L2 = ($metasArray[$catNomeOR][$subNomeOR] ?? 0) - $atualSubOR;
                      $corCompRNO_L2 = ($comparacaoRNO_L2 >=0) ? 'text-green-400' : 'text-red-400';
                      echo '<span class="'.$corCompRNO_L2.'">R$ '.number_format($comparacaoRNO_L2, 2, ',', '.').'</span>';
                  } else { echo '-';}
                ?>
              </td>
            </tr>
          <?php endforeach; // Fim do loop de subcategorias de Outras Receitas ?>
        <?php endforeach; // Fim do loop de categorias de Outras Receitas ?>
      <?php endif; ?>
      <!-- ==================== [FIM DA SEÇÃO RECEITAS NAO OPERACIONAIS] ==================== -->

      <!-- Z - ENTRADA DE REPASSE (categoria normal) -->
      <?php
        $catNameER = 'Z - ENTRADA DE REPASSE';
        // DEBUG: Verificar se a categoria existe na matriz
        echo "<!-- DEBUG: matrizOrdenada[$catNameER] = " . (isset($matrizOrdenada[$catNameER]) ? 'EXISTS' : 'NOT EXISTS') . " -->";
        if (isset($matrizOrdenada[$catNameER])) {
            echo "<!-- DEBUG: Subcategorias: " . implode(', ', array_keys($matrizOrdenada[$catNameER])) . " -->";
        }
        if(isset($matrizOrdenada[$catNameER])):
      ?>
        <tr class="dre-cat">
          <td class="p-2 text-left"><?= htmlspecialchars($catNameER) ?></td>
          <td class="p-2 text-right"><?= 'R$ '.number_format($media3Cat[$catNameER] ?? 0,2,',','.') ?></td>
          <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format((($media3Cat[$catNameER] ?? 0)/$media3Rec)*100,2,',','.') .'%' : '-' ?></td>
          <td class="p-2 text-right">
            <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
                   value="<?= number_format($media3Cat[$catNameER] ?? 0,2,',','.') ?>">
          </td>
          <td class="p-2 text-center">
            <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
                   style="width:60px;" value="<?= $media3Rec > 0 ? number_format((($media3Cat[$catNameER] ?? 0)/$media3Rec)*100,2,',','.') : '-' ?>">
          </td>
          <td class="p-2 text-right">-</td> <!-- Diferença -->
          <td class="p-2 text-right"><?= isset($metasArray[$catNameER]['']) ? 'R$ '.number_format($metasArray[$catNameER][''],2,',','.') : '' ?></td>
          <td class="p-2 text-center">
            <?php
              if ($metaReceita > 0 && isset($metasArray[$catNameER][''])) {
                echo number_format(($metasArray[$catNameER][''] / $metaReceita) * 100, 2, ',', '.') . '%';
              } else { echo '-'; }
            ?>
          </td>
          <td class="p-2 text-right"><?= 'R$ '.number_format($atualCat[$catNameER] ?? 0,2,',','.') ?></td>
          <td class="p-2 text-center">
            <?php
              $meta_er_val = $metasArray[$catNameER][''] ?? null;
              $realizado_er_val = $atualCat[$catNameER] ?? 0;
              if (isset($meta_er_val) && $meta_er_val != 0) {
                echo number_format(($realizado_er_val / $meta_er_val) * 100, 2, ',', '.') . '%';
              } else { echo '-'; }
            ?>
          </td>
          <td class="p-2 text-center">
            <?php 
              $meta_val = $metasArray[$catNameER][''] ?? null;
              $realizado_val = $atualCat[$catNameER] ?? 0;
              if (isset($meta_val)) {
                $comparacao = $meta_val - $realizado_val;
                $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
                echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
              } else { echo '-'; } ?>
          </td>
        </tr>
        <?php foreach(($matrizOrdenada[$catNameER] ?? []) as $sub => $mesValores): ?>
          <?php if($sub): ?>
           <tr class="dre-sub">
              <td class="p-2 text-left" style="padding-left:2em;"><?= htmlspecialchars($sub) ?></td>
              <td class="p-2 text-right"><?= 'R$ '.number_format($media3Sub[$catNameER][$sub] ?? 0,2,',','.') ?></td>
              <td class="p-2 text-center">
                <?= $media3Rec > 0 ? number_format((($media3Sub[$catNameER][$sub] ?? 0) / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?>
              </td>
              <td class="p-2 text-right">
                <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
                       value="<?= number_format($media3Sub[$catNameER][$sub] ?? 0, 2, ',', '.') ?>">
              </td>
              <td class="p-2 text-center">
                <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
                       style="width:60px;" value="<?= $media3Rec > 0 ? number_format((($media3Sub[$catNameER][$sub] ?? 0) / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?>">
              </td>
              <td class="p-2 text-right">-</td>
              <td class="p-2 text-right"><?= isset($metasArray[$catNameER][$sub]) ? 'R$ ' . number_format($metasArray[$catNameER][$sub], 2, ',', '.') : '' ?></td>
              <td class="p-2 text-center">
                <?php
                  if ($metaReceita > 0 && isset($metasArray[$catNameER][$sub])) {
                    echo number_format(($metasArray[$catNameER][$sub] / $metaReceita) * 100, 2, ',', '.') . '%';
                  } else { echo '-'; }
                ?>
              </td>
              <td class="p-2 text-right"><?= 'R$ ' . number_format($atualSub[$catNameER][$sub] ?? 0, 2, ',', '.') ?></td>
              <td class="p-2 text-center">
                <?php
                  $meta_ers_val = $metasArray[$catNameER][$sub] ?? null;
                  $realizado_ers_val = $atualSub[$catNameER][$sub] ?? 0;
                  if (isset($meta_ers_val) && $meta_ers_val != 0) {
                    echo number_format(($realizado_ers_val / $meta_ers_val) * 100, 2, ',', '.') . '%';
                  } else { echo '-'; }
                ?>
              </td>
              <td class="p-2 text-center">
                <?php 
                  $meta_val = $metasArray[$catNameER][$sub] ?? null;
                  $realizado_val = $atualSub[$catNameER][$sub] ?? 0;
                  if (isset($meta_val)) {
                    $comparacao = $meta_val - $realizado_val;
                    $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
                    echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
                  } else { echo '-'; } ?>
              </td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>
      <!-- ==================== [FIM DA SEÇÃO Z - ENTRADA DE REPASSE] ==================== -->

      <!-- Z - SAIDA DE REPASSE -->
      <?php
        $catNameSR = 'Z - SAIDA DE REPASSE';
        if(isset($matrizOrdenada[$catNameSR])):
      ?>
        <tr class="dre-cat">
          <td class="p-2 text-left"><?= htmlspecialchars($catNameSR) ?></td>
          <td class="p-2 text-right"><?= 'R$ '.number_format($media3Cat[$catNameSR] ?? 0,2,',','.') ?></td>
          <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format((($media3Cat[$catNameSR] ?? 0)/$media3Rec)*100,2,',','.') .'%' : '-' ?></td>
          <td class="p-2 text-right">
            <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
                   value="<?= number_format($media3Cat[$catNameSR] ?? 0,2,',','.') ?>">
          </td>
          <td class="p-2 text-center">
            <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
                   style="width:60px;" value="<?= $media3Rec > 0 ? number_format((($media3Cat[$catNameSR] ?? 0)/$media3Rec)*100,2,',','.') : '-' ?>">
          </td>
          <td class="p-2 text-right">-</td> <!-- Diferença -->
          <td class="p-2 text-right"><?= isset($metasArray[$catNameSR]['']) ? 'R$ '.number_format($metasArray[$catNameSR][''],2,',','.') : '' ?></td>
          <td class="p-2 text-center">
            <?php
              if ($metaReceita > 0 && isset($metasArray[$catNameSR][''])) {
                echo number_format(($metasArray[$catNameSR][''] / $metaReceita) * 100, 2, ',', '.') . '%';
              } else { echo '-'; }
            ?>
          </td>
          <td class="p-2 text-right"><?= 'R$ '.number_format($atualCat[$catNameSR] ?? 0,2,',','.') ?></td>
          <td class="p-2 text-center">
            <?php
              $meta_sr_val = $metasArray[$catNameSR][''] ?? null;
              $realizado_sr_val = $atualCat[$catNameSR] ?? 0;
              if (isset($meta_sr_val) && $meta_sr_val != 0) {
                echo number_format(($realizado_sr_val / $meta_sr_val) * 100, 2, ',', '.') . '%';
              } else { echo '-'; }
            ?>
          </td>
          <td class="p-2 text-center">
            <?php 
              $meta_val = $metasArray[$catNameSR][''] ?? null;
              $realizado_val = $atualCat[$catNameSR] ?? 0;
              if (isset($meta_val)) {
                $comparacao = $meta_val - $realizado_val;
                $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
                echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
              } else { echo '-'; } ?>
          </td>
        </tr>
        <?php foreach(($matrizOrdenada[$catNameSR] ?? []) as $sub => $mesValores): ?>
          <?php if($sub): ?>
           <tr class="dre-sub">
              <td class="p-2 text-left" style="padding-left:2em;"><?= htmlspecialchars($sub) ?></td>
              <td class="p-2 text-right"><?= 'R$ '.number_format($media3Sub[$catNameSR][$sub] ?? 0,2,',','.') ?></td>
              <td class="p-2 text-center">
                <?= $media3Rec > 0 ? number_format((($media3Sub[$catNameSR][$sub] ?? 0) / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?>
              </td>
              <td class="p-2 text-right">
                <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
                       value="<?= number_format($media3Sub[$catNameSR][$sub] ?? 0, 2, ',', '.') ?>">
              </td>
              <td class="p-2 text-center">
                <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
                       style="width:60px;" value="<?= $media3Rec > 0 ? number_format((($media3Sub[$catNameSR][$sub] ?? 0) / $media3Rec) * 100, 2, ',', '.') . '%' : '-' ?>">
              </td>
              <td class="p-2 text-right">-</td>
              <td class="p-2 text-right"><?= isset($metasArray[$catNameSR][$sub]) ? 'R$ ' . number_format($metasArray[$catNameSR][$sub], 2, ',', '.') : '' ?></td>
              <td class="p-2 text-center">
                <?php
                  if ($metaReceita > 0 && isset($metasArray[$catNameSR][$sub])) {
                    echo number_format(($metasArray[$catNameSR][$sub] / $metaReceita) * 100, 2, ',', '.') . '%';
                  } else { echo '-'; }
                ?>
              </td>
              <td class="p-2 text-right"><?= 'R$ ' . number_format($atualSub[$catNameSR][$sub] ?? 0, 2, ',', '.') ?></td>
              <td class="p-2 text-center">
                <?php
                  $meta_srs_val = $metasArray[$catNameSR][$sub] ?? null;
                  $realizado_srs_val = $atualSub[$catNameSR][$sub] ?? 0;
                  if (isset($meta_srs_val) && $meta_srs_val != 0) {
                    echo number_format(($realizado_srs_val / $meta_srs_val) * 100, 2, ',', '.') . '%';
                  } else { echo '-'; }
                ?>
              </td>
              <td class="p-2 text-center">
                <?php 
                  $meta_val = $metasArray[$catNameSR][$sub] ?? null;
                  $realizado_val = $atualSub[$catNameSR][$sub] ?? 0;
                  if (isset($meta_val)) {
                    $comparacao = $meta_val - $realizado_val;
                    $corComparacao = ($comparacao >= 0) ? 'text-green-400' : 'text-red-400';
                    echo '<span class="' . $corComparacao . '">R$ ' . number_format($comparacao, 2, ',', '.') . '</span>';
                  } else { echo '-'; } ?>
              </td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>
      <!-- ==================== [FIM DA SEÇÃO Z - SAIDA DE REPASSE] ==================== -->

      <!-- 10. INVESTIMENTO INTERNO, INVESTIMENTO EXTERNO e AMORTIZAÇÃO (editáveis) -->
      <?php
        foreach(['INVESTIMENTO INTERNO','INVESTIMENTO EXTERNO','AMORTIZAÇÃO'] as $catName): // AMORTIZAÇÃO pode ter meta, mesmo que não seja exibida como editável em alguns cenários
          if(isset($matrizOrdenada[$catName])):
      ?>
        <tr class="dre-cat">
  <td class="p-2 text-left"><?= $catName ?></td>
  <td class="p-2 text-right"><?= 'R$ '.number_format($media3Cat[$catName] ?? 0,2,',','.') ?></td>
  <td class="p-2 text-center"><?= $media3Rec > 0 ? number_format((($media3Cat[$catName] ?? 0)/$media3Rec)*100,2,',','.') .'%' : '-' ?></td>
  <td class="p-2 text-right">
    <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
           value="<?= number_format($media3Cat[$catName] ?? 0,2,',','.') ?>">
  </td>
  <td class="p-2 text-center">
    <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
           style="width:60px;" value="<?= $media3Rec > 0 ? number_format((($media3Cat[$catName] ?? 0)/$media3Rec)*100,2,',','.') : '-' ?>">
  </td>
  <td class="p-2 text-right">-</td> <!-- Diferença -->
  <td class="p-2 text-right"><?= isset($metasArray[$catName]['']) ? 'R$ '.number_format($metasArray[$catName][''],2,',','.') : '' ?></td>
  <td class="p-2 text-center"><?= ($metaReceita > 0 && isset($metasArray[$catName][''])) ? number_format(($metasArray[$catName]['']/$metaReceita)*100,2,',','.') .'%' : '' ?></td>
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
      } else { echo '-'; } ?>
  </td>
</tr>
    <?php foreach(($matrizOrdenada[$catName] ?? []) as $sub => $mesValores): ?>
      <?php if($sub): ?>
        <tr class="dre-sub">
          <td class="p-2 text-left" style="padding-left:2em;"><?= htmlspecialchars($sub) ?></td>
          <td class="p-2 text-right"><?= 'R$ '.number_format($media3Sub[$catName][$sub] ?? 0,2,',','.') ?></td>
          <td class="p-2 text-center">
            <?= $media3Rec>0 ? number_format((($media3Sub[$catName][$sub] ?? 0)/$media3Rec)*100,2,',','.') .'%' : '-' ?>
          </td>
          <td class="p-2 text-right">
            <input type="text" class="simul-valor font-semibold bg-gray-800 text-yellow-400 text-right w-24 rounded px-1"
                   value="<?= number_format($media3Sub[$catName][$sub] ?? 0,2,',','.') ?>">
          </td>
          <td class="p-2 text-center">
            <input type="text" class="simul-perc font-semibold bg-gray-800 text-yellow-400 text-center rounded px-1"
                   style="width:60px;" value="<?= $media3Rec>0 ? number_format((($media3Sub[$catName][$sub] ?? 0)/$media3Rec)*100,2,',','.') : '-' ?>">
          </td>
          <td class="p-2 text-right">-</td>
          <td class="p-2 text-right"><?= isset($metasArray[$catName][$sub]) ? 'R$ ' . number_format($metasArray[$catName][$sub], 2, ',', '.') : '' ?></td>
          <td class="p-2 text-center">
            <?php
              if ($metaReceita > 0 && isset($metasArray[$catName][$sub])) {
                echo number_format(($metasArray[$catName][$sub] / $metaReceita) * 100, 2, ',', '.') . '%';
              } else { echo '-'; }
            ?>
          </td>
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
              } else { echo '-'; } ?>
          </td>
        </tr>
      <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>
<?php endforeach; ?>

      <!-- FLUXO DE CAIXA (META - igual acompanhamento_financeiro) -->
      <?php
        // Cálculo igual acompanhamento_financeiro.php: sempre calcular a partir das metas das demais linhas
        $metaInvestInterno = $metasArray['INVESTIMENTO INTERNO'][''] ?? 0;
        $metaInvestExterno = $metasArray['INVESTIMENTO EXTERNO'][''] ?? 0;
        $metaSaidaRepasse  = $metasArray['Z - SAIDA DE REPASSE'][''] ?? 0;
        $metaAmortizacao   = $metasArray['AMORTIZAÇÃO'][''] ?? 0;
        $metaEntradaRepasse = $metasArray['Z - ENTRADA DE REPASSE'][''] ?? 0;
        // RECEITAS NAO OPERACIONAIS: somar apenas subcategorias, nunca a principal
        $metaTotalRNO = 0;
        if (!empty($metasArray['RECEITAS NAO OPERACIONAIS'])) {
          foreach ($metasArray['RECEITAS NAO OPERACIONAIS'] as $sub => $valor) {
            if ($sub !== '' && is_numeric($valor)) $metaTotalRNO += $valor;
          }
        }
        // Lucro Líquido da meta: Receita - Tributos - Custo Variável - Custo Fixo - Despesa Fixa - Despesa Venda
        $metaLucroLiquidoDisplay = $metasArray[$keyLucroLiquidoPHP][''] ?? $metaLucroLiquido;
        $metaFluxoCaixaCalculado = ($metaLucroLiquidoDisplay + $metaTotalRNO + $metaEntradaRepasse) - ($metaInvestInterno + $metaInvestExterno + $metaSaidaRepasse + $metaAmortizacao);
      ?>
      <tr id="rowFluxoCaixa" class="dre-cat" style="background:#082f49; color: #e0f2fe; font-weight: bold;">
        <td class="p-2 text-left">FLUXO DE CAIXA</td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($metaFluxoCaixaCalculado,2,',','.') ?></td>
        <td class="p-2 text-center"><?= $metaReceita > 0 ? number_format(($metaFluxoCaixaCalculado / $metaReceita) * 100, 2, ',', '.') . '%' : '-' ?></td>
        <td class="p-2 text-right">
          <input type="text" class="simul-valor font-bold bg-gray-700 text-yellow-300 text-right w-24 rounded px-1" readonly>
        </td>
        <td class="p-2 text-center">
          <input type="text" class="simul-perc font-bold bg-gray-700 text-yellow-300 text-center rounded px-1" style="width:60px;" readonly>
        </td>
        <td class="p-2 text-right">-</td> <!-- Diferença -->
        <td class="p-2 text-right">
            <?= 'R$ '.number_format($metaFluxoCaixaCalculado,2,',','.') ?>
        </td>
        <td class="p-2 text-center">
            <?php
              // % Meta s/ FAT. para FLUXO DE CAIXA
              if ($metaReceita > 0) {
                echo number_format(($metaFluxoCaixaCalculado / $metaReceita) * 100, 2, ',', '.') . '%';
              } else { echo '-'; }
            ?>
        </td>
        <td class="p-2 text-right"><?= 'R$ '.number_format($atualFluxoCaixa,2,',','.') ?></td>
        <td class="p-2 text-center">
            <?php
                if($metaFluxoCaixaCalculado != 0) {
                    echo number_format(($atualFluxoCaixa / $metaFluxoCaixaCalculado) * 100, 2, ',', '.') . '%';
                } else { echo '-';}
            ?>
        </td> 
        <td class="p-2 text-center">-</td> <!-- Comp. Meta -->
      </tr>
    </tbody>
  </table>
  
</main>

<!-- Scripts de Atualização, Toggle etc. (mantidos) -->
<script>
// Funções Utilitárias
function parseBRL(str) {
  const stringValue = String(str || '');
  return parseFloat(stringValue.replace(/[R$\s\.]/g, '').replace('%', '').replace(',', '.')) || 0; // Adicionado .replace('%', '')
}

function formatSimValue(value) { // Formata para campos de input de simulação (sem R$)
  return (value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatSimPerc(value, base) { // Formata percentual para campos de input de simulação
  if (base === 0 || isNaN(base) || isNaN(value)) return '0,00%';
  return ((value / base) * 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); // Removido o '%'
}

function getReceitaBrutaSimulada() {
  const inputReceita = document.querySelector('.simul-valor[data-receita="1"]');
  return inputReceita ? parseBRL(inputReceita.value) : 0;
}

function findCategoryRow(categoryName) {
  const catRows = document.querySelectorAll('tr.dre-cat');
  for (const row of catRows) {
    const firstCell = row.cells[0];
    if (firstCell && firstCell.textContent.trim().toUpperCase().startsWith(categoryName.toUpperCase())) {
      return row;
    }
  }
  return null;
}

function getSimulatedValueFromInput(row) {
    if (!row) return 0;
    const input = row.querySelector('input.simul-valor');
    return input ? parseBRL(input.value) : 0;
}

// Atualiza os totais das categorias principais com base em suas subcategorias (se houver)
function atualizarTotaisCategorias() {
  // Lista de todas as categorias principais que devem ser calculadas como soma de subcategorias
  const categoriasParaSomar = [
    'TRIBUTOS', 'CUSTO VARIÁVEL', 'DESPESA VENDA', 'CUSTO FIXO', 'DESPESA FIXA',
    'INVESTIMENTO INTERNO', 'INVESTIMENTO EXTERNO', 'AMORTIZAÇÃO', 'Z - SAIDA DE REPASSE', 'Z - ENTRADA DE REPASSE'
  ];

  categoriasParaSomar.forEach(function(catName) {
    const catRow = findCategoryRow(catName);
    if (!catRow) return;
    
    const catSimulValorInput = catRow.querySelector('input.simul-valor');
    const catSimulPercInput = catRow.querySelector('input.simul-perc');
    if (!catSimulValorInput || !catSimulPercInput) return;

    let subtotalValor = 0;
    let subtotalPercentual = 0;
    let hasSubCategories = false;
    let currentRow = catRow.nextElementSibling;

    // Soma todas as subcategorias desta categoria (tanto valor quanto percentual)
    while (currentRow && currentRow.classList.contains('dre-sub')) {
      hasSubCategories = true;
      const subInputValor = currentRow.querySelector('input.simul-valor');
      const subInputPerc = currentRow.querySelector('input.simul-perc');
      
      if (subInputValor) {
        subtotalValor += parseBRL(subInputValor.value);
      }
      
      if (subInputPerc) {
        subtotalPercentual += parseBRL(subInputPerc.value);
      }
      
      currentRow = currentRow.nextElementSibling;
    }

    // Atualiza tanto o valor quanto o percentual da categoria principal
    // apenas se houver subcategorias e se os campos não estiverem sendo editados no momento
    if (hasSubCategories) {
      if (catSimulValorInput !== document.activeElement) {
        catSimulValorInput.value = formatSimValue(subtotalValor);
      }
      
      if (catSimulPercInput !== document.activeElement) {
        // Formata como percentual: adiciona o símbolo % e formata com 2 casas decimais
        catSimulPercInput.value = subtotalPercentual.toLocaleString('pt-BR', { 
          minimumFractionDigits: 2, 
          maximumFractionDigits: 2 
        }) + '%';
      }
    }
  });
}

// Atualiza os campos de percentual de simulação para todas as linhas com inputs .simul-valor
function atualizarPercentuaisSimulacao() {
  const receitaBrutaSimulada = getReceitaBrutaSimulada();
  document.querySelectorAll('tr').forEach(row => {
    const simulValorInput = row.querySelector('input.simul-valor');
    const simulPercInput = row.querySelector('input.simul-perc');

    if (simulValorInput && simulPercInput) { // Atualiza o percentual baseado no valor
        // Caso 1: O input de percentual é somente leitura (o valor absoluto é o primário, o percentual é derivado).
        if (simulPercInput.readOnly) {
            const valor = parseBRL(simulValorInput.value);
            // Não atualiza o % da Receita Bruta aqui, pois ele é sempre 100% e seu input é readOnly.
            if (!simulValorInput.hasAttribute('data-receita')) { // skip RB
                simulPercInput.value = formatSimPerc(valor, receitaBrutaSimulada);
            }
        } 
        // Caso 2: O input de percentual é editável (o percentual é o primário, o valor absoluto é derivado).
        else {
            // Só atualiza o valor absoluto se o input de percentual não estiver sendo ativamente editado.
            // Isso evita sobrescrever a digitação do usuário no campo de percentual.
            if (simulPercInput !== document.activeElement) {
                const percentual = parseBRL(simulPercInput.value); // Pega o percentual definido pelo usuário
                const novoValor = (percentual / 100) * receitaBrutaSimulada; // Calcula o novo valor absoluto
                simulValorInput.value = formatSimValue(novoValor); // Atualiza o campo de valor absoluto
                // NÃO atualiza simulPercInput.value aqui, pois ele é a entrada fixa do usuário.
            }
        }
    }
  });
}

// Atualiza linhas sintéticas (RECEITA LÍQUIDA, LUCRO BRUTO, etc)
function recalcSinteticas() {
  const receitaBrutaSimulada = getReceitaBrutaSimulada();

  const rowTributos = document.querySelector('tr.cat_trib');
  const simTributos = getSimulatedValueFromInput(rowTributos);

  const rowCustoVariavel = document.querySelector('tr.cat_cvar');
  const simCustoVariavel = getSimulatedValueFromInput(rowCustoVariavel);

  // 1. RECEITA LÍQUIDA (rowFatLiquido)
  const simReceitaLiquida = receitaBrutaSimulada - simTributos;
  const rowRL = document.getElementById('rowFatLiquido');
  if (rowRL) {
    const inputRLValor = rowRL.querySelector('input.simul-valor');
    const inputRLPerc = rowRL.querySelector('input.simul-perc');
    if (inputRLValor) inputRLValor.value = formatSimValue(simReceitaLiquida);
    if (inputRLPerc) inputRLPerc.value = formatSimPerc(simReceitaLiquida, receitaBrutaSimulada);
  }

  // 2. LUCRO BRUTO (rowLucroBruto)
  const simLucroBruto = simReceitaLiquida - simCustoVariavel;
  const rowLB = document.getElementById('rowLucroBruto');
  if (rowLB) {
    const inputLBValor = rowLB.querySelector('input.simul-valor');
    const inputLBPerc = rowLB.querySelector('input.simul-perc');
    if (inputLBValor) inputLBValor.value = formatSimValue(simLucroBruto);
    if (inputLBPerc) inputLBPerc.value = formatSimPerc(simLucroBruto, receitaBrutaSimulada);
  }

  // Despesas para Lucro Líquido
  const simCustoFixo = getSimulatedValueFromInput(findCategoryRow('CUSTO FIXO'));
  const simDespesaFixa = getSimulatedValueFromInput(findCategoryRow('DESPESA FIXA'));
  const simDespesaVenda = getSimulatedValueFromInput(findCategoryRow('DESPESA VENDA'));
  const totalOutrasDespesasSim = simCustoFixo + simDespesaFixa + simDespesaVenda;

  // 3. LUCRO LÍQUIDO
  const simLucroLiquido = simLucroBruto - totalOutrasDespesasSim;
  const rowLL = findCategoryRow('LUCRO LÍQUIDO');
  if (rowLL) {
    const inputLLValor = rowLL.querySelector('input.simul-valor');
    const inputLLPerc = rowLL.querySelector('input.simul-perc');
    if (inputLLValor) inputLLValor.value = formatSimValue(simLucroLiquido);
    if (inputLLPerc) inputLLPerc.value = formatSimPerc(simLucroLiquido, receitaBrutaSimulada);
  }

  // 4. FLUXO DE CAIXA
  // (LUCRO LIQUIDO + RECEITAS NAO OPERACIONAIS + Z - ENTRADA DE REPASSE) - (INVESTIMENTO INTERNO + INVESTIMENTO EXTERNO + Z - SAIDA DE REPASSE)
  let simReceitasNaoOp = 0;
  document.querySelectorAll('input.simul-valor[data-cat="RECEITAS NAO OPERACIONAIS"]').forEach(input => {
    simReceitasNaoOp += parseBRL(input.value);
  });

  const simEntradaRepasse = getSimulatedValueFromInput(findCategoryRow('Z - ENTRADA DE REPASSE'));

  const simInvestInterno = getSimulatedValueFromInput(findCategoryRow('INVESTIMENTO INTERNO'));
  const simInvestExterno = getSimulatedValueFromInput(findCategoryRow('INVESTIMENTO EXTERNO'));
  const simSaidaRepasse  = getSimulatedValueFromInput(findCategoryRow('Z - SAIDA DE REPASSE'));
  const simAmortizacao   = getSimulatedValueFromInput(findCategoryRow('AMORTIZAÇÃO')); // Será 0 se a linha não existir

  const simFluxoCaixa = (simLucroLiquido + simReceitasNaoOp + simEntradaRepasse) - (simInvestInterno + simInvestExterno + simSaidaRepasse + simAmortizacao);

  const rowFC = document.getElementById('rowFluxoCaixa');
  if (rowFC) {
    const inputFCValor = rowFC.querySelector('input.simul-valor');
    const inputFCPerc = rowFC.querySelector('input.simul-perc');
    if (inputFCValor) inputFCValor.value = formatSimValue(simFluxoCaixa);
    if (inputFCPerc) inputFCPerc.value = formatSimPerc(simFluxoCaixa, receitaBrutaSimulada);
  }

  // --- ATUALIZAÇÃO RECEITAS NÃO OPERACIONAIS (RNO) ---
  let totalGeralSimulRNO = 0;

  // Passo 1: Calcular e atualizar totais para cada CATEGORIA de RNO (linhas dre-subcat-l1)
  document.querySelectorAll('tr.dre-subcat-l1').forEach(rowCatRNO => {
    const dataCatKey = rowCatRNO.querySelector('[data-simul-valor-rno-cat]')?.dataset.simulValorRnoCat;
    if (!dataCatKey) return;

    let subTotalCategoriaRNO = 0;
    // Seleciona os inputs da subcategoria L2 que pertencem a esta categoria L1
    document.querySelectorAll(`input.simul-valor[data-cat="RECEITAS NAO OPERACIONAIS"][data-sub-cat="${dataCatKey}"]`).forEach(inputSubCatL2RNO => {
        subTotalCategoriaRNO += parseBRL(inputSubCatL2RNO.value);
    });

    const tdValorCatRNO = rowCatRNO.querySelector(`td[data-simul-valor-rno-cat="${dataCatKey}"]`);
    if (tdValorCatRNO) {
        tdValorCatRNO.textContent = 'R$ ' + formatSimValue(subTotalCategoriaRNO);
    }
    const tdPercCatRNO = rowCatRNO.querySelector(`td[data-simul-perc-rno-cat="${dataCatKey}"]`);
    if (tdPercCatRNO) {
        tdPercCatRNO.textContent = formatSimPerc(subTotalCategoriaRNO, receitaBrutaSimulada);
    }
    totalGeralSimulRNO += subTotalCategoriaRNO; // Acumula para o total geral de RNO
  });

  // Passo 2: Atualizar o total geral da linha principal "RECEITAS NAO OPERACIONAIS" (dre-cat-principal)
  const tdTotalGeralSimulRNO = document.querySelector('td.simul-total-cat[data-cat-total-simul="RECEITAS NAO OPERACIONAIS"]');
  if (tdTotalGeralSimulRNO) {
    tdTotalGeralSimulRNO.textContent = 'R$ ' + formatSimValue(totalGeralSimulRNO);
  }

  const tdPercGeralSimulRNO = document.querySelector('td.simul-perc-cat[data-cat-perc-simul="RECEITAS NAO OPERACIONAIS"]');
  if (tdPercGeralSimulRNO) {
    tdPercGeralSimulRNO.textContent = formatSimPerc(totalGeralSimulRNO, receitaBrutaSimulada);
  }
}

function atualizarDiferencas() {
    document.querySelectorAll('#tabelaSimulacao tbody tr').forEach(row => {
        const cells = row.cells;
        // Garante que a linha tem colunas suficientes e a coluna de diferença existe.
        // A coluna de "Diferença (R$)" é a sexta (índice 5).
        if (cells.length < 6 || !cells[5]) return;

        const tdMedia3m = cells[1];
        const tdSimulacaoValorContainer = cells[3]; // Onde o valor da simulação está (pode ser input ou td)
        const tdDiferenca = cells[5];

        const media3mValor = parseBRL(tdMedia3m.textContent);
        let simulacaoValor = 0;

        const inputSimulValor = tdSimulacaoValorContainer.querySelector('input.simul-valor');
        if (inputSimulValor) {
            simulacaoValor = parseBRL(inputSimulValor.value);
        } else if (tdSimulacaoValorContainer.classList.contains('simul-total-cat') || tdSimulacaoValorContainer.hasAttribute('data-simul-valor-rno-cat')) {
            // Para linhas de total RNO (principal e L1) que têm o valor em um <td>
            simulacaoValor = parseBRL(tdSimulacaoValorContainer.textContent);
        } else {
            // Se não for um input editável ou um TD de total RNO, não calcula/atualiza a diferença.
            // Mantém o valor existente (ex: '-') se a linha não for aplicável.
            return;
        }

        const diferenca = media3mValor - simulacaoValor;
        tdDiferenca.textContent = 'R$ ' + formatSimValue(diferenca);
    });
}


function initializeDREToggle() {
    // Adiciona listeners de clique para linhas expansíveis
    document.querySelectorAll('tr.dre-cat, tr.dre-cat-principal, tr.dre-subcat-l1').forEach(headerRow => {
        headerRow.addEventListener('click', function(event) {
            // Não faz nada se o clique foi em um input, select, button ou link dentro da linha de cabeçalho
            if (event.target.tagName === 'INPUT' || event.target.tagName === 'SELECT' || event.target.tagName === 'BUTTON' || event.target.closest('a')) {
                return;
            }

            let currentRow = this.nextElementSibling;
            const isCat = this.classList.contains('dre-cat');
            const isCatPrincipalRNO = this.classList.contains('dre-cat-principal');
            const isSubCatL1 = this.classList.contains('dre-subcat-l1');

            while (currentRow) {
                let stopIterating = false;
                if (isCat) {
                    // Alterna a visibilidade das linhas .dre-sub que são filhas desta .dre-cat
                    if (currentRow.classList.contains('dre-sub')) {
                        currentRow.classList.toggle('dre-hide');
                    } else if (currentRow.classList.contains('dre-cat') || 
                               currentRow.classList.contains('dre-cat-principal') ||
                               currentRow.classList.contains('dre-subcat-l1')) {
                        // Para quando encontrar a próxima categoria principal ou um bloco RNO
                        stopIterating = true;
                    }
                } else if (isCatPrincipalRNO) { // Se clicou no cabeçalho principal de RNO
                    // Alterna a visibilidade das linhas .dre-subcat-l1
                    if (currentRow.classList.contains('dre-subcat-l1')) {
                        currentRow.classList.toggle('dre-hide');
                        // Se estiver recolhendo L1, garante que seus filhos L2 também sejam recolhidos
                        if (currentRow.classList.contains('dre-hide')) {
                            let subL2 = currentRow.nextElementSibling;
                            while(subL2 && subL2.classList.contains('dre-subcat-l2')) {
                                subL2.classList.add('dre-hide');
                                subL2 = subL2.nextElementSibling;
                            }
                        }
                    } else if (currentRow.classList.contains('dre-cat') || currentRow.classList.contains('dre-cat-principal')) {
                        stopIterating = true; // Para ao encontrar outra categoria principal DRE ou outro bloco RNO
                    }
                } else if (isSubCatL1) {
                    // Alterna a visibilidade das linhas .dre-subcat-l2 que são filhas desta .dre-subcat-l1
                    if (currentRow.classList.contains('dre-subcat-l2')) {
                        currentRow.classList.toggle('dre-hide');
                    } else if (currentRow.classList.contains('dre-subcat-l1') || 
                               currentRow.classList.contains('dre-cat-principal') ||
                               currentRow.classList.contains('dre-cat')) {
                        // Para quando encontrar a próxima subcategoria L1 de RNO, o cabeçalho principal de RNO ou uma categoria DRE principal
                        stopIterating = true;
                    }
                }

                if (stopIterating) {
                    break;
                }
                currentRow = currentRow.nextElementSibling;
            }
        });
    });

    // Define o estado inicial: todos os subitens ocultos
    document.querySelectorAll('tr.dre-sub, tr.dre-subcat-l1, tr.dre-subcat-l2').forEach(subRow => {
       subRow.classList.add('dre-hide');
    });
}

document.addEventListener('DOMContentLoaded', function() {
  // Função para recalcular tudo
  function recalcularTudo() {
    atualizarTotaisCategorias(); // Soma subcategorias para totais de categoria (se aplicável)
    atualizarPercentuaisSimulacao(); // Atualiza % de linhas editáveis
    recalcSinteticas(); // Calcula linhas sintéticas (Receita Líquida, Lucro Bruto, Lucro Líquido)
    atualizarDiferencas(); // Adicionado para calcular e atualizar a coluna "Diferença (R$)"
  }

  // Adiciona listener para todos os inputs de simulação de valor
  document.querySelectorAll('.simul-valor').forEach(function(input) {
    input.addEventListener('input', function() {
      recalcularTudo();
    });
  });

  // Adiciona listener para os inputs de simulação de percentual (caso o usuário edite diretamente o %)
  document.querySelectorAll('input.simul-perc').forEach(function(inputPerc) {
    inputPerc.addEventListener('input', function() {
        const row = inputPerc.closest('tr');
        const inputValor = row.querySelector('input.simul-valor');
        const receitaBrutaSimulada = getReceitaBrutaSimulada();

        if (inputValor && !inputPerc.readOnly && receitaBrutaSimulada > 0) { // Condição alterada para verificar se o CAMPO PERCENTUAL é editável
            const percentual = parseBRL(inputPerc.value); // parseBRL pode lidar com '%'
            const novoValor = (percentual / 100) * receitaBrutaSimulada;
            inputValor.value = formatSimValue(novoValor);
            recalcularTudo(); // Recalcula tudo após alterar valor via percentual
        }
    });
  });

  function applyInputRestrictions() {
    // Categorias onde as subcategorias têm percentual editável (e valor calculado)
    const percentEditableCategories = ['TRIBUTOS', 'CUSTO VARIÁVEL', 'DESPESA VENDA'];
    
    // Categorias onde as subcategorias têm valor editável (e percentual calculado)
    const valueEditableCategories = ['CUSTO FIXO', 'DESPESA FIXA', 'INVESTIMENTO INTERNO', 'INVESTIMENTO EXTERNO', 'AMORTIZAÇÃO', 'Z - SAIDA DE REPASSE', 'Z - ENTRADA DE REPASSE'];

    // Categorias principais que são sempre totais calculados (readonly)
    const mainCategoryNames = [
        'TRIBUTOS', 'CUSTO VARIÁVEL', 'DESPESA VENDA', 'CUSTO FIXO', 'DESPESA FIXA',
        'INVESTIMENTO INTERNO', 'INVESTIMENTO EXTERNO', 'AMORTIZAÇÃO', 'Z - SAIDA DE REPASSE', 'Z - ENTRADA DE REPASSE',
        'RECEITAS NAO OPERACIONAIS'
    ];

    // Linhas de resultado que são sempre calculadas (readonly)
    const calculatedResultRows = [
        'RECEITA LÍQUIDA (RECEITA BRUTA - TRIBUTOS)',
        'LUCRO BRUTO (RECEITA LÍQUIDA - CUSTO VARIÁVEL)',
        'LUCRO LÍQUIDO',
        'FLUXO DE CAIXA'
    ];

    let categoriaAtualContexto = '';

    document.querySelectorAll('#tabelaSimulacao tbody tr').forEach(row => {
        const primeiroTd = row.cells[0];
        if (!primeiroTd) return;
        
        const inputValorSimul = row.querySelector('input.simul-valor');
        const inputPercSimul = row.querySelector('input.simul-perc');

        if (!inputValorSimul || !inputPercSimul) return;

        const nomeLinhaAtual = primeiroTd.textContent.trim();

        // Atualiza contexto da categoria atual
        if (row.classList.contains('dre-cat') || row.classList.contains('dre-cat-principal')) {
            categoriaAtualContexto = nomeLinhaAtual;
        }

        // Limpa restrições anteriores
        inputValorSimul.readOnly = false;
        inputPercSimul.readOnly = false;
        delete inputValorSimul.dataset.dynamicReadonly;
        delete inputPercSimul.dataset.dynamicReadonly;

        // 1. RECEITA BRUTA: valor editável, percentual readonly
        if (inputValorSimul.hasAttribute('data-receita')) {
            inputPercSimul.readOnly = true;
            return;
        }

        // 2. Categorias principais: ambos readonly (valores calculados pela soma das subcategorias)
        if (row.classList.contains('dre-cat') && mainCategoryNames.includes(nomeLinhaAtual)) {
            inputValorSimul.readOnly = true;
            inputPercSimul.readOnly = true;
            inputValorSimul.dataset.dynamicReadonly = 'true';
            inputPercSimul.dataset.dynamicReadonly = 'true';
            return;
        }

        // 3. Categoria principal de RECEITAS NAO OPERACIONAIS (dre-cat-principal): ambos readonly
        if (row.classList.contains('dre-cat-principal') && nomeLinhaAtual === 'RECEITAS NAO OPERACIONAIS') {
            inputValorSimul.readOnly = true;
            inputPercSimul.readOnly = true;
            inputValorSimul.dataset.dynamicReadonly = 'true';
            inputPercSimul.dataset.dynamicReadonly = 'true';
            return;
        }

        // 4. Categorias L1 de RECEITAS NAO OPERACIONAIS (dre-subcat-l1): ambos readonly
        if (row.classList.contains('dre-subcat-l1')) {
            inputValorSimul.readOnly = true;
            inputPercSimul.readOnly = true;
            inputValorSimul.dataset.dynamicReadonly = 'true';
            inputPercSimul.dataset.dynamicReadonly = 'true';
            return;
        }

        // 5. Linhas de resultado calculadas: ambos readonly
        if (calculatedResultRows.includes(nomeLinhaAtual)) {
            inputValorSimul.readOnly = true;
            inputPercSimul.readOnly = true;
            return;
        }

        // 6. Subcategorias (dre-sub): aplicar regras baseadas na categoria pai
        if (row.classList.contains('dre-sub') && categoriaAtualContexto) {
            if (percentEditableCategories.includes(categoriaAtualContexto)) {
                // Percentual editável, valor calculado
                inputPercSimul.readOnly = false;
                inputValorSimul.readOnly = true;
                inputValorSimul.dataset.dynamicReadonly = 'true';
            } else if (valueEditableCategories.includes(categoriaAtualContexto)) {
                // Valor editável, percentual calculado
                inputValorSimul.readOnly = false;
                inputPercSimul.readOnly = true;
                inputPercSimul.dataset.dynamicReadonly = 'true';
            }
            return;
        }

        // 7. Sub-subcategorias L2 de RECEITAS NAO OPERACIONAIS: valor editável, percentual calculado
        if (row.classList.contains('dre-subcat-l2') && 
            inputValorSimul.dataset.cat === "RECEITAS NAO OPERACIONAIS") {
            inputValorSimul.readOnly = false;
            inputPercSimul.readOnly = true;
            inputPercSimul.dataset.dynamicReadonly = 'true';
            return;
        }

        // 8. Sub-subcategorias L2 de Z - ENTRADA DE REPASSE: valor editável, percentual calculado
        if (row.classList.contains('dre-subcat-l2') && 
            inputValorSimul.dataset.cat === "Z - ENTRADA DE REPASSE") {
            inputValorSimul.readOnly = false;
            inputPercSimul.readOnly = true;
            inputPercSimul.dataset.dynamicReadonly = 'true';
            return;
        }

        // Caso padrão: ambos editáveis (para casos não cobertos)
        inputValorSimul.readOnly = false;
        inputPercSimul.readOnly = false;
    });
  }

  // Cálculo inicial ao carregar a página
  recalcularTudo();
  initializeDREToggle(); // Inicializa a funcionalidade de expandir/recolher
  applyInputRestrictions(); // Aplica as restrições de edição
  initializeSubcategoryOrdering(); // Inicializa a ordenação de subcategorias

  // Função para ordenação dinâmica de subcategorias
  function initializeSubcategoryOrdering() {
    const ordenacaoSelect = document.getElementById('ordenacaoSelect');
    
    ordenacaoSelect.addEventListener('change', function() {
      const criterio = this.value;
      ordenarSubcategorias(criterio);
    });

    // Aplicar ordenação inicial por nome
    ordenarSubcategorias('nome');
  }

  function ordenarSubcategorias(criterio) {
    // Remover highlights anteriores
    document.querySelectorAll('.subcategoria-highlight').forEach(el => {
      el.classList.remove('subcategoria-highlight');
    });

    // Encontrar todas as categorias principais
    const categoriasPrincipais = document.querySelectorAll('tr.dre-cat');
    
    categoriasPrincipais.forEach(function(catRow) {
      const subcategorias = [];
      let currentRow = catRow.nextElementSibling;
      
      // Coletar todas as subcategorias desta categoria
      while (currentRow && currentRow.classList.contains('dre-sub')) {
        subcategorias.push(currentRow);
        currentRow = currentRow.nextElementSibling;
      }
      
      if (subcategorias.length > 0) {
        // Adicionar classe de transição
        subcategorias.forEach(sub => sub.classList.add('subcategoria-ordenada'));
        
        // Ordenar as subcategorias conforme o critério
        subcategorias.sort(function(a, b) {
          return compararSubcategorias(a, b, criterio);
        });
        
        // Reinserir as subcategorias na ordem correta
        const proximaLinha = currentRow; // Linha após as subcategorias
        subcategorias.forEach(function(subRow, index) {
          if (proximaLinha) {
            proximaLinha.parentNode.insertBefore(subRow, proximaLinha);
          } else {
            catRow.parentNode.appendChild(subRow);
          }
          
          // Adicionar highlight temporário
          setTimeout(() => {
            subRow.classList.add('subcategoria-highlight');
          }, index * 50);
        });
        
        // Remover highlight após um tempo
        setTimeout(() => {
          subcategorias.forEach(sub => {
            sub.classList.remove('subcategoria-highlight');
          });
        }, 2000);
      }
    });

    // Ordenar também as subcategorias de Receitas Não Operacionais (L2)
    const categoriasRNO = document.querySelectorAll('tr.dre-subcat-l1');
    
    categoriasRNO.forEach(function(catRNORow) {
      const subcategoriasRNO = [];
      let currentRow = catRNORow.nextElementSibling;
      
      // Coletar todas as subcategorias L2 desta categoria L1
      while (currentRow && currentRow.classList.contains('dre-subcat-l2')) {
        subcategoriasRNO.push(currentRow);
        currentRow = currentRow.nextElementSibling;
      }
      
      if (subcategoriasRNO.length > 0) {
        // Adicionar classe de transição
        subcategoriasRNO.forEach(sub => sub.classList.add('subcategoria-ordenada'));
        
        // Ordenar as subcategorias L2 conforme o critério
        subcategoriasRNO.sort(function(a, b) {
          return compararSubcategorias(a, b, criterio);
        });
        
        // Reinserir as subcategorias L2 na ordem correta
        const proximaLinha = currentRow;
        subcategoriasRNO.forEach(function(subRow, index) {
          if (proximaLinha) {
            proximaLinha.parentNode.insertBefore(subRow, proximaLinha);
          } else {
            catRNORow.parentNode.appendChild(subRow);
          }
          
          // Adicionar highlight temporário
          setTimeout(() => {
            subRow.classList.add('subcategoria-highlight');
          }, index * 50);
        });
        
        // Remover highlight após um tempo
        setTimeout(() => {
          subcategoriasRNO.forEach(sub => {
            sub.classList.remove('subcategoria-highlight');
          });
        }, 2000);
      }
    });

    // Mostrar feedback visual temporário
    mostrarFeedbackOrdenacao(criterio);
  }

  function compararSubcategorias(rowA, rowB, criterio) {
    const nomeA = rowA.cells[0].textContent.trim();
    const nomeB = rowB.cells[0].textContent.trim();
    
    switch (criterio) {
      case 'nome':
        return nomeA.localeCompare(nomeB, 'pt-BR', { sensitivity: 'base' });
      
      case 'nome-desc':
        return nomeB.localeCompare(nomeA, 'pt-BR', { sensitivity: 'base' });
      
      case 'meta-valor':
      case 'meta-valor-asc':
        // Coluna 6 = Meta (valor)
        const metaA = parseBRL(rowA.cells[6] ? rowA.cells[6].textContent : '0');
        const metaB = parseBRL(rowB.cells[6] ? rowB.cells[6].textContent : '0');
        return criterio === 'meta-valor' ? metaB - metaA : metaA - metaB;
      
      case 'meta-perc':
      case 'meta-perc-asc':
        // Coluna 7 = Meta %
        const metaPercA = parseBRL(rowA.cells[7] ? rowA.cells[7].textContent : '0');
        const metaPercB = parseBRL(rowB.cells[7] ? rowB.cells[7].textContent : '0');
        return criterio === 'meta-perc' ? metaPercB - metaPercA : metaPercA - metaPercB;
      
      default:
        return 0;
    }
  }

  function mostrarFeedbackOrdenacao(criterio) {
    // Criar elemento de feedback se não existir
    let feedback = document.getElementById('ordenacao-feedback');
    if (!feedback) {
      feedback = document.createElement('div');
      feedback.id = 'ordenacao-feedback';
      feedback.className = 'fixed top-4 right-4 bg-blue-500 text-white px-4 py-2 rounded shadow-lg z-50 transition-opacity duration-300';
      document.body.appendChild(feedback);
    }

    // Definir mensagem baseada no critério
    let mensagem = '';
    switch (criterio) {
      case 'nome':
        mensagem = 'Subcategorias ordenadas: Nome (A-Z)';
        break;
      case 'nome-desc':
        mensagem = 'Subcategorias ordenadas: Nome (Z-A)';
        break;
      case 'meta-valor':
        mensagem = 'Subcategorias ordenadas: Meta - Valor (Maior→Menor)';
        break;
      case 'meta-valor-asc':
        mensagem = 'Subcategorias ordenadas: Meta - Valor (Menor→Maior)';
        break;
      case 'meta-perc':
        mensagem = 'Subcategorias ordenadas: Meta - % (Maior→Menor)';
        break;
      case 'meta-perc-asc':
        mensagem = 'Subcategorias ordenadas: Meta - % (Menor→Maior)';
        break;
    }

    feedback.textContent = mensagem;
    feedback.style.opacity = '1';

    // Remover feedback após 3 segundos
    setTimeout(function() {
      feedback.style.opacity = '0';
    }, 3000);
  }

  // Salvar Metas
  document.getElementById('salvarMetasBtn').addEventListener('click', function() {
    const botaoSalvar = this;
    botaoSalvar.disabled = true; // Desabilitar botão para evitar cliques duplos
    botaoSalvar.textContent = 'SALVANDO...';

    const metasParaSalvar = [];
    // Construir a data da meta com base no filtro da DRE (primeiro dia do mês)
    const dataMetaInput = document.getElementById('dataMetaInput');
    const dataMeta = dataMetaInput.value;

    if (!dataMeta) {
        alert('Por favor, selecione uma data para salvar as metas.');
        botaoSalvar.disabled = false;
        botaoSalvar.textContent = 'SALVAR METAS';
        return;
    }

    let categoriaAtualContexto = '';
    document.querySelectorAll('#tabelaSimulacao tbody tr').forEach(row => {
        const primeiroTd = row.cells[0];
        // Não precisamos do segundoTd aqui, pois o inputPercSimul já pega o valor
        if (!primeiroTd) return;

        const inputValorSimul = row.querySelector('input.simul-valor');

        // Atualizar contexto de categoria mesmo se a linha de categoria não for uma meta em si (ex: se for readonly)
        if (row.classList.contains('dre-cat')) {
             categoriaAtualContexto = primeiroTd.textContent.trim();
        }

        if (!inputValorSimul) {
            return;
        }

        // Define se esta é uma linha calculada especial cuja meta deve ser salva
        const specialCalculatedRowIds = ['rowFatLiquido', 'rowLucroBruto', 'rowFluxoCaixa'];
        const isSpecialCalculatedRowToSave = specialCalculatedRowIds.includes(row.id);

        // Pular se o input de valor for readonly E NÃO for uma das seguintes exceções:
        // 1. Linhas calculadas especiais que queremos salvar (Receita Líquida, Lucro Bruto, Fluxo de Caixa)
        // 2. Linhas de categoria principal (dre-cat) - queremos salvar os valores das categorias principais mesmo sendo calculados
        // 3. Inputs com 'data-dynamic-readonly' - são campos derivados mas ainda precisam ser salvos
        // 4. Inputs editáveis (não readonly) - sempre salvamos
        if (inputValorSimul.readOnly &&
            !isSpecialCalculatedRowToSave &&
            !row.classList.contains('dre-cat') &&
            !row.classList.contains('dre-cat-principal') &&
            !row.classList.contains('dre-subcat-l1') &&
            !inputValorSimul.hasAttribute('data-dynamic-readonly')) {
            return;
        }

        const valorMeta = parseBRL(inputValorSimul.value); // Valor do input de simulação
        let percentualMeta = 0;
        const inputPercSimul = row.querySelector('input.simul-perc');
        if (inputPercSimul) { // Verifica se existe um input de percentual para esta linha
            percentualMeta = parseBRL(inputPercSimul.value);
        }

        let categoriaMeta = '';
        let subcategoriaMeta = '';

        if (row.classList.contains('dre-cat')) {
            categoriaMeta = primeiroTd.textContent.trim(); // Já é o categoriaAtualContexto
            subcategoriaMeta = ''; // Meta para a categoria principal
            metasParaSalvar.push({ categoria: categoriaMeta, subcategoria: subcategoriaMeta, valor: valorMeta, percentual: percentualMeta });

        } else if (row.classList.contains('dre-sub')) {
            if (categoriaAtualContexto) { // Garante que temos um contexto de categoria
                categoriaMeta = categoriaAtualContexto;
                subcategoriaMeta = primeiroTd.textContent.trim();
                metasParaSalvar.push({ categoria: categoriaMeta, subcategoria: subcategoriaMeta, valor: valorMeta, percentual: percentualMeta });
            }
        } else if (row.classList.contains('dre-subcat-l2')) { // Para RECEITAS NAO OPERACIONAIS
            if (inputValorSimul.dataset.cat === "RECEITAS NAO OPERACIONAIS" &&
                inputValorSimul.dataset.subCat && inputValorSimul.dataset.subSubCat) {

                categoriaMeta = inputValorSimul.dataset.subCat.replace(/_/g, ' ');
                subcategoriaMeta = inputValorSimul.dataset.subSubCat.replace(/_/g, ' ');
                metasParaSalvar.push({ categoria: categoriaMeta, subcategoria: subcategoriaMeta, valor: valorMeta, percentual: percentualMeta });
            } else if (inputValorSimul.dataset.cat === "Z - ENTRADA DE REPASSE" &&
                inputValorSimul.dataset.subCat && inputValorSimul.dataset.subSubCat) {

                categoriaMeta = inputValorSimul.dataset.subCat.replace(/_/g, ' ');
                subcategoriaMeta = inputValorSimul.dataset.subSubCat.replace(/_/g, ' ');
                metasParaSalvar.push({ categoria: categoriaMeta, subcategoria: subcategoriaMeta, valor: valorMeta, percentual: percentualMeta });
            }
        }
    });

    // Adicionar metas para linhas totalizadoras de RNO que não têm inputs diretos
    // 1. Linha principal "RECEITAS NAO OPERACIONAIS" (dre-cat-principal)
    const rnoPrincipalRow = document.querySelector('tr.dre-cat-principal');
    if (rnoPrincipalRow) {
        const tdTotalSimulRNO = rnoPrincipalRow.querySelector('td.simul-total-cat[data-cat-total-simul="RECEITAS NAO OPERACIONAIS"]');
        const tdPercSimulRNO = rnoPrincipalRow.querySelector('td.simul-perc-cat[data-cat-perc-simul="RECEITAS NAO OPERACIONAIS"]');
        if (tdTotalSimulRNO) {
            const valorMetaRNOPrincipal = parseBRL(tdTotalSimulRNO.textContent);
            const percentualMetaRNOPrincipal = tdPercSimulRNO ? parseBRL(tdPercSimulRNO.textContent) : 0;
            metasParaSalvar.push({ categoria: "RECEITAS NAO OPERACIONAIS", subcategoria: "", valor: valorMetaRNOPrincipal, percentual: percentualMetaRNOPrincipal });
        }
    }

    // 2. Categorias L1 dentro de RNO (linhas dre-subcat-l1)
    document.querySelectorAll('tr.dre-subcat-l1').forEach(rowCatL1RNO => {
        const nomeCategoriaL1RNO = rowCatL1RNO.cells[0].textContent.trim();
        const tdValorCatL1RNO = rowCatL1RNO.querySelector('td[data-simul-valor-rno-cat]');
        const tdPercCatL1RNO = rowCatL1RNO.querySelector('td[data-simul-perc-rno-cat]');
        if (nomeCategoriaL1RNO && tdValorCatL1RNO) {
            const valorMetaCatL1RNO = parseBRL(tdValorCatL1RNO.textContent);
            const percentualMetaCatL1RNO = tdPercCatL1RNO ? parseBRL(tdPercCatL1RNO.textContent) : 0;
            metasParaSalvar.push({ categoria: nomeCategoriaL1RNO, subcategoria: "", valor: valorMetaCatL1RNO, percentual: percentualMetaCatL1RNO });
        }
    });

    if (metasParaSalvar.length === 0) {
        alert('Nenhuma meta editável encontrada para salvar.');
        botaoSalvar.disabled = false;
        botaoSalvar.textContent = 'SALVAR METAS OFICIAIS';
        return;
    }

    // console.log('Enviando para salvar:', { metas: metasParaSalvar, data: dataMeta });

    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ metas: metasParaSalvar, data: dataMeta }),
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => { // Tenta ler o corpo do erro como texto
                throw new Error(`Erro ${response.status}: ${response.statusText}. Detalhes: ${text}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.sucesso) {
            alert('Metas salvas com sucesso!');
        } else {
            alert('Erro ao salvar metas: ' + (data.erro || 'Erro desconhecido retornado pelo servidor.'));
        }
    })
    .catch((error) => {
        console.error('Erro na requisição AJAX:', error);
        alert(`Erro ao conectar com o servidor para salvar metas. ${error.message}`);
    })
    .finally(() => {
        botaoSalvar.disabled = false;
        botaoSalvar.textContent = 'SALVAR METAS';
    });
  });

  // Carregar Metas Oficiais
  document.getElementById('carregarMetasOficiaisBtn').addEventListener('click', function() {
    const botaoCarregar = this;
    botaoCarregar.disabled = true;
    botaoCarregar.textContent = 'CARREGANDO...';

    let itemsLoaded = 0;

    document.querySelectorAll('#tabelaSimulacao tbody tr').forEach(row => {
        const inputValorSimul = row.querySelector('input.simul-valor');
        const inputPercSimul = row.querySelector('input.simul-perc');
        // A coluna "Meta" é a 7ª coluna (índice 6) na tabela.
        const metaValueCell = row.cells[6]; 
        // A coluna "Meta %" é a 8ª coluna (índice 7) na tabela.
        const metaPercCell = row.cells[7]; 

        if (!inputValorSimul || !inputPercSimul) return;

        // Para subcategorias onde o percentual é editável, carrega o percentual da meta
        if (!inputPercSimul.readOnly && inputValorSimul.readOnly) {
            if (metaPercCell) {
                const metaPercentual = parseBRL(metaPercCell.textContent);
                inputPercSimul.value = formatSimPerc(metaPercentual, 100); // formata como percentual
                // Dispara evento de input para acionar os event listeners
                inputPercSimul.dispatchEvent(new Event('input', { bubbles: true }));
                itemsLoaded++;
            }
        }
        // Para subcategorias onde o valor é editável, carrega o valor da meta
        else if (!inputValorSimul.readOnly) {
            if (metaValueCell) {
                const metaValue = parseBRL(metaValueCell.textContent);
                inputValorSimul.value = formatSimValue(metaValue);
                // Dispara evento de input para acionar os event listeners
                inputValorSimul.dispatchEvent(new Event('input', { bubbles: true }));
                itemsLoaded++;
            }
        }
    });

    // Garante que todos os cálculos sejam atualizados
    setTimeout(() => {
        recalcularTudo(); // Recalcula toda a DRE com os valores carregados nos campos editáveis
        applyInputRestrictions(); // Reaplica as restrições para garantir consistência
    }, 100);
    
    alert(itemsLoaded > 0 ? 'Metas oficiais carregadas com sucesso na simulação!' : 'Nenhuma meta editável encontrada para carregar.');
    botaoCarregar.disabled = false;
    botaoCarregar.textContent = 'CARREGAR METAS OFICIAIS';
  });

  // Botão Ponto de Equilíbrio
 document.getElementById('pontoEquilibrioBtn').addEventListener('click', function() {
    // Primeiro, captura os percentuais das subcategorias editáveis ANTES de alterar a receita bruta
    const percentuaisFixos = {};
    
    // Captura percentuais das subcategorias de TRIBUTOS, CUSTO VARIÁVEL e DESPESA VENDA
    ['TRIBUTOS', 'CUSTO VARIÁVEL', 'DESPESA VENDA'].forEach(categoria => {
        const catRow = findCategoryRow(categoria);
        if (catRow) {
            let currentRow = catRow.nextElementSibling;
            while (currentRow && currentRow.classList.contains('dre-sub')) {
                const inputPerc = currentRow.querySelector('input.simul-perc');
                if (inputPerc && !inputPerc.readOnly) {
                    const subcategoriaName = currentRow.cells[0].textContent.trim();
                    const key = `${categoria}___${subcategoriaName}`;
                    percentuaisFixos[key] = inputPerc.value;
                }
                currentRow = currentRow.nextElementSibling;
            }
        }
    });

    // Helpers
    const getPercentageFromInput = (categoryName) => {
        const row = findCategoryRow(categoryName);
        if (row) {
            const inputPerc = row.querySelector('input.simul-perc');
            return parseBRL(inputPerc.value) / 100;
        }
        return 0;
    };
    const getAbsoluteValueFromInput = (categoryName) => {
        const row = findCategoryRow(categoryName);
        if (row) {
            const inputVal = row.querySelector('input.simul-valor');
            return parseBRL(inputVal.value);
        }
        return 0;
    };

    // Percentuais variáveis
    const pTributos = getPercentageFromInput('TRIBUTOS');
    const pCustoVariavel = getPercentageFromInput('CUSTO VARIÁVEL');
    const pDespesaVenda = getPercentageFromInput('DESPESA VENDA');

    // Valores absolutos fixos
    const cf = getAbsoluteValueFromInput('CUSTO FIXO');
    const df = getAbsoluteValueFromInput('DESPESA FIXA');
    const ii = getAbsoluteValueFromInput('INVESTIMENTO INTERNO');
    const ie = getAbsoluteValueFromInput('INVESTIMENTO EXTERNO');
    const am = getAbsoluteValueFromInput('AMORTIZAÇÃO');
    const sr = getAbsoluteValueFromInput('Z - SAIDA DE REPASSE');

    // RECEITAS NAO OPERACIONAIS (soma das sub-linhas)
    let rno = 0;
    document.querySelectorAll('input.simul-valor[data-cat="RECEITAS NAO OPERACIONAIS"]').forEach(input => {
        rno += parseBRL(input.value);
    });

    // ENTRADA DE REPASSE (soma das sub-linhas)
    const entradaRepasse = getAbsoluteValueFromInput('Z - ENTRADA DE REPASSE');

    // Soma dos custos/despesas fixas e outras receitas/despesas
    const fixedCostsAndOther = (cf + df + ii + ie + sr + am) - (rno + entradaRepasse);

    // Fator variável
    const variableFactor = 1 - (pTributos + pCustoVariavel + pDespesaVenda);

    if (variableFactor <= 0) {
        alert('Não é possível calcular o Ponto de Equilíbrio. A soma dos percentuais de custos variáveis é muito alta (>= 100%).');
        return;
    }

    // Receita Bruta para Fluxo de Caixa = 0
    const novaReceitaBruta = fixedCostsAndOther / variableFactor;
    const finalReceitaBruta = Math.max(0, novaReceitaBruta);

    // Atualiza o campo da Receita Bruta
    const inputReceitaBrutaSimul = document.querySelector('.simul-valor[data-receita="1"]');
    if (inputReceitaBrutaSimul) {
        inputReceitaBrutaSimul.value = formatSimValue(finalReceitaBruta);
        
        // Recalcula tudo EXCETO restaura os percentuais fixos das subcategorias editáveis
        recalcularTudo();
        
        // Restaura os percentuais das subcategorias que devem permanecer fixos
        Object.keys(percentuaisFixos).forEach(key => {
            const [categoria, subcategoria] = key.split('___');
            const catRow = findCategoryRow(categoria);
            if (catRow) {
                let currentRow = catRow.nextElementSibling;
                while (currentRow && currentRow.classList.contains('dre-sub')) {
                    const subcategoriaName = currentRow.cells[0].textContent.trim();
                    if (subcategoriaName === subcategoria) {
                        const inputPerc = currentRow.querySelector('input.simul-perc');
                        if (inputPerc && !inputPerc.readOnly) {
                            inputPerc.value = percentuaisFixos[key];
                        }
                        break;
                    }
                    currentRow = currentRow.nextElementSibling;
                }
            }
        });
        
        // Recalcula novamente para aplicar os percentuais restaurados
        recalcularTudo();

        // Força o campo do Fluxo de Caixa a mostrar zero
        const rowFC = document.getElementById('rowFluxoCaixa');
        if (rowFC) {
            const inputFCValorSimul = rowFC.querySelector('input.simul-valor');
            if (inputFCValorSimul) inputFCValorSimul.value = formatSimValue(0);
            const inputFCPerc = rowFC.querySelector('input.simul-perc');
            if (inputFCPerc) inputFCPerc.value = formatSimPerc(0, finalReceitaBruta);
        }
    } else {
        alert('Campo de simulação da Receita Bruta não encontrado.');
    }
});

  // --- Funcionalidade de Salvar/Carregar Simulação (Apenas Banco de Dados) ---
  // const localStorageKeyCollection = 'simulacaoDRECollection'; // Removido - não usa mais localStorage
  const nomeSimulacaoInput = document.getElementById('nomeSimulacaoLocalInput');
  const listaSimulacoesSelect = document.getElementById('listaSimulacoesSalvas');

  // Função para carregar simulações do banco de dados
  function carregarListaSimulacoes() {
    fetch(window.location.href + '?action=listar_simulacoes')
        .then(response => response.json())
        .then(data => {
            if (data.sucesso) {
                // Limpa o select
                listaSimulacoesSelect.innerHTML = '<option value="">-- Selecione uma simulação --</option>';
                
                if (data.simulacoes.length === 0) {
                    const optionEmpty = document.createElement('option');
                    optionEmpty.value = "";
                    optionEmpty.textContent = "Nenhuma simulação salva";
                    optionEmpty.disabled = true;
                    listaSimulacoesSelect.appendChild(optionEmpty);
                } else {
                    // Garantir unicidade no frontend também (por segurança)
                    const simulacoesUnicas = [];
                    const nomesJaAdicionados = new Set();
                    
                    data.simulacoes.forEach(sim => {
                        if (!nomesJaAdicionados.has(sim.nome)) {
                            simulacoesUnicas.push(sim);
                            nomesJaAdicionados.add(sim.nome);
                        }
                    });
                    
                    simulacoesUnicas.forEach(sim => {
                        const optionSim = document.createElement('option');
                        optionSim.value = sim.nome;
                        // Exibe o nome da simulação e a data de criação para melhor identificação
                        const dataFormatada = new Date(sim.data).toLocaleString('pt-BR', {
                            year: 'numeric',
                            month: '2-digit',
                            day: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        optionSim.textContent = `${sim.nome} (${dataFormatada})`;
                        listaSimulacoesSelect.appendChild(optionSim);
                    });
                }
            } else {
                console.error('Erro ao carregar simulações:', data.erro);
                // Mostrar lista vazia se houver erro no banco
                listaSimulacoesSelect.innerHTML = '<option value="">-- Selecione uma simulação --</option>';
                const optionError = document.createElement('option');
                optionError.value = "";
                optionError.textContent = "Erro ao carregar simulações";
                optionError.disabled = true;
                listaSimulacoesSelect.appendChild(optionError);
            }
        })
        .catch(error => {
            console.error('Erro de comunicação:', error);
            // Mostrar lista vazia se houver erro de comunicação
            listaSimulacoesSelect.innerHTML = '<option value="">-- Selecione uma simulação --</option>';
            const optionCommError = document.createElement('option');
            optionCommError.value = "";
            optionCommError.textContent = "Erro de comunicação com servidor";
            optionCommError.disabled = true;
            listaSimulacoesSelect.appendChild(optionCommError);
        });
  }

  // Função populateSimulationsList removida - não usa mais localStorage

  document.getElementById('salvarSimulacaoLocalBtn').addEventListener('click', function() {
    const nomeSimulacao = nomeSimulacaoInput.value.trim();
    if (!nomeSimulacao) {
        alert('Por favor, insira um nome para a simulação.');
        nomeSimulacaoInput.focus();
        return;
    }

    const simulacaoData = {};
    let categoriaAtualContexto = '';

    document.querySelectorAll('#tabelaSimulacao tbody tr').forEach(row => {
        const primeiroTd = row.cells[0];
        if (!primeiroTd) return;

        const inputValorSimul = row.querySelector('input.simul-valor');
        const inputPercSimul = row.querySelector('input.simul-perc');

        if (row.classList.contains('dre-cat')) {
             categoriaAtualContexto = primeiroTd.textContent.trim();
        }

        if (!inputValorSimul) {
            return;
        }

        let key = '';
        if (row.classList.contains('dre-cat')) {
            key = primeiroTd.textContent.trim();
        } else if (row.classList.contains('dre-subcat-l2')) { // Para RECEITAS NAO OPERACIONAIS
            if (inputValorSimul.dataset.cat === "RECEITAS NAO OPERACIONAIS" &&
                inputValorSimul.dataset.subCat && inputValorSimul.dataset.subSubCat) {
                key = `RNO_${inputValorSimul.dataset.subCat.replace(/_/g, ' ')}___${inputValorSimul.dataset.subSubCat.replace(/_/g, ' ')}`;
            } else if (inputValorSimul.dataset.cat === "Z - ENTRADA DE REPASSE" &&
                inputValorSimul.dataset.subCat && inputValorSimul.dataset.subSubCat) {
                key = `ER_${inputValorSimul.dataset.subCat.replace(/_/g, ' ')}___${inputValorSimul.dataset.subSubCat.replace(/_/g, ' ')}`;
            }
        } else if (row.classList.contains('dre-sub')) {
            if (categoriaAtualContexto) {
                key = `${categoriaAtualContexto}___${primeiroTd.textContent.trim()}`;
            }
        }

        if (key) {
            // Salva o valor editável (seja valor ou percentual)
            if (!inputValorSimul.readOnly) {
                simulacaoData[key] = inputValorSimul.value;
            } else if (inputPercSimul && !inputPercSimul.readOnly) {
                // Para campos onde o percentual é editável, salva o percentual com sufixo "_perc"
                simulacaoData[key + "_perc"] = inputPercSimul.value;
            }
        }
    });

    if (Object.keys(simulacaoData).length > 0) {
        // Salvar no banco de dados
        const payload = {
            nomeSimulacao: nomeSimulacao,
            simulacao: simulacaoData
        };

        console.log('Enviando dados:', payload);

        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload)
        })
        .then(response => {
            console.log('Status da resposta:', response.status);
            console.log('Headers da resposta:', response.headers);
            
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Resposta de erro (texto):', text);
                    throw new Error(`Erro HTTP ${response.status}: ${response.statusText}. Resposta: ${text}`);
                });
            }
            
            return response.text().then(text => {
                console.log('Resposta (texto):', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Erro ao fazer parse do JSON:', e);
                    console.error('Texto recebido:', text);
                    throw new Error('Resposta não é um JSON válido: ' + text);
                }
            });
        })
        .then(data => {
            console.log('Dados recebidos:', data);
            if (data.sucesso) {
                alert(`Simulação "${nomeSimulacao}" salva no banco com sucesso!`);
                nomeSimulacaoInput.value = ''; // Limpa o campo do nome
                carregarListaSimulacoes(); // Atualiza a lista dropdown
            } else {
                alert('Erro ao salvar simulação: ' + (data.erro || 'Erro desconhecido'));
                console.error('Erro detalhado:', data);
            }
        })
        .catch(error => {
            console.error('Erro completo:', error);
            console.error('Payload enviado:', payload);
            alert('Erro de comunicação com o servidor: ' + error.message);
        });

        // Não salva mais no localStorage - apenas banco de dados
    } else {
        alert('Nenhum dado de simulação editável encontrado para salvar.');
    }
  });

  document.getElementById('carregarSimulacaoLocalBtn').addEventListener('click', function() {
    const nomeSimulacaoSelecionada = listaSimulacoesSelect.value;
    if (!nomeSimulacaoSelecionada) {
        alert('Por favor, selecione uma simulação da lista para carregar.');
        return;
    }

    // Carregar apenas do banco de dados
    fetch(window.location.href + '?action=carregar_simulacao&nome=' + encodeURIComponent(nomeSimulacaoSelecionada))
        .then(response => response.json())
        .then(data => {
            if (data.sucesso && Object.keys(data.simulacao).length > 0) {
                carregarSimulacaoNaTela(data.simulacao, nomeSimulacaoSelecionada);
            } else {
                alert(`Simulação "${nomeSimulacaoSelecionada}" não encontrada no banco de dados.`);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar simulação do banco:', error);
            alert('Erro de comunicação com o servidor ao carregar simulação.');
        });
  });

  // Função removida - não usa mais localStorage como fallback

  function carregarSimulacaoNaTela(simulacaoData, nomeSimulacao) {
    let categoriaAtualContexto = '';
    let itemsLoaded = 0;

    document.querySelectorAll('#tabelaSimulacao tbody tr').forEach(row => {
        const primeiroTd = row.cells[0];
        if (!primeiroTd) return;

        const inputValorSimul = row.querySelector('input.simul-valor');
        const inputPercSimul = row.querySelector('input.simul-perc');

        if (row.classList.contains('dre-cat')) {
             categoriaAtualContexto = primeiroTd.textContent.trim();
        }

        if (!inputValorSimul) {
            return;
        }

        let key = '';
        if (row.classList.contains('dre-cat')) {
            key = primeiroTd.textContent.trim();
        } else if (row.classList.contains('dre-subcat-l2')) {
            if (inputValorSimul.dataset.cat === "RECEITAS NAO OPERACIONAIS" &&
                inputValorSimul.dataset.subCat && inputValorSimul.dataset.subSubCat) {
                key = `RNO_${inputValorSimul.dataset.subCat.replace(/_/g, ' ')}___${inputValorSimul.dataset.subSubCat.replace(/_/g, ' ')}`;
            } else if (inputValorSimul.dataset.cat === "Z - ENTRADA DE REPASSE" &&
                inputValorSimul.dataset.subCat && inputValorSimul.dataset.subSubCat) {
                key = `ER_${inputValorSimul.dataset.subCat.replace(/_/g, ' ')}___${inputValorSimul.dataset.subSubCat.replace(/_/g, ' ')}`;
            }
        } else if (row.classList.contains('dre-sub')) {
            if (categoriaAtualContexto) {
                key = `${categoriaAtualContexto}___${primeiroTd.textContent.trim()}`;
            }
        }

        if (key) {
            // Carrega o valor se o campo de valor for editável
            if (!inputValorSimul.readOnly && simulacaoData.hasOwnProperty(key)) {
                inputValorSimul.value = simulacaoData[key];
                itemsLoaded++;
            }
            // Carrega o percentual se o campo de percentual for editável
            else if (inputPercSimul && !inputPercSimul.readOnly && simulacaoData.hasOwnProperty(key + "_perc")) {
                inputPercSimul.value = simulacaoData[key + "_perc"];
                itemsLoaded++;
            }
        }
    });

    recalcularTudo();
    alert(itemsLoaded > 0 ? `Simulação "${nomeSimulacao}" carregada com sucesso!` : 'Nenhum dado correspondente encontrado na simulação.');
    nomeSimulacaoInput.value = nomeSimulacao;
  }

  document.getElementById('excluirSimulacaoLocalBtn').addEventListener('click', function() {
    const nomeSimulacaoSelecionada = listaSimulacoesSelect.value;
    if (!nomeSimulacaoSelecionada) {
        alert('Por favor, selecione uma simulação da lista para excluir.');
        return;
    }

    if (confirm(`Tem certeza que deseja excluir a simulação "${nomeSimulacaoSelecionada}"? Esta ação não pode ser desfeita.`)) {
        // Excluir apenas do banco
        fetch(window.location.href + '?action=excluir_simulacao&nome=' + encodeURIComponent(nomeSimulacaoSelecionada))
            .then(response => response.json())
            .then(data => {
                if (data.sucesso) {
                    alert(`Simulação "${nomeSimulacaoSelecionada}" excluída com sucesso!`);
                    carregarListaSimulacoes(); // Atualiza a lista
                    nomeSimulacaoInput.value = '';
                } else {
                    alert('Erro ao excluir simulação do banco.');
                }
            })
            .catch(error => {
                console.error('Erro ao excluir simulação:', error);
                alert('Erro de comunicação com o servidor.');
            });
    }
  });

  carregarListaSimulacoes();

});
</script>
</body>
</html>