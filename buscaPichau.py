# Busca de produtos na Pichau
from cmath import e

from playwright.sync_api import sync_playwright
from playwright_stealth import Stealth
import csv
import time


def pesquisa_pichau(busca):
    site = "https://www.pichau.com.br"
    # Monta a URL de busca
    url = f"{site}/search?q={busca.replace(' ', '%20')}"
    arquivo = "csvs/preços.csv"
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
        print(f"Acessando a Pichau...")

        try:
            itens = getItens(pagina, url, busca, 0)
            pass
        except Exception as e:
            print(f"Ocorreu um erro ao buscar os itens da Pichau: {e}")

        f = open(arquivo, 'a', encoding='utf-8', newline='')
        writer = csv.writer(f)

        try:
            for item in itens:
                titulo = item.get_by_role("heading", level=2).text_content()
                titulo = titulo.replace(",", "")
                preco = item.locator('div[class$="-price_vista"]').text_content()
                link = item.first.get_attribute("href")
                linkfull = site + link
                produto = (titulo, preco, linkfull)
                writer.writerow(produto)
                pass
            resultado = "concluido"
        except Exception as e:
            print(f"Ocorreu um erro ao registrar os itens da Pichau: {e}")
            resultado = "erro"
        f.close()
        navegador.close()
    return resultado

# Obtém itens da Pichau com tentativas de recarga
def getItens(pagina, url, busca, tentativas):
    tentativas += 1
    if tentativas == 5:
        return 0
    else:
        seletor_produto = '[data-cy="list-product"]'
        pagina.goto(url)
        produtos = pagina.locator(seletor_produto).filter(has_text='R$').all()
        if len(produtos) > 0:
            print(f"encontrados {len(produtos)} {busca} na Pichau...")
            return produtos
        else:
            print(f"encontrados {len(produtos)} {busca} na Pichau...")
            print("tentando novamente")
            time.sleep(5)

            return getItens(pagina, url, busca, tentativas)
    
    

