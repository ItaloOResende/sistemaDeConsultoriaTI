# Filtra fontes de alimentação por capacidade e certificação 80 Plus
import re
import pandas as pd

pd.set_option('display.max_columns', None)
pd.set_option('display.max_rows', 10)
pd.set_option('display.width', None)

aFiltrar = "csvs/Produtos Ordenados.csv"


def filtrar_fontes(file_path, capacidade):
    print(f'filtrando fontes {capacidade}...')
    csvName = f"csvs/fonte_{capacidade.lower()}.csv"

    try:
        df = pd.read_csv(file_path, encoding='latin1', skiprows=1, header=None, names=['Produto', 'Preço', 'Link'])

        df["Preço"] = pd.to_numeric(df["Preço"], errors='coerce')
        dfOrdenado = df.dropna(subset=["Preço"]).sort_values(by='Preço', ascending=True).reset_index(drop=True)
        # Filtra por capacidade (aceita "550W", "550w" e "550 W")
        capacidadeFilter = rf"\b{re.escape(capacidade.rstrip('wW'))}\s*w\b"
        dfFiltrado = dfOrdenado[dfOrdenado['Produto'].str.contains(capacidadeFilter, case=False, na=False, regex=True)]
        # Só fontes com certificação 80 Plus no título
        dfFiltrado = dfFiltrado[dfFiltrado['Produto'].str.contains(r"80\s*plus", case=False, na=False, regex=True)]
        dfFiltrado.to_csv(csvName, index=False)
        if dfFiltrado.empty:
            print(f"nenhuma fonte {capacidade} encontrada")
            return None
        return pd.read_csv(csvName, nrows=10)
    except Exception as e:
        print(f"Ocorreu um erro ao filtrar o arquivo CSV: {e}")
        return None


if __name__ == '__main__':
    print(filtrar_fontes(aFiltrar, "500w"))
