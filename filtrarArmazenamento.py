# Filtra dispositivos de armazenamento (SSD/HDD) por tipo, variante e capacidade
import pandas as pd

pd.set_option('display.max_columns', None)
pd.set_option('display.max_rows', 10)
pd.set_option('display.width', None)

aFiltrar = "csvs/Produtos Ordenados.csv"


def filtrar_amazenamento(file_path, armType, armVariant, armSize):
    print('Filtando armazenamento...')
    csvName = f"csvs/{armType}_{armVariant}_{armSize}.csv"

    try:
        df = pd.read_csv(file_path, encoding='latin1', skiprows=1, header=None, names=['Produto', 'Preço', 'Link'])

        df["Preço"] = pd.to_numeric(df["Preço"])
        dfOrdenado = df.sort_values(by='Preço', ascending=True).reset_index(drop=True)
        # Define filtros para tamanho
        match armSize.lower():
            case "128gb":
                sizeFilter = "120gb|128gb"
            case "256gb":
                sizeFilter = "256gb|240gb"
            case "512gb":
                sizeFilter = "480gb|512gb|500gb"
            case "1tb":
                sizeFilter = "960gb|1tb"
            case "2tb":
                sizeFilter = "2tb"

        match armType.lower():
            case "hdd":
                match armVariant.lower():
                    case "sata":
                        dfFiltrado = dfOrdenado[dfOrdenado['Produto'].str.contains("HD", case=False, na=False) & dfOrdenado['Produto'].str.contains(armSize, case=False, na=False)]
                        #deixando outros tipos de ssds e dispositivos externos de fora
                        dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains("m.2|nvme|externo|ssd|nas|case", case=False, na=False)]
            case "ssd":
                match armVariant.lower():
                    case "m.2 sata":
                        dfOrdenado = df.sort_values(by='Preço', ascending=True).reset_index(drop=True)
                        dfFiltrado = dfOrdenado[dfOrdenado['Produto'].str.contains("m.2", case=False, na=False) & dfOrdenado['Produto'].str.contains("sata", case=False, na=False) & dfOrdenado['Produto'].str.contains(sizeFilter, case=False, na=False)]
                        #deixando dispositivos externos de fora
                        dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains("externo|120 gb", case=False, na=False)]
                    case "m.2 nvme":
                        dfOrdenado = df.sort_values(by='Preço', ascending=True).reset_index(drop=True)
                        dfFiltrado = dfOrdenado[dfOrdenado['Produto'].str.contains("m.2", case=False, na=False) & dfOrdenado['Produto'].str.contains("nvme", case=False, na=False) & dfOrdenado['Produto'].str.contains(sizeFilter, case=False, na=False)]
                        #deixando dispositivos externos de fora
                        dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains("externo|sata|120 gb", case=False, na=False)]
                    case "sata":
                            dfOrdenado = df.sort_values(by='Preço', ascending=True).reset_index(drop=True)
                            dfFiltrado = dfOrdenado[dfOrdenado['Produto'].str.contains("ssd", case=False, na=False) & dfOrdenado['Produto'].str.contains("sata", case=False, na=False) & dfOrdenado['Produto'].str.contains(sizeFilter, case=False, na=False)]
                            #deixando dispositivos externos de fora
                            dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains("externo|m.2|120 gb", case=False, na=False)]
                    case _:
                        print("armazenamento invalido")                                            
        
        dfFiltrado.to_csv(csvName, index=False)
        #print(dfOrdenado.to_string(index=False))
        return pd.read_csv(csvName, nrows=10)
    except Exception as e:
        print(f"Ocoreru um erro ao filtrar o arquivo CSV: {e}")
        return None


if __name__ == '__main__':
    print(filtrar_amazenamento(aFiltrar, "ssd", "m.2 sata", "256"))