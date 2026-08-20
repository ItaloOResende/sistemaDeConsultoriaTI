import pandas as pd

pd.set_option('display.max_columns', None)
pd.set_option('display.max_rows', None)
pd.set_option('display.width', None)

def organiza_tabela(file_path):
    print('organizando tabela...')

    try:
        df = pd.read_csv(file_path, encoding='latin1', header=None, names=['Produto', 'Preço', 'Link'])
        df["Preço"] = (
        df["Preço"]
        .astype(str)
        .str.replace("R$", "", regex=False)
        .str.replace(".", "", regex=False)  # Remove ponto de milhar
        .str.replace(",", ".", regex=False)  # Troca vírgula por ponto decimal
        .str.strip()

        )
        

        df["Preço"] = pd.to_numeric(df["Preço"])
        dfOrdenado = df.sort_values(by='Preço', ascending=True).reset_index(drop=True)
        dfOrdenado.to_csv('Produtos Ordenados.csv', index=False)
        #print(dfOrdenado.to_string(index=False))
        return pd.read_csv('Produtos Ordenados.csv', header=None)
    except Exception as e:
        print(f"Error reading the CSV file: {e}")
        return None

