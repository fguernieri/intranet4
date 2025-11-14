<?php
/**
 * API para retornar análise DRE completa com métricas temporais
 * Retorna dados consolidados de todas as categorias do DRE
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/financeiro_bar/supabase_connection.php';

// Verificar autenticação
session_start();
if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$periodo = $_GET['periodo'] ?? '';

if (empty($periodo)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Período não informado']);
    exit;
}

try {
    $supabase = new SupabaseConnection();
    
    // Converter período YYYY/MM para data YYYY-MM-01
    if (!preg_match('/^(\d{4})\/(\d{2})$/', $periodo, $matches)) {
        throw new Exception('Formato de período inválido');
    }
    
    $ano = $matches[1];
    $mes = $matches[2];
    $data_final = "$ano-$mes-01";
    
    // Calcular data inicial (6 meses atrás)
    $data_inicial = date('Y-m-01', strtotime($data_final . ' -5 months'));
    
    // Para média 12 meses: buscar 12 meses atrás
    $data_inicial_12m = date('Y-m-01', strtotime($data_final . ' -11 months'));
    
    // Para LY (Last Year): mesmo mês do ano anterior
    $data_ly = date('Y-m-01', strtotime($data_final . ' -12 months'));
    
    // Buscar receitas dos últimos 6 meses com paginação
    $receitas = [];
    $offset = 0;
    $limit = 1000;
    do {
        $batch = $supabase->select('freceitatap', [
            'select' => '*',
            'filters' => [
                'data_mes' => "gte.$data_inicial",
                'data_mes' => "lte.$data_final"
            ],
            'order' => 'data_mes.asc',
            'limit' => $limit,
            'offset' => $offset
        ]);
        if ($batch && count($batch) > 0) {
            $receitas = array_merge($receitas, $batch);
            $offset += $limit;
        }
    } while ($batch && count($batch) == $limit);
    
    // Buscar receitas dos últimos 12 meses para média 12M
    $receitas_12m = [];
    $offset = 0;
    do {
        $batch = $supabase->select('freceitatap', [
            'select' => '*',
            'filters' => [
                'data_mes' => "gte.$data_inicial_12m",
                'data_mes' => "lte.$data_final"
            ],
            'order' => 'data_mes.asc',
            'limit' => $limit,
            'offset' => $offset
        ]);
        if ($batch && count($batch) > 0) {
            $receitas_12m = array_merge($receitas_12m, $batch);
            $offset += $limit;
        }
    } while ($batch && count($batch) == $limit);
    
    // Buscar receitas de LY (mesmo mês ano anterior)
    $receitas_ly = [];
    $offset = 0;
    do {
        $batch = $supabase->select('freceitatap', [
            'select' => '*',
            'filters' => [
                'data_mes' => "eq.$data_ly"
            ],
            'order' => 'data_mes.asc',
            'limit' => $limit,
            'offset' => $offset
        ]);
        if ($batch && count($batch) > 0) {
            $receitas_ly = array_merge($receitas_ly, $batch);
            $offset += $limit;
        }
    } while ($batch && count($batch) == $limit);
    
    // Buscar despesas dos últimos 6 meses com paginação
    $despesas = [];
    $offset = 0;
    do {
        $batch = $supabase->select('fdespesastap', [
            'select' => '*',
            'filters' => [
                'data_mes' => "gte.$data_inicial",
                'data_mes' => "lte.$data_final"
            ],
            'order' => 'data_mes.asc',
            'limit' => $limit,
            'offset' => $offset
        ]);
        if ($batch && count($batch) > 0) {
            $despesas = array_merge($despesas, $batch);
            $offset += $limit;
        }
    } while ($batch && count($batch) == $limit);
    
    // Buscar despesas dos últimos 12 meses
    $despesas_12m = [];
    $offset = 0;
    do {
        $batch = $supabase->select('fdespesastap', [
            'select' => '*',
            'filters' => [
                'data_mes' => "gte.$data_inicial_12m",
                'data_mes' => "lte.$data_final"
            ],
            'order' => 'data_mes.asc',
            'limit' => $limit,
            'offset' => $offset
        ]);
        if ($batch && count($batch) > 0) {
            $despesas_12m = array_merge($despesas_12m, $batch);
            $offset += $limit;
        }
    } while ($batch && count($batch) == $limit);
    
    // Buscar despesas de LY
    $despesas_ly = [];
    $offset = 0;
    do {
        $batch = $supabase->select('fdespesastap', [
            'select' => '*',
            'filters' => [
                'data_mes' => "eq.$data_ly"
            ],
            'order' => 'data_mes.asc',
            'limit' => $limit,
            'offset' => $offset
        ]);
        if ($batch && count($batch) > 0) {
            $despesas_ly = array_merge($despesas_ly, $batch);
            $offset += $limit;
        }
    } while ($batch && count($batch) == $limit);
    
    // DEBUG: Log das queries
    error_log("DEBUG DRE TAP - Data inicial: $data_inicial, Data final: $data_final");
    error_log("DEBUG DRE TAP - Receitas count: " . count($receitas));
    error_log("DEBUG DRE TAP - Despesas count: " . count($despesas));
    
    // Organizar dados por mês
    $meses = [];
    $subcategorias_por_linha = []; // Armazenar subcategorias
    $data_atual = $data_inicial;
    for ($i = 0; $i < 6; $i++) {
        $meses[$data_atual] = [
            'data' => $data_atual,
            'receita_operacional' => 0,
            'receita_nao_operacional' => 0,
            'tributos' => 0,
            'custo_variavel' => 0,
            'custo_fixo' => 0,
            'despesa_fixa' => 0,
            'despesa_venda' => 0,
            'investimento_interno' => 0,
            'saidas_nao_operacionais' => 0,
            'retirada_lucro' => 0
        ];
        $data_atual = date('Y-m-01', strtotime($data_atual . ' +1 month'));
    }
    
    // Categorias não operacionais
    $categorias_nao_operacionais = [
        'ENTRADA DE REPASSE DE SALARIOS',
        'ENTRADA DE REPASSE EXTRA DE SALARIOS',
        'ENTRADA DE REPASSE',
        'ENTRADA DE REPASSE OUTROS'
    ];
    
    // Processar receitas
    foreach ($receitas as $r) {
        $data_mes = $r['data_mes'];
        if (!isset($meses[$data_mes])) continue;
        
        $categoria = strtoupper(trim($r['categoria'] ?? ''));
        $valor = floatval($r['total_receita_mes'] ?? 0);
        
        // Verificar se é não operacional
        $eh_nao_operacional = false;
        foreach ($categorias_nao_operacionais as $cat_nao_op) {
            if (strpos($categoria, $cat_nao_op) !== false) {
                $eh_nao_operacional = true;
                break;
            }
        }
        
        if ($eh_nao_operacional) {
            $meses[$data_mes]['receita_nao_operacional'] += $valor;
            
            // Armazenar subcategoria
            if (!isset($subcategorias_por_linha['receita_nao_operacional'])) {
                $subcategorias_por_linha['receita_nao_operacional'] = [];
            }
            if (!isset($subcategorias_por_linha['receita_nao_operacional'][$categoria])) {
                $subcategorias_por_linha['receita_nao_operacional'][$categoria] = [];
                foreach (array_keys($meses) as $d) {
                    $subcategorias_por_linha['receita_nao_operacional'][$categoria][$d] = 0;
                }
            }
            $subcategorias_por_linha['receita_nao_operacional'][$categoria][$data_mes] += $valor;
        } else {
            $meses[$data_mes]['receita_operacional'] += $valor;
            
            // Armazenar subcategoria
            if (!isset($subcategorias_por_linha['receita_operacional'])) {
                $subcategorias_por_linha['receita_operacional'] = [];
            }
            if (!isset($subcategorias_por_linha['receita_operacional'][$categoria])) {
                $subcategorias_por_linha['receita_operacional'][$categoria] = [];
                foreach (array_keys($meses) as $d) {
                    $subcategorias_por_linha['receita_operacional'][$categoria][$d] = 0;
                }
            }
            $subcategorias_por_linha['receita_operacional'][$categoria][$data_mes] += $valor;
        }
    }
    
    // Processar despesas
    foreach ($despesas as $d) {
        $data_mes = $d['data_mes'];
        if (!isset($meses[$data_mes])) continue;
        
        $categoria_pai = strtoupper(trim($d['categoria_pai'] ?? ''));
        $categoria = strtoupper(trim($d['categoria'] ?? ''));
        $valor = floatval($d['total_receita_mes'] ?? 0);
        
        $linha_chave = null;
        
        if ($categoria_pai === 'TRIBUTOS') {
            $meses[$data_mes]['tributos'] += $valor;
            $linha_chave = 'tributos';
        } elseif ($categoria_pai === 'CUSTO VARIÁVEL' || $categoria_pai === 'CUSTO VARIAVEL') {
            $meses[$data_mes]['custo_variavel'] += $valor;
            $linha_chave = 'custo_variavel';
        } elseif ($categoria_pai === 'CUSTO FIXO') {
            $meses[$data_mes]['custo_fixo'] += $valor;
            $linha_chave = 'custo_fixo';
        } elseif ($categoria_pai === 'DESPESA FIXA') {
            $meses[$data_mes]['despesa_fixa'] += $valor;
            $linha_chave = 'despesa_fixa';
        } elseif (strpos($categoria_pai, 'DESPESA') !== false && strpos($categoria_pai, 'VENDA') !== false) {
            $meses[$data_mes]['despesa_venda'] += $valor;
            $linha_chave = 'despesa_venda';
        } elseif ($categoria_pai === 'INVESTIMENTO INTERNO') {
            $meses[$data_mes]['investimento_interno'] += $valor;
            $linha_chave = 'investimento_interno';
        } elseif (strpos($categoria_pai, 'SAÍDA') !== false || strpos($categoria_pai, 'SAIDA') !== false) {
            $meses[$data_mes]['saidas_nao_operacionais'] += $valor;
            $linha_chave = 'saidas_nao_operacionais';
        } elseif (strpos($categoria, 'RETIRADA') !== false && strpos($categoria, 'LUCRO') !== false) {
            $meses[$data_mes]['retirada_lucro'] += $valor;
            $linha_chave = 'retirada_lucro';
        }
        
        // Armazenar subcategoria
        if ($linha_chave) {
            if (!isset($subcategorias_por_linha[$linha_chave])) {
                $subcategorias_por_linha[$linha_chave] = [];
            }
            if (!isset($subcategorias_por_linha[$linha_chave][$categoria])) {
                $subcategorias_por_linha[$linha_chave][$categoria] = [];
                foreach (array_keys($meses) as $d) {
                    $subcategorias_por_linha[$linha_chave][$categoria][$d] = 0;
                }
            }
            $subcategorias_por_linha[$linha_chave][$categoria][$data_mes] += $valor;
        }
    }
    
    // ===== PROCESSAR DADOS DE 12 MESES E LY =====
    // Calcular receita operacional dos últimos 12 meses
    $receita_op_12m = [];
    foreach ($receitas_12m as $r) {
        $data_mes = $r['data_mes'];
        $categoria = strtoupper(trim($r['categoria'] ?? ''));
        $valor = floatval($r['total_receita_mes'] ?? 0);
        
        // Verificar se é não operacional
        $eh_nao_operacional = false;
        foreach ($categorias_nao_operacionais as $cat_nao_op) {
            if (strpos($categoria, $cat_nao_op) !== false) {
                $eh_nao_operacional = true;
                break;
            }
        }
        
        if (!$eh_nao_operacional) {
            if (!isset($receita_op_12m[$data_mes])) {
                $receita_op_12m[$data_mes] = 0;
            }
            $receita_op_12m[$data_mes] += $valor;
        }
    }
    
    // Calcular receita operacional LY
    $receita_op_ly = 0;
    foreach ($receitas_ly as $r) {
        $categoria = strtoupper(trim($r['categoria'] ?? ''));
        $valor = floatval($r['total_receita_mes'] ?? 0);
        
        $eh_nao_operacional = false;
        foreach ($categorias_nao_operacionais as $cat_nao_op) {
            if (strpos($categoria, $cat_nao_op) !== false) {
                $eh_nao_operacional = true;
                break;
            }
        }
        
        if (!$eh_nao_operacional) {
            $receita_op_ly += $valor;
        }
    }
    
    // Calcular despesas dos últimos 12 meses por categoria
    $despesas_12m_calc = [
        'tributos' => [],
        'custo_variavel' => [],
        'custo_fixo' => [],
        'despesa_fixa' => [],
        'despesa_venda' => [],
        'investimento_interno' => [],
        'saidas_nao_operacionais' => [],
        'retirada_lucro' => []
    ];
    
    foreach ($despesas_12m as $d) {
        $data_mes = $d['data_mes'];
        $categoria_pai = strtoupper(trim($d['categoria_pai'] ?? ''));
        $categoria = strtoupper(trim($d['categoria'] ?? ''));
        $valor = floatval($d['total_receita_mes'] ?? 0);
        
        if ($categoria_pai === 'TRIBUTOS') {
            $key = 'tributos';
        } elseif ($categoria_pai === 'CUSTO VARIÁVEL' || $categoria_pai === 'CUSTO VARIAVEL') {
            $key = 'custo_variavel';
        } elseif ($categoria_pai === 'CUSTO FIXO') {
            $key = 'custo_fixo';
        } elseif ($categoria_pai === 'DESPESA FIXA') {
            $key = 'despesa_fixa';
        } elseif (strpos($categoria_pai, 'DESPESA') !== false && strpos($categoria_pai, 'VENDA') !== false) {
            $key = 'despesa_venda';
        } elseif ($categoria_pai === 'INVESTIMENTO INTERNO') {
            $key = 'investimento_interno';
        } elseif (strpos($categoria_pai, 'SAÍDA') !== false || strpos($categoria_pai, 'SAIDA') !== false) {
            $key = 'saidas_nao_operacionais';
        } elseif (strpos($categoria, 'RETIRADA') !== false && strpos($categoria, 'LUCRO') !== false) {
            $key = 'retirada_lucro';
        } else {
            continue;
        }
        
        if (!isset($despesas_12m_calc[$key][$data_mes])) {
            $despesas_12m_calc[$key][$data_mes] = 0;
        }
        $despesas_12m_calc[$key][$data_mes] += $valor;
    }
    
    // Calcular despesas LY por categoria
    $despesas_ly_calc = [
        'tributos' => 0,
        'custo_variavel' => 0,
        'custo_fixo' => 0,
        'despesa_fixa' => 0,
        'despesa_venda' => 0,
        'investimento_interno' => 0,
        'saidas_nao_operacionais' => 0,
        'retirada_lucro' => 0
    ];
    
    foreach ($despesas_ly as $d) {
        $categoria_pai = strtoupper(trim($d['categoria_pai'] ?? ''));
        $categoria = strtoupper(trim($d['categoria'] ?? ''));
        $valor = floatval($d['total_receita_mes'] ?? 0);
        
        if ($categoria_pai === 'TRIBUTOS') {
            $despesas_ly_calc['tributos'] += $valor;
        } elseif ($categoria_pai === 'CUSTO VARIÁVEL' || $categoria_pai === 'CUSTO VARIAVEL') {
            $despesas_ly_calc['custo_variavel'] += $valor;
        } elseif ($categoria_pai === 'CUSTO FIXO') {
            $despesas_ly_calc['custo_fixo'] += $valor;
        } elseif ($categoria_pai === 'DESPESA FIXA') {
            $despesas_ly_calc['despesa_fixa'] += $valor;
        } elseif (strpos($categoria_pai, 'DESPESA') !== false && strpos($categoria_pai, 'VENDA') !== false) {
            $despesas_ly_calc['despesa_venda'] += $valor;
        } elseif ($categoria_pai === 'INVESTIMENTO INTERNO') {
            $despesas_ly_calc['investimento_interno'] += $valor;
        } elseif (strpos($categoria_pai, 'SAÍDA') !== false || strpos($categoria_pai, 'SAIDA') !== false) {
            $despesas_ly_calc['saidas_nao_operacionais'] += $valor;
        } elseif (strpos($categoria, 'RETIRADA') !== false && strpos($categoria, 'LUCRO') !== false) {
            $despesas_ly_calc['retirada_lucro'] += $valor;
        }
    }
    
    // Calcular métricas para cada linha do DRE
    $linhas_dre = [
        'receita_operacional' => ['nome' => 'RECEITA OPERACIONAL', 'tipo' => 'receita', 'campo' => 'receita_operacional'],
        'tributos' => ['nome' => '(-) TRIBUTOS', 'tipo' => 'despesa', 'campo' => 'tributos'],
        'receita_liquida' => ['nome' => 'RECEITA LÍQUIDA', 'tipo' => 'resultado', 'calculo' => ['receita_operacional', '-', 'tributos']],
        'custo_variavel' => ['nome' => '(-) CUSTO VARIÁVEL', 'tipo' => 'despesa', 'campo' => 'custo_variavel'],
        'lucro_bruto' => ['nome' => 'LUCRO BRUTO', 'tipo' => 'resultado', 'calculo' => ['receita_liquida', '-', 'custo_variavel']],
        'custo_fixo' => ['nome' => '(-) CUSTO FIXO', 'tipo' => 'despesa', 'campo' => 'custo_fixo'],
        'despesa_fixa' => ['nome' => '(-) DESPESA FIXA', 'tipo' => 'despesa', 'campo' => 'despesa_fixa'],
        'despesa_venda' => ['nome' => '(-) DESPESAS DE VENDA', 'tipo' => 'despesa', 'campo' => 'despesa_venda'],
        'lucro_liquido' => ['nome' => 'LUCRO LÍQUIDO', 'tipo' => 'resultado', 'calculo' => ['lucro_bruto', '-', 'custo_fixo', '-', 'despesa_fixa', '-', 'despesa_venda']],
        'investimento_interno' => ['nome' => '(-) INVESTIMENTO INTERNO', 'tipo' => 'despesa', 'campo' => 'investimento_interno'],
        'receita_nao_operacional' => ['nome' => 'RECEITAS NÃO OPERACIONAIS', 'tipo' => 'receita', 'campo' => 'receita_nao_operacional'],
        'saidas_nao_operacionais' => ['nome' => '(-) SAÍDAS NÃO OPERACIONAIS', 'tipo' => 'despesa', 'campo' => 'saidas_nao_operacionais'],
        'retirada_lucro' => ['nome' => '(-) RETIRADA DE LUCRO', 'tipo' => 'despesa', 'campo' => 'retirada_lucro'],
        'impacto_caixa' => ['nome' => '(=) IMPACTO CAIXA', 'tipo' => 'resultado', 'calculo' => ['lucro_liquido', '-', 'investimento_interno', '+', 'receita_nao_operacional', '-', 'saidas_nao_operacionais', '-', 'retirada_lucro']]
    ];
    
    $resultado = [];
    $valores_calculados = []; // Armazenar valores calculados para usar em cálculos posteriores
    
    foreach ($linhas_dre as $chave => $config) {
        $valores_mes = [];
        
        // Extrair valores dos 6 meses
        foreach ($meses as $data => $dados) {
            if (isset($config['campo'])) {
                // Valor direto do campo
                $valores_mes[] = $dados[$config['campo']] ?? 0;
            } elseif (isset($config['calculo'])) {
                // Calcular baseado em outras linhas
                $valor = 0;
                $operador = '+';
                
                foreach ($config['calculo'] as $item) {
                    if ($item === '+' || $item === '-') {
                        $operador = $item;
                    } else {
                        // É uma referência a outra linha
                        $valor_ref = $valores_calculados[$item][$data] ?? 0;
                        if ($operador === '+') {
                            $valor += $valor_ref;
                        } else {
                            $valor -= $valor_ref;
                        }
                    }
                }
                
                $valores_mes[] = $valor;
                
                // Armazenar valor calculado para uso futuro
                if (!isset($valores_calculados[$chave])) {
                    $valores_calculados[$chave] = [];
                }
                $valores_calculados[$chave][$data] = $valor;
            }
        }
        
        // Também armazenar valores diretos para cálculos futuros
        if (isset($config['campo'])) {
            $i = 0;
            foreach ($meses as $data => $dados) {
                if (!isset($valores_calculados[$chave])) {
                    $valores_calculados[$chave] = [];
                }
                $valores_calculados[$chave][$data] = $valores_mes[$i];
                $i++;
            }
        }
        
        // Calcular métricas
        $valor_atual = end($valores_mes);
        $valor_anterior = count($valores_mes) > 1 ? $valores_mes[count($valores_mes) - 2] : 0;
        
        $ultimos_3 = array_slice($valores_mes, -3);
        $media_3m = count($ultimos_3) > 0 ? array_sum($ultimos_3) / count($ultimos_3) : 0;
        
        $media_6m = count($valores_mes) > 0 ? array_sum($valores_mes) / count($valores_mes) : 0;
        
        // Calcular média 12 meses e LY
        $media_12m = 0;
        $ly_value = 0;
        
        if (isset($config['campo'])) {
            // Para campos diretos
            $campo = $config['campo'];
            
            if ($campo === 'receita_operacional') {
                $media_12m = count($receita_op_12m) > 0 ? array_sum($receita_op_12m) / count($receita_op_12m) : 0;
                $ly_value = $receita_op_ly;
            } elseif (isset($despesas_12m_calc[$campo])) {
                $valores_12m = array_values($despesas_12m_calc[$campo]);
                $media_12m = count($valores_12m) > 0 ? array_sum($valores_12m) / count($valores_12m) : 0;
                $ly_value = $despesas_ly_calc[$campo] ?? 0;
            }
        } elseif (isset($config['calculo'])) {
            // Para campos calculados, precisamos calcular baseado nos valores de 12M e LY das linhas dependentes
            $valores_12m_calc = [];
            $ly_calc = 0;
            $operador = '+';
            
            foreach ($config['calculo'] as $item) {
                if ($item === '+' || $item === '-') {
                    $operador = $item;
                } else {
                    // Pegar valores 12M e LY da linha referenciada
                    $ref_12m = isset($resultado[$item]) ? $resultado[$item]['media_12_meses'] : 0;
                    $ref_ly = isset($resultado[$item]) ? $resultado[$item]['ly'] : 0;
                    
                    if ($operador === '+') {
                        $ly_calc += $ref_ly;
                    } else {
                        $ly_calc -= $ref_ly;
                    }
                    
                    // Para média 12M, precisamos calcular linha por linha
                    // Simplificação: usar mesma lógica que LY
                    if ($operador === '+') {
                        $media_12m += $ref_12m;
                    } else {
                        $media_12m -= $ref_12m;
                    }
                }
            }
            
            $ly_value = $ly_calc;
        }
        
        // Variação mês a mês
        $variacao_mes = 0;
        if ($valor_anterior != 0) {
            $variacao_mes = (($valor_atual - $valor_anterior) / abs($valor_anterior)) * 100;
        } elseif ($valor_atual != 0) {
            $variacao_mes = 100;
        }
        
        // vs Média 3M
        $vs_media_3m = 0;
        if ($media_3m != 0) {
            $vs_media_3m = (($valor_atual - $media_3m) / abs($media_3m)) * 100;
        } elseif ($valor_atual != 0) {
            $vs_media_3m = 100;
        }
        
        $resultado[$chave] = [
            'nome' => $config['nome'],
            'tipo' => $config['tipo'],
            'media_6m' => round($media_6m, 2),
            'media_3m' => round($media_3m, 2),
            'media_12_meses' => round($media_12m, 2),
            'ly' => round($ly_value, 2),
            'valor_anterior' => round($valor_anterior, 2),
            'valor_atual' => round($valor_atual, 2),
            'vs_media_3m' => round($vs_media_3m, 2),
            'variacao_mes' => round($variacao_mes, 2),
            'subcategorias' => []
        ];
        
        // Adicionar subcategorias se existirem
        if (isset($subcategorias_por_linha[$chave])) {
            foreach ($subcategorias_por_linha[$chave] as $nome_sub => $valores_sub_por_mes) {
                $valores_sub = array_values($valores_sub_por_mes);
                
                $sub_valor_atual = end($valores_sub);
                $sub_valor_anterior = count($valores_sub) > 1 ? $valores_sub[count($valores_sub) - 2] : 0;
                
                $sub_ultimos_3 = array_slice($valores_sub, -3);
                $sub_media_3m = count($sub_ultimos_3) > 0 ? array_sum($sub_ultimos_3) / count($sub_ultimos_3) : 0;
                
                $sub_media_6m = count($valores_sub) > 0 ? array_sum($valores_sub) / count($valores_sub) : 0;
                
                $sub_variacao_mes = 0;
                if ($sub_valor_anterior != 0) {
                    $sub_variacao_mes = (($sub_valor_atual - $sub_valor_anterior) / abs($sub_valor_anterior)) * 100;
                } elseif ($sub_valor_atual != 0) {
                    $sub_variacao_mes = 100;
                }
                
                $sub_vs_media_3m = 0;
                if ($sub_media_3m != 0) {
                    $sub_vs_media_3m = (($sub_valor_atual - $sub_media_3m) / abs($sub_media_3m)) * 100;
                } elseif ($sub_valor_atual != 0) {
                    $sub_vs_media_3m = 100;
                }
                
                $resultado[$chave]['subcategorias'][] = [
                    'nome' => $nome_sub,
                    'media_6m' => round($sub_media_6m, 2),
                    'media_3m' => round($sub_media_3m, 2),
                    'valor_anterior' => round($sub_valor_anterior, 2),
                    'valor_atual' => round($sub_valor_atual, 2),
                    'vs_media_3m' => round($sub_vs_media_3m, 2),
                    'variacao_mes' => round($sub_variacao_mes, 2)
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'periodo' => $periodo,
        'data_final' => $data_final,
        'linhas' => $resultado
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
