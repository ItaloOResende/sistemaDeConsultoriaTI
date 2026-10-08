# Filtra gabinetes por tamanho (mini tower / mid tower / full tower)
import re
import pandas as pd

pd.set_option('display.max_columns', None)
pd.set_option('display.max_rows', 10)
pd.set_option('display.width', None)

aFiltrar = "csvs/Produtos Ordenados.csv"


def filtrar_gabinetes(file_path, tipo):
    print(f'filtrando gabinetes {tipo}...')
    csvName = f"csvs/gabinete_{tipo.replace(' ', '_')}.csv"

    try:
        df = pd.read_csv(file_path, encoding='latin1', skiprows=1, header=None, names=['Produto', 'Preço', 'Link'])

        df["Preço"] = pd.to_numeric(df["Preço"], errors='coerce')
        dfOrdenado = df.dropna(subset=["Preço"]).sort_values(by='Preço', ascending=True).reset_index(drop=True)
        # Filtra por tamanho (aceita "mid tower", "mid-tower" e "Mid Tower")
        tipoFilter = rf"{tipo.replace(' ', r'[\s-]+')}"
        dfFiltrado = dfOrdenado[dfOrdenado['Produto'].str.contains(tipoFilter, case=False, na=False, regex=True)]
        # Só gabinetes no título
        dfFiltrado = dfFiltrado[dfFiltrado['Produto'].str.contains("gabinete|case", case=False, na=False)]
        # Exclui peças que não são gabinetes
        dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains("notebook|pc gamer|fonte|processador|cooler", case=False, na=False)]
        dfFiltrado.to_csv(csvName, index=False)
        if dfFiltrado.empty:
            print(f"nenhum gabinete {tipo} encontrado")
            return None
        return pd.read_csv(csvName, nrows=10)
    except Exception as e:
        print(f"Ocorreu um erro ao filtrar o arquivo CSV: {e}")
        return None


if __name__ == '__main__':
    print(filtrar_gabinetes(aFiltrar, "mid tower"))
