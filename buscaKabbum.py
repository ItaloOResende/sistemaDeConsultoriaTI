from playwright.sync_api import sync_playwright
from playwright_stealth import Stealth
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

      f=open('preços.csv', 'a',encoding='utf-8', newline='')
      writer = csv.writer(f)


      try:
         for produto in lista :
            #montando o link completo para cada produto
            link = f"{site}{produto.first.get_attribute("href")}"
            #encontra o nome do produto percorendo as spans e filtrando as indesejadas
            titulo = produto.locator('span[class*="break-normal h-40"]').text_content()
            preco = produto.locator('div[class$="flex gap-4 items-center"]').locator('span').filter(has_not_text="Desconto").filter(has_not_text="R$").text_content()
            produtofinal = (titulo.replace(",", ""), preco, link)
            writer.writerow(produtofinal)
            pass
      except Exception as e:
        print(f"Ocorreu um erro ao registrar os itens da Kabum: {e}")
      f.close()
      
      
      
      navegador.close()

if __name__ == '__main__':
   pesquisa_kabum('memoria ddr4')
