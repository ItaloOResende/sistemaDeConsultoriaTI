import sys
import csv
import buscaAmazon
import buscaKabbum
import buscaTerabyte
import fazerTabela

# Se passar o termo por parâmetro do terminal/PHP, usa ele; senão pede o input
if len(sys.argv) > 1:
    busca = " ".join(sys.argv[1:])
else:
    busca = input("informe o produto que deseja buscar: ")

# Limpa o CSV anterior antes de popular os novos
with open('preços.csv', 'w', newline='', encoding='utf-8') as f:
    pass

try:
    buscaAmazon.pesquisa_amazon(busca)
except Exception as e:
    print(f"Erro Amazon: {e}")

try:
    buscaKabbum.pesquisa_kabum(busca)
except Exception as e:
    print(f"Erro Kabum: {e}")

try:
    buscaTerabyte.pesquisa_terabyte(busca)
except Exception as e:
    print(f"Erro Terabyte: {e}")

try:
    fazerTabela.fazer_tabela()
except Exception as e:
    print(f"Erro ao gerar tabela: {e}")