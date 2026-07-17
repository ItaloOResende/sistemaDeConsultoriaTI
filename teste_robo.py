# Biblioteca responsável por controlar o navegador automaticamente
from playwright.sync_api import sync_playwright

# Utilizado para localizar a pasta do perfil do navegador e manipular caminhos
import os

# Pausas entre ações do navegador para evitar carregamentos muito rápidos
import time 

# Função responsável por procurar memória DDR4 8GB na Amazon
def varrer_amazon(page, termo_busca):
    # Monta a URL de busca com o termo informado
    url = f"https://www.amazon.com.br/s?k={termo_busca.replace(' ', '+')}"
    try:
        # Abre a página de resultados da Amazon
        page.goto(url, timeout=90000)
        time.sleep(2)

        # Se a página mostrar erro de carregamento, tenta recarregar
        if "desculpe" in page.title().lower() or page.locator("text=Algo deu errado").count() > 0:
            time.sleep(3)
            page.reload(timeout=90000)
            time.sleep(2)

        # Rola a página para carregar mais resultados visíveis
        for _ in range(3):
            page.mouse.wheel(0, 1500)
            time.sleep(1.2)

        # Volta ao topo para facilitar a leitura dos elementos
        page.evaluate("window.scrollTo(0, 0)")
        time.sleep(1)

        # Seleciona os cards de produtos da página
        produtos = page.locator("div[data-component-type='s-search-result']")
        total = produtos.count()
        menor_custo = float('inf')
        campeao = None
        if total > 0:
            for i in range(min(total, 20)):
                produto_atual = produtos.nth(i)
                try:
                    link_el = produto_atual.locator("h2 a, a.a-link-normal.a-text-normal").first
                    if link_el.count() == 0:
                        continue
                    # Lê o título do produto para validar se é memória DDR4 de 8GB
                    nome = link_el.text_content().strip().upper()
                    nome_limpo = nome.replace(" ", "")
                    tem_ddr4 = "DDR4" in nome_limpo
                    tem_8gb = "8GB" in nome_limpo or "8G" in nome_limpo

                    # Ignora itens que não são compatíveis com o alvo
                    if not tem_ddr4 or not tem_8gb or "NOTEBOOK" in nome or "SODIMM" in nome:
                        continue

                    # Busca o preço do produto na Amazon
                    preco_comp = produto_atual.locator("span.a-price").first
                    if preco_comp.count() > 0:
                        preco_txt = preco_comp.text_content().strip().split("R$")[1].strip().replace(".", "").replace(",", ".")
                        preco_prod = float(preco_txt)
                    else:
                        continue

                    # Tenta identificar o valor de frete para somar ao custo total
                    valor_frete = 0.0
                    linha_frete = produto_atual.locator("div.a-row.a-size-base.a-color-secondary, span[aria-label*='envio'], span[aria-label*='Entrega']").first
                    if linha_frete.count() > 0:
                        txt_frete = linha_frete.text_content().strip()
                        if "Entrega" in txt_frete or "entrega" in txt_frete:
                            txt_frete_min = txt_frete.lower()
                            if "grátis" in txt_frete_min or "gratis" in txt_frete_min:
                                valor_frete = 0.0
                            elif "r$" in txt_frete_min:
                                depois_do_rs = txt_frete_min.split("r$")[1].strip()
                                string_preco = depois_do_rs.split(":")[0].strip().split(" ")[0]
                                string_preco = string_preco.replace(".", "").replace(",", ".")
                                valor_frete = float(string_preco)
                    custo_total = preco_prod + valor_frete
                    print(f" -> Amazon Lido: R$ {preco_prod:.2f} + Frete R$ {valor_frete:.2f} = R$ {custo_total:.2f} | {nome[:25]}...")
                    if 0 < custo_total < menor_custo:
                        menor_custo = custo_total
                        link_rel = link_el.get_attribute("href")
                        campeao = {
                            "loja": "Amazon",
                            "nome": nome,
                            "preco_final": custo_total,
                            "link": f"https://www.amazon.com.br{link_rel.split('?')[0]}"
                        }
                except:
                    continue
        return campeao
    except Exception as e:
        print(f"❌ Erro na Amazon: {e}")
        return None

# Função responsável por consultar preços na KaBuM
def varrer_kabum(page, termo_busca):
    print("\n[Robô - KaBuM!] Lendo apenas produtos e preços...")

    # Ajusta o termo de busca para o formato usado pela loja
    termo_limpo = termo_busca.lower().replace("desktop", "").replace("memoria ram", "memoria").strip()
    termo_limpo = " ".join(termo_limpo.split()) 
    url = f"https://www.kabum.com.br/busca/{termo_limpo.replace(' ', '-')}?sort=price_asc"
    try:
        # Abre a página e espera que os produtos sejam renderizados
        page.goto(url, timeout=90000)
        print("[Robô - KaBuM!] Aguardando a renderização dos produtos na tela...")
        page.wait_for_selector("a[href*='/produto/']", timeout=20000)
        time.sleep(3) 

        # Coleta o texto bruto e o link de cada produto na tela usando JavaScript no navegador
        dados_produtos = page.evaluate("""
            () => {
                const resultados = [];
                const cards = document.querySelectorAll("a[href*='/produto/']");
                cards.forEach(card => {
                    const texto = card.innerText ? card.innerText.trim() : "";
                    if (texto.length > 20) {
                        resultados.push({
                            texto_completo: texto,
                            link: card.href
                        });
                    }
                });
                return resultados;
            }
        """)
        # Mostra quantos blocos foram capturados para análise posterior
        print(f"[Robô - KaBuM!] Elementos brutos encontrados no HTML: {len(dados_produtos)}")
        menor_custo = float('inf')
        campeao = None
        itens_lidos = 0

        # Percorre cada produto bruto e tenta identificar nome e preço válidos
        for prod in dados_produtos:
            if itens_lidos >= 15:
                break
            linhas = [linha.strip() for linha in prod['texto_completo'].split('\n') if linha.strip()]
            nome = ""
            precos_encontrados = []
            for linha in linhas:
                linha_up = linha.upper()
                if "DDR4" in linha_up and "8GB" in linha_up:
                    nome = linha_up
                if "R$" in linha_up and "10X" not in linha_up and "OU" not in linha_up and "PIX" not in linha_up:
                    try:
                        p_limpo = linha_up.split("R$")[1].strip().replace(".", "").replace(",", ".")
                        p_limpo = p_limpo.split()[0].strip()
                        preco_float = float(p_limpo)
                        precos_encontrados.append(preco_float)
                    except:
                        continue
            # Ignora itens sem nome ou sem preço identificável
            if not nome or not precos_encontrados:
                continue

            # Exclui produtos que não são compatíveis com o tipo buscado
            if "NOTEBOOK" in nome or "SODIMM" in nome:
                continue

            # Filtra preços muito baixos, que normalmente não são memória desktop DDR4 8GB
            precos_validos = [p for p in precos_encontrados if p >= 150.0]
            if not precos_validos:
                continue
            preco_prod = min(precos_validos)
            print(f" -> KaBuM! Lido: R$ {preco_prod:.2f} | {nome[:30]}...")
            itens_lidos += 1
            if preco_prod < menor_custo:
                menor_custo = preco_prod
                campeao = {
                    "loja": "KaBuM!",
                    "nome": nome,
                    "preco_final": preco_prod,
                    "link": prod['link']
                }
        # Se nenhum item passou nos filtros, informa isso para o usuário
        if itens_lidos == 0:
            print("[Robô - KaBuM!] Nenhum produto passou pelos filtros de hardware.")
        return campeao
    except Exception as e:
        print(f"❌ Erro na KaBuM!: {e}")
        return None

# Função responsável por procurar o menor preço na Terabyte
def varrer_terabyte(page, termo_busca):
    print("\n[Robô - Terabyte] Iniciando busca estrita para evitar lixo...")

    # URL já filtrada para o tipo de produto desejado
    url = f"https://www.terabyteshop.com.br/busca?str=memoria+ddr4+8gb&dir=asc&order=price"
    try:
        # Acessa a busca e aguarda o carregamento da página
        page.goto(url, timeout=90000)
        time.sleep(4)

        # Coleta todos os links de produtos exibidos na página
        links = page.locator("a[href*='/produto/']")
        total = links.count()
        print(f"[Robô - Terabyte] Total de itens mapeados: {total}")
        menor_custo = float('inf')
        campeao = None
        itens_lidos = 0
        if total > 0:
            for i in range(total):
                if itens_lidos >= 15: 
                    break
                try:
                    link_el = links.nth(i)
                    nome = link_el.text_content().strip().upper()
                    if "KIT" in nome or "UPGRADE" in nome or "PLACA" in nome or len(nome) < 10:
                        continue
                    if "DDR4" not in nome or "8GB" not in nome or "NOTEBOOK" in nome or "SODIMM" in nome:
                        continue
                    box = link_el.locator("xpath=./ancestor::div[contains(@class, 'product-item')] | ..")
                    preco_el = box.locator(".prod-new-price, [id*='val_prod']").first
                    preco_txt = preco_el.text_content().strip()
                    if "R$" in preco_txt:
                        preco_limpo = preco_txt.replace("R$", "").replace(".", "").replace(",", ".").strip()
                        preco_prod = float(preco_limpo)
                        print(f" -> Terabyte Lido: R$ {preco_prod:.2f} | {nome[:25]}...")
                        itens_lidos += 1
                        if preco_prod < menor_custo:
                            menor_custo = preco_prod
                            campeao = {
                                "loja": "Terabyte",
                                "nome": nome,
                                "preco_final": preco_prod,
                                "link": link_el.get_attribute("href")
                            }
                except:
                    continue
        return campeao
    except Exception as e:
        print(f"❌ Erro na Terabyte: {e}")
        return None

# Função responsável por consultar preços na Pichau
def varrer_pichau(page, termo_busca):
    print("\n[Robô - Pichau] Iniciando varredura...")

    # Monta a URL de busca com o termo informado
    url = f"https://www.pichau.com.br/search?q={termo_busca.replace(' ', '%20')}"
    try:
        # Abre a página e espera a listagem aparecer
        page.goto(url, timeout=90000)
        time.sleep(5) 

        # Seleciona os cards de produtos da loja
        cards = page.locator("div[data-testid='product-item']")
        total = cards.count()
        print(f"[Robô - Pichau] Total de produtos detectados: {total}")
        menor_custo = float('inf')
        campeao = None
        if total > 0:
            for i in range(min(total, 15)):
                card = cards.nth(i)
                try:
                    nome_el = card.locator("h2").first
                    if nome_el.count() == 0:
                        continue
                    nome = nome_el.text_content().strip().upper()
                    if "DDR4" not in nome or "8GB" not in nome or "NOTEBOOK" in nome or "SODIMM" in nome:
                        continue
                    preco_el = card.locator("div:has-text('R$')").last
                    preco_txt = preco_el.text_content().strip()
                    if "R$" in preco_txt:
                        preco_limpo = preco_txt.split("R$")[1].strip().replace(".", "").replace(",", ".").split(" ")[0]
                        preco_limpo = "".join([c for c in preco_limpo if c.isdigit() or c == '.'])
                        preco_prod = float(preco_limpo)
                    else:
                        continue
                    print(f" -> Pichau Lido: R$ {preco_prod:.2f} | {nome[:25]}...")
                    if preco_prod < menor_custo:
                        menor_custo = preco_prod
                        link_el = card.locator("a").first
                        link_rel = link_el.get_attribute("href")
                        link_final = link_rel if "pichau.com.br" in link_rel else f"https://www.pichau.com.br{link_rel}"
                        campeao = {
                            "loja": "Pichau",
                            "nome": nome,
                            "preco_final": preco_prod,
                            "link": link_final
                        }
                except:
                    continue
        return campeao
    except Exception as e:
        print(f"❌ Erro na Pichau: {e}")
        return None

# Função principal: abre o navegador, roda as buscas e compara os resultados
def comparar_lojas(termo):
    with sync_playwright() as p:
        # Usa um perfil persistente do Chrome para manter cookies e sessão do navegador
        pasta_perfil = os.path.join(os.getcwd(), "perfil_robo")

        # Cria um contexto de navegação com configuração de navegador real
        browser_context = p.chromium.launch_persistent_context(
            pasta_perfil,
            headless=False,
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
            locale="pt-BR",
            args=["--disable-blink-features=AutomationControlled"],
            viewport={'width': 1280, 'height': 1024}
        )

        # Página ativa que será reutilizada pelas funções de raspagem
        page = browser_context.new_page()

        # Executa a coleta em cada loja
        res_amazon = varrer_amazon(page, termo)
        time.sleep(2)
        res_kabum = varrer_kabum(page, termo)
        time.sleep(2)
        res_terabyte = varrer_terabyte(page, termo)
        time.sleep(2)
        res_pichau = varrer_pichau(page, termo)

        # Filtra apenas resultados válidos retornados pelas funções
        resultados = [r for r in [res_amazon, res_kabum, res_terabyte, res_pichau] if r is not None]
        # Se existirem resultados válidos, mostra o melhor preço e o link do vencedor
        if resultados:
            vencedor = min(resultados, key=lambda x: x['preco_final'])
            print("\n" + "="*50)
            print("📊 ===== RELATÓRIO COMPARATIVO BLACK TI =====")
            print("="*50)
            for res in resultados:
                print(f"🏢 {res['loja']}: {res['nome'][:30]}... -> R$ {res['preco_final']:.2f}")
            print("\n" + "="*50)
            print(f"🏆 VENCEDOR ABSOLUTO: {vencedor['loja'].upper()}!")
            print(f"💰 Preço Imbatível: R$ {vencedor['preco_final']:.2f}")
            print(f"🔗 Link direto: {vencedor['link']}")
            print("="*50)

            # Abre a página do produto vencedor para o usuário visualizar
            page.goto(vencedor['link'])
        else:
            print("\n❌ Nenhuma loja retornou dados válidos de hardware com os filtros ativos.")

        # Mantém o navegador aberto para análise manual do usuário
        print("\n[FIM] Dê Ctrl+C no terminal para fechar tudo.")
        while True:
            time.sleep(1)

# Execução inicial do robô com o termo de busca padrão
comparar_lojas("memoria ram ddr4 8gb desktop")