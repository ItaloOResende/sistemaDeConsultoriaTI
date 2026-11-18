<?php
set_time_limit(300);

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/cotacao.php';

// Os CSVs ficam em csvs/, na raiz do projeto (um nível acima de site/php).
$raizProjeto = dirname(dirname(__DIR__));
$csvsDir     = $raizProjeto . DIRECTORY_SEPARATOR . 'csvs' . DIRECTORY_SEPARATOR;

$perfilBusca    = $_GET['perfil_busca'] ?? 'barato';
$modoSetup      = ($_GET['montar_setup'] ?? '') === '1';
$completarSetup = ($_GET['completar_setup'] ?? '') === '1';

$cotacao = calcularCotacao($_GET, $csvsDir, $modoSetup, $perfilBusca, $completarSetup);

$pecasParaCotar    = $cotacao['pecas'];
$resultadosPorPeca = $cotacao['resultados'];
$totalSoma         = $cotacao['total'];
$pecasComFalta     = $cotacao['faltantes'];
$ultimaAtualizacao = $cotacao['atualizacao'];

// Variáveis usadas pelo formulário (refletem os padrões do modo setup)
$entrada      = $cotacao['entrada'];
$cpuMarca     = $entrada['cpu_marca'];
$cpuLinha     = $entrada['cpu_linha'];
$cpuGen       = $entrada['cpu_gen'];
$cpuVideo     = $entrada['cpu_video'];
$ramTipo      = $entrada['ram_tipo'];
$ramCap       = $entrada['ram_cap'];
$ramDdr       = $entrada['ram_ddr'];
$discoTipo    = $entrada['disco_tipo'];
$discoCap     = $entrada['disco_cap'];
$gpuMarca     = $entrada['gpu_marca'];
$gpuSerie     = $entrada['gpu_serie'];
$gpuModelo    = $entrada['gpu_modelo'];
$fonteCap     = $entrada['fonte_cap'];
$placaSoquete = $entrada['placa_soquete'];
$gabineteTipo = $entrada['gabinete_tipo'];
$coolerTipo   = $entrada['cooler_tipo'];
$coolerEstilo = $entrada['cooler_estilo'];

$ehAdmin       = ehAdmin();
$temResultado  = $totalSoma > 0 && !empty($resultadosPorPeca);
$podeFinalizar = $temResultado && $pecasComFalta === 0;

// Preço mostrado ao cliente final x custo interno (admin).
// A regra de lucro é aplicada PEÇA A PEÇA e depois somada — nunca no total.
$lucroPecas      = 0.0;
$detalhesLucro   = [];
foreach ($resultadosPorPeca as $dadosPeca) {
    if (($dadosPeca['melhor'] ?? null) === null) continue;
    $itemPeca  = $dadosPeca['melhor'];
    $custoPeca = (float) $itemPeca['precoNum'];
    $lucroPeca = lucroPecasCliente($custoPeca);
    $lucroPecas += $lucroPeca;
    $detalhesLucro[] = [
        'rotulo' => $dadosPeca['rotulo'],
        'custo'  => $custoPeca,
        'lucro'  => $lucroPeca,
        'venda'  => round($custoPeca + $lucroPeca, 2),
    ];
}
$lucroPecas      = round($lucroPecas, 2);
$totalPecasLucro = round($totalSoma + $lucroPecas, 2);
$montagemCliente = $modoSetup ? (float) $configPrecos['montagem_pc'] : 0.0;
$freteCliente    = $modoSetup ? (float) $configPrecos['frete_pc'] : 0.0;
$totalCliente    = round($totalPecasLucro + $montagemCliente + $freteCliente, 2);
$valorServico    = $modoSetup ? $montagemCliente : $lucroPecas;

// Mantém todos os filtros atuais ao ir para o checkout
$queryCheckout = $_GET;
$queryCheckout['montar_setup']   = $modoSetup ? '1' : '0';
$queryCheckout['completar_setup'] = $completarSetup ? '1' : '0';
$linkCheckout = 'checkout.php?' . http_build_query($queryCheckout);

// Atalho no quadro do total: refaz a busca completando as peças que faltam.
$queryCompletarSetup = $_GET;
$queryCompletarSetup['montar_setup']   = '1';
$queryCompletarSetup['completar_setup'] = '1';
$linkCompletarSetup = 'index.php?' . http_build_query($queryCompletarSetup) . '#resultados';

$erroTerminal = '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <link rel="stylesheet" href="../css/style.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= htmlspecialchars($nomeSistema) ?> — compare preços de peças de PC nas principais lojas e monte seu setup pagando menos.">
    <meta name="theme-color" content="#0b1120">
    <title><?= htmlspecialchars($nomeSistema) ?> — Consultoria &amp; Cotação de Hardware</title>
</head>
<body>

<!-- MENU FIXO -->
<?php require __DIR__ . '/includes/topbar.php'; ?>

<div class="dashboard-container">
    
    <!-- BARRA LATERAL (FILTROS) -->
    <aside class="sidebar" id="cotar">
        <div class="sidebar-header">
            <h2><?= htmlspecialchars($nomeSistema) ?></h2>
            <p>Configurador & Cotação em Tempo Real</p>
        </div>

        <form method="GET" action="index.php" id="formSidebar">
            
            <div class="perfil-box">
                <span>Critério de Preço:</span>
                <div class="radio-group">
                    <label class="radio-label">
                        <input type="radio" name="perfil_busca" value="barato" <?= $perfilBusca === 'barato' ? 'checked' : '' ?>>
                        <span>💰 Economia</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="perfil_busca" value="desempenho" <?= $perfilBusca === 'desempenho' ? 'checked' : '' ?>>
                        <span>🚀 Desempenho</span>
                    </label>
                </div>
            </div>

            <div class="accordion-group">
                
                <!-- 1. Processador -->
                <div class="accordion-item">
                    <button type="button" class="accordion-btn" onclick="toggleAccordion(this)">
                        <span>🧠 Processador</span>
                        <span class="seta">▼</span>
                    </button>
                    <div class="accordion-content">
                        <select name="cpu_marca" id="cpu_marca" class="seletor-peca">
                            <option value="">Marca (Selecionar)</option>
                            <option value="intel" <?= ($cpuMarca == 'intel') ? 'selected' : '' ?>>Intel Core</option>
                            <option value="amd" <?= ($cpuMarca == 'amd') ? 'selected' : '' ?>>AMD Ryzen</option>
                        </select>
                        
                        <select name="cpu_linha" id="cpu_linha">
                            <option value="">Linha (Selecionar)</option>
                        </select>
                        
                        <select name="cpu_gen" id="cpu_gen">
                            <option value="">Geração (Selecionar)</option>
                        </select>

                        <select name="video_integrado" id="video_integrado">
                            <option value="">Vídeo Integrado (Selecionar)</option>
                            <option value="sim" <?= ($cpuVideo == 'sim') ? 'selected' : '' ?>>Com Vídeo Integrado</option>
                            <option value="nao" <?= ($cpuVideo == 'nao') ? 'selected' : '' ?>>Sem Vídeo Integrado</option>
                        </select>
                    </div>
                </div>

                <!-- 2. Memória RAM -->
                <div class="accordion-item">
                    <button type="button" class="accordion-btn" onclick="toggleAccordion(this)">
                        <span>⚡ Memória RAM</span>
                        <span class="seta">▼</span>
                    </button>
                    <div class="accordion-content">
                        <select name="ram_tipo" id="ram_tipo" class="seletor-peca">
                            <option value="">Equipamento (Selecionar)</option>
                            <option value="desktop" <?= (($_GET['ram_tipo'] ?? '') == 'desktop') ? 'selected' : '' ?>>Desktop (PC de Mesa)</option>
                            <option value="notebook" <?= (($_GET['ram_tipo'] ?? '') == 'notebook') ? 'selected' : '' ?>>Notebook (SODIMM)</option>
                        </select>

                        <select name="ram_cap" id="ram_cap" class="seletor-peca">
                            <option value="">Capacidade (Ignorar)</option>
                            <option value="2gb" <?= (($ramCap ?? '') == '2gb') ? 'selected' : '' ?>>2GB</option>
                            <option value="4gb" <?= (($ramCap ?? '') == '4gb') ? 'selected' : '' ?>>4GB</option>
                            <option value="8gb" <?= (($ramCap ?? '') == '8gb') ? 'selected' : '' ?>>8GB</option>
                            <option value="16gb" <?= (($ramCap ?? '') == '16gb') ? 'selected' : '' ?>>16GB</option>
                            <option value="32gb" <?= (($ramCap ?? '') == '32gb') ? 'selected' : '' ?>>32GB</option>
                            <option value="64gb" <?= (($ramCap ?? '') == '64gb') ? 'selected' : '' ?>>64GB</option>
                        </select>

                        <select name="ram_ddr" id="ram_ddr" class="seletor-peca">
                            <option value="">Qualquer Padrão DDR...</option>
                            <option value="ddr3" <?= (($ramDdr ?? '') == 'ddr3') ? 'selected' : '' ?>>DDR3</option>
                            <option value="ddr4" <?= (($ramDdr ?? '') == 'ddr4') ? 'selected' : '' ?>>DDR4</option>
                            <option value="ddr5" <?= (($ramDdr ?? '') == 'ddr5') ? 'selected' : '' ?>>DDR5</option>
                        </select>
                    </div>
                </div>

                <!-- 3. Armazenamento -->
                <div class="accordion-item">
                    <button type="button" class="accordion-btn" onclick="toggleAccordion(this)">
                        <span>💾 Armazenamento</span>
                        <span class="seta">▼</span>
                    </button>
                    <div class="accordion-content">
                        <select name="disco_tipo" id="disco_tipo" class="seletor-peca">
                            <option value="">Tipo (Ignorar)</option>
                            <option value="M.2 NVMe" <?= ($discoTipo == 'M.2 NVMe') ? 'selected' : '' ?>>M.2 NVMe</option>
                            <option value="M.2 SATA" <?= ($discoTipo == 'M.2 SATA') ? 'selected' : '' ?>>M.2 SATA</option>
                            <option value="ssd sata" <?= ($discoTipo == 'ssd sata') ? 'selected' : '' ?>>SSD SATA</option>
                            <option value="hd PC" <?= ($discoTipo == 'hd PC') ? 'selected' : '' ?>>HD PC</option>
                            <option value="hd notebook" <?= ($discoTipo == 'hd notebook') ? 'selected' : '' ?>>HD notebook</option>
                        </select>
                        <select name="disco_cap" id="disco_cap" class="seletor-peca">
                            <option value="">Capacidade (Ignorar)</option>
                            <option value="120gb" <?= ($discoCap == '120gb') ? 'selected' : '' ?>>120GB</option>
                            <option value="240gb" <?= ($discoCap == '240gb') ? 'selected' : '' ?>>240GB / 256GB</option>
                            <option value="480gb" <?= ($discoCap == '480gb') ? 'selected' : '' ?>>480GB / 512GB</option>
                            <option value="1tb" <?= ($discoCap == '1tb') ? 'selected' : '' ?>>1TB</option>
                            <option value="2tb" <?= ($discoCap == '2tb') ? 'selected' : '' ?>>2TB</option>
                            <option value="4tb" <?= ($discoCap == '4tb') ? 'selected' : '' ?>>4TB</option>
                        </select>
                    </div>
                </div>

                <!-- 4. Placa de Vídeo -->
                <div class="accordion-item">
                    <button type="button" class="accordion-btn" onclick="toggleAccordion(this)">
                        <span>🎮 Placa de Vídeo</span>
                        <span class="seta">▼</span>
                    </button>
                    <div class="accordion-content">
                        <select name="gpu_marca" id="gpu_marca" class="seletor-peca">
                            <option value="">Marca (Sem Placa)</option>
                            <option value="nvidia" <?= ($gpuMarca == 'nvidia') ? 'selected' : '' ?>>NVIDIA GeForce</option>
                            <option value="amd" <?= ($gpuMarca == 'amd') ? 'selected' : '' ?>>AMD Radeon</option>
                        </select>
                        <select name="gpu_serie" id="gpu_serie">
                            <option value="">Série...</option>
                        </select>
                        <select name="gpu_modelo" id="gpu_modelo">
                            <option value="">Modelo Específico...</option>
                        </select>
                    </div>
                </div>

                <!-- 5. Outros Componentes -->
                <div class="accordion-item">
                    <button type="button" class="accordion-btn" onclick="toggleAccordion(this)">
                        <span>🔧 Outros Componentes</span>
                        <span class="seta">▼</span>
                    </button>
                    <div class="accordion-content">
                        <select name="fonte_cap" id="fonte_cap" class="seletor-peca">
                            <option value="">Fonte (Ignorar)</option>
                            <option value="todos" <?= ($fonteCap == 'todos') ? 'selected' : '' ?>>Qualquer capacidade (mais barata)</option>
                            <option value="400w" <?= ($fonteCap == '400w') ? 'selected' : '' ?>>400W</option>
                            <option value="500w" <?= ($fonteCap == '500w') ? 'selected' : '' ?>>500W</option>
                            <option value="550w" <?= ($fonteCap == '550w') ? 'selected' : '' ?>>550W</option>
                            <option value="600w" <?= ($fonteCap == '600w') ? 'selected' : '' ?>>600W</option>
                            <option value="650w" <?= ($fonteCap == '650w') ? 'selected' : '' ?>>650W</option>
                            <option value="700w" <?= ($fonteCap == '700w') ? 'selected' : '' ?>>700W</option>
                            <option value="750w" <?= ($fonteCap == '750w') ? 'selected' : '' ?>>750W</option>
                            <option value="800w" <?= ($fonteCap == '800w') ? 'selected' : '' ?>>800W</option>
                            <option value="850w" <?= ($fonteCap == '850w') ? 'selected' : '' ?>>850W</option>
                            <option value="1000w" <?= ($fonteCap == '1000w') ? 'selected' : '' ?>>1000W</option>
                        </select>

                        <select name="placa_soquete" id="placa_soquete" class="seletor-peca">
                            <option value="">Placa-Mãe (Ignorar)</option>
                            <option value="todos" <?= ($placaSoquete == 'todos') ? 'selected' : '' ?>>Qualquer soquete (mais barata)</option>
                            <option value="AM4" <?= (strcasecmp($placaSoquete, 'AM4') === 0) ? 'selected' : '' ?>>AM4</option>
                            <option value="AM5" <?= (strcasecmp($placaSoquete, 'AM5') === 0) ? 'selected' : '' ?>>AM5</option>
                            <option value="LGA 1151" <?= (strcasecmp($placaSoquete, 'LGA 1151') === 0) ? 'selected' : '' ?>>LGA 1151</option>
                            <option value="LGA 1200" <?= (strcasecmp($placaSoquete, 'LGA 1200') === 0) ? 'selected' : '' ?>>LGA 1200</option>
                            <option value="LGA 1700" <?= (strcasecmp($placaSoquete, 'LGA 1700') === 0) ? 'selected' : '' ?>>LGA 1700</option>
                        </select>

                        <select name="gabinete_tipo" id="gabinete_tipo" class="seletor-peca">
                            <option value="">Gabinete (Ignorar)</option>
                            <option value="todos" <?= ($gabineteTipo == 'todos') ? 'selected' : '' ?>>Qualquer tamanho (mais barato)</option>
                            <option value="mini_tower" <?= ($gabineteTipo == 'mini_tower') ? 'selected' : '' ?>>Mini Tower</option>
                            <option value="mid_tower" <?= ($gabineteTipo == 'mid_tower') ? 'selected' : '' ?>>Mid Tower</option>
                            <option value="full_tower" <?= ($gabineteTipo == 'full_tower') ? 'selected' : '' ?>>Full Tower</option>
                        </select>

                        <select name="cooler_tipo" id="cooler_tipo" class="seletor-peca">
                            <option value="">Cooler (Ignorar)</option>
                            <option value="todos" <?= ($coolerTipo == 'todos') ? 'selected' : '' ?>>Qualquer tipo (mais barato)</option>
                            <option value="air" <?= ($coolerTipo == 'air') ? 'selected' : '' ?>>Air Cooler (a ar)</option>
                            <option value="water" <?= ($coolerTipo == 'water') ? 'selected' : '' ?>>Water Cooler (líquido)</option>
                        </select>

                        <select name="cooler_estilo" id="cooler_estilo" class="seletor-peca">
                            <option value="">Estilo do Cooler (Ignorar)</option>
                            <option value="todos" <?= ($coolerEstilo == 'todos') ? 'selected' : '' ?>>Qualquer estilo</option>
                            <option value="simples" <?= ($coolerEstilo == 'simples') ? 'selected' : '' ?>>Simples (sem LED)</option>
                            <option value="rgb" <?= ($coolerEstilo == 'rgb') ? 'selected' : '' ?>>RGB</option>
                            <option value="argb" <?= ($coolerEstilo == 'argb') ? 'selected' : '' ?>>ARGB</option>
                        </select>
                    </div>
                </div>

            </div>

            <label class="checkbox-container">
                <input type="checkbox" name="montar_setup" id="montarSetupCheckbox" value="1" <?= $modoSetup ? 'checked' : '' ?>>
                <span>Montar Setup Completo</span>
            </label>

            <button type="submit" class="btn-buscar">Cotar Peças</button>
        </form>
    </aside>

    <!-- ÁREA PRINCIPAL -->
    <main class="main-content" id="resultados">
        
        <?php if (!empty($erroTerminal)): ?>
            <pre style="background:#1e1e2e; color:#f38ba8; padding:1.2rem; border-radius:8px; border:1px solid #f38ba8; overflow-x:auto; font-size:0.85rem;"><?= htmlspecialchars($erroTerminal) ?></pre>
        <?php endif; ?>

        <?php
        $acaoCheckoutHtml = '';
        if ($temResultado) {
            if ($podeFinalizar) {
                $acaoCheckoutHtml = '<a class="btn-primario btn-comprar" href="' . e($linkCheckout) . '">'
                    . ($modoSetup ? 'Solicitar montagem' : 'Comprar peças') . '</a>';
            } else {
                $acaoCheckoutHtml = '<span class="checkout-nota checkout-nota-alerta">Ajuste os componentes sem oferta para finalizar.</span>';
            }
        }

        ?>

        <?php if ($totalSoma > 0): ?>
            <div class="summary-banner <?= $ehAdmin ? 'summary-admin' : 'summary-cliente' ?>">
                <div class="summary-texto">
                    <?php if ($ehAdmin): ?>
                        <div class="summary-title">Resumo do orçamento - Visão administrativa</div>
                        <div class="summary-subtitle">
                            <?= $pecasComFalta > 0
                                ? "Soma parcial: {$pecasComFalta} peça(s) sem oferta nos dados coletados."
                                : 'Soma do menor preço encontrado para cada componente.' ?>
                            <?php if ($ultimaAtualizacao > 0): ?>
                                · Dados de <?= date('d/m/Y H:i', $ultimaAtualizacao) ?>
                            <?php endif; ?>
                        </div>
                        <div class="resumo-linhas">
                            <div class="resumo-linha"><span>Custo total das peças</span><span><?= formatarPreco($totalSoma) ?></span></div>
                            <details class="lucro-detalhes">
                                <summary>
                                    <span>Lucro das peças <span class="seta">▾</span></span>
                                    <span><?= formatarPreco($lucroPecas) ?></span>
                                </summary>
                                <?php foreach ($detalhesLucro as $det): ?>
                                    <div class="lucro-linha">
                                        <strong><?= e($det['rotulo']) ?></strong>
                                        <span>
                                            custo <?= formatarPreco($det['custo']) ?>
                                            · lucro <?= formatarPreco($det['lucro']) ?>
                                            · venda <?= formatarPreco($det['venda']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </details>
                            <div class="resumo-linha"><span>Peças com lucro</span><span><?= formatarPreco($totalPecasLucro) ?></span></div>
                            <?php if ($modoSetup): ?>
                                <div class="resumo-linha"><span>Montagem, testes e embalagem</span><span><?= formatarPreco($montagemCliente) ?></span></div>
                            <?php endif; ?>
                            <div class="resumo-linha resumo-total"><span>Total a cobrar</span><strong><?= formatarPreco($totalCliente) ?></strong></div>
                        </div>
                        <?php if ($acaoCheckoutHtml !== ''): ?>
                            <div class="summary-action summary-action-admin"><?= $acaoCheckoutHtml ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="summary-title">
                            Total das peças selecionadas
                        </div>
                        <div class="summary-subtitle">
                            <?php if ($modoSetup): ?>
                                Peças + montagem
                            <?php else: ?>
                                Peças
                            <?php endif; ?>
                            <?php if ($pecasComFalta > 0): ?>
                                · <?= $pecasComFalta ?> peça(s) sem oferta.
                            <?php endif; ?>
                        </div>
                        <?php if ($modoSetup && $pecasComFalta > 0): ?>
                            <div class="resumo-aviso">⚠ <?= $pecasComFalta ?> componente(s) sem oferta nos dados atuais. Fale com a gente para ajustar a configuração.</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if (!$ehAdmin): ?>
                    <div class="summary-price">
                        <span class="summary-price-label">Total</span>
                        <?= formatarPreco($totalCliente) ?>
                        <?php if (!$modoSetup): ?>
                            <a class="summary-setup-link" href="<?= e($linkCompletarSetup) ?>">Quer que montemos seu PC? Clique aqui</a>
                        <?php endif; ?>
                        <?php if ($acaoCheckoutHtml !== ''): ?>
                            <div class="summary-action"><?= $acaoCheckoutHtml ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>

        <?php if (!empty($resultadosPorPeca)): ?>
            <?php foreach ($resultadosPorPeca as $chave => $dados): ?>
                <div class="categoria-bloco">
                    <div class="categoria-label">
                        <?= htmlspecialchars($dados['rotulo']) ?> — Termo: "<?= htmlspecialchars($dados['termo']) ?>"
                        <?php if ($ehAdmin && $dados['total'] > 0): ?>
                            · <?= $dados['total'] ?> oferta(s) encontrada(s)
                        <?php endif; ?>
                    </div>

                    <?php if ($ehAdmin): ?>
                        <?php if ($dados['melhor'] !== null): $item = $dados['melhor']; ?>
                            <?php
                            $precoCustoItem = (float) $item['precoNum'];
                            $lucroItem      = lucroPecasCliente($precoCustoItem);
                            $precoVendaItem = precoPecaCliente($precoCustoItem);
                            $margemEfetivaItem = $precoCustoItem > 0 ? ($lucroItem / $precoCustoItem) * 100 : 0;
                            ?>
                            <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank" rel="noopener" class="product-card destaque-menor">
                                <div class="product-info">
                                    <div class="product-title"><?= htmlspecialchars($item['titulo']) ?></div>
                                    <div class="product-price">Custo: <?= htmlspecialchars($item['precoTexto']) ?></div>
                                    <div class="admin-peca-venda">
                                        Venda: <strong><?= formatarPreco($precoVendaItem) ?></strong>
                                        <span>· lucro <?= formatarPreco($lucroItem) ?> (<?= number_format($margemEfetivaItem, 2, ',', '.') ?>%)</span>
                                    </div>
                                </div>
                                <div class="loja-badge <?= $item['loja']['tag_class'] ?>">
                                    <span><?= $item['loja']['icone'] ?></span>
                                    <span><?= $item['loja']['nome'] ?></span>
                                </div>
                            </a>

                            <?php if (!empty($dados['alternativas'])): ?>
                                <details class="alternativas">
                                    <summary>Ver outras <?= count($dados['alternativas']) ?> ofertas mais baratas</summary>
                                    <?php foreach ($dados['alternativas'] as $alt): ?>
                                        <a class="alternativa" href="<?= htmlspecialchars($alt['link']) ?>" target="_blank" rel="noopener">
                                            <span class="alt-titulo"><?= htmlspecialchars($alt['titulo']) ?></span>
                                            <span class="alt-preco"><?= htmlspecialchars($alt['precoTexto']) ?></span>
                                            <span class="loja-badge <?= $alt['loja']['tag_class'] ?>"><?= $alt['loja']['icone'] ?> <?= $alt['loja']['nome'] ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </details>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="sem-oferta">
                                Nenhuma oferta encontrada para <b><?= htmlspecialchars($dados['rotulo']) ?></b> nos dados coletados.
                                <?php if (!empty($dados['aviso'])): ?>
                                    <div class="dica"><?= htmlspecialchars($dados['aviso']) ?></div>
                                <?php elseif (empty($dados['arquivos'])): ?>
                                    <div class="dica">Arquivo esperado: <code><?= htmlspecialchars(implode(' | ', array_map('basename', $dados['padroes']))) ?></code></div>
                                <?php else: ?>
                                    <div class="dica">Os arquivos existem, mas estão vazios: <code><?= htmlspecialchars(implode(', ', $dados['arquivos'])) ?></code></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($dados['melhor'] !== null): $item = $dados['melhor']; ?>
                            <div class="cliente-peca">
                                <span class="cliente-peca-check">✓</span>
                                <span><?= htmlspecialchars($item['titulo']) ?></span>
                                <span class="cliente-peca-preco"><?= formatarPreco(precoPecaCliente((float) $item['precoNum'])) ?></span>
                            </div>
                        <?php else: ?>
                            <div class="sem-oferta">
                                Este componente está indisponível na configuração escolhida. Fale com a gente pelo WhatsApp para ajustarmos.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-placeholder">
                <h2>Nenhuma cotação realizada</h2>
                <p style="margin-top:0.5rem;">Selecione os componentes no menu lateral à esquerda e clique em <b>"Cotar Peças"</b>.</p>
            </div>
        <?php endif; ?>

    </main>

</div>

<!-- RODAPÉ -->
<?php require __DIR__ . '/includes/footer.php'; ?>

<script>
// Dados para CPU
const dadosCpu = {
    intel: {
        linhas: [
            { val: "i3", label: "Core i3" },
            { val: "i5", label: "Core i5" },
            { val: "i7", label: "Core i7" },
            { val: "i9", label: "Core i9" }
        ],
        geracoes: [
            { val: "6ª", label: "6ª Geração" },
            { val: "7ª", label: "7ª Geração" },
            { val: "8ª", label: "8ª Geração" },
            { val: "9ª", label: "9ª Geração" },
            { val: "10ª", label: "10ª Geração" },
            { val: "11ª", label: "11ª Geração" },
            { val: "12ª", label: "12ª Geração" },
            { val: "13ª", label: "13ª Geração" },
            { val: "14ª", label: "14ª Geração" }
        ]
    },
    amd: {
        linhas: [
            { val: "ryzen 3", label: "Ryzen 3" },
            { val: "ryzen 5", label: "Ryzen 5" },
            { val: "ryzen 7", label: "Ryzen 7" },
            { val: "ryzen 9", label: "Ryzen 9" }
        ],
        geracoes: [
            { val: "3000", label: "Série 3000" },
            { val: "4000", label: "Série 4000" },
            { val: "5000", label: "Série 5000" },
            { val: "7000", label: "Série 7000" },
            { val: "8000", label: "Série 8000" },
            { val: "9000", label: "Série 9000" }
        ]
    }
};

// Dados para GPU
const dadosGpu = {
    nvidia: {
        series: [
            { val: "gtx", label: "Série GTX (Entrada / Reposição)" },
            { val: "rtx 20", label: "Série RTX 20 (Legado)" },
            { val: "rtx 30", label: "Série RTX 30 (Custo-Benefício)" },
            { val: "rtx 40", label: "Série RTX 40 (Geração Recente)" },
            { val: "rtx 50", label: "Série RTX 50 (Nova Geração / Topo)" }
        ],
        modelos: {
            gtx: [
                { val: "gtx 1050", label: "GTX 1050 2GB/4GB" },
                { val: "gtx 1060", label: "GTX 1060 3GB/6GB" },
                { val: "gtx 1070", label: "GTX 1070 8GB" },
                { val: "gtx 1650", label: "GTX 1650 4GB" },
                { val: "gtx 1660", label: "GTX 1660 6GB" },
                { val: "gtx 1660 super", label: "GTX 1660 Super 6GB" },
                { val: "gtx 1660 ti", label: "GTX 1660 Ti 6GB" }
            ],
            "rtx 20": [
                { val: "rtx 2050", label: "RTX 2050 4GB" },
                { val: "rtx 2060", label: "RTX 2060 6GB/12GB" },
                { val: "rtx 2060 super", label: "RTX 2060 Super 8GB" },
                { val: "rtx 2070", label: "RTX 2070 8GB" },
                { val: "rtx 2080", label: "RTX 2080 8GB" }
            ],
            "rtx 30": [
                { val: "rtx 3050", label: "RTX 3050 6GB/8GB" },
                { val: "rtx 3060", label: "RTX 3060 12GB" },
                { val: "rtx 3060 ti", label: "RTX 3060 Ti 8GB" },
                { val: "rtx 3070", label: "RTX 3070 8GB" },
                { val: "rtx 3070 ti", label: "RTX 3070 Ti 8GB" },
                { val: "rtx 3080", label: "RTX 3080 10GB/12GB" },
                { val: "rtx 3090", label: "RTX 3090 24GB" }
            ],
            "rtx 40": [
                { val: "rtx 4050", label: "RTX 4050 6GB" },
                { val: "rtx 4060", label: "RTX 4060 8GB" },
                { val: "rtx 4060 ti", label: "RTX 4060 Ti 8GB" },
                { val: "rtx 4060 ti 16gb", label: "RTX 4060 Ti 16GB" },
                { val: "rtx 4070", label: "RTX 4070 12GB" },
                { val: "rtx 4070 super", label: "RTX 4070 Super 12GB" },
                { val: "rtx 4070 ti", label: "RTX 4070 Ti 12GB" },
                { val: "rtx 4070 ti super", label: "RTX 4070 Ti Super 16GB" },
                { val: "rtx 4080", label: "RTX 4080 16GB" },
                { val: "rtx 4080 super", label: "RTX 4080 Super 16GB" },
                { val: "rtx 4090", label: "RTX 4090 24GB" }
            ],
            "rtx 50": [
                { val: "rtx 5050", label: "RTX 5050 8GB" },
                { val: "rtx 5060", label: "RTX 5060 8GB" },
                { val: "rtx 5070", label: "RTX 5070 12GB" },
                { val: "rtx 5070 ti", label: "RTX 5070 Ti 16GB" },
                { val: "rtx 5080", label: "RTX 5080 16GB" },
                { val: "rtx 5090", label: "RTX 5090 32GB" }
            ]
        }
    },
    amd: {
        series: [
            { val: "rx 500", label: "Série RX 500 (Básica / Usada)" },
            { val: "rx 5000", label: "Série RX 5000 (Legado)" },
            { val: "rx 6000", label: "Série RX 6000 (Custo-Benefício)" },
            { val: "rx 7000", label: "Série RX 7000 (Geração Atual)" },
            { val: "rx 9000", label: "Série RX 9000 (Nova Geração)" }
        ],
        modelos: {
            "rx 500": [
                { val: "rx 570", label: "RX 570 4GB/8GB" },
                { val: "rx 580", label: "RX 580 8GB" },
                { val: "rx 590", label: "RX 590 8GB" }
            ],
            "rx 5000": [
                { val: "rx 5300", label: "RX 5300 3GB" },
                { val: "rx 5500 xt", label: "RX 5500 XT 4GB/8GB" },
                { val: "rx 5600 xt", label: "RX 5600 XT 6GB" },
                { val: "rx 5700", label: "RX 5700 8GB" },
                { val: "rx 5700 xt", label: "RX 5700 XT 8GB" }
            ],
            "rx 6000": [
                { val: "rx 6300", label: "RX 6300 2GB" },
                { val: "rx 6400", label: "RX 6400 4GB" },
                { val: "rx 6500 xt", label: "RX 6500 XT 4GB" },
                { val: "rx 6600", label: "RX 6600 8GB" },
                { val: "rx 6600 xt", label: "RX 6600 XT 8GB" },
                { val: "rx 6650 xt", label: "RX 6650 XT 8GB" },
                { val: "rx 6700", label: "RX 6700 10GB" },
                { val: "rx 6700 xt", label: "RX 6700 XT 12GB" },
                { val: "rx 6750 xt", label: "RX 6750 XT 12GB" },
                { val: "rx 6800", label: "RX 6800 16GB" },
                { val: "rx 6800 xt", label: "RX 6800 XT 16GB" },
                { val: "rx 6900 xt", label: "RX 6900 XT 16GB" },
                { val: "rx 6950 xt", label: "RX 6950 XT 16GB" }
            ],
            "rx 7000": [
                { val: "rx 7400", label: "RX 7400 8GB" },
                { val: "rx 7600", label: "RX 7600 8GB" },
                { val: "rx 7650", label: "RX 7650 GRE 8GB" },
                { val: "rx 7700", label: "RX 7700 12GB" },
                { val: "rx 7800 xt", label: "RX 7800 XT 16GB" },
                { val: "rx 7900 gre", label: "RX 7900 GRE 16GB" },
                { val: "rx 7900 xt", label: "RX 7900 XT 20GB" },
                { val: "rx 7900 xtx", label: "RX 7900 XTX 24GB" }
            ],
            "rx 9000": [
                { val: "rx 9050", label: "RX 9050 8GB" },
                { val: "rx 9060", label: "RX 9060 8GB/16GB" },
                { val: "rx 9070", label: "RX 9070 12GB/16GB" }
            ]
        }
    },
    intel: {
        series: [
            { val: "arc a", label: "Intel Arc Série A (1ª Geração)" },
            { val: "arc b", label: "Intel Arc Battlemage (Nova Geração)" }
        ],
        modelos: {
            "arc a": [
                { val: "arc a380", label: "Arc A380 6GB" },
                { val: "arc a580", label: "Arc A580 8GB" },
                { val: "arc a750", label: "Arc A750 8GB" },
                { val: "arc a770", label: "Arc A770 16GB" }
            ],
            "arc b": [
                { val: "arc b570", label: "Arc B570 10GB" },
                { val: "arc b580", label: "Arc B580 12GB" }
            ]
        }
    }
};

function toggleAccordion(btn) {
    btn.classList.toggle('active');
    const content = btn.nextElementSibling;
    content.classList.toggle('open');
}

// Cascata CPU
const selectCpuMarca = document.getElementById('cpu_marca');
const selectCpuLinha = document.getElementById('cpu_linha');
const selectCpuGen   = document.getElementById('cpu_gen');

function atualizarCpu(linhaSel, genSel) {
    const marca = selectCpuMarca.value;
    selectCpuLinha.innerHTML = '<option value="">Linha (Selecionar)</option>';
    selectCpuGen.innerHTML = '<option value="">Geração (Selecionar)</option>';

    if (dadosCpu[marca]) {
        dadosCpu[marca].linhas.forEach(l => {
            const opt = document.createElement('option');
            opt.value = l.val;
            opt.textContent = l.label;
            if (linhaSel && linhaSel === l.val) opt.selected = true;
            selectCpuLinha.appendChild(opt);
        });

        dadosCpu[marca].geracoes.forEach(g => {
            const opt = document.createElement('option');
            opt.value = g.val;
            opt.textContent = g.label;
            if (genSel && genSel === g.val) opt.selected = true;
            selectCpuGen.appendChild(opt);
        });
    }
}
selectCpuMarca.addEventListener('change', () => atualizarCpu(null, null));

// Cascata GPU
const selectGpuMarca  = document.getElementById('gpu_marca');
const selectGpuSerie  = document.getElementById('gpu_serie');
const selectGpuModelo = document.getElementById('gpu_modelo');

function atualizarGpuSeries(serieSel, modeloSel) {
    const marca = selectGpuMarca.value;
    selectGpuSerie.innerHTML = '<option value="">Série...</option>';
    selectGpuModelo.innerHTML = '<option value="">Modelo Específico...</option>';

    if (dadosGpu[marca]) {
        dadosGpu[marca].series.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.val;
            opt.textContent = s.label;
            if (serieSel && serieSel === s.val) opt.selected = true;
            selectGpuSerie.appendChild(opt);
        });
        if (serieSel) atualizarGpuModelos(serieSel, modeloSel);
    }
}

function atualizarGpuModelos(serie, modeloSel) {
    const marca = selectGpuMarca.value;
    selectGpuModelo.innerHTML = '<option value="">Modelo Específico...</option>';

    if (dadosGpu[marca] && dadosGpu[marca].modelos[serie]) {
        dadosGpu[marca].modelos[serie].forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.val;
            opt.textContent = m.label;
            if (modeloSel && modeloSel === m.val) opt.selected = true;
            selectGpuModelo.appendChild(opt);
        });
    }
}

selectGpuMarca.addEventListener('change', () => atualizarGpuSeries(null, null));
selectGpuSerie.addEventListener('change', (e) => atualizarGpuModelos(e.target.value, null));

// Reseta os selects dependentes quando o select de cima muda
function resetSelect(id) {
    const el = document.getElementById(id);
    if (el && el.options.length > 0) el.selectedIndex = 0;
}

// CPU: linha -> geração
selectCpuLinha.addEventListener('change', () => resetSelect('cpu_gen'));

// RAM: equipamento -> capacidade e padrão DDR | capacidade -> padrão DDR
document.getElementById('ram_tipo').addEventListener('change', () => {
    resetSelect('ram_cap');
    resetSelect('ram_ddr');
});
document.getElementById('ram_cap').addEventListener('change', () => resetSelect('ram_ddr'));

// Armazenamento: tipo -> capacidade
document.getElementById('disco_tipo').addEventListener('change', () => resetSelect('disco_cap'));

// Cooler: tipo -> estilo
document.getElementById('cooler_tipo').addEventListener('change', () => resetSelect('cooler_estilo'));

// Mantém as sanfonas e selects preenchidos após o submit
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    
    // CPU
    const marcaCpu = urlParams.get('cpu_marca');
    if (marcaCpu) {
        atualizarCpu(urlParams.get('cpu_linha'), urlParams.get('cpu_gen'));
        document.getElementById('cpu_marca').closest('.accordion-content').classList.add('open');
        document.getElementById('cpu_marca').closest('.accordion-item').querySelector('.accordion-btn').classList.add('active');
    }

    // RAM
    if (urlParams.get('ram_cap') || urlParams.get('ram_ddr')) {
        document.getElementById('ram_cap').closest('.accordion-content').classList.add('open');
        document.getElementById('ram_cap').closest('.accordion-item').querySelector('.accordion-btn').classList.add('active');
    }

    // Armazenamento
    if (urlParams.get('disco_tipo') || urlParams.get('disco_cap')) {
        document.getElementById('disco_tipo').closest('.accordion-content').classList.add('open');
        document.getElementById('disco_tipo').closest('.accordion-item').querySelector('.accordion-btn').classList.add('active');
    }

    // GPU
    const marcaGpu = urlParams.get('gpu_marca');
    if (marcaGpu) {
        atualizarGpuSeries(urlParams.get('gpu_serie'), urlParams.get('gpu_modelo'));
        document.getElementById('gpu_marca').closest('.accordion-content').classList.add('open');
        document.getElementById('gpu_marca').closest('.accordion-item').querySelector('.accordion-btn').classList.add('active');
    }

    // Mantém abertos os grupos que têm algum filtro preenchido
    // (ex.: só o tipo da RAM ou só o tipo do armazenamento)
    document.querySelectorAll('.accordion-item').forEach(item => {
        const temFiltro = Array.from(item.querySelectorAll('select')).some(s => s.value !== '');
        if (temFiltro) {
            item.querySelector('.accordion-content').classList.add('open');
            item.querySelector('.accordion-btn').classList.add('active');
        }
    });
});

// Best-effort: o link abre em nova guia e tentamos devolver o foco para esta.
// Quem decide é o navegador; em alguns ele ignora essa chamada.
document.querySelectorAll('.social-link').forEach(link => {
    link.addEventListener('click', () => {
        setTimeout(() => { try { window.focus(); } catch (err) {} }, 80);
    });
});
</script>

</body>
</html>