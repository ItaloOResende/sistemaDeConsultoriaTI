from playwright.sync_api import sync_playwright
import csv

  # Limpa o conteúdo do arquivo antes de escrever novos dados


def pesquisa_kabum(busca):
  site = "https://www.kabum.com.br"
  url = f"{site}/busca/{busca.lower().replace(' ', '-')}"
  with sync_playwright() as pw:
      # Lança o navegador (headless=False permite que você veja a ação acontecendo)
      navegador =  pw.chromium.launch(headless=True)
      pagina =  navegador.new_page()
      print(f"Acessando a URL: {url}...")
      pagina.goto(url)

  #   aceite = pagina.get_by_role("button", name="Aceitar")
    # if aceite:
  #       aceite.click()
      #encontrra todos os produtos por meio dos links contendo certo texto
      lista = pagina.locator("a[href*='/produto/']").all()
      print(f' achados {len(lista)} produtos na Kabum...')

      f=open('preços.csv', 'a', newline='')
      writer = csv.writer(f)


      for produto in lista :
        #montando o link completo para cada produto
        link = "https://www.kabum.com.br/" + produto.first.get_attribute("href")
        #encontra o nome do produto percorendo as spans e filtrando as indesejadas
        nomes = produto.filter(has_not_text='Produto').filter(has_not_text='Selo').filter(has_not_text='Avaliação').filter(has_text='R$').locator('span').all()
        #escolhendo apenas as spans desejaveis
        for name in range(0,len(nomes),6):
            titulo = nomes[name+1].text_content()
            preco = nomes[name+3].text_content() + nomes[name+4].text_content()
            produtofinal = (titulo.replace(",", ""), preco, link)
            writer.writerow(produtofinal)
      f.close()
      
      
      
      navegador.close()


  #TODO adicionar tratamento de exceções  para caso o site nao ser acessado. escrever umam funcao main
