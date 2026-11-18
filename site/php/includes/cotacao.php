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
function coletarProdutos(array $padroes, ?callable $filtro = null)
{
    $unicos = [];

    foreach (arquivosExistentes($padroes) as $arquivo) {
        foreach (lerProdutosDoCsv($arquivo) as $item) {
            if ($filtro !== null && !$filtro($item)) continue;
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
function normalizarEntrada(array $get, bool $modoSetup, string $perfilBusca, bool $aplicarPadroes = true): array
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

    // Valores padrão do "Montar Setup Completo".
    // Quando o usuário pede para completar o PC, esses padrões são ignorados:
    // as peças faltantes são escolhidas pela compatibilidade real.
    if ($modoSetup && $aplicarPadroes) {
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

// ---------------------------------------------------------------------------
// Completar o PC: escolhe, para cada peça faltante, o item mais barato que seja
// compatível com o que o usuário já selecionou.
// ---------------------------------------------------------------------------

function itemEhMemoriaRam(array $item): bool
{
    $titulo = mb_strtolower($item['titulo']);
    if (strpos($titulo, 'mem') === false && strpos($titulo, 'ram') === false) return false;
    if (preg_match('/placa de v[íi]deo|gpu|ventoinha|ssd|hd /', $titulo)) return false;
    return true;
}

function normalizarCapacidadeFonte(string $token): int
{
    return (int) preg_replace('/\D/', '', $token);
}

function estimarWattsCpu(string $marca, string $linha, string $gen): int
{
    if ($marca === 'intel') {
        $linhaNum = (int) preg_replace('/\D/', '', $linha);
        if ($linhaNum >= 9) return 125;
        if ($linhaNum >= 7) return 95;
        return 65;
    }

    $linhaNum = (int) preg_replace('/\D/', '', $linha);
    if ($linhaNum >= 9) return 105;
    return 65;
}

function estimarWattsGpu(string $titulo): int
{
    $titulo = mb_strtolower($titulo);
    $modelos = [
        '1050' => 75, '1060' => 120, '1070' => 150, '1080' => 180,
        '2050' => 75, '2060' => 160, '2080' => 210,
        '3050' => 130, '3060' => 170, '3070' => 220, '3080' => 320,
        '4060' => 115, '4080' => 320,
        '5050' => 130, '5060' => 180, '5070' => 250, '5080' => 320, '5090' => 450,
        '570' => 150, '580' => 185, '590' => 225,
        '6300' => 100, '6400' => 100, '6500' => 100, '6600' => 130,
        '6650' => 150, '6700' => 175, '6750' => 175, '6800' => 250,
        '6900' => 300, '6950' => 300,
        '7400' => 120, '7600' => 165, '7650' => 165, '7700' => 175,
        '7800' => 250, '7900' => 300,
        '9050' => 150, '9060' => 180, '9070' => 220,
    ];

    foreach ($modelos as $modelo => $watts) {
        if (preg_match('/(?<!\d)' . preg_quote($modelo, '/') . '(?!\d)/', $titulo)) {
            return $watts;
        }
    }
    return 150;
}

function socketEDdrDaCpu(string $marca, string $linha, string $gen): array
{
    $genNum = (int) preg_replace('/\D/', '', $gen);

    if ($marca === 'intel') {
        if ($genNum >= 12) return ['socket' => 'LGA 1700', 'ddr' => null];
        if ($genNum >= 10) return ['socket' => 'LGA 1200', 'ddr' => 'ddr4'];
        return ['socket' => 'LGA 1151', 'ddr' => 'ddr4'];
    }

    if ($genNum >= 7000) return ['socket' => 'AM5', 'ddr' => 'ddr5'];
    return ['socket' => 'AM4', 'ddr' => 'ddr4'];
}

function slugSoquete(string $socket): string
{
    return strtolower(str_replace(' ', '_', $socket));
}

function dadosCpuDoArquivo(string $arquivo): ?array
{
    $nome = basename($arquivo);
    if (preg_match('/^amd_ryzen_(\d+)_(\d+)\.csv$/', $nome, $m)) {
        return ['cpu_marca' => 'amd', 'cpu_linha' => 'ryzen ' . $m[1], 'cpu_gen' => $m[2]];
    }
    if (preg_match('/^intel_i(\d+)_(\d+)\.csv$/', $nome, $m)) {
        return ['cpu_marca' => 'intel', 'cpu_linha' => 'i' . $m[1], 'cpu_gen' => $m[2]];
    }
    return null;
}

function escolherPlacaMae(string $csvsDir, string $socket, ?string $ddr): ?string
{
    $arquivo = $csvsDir . 'placa_mae_' . slugSoquete($socket) . '.csv';
    if (!is_file($arquivo)) return null;

    $filtro = null;
    if ($ddr !== null && in_array($ddr, ['ddr3', 'ddr4', 'ddr5'], true)) {
        $filtro = function ($item) use ($ddr) {
            $titulo = mb_strtolower($item['titulo']);
            foreach (['ddr3', 'ddr4', 'ddr5'] as $outro) {
                if ($outro !== $ddr && strpos($titulo, $outro) !== false) return false;
            }
            return strpos($titulo, $ddr) !== false;
        };
    }

    $itens  = coletarProdutos([$arquivo], $filtro);
    $melhor = $itens[0] ?? null;
    if ($melhor === null && $filtro !== null) {
        // Não existe placa com esse tipo de memória no soquete: usa a mais barata.
        $itens  = coletarProdutos([$arquivo]);
        $melhor = $itens[0] ?? null;
    }
    if ($melhor === null) return null;

    $titulo = mb_strtolower($melhor['titulo']);
    foreach (['ddr5', 'ddr4', 'ddr3'] as $tipo) {
        if (strpos($titulo, $tipo) !== false) return $tipo;
    }
    return null;
}

function escolherRam(string $csvsDir, string $ddr, ?string $capFixa): ?string
{
    $padrao = $capFixa !== null
        ? [$csvsDir . "memoria_{$ddr}_{$capFixa}.csv"]
        : (glob($csvsDir . "memoria_{$ddr}_*.csv") ?: []);

    $melhorPreco = INF;
    $capEscolhida = null;
    foreach ($padrao as $arquivo) {
        if (!preg_match('/_(\d+gb)\.csv$/', basename($arquivo), $m)) continue;
        $itens = coletarProdutos([$arquivo], 'itemEhMemoriaRam');
        $item  = $itens[0] ?? null;
        if ($item === null) continue;
        if ($item['precoNum'] < $melhorPreco) {
            $melhorPreco   = $item['precoNum'];
            $capEscolhida = $m[1];
        }
    }
    return $capEscolhida;
}

function escolherArmazenamento(string $csvsDir): ?array
{
    $mapa = [
        'ssd_m.2 nvme' => ['tipo' => 'M.2 NVMe', 'cap' => ''],
        'ssd_m.2 sata' => ['tipo' => 'M.2 SATA', 'cap' => ''],
        'ssd_sata'     => ['tipo' => 'SSD SATA', 'cap' => ''],
        'hdd_sata'     => ['tipo' => 'HD PC', 'cap' => ''],
    ];

    $melhorPreco = INF;
    $escolhido   = null;

    foreach (glob($csvsDir . '*.csv') ?: [] as $arquivo) {
        $nome = basename($arquivo, '.csv');
        $dados = null;
        foreach ($mapa as $prefixo => $info) {
            if (strpos($nome, $prefixo . '_') === 0) {
                $dados = [
                    'tipo' => $info['tipo'],
                    'cap'  => substr($nome, strlen($prefixo) + 1),
                ];
                break;
            }
        }
        if ($dados === null) continue;

        $itens = coletarProdutos([$arquivo]);
        $item  = $itens[0] ?? null;
        if ($item === null) continue;
        if ($item['precoNum'] < $melhorPreco) {
            $melhorPreco = $item['precoNum'];
            $escolhido   = $dados;
        }
    }
    return $escolhido;
}

function escolherGpu(string $csvsDir, int $wattsMax, string $marcaFixa = ''): ?array
{
    if ($marcaFixa === 'nvidia') {
        $arquivos = glob($csvsDir . 'nvidia_*.csv') ?: [];
    } elseif ($marcaFixa === 'amd') {
        $arquivos = glob($csvsDir . 'amd_rx_*.csv') ?: [];
    } else {
        $arquivos = array_merge(
            glob($csvsDir . 'nvidia_*.csv') ?: [],
            glob($csvsDir . 'amd_rx_*.csv') ?: []
        );
    }

    $melhor = null;
    foreach ($arquivos as $arquivo) {
        $marca = str_starts_with(basename($arquivo), 'amd_') ? 'amd' : 'nvidia';
        $itens = coletarProdutos([$arquivo], function ($item) use ($wattsMax) {
            $titulo = mb_strtolower($item['titulo']);
            if (strpos($titulo, 'placa') === false) return false;
            if (preg_match('/ventoinha|fan|suporte|cooler de placa/', $titulo)) return false;
            return estimarWattsGpu($item['titulo']) <= $wattsMax;
        });
        $item = $itens[0] ?? null;
        if ($item === null) continue;
        if ($melhor === null || $item['precoNum'] < $melhor['preco']) {
            $melhor = [
                'preco' => $item['precoNum'],
                'marca' => $marca,
                'watts' => estimarWattsGpu($item['titulo']),
            ];
        }
    }
    return $melhor;
}

function escolherFonte(string $csvsDir, int $necessaria): ?string
{
    $melhor      = null;
    $maiorWatts  = 0;
    $capMaior    = null;

    foreach (glob($csvsDir . 'fonte_*.csv') ?: [] as $arquivo) {
        if (!preg_match('/^fonte_(.+)\.csv$/', basename($arquivo), $m)) continue;
        $watts = normalizarCapacidadeFonte($m[1]);
        if ($watts > $maiorWatts) {
            $maiorWatts = $watts;
            $capMaior   = $m[1];
        }
        if ($watts < $necessaria) continue;

        $itens = coletarProdutos([$arquivo]);
        $item  = $itens[0] ?? null;
        if ($item === null) continue;
        if ($melhor === null || $item['precoNum'] < $melhor['preco']) {
            $melhor = ['preco' => $item['precoNum'], 'cap' => $m[1]];
        }
    }

    return $melhor['cap'] ?? $capMaior;
}

function escolherGabinete(string $csvsDir): ?string
{
    $melhorPreco = INF;
    $escolhido   = null;

    foreach (['mid_tower', 'full_tower'] as $tipo) {
        $itens = coletarProdutos([$csvsDir . "gabinete_{$tipo}.csv"]);
        $item  = $itens[0] ?? null;
        if ($item === null) continue;
        if ($item['precoNum'] < $melhorPreco) {
            $melhorPreco = $item['precoNum'];
            $escolhido   = $tipo;
        }
    }
    return $escolhido;
}

function escolherCooler(string $csvsDir, string $socket): ?array
{
    $melhorPreco = INF;
    $escolhido   = null;

    $arquivos = array_merge(
        glob($csvsDir . 'air_cooler_*.csv') ?: [],
        glob($csvsDir . 'water_cooler_*.csv') ?: []
    );

    foreach ($arquivos as $arquivo) {
        if (!preg_match('/^(air|water)_cooler_(simples|rgb|argb)\.csv$/', basename($arquivo), $m)) continue;

        $itens = coletarProdutos([$arquivo], function ($item) use ($socket) {
            $titulo  = mb_strtolower($item['titulo']);
            $temIntel = strpos($titulo, 'intel') !== false || strpos($titulo, 'lga') !== false;
            $temAmd   = strpos($titulo, 'amd') !== false || strpos($titulo, 'am4') !== false
                || strpos($titulo, 'am5') !== false || strpos($titulo, 'ryzen') !== false;

            if (strpos($socket, 'LGA') === 0) {
                return !($temAmd && !$temIntel);
            }
            return !($temIntel && !$temAmd);
        });

        $item = $itens[0] ?? null;
        if ($item === null) continue;
        if ($item['precoNum'] < $melhorPreco) {
            $melhorPreco = $item['precoNum'];
            $escolhido   = ['tipo' => $m[1], 'estilo' => $m[2]];
        }
    }
    return $escolhido;
}

/**
 * Preenche só as peças que faltam, mantendo o que o usuário já escolheu.
 * Sempre pega o item mais barato que combine com o restante do PC.
 *
 * @return array{0:array<string,string>,1:array<string,mixed>}
 */
function completarEntradaSetup(array $entrada, string $csvsDir, string $perfilBusca): array
{
    $socket = '';
    $ddr    = ($entrada['ram_ddr'] !== '') ? $entrada['ram_ddr'] : null;
    if ($entrada['placa_soquete'] !== '' && $entrada['placa_soquete'] !== 'todos') {
        $socket = strtoupper($entrada['placa_soquete']);
    }

    // 1. Processador (se o usuário não escolheu nenhum).
    if ($entrada['cpu_marca'] === '') {
        $arquivos = array_merge(
            glob($csvsDir . 'amd_ryzen_*.csv') ?: [],
            glob($csvsDir . 'intel_i*.csv') ?: []
        );
        $melhor = null;

        foreach ($arquivos as $arquivo) {
            $dadosCpu = dadosCpuDoArquivo($arquivo);
            if ($dadosCpu === null) continue;

            $info = socketEDdrDaCpu($dadosCpu['cpu_marca'], $dadosCpu['cpu_linha'], $dadosCpu['cpu_gen']);
            if ($socket !== '' && $info['socket'] !== $socket) continue;
            if ($ddr === 'ddr5' && !in_array($info['socket'], ['AM5', 'LGA 1700'], true)) continue;
            if ($ddr === 'ddr4' && $info['socket'] === 'AM5') continue;

            $itens = coletarProdutos([$arquivo]);
            $item  = $itens[0] ?? null;
            if ($item === null) continue;

            if ($melhor === null || $item['precoNum'] < $melhor['preco']) {
                $melhor = [
                    'preco' => $item['precoNum'],
                    'dados' => $dadosCpu,
                    'info'  => $info,
                ];
            }
        }

        if ($melhor !== null) {
            $entrada['cpu_marca'] = $melhor['dados']['cpu_marca'];
            $entrada['cpu_linha'] = $melhor['dados']['cpu_linha'];
            $entrada['cpu_gen']   = $melhor['dados']['cpu_gen'];
            $socket = $melhor['info']['socket'];
            if ($ddr === null) $ddr = $melhor['info']['ddr'];
        }
    }

    // 2. Soquete e placa-mãe.
    if ($socket === '') {
        $info = socketEDdrDaCpu($entrada['cpu_marca'], $entrada['cpu_linha'], $entrada['cpu_gen']);
        $socket = $info['socket'];
        if ($ddr === null) $ddr = $info['ddr'];
    }
    $ddrDaPlaca = escolherPlacaMae($csvsDir, $socket, $ddr);
    if ($ddrDaPlaca !== null) {
        $ddr = $ddrDaPlaca;
        $entrada['ram_ddr'] = $ddrDaPlaca;
    }
    if ($entrada['placa_soquete'] === '' || $entrada['placa_soquete'] === 'todos') {
        $entrada['placa_soquete'] = $socket;
    }

    // 3. Memória RAM.
    if ($entrada['ram_tipo'] === '') $entrada['ram_tipo'] = 'desktop';
    if ($entrada['ram_cap'] === '' && $entrada['ram_ddr'] !== '') {
        $cap = escolherRam($csvsDir, $entrada['ram_ddr'], null);
        if ($cap !== null) $entrada['ram_cap'] = $cap;
    }

    // 4. Armazenamento.
    if ($entrada['disco_tipo'] === '' || $entrada['disco_cap'] === '') {
        $disco = escolherArmazenamento($csvsDir);
        if ($disco !== null) {
            if ($entrada['disco_tipo'] === '') $entrada['disco_tipo'] = $disco['tipo'];
            if ($entrada['disco_cap'] === '')  $entrada['disco_cap']  = $disco['cap'];
        }
    }

    // 5. Placa de vídeo.
    $maxWatts = PHP_INT_MAX;
    if ($entrada['fonte_cap'] !== '' && $entrada['fonte_cap'] !== 'todos') {
        $maxWatts = normalizarCapacidadeFonte($entrada['fonte_cap']);
    }
    $gpu = escolherGpu($csvsDir, $maxWatts, $entrada['gpu_marca']);
    if ($gpu !== null && $entrada['gpu_marca'] === '') {
        $entrada['gpu_marca'] = $gpu['marca'];
    }
    $gpuWatts = $gpu['watts'] ?? 150;

    // 6. Fonte.
    if ($entrada['fonte_cap'] === '' || $entrada['fonte_cap'] === 'todos') {
        $necessaria = estimarWattsCpu($entrada['cpu_marca'], $entrada['cpu_linha'], $entrada['cpu_gen'])
            + $gpuWatts + 220;
        $fonte = escolherFonte($csvsDir, $necessaria);
        if ($fonte !== null) $entrada['fonte_cap'] = $fonte;
    }

    // 7. Gabinete.
    if ($entrada['gabinete_tipo'] === '' || $entrada['gabinete_tipo'] === 'todos') {
        $gabinete = escolherGabinete($csvsDir);
        if ($gabinete !== null) $entrada['gabinete_tipo'] = $gabinete;
    }

    // 8. Cooler.
    if ($entrada['cooler_tipo'] === '' && $entrada['cooler_estilo'] === '') {
        $cooler = escolherCooler($csvsDir, $socket);
        if ($cooler !== null) {
            $entrada['cooler_tipo']  = $cooler['tipo'];
            $entrada['cooler_estilo'] = $cooler['estilo'];
        }
    }

    return [
        $entrada,
        [
            'socket'    => $socket,
            'ddr'       => $ddr,
            'watts_max' => $maxWatts === PHP_INT_MAX ? null : $maxWatts,
        ],
    ];
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
function montarPecasParaCotar(array $entrada, string $csvsDir, bool $modoSetup, string $perfilBusca, array $compatibilidade = []): array
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
            'filtro'  => 'itemEhMemoriaRam',
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

        $pecasParaCotar['gpu'] = [
            'rotulo'  => 'Placa de Vídeo',
            'termo'   => $termoGpu,
            'padroes' => $padroesGpu,
            'exigir'  => ['placa'],
            'filtro'  => function ($item) {
                $titulo = mb_strtolower($item['titulo']);
                return !preg_match('/ventoinha|fan|suporte|cooler de placa/', $titulo);
            },
        ];
        if (!empty($compatibilidade['watts_max'])) {
            $pecasParaCotar['gpu']['watts_max'] = (int) $compatibilidade['watts_max'];
        }
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
        if (!empty($compatibilidade['ddr'])) {
            $pecasParaCotar['placa_mae']['exigir'] = [$compatibilidade['ddr']];
        }
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
        if (!empty($compatibilidade['socket'])) {
            $socketInfo = $compatibilidade['socket'];
            $pecasParaCotar['cooler']['filtro'] = function ($item) use ($socketInfo) {
                $titulo   = mb_strtolower($item['titulo']);
                $temIntel = strpos($titulo, 'intel') !== false || strpos($titulo, 'lga') !== false;
                $temAmd   = strpos($titulo, 'amd') !== false || strpos($titulo, 'am4') !== false
                    || strpos($titulo, 'am5') !== false || strpos($titulo, 'ryzen') !== false;

                if (strpos($socketInfo, 'LGA') === 0) {
                    return !($temAmd && !$temIntel);
                }
                return !($temIntel && !$temAmd);
            };
        }
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
function calcularCotacao(array $get, string $csvsDir, bool $modoSetup, string $perfilBusca, bool $completarSetup = false): array
{
    $compatibilidade = [];
    $entrada         = normalizarEntrada($get, $modoSetup, $perfilBusca, !$completarSetup);

    if ($completarSetup && $modoSetup) {
        [$entrada, $compatibilidade] = completarEntradaSetup($entrada, $csvsDir, $perfilBusca);
    }

    $pecasParaCotar = montarPecasParaCotar($entrada, $csvsDir, $modoSetup, $perfilBusca, $compatibilidade);

    $resultadosPorPeca = [];
    $totalSoma         = 0.0;
    $pecasComFalta     = 0;
    $ultimaAtualizacao = 0;

    foreach (glob($csvsDir . '*.csv') ?: [] as $arquivoCsv) {
        $ultimaAtualizacao = max($ultimaAtualizacao, (int) @filemtime($arquivoCsv));
    }

    foreach ($pecasParaCotar as $chave => $dados) {
        $itens = coletarProdutos($dados['padroes'], function ($item) use ($dados) {
            if (!empty($dados['exigir'])) {
                $titulo = mb_strtolower($item['titulo']);
                foreach ($dados['exigir'] as $trecho) {
                    if (strpos($titulo, mb_strtolower($trecho)) === false) return false;
                }
            }
            if (isset($dados['filtro']) && !call_user_func($dados['filtro'], $item)) return false;
            if (!empty($dados['watts_max']) && estimarWattsGpu($item['titulo']) > (int) $dados['watts_max']) {
                return false;
            }
            return true;
        });

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
        'pecas'            => $pecasParaCotar,
        'resultados'       => $resultadosPorPeca,
        'total'            => $totalSoma,
        'faltantes'        => $pecasComFalta,
        'atualizacao'      => $ultimaAtualizacao,
        'entrada'          => $entrada,
        'compatibilidade'  => $compatibilidade,
    ];
}
