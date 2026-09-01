import csv

import buscaAmazon
import buscaKabbum
import buscaPichau
import buscaTerabyte
import buscaPichau
import fazerTabela

busca = input("informe o produto que deseja buscar:")
f=open('preços.csv', 'a', newline='')
writer = csv.writer(f)
f.truncate(0)
f.close()
try:
    buscaAmazon.pesquisa_amazon(busca)
    pass
except Exception as e:
    print(f"Ocorreu um erro cc a busca da Aamazon: {e}")

try:
    buscaKabbum.pesquisa_kabum(busca)
    pass
except Exception as e:
    print(f"Ocorreu um erro com a busca da Kabum: {e}")

try:
    buscaTerabyte.pesquisa_terabyte(busca)
    pass
except Exception as e:
    print(f"Ocorreu um erro com a busca da Terabyte: {e}")
try:
    buscaPichau.pesquisa_pichau(busca)
    pass
except Exception as e:
    print(f"Ocorreu um erro com a busca da Pichau: {e}")

try:
    print(fazerTabela.organiza_tabela('preços.csv'))
except Exception as e:
    print(f"Ocorreu um erro ao organizar a tabela: {e}")