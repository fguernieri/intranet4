-- Substitui a view public.fdespesastap_detalhes pela nova definição (v2)
-- ATENÇÂO: este script DROPA a view antiga (com CASCADE) e cria a nova.
-- Faça backup da definição atual e das dependências antes de executar.

BEGIN;

-- Opcional: você pode comentar a linha abaixo e primeiro rodar o backup sugerido no README antes de dropar.
DROP VIEW IF EXISTS public.fdespesastap_detalhes CASCADE;

CREATE VIEW public.fdespesastap_detalhes AS
SELECT
    ROW_NUMBER() OVER (ORDER BY f.data_pagto DESC, f.categoria, f.vlr_pago DESC) AS lancamento_id,
    f.nr_empresa,
    f.nr_filial,
    f.nr_lanc,
    f.seq_lanc,
    v.visto,
    DATE_TRUNC('month', f.data_pagto)::DATE AS data_mes,
    f.data_pagto,
    UPPER(TRIM(BOTH FROM f.categoria)) AS categoria,
    d.nivel AS categoria_nivel,
    d.nr_categoria AS categoria_nr,
    d.nr_categoria_superior,
    UPPER(TRIM(BOTH FROM ds.categoria)) AS categoria_superior,
    CASE
        WHEN UPPER(TRIM(BOTH FROM f.categoria)) = 'EQUIPAMENTO' THEN 'INVESTIMENTO INTERNO'
        WHEN UPPER(TRIM(BOTH FROM f.categoria)) = 'EVENTOS' THEN 'INVESTIMENTO INTERNO'
        WHEN UPPER(TRIM(BOTH FROM f.categoria)) = 'OUTROS INVESTIMENTOS' THEN 'INVESTIMENTO INTERNO'
        WHEN UPPER(TRIM(BOTH FROM f.categoria)) = 'INVESTIMENTO INTERNO' THEN 'INVESTIMENTO INTERNO'
        WHEN UPPER(TRIM(BOTH FROM f.categoria)) = 'SAÍDA DE REPASSE' THEN 'SAÍDAS NÃO OPERACIONAIS'
        ELSE UPPER(TRIM(BOTH FROM dp.categoria))
    END AS categoria_pai,
    f.vlr_pago,
    TRIM(BOTH FROM f.descricao) AS descricao,
    f.conta_bancaria,
    TRIM(BOTH FROM f.cliente_fornecedor) AS cliente_fornecedor,
    f.nr_documento,
    TRIM(BOTH FROM f.observacoes) AS observacoes,
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
    SELECT DISTINCT ON (UPPER(TRIM(BOTH FROM dlegendadretap.categoria)))
        UPPER(TRIM(BOTH FROM dlegendadretap.categoria)) AS categoria,
        dlegendadretap.nivel,
        dlegendadretap.nr_categoria,
        dlegendadretap.nr_categoria_superior
    FROM dlegendadretap
    ORDER BY UPPER(TRIM(BOTH FROM dlegendadretap.categoria)), dlegendadretap.nivel DESC
) d ON UPPER(TRIM(BOTH FROM f.categoria)) = d.categoria
LEFT JOIN dlegendadretap ds ON d.nr_categoria_superior = ds.nr_categoria
LEFT JOIN dlegendadretap dp ON dp.nr_categoria = d.nr_categoria
   AND dp.nivel = (d.nivel - 1)
   AND UPPER(TRIM(BOTH FROM f.categoria)) NOT IN ('EQUIPAMENTO','EVENTOS','OUTROS INVESTIMENTOS','SAÍDA DE REPASSE')
WHERE
  f.cod_situacao = 90
  AND f.tipo_lancamento = 10
  AND f.conta_bancaria IN ('TAP BANCO DO BRASIL','ITAU TAPROOM','CAIXINHA TAP')
ORDER BY f.data_pagto DESC, UPPER(TRIM(BOTH FROM f.categoria)), f.vlr_pago DESC;

COMMENT ON VIEW public.fdespesastap_detalhes IS 'Versão substituída: inclui chaves (nr_empresa,nr_filial,nr_lanc,seq_lanc) e a coluna visto (v.visto) para uso no dashboard.';

COMMIT;
