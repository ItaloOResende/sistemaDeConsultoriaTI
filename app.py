from flask import Flask, render_template_string
from playwright.sync_api import sync_playwright

app = Flask(__name__)

# =====================================================================
# SEU CEP CONFIGURADO PARA O CALCULO DE FRETE
# =====================================================================
MEU_CEP = "32280570"  

# Frete fixo estimado para a sua região no lote de peças
FRETE_FIXO_LOCAL = 19.90 

def buscar_item_mais_barato_amazon(termo):
    with sync_playwright() as p:
        # headless=False força o navegador a abrir na sua tela para você testar visualmente
        browser = p.chromium.launch(headless=False) 
        page = browser.new_page()
        
        # URL já configurada para buscar o item e ordenar automaticamente pelo MENOR PREÇO (&s=price-asc-rank)
        url_busca = f"https://www.amazon.com.br/s?k={termo.replace(' ', '+')}&s=price-asc-rank"
        
        try:
            page.goto(url_busca, timeout=60000)
            page.wait_for_load_state("networkidle") # Espera o site carregar tudo
            
            # Seleciona o primeiro produto válido da lista (que será o mais barato)
            primeiro_produto = page.locator("div[data-component-type='s-search-result']").first
            
            if primeiro_produto.count() > 0:
                nome = primeiro_produto.locator("h2").text_content().strip()
                
                # Pega o link exato de compra daquele produto específico
                link_relativo = primeiro_produto.locator("h2 a").get_attribute("href")
                link_direto_compra = f"https://www.amazon.com.br{link_relativo.split('?')[0]}"
                
                # Tenta pegar o preço real impresso na tela
                try:
                    preco_inteiro = primeiro_produto.locator("span.a-price-whole").first.text_content().strip()
                    preco_fracao = primeiro_produto.locator("span.a-price-fraction").first.text_content().strip()
                    preco_str = preco_inteiro.replace(".", "").replace(",", "") + "." + preco_fracao
                    preco = float(preco_str)
                except:
                    preco = 0.00 
                
                browser.close()
                return {
                    "loja": "Amazon (Menor Preço)",
                    "nome": nome,
                    "preco_peca": preco,
                    "frete": FRETE_FIXO_LOCAL,
                    "total": preco + FRETE_FIXO_LOCAL if preco > 0 else FRETE_FIXO_LOCAL,
                    "link": link_direto_compra
                }
        except Exception as e:
            print(f"Erro ao buscar '{termo}': {e}")
            
        browser.close()
        return {
            "loja": "Amazon (Link Ordenado)",
            "nome": f"{termo} (Não conseguimos extrair o preço, veja direto no link)",
            "preco_peca": 0.00,
            "frete": FRETE_FIXO_LOCAL,
            "total": 0.00,
            "link": url_busca
        }

@app.route('/')
def index():
    # Peças do PC
    pecas_para_buscar = [
        "Intel Core i3 8100",
        "Memoria RAM 8GB DDR4 Desktop",
        "SSD 256GB SATA 2.5"
    ]
    
    resultados = []
    total_configuracao = 0
    
    for peca in pecas_para_buscar:
        dados_peca = buscar_item_mais_barato_amazon(peca)
        resultados.append(dados_peca)
        if dados_peca["preco_peca"] > 0:
            total_configuracao += dados_peca["total"]

    html_template = """
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>Consultor de TI - Menor Preço Real</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f4f9; margin: 40px; }
            .container { max-width: 1100px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
            h1 { color: #333; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background-color: #2c3e50; color: white; }
            .btn-comprar { background-color: #2ecc71; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 13px; display: inline-block; }
            .btn-comprar:hover { background-color: #27ae60; }
            .total-box { margin-top: 20px; padding: 15px; background: #e8f8f5; border-left: 5px solid #2ecc71; font-size: 18px; font-weight: bold; }
            .cep-box { color: #7f8c8d; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Peças Mais Baratas Encontradas</h1>
            <div class="cep-box">Destino de Entrega para Montagem: Contagem-MG (CEP: <strong>{{ cep }}</strong>)</div>
            
            <table>
                <tr>
                    <th>Componente Real Encontrado</th>
                    <th>Loja</th>
                    <th>Preço Real da Peça</th>
                    <th>Frete p/ sua Casa</th>
                    <th>Subtotal</th>
                    <th>Ação</th>
                </tr>
                {% for item in resultados %}
                <tr>
                    <td><strong>{{ item.nome[:100] }}...</strong></td>
                    <td>{{ item.loja }}</td>
                    <td>{% if item.preco_peca > 0 %}R$ {{ "%.2f"|format(item.preco_peca) }}{% else %}Ver no site{% endif %}</td>
                    <td>R$ {{ "%.2f"|format(item.frete) }}</td>
                    <td>{% if item.preco_peca > 0 %}R$ {{ "%.2f"|format(item.total) }}{% else %}---{% endif %}</td>
                    <td><a href="{{ item.link }}" target="_blank" class="btn-comprar">Comprar Peça Direta ↗</a></td>
                </tr>
                {% endfor %}
            </table>
            
            {% if total_configuracao > 0 %}
            <div class="total-box">
                Custo Total Real de Custo na sua casa: R$ {{ "%.2f"|format(total_configuracao) }}
            </div>
            {% endif %}
        </div>
    </body>
    </html>
    """
    return render_template_string(html_template, resultados=resultados, total_configuracao=total_configuracao, cep=MEU_CEP)

if __name__ == '__main__':
    app.run(debug=True)