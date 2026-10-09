# Filtra gabinetes por tamanho (mini tower / mid tower / full tower)
import re
import pandas as pd

pd.set_option('display.max_columns', None)
pd.set_option('display.max_rows', 10)
pd.set_option('display.width', None)

aFiltrar = "csvs/Produtos Ordenados.csv"


def identificar_tamanho(produto):
    """Extrai o tamanho da placa-mãe suportado a partir do título do gabinete.

    Reconhece E-ATX, Mini-ITX, Mini-ATX, Micro-ATX (mATX) e ATX.
    Retorna string vazia quando o título não informa o tamanho.
    """
    texto = str(produto).lower()
    # \b = limite de palavra; [\s-]* aceita "E-ATX", "E ATX" e "EATX"
    if re.search(r"\be[\s-]*atx\b", texto):
        return "E-ATX"
    # \bmini[\s-]*itx\b pega "Mini-ITX"/"Mini ITX"; o \bitx\b alternativo pega "ITX" isolado
    if re.search(r"\bmini[\s-]*itx\b|\bitx\b", texto):
        return "Mini-ITX"
    # "Mini ATX" / "Mini-ATX" (formato raro, por isso vem antes do ATX comum)
    if re.search(r"\bmini[\s-]*atx\b", texto):
        return "Mini-ATX"
    # (?:micro|m|u|µ) cobre "Micro-ATX", "mATX", "uATX" e "µATX"; [\s-]* aceita separador
    if re.search(r"\b(?:micro|m|u|µ)[\s-]*atx\b", texto):
        return "Micro-ATX"
    # ATX "puro" é checado por último, senão casaria dentro de "Micro-ATX"/"E-ATX"
    if re.search(r"\batx\b", texto):
        return "ATX"
    return ""


def filtrar_gabinetes(file_path, tipo):
    print(f'filtrando gabinetes {tipo}...')
    csvName = f"csvs/gabinete_{tipo.replace(' ', '_')}.csv"

    try:
        df = pd.read_csv(file_path, encoding='latin1', skiprows=1, header=None, names=['Produto', 'Preço', 'Link'])

        df["Preço"] = pd.to_numeric(df["Preço"], errors='coerce')
        dfOrdenado = df.dropna(subset=["Preço"]).sort_values(by='Preço', ascending=True).reset_index(drop=True)
        # Filtra por tamanho (aceita "mid tower", "mid-tower" e "Mid Tower"):
        # o espaço do tipo vira [\s-]+ (um ou mais espaços/hífens)
        tipoFilter = rf"{tipo.replace(' ', r'[\s-]+')}"
        dfFiltrado = dfOrdenado[dfOrdenado['Produto'].str.contains(tipoFilter, case=False, na=False, regex=True)]
        # Só gabinetes no título ("gabinete" ou "case")
        dfFiltrado = dfFiltrado[dfFiltrado['Produto'].str.contains("gabinete|case", case=False, na=False)]
        # Exclui peças que não são gabinetes (alternância simples de palavras)
        dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains("notebook|pc gamer|fonte|processador|cooler", case=False, na=False)]

        # Adiciona o tamanho logo após o Produto: Produto, Tamanho, Preço, Link
        dfFiltrado.insert(1, "Tamanho", dfFiltrado['Produto'].map(identificar_tamanho))

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
