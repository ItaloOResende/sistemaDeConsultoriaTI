<?php
set_time_limit(300);

$resultadosPorPeca = [];
$totalSoma = 0.0;
$erroTerminal = '';

function converterPrecoParaFloat($precoStr) {
    $limpo = preg_replace('/[^\d,]/', '', $precoStr);
    $limpo = str_replace(',', '.', $limpo);
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

if (!empty($cpuMarca)) {
    $termoCpu = "processador " . $cpuMarca;
    if (!empty($cpuLinha)) $termoCpu .= " " . $cpuLinha;
    if (!empty($cpuGen)) {
        $termoCpu .= " " . $cpuGen;
    } elseif ($perfilBusca === 'desempenho') {
        $termoCpu .= ($cpuMarca === 'intel') ? ' 14ª geracao' : ' 7000';
    }
}

// 2. Memória RAM (Capacidade primeiro, DDR depois)
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

// 4. Placa de Vídeo (Marca -> Série -> Modelo)
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

// Se ativou modo SETUP COMPLETO, preenche os itens restantes automaticamente
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

// 6. Execução dos scripts Python
if (!empty($pecasParaCotar)) {
    $raiz_projeto = dirname(__DIR__);

    foreach ($pecasParaCotar as $chave => $dados) {
        $termo = $dados['termo'];
        $rotulo = $dados['rotulo'];
        $termoEscapado = escapeshellarg($termo);

        $csv_ordenado = $raiz_projeto . DIRECTORY_SEPARATOR . 'Produtos Ordenados.csv';
        $csv_bruto = $raiz_projeto . DIRECTORY_SEPARATOR . 'preços.csv';

        if (file_exists($csv_ordenado)) @file_put_contents($csv_ordenado, '');
        if (file_exists($csv_bruto)) @file_put_contents($csv_bruto, '');

        $cmd = "cd /d \"$raiz_projeto\" && python -X utf8 buscaGeral.py $termoEscapado 2>&1";
        $output = shell_exec($cmd);

        $csv_file = (file_exists($csv_ordenado) && filesize($csv_ordenado) > 0) ? $csv_ordenado : ((file_exists($csv_bruto) && filesize($csv_bruto) > 0) ? $csv_bruto : null);
        $itensDestaPeca = [];

        if ($csv_file) {
            if (($handle = fopen($csv_file, "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) >= 3) {
                        $link = trim($data[2]);
                        $precoTexto = trim($data[1]);
                        $precoNum = converterPrecoParaFloat($precoTexto);
                        $titulo = mb_convert_encoding(trim($data[0]), 'UTF-8', 'UTF-8');

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

            usort($itensDestaPeca, function ($a, $b) use ($perfilBusca) {
                return ($perfilBusca === 'desempenho') ? ($b['precoNum'] <=> $a['precoNum']) : ($a['precoNum'] <=> $b['precoNum']);
            });
        } else {
            $erroTerminal .= "Falha na busca de [$rotulo]: " . ($output ?: "Nenhum dado retornado") . "\n";
        }

        if (!empty($itensDestaPeca)) {
            $totalSoma += $itensDestaPeca[0]['precoNum'];
        }

        $resultadosPorPeca[$chave] = [
            'rotulo' => $rotulo,
            'termo'  => $termo,
            'itens'  => $itensDestaPeca
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Consultoria TI - Painel de Cotação</title>
    <style>
        :root {
            --bg-main: #0b1120;
            --bg-card: #151f32;
            --bg-input: #090e17;
            --border: #24324a;
            --primary: #38bdf8;
            --primary-hover: #0284c7;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent-green: #34d399;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            justify-content: center;
        }

        .dashboard-container {
            width: 100%;
            max-width: 1600px;
            display: flex;
            gap: 2rem;
            padding: 2rem;
            align-items: flex-start;
        }

        /* SIDEBAR FIXA (ESQUERDA) */
        .sidebar {
            width: 380px;
            min-width: 380px;
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            position: sticky;
            top: 2rem;
            max-height: calc(100vh - 4rem);
            overflow-y: auto;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
        }

        .sidebar-header {
            margin-bottom: 1.25rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1rem;
        }

        .sidebar-header h2 {
            font-size: 1.35rem;
            color: var(--primary);
            font-weight: 800;
        }

        .sidebar-header p {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.2rem;
        }

        .perfil-box {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
        }

        .perfil-box span {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .radio-group {
            display: flex;
            justify-content: space-between;
        }

        .radio-label {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
        }

        .radio-label input[type="radio"] {
            accent-color: var(--primary);
        }

        /* ACCORDIONS (SANFONAS) */
        .accordion-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .accordion-item {
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            transition: border-color 0.2s;
        }

        .accordion-item:focus-within {
            border-color: var(--primary);
        }

        .accordion-btn {
            width: 100%;
            background: transparent;
            border: none;
            color: var(--text-main);
            padding: 0.85rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-align: left;
        }

        .accordion-btn span.seta {
            transition: transform 0.2s;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .accordion-btn.active span.seta {
            transform: rotate(180deg);
        }

        .accordion-content {
            display: none;
            padding: 0.75rem 1rem 1rem 1rem;
            border-top: 1px solid var(--border);
            flex-direction: column;
            gap: 0.6rem;
        }

        .accordion-content.open {
            display: flex;
        }

        select {
            width: 100%;
            padding: 0.65rem 0.75rem;
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text-main);
            font-size: 0.88rem;
            outline: none;
            cursor: pointer;
        }

        select:focus {
            border-color: var(--primary);
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            cursor: pointer;
            user-select: none;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
        }

        .checkbox-container input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }

        .btn-buscar {
            width: 100%;
            padding: 0.85rem;
            background: var(--primary);
            border: none;
            border-radius: 8px;
            color: #000;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-buscar:hover {
            background: var(--primary-hover);
        }

        /* ÁREA DE RESULTADOS (DIREITA) */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .summary-banner {
            background: linear-gradient(135deg, #151f32, #1e293b);
            border: 1px solid var(--primary);
            border-radius: 12px;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .summary-title {
            font-size: 1.25rem;
            font-weight: 700;
        }

        .summary-subtitle {
            font-size: 0.88rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        .summary-price {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--accent-green);
        }

        .categoria-bloco {
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
        }

        .categoria-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.75rem;
            margin-bottom: 1.2rem;
        }

        .categoria-header h3 {
            font-size: 1.15rem;
            color: var(--primary);
        }

        .product-grid {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .product-card {
            background-color: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
            text-decoration: none;
            color: inherit;
            transition: transform 0.15s, border-color 0.15s;
        }

        .product-card:hover {
            border-color: var(--primary);
            transform: translateX(4px);
        }

        .product-info {
            flex: 1;
        }

        .product-title {
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 0.35rem;
            line-height: 1.35;
        }

        .product-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--accent-green);
        }

        .loja-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.85rem;
            font-weight: 700;
            padding: 0.45rem 0.85rem;
            border-radius: 6px;
            white-space: nowrap;
        }

        .loja-kabum { background-color: rgba(249, 115, 22, 0.15); color: #fb923c; border: 1px solid rgba(249, 115, 22, 0.3); }
        .loja-amazon { background-color: rgba(234, 179, 8, 0.15); color: #facc15; border: 1px solid rgba(234, 179, 8, 0.3); }
        .loja-terabyte { background-color: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .loja-pichau { background-color: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .loja-padrao { background-color: rgba(148, 163, 184, 0.15); color: #cbd5e1; border: 1px solid rgba(148, 163, 184, 0.3); }

        .empty-placeholder {
            padding: 4rem 2rem;
            text-align: center;
            background: var(--bg-card);
            border: 1px dashed var(--border);
            border-radius: 12px;
            color: var(--text-muted);
        }
    </style>
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
                            <option value="">Ignorar / Não incluir</option>
                            <option value="intel" <?= ($cpuMarca == 'intel') ? 'selected' : '' ?>>Intel Core</option>
                            <option value="amd" <?= ($cpuMarca == 'amd') ? 'selected' : '' ?>>AMD Ryzen</option>
                        </select>
                        <select name="cpu_linha" id="cpu_linha">
                            <option value="">Qualquer Linha...</option>
                        </select>
                        <select name="cpu_gen" id="cpu_gen">
                            <option value="">Qualquer Geração...</option>
                        </select>
                    </div>
                </div>

                <!-- 2. Memória RAM (Capacidade primeiro, DDR depois) -->
                <div class="accordion-item">
                    <button type="button" class="accordion-btn" onclick="toggleAccordion(this)">
                        <span>⚡ Memória RAM</span>
                        <span class="seta">▼</span>
                    </button>
                    <div class="accordion-content">
                        <select name="ram_cap" id="ram_cap" class="seletor-peca">
                            <option value="">Ignorar / Não incluir</option>
                            <option value="4gb" <?= ($ramCap == '4gb') ? 'selected' : '' ?>>4GB</option>
                            <option value="8gb" <?= ($ramCap == '8gb') ? 'selected' : '' ?>>8GB</option>
                            <option value="16gb" <?= ($ramCap == '16gb') ? 'selected' : '' ?>>16GB</option>
                            <option value="32gb" <?= ($ramCap == '32gb') ? 'selected' : '' ?>>32GB</option>
                            <option value="64gb" <?= ($ramCap == '64gb') ? 'selected' : '' ?>>64GB</option>
                        </select>
                        <select name="ram_ddr" id="ram_ddr" class="seletor-peca">
                            <option value="">Qualquer Padrão DDR...</option>
                            <option value="ddr3" <?= ($ramDdr == 'ddr3') ? 'selected' : '' ?>>DDR3</option>
                            <option value="ddr4" <?= ($ramDdr == 'ddr4') ? 'selected' : '' ?>>DDR4</option>
                            <option value="ddr5" <?= ($ramDdr == 'ddr5') ? 'selected' : '' ?>>DDR5</option>
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
                            <option value="">Ignorar / Não incluir</option>
                            <option value="ssd nvme" <?= ($discoTipo == 'ssd nvme') ? 'selected' : '' ?>>SSD NVMe M.2</option>
                            <option value="ssd sata" <?= ($discoTipo == 'ssd sata') ? 'selected' : '' ?>>SSD SATA III</option>
                            <option value="hd interno" <?= ($discoTipo == 'hd interno') ? 'selected' : '' ?>>HD Mecânico</option>
                        </select>
                        <select name="disco_cap" id="disco_cap" class="seletor-peca">
                            <option value="">Qualquer Capacidade...</option>
                            <option value="240gb" <?= ($discoCap == '240gb') ? 'selected' : '' ?>>240GB / 256GB</option>
                            <option value="480gb" <?= ($discoCap == '480gb') ? 'selected' : '' ?>>480GB / 512GB</option>
                            <option value="1tb" <?= ($discoCap == '1tb') ? 'selected' : '' ?>>1TB</option>
                            <option value="2tb" <?= ($discoCap == '2tb') ? 'selected' : '' ?>>2TB</option>
                            <option value="4tb" <?= ($discoCap == '4tb') ? 'selected' : '' ?>>4TB</option>
                        </select>
                    </div>
                </div>

                <!-- 4. Placa de Vídeo (3 Níveis) -->
                <div class="accordion-item">
                    <button type="button" class="accordion-btn" onclick="toggleAccordion(this)">
                        <span>🎮 Placa de Vídeo</span>
                        <span class="seta">▼</span>
                    </button>
                    <div class="accordion-content">
                        <select name="gpu_marca" id="gpu_marca" class="seletor-peca">
                            <option value="">Ignorar / Sem Placa</option>
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

    <!-- ÁREA PRINCIPAL (RESULTADOS) -->
    <main class="main-content">
        
        <?php if (!empty($erroTerminal)): ?>
            <pre style="background:#1e1e2e; color:#f38ba8; padding:1.2rem; border-radius:8px; border:1px solid #f38ba8; overflow-x:auto; font-size:0.85rem;"><?= htmlspecialchars($erroTerminal) ?></pre>
        <?php endif; ?>

        <!-- Banner Consolidado de Soma -->
        <?php if (count($resultadosPorPeca) > 1 && $totalSoma > 0): ?>
            <div class="summary-banner">
                <div>
                    <div class="summary-title">
                        <?= $modoSetup ? 'Orçamento Estimado da Máquina Completa' : 'Valor Total dos Componentes Selecionados' ?>
                    </div>
                    <div class="summary-subtitle">
                        <?= $modoSetup ? 'Inclui Processador, Placa-Mãe, RAM, Armazenamento, Fonte e Gabinete mais em conta.' : 'Soma calculada com a melhor opção de cada item marcado.' ?>
                    </div>
                </div>
                <div class="summary-price">
                    R$ <?= number_format($totalSoma, 2, ',', '.') ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Listas de Resultados -->
        <?php if (!empty($resultadosPorPeca)): ?>
            <?php foreach ($resultadosPorPeca as $chave => $dados): ?>
                <section class="categoria-bloco">
                    <div class="categoria-header">
                        <h3><?= htmlspecialchars($dados['rotulo']) ?> — <span style="font-size:0.9rem; color:var(--text-muted); font-weight:normal;">Busca: "<?= htmlspecialchars($dados['termo']) ?>"</span></h3>
                        <span><?= count($dados['itens']) ?> ofertas encontradas</span>
                    </div>

                    <?php if (!empty($dados['itens'])): ?>
                        <div class="product-grid">
                            <?php foreach ($dados['itens'] as $item): ?>
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
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="color:var(--text-muted); text-align:center; padding:1.5rem;">
                            Nenhum resultado registrado para esta categoria.
                        </div>
                    <?php endif; ?>
                </section>
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

// Dados para GPU em 3 Níveis
const dadosGpu = {
    nvidia: {
        series: [
            { val: "gtx", label: "Série GTX (Entrada)" },
            { val: "rtx 30", label: "Série RTX 30" },
            { val: "rtx 40", label: "Série RTX 40 (Atual)" }
        ],
        modelos: {
            gtx: [
                { val: "gtx 1650", label: "GTX 1650 4GB" }
            ],
            "rtx 30": [
                { val: "rtx 3050", label: "RTX 3050 6GB/8GB" },
                { val: "rtx 3060", label: "RTX 3060 12GB" },
                { val: "rtx 3060 ti", label: "RTX 3060 Ti" }
            ],
            "rtx 40": [
                { val: "rtx 4060", label: "RTX 4060 8GB" },
                { val: "rtx 4060 ti", label: "RTX 4060 Ti" },
                { val: "rtx 4070", label: "RTX 4070 12GB" },
                { val: "rtx 4070 super", label: "RTX 4070 Super" }
            ]
        }
    },
    amd: {
        series: [
            { val: "rx 500", label: "Série RX 500 (Básica)" },
            { val: "rx 6000", label: "Série RX 6000 (Custo-Benefício)" },
            { val: "rx 7000", label: "Série RX 7000 (Atual)" }
        ],
        modelos: {
            "rx 500": [
                { val: "rx 580", label: "RX 580 8GB" }
            ],
            "rx 6000": [
                { val: "rx 6600", label: "RX 6600 8GB" },
                { val: "rx 6650 xt", label: "RX 6650 XT" },
                { val: "rx 6750 xt", label: "RX 6750 XT 12GB" }
            ],
            "rx 7000": [
                { val: "rx 7600", label: "RX 7600 8GB" },
                { val: "rx 7700 xt", label: "RX 7700 XT 12GB" },
                { val: "rx 7800 xt", label: "RX 7800 XT 16GB" }
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
    selectCpuLinha.innerHTML = '<option value="">Qualquer Linha...</option>';
    selectCpuGen.innerHTML = '<option value="">Qualquer Geração...</option>';

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

// Auto-check setup completo se marcou mais de 1 peça
const checkboxSetup = document.getElementById('montarSetupCheckbox');
const seletores = document.querySelectorAll('.seletor-peca');

function checarMultiplos() {
    let preenchidos = 0;
    seletores.forEach(s => {
        if (s.value !== '') preenchidos++;
    });
    if (preenchidos > 1) {
        checkboxSetup.checked = true;
    }
}
seletores.forEach(s => s.addEventListener('change', checarMultiplos));

// Abre automaticamente as sanfonas que já possuem seleções ativas vindas da URL
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