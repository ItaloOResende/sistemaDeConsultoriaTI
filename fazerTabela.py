# Organiza e limpa os preços extraídos das lojas
import pandas as pd

pd.set_option('display.max_columns', None)
pd.set_option('display.max_rows', None)
pd.set_option('display.width', None)

def prepara_tabela(file_path):
    print('preparando tabela...')

    try:
        # Lê CSV com produtos, preços e links
        df = pd.read_csv(file_path, encoding='latin1', header=None, names=['Produto', 'Preço', 'Link'])
        # Limpa e converte o campo de preço para numérico
        df["Preço"] = (
            df["Preço"]
            .astype(str)
            .str.replace("R$", "", regex=False)
            .str.replace("Â", "", regex=False)
            .str.replace(".", "", regex=False)  # Remove ponto de milhar
            .str.replace(",", ".", regex=False)  # Troca vírgula por ponto decimal
            .str.strip()
        )

        df["Preço"] = pd.to_numeric(df["Preço"])
        # Ordena por preço crescente
        dfOrdenado = df.sort_values(by='Preço', ascending=True).reset_index(drop=True)
        # Salva tabela ordenada
        dfOrdenado.to_csv('csvs/Produtos Ordenados.csv', index=False)
        return pd.read_csv('csvs/Produtos Ordenados.csv', header=None)
    except Exception as e:
        resultado = f"Ocorreu um erro ao organizar o arquivo CSV: {e}"
        return resultado

if __name__ == '__main__':
    print(prepara_tabela('csvs/preços.csv'))