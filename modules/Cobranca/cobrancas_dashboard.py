#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
  Executa a consulta na view v_cobrancas_em_aberto
  e insere/atualiza o snapshot do dia em cobrancas_snapshot.
  Pode ser agendado para rodar toda quinta (cron) ou
  chamado manualmente antes de abrir o dashboard.
"""
import pymysql
import os
import logging
from datetime import date
from typing import Dict, Any, List, Tuple


DB = dict(
    host     = os.getenv('DB_HOST', 'bastardsbrewery.com.br'),
    user     = os.getenv('DB_USER', 'basta920_lucas'),
    password = os.getenv('DB_PASS', 'C;f.(7(2K+D%'),
    database = os.getenv('DB_NAME', 'basta920_dw_fabrica'),
    charset  = 'utf8mb4',
    cursorclass = pymysql.cursors.DictCursor
)

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

SQL_VIEW = """
SELECT  ID_CLIENTE, CLIENTE, NUMERO_PEDIDO, NUMERO_PARCELA,
        TOTAL_COM_JUROS AS VALOR_VENCIDO,
        DIAS_VENCIDOS, DATA_VENCIMENTO
FROM    vw_cobrancas_vencidas;
"""

def main() -> None:
    hoje: date = date.today()
    with pymysql.connect(**DB) as conn, conn.cursor() as cur:
        logging.info('→ Lendo view vw_contas_atrasadas_hrx…')
        cur.execute(SQL_VIEW)
        rows: List[Dict[str, Any]] = cur.fetchall()

        logging.info(f'→ {len(rows)} registros lidos da view.')
        logging.info('→ Gravando snapshot em cobrancas_snapshot…')
        sql_ins = """
        INSERT INTO cobrancas_snapshot
        (data_ref,id_cliente,cliente,numero_pedido,numero_parcela,
         valor_vencido,dias_vencidos,data_vencimento)
        VALUES (%s,%s,%s,%s,%s,%s,%s,%s)
        ON DUPLICATE KEY UPDATE
            valor_vencido = VALUES(valor_vencido),
            dias_vencidos = VALUES(dias_vencidos),
            data_vencimento = VALUES(data_vencimento);
        """
        data_to_insert: List[Tuple] = [
            (hoje, r['ID_CLIENTE'], r['CLIENTE'],
             r['NUMERO_PEDIDO'], r['NUMERO_PARCELA'],
             r['VALOR_VENCIDO'], r['DIAS_VENCIDOS'],
             r['DATA_VENCIMENTO']) for r in rows
        ]
        if data_to_insert:
            cur.executemany(sql_ins, data_to_insert)
            conn.commit()
            logging.info(f'✔ Snapshot de {len(data_to_insert)} títulos salvo para {hoje:%Y-%m-%d}')
        else:
            logging.info('✔ Nenhum dado para inserir no snapshot.')

if __name__ == '__main__':
    main()
