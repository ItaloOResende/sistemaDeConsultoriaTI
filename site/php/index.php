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

// ---------------------------------------------------------------------------
// Configuração
// ---------------------------------------------------------------------------
// Os CSVs ficam em csvs/, na raiz do projeto (um nível acima de site/php).
$raizProjeto = dirname(dirname(__DIR__));
$csvsDir     = $raizProjeto . DIRECTORY_SEPARATOR . 'csvs' . DIRECTORY_SEPARATOR;

$perfilBusca = $_GET['perfil_busca'] ?? 'barato';
$modoSetup   = ($_GET['montar_setup'] ?? '') === '1';

// ---------------------------------------------------------------------------
// Funções auxiliares
// ---------------------------------------------------------------------------
function chaveProduto($link, $titulo) {
    // Amazon: /dp/ASIN (ignora parâmetros de rastreamento)
    if (preg_match('~/dp/([A-Za-z0-9]{10})~', $link, $m)) {
        return 'amazon:' . strtoupper($m[1]);
    }
    // Terabyte: /produto/ID
    if (preg_match('~/produto/(\d+)~', $link, $m)) {
        return 'terabyte:' . $m[1];
    }
    // KaBuM / Pichau: slug no fim da URL
    $caminho = parse_url($link, PHP_URL_PATH) ?: '';
    $slug = strtolower(trim(basename(rtrim($caminho, '/'))));
    if ($slug !== '') return 'url:' . $slug;

    return 'titulo:' . mb_strtolower(preg_replace('/\s+/', ' ', trim($titulo)));
}

/**
 * Lê um CSV de produtos aceitando:
 *   3 colunas -> Produto, Preço, Link
 *   4 colunas -> Produto, Soquete/Tamanho, Preço, Link
 * Detecta o preço e o link pela posição/conteúdo, sem depender da ordem fixa.
 */
function lerProdutosDoCsv($arquivo) {
    $itens = [];
    $handle = @fopen($arquivo, 'r');
    if ($handle === false) return $itens;

    while (($linha = fgetcsv($handle, 0, ',')) !== false) {
        if (!is_array($linha) || count($linha) < 2) continue;
        if (strtolower(trim($linha[0] ?? '')) === 'produto') continue; // cabeçalho

        $titulo = trim($linha[0]);
        $link   = '';
        $preco  = '';

        foreach ($linha as $i => $celula) {
            $celula = trim($celula);
            if ($celula === '') continue;

            if ($link === '' && preg_match('~^https?://~i', $celula)) {
                $link = $celula;
                continue;
            }
            // Preço = célula só com números/separadores (evita "LGA 1700" e "ATX")
            if ($i > 0 && $preco === '' && preg_match('/^(?:R\$\s*)?[\d][\d.,]*$/', $celula)) {
                $preco = $celula;
            }
        }

        if ($titulo === '' || $link === '' || $preco === '') continue;

        $valor = converterPrecoParaFloat($preco);
        if ($valor <= 0) continue;

        $itens[] = [
            'titulo'     => $titulo,
            'precoTexto' => 'R$ ' . number_format($valor, 2, ',', '.'),
            'precoNum'   => $valor,
            'link'       => $link,
            'loja'       => identificarLoja($link),
        ];
    }

    fclose($handle);
    return $itens;
}

/** Expande padrões glob e devolve apenas arquivos existentes, sem repetição. */
function arquivosExistentes(array $padroes) {
    $arquivos = [];
    foreach ($padroes as $padrao) {
        foreach (glob($padrao) ?: [] as $arquivo) {
            if (is_file($arquivo)) $arquivos[$arquivo] = true;
        }
    }
    return array_keys($arquivos);
}

/**
 * Junta todos os arquivos, remove itens repetidos (mesmo link de produto)
 * e devolve a lista ordenada do mais barato para o mais caro.
 */
function coletarProdutos(array $padroes) {
    $unicos = [];

    foreach (arquivosExistentes($padroes) as $arquivo) {
        foreach (lerProdutosDoCsv($arquivo) as $item) {
            $chave = chaveProduto($item['link'], $item['titulo']);
            if (!isset($unicos[$chave]) || $item['precoNum'] < $unicos[$chave]['precoNum']) {
                $unicos[$chave] = $item;
            }
        }
    }

    $lista = array_values($unicos);
    usort($lista, fn($a, $b) => $a['precoNum'] <=> $b['precoNum']);
    return $lista;
}

// ---------------------------------------------------------------------------
// Entrada do formulário
// ---------------------------------------------------------------------------
$cpuMarca = strtolower(trim($_GET['cpu_marca'] ?? ''));
$cpuLinha = strtolower(trim($_GET['cpu_linha'] ?? ''));
$cpuGen   = trim($_GET['cpu_gen'] ?? '');
$cpuVideo = $_GET['video_integrado'] ?? '';

$ramTipo = strtolower(trim($_GET['ram_tipo'] ?? ''));
$ramCap = strtolower(trim($_GET['ram_cap'] ?? ''));
$ramDdr = strtolower(trim($_GET['ram_ddr'] ?? ''));

$discoTipo = trim($_GET['disco_tipo'] ?? '');
$discoCap  = strtolower(trim($_GET['disco_cap'] ?? ''));

$gpuMarca  = strtolower(trim($_GET['gpu_marca'] ?? ''));
$gpuSerie  = strtolower(trim($_GET['gpu_serie'] ?? ''));
$gpuModelo = strtolower(trim($_GET['gpu_modelo'] ?? ''));

$fonteCap     = strtolower(trim($_GET['fonte_cap'] ?? ''));
$placaSoquete = trim($_GET['placa_soquete'] ?? '');
$gabineteTipo = strtolower(trim($_GET['gabinete_tipo'] ?? ''));
$coolerTipo   = strtolower(trim($_GET['cooler_tipo'] ?? ''));
$coolerEstilo = strtolower(trim($_GET['cooler_estilo'] ?? ''));

// ---------------------------------------------------------------------------
// Valores padrão do "Montar Setup Completo"
// ---------------------------------------------------------------------------
if ($modoSetup) {
    if ($cpuMarca === '') {
        $cpuMarca = 'amd';
        $cpuLinha = ($perfilBusca === 'desempenho') ? 'ryzen 7' : 'ryzen 5';
        $cpuGen   = ($perfilBusca === 'desempenho') ? '7000' : '5000';
    }
    if ($ramCap === '' && $ramDdr === '') {
        $ramCap = ($perfilBusca === 'desempenho') ? '32gb' : '16gb';
        $ramDdr = ($perfilBusca === 'desempenho') ? 'ddr5' : 'ddr4';
    }
    if ($discoTipo === '' && $discoCap === '') {
        $discoTipo = 'M.2 NVMe';
        $discoCap  = ($perfilBusca === 'desempenho') ? '1tb' : '480gb';
    }
}

// ---------------------------------------------------------------------------
// Monta as peças: rótulo, termo exibido e padrões de arquivo CSV
// ---------------------------------------------------------------------------
$pecasParaCotar = [];

// 1. Processador
if ($cpuMarca !== '') {
    $termoCpu = "processador {$cpuMarca}";
    if ($cpuLinha !== '') $termoCpu .= " {$cpuLinha}";
    if ($cpuGen !== '')   $termoCpu .= ($cpuMarca === 'intel') ? " {$cpuGen}ª geracao" : " serie {$cpuGen}";
    if ($cpuVideo === 'sim' && $cpuMarca === 'amd') $termoCpu .= ' g';
    elseif ($cpuVideo === 'nao' && $cpuMarca === 'intel') $termoCpu .= ' f';

    $padroesCpu = [];
    if ($cpuMarca === 'intel') {
        $linhaArq = preg_replace('/[^i0-9]/', '', $cpuLinha);   // i5
        $genArq   = preg_replace('/\D/', '', $cpuGen);          // 12
        if ($linhaArq !== '') {
            $padroesCpu[] = $csvsDir . "intel_{$linhaArq}_" . ($genArq !== '' ? $genArq : '*') . '.csv';
        } else {
            $padroesCpu[] = $csvsDir . 'intel_*.csv';
        }
    } else {
        $linhaArq = preg_replace('/\D/', '', $cpuLinha);        // "ryzen 5" -> 5
        $genArq   = preg_replace('/\D/', '', $cpuGen);          // 5000
        if ($linhaArq !== '') {
            $padroesCpu[] = $csvsDir . "amd_ryzen_{$linhaArq}_" . ($genArq !== '' ? $genArq : '*') . '.csv';
        } else {
            $padroesCpu[] = $csvsDir . 'amd_ryzen_*.csv';
        }
    }

    $pecasParaCotar['cpu'] = ['rotulo' => 'Processador', 'termo' => $termoCpu, 'padroes' => $padroesCpu];
}

// 2. Memória RAM
if ($ramTipo !== '' || $ramCap !== '' || $ramDdr !== '') {
    $termoRam = 'memoria ram';
    if ($ramTipo !== '') $termoRam .= " {$ramTipo}";
    if ($ramCap !== '')  $termoRam .= " {$ramCap}";
    if ($ramDdr !== '')  $termoRam .= " {$ramDdr}";

    $padroesRam = [];
    $avisoRam   = '';

    if ($ramTipo === 'notebook') {
        // A coleta automática atual não inclui SODIMM/notebook
        $avisoRam = 'A coleta automática não inclui memórias de notebook (SODIMM). Escolha "Desktop" para buscar nos dados disponíveis.';
        $padroesRam[] = $csvsDir . 'memoria_notebook_*.csv';
    } elseif ($ramDdr !== '' && $ramCap !== '') {
        $padroesRam[] = $csvsDir . "memoria_{$ramDdr}_{$ramCap}.csv";
    } elseif ($ramDdr !== '') {
        $padroesRam[] = $csvsDir . "memoria_{$ramDdr}_*.csv";
    } elseif ($ramCap !== '') {
        $padroesRam[] = $csvsDir . "memoria_ddr*_{$ramCap}.csv";
    } else {
        // Só o equipamento (Desktop): procura em todas as memórias desktop
        $padroesRam[] = $csvsDir . 'memoria_*.csv';
    }

    $pecasParaCotar['ram'] = [
        'rotulo'  => 'Memória RAM',
        'termo'   => $termoRam,
        'padroes' => $padroesRam,
        'aviso'   => $avisoRam,
    ];
}

// 3. Armazenamento
if ($discoTipo !== '' || $discoCap !== '') {
    $termoArmazenamento = $discoTipo !== '' ? $discoTipo : 'ssd m.2 nvme';
    if ($discoCap !== '') $termoArmazenamento .= " {$discoCap}";

    $mapaTipoDisco = [
        'm.2 nvme'    => 'ssd_m.2 nvme',
        'm.2 sata'    => 'ssd_m.2 sata',
        'ssd sata'    => 'ssd_sata',
        'hd pc'       => 'hdd_sata',
        'hd notebook' => 'hdd_sata',
    ];
    // A interface usa 120/240/480; os arquivos gerados usam 128/256/512.
    $mapaCapDisco = ['120gb' => '128gb', '240gb' => '256gb', '480gb' => '512gb'];

    $tipoArq = $mapaTipoDisco[strtolower($discoTipo)] ?? '';
    $capArq  = $mapaCapDisco[$discoCap] ?? $discoCap;

    $padroesDisco = [];
    if ($tipoArq !== '') {
        $padroesDisco[] = $csvsDir . $tipoArq . '_' . ($capArq !== '' ? $capArq : '*') . '.csv';
    } elseif ($capArq !== '') {
        $padroesDisco[] = $csvsDir . "*_{$capArq}.csv";
    }

    $pecasParaCotar['armazenamento'] = ['rotulo' => 'Armazenamento', 'termo' => $termoArmazenamento, 'padroes' => $padroesDisco];
}

// 4. Placa de Vídeo
if ($gpuMarca !== '') {
    if ($gpuModelo !== '')     $termoGpu = $gpuModelo;
    elseif ($gpuSerie !== '')  $termoGpu = $gpuSerie;
    else                       $termoGpu = ($gpuMarca === 'nvidia') ? 'placa de video rtx' : 'placa de video radeon rx';

    $padroesGpu = [];

    if ($gpuModelo !== '') {
        // "rtx 4060 ti 16gb" -> 4060
        if (preg_match('/(\d{3,4})/', $gpuModelo, $m)) {
            $padroesGpu[] = $csvsDir . "nvidia_*_{$m[1]}.csv";
            $padroesGpu[] = $csvsDir . "amd_rx_*_{$m[1]}.csv";
        }
    } elseif ($gpuSerie !== '') {
        if (str_starts_with($gpuSerie, 'rx')) {
            if (preg_match('/(\d+)/', $gpuSerie, $m)) {
                $numeros = $m[1];
                // "6000" -> amd_rx_6???  |  "500" -> amd_rx_5??
                $padroesGpu[] = $csvsDir . 'amd_rx_' . $numeros[0] . str_repeat('?', strlen($numeros) - 1) . '.csv';
            }
        } else {
            if (preg_match('/(\d+)/', $gpuSerie, $m)) {
                // "40" -> nvidia_*_40??  (4060, 4070...)
                $padroesGpu[] = $csvsDir . 'nvidia_*_' . $m[1] . str_repeat('?', 4 - strlen($m[1])) . '.csv';
            } else {
                $padroesGpu[] = $csvsDir . 'nvidia_gtx_????.csv';
            }
        }
    } else {
        $padroesGpu[] = $csvsDir . (($gpuMarca === 'nvidia') ? 'nvidia_*.csv' : 'amd_rx_*.csv');
    }

    $pecasParaCotar['gpu'] = ['rotulo' => 'Placa de Vídeo', 'termo' => $termoGpu, 'padroes' => $padroesGpu];
}

// 5. Fontes, placas-mãe, gabinetes e coolers

// Fonte de alimentação
$capacidadeFontePadrao = ($perfilBusca === 'desempenho' || $gpuMarca !== '') ? '650w' : '500w';
if ($fonteCap !== '') {
    $capacidadeFonte = ($fonteCap === 'todos') ? '' : $fonteCap;
} elseif ($modoSetup) {
    $capacidadeFonte = $capacidadeFontePadrao;
} else {
    $capacidadeFonte = null;
}
if ($capacidadeFonte !== null) {
    $pecasParaCotar['fonte'] = [
        'rotulo'  => 'Fonte de Alimentação',
        'termo'   => $capacidadeFonte !== '' ? "fonte {$capacidadeFonte} 80 plus" : 'fonte 80 plus',
        'padroes' => [$csvsDir . ($capacidadeFonte !== '' ? "fonte_{$capacidadeFonte}.csv" : 'fonte_*.csv')],
    ];
}

// Placa-mãe
$soquetePlaca = '';
if ($placaSoquete !== '') {
    $soquetePlaca = ($placaSoquete === 'todos') ? '' : $placaSoquete;
} elseif ($modoSetup) {
    // Soquete coerente com a CPU escolhida
    if ($cpuMarca === 'intel') {
        $gen = (int) preg_replace('/\D/', '', $cpuGen);
        if ($gen === 0 || $gen >= 12)  $soquetePlaca = 'LGA 1700';
        elseif ($gen >= 10)            $soquetePlaca = 'LGA 1200';
        else                           $soquetePlaca = 'LGA 1151';
    } elseif ($cpuMarca === 'amd') {
        $gen = (int) preg_replace('/\D/', '', $cpuGen);
        $soquetePlaca = ($gen >= 7000) ? 'AM5' : 'AM4';
    }
}
if ($placaSoquete !== '' || $modoSetup) {
    $pecasParaCotar['placa_mae'] = [
        'rotulo'  => 'Placa-Mãe',
        'termo'   => $soquetePlaca !== '' ? 'placa mae ' . strtolower($soquetePlaca) : 'placa mae',
        'padroes' => [$csvsDir . ($soquetePlaca !== ''
            ? 'placa_mae_' . strtolower(str_replace(' ', '_', $soquetePlaca)) . '.csv'
            : 'placa_mae_*.csv')],
    ];
}

// Gabinete
if ($gabineteTipo !== '' || $modoSetup) {
    $tipoGabinete = ($gabineteTipo === '' || $gabineteTipo === 'todos') ? '' : $gabineteTipo;
    $pecasParaCotar['gabinete'] = [
        'rotulo'  => 'Gabinete',
        'termo'   => $tipoGabinete !== ''
            ? 'gabinete ' . str_replace('_', ' ', $tipoGabinete)
            : 'gabinete gamer com fans',
        'padroes' => [$csvsDir . ($tipoGabinete !== '' ? "gabinete_{$tipoGabinete}.csv" : 'gabinete_*.csv')],
    ];
}

// Cooler (air/water + estilo)
if ($coolerTipo !== '' || $coolerEstilo !== '') {
    $tipoCooler   = ($coolerTipo === 'todos') ? '' : $coolerTipo;
    $estiloCooler = ($coolerEstilo === 'todos') ? '' : $coolerEstilo;

    $termoCooler = $tipoCooler !== '' ? "{$tipoCooler} cooler" : 'cooler';
    if ($estiloCooler !== '') $termoCooler .= " {$estiloCooler}";

    $padroesCooler = [$csvsDir . ($tipoCooler !== '' ? $tipoCooler : '*') . '_cooler_' . ($estiloCooler !== '' ? $estiloCooler : '*') . '.csv'];

    $pecasParaCotar['cooler'] = [
        'rotulo'  => 'Cooler',
        'termo'   => $termoCooler,
        'padroes' => $padroesCooler,
    ];
}

// ---------------------------------------------------------------------------
// Coleta as ofertas e escolhe a mais barata de cada peça
// ---------------------------------------------------------------------------
$resultadosPorPeca = [];
$totalSoma         = 0.0;
$pecasComFalta     = 0;
$ultimaAtualizacao = 0;

foreach (glob($csvsDir . '*.csv') ?: [] as $arquivoCsv) {
    $ultimaAtualizacao = max($ultimaAtualizacao, (int) @filemtime($arquivoCsv));
}

foreach ($pecasParaCotar as $chave => $dados) {
    $itens        = coletarProdutos($dados['padroes']);
    $melhor       = $itens[0] ?? null;
    $alternativas = array_slice($itens, 1, 3);

    if ($melhor !== null) {
        $totalSoma += $melhor['precoNum'];
    } else {
        $pecasComFalta++;
    }

    $resultadosPorPeca[$chave] = [
        'rotulo'       => $dados['rotulo'],
        'termo'        => $dados['termo'],
        'padroes'      => $dados['padroes'],
        'arquivos'     => array_map('basename', arquivosExistentes($dados['padroes'])),
        'aviso'        => $dados['aviso'] ?? '',
        'total'        => count($itens),
        'melhor'       => $melhor,
        'alternativas' => $alternativas,
    ];
}

$erroTerminal = '';
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
    <main class="main-content">
        
        <?php if (!empty($erroTerminal)): ?>
            <pre style="background:#1e1e2e; color:#f38ba8; padding:1.2rem; border-radius:8px; border:1px solid #f38ba8; overflow-x:auto; font-size:0.85rem;"><?= htmlspecialchars($erroTerminal) ?></pre>
        <?php endif; ?>

        <?php if ($totalSoma > 0): ?>
            <div class="summary-banner">
                <div>
                    <div class="summary-title">
                        <?= $modoSetup ? 'Orçamento Estimado (somando as peças encontradas)' : (count($resultadosPorPeca) > 1 ? 'Total das Peças Selecionadas' : 'Oferta Mais Barata Encontrada') ?>
                    </div>
                    <div class="summary-subtitle">
                        <?= $pecasComFalta > 0
                            ? "Soma parcial: {$pecasComFalta} peça(s) sem oferta nos dados coletados."
                            : 'Soma do menor preço encontrado para cada componente.' ?>
                        <?php if ($ultimaAtualizacao > 0): ?>
                            · Dados de <?= date('d/m/Y H:i', $ultimaAtualizacao) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="summary-price">
                    R$ <?= number_format($totalSoma, 2, ',', '.') ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($resultadosPorPeca)): ?>
            <?php foreach ($resultadosPorPeca as $chave => $dados): ?>
                <div class="categoria-bloco">
                    <div class="categoria-label">
                        <?= htmlspecialchars($dados['rotulo']) ?> — Termo: "<?= htmlspecialchars($dados['termo']) ?>"
                        <?php if ($dados['total'] > 0): ?>
                            · <?= $dados['total'] ?> oferta(s) encontrada(s)
                        <?php endif; ?>
                    </div>

                    <?php if ($dados['melhor'] !== null): $item = $dados['melhor']; ?>
                        <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank" rel="noopener" class="product-card destaque-menor">
                            <div class="product-info">
                                <div class="product-title"><?= htmlspecialchars($item['titulo']) ?></div>
                                <div class="product-price"><?= htmlspecialchars($item['precoTexto']) ?></div>
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
</script>

</body>
</html>