<?php
// ---------------------------------------------------------------------------
// Motor de cotação: lê os CSVs, deduplica ofertas e escolhe a mais barata.
// Usado pela página inicial e pelo checkout (que nunca confia no preço do
// navegador: recalcula tudo a partir dos mesmos filtros).
// ---------------------------------------------------------------------------

function converterPrecoParaFloat($precoStr)
{
    $limpo = preg_replace('/[^\d,\.]/', '', (string) $precoStr);

    if (strpos($limpo, ',') !== false && strpos($limpo, '.') !== false) {
        $limpo = str_replace('.', '', $limpo);
        $limpo = str_replace(',', '.', $limpo);
    } elseif (strpos($limpo, ',') !== false) {
        $limpo = str_replace(',', '.', $limpo);
    }

    return floatval($limpo);
}

function identificarLoja($url)
{
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

function chaveProduto($link, $titulo)
{
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
function lerProdutosDoCsv($arquivo)
{
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
function arquivosExistentes(array $padroes)
{
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
function coletarProdutos(array $padroes)
{
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

/**
 * Normaliza os filtros do formulário e aplica os padrões do "Montar Setup".
 *
 * @return array<string, string>
 */
function normalizarEntrada(array $get, bool $modoSetup, string $perfilBusca): array
{
    $entrada = [
        'cpu_marca'     => strtolower(trim($get['cpu_marca'] ?? '')),
        'cpu_linha'     => strtolower(trim($get['cpu_linha'] ?? '')),
        'cpu_gen'       => trim($get['cpu_gen'] ?? ''),
        'cpu_video'     => $get['video_integrado'] ?? '',
        'ram_tipo'      => strtolower(trim($get['ram_tipo'] ?? '')),
        'ram_cap'       => strtolower(trim($get['ram_cap'] ?? '')),
        'ram_ddr'       => strtolower(trim($get['ram_ddr'] ?? '')),
        'disco_tipo'    => trim($get['disco_tipo'] ?? ''),
        'disco_cap'     => strtolower(trim($get['disco_cap'] ?? '')),
        'gpu_marca'     => strtolower(trim($get['gpu_marca'] ?? '')),
        'gpu_serie'     => strtolower(trim($get['gpu_serie'] ?? '')),
        'gpu_modelo'    => strtolower(trim($get['gpu_modelo'] ?? '')),
        'fonte_cap'     => strtolower(trim($get['fonte_cap'] ?? '')),
        'placa_soquete' => trim($get['placa_soquete'] ?? ''),
        'gabinete_tipo' => strtolower(trim($get['gabinete_tipo'] ?? '')),
        'cooler_tipo'   => strtolower(trim($get['cooler_tipo'] ?? '')),
        'cooler_estilo' => strtolower(trim($get['cooler_estilo'] ?? '')),
    ];

    // Valores padrão do "Montar Setup Completo"
    if ($modoSetup) {
        if ($entrada['cpu_marca'] === '') {
            $entrada['cpu_marca'] = 'amd';
            $entrada['cpu_linha'] = ($perfilBusca === 'desempenho') ? 'ryzen 7' : 'ryzen 5';
            $entrada['cpu_gen']   = ($perfilBusca === 'desempenho') ? '7000' : '5000';
        }
        if ($entrada['ram_cap'] === '' && $entrada['ram_ddr'] === '') {
            $entrada['ram_cap'] = ($perfilBusca === 'desempenho') ? '32gb' : '16gb';
            $entrada['ram_ddr'] = ($perfilBusca === 'desempenho') ? 'ddr5' : 'ddr4';
        }
        if ($entrada['disco_tipo'] === '' && $entrada['disco_cap'] === '') {
            $entrada['disco_tipo'] = 'M.2 NVMe';
            $entrada['disco_cap']  = ($perfilBusca === 'desempenho') ? '1tb' : '480gb';
        }
    }

    return $entrada;
}

/**
 * Monta a lista de peças que serão cotadas a partir dos filtros já normalizados.
 *
 * @param array<string,string> $entrada
 * @param string $csvsDir     Pasta dos CSVs (com separador no final).
 * @param bool   $modoSetup   "Montar Setup Completo" marcado?
 * @param string $perfilBusca 'barato' ou 'desempenho'.
 * @return array<string, array{rotulo:string,termo:string,padroes:array,aviso?:string}>
 */
function montarPecasParaCotar(array $entrada, string $csvsDir, bool $modoSetup, string $perfilBusca): array
{
    $cpuMarca = $entrada['cpu_marca'];
    $cpuLinha = $entrada['cpu_linha'];
    $cpuGen   = $entrada['cpu_gen'];
    $cpuVideo = $entrada['cpu_video'];

    $ramTipo = $entrada['ram_tipo'];
    $ramCap  = $entrada['ram_cap'];
    $ramDdr  = $entrada['ram_ddr'];

    $discoTipo = $entrada['disco_tipo'];
    $discoCap  = $entrada['disco_cap'];

    $gpuMarca  = $entrada['gpu_marca'];
    $gpuSerie  = $entrada['gpu_serie'];
    $gpuModelo = $entrada['gpu_modelo'];

    $fonteCap     = $entrada['fonte_cap'];
    $placaSoquete = $entrada['placa_soquete'];
    $gabineteTipo = $entrada['gabinete_tipo'];
    $coolerTipo   = $entrada['cooler_tipo'];
    $coolerEstilo = $entrada['cooler_estilo'];

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

    // 5. Fonte
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

    // 6. Placa-mãe
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

    // 7. Gabinete
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

    // 8. Cooler
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

    return $pecasParaCotar;
}

/**
 * Executa a cotação completa.
 *
 * @return array{
 *   pecas: array,
 *   resultados: array,
 *   total: float,
 *   faltantes: int,
 *   atualizacao: int
 * }
 */
function calcularCotacao(array $get, string $csvsDir, bool $modoSetup, string $perfilBusca): array
{
    $entrada        = normalizarEntrada($get, $modoSetup, $perfilBusca);
    $pecasParaCotar = montarPecasParaCotar($entrada, $csvsDir, $modoSetup, $perfilBusca);

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

    return [
        'pecas'        => $pecasParaCotar,
        'resultados'   => $resultadosPorPeca,
        'total'        => $totalSoma,
        'faltantes'    => $pecasComFalta,
        'atualizacao'  => $ultimaAtualizacao,
        'entrada'      => $entrada,
    ];
}
