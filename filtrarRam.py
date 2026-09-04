import pandas as pd

pd.set_option('display.max_columns', None)
pd.set_option('display.max_rows', 10)
pd.set_option('display.width', None)

aFiltrar = "csvs/Produtos Ordenados.csv"


def filtrar_memoria_desktop(file_path, memType, memSize):
    print('preparando tabela...')
    csvName= f"csvs/memoria_{memType}_{memSize}.csv" 

    try:
        df = pd.read_csv(file_path, encoding='latin1', skiprows=1, header=None, names=['Produto', 'Preço', 'Link'])

        df["Preço"] = pd.to_numeric(df["Preço"])
        dfOrdenado = df.sort_values(by='Preço', ascending=True).reset_index(drop=True)
        dfFiltrado = dfOrdenado[dfOrdenado['Produto'].str.contains(memType, case=False, na=False) & dfOrdenado['Produto'].str.contains(memSize, case=False, na=False)]
        dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains("notebook", case=False, na=False) 
                                | ~dfFiltrado['Produto'].str.contains("SODIMM", case=False, na=False)
                                | ~dfFiltrado['Produto'].str.contains("laptop", case=False, na=False)]
        dfFiltrado.to_csv(csvName, index=False)
        #print(dfOrdenado.to_string(index=False))
        return pd.read_csv(csvName, nrows=10)
    except Exception as e:
        print(f"Ocoreru um erro ao filtrar o arquivo CSV: {e}")
        return None

if __name__ == '__main__':
        print(filtrar_memoria_desktop(aFiltrar, "ddr4", "16gb"))