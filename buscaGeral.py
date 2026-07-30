import csv

import buscaAmazon
import buscaKabbum
import buscaPichau
import buscaTerabyte
import buscaPichau
import fazerTabela

busca = "memoria ram ddr4"
f=open('preços.csv', 'a', newline='')
writer = csv.writer(f)
f.truncate(0)
f.close()
buscaAmazon.pesquisa_amazon(busca)
buscaKabbum.pesquisa_kabum(busca)
buscaTerabyte.pesquisa_terabyte(busca)
buscaPichau.pesquisa_pichau(busca)
print(fazerTabela.organiza_tabela('preços.csv'))