import pandas as pd

pd.set_option('display.max_columns', None)
pd.set_option('display.max_rows', 10)
pd.set_option('display.width', None)

aFiltrar = "csvs/Produtos Ordenados.csv"


def filtrar_nvidia(file_path, series, generation,tier):
    print('preparando tabela...')
    csvName= f"csvs/nvidia_{series}_{generation}{tier}.csv" 

    try:
        df = pd.read_csv(file_path, encoding='latin1', skiprows=1, header=None, names=['Produto', 'Preço', 'Link'])
        df["Produto"] = (
        df["Produto"]
        .astype(str)
        .str.replace("VÃÂdeo", "Video", regex=False)
        )
        gpuFilter = f"{generation}{tier}"
        df["Preço"] = pd.to_numeric(df["Preço"])
        dfOrdenado = df.sort_values(by='Preço', ascending=True).reset_index(drop=True)
        dfFiltrado = dfOrdenado[dfOrdenado['Produto'].str.contains(series, case=False, na=False) & dfOrdenado['Produto'].str.contains(gpuFilter, case=False, na=False)]
        dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains("PC Gamer|SSD|AMD|Intel", case=False, na=False)]
        dfFiltrado.to_csv(csvName, index=False)
        #print(dfOrdenado.to_string(index=False))
        return pd.read_csv(csvName, nrows=10)
    except Exception as e:
        print(f"Ocoreru um erro ao filtrar o arquivo CSV: {e}")
        return None

if __name__ == '__main__':
        #print(filtrar_memoria_desktop(aFiltrar, "ddr4", "16gb"))
        print(filtrar_nvidia(aFiltrar, "gtx", "10", "50"))