import re
import pandas as pd

pd.set_option('display.max_columns', None)
pd.set_option('display.max_rows', 10)
pd.set_option('display.width', None)

aFiltrar = "csvs/Produtos Ordenados.csv"

# móvel, notebook, cpu de entrada e acessórios que aparecem junto das buscas
excluirCpu = "notebook|laptop|ultrabook|2 em 1|celeron|pentium|atom|amd|ryzen|threadripper|ssd|placa de video|memoria|suporte|cooler|ventoinha|pastilha|kit|cabo"


def filtrar_intel_cpu(file_path, linha, geracao, videoIntegrado=None):
    print(f'filtrando processadores intel core {linha} {geracao}ª geração...')
    csvName = f"csvs/intel_{linha}_{geracao}.csv"

    try:
        df = pd.read_csv(file_path, encoding='latin1', skiprows=1, header=None, names=['Produto', 'Preço', 'Link'])
        df["Preço"] = pd.to_numeric(df["Preço"], errors='coerce')

        # as lojas gravam os títulos com acentuação quebrada, então normaliza os espaços
        produtos = df["Produto"].astype(str).str.replace(r"\s+", " ", regex=True)
        dfOrdenado = df.assign(Produto=produtos).sort_values(by='Preço', ascending=True).reset_index(drop=True)

        # i5-12400, i5 12400 ou ainda a nomenclatura do anúncio (ex.: "12ª Geração")
        modeloFilter = rf"{re.escape(linha)}\s*-?\s*{re.escape(geracao)}\d{{3}}"
        geraFilter = rf"{re.escape(geracao)}[^0-9a-z]{{0,4}}gera"

        dfFiltrado = dfOrdenado[
            dfOrdenado['Produto'].str.contains(linha, case=False, na=False)
            & (
                dfOrdenado['Produto'].str.contains(modeloFilter, case=False, na=False, regex=True)
                | dfOrdenado['Produto'].str.contains(geraFilter, case=False, na=False, regex=True)
            )
        ]

        # processadores mobile (i5-1235U, i7-1260P, i9-12900HK, ...)
        mobileFilter = rf"{re.escape(linha)}\s*-?\s*{re.escape(geracao)}\d{{2,3}}[UPGHY]"
        dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains(mobileFilter, case=False, na=False, regex=True)]

        # sufixo K/KS indica processador desbloqueado, é o que se quer com vídeo integrado
        comVideoFilter = rf"{re.escape(linha)}\s*-?\s*{re.escape(geracao)}\d{{3}}KS?\b"

        match videoIntegrado.lower() if videoIntegrado else "todos":
            case "todos":
                pass
            case "sim":
                dfFiltrado = dfFiltrado[dfFiltrado['Produto'].str.contains(comVideoFilter, case=False, na=False, regex=True)]
            case "nao":
                dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains(comVideoFilter, case=False, na=False, regex=True)]
            case _:
                print(f"filtro de vídeo integrado invalido: {videoIntegrado}")

        dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains(excluirCpu, case=False, na=False)]

        dfFiltrado.to_csv(csvName, index=False)
        #print(dfOrdenado.to_string(index=False))
        if dfFiltrado.empty:
            print(f"nenhum processador {linha} da {geracao}ª geração encontrado")
            return None
        return pd.read_csv(csvName, nrows=10)
    except Exception as e:
        print(f"Ocoreru um erro ao filtrar o arquivo CSV: {e}")
        return None


if __name__ == '__main__':
    print(filtrar_intel_cpu(aFiltrar, "i5", "12"))
