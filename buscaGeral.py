import sys
import csv
import time
import buscaAmazon
import buscaKabbum
import buscaTerabyte
import buscaPichau
import fazerTabela

import filtrarRam
import filtrarArmazenamento
import filtrarGpus

ramTypes = ["ddr3","ddr4", "ddr5"]
ramSizes = [ "4gb", "8gb", "16gb"]
gpuVendors = ["nvidia", "amd"]
nvidiaSeries = ["gtx", "rtx"]
nvidiaGenerations = ["10", "16", "20", "30", "40", "50"]
nvidiaTiers = ["50", "60","70","80","90"]
AMDSeries = ["rx"]
radeon5HSeries = ["520","530","550","560","570","580", "590"]
radeon5TSeries = ["5300","5500", "5600", "5700"]
radeon6TSeries = ["6300","6400", "6500", "6600","6650", "6700", "6750", "6800", "6900", "6950"]
radeon7TSeries = ["7400","7600", "7650", "7700", "7800", "7900"]
radeon9TSeries = ["9050","9060","9070" ]
armazenamentoTypes = ["ssd", "hdd"]
armVariant = ["m.2 sata", "m.2 nvme", "sata"]
armazenamentoSizes = ["120gb","128gb","240gb", "256gb", "500gb", "512gb", "960gb", "1tb", "2tb"]

def pesquisa(busca):
    print(f"Pesquisando por: {busca}...")
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

def pesquisar_ram(rsizes, rtypes):
    with open('csvs/preços.csv', 'w', newline='', encoding='utf-8') as f:
            pass
    print("pesquisando memorias ram...")
    for rtype in rtypes:
        for rsize in rsizes:
            if rtype == "ddr5" and rsize == "4gb":
                continue  # Pula a combinação de DDR5 com 4GB, pois não existe
            busca = f"memoria ram {rtype} {rsize}"
            print (f"Pesquisando por: {busca}...")
            

            #pesquisando na Amazon
            pesquisa(busca)
            time.sleep(5)

             
    for rtype in rtypes:
            for rsize in rsizes:
                if rtype == "ddr5" and rsize == "4gb":
                    continue  # Pula a combinação de DDR5 com 4GB, pois não existe
                busca = f"memoria ram {rtype} {rsize}"
                #filtrando tabela
                try:
                    filtrarRam.filtrar_memoria_desktop('csvs/Produtos Ordenados.csv', rtype, rsize)
                    print(f"Filtragem de {busca} concluidas ")
                    
                except Exception as e:
                    print(f"Ocorreu um erro  ao filtrar as memorias: {e}")
                        # Pausa de 10 segundos entre cada pesquisa para evitar sobrebloqueios

def pesquisa_armazenamento(armType, armVariant, armSize):
    with open('csvs/preços.csv', 'w', newline='', encoding='utf-8') as f:
        pass
    for t in armType:
        for v in armVariant:
            if t == "hdd":
                if v in ("m.2 sata", "m.2 nvme"):
                    continue #nao existe HDs m.2
            for s in armSize:
                if s in ("120gb","240gb","500gb","960gb"): #PULANDO ESSAS PESQUISAS POIS OS RESULTADOS SERIAM REDUNDANTES
                    continue
                busca  = f"{t} {v} {s}"
                pesquisa(busca)
#FILTRANDO ARMAZENAMENTO
    for t in armType:
        for v in armVariant:
            if t == "hdd":
                if v in ("m.2 sata", "m.2 nvme"):
                    continue #nao existe HDs m.2
            for s in armSize:                    
                busca  = f"{t} {v} {s}"
                print(f"filtrando {busca}")
                filtrarArmazenamento.filtrar_amazenamento('csvs/Produtos Ordenados.csv', t,v,s)

def pesquisar_nvidia(vendor, series,generations,tiers):
    with open('csvs/preços.csv', 'w', newline='', encoding='utf-8') as f:
            pass
    for g in generations:
                for t in tiers:
                    if g == "10":
                        if int(t) < 80:
                            busca = f"{vendor} {series[0]} {g}{t}"
                    elif g == '16':
                            if int(t) < 60:
                                busca = f"{vendor} {series[0]} {g}{t}"
                    elif int(g) > 16 and int(g) < 40:
                        if int(t) > 30 and int(t) < 90:
                            busca = f"{vendor} {series[1]} {g}{t}"
                    elif int(g) >= 40:
                        if int(t) > 30:
                            busca = f"{vendor} {series[1]} {g}{t}"
                    
                    pesquisa(busca)
    #FILTRANDO
    for g in generations:
                    for t in tiers:
                        if g == "10":
                            if int(t) < 80:
                                filtrarGpus.filtrar_nvidia('csvs/Produtos Ordenados.csv', series[0], g, t)
                        elif g == '16':
                                if int(t) < 60:
                                    filtrarGpus.filtrar_nvidia('csvs/Produtos Ordenados.csv', series[0], g, t)
                        elif int(g) > 16 and int(g) < 40:
                            if int(t) > 30 and int(t) < 90:
                                filtrarGpus.filtrar_nvidia('csvs/Produtos Ordenados.csv', series[1], g, t)
                        elif int(g) >= 40:
                            if int(t) > 30:
                                filtrarGpus.filtrar_nvidia('csvs/Produtos Ordenados.csv', series[1], g, t)
                        

#######################FUNCAO MAIN (RODA QUANDO O ARQUIVO.PY E CHAMADO INDIVIDUALMENTE)#############################################
if __name__ == '__main__':
    # Limpa o CSV anterior antes de popular os novos
    with open('csvs/preços.csv', 'w', newline='', encoding='utf-8') as f:
        pass
    
    pesquisa_armazenamento(armazenamentoTypes, armVariant, armazenamentoSizes)
    #pesquisar_ram(ramSizes, ramTypes)
    #pesquisar_nvidia(gpuVendors[0], nvidiaSeries, nvidiaGenerations, nvidiaTiers)
