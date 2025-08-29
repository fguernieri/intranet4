<?php
/**
 * API DRE - Retorna dados hierárquicos para a DRE (Demonstração do Resultado do Exercício)
 *
 * Consome dados de Supabase API das tabelas:
 * - fcontasapagartap (saídas)
 * - fcontasarecebertap (receitas operacionais)
 * - foutrasreceitastap (receitas não operacionais)
 *
 * Agrupa por CATEGORIA > SUBCATEGORIA > DESCRICAO_CONTA
 *

 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php'; // Reutiliza autenticação existente


/**
 * Busca dados do Supabase com filtro opcional de mês/ano.
 * @param string $endpoint
 * @param int|null $mes
 * @param int|null $ano
 * @return array
 */
function fetch_supabase($endpoint, $mes = null, $ano = null) {
    $url = 'https://gybhszcefuxsdhpvxbnk.supabase.co/rest/v1/' . $endpoint;
    $apiKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imd5YmhzemNlZnV4c2RocHZ4Ym5rIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTMwMTYwOTYsImV4cCI6MjA2ODU5MjA5Nn0.i3sOjUvpsimEvtO0uDGFNK92IZjcy1VIva_KEBdlZI8';
    $params = [];
    if ($mes && $ano) {
        // Monta filtro para DATA_PAGAMENTO no formato YYYY-MM
        $mesStr = str_pad($mes, 2, '0', STR_PAD_LEFT);
        $inicio = "$ano-$mesStr-01";
        $fim = date('Y-m-t', strtotime($inicio));
        // Supabase usa query string para filtros: campo=gte.data&campo=lte.data
        $params[] = "DATA_PAGAMENTO=gte.$inicio";
        $params[] = "DATA_PAGAMENTO=lte.$fim";
    }
    if ($params) {
        $url .= '?' . implode('&', $params);
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $apiKey,
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($result === false || $httpCode !== 200) {
        return [];
    }
    curl_close($ch);
    return json_decode($result, true);
}

try {
    // Recebe filtros
    $mes = isset($_GET['mes']) ? intval($_GET['mes']) : null;
    $ano = isset($_GET['ano']) ? intval($_GET['ano']) : null;

    // Busca dados das três tabelas separadamente (mês/ano atual)
    $dados_receita = fetch_supabase('fcontasarecebertap', $mes, $ano);
    $dados_despesa = fetch_supabase('fcontasapagartap', $mes, $ano);
    $dados_outras = fetch_supabase('foutrasreceitastap', $mes, $ano);

    // Busca dados dos 3 meses anteriores para cálculo de média
    $medias_meses = [];
    if ($mes && $ano) {
        $medias_meses = [];
        for ($i = 1; $i <= 3; $i++) {
            $mes_ref = $mes - $i;
            $ano_ref = $ano;
            if ($mes_ref <= 0) {
                $mes_ref += 12;
                $ano_ref--;
            }
            $medias_meses[] = [
                'mes' => $mes_ref,
                'ano' => $ano_ref,
                'receita' => fetch_supabase('fcontasarecebertap', $mes_ref, $ano_ref),
                'despesa' => fetch_supabase('fcontasapagartap', $mes_ref, $ano_ref),
                'outras' => fetch_supabase('foutrasreceitastap', $mes_ref, $ano_ref),
            ];
        }
    }

    // Receita Operacional: só total
    $total_receita = 0;
    foreach ($dados_receita as $row) {
        $total_receita += floatval($row['VALOR'] ?? 0);
    }
    // Calcula média dos últimos 3 meses para Receita Operacional
    $media_receita = 0;
    $media_count = 0;
    foreach ($medias_meses as $m) {
        $soma = 0;
        foreach ($m['receita'] as $row) {
            $soma += floatval($row['VALOR'] ?? 0);
        }
        $media_receita += $soma;
        $media_count++;
    }
    $media_receita = $media_count > 0 ? $media_receita / $media_count : 0;

    $dre = [];
    $dre[] = [
        'categoria' => 'Receita Operacional',
        'valor' => $total_receita,
        'media_3m' => $media_receita,
        'percentual_receita_operacional' => 100.0,
        'percentual_media_receita_operacional' => 100.0,
        'subcategorias' => [],
        'tipo_linha' => 'fixa',
        'nivel' => 1
    ];

    // Demais contas agrupadas hierarquicamente (despesas e outras receitas)
    $dados = array_merge($dados_despesa, $dados_outras);
    $tree = [];
    // Para médias: precisamos de arrays de valores por categoria/subcategoria
    $medias_cat = [];
    $medias_sub = [];
    foreach ($dados as $row) {
        $cat = $row['CATEGORIA'] ?? 'Sem Categoria';
        $sub = $row['SUBCATEGORIA'] ?? 'Sem Subcategoria';
        $desc = $row['DESCRICAO_CONTA'] ?? 'Sem Descrição';
        $valor = floatval($row['VALOR'] ?? 0);

        if (!isset($tree[$cat])) {
            $tree[$cat] = [
                'categoria' => $cat,
                'valor' => 0,
                'media_3m' => 0,
                'subcategorias' => [],
                'tipo_linha' => 'hierarquia',
                'nivel' => 1
            ];
        }
        if (!isset($tree[$cat]['subcategorias'][$sub])) {
            $tree[$cat]['subcategorias'][$sub] = [
                'subcategoria' => $sub,
                'valor' => 0,
                'media_3m' => 0,
                'contas' => [],
                'nivel' => 2
            ];
        }
        $tree[$cat]['valor'] += $valor;
        $tree[$cat]['subcategorias'][$sub]['valor'] += $valor;
        $tree[$cat]['subcategorias'][$sub]['contas'][] = [
            'descricao_conta' => $desc,
            'valor' => $valor,
            'nivel' => 3,
            'parcela' => $row['PARCELA'] ?? '',
            'data_pagamento' => $row['DATA_PAGAMENTO'] ?? '',
            'status' => $row['STATUS'] ?? '',
            'tipo' => $row['TIPO'] ?? '',
            'id_conta' => $row['ID_CONTA'] ?? '',
            'fornecedor' => $row['FORNECEDOR'] ?? '',
        ];
    }

    // Calcula médias para cada categoria e subcategoria
    foreach ($tree as $cat => &$catData) {
        $soma_cat = 0;
        $count_cat = 0;
        foreach ($medias_meses as $m) {
            $mes_dados = array_merge($m['despesa'], $m['outras']);
            foreach ($mes_dados as $row) {
                if (($row['CATEGORIA'] ?? 'Sem Categoria') === $cat) {
                    $soma_cat += floatval($row['VALOR'] ?? 0);
                    $count_cat++;
                }
            }
        }
        $catData['media_3m'] = $count_cat > 0 ? $soma_cat / 3 : 0;
        $catData['percentual_receita_operacional'] = $total_receita > 0 ? ($catData['valor'] / $total_receita) * 100 : 0;
        $catData['percentual_media_receita_operacional'] = $media_receita > 0 ? ($catData['media_3m'] / $media_receita) * 100 : 0;
        foreach ($catData['subcategorias'] as $sub => &$subData) {
            $soma_sub = 0;
            $count_sub = 0;
            foreach ($medias_meses as $m) {
                $mes_dados = array_merge($m['despesa'], $m['outras']);
                foreach ($mes_dados as $row) {
                    if (($row['CATEGORIA'] ?? 'Sem Categoria') === $cat && ($row['SUBCATEGORIA'] ?? 'Sem Subcategoria') === $sub) {
                        $soma_sub += floatval($row['VALOR'] ?? 0);
                        $count_sub++;
                    }
                }
            }
            $subData['media_3m'] = $count_sub > 0 ? $soma_sub / 3 : 0;
            $subData['percentual_receita_operacional'] = $total_receita > 0 ? ($subData['valor'] / $total_receita) * 100 : 0;
            $subData['percentual_media_receita_operacional'] = $media_receita > 0 ? ($subData['media_3m'] / $media_receita) * 100 : 0;
        }
        unset($subData);
    }
    unset($catData);
    // Ordem e cálculo fixos
    $ordem = [
        'TRIBUTOS',
        'RECEITA LIQUIDA',
        'CUSTO VARIAVEL',
        'LUCRO BRUTO',
        'CUSTO FIXO',
        'DESPESA FIXA',
        'DESPESA DE VENDA',
        'LUCRO LIQUIDO',
        'INVESTIMENTO INTERNO',
        'AMORTIZACAO',
        'RECEITAS NAO OPERACIONAIS',
        'SAIDAS NAO OPERACIONAIS',
        'RETIRADA DE LUCRO',
        'SALDO NAO OPERACIONAL',
        'FLUXO DE CAIXA'
    ];

    $get_val = function($cat) use ($tree) {
        return isset($tree[$cat]) ? $tree[$cat]['valor'] : 0;
    };
    $get_media = function($cat) use ($tree) {
        return isset($tree[$cat]) ? $tree[$cat]['media_3m'] : 0;
    };

    $valor_tributos = $get_val('TRIBUTOS');
    $media_tributos = $get_media('TRIBUTOS');
    $valor_receita_liquida = $total_receita - $valor_tributos;
    $media_receita_liquida = $media_receita - $media_tributos;
    $valor_custo_variavel = $get_val('CUSTO VARIAVEL');
    $media_custo_variavel = $get_media('CUSTO VARIAVEL');
    $valor_lucro_bruto = $valor_receita_liquida - $valor_custo_variavel;
    $media_lucro_bruto = $media_receita_liquida - $media_custo_variavel;
    $valor_custo_fixo = $get_val('CUSTO FIXO');
    $media_custo_fixo = $get_media('CUSTO FIXO');
    $valor_despesa_fixa = $get_val('DESPESA FIXA');
    $media_despesa_fixa = $get_media('DESPESA FIXA');
    $valor_despesa_venda = $get_val('DESPESA DE VENDA');
    $media_despesa_venda = $get_media('DESPESA DE VENDA');
    $valor_lucro_liquido = $valor_lucro_bruto - ($valor_custo_fixo + $valor_despesa_fixa + $valor_despesa_venda);
    $media_lucro_liquido = $media_lucro_bruto - ($media_custo_fixo + $media_despesa_fixa + $media_despesa_venda);
    $valor_investimento_interno = $get_val('INVESTIMENTO INTERNO');
    $media_investimento_interno = $get_media('INVESTIMENTO INTERNO');
    $valor_amortizacao = $get_val('AMORTIZACAO');
    $media_amortizacao = $get_media('AMORTIZACAO');
    $valor_receitas_nao_op = $get_val('RECEITAS NAO OPERACIONAIS');
    $media_receitas_nao_op = $get_media('RECEITAS NAO OPERACIONAIS');
    $valor_saidas_nao_op = $get_val('SAIDAS NAO OPERACIONAIS');
    $media_saidas_nao_op = $get_media('SAIDAS NAO OPERACIONAIS');
    $valor_retirada_lucro = $get_val('RETIRADA DE LUCRO');
    $media_retirada_lucro = $get_media('RETIRADA DE LUCRO');
    $valor_saldo_nao_op = $valor_receitas_nao_op - $valor_saidas_nao_op - $valor_retirada_lucro;
    $media_saldo_nao_op = $media_receitas_nao_op - $media_saidas_nao_op - $media_retirada_lucro;
    // FLUXO DE CAIXA usa o saldo não operacional calculado acima
    $valor_fluxo_caixa = $valor_lucro_liquido - ($valor_investimento_interno + $valor_amortizacao) + $valor_saldo_nao_op;
    $media_fluxo_caixa = $media_lucro_liquido - ($media_investimento_interno + $media_amortizacao) + $media_saldo_nao_op;

    $dre_ordenado = [];
    foreach ($ordem as $cat) {
        if ($cat === 'RECEITA LIQUIDA') {
            $dre_ordenado[] = [
                'categoria' => 'RECEITA LIQUIDA',
                'valor' => $valor_receita_liquida,
                'media_3m' => $media_receita_liquida,
                'percentual_receita_operacional' => $total_receita > 0 ? ($valor_receita_liquida / $total_receita) * 100 : 0,
                'percentual_media_receita_operacional' => $media_receita > 0 ? ($media_receita_liquida / $media_receita) * 100 : 0,
                'subcategorias' => [],
                'tipo_linha' => 'linha_calculada',
                'nivel' => 1
            ];
        } else if ($cat === 'LUCRO BRUTO') {
            $dre_ordenado[] = [
                'categoria' => 'LUCRO BRUTO',
                'valor' => $valor_lucro_bruto,
                'media_3m' => $media_lucro_bruto,
                'percentual_receita_operacional' => $total_receita > 0 ? ($valor_lucro_bruto / $total_receita) * 100 : 0,
                'percentual_media_receita_operacional' => $media_receita > 0 ? ($media_lucro_bruto / $media_receita) * 100 : 0,
                'subcategorias' => [],
                'tipo_linha' => 'linha_calculada',
                'nivel' => 1
            ];
        } else if ($cat === 'LUCRO LIQUIDO') {
            $dre_ordenado[] = [
                'categoria' => 'LUCRO LIQUIDO',
                'valor' => $valor_lucro_liquido,
                'media_3m' => $media_lucro_liquido,
                'percentual_receita_operacional' => $total_receita > 0 ? ($valor_lucro_liquido / $total_receita) * 100 : 0,
                'percentual_media_receita_operacional' => $media_receita > 0 ? ($media_lucro_liquido / $media_receita) * 100 : 0,
                'subcategorias' => [],
                'tipo_linha' => 'linha_calculada',
                'nivel' => 1
            ];
        } else if ($cat === 'SALDO NAO OPERACIONAL') {
            $dre_ordenado[] = [
                'categoria' => 'SALDO NAO OPERACIONAL',
                'valor' => $valor_saldo_nao_op,
                'media_3m' => $media_saldo_nao_op,
                'percentual_receita_operacional' => $total_receita > 0 ? ($valor_saldo_nao_op / $total_receita) * 100 : 0,
                'percentual_media_receita_operacional' => $media_receita > 0 ? ($media_saldo_nao_op / $media_receita) * 100 : 0,
                'subcategorias' => [],
                'tipo_linha' => 'linha_calculada',
                'nivel' => 1
            ];
        } else if ($cat === 'FLUXO DE CAIXA') {
            $dre_ordenado[] = [
                'categoria' => 'FLUXO DE CAIXA',
                'valor' => $valor_fluxo_caixa,
                'media_3m' => $media_fluxo_caixa,
                'percentual_receita_operacional' => $total_receita > 0 ? ($valor_fluxo_caixa / $total_receita) * 100 : 0,
                'percentual_media_receita_operacional' => $media_receita > 0 ? ($media_fluxo_caixa / $media_receita) * 100 : 0,
                'subcategorias' => [],
                'tipo_linha' => 'linha_calculada',
                'nivel' => 1
            ];
        } else {
            if (isset($tree[$cat])) {
                $catData = $tree[$cat];
                $catData['tipo_linha'] = 'categoria';
                $catData['subcategorias'] = array_values($catData['subcategorias']);
                $dre_ordenado[] = $catData;
            }
        }
    }

    $dre = array_merge([$dre[0]], $dre_ordenado);

    echo json_encode([
        'code' => 200,
        'message' => 'ok',
        'data' => $dre,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'code' => 500,
        'message' => 'Erro: ' . $e->getMessage(),
        'data' => [],
    ]);
}