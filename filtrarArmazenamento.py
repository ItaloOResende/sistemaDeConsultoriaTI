import pandas as pd

pd.set_option('display.max_columns', None)
pd.set_option('display.max_rows', 10)
pd.set_option('display.width', None)

aFiltrar = "csvs/Produtos Ordenados.csv"


def filtrar_ssd(file_path, ssdType, ssdSize):
    print('Filtando SSDs...')
    csvName= f"csvs/memoria_{ssdType}_{ssdSize}.csv" 

    try:
        df = pd.read_csv(file_path, encoding='latin1', skiprows=1, header=None, names=['Produto', 'Preço', 'Link'])

        df["Preço"] = pd.to_numeric(df["Preço"])
        dfOrdenado = df.sort_values(by='Preço', ascending=True).reset_index(drop=True)
        dfFiltrado = dfOrdenado[dfOrdenado['Produto'].str.contains(ssdType, case=False, na=False) & dfOrdenado['Produto'].str.contains(ssdSize, case=False, na=False)]
        #deixando outros tipos de ssds e dispositivos externos de fora
        dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains("m.2|nvme|externo", case=False, na=False)]
        dfFiltrado.to_csv(csvName, index=False)
        #print(dfOrdenado.to_string(index=False))
        return pd.read_csv(csvName, nrows=10)
    except Exception as e:
        print(f"Ocoreru um erro ao filtrar o arquivo CSV: {e}")
        return None

def filtrar_ssd_m2(file_path, ssdVariant, ssdType, ssdSize):
    print('Filtando SSDs M.2...')
    csvName= f"csvs/ssd{ssdVariant}_{ssdType}_{ssdSize}.csv" 

    try:
        df = pd.read_csv(file_path, encoding='latin1', skiprows=1, header=None, names=['Produto', 'Preço', 'Link'])

        df["Preço"] = pd.to_numeric(df["Preço"])
        dfOrdenado = df.sort_values(by='Preço', ascending=True).reset_index(drop=True)
        dfFiltrado = dfOrdenado[dfOrdenado['Produto'].str.contains(ssdType, case=False, na=False) & dfOrdenado['Produto'].str.contains(ssdVariant, case=False, na=False) & dfOrdenado['Produto'].str.contains(ssdSize, case=False, na=False)]
        #deixando dispositivos externos de fora
        dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains("externo", case=False, na=False)]
        dfFiltrado.to_csv(csvName, index=False)
        #print(dfOrdenado.to_string(index=False))
        return pd.read_csv(csvName, nrows=10)
    except Exception as e:
        print(f"Ocoreru um erro ao filtrar o arquivo CSV: {e}")
        return None

def filtrar_hdd(file_path, hddtype, hddSize):
    print('Filtando HDDs...')
     

    try:
        df = pd.read_csv(file_path, encoding='latin1', skiprows=1, header=None, names=['Produto', 'Preço', 'Link'])

        df["Preço"] = pd.to_numeric(df["Preço"])
        dfOrdenado = df.sort_values(by='Preço', ascending=True).reset_index(drop=True)
        dfFiltrado = dfOrdenado[dfOrdenado['Produto'].str.contains(hddSize, case=False, na=False)]

        if hddtype == "desktop":
            #deixando ssds e dispositivos externos de fora
            dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains("m.2|nvme|externo|ssd", case=False, na=False)]
            csvName= f"csvs/hdd_{hddSize}.csv"
        elif hddtype == "notebook":
            dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains("m.2|nvme|externo|ssd", case=False, na=False) & dfFiltrado['Produto'].str.contains("slim|notebook", case=False, na=False)]
            csvName= f"csvs/hdd_notebook_{hddSize}.csv"
        dfFiltrado.to_csv(csvName, index=False)
        #print(dfOrdenado.to_string(index=False))
        return pd.read_csv(csvName, nrows=10)
    except Exception as e:
        print(f"Ocoreru um erro ao filtrar o arquivo CSV: {e}")
        return None


if __name__ == '__main__':
        print(filtrar_ssd(aFiltrar, "sata", "1tb"))
        print(filtrar_ssd_m2(aFiltrar, "m.2", "nvme", "1tb"))