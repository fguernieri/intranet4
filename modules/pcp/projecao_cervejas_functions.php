<?php

/**
 * Função para obter cervejas disponíveis
 */
function getCervejasDisponiveis($conn) {
    // Detecta se é PostgreSQL ou MySQL
    if ($conn instanceof PostgreSQLWrapper) {
        // Query específica para PostgreSQL
        $sql = "SELECT DISTINCT cerveja FROM vw_projecao_estoque ORDER BY cerveja";
    } else {
        // Query para MySQL (usando nomes em minúscula)
        $sql = "SELECT DISTINCT CERVEJA FROM VW_PROJECAO_ESTOQUE ORDER BY CERVEJA";
    }
    
    $result = $conn->query($sql);
    if (!$result) {
        return false;
    }
    
    return $result->fetch_all();
}

/**
 * Função para obter dados de projeção de cervejas - Versão MySQL 5.x
 * Esta versão usa a função mais simples por questões de performance
 */
function getDadosProjecaoCervejas($conn) {
    // Detecta se é PostgreSQL ou MySQL
    if ($conn instanceof PostgreSQLWrapper) {
        // Query específica para PostgreSQL (ajuste os nomes das colunas se necessário)
        $sql = "
            SELECT 
                data,
                cerveja,
                estoque_inicial,
                media_diaria,
                producao_futura,
                prod_sem_tanque,
                producao_atrasada,
                estoque_acumulado,
                projecao_com_e_sem_tanque,
                projecao_com_producao_atrasada
            FROM vw_projecao_estoque 
            ORDER BY cerveja, data
        ";
    } else {
        // Query para MySQL (usando nomes em minúscula da view)
        $sql = "
            SELECT 
                DATA,
                CERVEJA,
                ESTOQUE_INICIAL,
                MEDIA_DIARIA,
                PRODUCAO_FUTURA,
                PROD_SEM_TANQUE,
                PRODUCAO_ATRASADA,
                ESTOQUE_ACUMULADO,
                PROJECAO_COM_E_SEM_TANQUE,
                PROJECAO_COM_PRODUCAO_ATRASADA
            FROM VW_PROJECAO_ESTOQUE
            ORDER BY CERVEJA, DATA
        ";
    }
    
    $result = $conn->query($sql);
    if (!$result) {
        return false;
    }
    
    $dados = $result->fetch_all();
    
    // Normaliza os nomes das colunas para maiúsculo (compatibilidade com o código existente)
    $dados_normalizados = [];
    foreach ($dados as $linha) {
        $linha_normalizada = [];
        foreach ($linha as $chave => $valor) {
            $linha_normalizada[strtoupper($chave)] = $valor;
        }
        $dados_normalizados[] = $linha_normalizada;
    }
    
    return $dados_normalizados;
}

/**
 * Função completa para obter dados de projeção de cervejas - Versão MySQL 5.x
 * Esta versão replica exatamente a query original, mas pode ser lenta
 */
function getDadosProjecaoCervejasCompleta($conn) {
    // Query adaptada para MySQL 5.x (sem CTEs)
    $sql = "
        SELECT 
            eb.DATA,
            eb.CERVEJA,
            eb.ESTOQUE_INICIAL,
            eb.MEDIA_DIARIA,
            eb.PRODUCAO_FUTURA,
            eb.PROD_SEM_TANQUE,
            eb.PRODUCAO_ATRASADA,
            
            -- Cálculo simplificado do estoque acumulado
            GREATEST(
                CASE 
                    WHEN eb.DATA = CURDATE() THEN eb.ESTOQUE_INICIAL
                    ELSE (
                        SELECT 
                            COALESCE(SUM(
                                CASE 
                                    WHEN eb2.DATA = CURDATE() THEN eb2.ESTOQUE_INICIAL
                                    ELSE eb2.PRODUCAO_FUTURA - eb2.MEDIA_DIARIA
                                END
                            ), 0)
                        FROM (
                            SELECT 
                                d.DATA,
                                c.CERVEJA,
                                CASE 
                                    WHEN d.DATA = CURDATE() 
                                    THEN (COALESCE(ei.ESTOQUE_LITROS, 0) + COALESCE(ph.PRODUCAO_HOJE, 0))
                                    ELSE 0 
                                END AS ESTOQUE_INICIAL,
                                COALESCE(md.MEDIA_DIARIA, 0) AS MEDIA_DIARIA,
                                CASE 
                                    WHEN COALESCE(fp_com_tanque.VOLUME, 0) = 4500 
                                    THEN 6000 
                                    ELSE COALESCE(fp_com_tanque.VOLUME, 0) 
                                END AS PRODUCAO_FUTURA,
                                CASE 
                                    WHEN COALESCE(fp_sem_tanque.VOLUME, 0) = 4500 
                                    THEN 6000 
                                    ELSE COALESCE(fp_sem_tanque.VOLUME, 0) 
                                END AS PROD_SEM_TANQUE,
                                COALESCE(pa.PRODUCAO_ATRASADA, 0) AS PRODUCAO_ATRASADA
                            FROM (
                                SELECT (CURDATE() + INTERVAL dias.i DAY) AS DATA
                                FROM (
                                    SELECT 0 AS i UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL
                                    SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL
                                    SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL
                                    SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL
                                    SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL
                                    SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL
                                    SELECT 30 UNION ALL SELECT 31 UNION ALL SELECT 32 UNION ALL SELECT 33 UNION ALL SELECT 34 UNION ALL
                                    SELECT 35 UNION ALL SELECT 36 UNION ALL SELECT 37 UNION ALL SELECT 38 UNION ALL SELECT 39 UNION ALL
                                    SELECT 40 UNION ALL SELECT 41 UNION ALL SELECT 42 UNION ALL SELECT 43 UNION ALL SELECT 44 UNION ALL
                                    SELECT 45 UNION ALL SELECT 46 UNION ALL SELECT 47 UNION ALL SELECT 48 UNION ALL SELECT 49 UNION ALL
                                    SELECT 50 UNION ALL SELECT 51 UNION ALL SELECT 52 UNION ALL SELECT 53 UNION ALL SELECT 54 UNION ALL
                                    SELECT 55 UNION ALL SELECT 56 UNION ALL SELECT 57 UNION ALL SELECT 58 UNION ALL SELECT 59
                                ) dias
                            ) d
                            CROSS JOIN (
                                SELECT DISTINCT CERVEJA FROM vw_estoque_em_litros
                                UNION 
                                SELECT DISTINCT ESTILO AS CERVEJA FROM fordensproducoes
                            ) c
                            LEFT JOIN vw_estoque_em_litros ei ON c.CERVEJA = ei.CERVEJA
                            LEFT JOIN vw_mediavendasparapcp md ON c.CERVEJA = md.CERVEJA
                            LEFT JOIN (
                                SELECT 
                                    fp.ESTILO AS CERVEJA,
                                    fp.DATA_FIM,
                                    SUM(fp.VOLUME) AS VOLUME
                                FROM fordensproducoes fp
                                LEFT JOIN vtodasproducoesliberadasparaenvase vlpe ON fp.LOTE = vlpe.LOTE
                                WHERE fp.DATA_FIM > CURDATE()
                                  AND fp.NOME_TANQUE <> 'TQ Não definido'
                                  AND vlpe.LOTE IS NULL
                                GROUP BY fp.ESTILO, fp.DATA_FIM
                            ) fp_com_tanque ON c.CERVEJA = fp_com_tanque.CERVEJA AND d.DATA = fp_com_tanque.DATA_FIM
                            LEFT JOIN (
                                SELECT 
                                    fp.ESTILO AS CERVEJA,
                                    fp.DATA_FIM,
                                    SUM(fp.VOLUME) AS VOLUME
                                FROM fordensproducoes fp
                                LEFT JOIN vtodasproducoesliberadasparaenvase vlpe ON fp.LOTE = vlpe.LOTE
                                WHERE fp.DATA_FIM > CURDATE()
                                  AND fp.NOME_TANQUE = 'TQ Não definido'
                                  AND vlpe.LOTE IS NULL
                                GROUP BY fp.ESTILO, fp.DATA_FIM
                            ) fp_sem_tanque ON c.CERVEJA = fp_sem_tanque.CERVEJA AND d.DATA = fp_sem_tanque.DATA_FIM
                            LEFT JOIN (
                                SELECT 
                                    fp.ESTILO AS CERVEJA,
                                    SUM(fp.VOLUME) AS PRODUCAO_HOJE
                                FROM fordensproducoes fp
                                LEFT JOIN vtodasproducoesliberadasparaenvase vlpe ON fp.LOTE = vlpe.LOTE
                                WHERE fp.DATA_FIM = CURDATE()
                                  AND vlpe.LOTE IS NULL
                                GROUP BY fp.ESTILO
                            ) ph ON c.CERVEJA = ph.CERVEJA
                            LEFT JOIN (
                                SELECT 
                                    fp.ESTILO AS CERVEJA,
                                    SUM(fp.VOLUME) AS PRODUCAO_ATRASADA
                                FROM fordensproducoes fp
                                LEFT JOIN vtodasproducoesliberadasparaenvase vlpe ON fp.LOTE = vlpe.LOTE
                                WHERE fp.DATA_FIM < CURDATE()
                                  AND (vlpe.LOTE IS NULL OR fp.DATA_FIM = CURDATE())
                                  AND fp.LOTE <> 'HERMES241018E02'
                                GROUP BY fp.ESTILO
                            ) pa ON c.CERVEJA = pa.CERVEJA
                        ) eb2
                        WHERE eb2.CERVEJA = eb.CERVEJA AND eb2.DATA <= eb.DATA
                    )
                END, 0
            ) AS ESTOQUE_ACUMULADO,
            
            -- Projeção com e sem tanque (simplificada)
            GREATEST(
                CASE 
                    WHEN eb.DATA = CURDATE() THEN eb.ESTOQUE_INICIAL + eb.PRODUCAO_FUTURA + eb.PROD_SEM_TANQUE
                    ELSE eb.ESTOQUE_INICIAL + eb.PRODUCAO_FUTURA + eb.PROD_SEM_TANQUE - eb.MEDIA_DIARIA
                END, 0
            ) AS PROJECAO_COM_E_SEM_TANQUE,
            
            -- Projeção com produção atrasada (simplificada)
            GREATEST(
                CASE 
                    WHEN eb.DATA = CURDATE() THEN eb.ESTOQUE_INICIAL + eb.PRODUCAO_FUTURA + eb.PROD_SEM_TANQUE + eb.PRODUCAO_ATRASADA
                    ELSE eb.ESTOQUE_INICIAL + eb.PRODUCAO_FUTURA + eb.PROD_SEM_TANQUE - eb.MEDIA_DIARIA
                END, 0
            ) AS PROJECAO_COM_PRODUCAO_ATRASADA

        FROM (
            SELECT 
                d.DATA,
                c.CERVEJA,
                CASE 
                    WHEN d.DATA = CURDATE() 
                    THEN (COALESCE(ei.ESTOQUE_LITROS, 0) + COALESCE(ph.PRODUCAO_HOJE, 0))
                    ELSE 0 
                END AS ESTOQUE_INICIAL,
                COALESCE(md.MEDIA_DIARIA, 0) AS MEDIA_DIARIA,
                CASE 
                    WHEN COALESCE(fp_com_tanque.VOLUME, 0) = 4500 
                    THEN 6000 
                    ELSE COALESCE(fp_com_tanque.VOLUME, 0) 
                END AS PRODUCAO_FUTURA,
                CASE 
                    WHEN COALESCE(fp_sem_tanque.VOLUME, 0) = 4500 
                    THEN 6000 
                    ELSE COALESCE(fp_sem_tanque.VOLUME, 0) 
                END AS PROD_SEM_TANQUE,
                COALESCE(pa.PRODUCAO_ATRASADA, 0) AS PRODUCAO_ATRASADA
            FROM (
                SELECT (CURDATE() + INTERVAL dias.i DAY) AS DATA
                FROM (
                    SELECT 0 AS i UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL
                    SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL
                    SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL
                    SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL
                    SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL
                    SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL
                    SELECT 30 UNION ALL SELECT 31 UNION ALL SELECT 32 UNION ALL SELECT 33 UNION ALL SELECT 34 UNION ALL
                    SELECT 35 UNION ALL SELECT 36 UNION ALL SELECT 37 UNION ALL SELECT 38 UNION ALL SELECT 39 UNION ALL
                    SELECT 40 UNION ALL SELECT 41 UNION ALL SELECT 42 UNION ALL SELECT 43 UNION ALL SELECT 44 UNION ALL
                    SELECT 45 UNION ALL SELECT 46 UNION ALL SELECT 47 UNION ALL SELECT 48 UNION ALL SELECT 49 UNION ALL
                    SELECT 50 UNION ALL SELECT 51 UNION ALL SELECT 52 UNION ALL SELECT 53 UNION ALL SELECT 54 UNION ALL
                    SELECT 55 UNION ALL SELECT 56 UNION ALL SELECT 57 UNION ALL SELECT 58 UNION ALL SELECT 59
                ) dias
            ) d
            CROSS JOIN (
                SELECT DISTINCT CERVEJA FROM vw_estoque_em_litros
                UNION 
                SELECT DISTINCT ESTILO AS CERVEJA FROM fordensproducoes
            ) c
            LEFT JOIN vw_estoque_em_litros ei ON c.CERVEJA = ei.CERVEJA
            LEFT JOIN vw_mediavendasparapcp md ON c.CERVEJA = md.CERVEJA
            LEFT JOIN (
                SELECT 
                    fp.ESTILO AS CERVEJA,
                    fp.DATA_FIM,
                    SUM(fp.VOLUME) AS VOLUME
                FROM fordensproducoes fp
                LEFT JOIN vtodasproducoesliberadasparaenvase vlpe ON fp.LOTE = vlpe.LOTE
                WHERE fp.DATA_FIM > CURDATE()
                  AND fp.NOME_TANQUE <> 'TQ Não definido'
                  AND vlpe.LOTE IS NULL
                GROUP BY fp.ESTILO, fp.DATA_FIM
            ) fp_com_tanque ON c.CERVEJA = fp_com_tanque.CERVEJA AND d.DATA = fp_com_tanque.DATA_FIM
            LEFT JOIN (
                SELECT 
                    fp.ESTILO AS CERVEJA,
                    fp.DATA_FIM,
                    SUM(fp.VOLUME) AS VOLUME
                FROM fordensproducoes fp
                LEFT JOIN vtodasproducoesliberadasparaenvase vlpe ON fp.LOTE = vlpe.LOTE
                WHERE fp.DATA_FIM > CURDATE()
                  AND fp.NOME_TANQUE = 'TQ Não definido'
                  AND vlpe.LOTE IS NULL
                GROUP BY fp.ESTILO, fp.DATA_FIM
            ) fp_sem_tanque ON c.CERVEJA = fp_sem_tanque.CERVEJA AND d.DATA = fp_sem_tanque.DATA_FIM
            LEFT JOIN (
                SELECT 
                    fp.ESTILO AS CERVEJA,
                    SUM(fp.VOLUME) AS PRODUCAO_HOJE
                FROM fordensproducoes fp
                LEFT JOIN vtodasproducoesliberadasparaenvase vlpe ON fp.LOTE = vlpe.LOTE
                WHERE fp.DATA_FIM = CURDATE()
                  AND vlpe.LOTE IS NULL
                GROUP BY fp.ESTILO
            ) ph ON c.CERVEJA = ph.CERVEJA
            LEFT JOIN (
                SELECT 
                    fp.ESTILO AS CERVEJA,
                    SUM(fp.VOLUME) AS PRODUCAO_ATRASADA
                FROM fordensproducoes fp
                LEFT JOIN vtodasproducoesliberadasparaenvase vlpe ON fp.LOTE = vlpe.LOTE
                WHERE fp.DATA_FIM < CURDATE()
                  AND (vlpe.LOTE IS NULL OR fp.DATA_FIM = CURDATE())
                  AND fp.LOTE <> 'HERMES241018E02'
                GROUP BY fp.ESTILO
            ) pa ON c.CERVEJA = pa.CERVEJA
        ) eb
        ORDER BY eb.CERVEJA, eb.DATA
    ";
    
    $result = $conn->query($sql);
    
    if ($result === false) {
        error_log("Erro na query de projeção de cervejas: " . $conn->error);
        return false;
    }
    
    $dados = [];
    while ($row = $result->fetch_assoc()) {
        $dados[] = $row;
    }
    
    return $dados;
}

/**
 * Função alternativa usando uma abordagem mais simples para MySQL 5.x
 */
function getDadosProjecaoCervejasSimples($conn) {
    // Primeiro, buscar dados base
    $sql_base = "
        SELECT 
            d.DATA,
            c.CERVEJA,
            CASE 
                WHEN d.DATA = CURDATE() 
                THEN (COALESCE(ei.ESTOQUE_LITROS, 0) + COALESCE(ph.PRODUCAO_HOJE, 0))
                ELSE 0 
            END AS ESTOQUE_INICIAL,
            COALESCE(md.MEDIA_DIARIA, 0) AS MEDIA_DIARIA,
            CASE 
                WHEN COALESCE(fp_com_tanque.VOLUME, 0) = 4500 
                THEN 6000 
                ELSE COALESCE(fp_com_tanque.VOLUME, 0) 
            END AS PRODUCAO_FUTURA,
            CASE 
                WHEN COALESCE(fp_sem_tanque.VOLUME, 0) = 4500 
                THEN 6000 
                ELSE COALESCE(fp_sem_tanque.VOLUME, 0) 
            END AS PROD_SEM_TANQUE,
            COALESCE(pa.PRODUCAO_ATRASADA, 0) AS PRODUCAO_ATRASADA
        FROM (
            SELECT (CURDATE() + INTERVAL dias.i DAY) AS DATA
            FROM (
                SELECT 0 AS i UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL
                SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL
                SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL
                SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL
                SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL
                SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL
                SELECT 30 UNION ALL SELECT 31 UNION ALL SELECT 32 UNION ALL SELECT 33 UNION ALL SELECT 34 UNION ALL
                SELECT 35 UNION ALL SELECT 36 UNION ALL SELECT 37 UNION ALL SELECT 38 UNION ALL SELECT 39 UNION ALL
                SELECT 40 UNION ALL SELECT 41 UNION ALL SELECT 42 UNION ALL SELECT 43 UNION ALL SELECT 44 UNION ALL
                SELECT 45 UNION ALL SELECT 46 UNION ALL SELECT 47 UNION ALL SELECT 48 UNION ALL SELECT 49 UNION ALL
                SELECT 50 UNION ALL SELECT 51 UNION ALL SELECT 52 UNION ALL SELECT 53 UNION ALL SELECT 54 UNION ALL
                SELECT 55 UNION ALL SELECT 56 UNION ALL SELECT 57 UNION ALL SELECT 58 UNION ALL SELECT 59
            ) dias
        ) d
        CROSS JOIN (
            SELECT DISTINCT CERVEJA FROM vw_estoque_em_litros
            UNION 
            SELECT DISTINCT ESTILO AS CERVEJA FROM fordensproducoes
        ) c
        LEFT JOIN vw_estoque_em_litros ei ON c.CERVEJA = ei.CERVEJA
        LEFT JOIN vw_mediavendasparapcp md ON c.CERVEJA = md.CERVEJA
        LEFT JOIN (
            SELECT 
                fp.ESTILO AS CERVEJA,
                fp.DATA_FIM,
                SUM(fp.VOLUME) AS VOLUME
            FROM fordensproducoes fp
            LEFT JOIN vtodasproducoesliberadasparaenvase vlpe ON fp.LOTE = vlpe.LOTE
            WHERE fp.DATA_FIM > CURDATE()
              AND fp.NOME_TANQUE <> 'TQ Não definido'
              AND vlpe.LOTE IS NULL
            GROUP BY fp.ESTILO, fp.DATA_FIM
        ) fp_com_tanque ON c.CERVEJA = fp_com_tanque.CERVEJA AND d.DATA = fp_com_tanque.DATA_FIM
        LEFT JOIN (
            SELECT 
                fp.ESTILO AS CERVEJA,
                fp.DATA_FIM,
                SUM(fp.VOLUME) AS VOLUME
            FROM fordensproducoes fp
            LEFT JOIN vtodasproducoesliberadasparaenvase vlpe ON fp.LOTE = vlpe.LOTE
            WHERE fp.DATA_FIM > CURDATE()
              AND fp.NOME_TANQUE = 'TQ Não definido'
              AND vlpe.LOTE IS NULL
            GROUP BY fp.ESTILO, fp.DATA_FIM
        ) fp_sem_tanque ON c.CERVEJA = fp_sem_tanque.CERVEJA AND d.DATA = fp_sem_tanque.DATA_FIM
        LEFT JOIN (
            SELECT 
                fp.ESTILO AS CERVEJA,
                SUM(fp.VOLUME) AS PRODUCAO_HOJE
            FROM fordensproducoes fp
            LEFT JOIN vtodasproducoesliberadasparaenvase vlpe ON fp.LOTE = vlpe.LOTE
            WHERE fp.DATA_FIM = CURDATE()
              AND vlpe.LOTE IS NULL
            GROUP BY fp.ESTILO
        ) ph ON c.CERVEJA = ph.CERVEJA
        LEFT JOIN (
            SELECT 
                fp.ESTILO AS CERVEJA,
                SUM(fp.VOLUME) AS PRODUCAO_ATRASADA
            FROM fordensproducoes fp
            LEFT JOIN vtodasproducoesliberadasparaenvase vlpe ON fp.LOTE = vlpe.LOTE
            WHERE fp.DATA_FIM < CURDATE()
              AND (vlpe.LOTE IS NULL OR fp.DATA_FIM = CURDATE())
              AND fp.LOTE <> 'HERMES241018E02'
            GROUP BY fp.ESTILO
        ) pa ON c.CERVEJA = pa.CERVEJA
        ORDER BY c.CERVEJA, d.DATA
    ";
    
    $result = $conn->query($sql_base);
    
    if ($result === false) {
        error_log("Erro na query base de projeção de cervejas: " . $conn->error);
        return false;
    }
    
    $dados_base = [];
    while ($row = $result->fetch_assoc()) {
        $dados_base[] = $row;
    }
    
    // Processar dados para calcular acumulados
    $dados_finais = [];
    $acumulados = [];
    
    foreach ($dados_base as $row) {
        $cerveja = $row['CERVEJA'];
        
        // Inicializar acumulado se necessário
        if (!isset($acumulados[$cerveja])) {
            $acumulados[$cerveja] = [
                'estoque' => 0,
                'com_sem_tanque' => 0,
                'com_atrasada' => 0
            ];
        }
        
        // Calcular variações diárias
        if ($row['DATA'] == date('Y-m-d')) {
            // Primeiro dia - estoque inicial
            $acumulados[$cerveja]['estoque'] = $row['ESTOQUE_INICIAL'];
            $acumulados[$cerveja]['com_sem_tanque'] = $row['ESTOQUE_INICIAL'] + $row['PRODUCAO_FUTURA'] + $row['PROD_SEM_TANQUE'];
            $acumulados[$cerveja]['com_atrasada'] = $row['ESTOQUE_INICIAL'] + $row['PRODUCAO_FUTURA'] + $row['PROD_SEM_TANQUE'] + $row['PRODUCAO_ATRASADA'];
        } else {
            // Dias seguintes - aplicar variações
            $acumulados[$cerveja]['estoque'] = max(0, $acumulados[$cerveja]['estoque'] + $row['PRODUCAO_FUTURA'] - $row['MEDIA_DIARIA']);
            $acumulados[$cerveja]['com_sem_tanque'] = max(0, $acumulados[$cerveja]['com_sem_tanque'] + $row['PRODUCAO_FUTURA'] + $row['PROD_SEM_TANQUE'] - $row['MEDIA_DIARIA']);
            $acumulados[$cerveja]['com_atrasada'] = max(0, $acumulados[$cerveja]['com_atrasada'] + $row['PRODUCAO_FUTURA'] + $row['PROD_SEM_TANQUE'] - $row['MEDIA_DIARIA']);
        }
        
        // Adicionar aos dados finais
        $dados_finais[] = [
            'DATA' => $row['DATA'],
            'CERVEJA' => $row['CERVEJA'],
            'ESTOQUE_INICIAL' => $row['ESTOQUE_INICIAL'],
            'MEDIA_DIARIA' => $row['MEDIA_DIARIA'],
            'PRODUCAO_FUTURA' => $row['PRODUCAO_FUTURA'],
            'PROD_SEM_TANQUE' => $row['PROD_SEM_TANQUE'],
            'PRODUCAO_ATRASADA' => $row['PRODUCAO_ATRASADA'],
            'ESTOQUE_ACUMULADO' => $acumulados[$cerveja]['estoque'],
            'PROJECAO_COM_E_SEM_TANQUE' => $acumulados[$cerveja]['com_sem_tanque'],
            'PROJECAO_COM_PRODUCAO_ATRASADA' => $acumulados[$cerveja]['com_atrasada']
        ];
    }
    
    return $dados_finais;
}

?>
