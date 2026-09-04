<?php
set_time_limit(300);

$resultadosPorPeca = [];
$totalSoma = 0.0;
$erroTerminal = '';

function converterPrecoParaFloat($precoStr) {
    $limpo = preg_replace('/[^\d,\.]/', '', $precoStr);

    if (strpos($limpo, ',') !== false && strpos($limpo, '.') !== false) {
        $limpo = str_replace('.', '', $limpo);
        $limpo = str_replace(',', '.', $limpo);
    } elseif (strpos($limpo, ',') !== false) {
        $limpo = str_replace(',', '.', $limpo);
    }

    return floatval($limpo);
}

function identificarLoja($url) {
    $urlLower = strtolower($url);
    if (strpos($urlLower, 'kabum.com.br') !== false) {
        return ['nome' => 'KaBuM!', 'tag_class' => 'loja-kabum', 'icone' => '🟧'];
    } elseif (strpos($urlLower, 'amazon.com') !== false) {
        return ['nome' => 'Amazon', 'tag_class' => 'loja-amazon', 'icone' => '📦'];
    } elseif (strpos($urlLower, 'terabyteshop.com.br') !== false) {
        return ['nome' => 'Terabyte', 'tag_class' => 'loja-terabyte', 'icone' => '⚡'];
    } elseif (strpos($urlLower, 'pichau.com.br') !== false) {
        return ['nome' => 'Pichau', 'tag_class' => 'loja-pichau', 'icone' => '🔴'];
    }
    return ['nome' => 'Loja Parceira', 'tag_class' => 'loja-padrao', 'icone' => '🛒'];
}

$perfilBusca = $_GET['perfil_busca'] ?? 'barato';
$modoSetup = isset($_GET['montar_setup']) && $_GET['montar_setup'] === '1';

// 1. Processador
$termoCpu = '';
$cpuMarca = $_GET['cpu_marca'] ?? '';
$cpuLinha = $_GET['cpu_linha'] ?? '';
$cpuGen   = $_GET['cpu_gen'] ?? '';
$cpuVideo = $_GET['video_integrado'] ?? '';

if (!empty($cpuMarca)) {
    $termoCpu = "processador " . $cpuMarca;
    if (!empty($cpuLinha)) $termoCpu .= " " . $cpuLinha;
    if (!empty($cpuGen)) {
        $termoCpu .= " " . $cpuGen;
    } elseif ($perfilBusca === 'desempenho') {
        $termoCpu .= ($cpuMarca === 'intel') ? ' 14ª geracao' : ' 7000';
    }

    if ($cpuVideo === 'sim') {
        if ($cpuMarca === 'amd') {
            $termoCpu .= " g";
        }
    } elseif ($cpuVideo === 'nao') {
        if ($cpuMarca === 'intel') {
            $termoCpu .= " f";
        }
    }
}

// 2. Memória RAM
$termoRam = '';
$ramCap = $_GET['ram_cap'] ?? '';
$ramDdr = $_GET['ram_ddr'] ?? '';

if (!empty($ramCap) || !empty($ramDdr)) {
    $termoRam = "memoria ram";
    if (!empty($ramCap)) $termoRam .= " " . $ramCap;
    if (!empty($ramDdr)) {
        $termoRam .= " " . $ramDdr;
    } elseif ($perfilBusca === 'desempenho') {
        $termoRam .= " ddr5";
    } elseif ($perfilBusca === 'barato') {
        $termoRam .= " ddr4";
    }
}

// 3. Armazenamento
$termoArmazenamento = '';
$discoTipo = $_GET['disco_tipo'] ?? '';
$discoCap  = $_GET['disco_cap'] ?? '';

if (!empty($discoTipo) || !empty($discoCap)) {
    $termoArmazenamento = $discoTipo ?: 'ssd nvme';
    if (!empty($discoCap)) $termoArmazenamento .= " " . $discoCap;
}

// 4. Placa de Vídeo
$termoGpu = '';
$gpuMarca  = $_GET['gpu_marca'] ?? '';
$gpuSerie  = $_GET['gpu_serie'] ?? '';
$gpuModelo = $_GET['gpu_modelo'] ?? '';

if (!empty($gpuMarca)) {
    if (!empty($gpuModelo)) {
        $termoGpu = $gpuModelo;
    } elseif (!empty($gpuSerie)) {
        $termoGpu = $gpuSerie;
    } else {
        $termoGpu = ($gpuMarca === 'nvidia') ? 'placa de video rtx' : 'placa de video radeon rx';
    }
}

// 5. Montagem do array de cotação
$pecasParaCotar = [];

if (!empty($termoCpu)) $pecasParaCotar['cpu'] = ['rotulo' => 'Processador', 'termo' => $termoCpu];
if (!empty($termoRam)) $pecasParaCotar['ram'] = ['rotulo' => 'Memória RAM', 'termo' => $termoRam];
if (!empty($termoArmazenamento)) $pecasParaCotar['armazenamento'] = ['rotulo' => 'Armazenamento', 'termo' => $termoArmazenamento];
if (!empty($termoGpu)) $pecasParaCotar['gpu'] = ['rotulo' => 'Placa de Vídeo', 'termo' => $termoGpu];

if ($modoSetup) {
    if (!isset($pecasParaCotar['cpu'])) {
        $pecasParaCotar['cpu'] = [
            'rotulo' => 'Processador',
            'termo'  => ($perfilBusca === 'desempenho') ? 'processador ryzen 7 7700' : 'processador ryzen 5 5600gt'
        ];
    }
    if (!isset($pecasParaCotar['placa_mae'])) {
        $isIntel = (isset($pecasParaCotar['cpu']) && strpos(strtolower($pecasParaCotar['cpu']['termo']), 'intel') !== false);
        $termoPlaca = $isIntel ? 'placa mae lga 1700' : (($perfilBusca === 'desempenho') ? 'placa mae b650 am5' : 'placa mae b550 am4');
        $pecasParaCotar['placa_mae'] = ['rotulo' => 'Placa-Mãe', 'termo' => $termoPlaca];
    }
    if (!isset($pecasParaCotar['ram'])) {
        $pecasParaCotar['ram'] = [
            'rotulo' => 'Memória RAM',
            'termo'  => ($perfilBusca === 'desempenho') ? 'memoria ram 32gb ddr5' : 'memoria ram 16gb ddr4'
        ];
    }
    if (!isset($pecasParaCotar['armazenamento'])) {
        $pecasParaCotar['armazenamento'] = [
            'rotulo' => 'Armazenamento',
            'termo'  => ($perfilBusca === 'desempenho') ? 'ssd nvme 1tb' : 'ssd nvme 500gb'
        ];
    }
    if (!isset($pecasParaCotar['fonte'])) {
        $pecasParaCotar['fonte'] = [
            'rotulo' => 'Fonte de Alimentação',
            'termo'  => ($perfilBusca === 'desempenho' || isset($pecasParaCotar['gpu'])) ? 'fonte 650w 80 plus' : 'fonte 500w 80 plus'
        ];
    }
    if (!isset($pecasParaCotar['gabinete'])) {
        $pecasParaCotar['gabinete'] = [
            'rotulo' => 'Gabinete',
            'termo'  => 'gabinete gamer com fans'
        ];
    }
}

// 6. Leitura direta das tabelas CSV
if (!empty($pecasParaCotar)) {
    $raiz_projeto = dirname(dirname(__DIR__));
    $pasta_csvs   = $raiz_projeto . DIRECTORY_SEPARATOR . 'csvs' . DIRECTORY_SEPARATOR;

    foreach ($pecasParaCotar as $chave => $dados) {
        $termo  = $dados['termo'];
        $rotulo = $dados['rotulo'];
        $arquivosParaLer = [];

        if ($chave === 'ram') {
            $capacidade = strtolower($_GET['ram_cap'] ?? '8gb');
            $geracao    = strtolower($_GET['ram_ddr'] ?? '');

            if (!empty($geracao)) {
                $arquivosParaLer[] = $pasta_csvs . "memoria_{$geracao}_{$capacidade}.csv";
            } else {
                $arquivosParaLer = glob($pasta_csvs . "memoria_ddr*_{$capacidade}.csv") ?: [];
            }
        } else {
            $arquivosParaLer = [
                $raiz_projeto . DIRECTORY_SEPARATOR . 'Produtos Ordenados.csv',
                $raiz_projeto . DIRECTORY_SEPARATOR . 'preços.csv'
            ];
        }

        $itensDestaPeca = [];

        foreach ($arquivosParaLer as $csv_file) {
            if (file_exists($csv_file) && filesize($csv_file) > 0) {
                if (($handle = fopen($csv_file, "r")) !== FALSE) {
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        if (count($data) >= 3) {
                            $link       = trim($data[2]);
                            $precoTexto = trim($data[1]);
                            $precoNum   = converterPrecoParaFloat($precoTexto);
                            $titulo     = mb_convert_encoding(trim($data[0]), 'UTF-8', 'UTF-8');

                            if ($precoNum > 0 && !empty($link)) {
                                $itensDestaPeca[] = [
                                    'titulo'     => $titulo,
                                    'precoTexto' => (strpos($precoTexto, 'R$') === false ? 'R$ ' : '') . $precoTexto,
                                    'precoNum'   => $precoNum,
                                    'link'       => $link,
                                    'loja'       => identificarLoja($link)
                                ];
                            }
                        }
                    }
                    fclose($handle);
                }
            }
        }

        if (!empty($itensDestaPeca)) {
            usort($itensDestaPeca, function ($a, $b) use ($perfilBusca) {
                return ($perfilBusca === 'desempenho') ? ($b['precoNum'] <=> $a['precoNum']) : ($a['precoNum'] <=> $b['precoNum']);
            });

            $melhorOpcao = $itensDestaPeca[0];
            $totalSoma += $melhorOpcao['precoNum'];

            $resultadosPorPeca[$chave] = [
                'rotulo' => $rotulo,
                'termo'  => $termo,
                'item'   => $melhorOpcao
            ];
        } else {
            $resultadosPorPeca[$chave] = [
                'rotulo' => $rotulo,
                'termo'  => $termo,
                'item'   => null
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <link rel="stylesheet" href="../css/style.css">
    <meta charset="UTF-8">
    <title>Consultoria TI - Painel de Cotação</title>
</head>
<body>

<div class="dashboard-container">
    
    <!-- BARRA LATERAL (FILTROS) -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>Consultoria TI</h2>
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
                            <option value="">Capacidade (Selecionar)</option>
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

            </div>

            <label class="checkbox-container">
                <input type="checkbox" name="montar_setup" id="montarSetupCheckbox" value="1" <?= $modoSetup ? 'checked' : '' ?>>
                <span>Montar Setup Completo</span>
            </label>

            <button type="submit" class="btn-buscar">Cotar Peças</button>
        </form>
    </aside>

    <!-- ÁREA PRINCIPAL -->
    <main class="main-content">
        
        <?php if (!empty($erroTerminal)): ?>
            <pre style="background:#1e1e2e; color:#f38ba8; padding:1.2rem; border-radius:8px; border:1px solid #f38ba8; overflow-x:auto; font-size:0.85rem;"><?= htmlspecialchars($erroTerminal) ?></pre>
        <?php endif; ?>

        <?php if ($totalSoma > 0): ?>
            <div class="summary-banner">
                <div>
                    <div class="summary-title">
                        <?= $modoSetup ? 'Orçamento Estimado da Máquina Completa' : (count($resultadosPorPeca) > 1 ? 'Valor Total das Peças Selecionadas' : 'Melhor Oferta Encontrada') ?>
                    </div>
                    <div class="summary-subtitle">
                        Soma automática com o melhor valor unitário de cada componente.
                    </div>
                </div>
                <div class="summary-price">
                    R$ <?= number_format($totalSoma, 2, ',', '.') ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($resultadosPorPeca)): ?>
            <?php foreach ($resultadosPorPeca as $chave => $dados): ?>
                <?php if (!empty($dados['item'])): 
                    $item = $dados['item'];
                ?>
                    <div class="categoria-bloco">
                        <div class="categoria-label">
                            <?= htmlspecialchars($dados['rotulo']) ?> — Termo: "<?= htmlspecialchars($dados['termo']) ?>"
                        </div>
                        
                        <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank" class="product-card">
                            <div class="product-info">
                                <div class="product-title"><?= htmlspecialchars($item['titulo']) ?></div>
                                <div class="product-price"><?= htmlspecialchars($item['precoTexto']) ?></div>
                            </div>
                            <div class="loja-badge <?= $item['loja']['tag_class'] ?>">
                                <span><?= $item['loja']['icone'] ?></span>
                                <span><?= $item['loja']['nome'] ?></span>
                            </div>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="categoria-bloco" style="color:var(--text-muted);">
                        Nenhum produto válido encontrado para <b><?= htmlspecialchars($dados['rotulo']) ?></b>.
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-placeholder">
                <h2>Nenhuma cotação realizada</h2>
                <p style="margin-top:0.5rem;">Selecione os componentes no menu lateral à esquerda e clique em <b>"Cotar Peças"</b>.</p>
            </div>
        <?php endif; ?>

    </main>

</div>

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
            { val: "8000", label: "Série 8000" }
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
                { val: "gtx 1650", label: "GTX 1650 4GB" },
                { val: "gtx 1660", label: "GTX 1660 6GB" },
                { val: "gtx 1660 super", label: "GTX 1660 Super 6GB" },
                { val: "gtx 1660 ti", label: "GTX 1660 Ti 6GB" }
            ],
            "rtx 20": [
                { val: "rtx 2060", label: "RTX 2060 6GB/12GB" },
                { val: "rtx 2060 super", label: "RTX 2060 Super 8GB" }
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
            { val: "rx 6000", label: "Série RX 6000 (Custo-Benefício)" },
            { val: "rx 7000", label: "Série RX 7000 (Geração Atual)" },
            { val: "rx 8000", label: "Série RX 8000 (Nova Geração)" }
        ],
        modelos: {
            "rx 500": [
                { val: "rx 550", label: "RX 550 4GB" },
                { val: "rx 580", label: "RX 580 8GB" },
                { val: "rx 590", label: "RX 590 8GB" }
            ],
            "rx 6000": [
                { val: "rx 6400", label: "RX 6400 4GB" },
                { val: "rx 6500 xt", label: "RX 6500 XT 4GB" },
                { val: "rx 6600", label: "RX 6600 8GB" },
                { val: "rx 6600 xt", label: "RX 6600 XT 8GB" },
                { val: "rx 6650 xt", label: "RX 6650 XT 8GB" },
                { val: "rx 6700 xt", label: "RX 6700 XT 12GB" },
                { val: "rx 6750 xt", label: "RX 6750 XT 12GB" },
                { val: "rx 6800 xt", label: "RX 6800 XT 16GB" },
                { val: "rx 6900 xt", label: "RX 6900 XT 16GB" }
            ],
            "rx 7000": [
                { val: "rx 7600", label: "RX 7600 8GB" },
                { val: "rx 7600 xt", label: "RX 7600 XT 16GB" },
                { val: "rx 7700 xt", label: "RX 7700 XT 12GB" },
                { val: "rx 7800 xt", label: "RX 7800 XT 16GB" },
                { val: "rx 7900 gre", label: "RX 7900 GRE 16GB" },
                { val: "rx 7900 xt", label: "RX 7900 XT 20GB" },
                { val: "rx 7900 xtx", label: "RX 7900 XTX 24GB" }
            ],
            "rx 8000": [
                { val: "rx 8600 xt", label: "RX 8600 XT" },
                { val: "rx 8700 xt", label: "RX 8700 XT" },
                { val: "rx 8800 xt", label: "RX 8800 XT" }
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

// Auto-check setup completo
const checkboxSetup = document.getElementById('montarSetupCheckbox');
const seletores = document.querySelectorAll('.seletor-peca');

function checarMultiplos() {
    let preenchidos = 0;
    seletores.forEach(s => {
        if (s.value !== '') preenchidos++;
    });
    if (checkboxSetup) {
        checkboxSetup.checked = preenchidos >= 2;
    }
}

seletores.forEach(s => s.addEventListener('change', checarMultiplos));

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
});
</script>

</body>
</html>