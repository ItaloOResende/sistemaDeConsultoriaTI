import pandas as pd

pd.set_option('display.max_columns', None)
pd.set_option('display.max_rows', 10)
pd.set_option('display.width', None)

aFiltrar = "Produtos Ordenados.csv"


def filtrar_memoria(file_path, memType, memSize):
    print('preparando tabela...')
    csvName= f"memoria_{memType}_{memSize}.csv" 

    try:
        df = pd.read_csv(file_path, encoding='latin1', skiprows=1, header=None, names=['Produto', 'Preço', 'Link'])

        df["Preço"] = pd.to_numeric(df["Preço"])
        dfOrdenado = df.sort_values(by='Preço', ascending=True).reset_index(drop=True)
        dfFiltrado = dfOrdenado[dfOrdenado['Produto'].str.contains(memType, case=False, na=False) & dfOrdenado['Produto'].str.contains(memSize, case=False, na=False)]
        dfFiltrado.to_csv(csvName, index=False)
        #print(dfOrdenado.to_string(index=False))
        return pd.read_csv(csvName,skiprows=1, nrows=10)
    except Exception as e:
        print(f"Ocoreru um erro ao filtrar o arquivo CSV: {e}")
        return None

if __name__ == '__main__':
        print(filtrar_memoria(aFiltrar, "ddr4", "16gb"))