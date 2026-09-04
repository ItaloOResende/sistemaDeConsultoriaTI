import sys
import csv
import time
import buscaAmazon
import buscaKabbum
import buscaTerabyte
import buscaPichau
import fazerTabela

import filtrarRam
import filtrarSSD

ramTypes = ["ddr3","ddr4", "ddr5"]
ramSizes = [ "4gb", "8gb", "16gb"]

def pesquisar_ram(rsizes, rtypes):
    print("pesquisando memorias ram...")
    for rtype in rtypes:
        for rsize in rsizes:
            if rtype == "ddr5" and rsize == "4gb":
                continue  # Pula a combinação de DDR5 com 4GB, pois não existe
            busca = f"memoria ram {rtype} {rsize}"
            print (f"Pesquisando por: {busca}...")
            # Limpa o CSV anterior antes de popular os novos
            with open('csvs/preços.csv', 'w', newline='', encoding='utf-8') as f:
                pass

            #pesquisando na Amazon
            try:
                buscaAmazon.pesquisa_amazon(busca)
            except Exception as e:
                print(f"Erro Amazon: {e}")

            #pesquisando na Kabum
            try:
                buscaKabbum.pesquisa_kabum(busca)
            except Exception as e:
                print(f"Erro Kabum: {e}")

            #pesquisando na Terabyte
            try:
                buscaTerabyte.pesquisa_terabyte(busca)
            except Exception as e:
                print(f"Erro Terabyte: {e}")

            #pesquisando na Pichau
            try:
                buscaPichau.pesquisa_pichau(busca)
            except Exception as e:
                print(f"Erro Pichau: {e}")

            #organizando tabela
            try:
                fazerTabela.prepara_tabela('csvs/preços.csv')
            except Exception as e:
                print(f"Erro ao gerar tabela: {e}")

            #filtrando tabela
            try:
                filtrarRam.filtrar_memoria_desktop('csvs/Produtos Ordenados.csv', rtype, rsize)
                print(f"Pesquisa e filtragem de {busca} concluidas ")
                time.sleep(10)
            except Exception as e:
                print(f"Ocorreu um erro  ao filtrar as memorias: {e}")
                  # Pausa de 10 segundos entre cada pesquisa para evitar sobrebloqueios 



# Se passar o termo por parâmetro do terminal/PHP, usa ele; senão pede o input
# if len(sys.argv) > 1:
#     busca = " ".join(sys.argv[1:])
# else:
#     busca = input("informe o produto que deseja buscar: ")

# Limpa o CSV anterior antes de popular os novos
# with open('preços.csv', 'w', newline='', encoding='utf-8') as f:
#     pass

# try:
#     buscaAmazon.pesquisa_amazon(busca)
# except Exception as e:
#     print(f"Erro Amazon: {e}")

# try:
#     buscaKabbum.pesquisa_kabum(busca)
# except Exception as e:
#     print(f"Erro Kabum: {e}")

# try:
#     buscaTerabyte.pesquisa_terabyte(busca)
# except Exception as e:
#     print(f"Erro Terabyte: {e}")

# try:
#     buscaKabbum.pesquisa_kabum(busca)
# except Exception as e:
#     print(f"Erro Kabum: {e}")
# try:
#    fazerTabela.prepara_tabela('preços.csv')
# except Exception as e:
#     print(f"Erro ao gerar tabela: {e}")

# try:
#     filtrarRam.filtrar_memoria_desktop('Produtos Ordenados.csv', "drr4", "16gb")
#     pass
# except Exception as e:
#     print(f"Ocorreu um erro  ao filtrar tabela: {e}")

if __name__ == '__main__':

    pesquisar_ram(ramSizes, ramTypes)
    