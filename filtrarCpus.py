# Filtra processadores Intel e AMD a partir dos resultados
import re
import pandas as pd

pd.set_option('display.max_columns', None)
pd.set_option('display.max_rows', 10)
pd.set_option('display.width', None)

aFiltrar = "csvs/Produtos Ordenados.csv"

# Termos a excluir: CPUs móveis, notebooks e acessórios indesejados
excluirCpu = "notebook|laptop|ultrabook|2 em 1|celeron|pentium|atom|amd|ryzen|threadripper|ssd|placa de video|memoria|suporte|cooler|ventoinha|pastilha|kit|cabo|xeon"

# Padrões de exclusão específicos para AMD
excluirCpuAmd = (
    r"\b(?:notebook|laptop|ultrabook|2 em 1|mobile|celeron|pentium|atom|epyc|threadripper|fenrir|ssd|mem[oó]ria|placa(?:-m[aã]e)?|suporte|ventoinha|pastilha|kit|cabo|water\s*cooler|server)\b"
    r"|cooler\b(?![^(]*\))"
)


def corrigir_texto(texto):
    """Corrige títulos com encoding duplo ('MemÃ³ria') das lojas."""
    try:
        return texto.encode('latin1').decode('utf-8')
    except (UnicodeEncodeError, UnicodeDecodeError):
        return texto


def ler_precos(file_path):
    """Lê o CSV e converte preço para formato numérico válido.

    Aceita tanto 'Produtos Ordenados.csv' (já tratado) quanto 'preços.csv' cru.
    """
    df = pd.read_csv(file_path, encoding='latin1', skiprows=1, header=None, names=['Produto', 'Preço', 'Link'])
    precos = df["Preço"].astype(str).str.replace(r"[^\d,.]", "", regex=True)  # Remove símbolos

    # Trata formato brasileiro (vírgula decimal)
    precos = precos.where(~precos.str.contains(",", regex=False),
                          precos.str.replace(".", "", regex=False))
    df["Preço"] = pd.to_numeric(precos.str.replace(",", ".", regex=False), errors="coerce")
    return df


def filtrar_intel_cpu(file_path, linha, geracao, videoIntegrado=None):
    """Filtra processadores Intel Core desktop."""
    print(f'filtrando processadores intel core {linha} {geracao}ª geração...')
    csvName = f"csvs/intel_{linha}_{geracao}.csv"

    try:
        df = pd.read_csv(file_path, encoding='latin1', skiprows=1, header=None, names=['Produto', 'Preço', 'Link'])
        df["Preço"] = pd.to_numeric(df["Preço"], errors='coerce')

        # Normaliza espaços nos títulos
        produtos = df["Produto"].astype(str).str.replace(r"\s+", " ", regex=True)
        dfOrdenado = df.assign(Produto=produtos).sort_values(by='Preço', ascending=True).reset_index(drop=True)

        # Filtros para modelos desktop
        modeloFilter = rf"{re.escape(linha)}\s*-?\s*{re.escape(geracao)}\d{{3}}"
        geraFilter = rf"{re.escape(geracao)}[^0-9a-z]{{0,4}}gera"

        dfFiltrado = dfOrdenado[
            dfOrdenado['Produto'].str.contains(linha, case=False, na=False)
            & (
                dfOrdenado['Produto'].str.contains(modeloFilter, case=False, na=False, regex=True)
                | dfOrdenado['Produto'].str.contains(geraFilter, case=False, na=False, regex=True)
            )
        ]

        # Exclui versões móveis
        mobileFilter = rf"{re.escape(linha)}\s*-?\s*{re.escape(geracao)}\d{{2,3}}[HUV]"
        dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains(mobileFilter, case=False, na=False, regex=True)]

        # Filtra por presença de vídeo integrado (sufixo K/KS)
        comVideoFilter = rf"{re.escape(linha)}\s*-?\s*{re.escape(geracao)}\d{{3}}KS?\b"

        match videoIntegrado.lower() if videoIntegrado else "todos":
            case "todos":
                pass
            case "sim":
                dfFiltrado = dfFiltrado[dfFiltrado['Produto'].str.contains(comVideoFilter, case=False, na=False, regex=True)]
            case "nao":
                dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains(comVideoFilter, case=False, na=False, regex=True)]
            case _:
                print(f"filtro de vídeo integrado inválido: {videoIntegrado}")

        dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains(excluirCpu, case=False, na=False)]

        dfFiltrado.to_csv(csvName, index=False)
        if dfFiltrado.empty:
            print(f"nenhum processador {linha} da {geracao}ª geração encontrado")
            return None
        return pd.read_csv(csvName, nrows=10)
    except Exception as e:
        print(f"Ocorreu um erro ao filtrar o arquivo CSV: {e}")
        return None


def filtrar_amd_cpu(file_path, linha, geracao, videoIntegrado=None):
    """Filtra processadores AMD desktop (ex.: 'ryzen 5' + '5000').

    videoIntegrado: 'sim'=só APUs, 'nao'=sem iGPU, 'todos'=padrão.
    """
    digito = geracao.strip()[:1]
    csvName = f"csvs/amd_{linha.replace(' ', '_')}_{geracao}.csv"

    try:
        df = ler_precos(file_path)
        produtos = df["Produto"].astype(str).str.replace(r"\s+", " ", regex=True).map(corrigir_texto)
        dfOrdenado = df.assign(Produto=produtos).dropna(subset=["Preço"]).sort_values(by='Preço', ascending=True).reset_index(drop=True)

        if dfOrdenado.empty:
            return None

        # Filtro para modelos desktop
        modeloFilter = rf"{re.escape(linha)}\s*-?\s*{re.escape(digito)}\d{{3}}"
        dfFiltrado = dfOrdenado[dfOrdenado['Produto'].str.contains(modeloFilter, case=False, na=False, regex=True)]

        # Exclui versões móveis
        dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains(
            rf"{re.escape(linha)}\s*-?\s*{re.escape(digito)}\d{{2,3}}[HUP]", case=False, na=False, regex=True)]

        # Filtra por presença de vídeo integrado
        comVideoFilter = rf"{re.escape(linha)}\s*-?\s*{re.escape(digito)}\d{{3}}GT\b"
        v = (videoIntegrado or "todos").lower()
        if v == "sim":
            dfFiltrado = dfFiltrado[dfFiltrado['Produto'].str.contains(comVideoFilter, case=False, na=False, regex=True)]
        elif v == "nao":
            dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains(comVideoFilter, case=False, na=False, regex=True)]

        dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains(excluirCpuAmd, case=False, na=False)]

        dfFiltrado.to_csv(csvName, index=False)
        if dfFiltrado.empty:
            return None
        return pd.read_csv(csvName, nrows=10)
    except Exception as e:
        print(f"Erro ao filtrar AMD: {e}")
        return None


if __name__ == '__main__':
    print(filtrar_intel_cpu(aFiltrar, "i5", "12"))
    print(filtrar_amd_cpu(aFiltrar, "ryzen 5", "5000"))
