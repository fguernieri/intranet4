import os
import pandas as pd
import mysql.connector
from mysql.connector import Error

# --- Configurações do Banco de Dados ---
# Por favor, substitua com suas credenciais e informações do banco de dados
DB_CONFIG = {
    'host': 'bastardsbrewery.com.br',
    'user': 'basta920_lucas',
    'password': 'C;f.(7(2K+D%',
    'database': 'basta920_dw_fabrica'
}

# --- Configurações dos Arquivos ---
# Obtém o diretório onde o script está localizado
# Assume-se que o script está um nível acima da pasta 'filiais'
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
FILIAIS_DIR = os.path.join(BASE_DIR, 'filiais')
FILENAMES = ['CROSS', '7TRAGOS', 'WAB' , 'BDF'] # Lista de prefixos dos arquivos a serem processados

def create_table_if_not_exists(cursor, table_name, columns):
    """
    Cria uma tabela se ela não existir, baseando-se nos nomes das colunas fornecidos.
    Define Estoquetotal como DECIMAL, CODIGO como VARCHAR PK, e outras como VARCHAR.
    Os nomes das colunas são envoltos em crases para lidar com espaços ou caracteres especiais.
    """
    if not columns:
        print(f"Nenhuma coluna encontrada para a tabela {table_name}. Pulando criação.")
        return

    column_defs_list = []

    for col_name in columns:
        col_name_quoted = f"`{col_name}`"
        if col_name == 'Estoquetotal':
            column_defs_list.append(f"{col_name_quoted} DECIMAL(10, 2)") # Ajuste precisão/escala se necessário
        elif col_name == 'CODIGO':
            column_defs_list.append(f"{col_name_quoted} VARCHAR(255)") # CODIGO continua como VARCHAR
        else:
            column_defs_list.append(f"{col_name_quoted} VARCHAR(255)")

    column_definitions_sql = ", ".join(column_defs_list)

    # SQL para criar a tabela
    create_table_sql = f"CREATE TABLE IF NOT EXISTS `{table_name}` ({column_definitions_sql})"
    
    try:
        cursor.execute(create_table_sql)
        print(f"Tabela `{table_name}` verificada/criada com sucesso.")
    except Error as e:
        print(f"Erro ao criar tabela `{table_name}`: {e}")
        raise # Propaga o erro para ser tratado no bloco principal

def insert_data_into_table(db_connection, cursor, table_name, df):
    """
    Insere dados de um DataFrame do Pandas em uma tabela do banco de dados.
    """
    if df.empty:
        print(f"DataFrame para `{table_name}` está vazio. Nenhum dado para inserir.")
        return

    # Substitui os valores pd.NA (ou np.nan) do Pandas por None,
    # que é compatível com SQL NULL.
    # Isso garante que células vazias ou com apenas espaços no Excel
    # (após o tratamento no main) sejam inseridas como NULL no banco.
    # O .astype(object) ajuda a garantir que a substituição funcione bem.
    df_for_insert = df.astype(object).where(pd.notnull(df), None)

    columns = df_for_insert.columns.tolist()
    # Nomes das colunas para a instrução SQL (envolvidos em crases)
    sql_column_names = ", ".join([f"`{col}`" for col in columns])
    # Placeholders para os valores (%s)
    placeholders = ", ".join(["%s"] * len(columns))
    
    insert_sql = f"INSERT INTO `{table_name}` ({sql_column_names}) VALUES ({placeholders})"

    # Converte o DataFrame para uma lista de tuplas para inserção em lote
    data_to_insert = [tuple(row) for row in df_for_insert.to_numpy()]
    
    try:
        cursor.executemany(insert_sql, data_to_insert)
        db_connection.commit() # Confirma as inserções no banco
        print(f"{cursor.rowcount} linhas inseridas com sucesso na tabela `{table_name}`.")
    except Error as e:
        print(f"Erro ao inserir dados na tabela `{table_name}`: {e}")
        db_connection.rollback() # Desfaz as alterações em caso de erro
        raise # Propaga o erro

def main():
    db_connection = None
    try:
        # Estabelece a conexão com o banco de dados
        db_connection = mysql.connector.connect(**DB_CONFIG)
        if db_connection.is_connected():
            print("Conectado ao banco de dados MySQL com sucesso!")
            cursor = db_connection.cursor()

            for filename_prefix in FILENAMES:
                # Tenta encontrar o arquivo com o prefixo, com .xlsx ou com .xls
                possible_filenames_to_try = [filename_prefix, f"{filename_prefix}.xlsx", f"{filename_prefix}.xls"]
                file_path_found = None
                
                for fname_attempt in possible_filenames_to_try:
                    current_path_attempt = os.path.join(FILIAIS_DIR, fname_attempt)
                    if os.path.exists(current_path_attempt):
                        file_path_found = current_path_attempt
                        break # Encontrou o arquivo, sai do loop interno
                
                if not file_path_found:
                    print(f"Arquivo para '{filename_prefix}' não encontrado na pasta '{FILIAIS_DIR}'. Pulando.")
                    continue # Pula para o próximo prefixo de arquivo

                print(f"\nProcessando arquivo: {file_path_found}...")
                # Define o nome da tabela como Estoque + NomeDoArquivo (sem extensão)
                table_name = f"Estoque{filename_prefix}"

                try:
                    # Tenta ler o arquivo como Excel (.xlsx ou .xls).
                    # Se o arquivo tiver múltiplas abas, você pode especificar qual ler:
                    # df = pd.read_excel(file_path_found, sheet_name='NomeDaAba')
                    # Força a leitura de todas as colunas como string para evitar perda de texto devido à inferência de tipo.
                    df = pd.read_excel(file_path_found, dtype=str)
                    
                    if df.empty:
                        print(f"Arquivo '{file_path_found}' está vazio ou não pôde ser lido como Excel (leitura inicial). Pulando.")
                        continue
                        
                    # Remove espaços em branco extras dos nomes das colunas
                    df.columns = df.columns.str.strip()

                    # Limpa espaços no início e no fim do conteúdo de todas as células
                    for col in df.columns:
                        if df[col].dtype == 'object': # Aplica strip apenas em colunas que são strings (object dtype)
                            df[col] = df[col].str.strip()
                    print("Espaços em branco no início/fim do conteúdo das células foram removidos.")

                    # Renomear colunas
                    rename_map = {
                        'Cód. Ref.': 'CODIGO',
                        'Estoque total': 'Estoquetotal'
                    }
                    # Renomeia apenas as colunas que existem no DataFrame
                    actual_renames = {old: new for old, new in rename_map.items() if old in df.columns}
                    if actual_renames:
                        df.rename(columns=actual_renames, inplace=True)
                        print(f"Colunas renomeadas: {actual_renames}")

                    # 'CODIGO' permanecerá como string após a limpeza de espaços.
                    # A conversão para pd.Int64Dtype() foi removida para maior flexibilidade
                    # e para alinhar com a criação da tabela como VARCHAR.
                    if 'CODIGO' in df.columns:
                        print("Coluna 'CODIGO' será mantida como texto.")
                    
                    # Converter 'Estoquetotal' para numérico
                    if 'Estoquetotal' in df.columns:
                        try:
                            df['Estoquetotal'] = pd.to_numeric(df['Estoquetotal'], errors='coerce')
                            print("Coluna 'Estoquetotal' convertida para tipo numérico.")
                        except Exception as e:
                            print(f"Aviso: Falha ao converter a coluna 'Estoquetotal' para numérico. Erro: {e}. Verifique os dados.")

                    # Substitui células que contêm apenas espaços em branco (ou são strings vazias) por pd.NA.
                    # pd.NA é o marcador de missing value do Pandas (requer Pandas >= 1.0).
                    # Isso ajuda a identificar células que parecem ter texto (espaços) mas são efetivamente vazias.
                    # Esta operação afetará principalmente colunas que permaneceram como string.
                    df.replace(r'^\s*$', pd.NA, regex=True, inplace=True)
                    # Remove linhas onde todas as células são NA (ou seja, linhas completamente vazias após o tratamento acima)

                    # Remove linhas duplicadas com base na coluna 'CODIGO' (após renomeação), mantendo a primeira ocorrência.
                    if 'CODIGO' in df.columns:
                        df.drop_duplicates(subset=['CODIGO'], keep='first', inplace=True)
                        print(f"Linhas duplicadas baseadas em 'CODIGO' removidas. Restam {len(df)} linhas.")

                    df.dropna(how='all', inplace=True)

                    # Cria a tabela no banco de dados se ela ainda não existir
                    # Verifica se o DataFrame ficou vazio após remover as linhas totalmente em branco
                    if df.empty:
                        print(f"Arquivo '{file_path_found}' ficou vazio após remover linhas totalmente em branco. Pulando.")
                        continue

                    create_table_if_not_exists(cursor, table_name, df.columns.tolist())
                    
                    # Limpa a tabela antes de inserir novos dados para evitar duplicatas de execuções anteriores.
                    print(f"Limpando tabela `{table_name}` antes de inserir novos dados.")
                    cursor.execute(f"TRUNCATE TABLE `{table_name}`")
                    
                    # Insere os dados do DataFrame na tabela
                    insert_data_into_table(db_connection, cursor, table_name, df)

                except pd.errors.EmptyDataError:
                    print(f"Arquivo '{file_path_found}' está vazio (EmptyDataError). Pulando.")
                except Exception as e:
                    # Captura outras exceções durante o processamento do arquivo
                    print(f"Erro ao processar o arquivo '{file_path_found}': {e}")
            
            cursor.close() # Fecha o cursor

    except Error as e:
        # Captura erros de conexão com o MySQL
        print(f"Erro na conexão com o MySQL: {e}")
    finally:
        # Garante que a conexão com o banco de dados seja fechada
        if db_connection and db_connection.is_connected():
            db_connection.close()
            print("\nConexão com o MySQL fechada.")

if __name__ == '__main__':
    # Ponto de entrada do script
    main()
