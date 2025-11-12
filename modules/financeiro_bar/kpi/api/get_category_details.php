<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Sao_Paulo');

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
require_once __DIR__ . '/../../supabase_connection.php';

// Verificar autenticação
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

header('Content-Type: application/json');

// Receber parâmetros
$categoria_pai = $_GET['categoria'] ?? '';
$periodo = $_GET['periodo'] ?? ''; // formato YYYY/MM (opcional - se vazio, usa último mês fechado)

if (empty($categoria_pai)) {
    http_response_code(400);
    echo json_encode(['error' => 'Categoria não especificada']);
    exit;
}

try {
    $supabase = new SupabaseConnection();
    
    // Mapeamento de meses para português
    $meses_abrev = [
        'Jan' => 'Jan', 'Feb' => 'Fev', 'Mar' => 'Mar', 'Apr' => 'Abr',
        'May' => 'Mai', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Ago',
        'Sep' => 'Set', 'Oct' => 'Out', 'Nov' => 'Nov', 'Dec' => 'Dez'
    ];
    
    // Definir intervalo baseado no período selecionado ou últimos 6 meses
    if (!empty($periodo)) {
        // Se período especificado (formato YYYY/MM), usar como mês final
        // e buscar os 6 meses anteriores a ele
        $periodo_parts = explode('/', $periodo);
        if (count($periodo_parts) === 2) {
            $ano = $periodo_parts[0];
            $mes = str_pad($periodo_parts[1], 2, '0', STR_PAD_LEFT);
            $last_closed = "{$ano}-{$mes}-01";
            $start = date('Y-m-01', strtotime('-5 months', strtotime($last_closed)));
        } else {
            // Formato inválido, usar padrão
            $first_day_this_month = date('Y-m-01');
            $last_closed = date('Y-m-01', strtotime('-1 month', strtotime($first_day_this_month)));
            $start = date('Y-m-01', strtotime('-5 months', strtotime($last_closed)));
        }
    } else {
        // Padrão: últimos 6 meses fechados
        $first_day_this_month = date('Y-m-01');
        $last_closed = date('Y-m-01', strtotime('-1 month', strtotime($first_day_this_month)));
        $start = date('Y-m-01', strtotime('-5 months', strtotime($last_closed)));
    }
    
    // Log para debug das datas
    error_log("=== DEBUG DATAS ===");
    error_log("Período solicitado: " . ($periodo ?: 'PADRÃO (últimos 6 meses)'));
    error_log("Último mês fechado: " . $last_closed);
    error_log("Data início (start): " . $start);
    error_log("Período: " . $start . " até " . $last_closed);
    error_log("===================");;
    
    // Inicializar estrutura de meses
    $months = [];
    for ($i = 0; $i < 6; $i++) { // 6 meses
        $m = date('Y-m', strtotime("+{$i} months", strtotime($start)));
        $mes_en = date('M', strtotime($m . '-01'));
        $mes_pt = $meses_abrev[$mes_en] ?? $mes_en;
        $ano = date('Y', strtotime($m . '-01'));
        $months[$m] = [
            'label' => $mes_pt . '/' . $ano,
            'revenue' => 0.0,
            'total' => 0.0,
            'subcategorias' => []
        ];
    }
    
    // Buscar receitas (para cálculo de %)
    try {
        // Filtrar por data: >= start AND <= last_closed
        $all_receitas = $supabase->select('freceitatap', [
            'select' => 'data_mes,total_receita_mes',
            'filters' => [
                'data_mes' => "gte.{$start}",
            ],
            'order' => 'data_mes.asc', // Ordenar crescente para facilitar
            'limit' => 1000
        ]) ?: [];
        
        // Filtrar no PHP para garantir o range correto
        $all_receitas = array_filter($all_receitas, function($r) use ($start, $last_closed) {
            if (empty($r['data_mes'])) return false;
            return $r['data_mes'] >= $start && $r['data_mes'] <= $last_closed;
        });
        
        foreach ($all_receitas as $r) {
            if (empty($r['data_mes'])) continue;
            $mk = date('Y-m', strtotime($r['data_mes']));
            if (!isset($months[$mk])) continue;
            $months[$mk]['revenue'] += floatval($r['total_receita_mes'] ?? 0);
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar receitas: " . $e->getMessage());
    }
    
    // Buscar despesas da categoria específica (usando fdespesastap agregada)
    // Usar data_mes e total_receita_mes (valor agregado por categoria/mês)
    try {
        $all_despesas_detalhes = $supabase->select('fdespesastap', [
            'select' => 'data_mes,categoria_pai,categoria,total_receita_mes',
            'filters' => [
                'data_mes' => "gte.{$start}",
            ],
            'order' => 'data_mes.asc',
            'limit' => 10000
        ]) ?: [];
        
        // Filtrar no PHP para garantir o range correto
        $all_despesas_detalhes = array_filter($all_despesas_detalhes, function($d) use ($start, $last_closed) {
            if (empty($d['data_mes'])) return false;
            return $d['data_mes'] >= $start && $d['data_mes'] <= $last_closed;
        });
        
    } catch (Exception $e) {
        error_log("Erro ao buscar despesas_detalhes: " . $e->getMessage());
        $all_despesas_detalhes = [];
    }
    
    // Normalizar nome da categoria
    $categoria_pai_norm = strtoupper(trim($categoria_pai));
    
    // Log para debug (remover depois)
    error_log("=== API get_category_details ===");
    error_log("Categoria solicitada ORIGINAL: '" . $categoria_pai . "'");
    error_log("Categoria solicitada NORMALIZADA: '" . $categoria_pai_norm . "'");
    error_log("Total registros despesas BRUTOS: " . count($all_despesas_detalhes));
    
    // Log das primeiras linhas para ver o que está vindo
    if (count($all_despesas_detalhes) > 0) {
        $primeiro = $all_despesas_detalhes[0];
        error_log("Primeiro registro: data_mes=" . ($primeiro['data_mes'] ?? 'NULL') . 
                  ", categoria_pai=" . ($primeiro['categoria_pai'] ?? 'NULL') . 
                  ", categoria=" . ($primeiro['categoria'] ?? 'NULL') . 
                  ", total_receita_mes=" . ($primeiro['total_receita_mes'] ?? 'NULL'));
        
        // Contar quantos registros tem de cada categoria_pai
        $contagem_cats = [];
        foreach ($all_despesas_detalhes as $d) {
            $cp = $d['categoria_pai'] ?? 'NULL';
            $cp_norm = strtoupper(trim($cp));
            if (!isset($contagem_cats[$cp_norm])) $contagem_cats[$cp_norm] = 0;
            $contagem_cats[$cp_norm]++;
        }
        error_log("Categorias_pai encontradas nos dados:");
        foreach ($contagem_cats as $cat => $qtd) {
            $match = ($cat === $categoria_pai_norm) ? " <<< MATCH!" : "";
            error_log("  - '{$cat}': {$qtd} registros{$match}");
        }
    }
    error_log("================================");
    
    // Agrupar por subcategoria e mês
    $subcategorias_info = []; // armazena info de cada subcategoria
    $registros_filtrados = 0;
    
    foreach ($all_despesas_detalhes as $d) {
        if (empty($d['data_mes'])) continue;
        $catpai = strtoupper(trim($d['categoria_pai'] ?? ''));
        
        // Função para normalizar nome de categoria (remover acentos e caracteres especiais)
        $normalizar = function($str) {
            // Remover acentos
            $str = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú', 'À', 'Ã', 'Õ', 'Ê', 'Â', 'Ô', 'Ç'], 
                              ['A', 'E', 'I', 'O', 'U', 'A', 'A', 'O', 'E', 'A', 'O', 'C'], 
                              $str);
            // Remover caracteres especiais como (-), manter apenas letras, números e espaços
            $str = preg_replace('/[^A-Z0-9\s]/', '', $str);
            // Remover espaços múltiplos
            $str = preg_replace('/\s+/', ' ', $str);
            return trim($str);
        };
        
        $catpai_norm = $normalizar($catpai);
        $categoria_solicitada_norm = $normalizar($categoria_pai_norm);
        
        // Filtrar apenas a categoria solicitada
        if ($catpai_norm !== $categoria_solicitada_norm) continue;
        
        $registros_filtrados++;
        
        // Usar data_mes diretamente
        $mk = date('Y-m', strtotime($d['data_mes']));
        if (!isset($months[$mk])) continue;
        
        $subcat = trim($d['categoria'] ?? 'SEM CATEGORIA');
        $valor = floatval($d['total_receita_mes'] ?? 0);
        
        // Inicializar subcategoria se não existir
        if (!isset($subcategorias_info[$subcat])) {
            $subcategorias_info[$subcat] = [];
            foreach ($months as $mes => $info) {
                $subcategorias_info[$subcat][$mes] = 0.0;
            }
        }
        
        // Somar valor
        $subcategorias_info[$subcat][$mk] += $valor;
        $months[$mk]['total'] += $valor;
        
        if (!isset($months[$mk]['subcategorias'][$subcat])) {
            $months[$mk]['subcategorias'][$subcat] = 0.0;
        }
        $months[$mk]['subcategorias'][$subcat] += $valor;
    }
    
    // Log para debug
    error_log("API get_category_details - Registros filtrados para categoria: " . $registros_filtrados);
    error_log("API get_category_details - Total subcategorias encontradas: " . count($subcategorias_info));
    error_log("API get_category_details - Subcategorias: " . implode(', ', array_keys($subcategorias_info)));
    
    // Calcular percentuais e preparar arrays finais
    $chart_labels = [];
    $chart_revenue = [];
    $chart_total = [];
    $chart_pct = [];
    $chart_subcategorias = []; // { "Salários": [valores...], "Aluguel": [...] }
    
    foreach ($months as $m => $vals) {
        $chart_labels[] = $vals['label'];
        $chart_revenue[] = round($vals['revenue'], 2);
        $chart_total[] = round($vals['total'], 2);
        
        if ($vals['revenue'] > 0) {
            $chart_pct[] = round(($vals['total'] / $vals['revenue']) * 100, 2);
        } else {
            $chart_pct[] = null;
        }
        
        // Processar subcategorias
        foreach ($vals['subcategorias'] as $subcat => $valor) {
            if (!isset($chart_subcategorias[$subcat])) {
                $chart_subcategorias[$subcat] = array_fill(0, count($months), 0);
            }
            $idx = array_search($vals['label'], $chart_labels);
            if ($idx !== false) {
                $chart_subcategorias[$subcat][$idx] = round($valor, 2);
            }
        }
    }
    
    // Preencher zeros para subcategorias que não têm valores em alguns meses
    foreach ($chart_subcategorias as $subcat => &$valores) {
        while (count($valores) < count($chart_labels)) {
            $valores[] = 0;
        }
    }
    
    // Calcular métricas de análise para cada subcategoria
    $subcategorias_metricas = [];
    
    foreach ($subcategorias_info as $subcat => $valores_por_mes) {
        $valores = array_values($valores_por_mes);
        $valores_numericos = array_filter($valores, function($v) { return $v > 0; });
        
        if (empty($valores_numericos)) continue;
        
        // Valor atual (último mês)
        $valor_atual = end($valores);
        
        // Valor mês anterior
        $valor_anterior = $valores[count($valores) - 2] ?? 0;
        
        // Variação mês a mês
        $variacao_mes = 0;
        if ($valor_anterior > 0) {
            $variacao_mes = (($valor_atual - $valor_anterior) / $valor_anterior) * 100;
        } elseif ($valor_atual > 0) {
            $variacao_mes = 100; // aumentou de 0
        }
        
        // Média dos 12 meses
        $media = array_sum($valores) / count($valores);
        
        // Média dos últimos 3 meses
        $ultimos_3_meses = array_slice($valores, -3);
        $media_3m = count($ultimos_3_meses) > 0 ? array_sum($ultimos_3_meses) / count($ultimos_3_meses) : 0;
        
        // Média dos últimos 6 meses (todos os meses disponíveis)
        $media_6m = $media; // Como já são 6 meses, é a mesma coisa
        
        // Desvio padrão
        $soma_quadrados = 0;
        foreach ($valores as $v) {
            $soma_quadrados += pow($v - $media, 2);
        }
        $desvio_padrao = sqrt($soma_quadrados / count($valores));
        
        // Coeficiente de Variação (CV)
        $cv = ($media > 0) ? ($desvio_padrao / $media) * 100 : 0;
        
        // ========== ANÁLISE DE TENDÊNCIA MELHORADA ==========
        // Considera múltiplos fatores para determinar tendência real
        
        // 1. Comparação do valor atual com médias
        $valor_vs_media_6m = $media_6m > 0 ? (($valor_atual - $media_6m) / $media_6m) * 100 : 0;
        $valor_vs_media_3m = $media_3m > 0 ? (($valor_atual - $media_3m) / $media_3m) * 100 : 0;
        
        // 2. Comparação média dos últimos 3 vs primeiros 3 meses
        $primeiros_3_meses = array_slice($valores, 0, 3);
        $ultimos_3_meses_para_tendencia = array_slice($valores, -3);
        
        $media_primeiros_3 = count($primeiros_3_meses) > 0 ? array_sum($primeiros_3_meses) / count($primeiros_3_meses) : 0;
        $media_ultimos_3 = count($ultimos_3_meses_para_tendencia) > 0 ? array_sum($ultimos_3_meses_para_tendencia) / count($ultimos_3_meses_para_tendencia) : 0;
        
        $variacao_periodo = 0;
        if ($media_primeiros_3 > 0) {
            $variacao_periodo = (($media_ultimos_3 - $media_primeiros_3) / $media_primeiros_3) * 100;
        } elseif ($media_ultimos_3 > 0) {
            $variacao_periodo = 100;
        }
        
        // 3. Tendência dos últimos 3 meses (regressão linear simples)
        $ultimos_3_valores = array_slice($valores, -3);
        $n = count($ultimos_3_valores);
        $soma_x = 0;
        $soma_y = 0;
        $soma_xy = 0;
        $soma_x2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $x = $i;
            $y = $ultimos_3_valores[$i];
            $soma_x += $x;
            $soma_y += $y;
            $soma_xy += $x * $y;
            $soma_x2 += $x * $x;
        }
        
        $denominador = ($n * $soma_x2) - ($soma_x * $soma_x);
        $coeficiente_angular = 0;
        if ($denominador != 0) {
            $coeficiente_angular = (($n * $soma_xy) - ($soma_x * $soma_y)) / $denominador;
        }
        
        // 4. Decisão de tendência baseada em múltiplos fatores
        $pontos_subida = 0;
        $pontos_descida = 0;
        
        // Fator 1: Valor atual vs Média 6M (peso 2)
        if ($valor_vs_media_6m > 5) {
            $pontos_subida += 2;
        } elseif ($valor_vs_media_6m < -5) {
            $pontos_descida += 2;
        }
        
        // Fator 2: Valor atual vs Média 3M (peso 3 - mais recente)
        if ($valor_vs_media_3m > 5) {
            $pontos_subida += 3;
        } elseif ($valor_vs_media_3m < -5) {
            $pontos_descida += 3;
        }
        
        // Fator 3: Variação mês a mês (peso 2)
        if ($variacao_mes > 5) {
            $pontos_subida += 2;
        } elseif ($variacao_mes < -5) {
            $pontos_descida += 2;
        }
        
        // Fator 4: Coeficiente angular últimos 3 meses (peso 3)
        $media_valores_3m = $media_3m > 0 ? $media_3m : 1;
        $inclinacao_percentual = ($coeficiente_angular / $media_valores_3m) * 100;
        
        if ($inclinacao_percentual > 3) {
            $pontos_subida += 3;
        } elseif ($inclinacao_percentual < -3) {
            $pontos_descida += 3;
        }
        
        // Fator 5: Variação entre primeiros 3 vs últimos 3 (peso 1)
        if ($variacao_periodo > 10) {
            $pontos_subida += 1;
        } elseif ($variacao_periodo < -10) {
            $pontos_descida += 1;
        }
        
        // Classificar tendência baseado na pontuação
        $tendencia = 'Estável';
        $tendencia_status = 'neutro';
        $variacao_tendencia = $valor_vs_media_3m; // usar a comparação mais relevante
        
        $diferenca_pontos = $pontos_subida - $pontos_descida;
        
        if ($diferenca_pontos >= 3) {
            $tendencia = 'Subindo';
            $tendencia_status = 'ruim'; // custo subindo é ruim
        } elseif ($diferenca_pontos <= -3) {
            $tendencia = 'Descendo';
            $tendencia_status = 'bom'; // custo descendo é bom
        }
        // Se diferença é entre -2 e +2, mantém Estável
        
        // ========== FIM ANÁLISE DE TENDÊNCIA ==========
        
        // Crescimento acumulado (primeiro vs último)
        $primeiro_valor = 0;
        foreach ($valores as $v) {
            if ($v > 0) {
                $primeiro_valor = $v;
                break;
            }
        }
        $crescimento_acumulado = 0;
        if ($primeiro_valor > 0) {
            $crescimento_acumulado = (($valor_atual - $primeiro_valor) / $primeiro_valor) * 100;
        }
        
        // % sobre total da categoria (último mês)
        $total_categoria = end($chart_total);
        $pct_categoria_pai = ($total_categoria > 0) ? ($valor_atual / $total_categoria) * 100 : 0;
        
        // % sobre receita (último mês)
        $receita_atual = end($chart_revenue);
        $pct_receita = ($receita_atual > 0) ? ($valor_atual / $receita_atual) * 100 : 0;
        
        $subcategorias_metricas[$subcat] = [
            'nome' => $subcat,
            'valor_atual' => round($valor_atual, 2),
            'valor_anterior' => round($valor_anterior, 2),
            'variacao_mes' => round($variacao_mes, 2),
            'media_12m' => round($media, 2),
            'media_3m' => round($media_3m, 2),
            'media_6m' => round($media_6m, 2),
            'desvio_padrao' => round($desvio_padrao, 2),
            'cv' => round($cv, 2),
            'tendencia' => $tendencia,
            'tendencia_status' => $tendencia_status,
            'variacao_tendencia' => round($variacao_tendencia, 2),
            'crescimento_acumulado' => round($crescimento_acumulado, 2),
            'pct_categoria_pai' => round($pct_categoria_pai, 2),
            'pct_receita' => round($pct_receita, 2),
            'valores_12m' => array_map(function($v) { return round($v, 2); }, $valores)
        ];
    }
    
    // Ordenar subcategorias por valor atual (decrescente)
    usort($subcategorias_metricas, function($a, $b) {
        return $b['valor_atual'] <=> $a['valor_atual'];
    });
    
    // Identificar análises automáticas
    $analises = [
        'maior_crescimento' => null,
        'maior_reducao' => null,
        'tendencia_alta' => null,
        'tendencia_baixa' => null,
        'maior_participacao' => null
    ];
    
    if (!empty($subcategorias_metricas)) {
        // Maior crescimento (variação mês)
        $temp = $subcategorias_metricas;
        usort($temp, function($a, $b) { return $b['variacao_mes'] <=> $a['variacao_mes']; });
        $analises['maior_crescimento'] = $temp[0];
        
        // Maior redução (variação mês negativa)
        usort($temp, function($a, $b) { return $a['variacao_mes'] <=> $b['variacao_mes']; });
        $analises['maior_reducao'] = $temp[0];
        
        // Tendência de alta (maior variação positiva da tendência)
        usort($temp, function($a, $b) { return $b['variacao_tendencia'] <=> $a['variacao_tendencia']; });
        $analises['tendencia_alta'] = $temp[0];
        
        // Tendência de baixa (maior variação negativa da tendência)
        usort($temp, function($a, $b) { return $a['variacao_tendencia'] <=> $b['variacao_tendencia']; });
        $analises['tendencia_baixa'] = $temp[0];
        
        // Maior participação
        usort($temp, function($a, $b) { return $b['pct_categoria_pai'] <=> $a['pct_categoria_pai']; });
        $analises['maior_participacao'] = $temp[0];
    }
    
    // Calcular resumo executivo
    $total_atual = end($chart_total);
    $total_anterior = count($chart_total) > 1 ? $chart_total[count($chart_total) - 2] : 0;
    $receita_atual = end($chart_revenue);
    $pct_receita_atual = ($receita_atual > 0) ? ($total_atual / $receita_atual) * 100 : 0;
    
    // Variação mês a mês do total
    $variacao_total_mes = 0;
    if ($total_anterior > 0) {
        $variacao_total_mes = (($total_atual - $total_anterior) / $total_anterior) * 100;
    } elseif ($total_atual > 0) {
        $variacao_total_mes = 100;
    }
    
    // Calcular tendência geral (média das tendências de todas subcategorias)
    $tendencia_geral_valor = count($subcategorias_metricas) > 0 ? 
        (array_sum(array_column($subcategorias_metricas, 'variacao_tendencia')) / count($subcategorias_metricas)) : 0;
    
    $tendencia_geral = 'Estável';
    $tendencia_geral_status = 'neutro';
    if ($tendencia_geral_valor > 10) {
        $tendencia_geral = 'Subindo';
        $tendencia_geral_status = 'ruim';
    } elseif ($tendencia_geral_valor < -10) {
        $tendencia_geral = 'Descendo';
        $tendencia_geral_status = 'bom';
    }
    
    $resumo = [
        'total_atual' => round($total_atual, 2),
        'total_anterior' => round($total_anterior, 2),
        'variacao_total_mes' => round($variacao_total_mes, 2),
        'receita_atual' => round($receita_atual, 2),
        'pct_receita' => round($pct_receita_atual, 2),
        'maior_subcategoria' => $analises['maior_participacao']['nome'] ?? 'N/A',
        'maior_subcategoria_valor' => $analises['maior_participacao']['valor_atual'] ?? 0,
        'maior_subcategoria_pct' => $analises['maior_participacao']['pct_categoria_pai'] ?? 0,
        'flutuacao_geral' => count($subcategorias_metricas) > 0 ? 
            (array_sum(array_column($subcategorias_metricas, 'cv')) / count($subcategorias_metricas)) : 0,
        'tendencia_geral' => $tendencia_geral,
        'tendencia_geral_status' => $tendencia_geral_status,
        'tendencia_geral_valor' => round($tendencia_geral_valor, 2)
    ];
    
    // Formatar mês de referência em português
    $mes_ref_timestamp = strtotime($last_closed);
    $mes_ref_en = date('M', $mes_ref_timestamp);
    $mes_ref_pt = $meses_abrev[$mes_ref_en] ?? $mes_ref_en;
    $ano_ref = date('Y', $mes_ref_timestamp);
    $mes_referencia = $mes_ref_pt . '/' . $ano_ref;
    
    // Identificar se é categoria de despesa (crescer = ruim)
    $categorias_despesa = ['CUSTO FIXO', 'DESPESA FIXA', 'CUSTO VARIAVEL', 'CUSTO VARIÁVEL', 
                           'TRIBUTOS', 'DESPESAS DE VENDA', 'DESPESA DE VENDA', '(-) DESPESAS DE VENDA',
                           'INVESTIMENTO INTERNO'];
    $eh_despesa = in_array($categoria_pai_norm, $categorias_despesa);
    
    // Montar resposta
    $response = [
        'success' => true,
        'categoria' => $categoria_pai,
        'eh_despesa' => $eh_despesa,
        'mes_referencia' => $mes_referencia,
        'periodo_analise' => date('m/Y', strtotime($start)) . ' - ' . date('m/Y', strtotime($last_closed)),
        'chart' => [
            'labels' => $chart_labels,
            'revenue' => $chart_revenue,
            'total' => $chart_total,
            'pct' => $chart_pct,
            'subcategorias' => $chart_subcategorias
        ],
        'resumo' => $resumo,
        'subcategorias' => $subcategorias_metricas,
        'analises' => $analises
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro ao buscar dados',
        'message' => $e->getMessage()
    ]);
}
?>
