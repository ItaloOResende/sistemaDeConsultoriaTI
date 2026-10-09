# Busca de produtos na Amazon Brasil
from ast import iter_child_nodes

from playwright.sync_api import sync_playwright
from playwright_stealth import Stealth
import csv
import time

def pesquisa_amazon(busca):
    # Abre o arquivo CSV para adição dos resultados
    arquivo = "csvs/preços.csv"
    f = open(arquivo, 'a', encoding='utf-8', newline='')
    writer = csv.writer(f)
    site = "https://www.amazon.com.br"
    # Monta a URL de busca
    url = f"{site}/s?k={busca.replace(' ', '+')}"
    with Stealth().use_sync(sync_playwright()) as pw:
        # O stealth injeta flags e disfarces no Chromium automaticamente
        navegador = pw.chromium.launch(
            headless=True,
            args=[
                "--no-sandbox",
                "--disable-setuid-sandbox",
            ]
        )

        # Configura contexto para simular navegador real
        context = navegador.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",
            viewport={"width": 1920, "height": 1080},
            locale="pt-BR"
        )
        context.route("**/*", lambda route: route.abort() 
                             if route.request.resource_type in ["image", "stylesheet", "font", "media"] else route.continue_())
        pagina = context.new_page()
        print(f"Acessando a Amazon...")

        itens = getItens(pagina, url,busca)
        escopo = len(itens) - 8
        try:
            # Extrai dados dos itens encontrados
            for item in range(0, escopo):
                titulo = itens[item].locator('div[data-cy="title-recipe"]').locator('span').first.text_content()
                preco = itens[item].locator('span').get_by_text("R$").filter(has_text="R$").filter(has_not_text="juros").first.text_content()
                link = itens[item].get_by_role("link").first.get_attribute("href")
                linkfull = site + link
                produto = (titulo.replace(",", ""), preco, linkfull)
                writer.writerow(produto)
                pass
            resultado = "concluido"
        except Exception as e:
            print(f"Ocorreu um erro ao registrar os itens da Amazon: {e}")
            resultado = "erro"
        f.close()
        navegador.close()
    return resultado
                    



# Obtém lista de itens na página de resultados da Amazon
def getItens(pagina, url, busca):
    pagina.goto(url)
    # Desce até o final para carregar todos os itens
    pagina.evaluate("window.scrollTo(0, document.body.scrollHeight)")
    pagina.wait_for_timeout(2000)  # Aguarda carregamento do conteúdo

    # Filtra itens válidos (exclui patrocinados e itens sem preço)
    itens = pagina.locator('div[role$="listitem"]').filter(has_not_text="rápido").filter(has_not_text="Patrocinado").filter(has_text="R$").all()
    print(f'foram encontrados {len(itens)} {busca} na Amazon...')

    return itens


if __name__ == '__main__':
    pesquisa_amazon('memoria ddr4 16gb')