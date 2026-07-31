from playwright.sync_api import sync_playwright
from playwright_stealth import Stealth
import csv
import time


# busca = "Memoria Ram 8Gb DDR4"


def pesquisa_pichau(busca):
    site = "https://www.pichau.com.br"
    url = f"{site}/search?q={busca.replace(' ', '%20')}"
    with Stealth().use_sync(sync_playwright()) as pw:
        
        # O stealth injeta flags e disfarces no Chromium automaticamente
        navegador = pw.chromium.launch(
            headless=True,
            args=[
                "--no-sandbox",
                "--disable-setuid-sandbox",
            ]
        )

        context = navegador.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",
            viewport={"width": 1920, "height": 1080},
            locale="pt-BR"
        )
        pagina = context.new_page()
        print(f"Acessando a Pichau...")
        
        itens = getItens(pagina,url)

        

        f=open('preços.csv', 'a', newline='')
        writer = csv.writer(f)

        for item in itens:
            titulo = item.get_by_role("heading",level=2).text_content()
            titulo = titulo.replace(",", "")
            preco = item.locator('div[class$="-price_vista"]').text_content()        
            link = item.first.get_attribute("href")
            linkfull = site + link
            print(f"{titulo}\n {preco} /n {linkfull}")
            produto = (titulo, preco, linkfull)
            writer.writerow(produto)
            
        # f.close()
        navegador.close()

def getItens(pagina,url):
    seletor_produto = '[data-cy="list-product"]'
    pagina.goto(url)
    # pagina.locator(seletor_produto).first.wait_for(state="visible", timeout=10000)
    produtos = pagina.locator(seletor_produto).filter(has_text='R$').all()
    if len(produtos) > 0:
        print(f"encontrados {len(produtos)} itens")
        # for itens in produtos:
        #     print(produtos)
        return produtos
    else: 
        print(f"encontrados {len(produtos)} itens")
        print("tentando novamente")
        time.sleep(5)

        return getItens(pagina,url)
    

