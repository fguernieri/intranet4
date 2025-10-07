#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Consulta rápida da quantidade de registros na tabela fcontaspagartap
"""

import os
import sys
from supabase import create_client, Client

# Configurações do Supabase
SUPABASE_URL = "https://igfvttopbvrnwpydtmjs.supabase.co"
SUPABASE_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImlnZnZ0dG9wYnZybndweWR0bWpzIiwicm9sZSI6ImFub24iLCJpYXQiOjE3MjU4NzgxMzIsImV4cCI6MjA0MTQ1NDEzMn0.JkqV8oWpEKFSzqQODQTnqk7eEXXnYlMxWZKGwF6-PVs"

def main():
    try:
        # Conectar ao Supabase
        supabase: Client = create_client(SUPABASE_URL, SUPABASE_KEY)
        print("Conectado ao Supabase com sucesso!")
        
        # Consultar quantidade total de registros
        response = supabase.table('fcontaspagartap').select('*', count='exact').execute()
        print(f"Total de registros na tabela: {response.count}")
        
        # Consultar registros de agosto 2025
        response_agosto = supabase.table('fcontaspagartap')\
            .select('*', count='exact')\
            .gte('data_pagto', '2025-08-01')\
            .lte('data_pagto', '2025-08-31')\
            .execute()
        print(f"Registros de agosto 2025: {response_agosto.count}")
        
        # Amostra dos primeiros registros
        sample = supabase.table('fcontaspagartap')\
            .select('data_pagto, descricao, categoria, tipo_lancamento, valor')\
            .limit(5)\
            .execute()
        
        print("\nAmostra dos primeiros 5 registros:")
        for i, record in enumerate(sample.data, 1):
            print(f"{i}. Data: {record.get('data_pagto', 'N/A')}, "
                  f"Descrição: {record.get('descricao', 'N/A')[:50]}..., "
                  f"Categoria: {record.get('categoria', 'N/A')}, "
                  f"Tipo: {record.get('tipo_lancamento', 'N/A')}, "
                  f"Valor: {record.get('valor', 'N/A')}")
        
    except Exception as e:
        print(f"Erro: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()