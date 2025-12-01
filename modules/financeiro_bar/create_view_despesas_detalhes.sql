-- View para detalhes dos lançamentos de despesas (sem agrupamento)
-- Esta view mantém todos os lançamentos individuais para permitir drill-down

CREATE VIEW fdespesastap_detalhes AS
SELECT 
    ROW_NUMBER() OVER (ORDER BY f.data_pagto DESC, f.categoria, f.vlr_pago DESC) AS lancamento_id,
    f.nr_empresa,
    f.nr_filial,
    f.nr_lanc,
    f.seq_lanc,
    v.visto,
    DATE_TRUNC('month', f.data_pagto)::DATE AS data_mes,
    f.data_pagto,
    f.categoria,
    d.nivel AS categoria_nivel,
    d.nr_categoria AS categoria_nr,
    d.nr_categoria_superior,
    ds.categoria AS categoria_superior,
    CASE 
        WHEN f.categoria = 'EQUIPAMENTO' THEN 'INVESTIMENTO INTERNO'
        WHEN f.categoria = 'INVESTIMENTO INTERNO' THEN 'INVESTIMENTO INTERNO'
        WHEN f.categoria = 'SAÍDA DE REPASSE' THEN 'SAÍDAS NÃO OPERACIONAIS'
        ELSE dp.categoria
    END AS categoria_pai,
    f.vlr_pago,
    f.descricao,
    f.conta_bancaria,
    f.cliente_fornecedor,
    f.nr_documento,
    f.observacoes,
    f.tipo_de_documento,
    f.centro_de_custo,
    f.vlr_lancamento,
    f.vlr_total
FROM fcontaspagartap f
LEFT JOIN public.fcontaspagartap_vistos v
    ON f.nr_empresa = v.nr_empresa
   AND f.nr_filial = v.nr_filial
   AND f.nr_lanc = v.nr_lanc
   AND f.seq_lanc = v.seq_lanc
LEFT JOIN (
    -- Pegar apenas o registro com maior nível para cada categoria
    SELECT DISTINCT ON (categoria) 
        categoria, nivel, nr_categoria, nr_categoria_superior
    FROM dlegendadretap 
    ORDER BY categoria, nivel DESC
) d ON f.categoria = d.categoria
LEFT JOIN dlegendadretap ds 
    ON d.nr_categoria_superior = ds.nr_categoria
LEFT JOIN dlegendadretap dp 
    ON dp.nr_categoria = d.nr_categoria 
   AND dp.nivel = (d.nivel - 1)
   AND f.categoria NOT IN ('EQUIPAMENTO', 'SAÍDA DE REPASSE')
WHERE f.cod_situacao = 90
  AND f.tipo_lancamento = 10
  AND f.conta_bancaria IN ('TAP BANCO DO BRASIL', 'ITAU TAPROOM', 'CAIXINHA TAP')
ORDER BY f.data_pagto DESC, f.categoria, f.vlr_pago DESC;

COMMENT ON VIEW fdespesastap_detalhes IS 'View detalhada dos lançamentos de despesas sem agrupamento - permite drill-down nos lançamentos individuais de cada categoria';