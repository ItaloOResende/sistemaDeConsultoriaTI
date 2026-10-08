# Arquivo principal para coordenação das buscas de peças por diferentes lojas
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
import filtrarCpus
import filtrarFontes
import filtrarCoolers
import filtrarGabinetes
import filtrarPlacasMae

# Configurações para busca de memórias RAM
ramTypes = ["ddr3","ddr4", "ddr5"]
ramSizes = [ "4gb", "8gb", "16gb"]

# Configurações para busca de placas de vídeo NVIDIA
gpuVendors = ["nvidia", "amd"]
nvidiaSeries = ["gtx", "rtx"]
nvidiaGenerations = ["10", "16", "20", "30", "40", "50"]
nvidiaTiers = ["50", "60","70","80","90"]

# Configurações para busca de placas de vídeo AMD
AMDSeries ="rx"
radeon5HSeries = ["570","580", "590"]
radeon5TSeries = ["5300","5500", "5600", "5700"]
radeon6TSeries = ["6300","6400", "6500", "6600","6650", "6700", "6750", "6800", "6900", "6950"]
radeon7TSeries = ["7400","7600", "7650", "7700", "7800", "7900"]
radeon9TSeries = ["9050","9060","9070" ]

# Configurações para busca de armazenamento (SSD/HDD)
armazenamentoTypes = ["ssd", "hdd"]
armVariant = ["m.2 sata", "m.2 nvme", "sata"]
armazenamentoSizes = ["128gb", "256gb", "512gb", "1tb", "2tb"]

# Configurações para busca de processadores Intel
intelVendor = "intel"
intelLines = ["i3", "i5", "i7", "i9"]  # Para incluir os Core Ultra, basta acrescentar aqui
intelGenerations = ["8", "9","10", "11", "12", "13", "14"]
# Combinações que não existem no mercado de desktop (evita busca sem resultado)
intelIndisponiveis = [("", "")]

# Configurações para busca de processadores AMD Ryzen
# Séries desktop: 3000 (Zen 2), 5000 (Zen 3), 7000 (Zen 4) e 9000 (Zen 5)
amdRyzenLines = ["ryzen 3", "ryzen 5", "ryzen 7", "ryzen 9"]
amdRyzenGenerations = ["3000", "4000","5000", "7000","8000", "9000"]
# Combinações que não existem no mercado de desktop (evita busca sem resultado)
# A linha Ryzen 3 parou no 3200G, não tem peça de 7000 nem 9000
amdRyzenIndisponiveis = [("ryzen 3", "7000"), ("ryzen 3", "9000")]
fontesCapacidade = ["400w", "500w","550W", "600w", "650W", "700w", "750W", "800w","850W", "1000w"]

# Configurações para busca de coolers
coolerTipos = ["air cooler", "water cooler"]
coolerEstilos = ["simples", "rgb", "argb"]

# Configurações para busca de gabinetes
gabinetesTipos = ["mini tower", "mid tower", "full tower"]

# Configurações para busca de placas-mãe (separadas por soquete)
placasMaeSoquetes = ["LGA 1151", "LGA 1200", "LGA 1700", "AM4", "AM5"]

# Realiza busca em todas as lojas e organiza os resultados
def pesquisa(busca):
    print(f"Pesquisando por: {busca}...")
    # Busca na Amazon
    try:
        buscaAmazon.pesquisa_amazon(busca)
    except Exception as e:
        print(f"Erro Amazon: {e}")

    # Busca na Kabum
    try:
        buscaKabbum.pesquisa_kabum(busca)
    except Exception as e:
        print(f"Erro Kabum: {e}")

    # Busca na Terabyte
    try:
        buscaTerabyte.pesquisa_terabyte(busca)
    except Exception as e:
        print(f"Erro Terabyte: {e}")

    # Busca na Pichau
    try:
        buscaPichau.pesquisa_pichau(busca)
    except Exception as e:
        print(f"Erro Pichau: {e}")

    # Organiza os resultados em tabela ordenada por preço
    try:
        fazerTabela.prepara_tabela('csvs/preços.csv')
    except Exception as e:
        print(f"Erro ao gerar tabela: {e}")
    time.sleep(10)

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
            try:
                filtrarRam.filtrar_memoria_desktop('csvs/Produtos Ordenados.csv', rtype, rsize)
                print(f"Filtragem de {busca} concluidas ")
                
            except Exception as e:
                print(f"Ocorreu um erro  ao filtrar as memorias: {e}")

def pesquisa_armazenamento(armType, armVariant, armSize):
    with open('csvs/preços.csv', 'w', newline='', encoding='utf-8') as f:
        pass
    for t in armType:
        for v in armVariant:
            if t == "hdd":
                if v in ("m.2 sata", "m.2 nvme"):
                    continue #nao existe HDs m.2
            for s in armSize:
                if t == "hdd":
                   if s in ("128gb", "256gb"):
                       continue
                busca  = f"{t} {v} {s}"
                pesquisa(busca)
                filtrarArmazenamento.filtrar_amazenamento('csvs/Produtos Ordenados.csv', t,v,s)
    
def pesquisar_nvidia(vendor, series,generations,tiers):
    
    with open('csvs/preços.csv', 'w', newline='', encoding='utf-8') as f:
            pass
    #pesquisando e filtrando
    for g in generations:
                for t in tiers:
                    if g == "10":
                        if int(t) < 80:
                            busca = f"{vendor} {series[0]} {g}{t}"
                            filtrarGpus.filtrar_nvidia('csvs/Produtos Ordenados.csv', series[0], g, t)
                    elif g == '16':
                            if int(t) < 60:
                                busca = f"{vendor} {series[0]} {g}{t}"
                                filtrarGpus.filtrar_nvidia('csvs/Produtos Ordenados.csv', series[0], g, t)
                    elif int(g) > 16 and int(g) < 40:
                        if int(t) > 30 and int(t) < 90:
                            busca = f"{vendor} {series[1]} {g}{t}"
                            filtrarGpus.filtrar_nvidia('csvs/Produtos Ordenados.csv', series[0], g, t)
                    elif int(g) >= 40:
                        if int(t) > 30:
                            busca = f"{vendor} {series[1]} {g}{t}"
                            filtrarGpus.filtrar_nvidia('csvs/Produtos Ordenados.csv', series[0], g, t)
                    
                    pesquisa(busca)

def pesquisar_amd_gpu(vendor,series,generations):
    # Limpa o CSV anterior antes de popular os novos
    with open('csvs/preços.csv', 'w', newline='', encoding='utf-8') as f:
        pass
    for g in generations:
        busca = f"{vendor} radeon {series} {g}"
        pesquisa(busca)
        filtrarGpus.filtrar_amd_gpu("csvs/Produtos Ordenados.csv",vendor,series,g)

def pesquisar_intel_cpu(vendor, lines, generations, videoIntegrado=None):
    # Limpa o CSV anterior antes de popular os novos
    with open('csvs/preços.csv', 'w', newline='', encoding='utf-8') as f:
        pass
    print("pesquisando processadores intel...")
    for linha in lines:
        for g in generations:
            if (g, linha) in intelIndisponiveis:
                continue  # Pula combinações que não existem em desktop
            busca = f"processador {vendor} core {linha} {g} geracao"
            pesquisa(busca)
            try:
                filtrarCpus.filtrar_intel_cpu('csvs/Produtos Ordenados.csv', linha, g, videoIntegrado)
                print(f"Filtragem de {busca} concluidas ")
            except Exception as e:
                print(f"Ocorreu um erro ao filtrar os processadores: {e}")

def pesquisar_amd_cpu(lines=None, generations=None, vendor="amd"):
    # Limpa o CSV anterior antes de popular os novos
    with open('csvs/preços.csv', 'w', newline='', encoding='utf-8') as f:
        pass
    print("pesquisando processadores amd ryzen...")

    if lines is None:
        lines = amdRyzenLines
    if generations is None:
        generations = amdRyzenGenerations

    for linha in lines:
        for g in generations:
            if (linha, g) in amdRyzenIndisponiveis:
                continue
            busca = f"processador {vendor} {linha} {g}"
            # pesquisa(busca)
            try:
                filtrarCpus.filtrar_amd_cpu('csvs/Produtos Ordenados.csv', linha, g)
                print(f"Filtragem de {busca} concluidas ")
            except Exception as e:
                print(f"Ocorreu um erro ao filtrar os processadores: {e}")

def pesquisar_coolers(tipos=None, estilos=None):
    with open('csvs/preços.csv', 'w', newline='', encoding='utf-8') as f:
        pass
    print("pesquisando coolers...")
    if tipos is None:
        tipos = coolerTipos
    if estilos is None:
        estilos = coolerEstilos
    buscas = []
    for tipo in tipos:
        for estilo in estilos:
            if estilo == "simple":
                buscas.append(f"{tipo}")
            else:
                buscas.append(f"{tipo} {estilo}")
    for busca in buscas:
        pesquisa(busca)
        try:
            filtrarCoolers.filtrar_coolers('csvs/Produtos Ordenados.csv', tipo, estilo)
            print(f"Filtragem de {busca} concluidas ")
        except Exception as e:
            print(f"Ocorreu um erro ao filtrar os coolers: {e}")        
    

def pesquisar_fontes(capacidades=None):
    # Limpa o CSV anterior antes de popular os novos
    with open('csvs/preços.csv', 'w', newline='', encoding='utf-8') as f:
        pass
    print("pesquisando fontes de alimentacao...")

    if capacidades is None:
        capacidades = fontesCapacidade

    for capacidade in capacidades:
        busca = f"fonte {capacidade} 80 plus"
        pesquisa(busca)
        try:
            filtrarFontes.filtrar_fontes('csvs/Produtos Ordenados.csv', capacidade)
            print(f"Filtragem de {busca} concluidas ")
        except Exception as e:
            print(f"Ocorreu um erro ao filtrar as fontes: {e}")

def pesquisar_gabinetes(tipos=None):
    # Limpa o CSV anterior antes de popular os novos
    with open('csvs/preços.csv', 'w', newline='', encoding='utf-8') as f:
        pass
    print("pesquisando gabinetes...")

    if tipos is None:
        tipos = gabinetesTipos

    for tipo in tipos:
        busca = f"gabinete {tipo}"
        #pesquisa(busca)
        try:
            filtrarGabinetes.filtrar_gabinetes('csvs/Produtos Ordenados.csv', tipo)
            print(f"Filtragem de {busca} concluidas ")
        except Exception as e:
            print(f"Ocorreu um erro ao filtrar os gabinetes: {e}")

def pesquisar_placas_mae(soquetes=None):
    # Limpa o CSV anterior antes de popular os novos
    with open('csvs/preços.csv', 'w', newline='', encoding='utf-8') as f:
        pass
    print("pesquisando placas-mae...")

    if soquetes is None:
        soquetes = placasMaeSoquetes

    for soquete in soquetes:
        busca = f"placa mae {soquete}"
        pesquisa(busca)
        try:
            filtrarPlacasMae.filtrar_placas_mae('csvs/Produtos Ordenados.csv', soquete)
            print(f"Filtragem de {busca} concluidas ")
        except Exception as e:
            print(f"Ocorreu um erro ao filtrar as placas-mae: {e}")

#######################FUNCAO MAIN (RODA QUANDO O ARQUIVO.PY E CHAMADO INDIVIDUALMENTE)#############################################
if __name__ == '__main__':
    # Limpa o CSV anterior antes de popular os novos
    ###################--FAVOR COMENTAR AS PESQUISAS QUE NAO DESEJA FAZER--#####################################################
    # pesquisa_armazenamento(armazenamentoTypes, armVariant, armazenamentoSizes) #PESQUISA HDS E SSDS
    # pesquisar_ram(ramSizes, ramTypes) #PESQQQUISA MEMORIAS RAM DDR3 A 5
    # pesquisar_nvidia(gpuVendors[0], nvidiaSeries, nvidiaGenerations, nvidiaTiers) # PESQUISA PLACAS DE VIDEO NVIDIA DESDE A GTX1050 PRA CIMA
    # pesquisar_intel_cpu(intelVendor, intelLines, intelGenerations) #PESQUISA PROCESSADORES INTEL CORE DA 10ª A 14ª GERAÇÃO
    # pesquisar_amd_cpu(amdRyzenLines, amdRyzenGenerations, "amd","todos") #PESQUISA PROCESSADORES AMD RYZEN (1 BUSCA POR SKU)
    # pesquisar_amd_cpu() #PESQUISA PROCESSADORES AMD RYZEN (1 BUSCA POR SKU)
    # pesquisar_intel_cpu(intelVendor, intelLines, intelGenerations, "todos") #PESQUISA  OS INTEL CORE COM E SEM VIDEO INTEGRADO
    # pesquisar_amd_gpu(gpuVendors[1],AMDSeries,radeon5HSeries)
    # pesquisar_amd_gpu(gpuVendors[1],AMDSeries,radeon5TSeries)
    # pesquisar_amd_gpu(gpuVendors[1],AMDSeries,radeon6TSeries)
    # pesquisar_amd_gpu(gpuVendors[1],AMDSeries,radeon7TSeries)
    # pesquisar_amd_gpu(gpuVendors[1],AMDSeries,radeon9TSeries)
    # pesquisar_fontes(fontesCapacidade) #PESQUISA FONTES DE ALIMENTAÇÃO 80 PLUS
    # pesquisar_placas_mae(placasMaeSoquetes) #PESQUISA PLACAS-MAE POR SOQUETE (LGA/AM)
    pesquisar_gabinetes(gabinetesTipos) #PESQUISA GABINETES (MINI/MID/FULL TOWER)
    # pesquisar_coolers() #PESQUISA COOLERS (AIR/WATER) SIMPLE/RGB/ARGB