# Busca de produtos na Kabum
from playwright.sync_api import sync_playwright
from playwright_stealth import Stealth
import csv


def pesquisa_kabum(busca):
    site = "https://www.kabum.com.br"
    # Monta a URL de busca
    url = f"{site}/busca/{busca.lower().replace(' ', '-')}"
    arquivo = "csvs/preços.csv"
    with sync_playwright() as pw:
        # Inicia navegador em modo headless
        navegador = pw.chromium.launch(headless=True)
        pagina = navegador.new_page()
        print(f"Acessando a Kabum...")
        pagina.goto(url)

        # Encontra todos os produtos por meio dos links que contêm '/produto/'
        lista = pagina.locator("a[href*='/produto/']").all()
        print(f' achados {len(lista)} {busca} na Kabum...')

        f = open(arquivo, 'a', encoding='utf-8', newline='')
        writer = csv.writer(f)

        try:
            for produto in lista:
                # Monta o link completo para cada produto
                link = f"{site}{produto.first.get_attribute('href')}"
                # Extrai título e preço do produto
                titulo = produto.locator('span[class*="break-normal h-40"]').text_content()
                preco = produto.locator('div[class$="flex gap-4 items-center"]').locator('span').filter(has_not_text="Desconto").filter(has_not_text="R$").text_content()
                produtofinal = (titulo.replace(",", ""), preco, link)
                writer.writerow(produtofinal)
                pass
            resultado = "concluido"
        except Exception as e:
            print(f"Ocorreu um erro ao registrar os itens da Kabum: {e}")
            resultado = "erro"
        f.close()

        navegador.close()

    return resultado

if __name__ == '__main__':
   pesquisa_kabum('memoria ddr4')
