# Filtra coolers por tipo (air/water) e estilo (simple/rgb/argb)
import pandas as pd

pd.set_option('display.max_columns', None)
pd.set_option('display.max_rows', 10)
pd.set_option('display.width', None)

aFiltrar = "csvs/Produtos Ordenados.csv"


def filtrar_coolers(file_path, tipo, estilo):
    print(f'filtrando coolers {tipo} {estilo}...')
    csvName = f"csvs/{tipo.replace(' ', '_')}_{estilo}.csv"

    try:
        df = pd.read_csv(file_path, encoding='latin1', skiprows=1, header=None, names=['Produto', 'Preço', 'Link'])

        df["Preço"] = pd.to_numeric(df["Preço"], errors='coerce')
        dfOrdenado = df.dropna(subset=["Preço"]).sort_values(by='Preço', ascending=True).reset_index(drop=True)
        # Filtra por tipo (air cooler / water cooler)
        dfFiltrado = dfOrdenado[dfOrdenado['Produto'].str.contains(tipo, case=False, na=False)]
        # Filtra por estilo: simple não tem RGB/ARGB no nome
        if estilo == "rgb":
            # "argb" também contém "rgb", então separa os dois
            dfFiltrado = dfFiltrado[dfFiltrado['Produto'].str.contains("rgb", case=False, na=False) & ~dfFiltrado['Produto'].str.contains("argb", case=False, na=False)]
        elif estilo == "argb":
            dfFiltrado = dfFiltrado[dfFiltrado['Produto'].str.contains("argb", case=False, na=False)]
        else:  # simple: nenhum RGB ou ARGB no nome
            dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains("rgb|argb", case=False, na=False)]
        # Exclui kits, processadores e outros produtos que não são coolers
        dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains("notebook|processador|ssd|pc gamer", case=False, na=False)]
        dfFiltrado.to_csv(csvName, index=False)
        if dfFiltrado.empty:
            print(f"nenhum cooler {tipo} {estilo} encontrado")
            return None
        return pd.read_csv(csvName, nrows=10)
    except Exception as e:
        print(f"Ocorreu um erro ao filtrar o arquivo CSV: {e}")
        return None


if __name__ == '__main__':
    print(filtrar_coolers(aFiltrar, "air cooler", "simple"))
