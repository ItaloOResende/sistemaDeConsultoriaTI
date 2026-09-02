from playwright.sync_api import sync_playwright
import csv



# busca = "Memoria Ram DDR4"


def pesquisa_terabyte(busca):
    site = "https://www.terabyteshop.com.br"
    url = f"{site}/busca?str={busca.replace(' ', '%20')}"
    with sync_playwright() as pw:
        #Adiciona flags do Chromium para camuflar automação e habilitar o novo modo headless
        navegador = pw.chromium.launch(
        headless=True,
        args=[
            "--headless=new",  # Usa a arquitetura mais nova do headless (idêntica à com interface)
            "--disable-blink-features=AutomationControlled",  # Esconde a flag de automação
            "--no-sandbox",
        ],
        )

        #Configura o contexto simulando uma tela e User-Agent reais
        context = navegador.new_context(
        user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36",
        viewport={"width": 1920, "height": 1080},
        device_scale_factor=1,
            )
        pagina = context.new_page()


        #Remove a propriedade 'navigator.webdriver' via script antes das páginas carregarem
        pagina.add_init_script(
        "Object.defineProperty(navigator, 'webdriver', {get: () => undefined})"
        )
        print(f"Acessando a Terabyte...")
        
        try:
              itens = getItens(pagina,url,0)
              pass
        except Exception as e:
              print(f"Ocorreu um erro ao buscar os itens na Terabyte: {e}")

        

        f=open('preços.csv', 'a',encoding='utf-8', newline='')
        writer = csv.writer(f)

        try:
            for item in itens:
                        titulo = item.get_by_role("heading",level=2).text_content()
                        titulo = titulo.replace(",", "")
                        preco = item.locator(".product-item__new-price").filter(has_text='R$').text_content()
                        preco = preco[:-14]
                        link = item.get_by_role('link').first.get_attribute("href")
                        linkfull = site + link
                        produto = (titulo, preco, linkfull)
                        writer.writerow(produto)
                        pass
        except Exception as e:
                print(f"Ocorreu um erro ao registrar os itens da Terabyte: {e}")
            
        f.close()
        navegador.close()

def getItens(pagina,url,tentativas):
    tentativas += 1
    if tentativas == 5:
        return 0
    else:
        pagina.goto(url)
        produtos = pagina.locator(".product-item__box").filter(has_text='R$').all()
        if len(produtos) > 0:
            print(f"encontrados {len(produtos)} itens")
            return produtos
        else: 
                print("tentando novamente")

                return getItens(pagina,url,tentativas)
    

