# Filtra placas-mãe por soquete (LGA ****, AM*)
import re
import pandas as pd

pd.set_option('display.max_columns', None)
pd.set_option('display.max_rows', 10)
pd.set_option('display.width', None)

aFiltrar = "csvs/Produtos Ordenados.csv"


def identificar_tamanho(produto):
    """Extrai o tamanho (form factor) da placa-mãe a partir do título.

    Reconhece E-ATX, Mini-ITX, Mini-ATX, Micro-ATX (mATX) e ATX.
    Retorna string vazia quando o título não informa o tamanho.
    """
    texto = str(produto).lower()
    if re.search(r"\be[\s-]*atx\b", texto):
        return "E-ATX"
    if re.search(r"\bmini[\s-]*itx\b|\bitx\b", texto):
        return "Mini-ITX"
    if re.search(r"\bmini[\s-]*atx\b", texto):
        return "Mini-ATX"
    if re.search(r"\b(?:micro|m|u|µ)[\s-]*atx\b", texto):
        return "Micro-ATX"
    if re.search(r"\batx\b", texto):
        return "ATX"
    return ""


def filtrar_placas_mae(file_path, soquete):
    print(f'filtrando placas-mae {soquete}...')
    csvName = f"csvs/placa_mae_{soquete.replace(' ', '_').lower()}.csv"

    try:
        df = pd.read_csv(file_path, encoding='latin1', skiprows=1, header=None, names=['Produto', 'Preço', 'Link'])

        df["Preço"] = pd.to_numeric(df["Preço"], errors='coerce')
        dfOrdenado = df.dropna(subset=["Preço"]).sort_values(by='Preço', ascending=True).reset_index(drop=True)
        # Filtra por soquete (aceita "LGA 1700", "LGA1700" e "AM4")
        padrao = re.escape(soquete).replace(r'\ ', r'[\s-]*')
        soqueteFilter = rf"\b{padrao}\b"
        dfFiltrado = dfOrdenado[dfOrdenado['Produto'].str.contains(soqueteFilter, case=False, na=False, regex=True)]
        # Só placas-mãe no título
        dfFiltrado = dfFiltrado[dfFiltrado['Produto'].str.contains(r"placa[\s-]*m[ãa]e|motherboard", case=False, na=False, regex=True)]
        # Exclui peças que não são placas-mãe
        dfFiltrado = dfFiltrado[~dfFiltrado['Produto'].str.contains("notebook|processador|memoria|mem[oó]ria|ssd|fonte|gabinete|cooler|water cooler|pc gamer", case=False, na=False)]

        # Adiciona o tamanho logo após o Produto: Produto, Tamanho, Preço, Link
        dfFiltrado.insert(1, "Tamanho", dfFiltrado['Produto'].map(identificar_tamanho))

        dfFiltrado.to_csv(csvName, index=False)
        if dfFiltrado.empty:
            print(f"nenhuma placa-mae {soquete} encontrada")
            return None
        return pd.read_csv(csvName, nrows=10)
    except Exception as e:
        print(f"Ocorreu um erro ao filtrar o arquivo CSV: {e}")
        return None


if __name__ == '__main__':
    print(filtrar_placas_mae(aFiltrar, "AM4"))
