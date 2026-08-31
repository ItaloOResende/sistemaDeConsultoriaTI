from bs4 import BeautifulSoup
import requests
import csv
import time
def pesquisa_amazon(busca):
    #abrindo o arquivo CSV para escrita
    f=open('preços.csv', 'a', encoding='utf-8', newline='')
    writer = csv.writer(f)

    # usando url de pesquisa modificada para buscar o termo desejado
    site = "https://www.amazon.com.br"
    url = f"{site}/s?k={busca.replace(" ", "+")}"
    headers = {'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',
           'referer': 'https://google.com',}

    
    #teste de conectividade
    #print(pagina.status_code)
    

    #achando todos os itens de produtos na página
    
    itens = getItens(url, headers)
    #encontrando os elementos de título e preço para cada item e escrevendo no arquivo CSV
    for item in itens:
        # achando o nome do produto por meio de elementos heading
        titleElement = item.find('h2')
        #achando a moeda do preço por meio de elementos span
        priceCurrency = item.find('span', class_="a-price-symbol")
        # achando o preço do produto por meio de elementos span
        priceFull= item.find('span', class_="a-price-whole")
        # achando a fração do preço do produto por meio de elementos span
        priceFraction = item.find('span', class_="a-price-fraction")
        #guardando os dados no arquivo CSV apenas se todos os elementos forem encontrados
        if titleElement and priceCurrency and priceFull and priceFraction:
            titulo = titleElement.find('span').text
            #filtragem basica
            if "testador" in titulo.lower(): continue
            elif "conversor" in titulo.lower():continue
            #juntando todos os elementos que compõe o preço
            preco = f"{priceCurrency.text}{priceFull.text}{priceFraction.text}"
            # achando o link do produto em uma href
            link = item.find('a',href=True)
            #criando o link completo
            fulllink = site + link.get('href')
            #tirando virgulas para evitar confusão
            produto = (titulo.replace(",", ""), preco, fulllink)
            writer.writerow(produto)
    f.close()

def getItens(url, headers):
    pagina = requests.get(url, headers=headers)
    paginaDados = BeautifulSoup(pagina.text, 'html.parser')
    print("Buscando na Amazon...")
    #encontrando todos os itens listados por meio de divs
    itens = paginaDados.find_all('div', role="listitem") 
    if len(itens) > 0:
        print(f"Encontrados {len(itens)} itens na Amazon...")
        return itens
    else: 
        #tentando de novo se a busca for interrompida
        print("tentando novamente")
        time.sleep(1)
        getItens(url,headers)
