<?php
// ---------------------------------------------------------------------------
// Configuração geral do TechCota
// ---------------------------------------------------------------------------

$nomeSistema   = 'TechCota';
$sloganSistema = 'Consultoria & Cotação de Hardware';
$siglaSistema  = 'TC';
$anoAtual      = date('Y');

// ---------------------------------------------------------------------------
// Contatos (rodapé)
// LinkedIn/Instagram aceitam o handle ou a URL completa.
// WhatsApp: apenas números com DDI/DDD.
// ---------------------------------------------------------------------------
$contatos = [
    [
        'nome'      => 'Ítalo Oliveira',
        'linkedin'  => 'https://www.linkedin.com/in/italooresende/',
        'instagram' => 'https://www.instagram.com/italoliveires/?hl=pt-br',
        'whatsapp'  => '5531984534766',
        'email'     => 'italoliveira5@gmail.com',
    ],
    [
        'nome'      => 'Igor Quintino',
        'linkedin'  => 'https://www.linkedin.com/in/quintino-igor/',
        'instagram' => 'https://www.instagram.com/igorcquintino/?hl=pt-br',
        'whatsapp'  => '5531989487756',
        'email'     => 'igorcquintino@hotmail.com',
    ],
];

$mensagemContato = 'Olá! Vim pelo site ' . $nomeSistema . ' e gostaria de falar sobre um orçamento.';
$assuntoContato  = 'Contato pelo site ' . $nomeSistema;

// ---------------------------------------------------------------------------
// Preços para o cliente final
// ---------------------------------------------------------------------------
// O admin vê o custo real das peças. O cliente vê o custo + os valores abaixo.
// Estes valores podem ser alterados no painel administrativo (admin.php) e
// ficam salvos em dados/precos.json, compartilhados entre os administradores.
//   margem_peca_pct -> % de lucro em cima do custo de cada peça
//   lucro_min_peca  -> lucro mínimo em reais por peça (0 = sem mínimo)
//   lucro_max_peca  -> lucro máximo em reais por peça (0 = sem máximo)
//   montagem_pc     -> valor fixo cobrado para montar/testar/embalar o PC
//   frete_pc        -> frete estimado do PC montado (0 = "a combinar")
$configPrecos = [
    'margem_peca_pct' => 20.0,
    'lucro_min_peca'  => 10.00,
    'lucro_max_peca'  => 150.00,
    'montagem_pc'     => 150.00,
    'frete_pc'        => 0.00,
];

// Carrega os preços salvos pelo painel admin, se existirem.
$arquivoPrecos = __DIR__ . '/../dados/precos.json';
if (is_file($arquivoPrecos)) {
    $precosSalvos = json_decode((string) @file_get_contents($arquivoPrecos), true);
    if (is_array($precosSalvos)) {
        foreach (['margem_peca_pct', 'lucro_min_peca', 'lucro_max_peca', 'montagem_pc', 'frete_pc'] as $chavePreco) {
            if (isset($precosSalvos[$chavePreco]) && is_numeric($precosSalvos[$chavePreco])) {
                $configPrecos[$chavePreco] = (float) $precosSalvos[$chavePreco];
            }
        }
    }
}

/** Lucro em reais sobre o custo de uma peça, respeitando piso e teto. */
function lucroPecasCliente(float $custo): float
{
    global $configPrecos;

    $lucro = $custo * (float) $configPrecos['margem_peca_pct'] / 100;
    $minimo = (float) ($configPrecos['lucro_min_peca'] ?? 0);
    $maximo = (float) ($configPrecos['lucro_max_peca'] ?? 0);

    if ($minimo > 0) $lucro = max($lucro, $minimo);
    if ($maximo > 0) $lucro = min($lucro, $maximo);

    return round($lucro, 2);
}

/** Preço de venda das peças (custo + lucro por peça). */
function precoPecasComLucro(float $custo): float
{
    return round($custo + lucroPecasCliente($custo), 2);
}

/** Preço de venda de peça(s) avulsa(s). */
function precoPecaCliente(float $custo): float
{
    return precoPecasComLucro($custo);
}

/** Preço de venda de um PC completo (peças + lucro + montagem + frete). */
function precoPcCliente(float $custo): float
{
    global $configPrecos;
    return round(
        precoPecasComLucro($custo)
        + (float) $configPrecos['montagem_pc']
        + (float) $configPrecos['frete_pc'],
        2
    );
}

/** Salva os preços no arquivo usado pelo painel admin. */
function salvarConfigPrecos(array $dados): bool
{
    $arquivo = __DIR__ . '/../dados/precos.json';
    $pasta = dirname($arquivo);
    if (!is_dir($pasta)) {
        @mkdir($pasta, 0775, true);
    }

    $json = json_encode([
        'margem_peca_pct' => round((float) $dados['margem_peca_pct'], 2),
        'lucro_min_peca'  => round((float) $dados['lucro_min_peca'], 2),
        'lucro_max_peca'  => round((float) $dados['lucro_max_peca'], 2),
        'montagem_pc'     => round((float) $dados['montagem_pc'], 2),
        'frete_pc'        => round((float) $dados['frete_pc'], 2),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    return $json !== false && @file_put_contents($arquivo, $json, LOCK_EX) !== false;
}

function formatarPreco(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// ---------------------------------------------------------------------------
// Endereço de montagem
// ---------------------------------------------------------------------------
// O endereço agora é cadastrado pelo administrador no painel de pedidos
// (admin.php) e fica salvo no banco de dados, compartilhado entre todos os admins.

// ---------------------------------------------------------------------------
// Administradores iniciais (criados automaticamente no primeiro acesso)
// Troque a senha em "Minha conta" após o primeiro login.
// ---------------------------------------------------------------------------
$adminsIniciais = [
    [
        'nome'       => 'Ítalo Oliveira',
        'email'      => 'italoliveira5@gmail.com',
        'senha_hash' => '$2y$10$DXTV1YVs6MFo39JTfGHCvuXU3GRnEmgAzDaUpHJhpNrHSNIMVTzTe',
    ],
    [
        'nome'       => 'Igor Quintino',
        'email'      => 'igorcquintino@hotmail.com',
        'senha_hash' => '$2y$10$DXTV1YVs6MFo39JTfGHCvuXU3GRnEmgAzDaUpHJhpNrHSNIMVTzTe',
    ],
];

// ---------------------------------------------------------------------------
// Helpers de redes sociais
// ---------------------------------------------------------------------------
function urlLinkedin($valor)
{
    $valor = trim((string) $valor);
    if ($valor === '') return '';
    if (preg_match('~^https?://~i', $valor)) return $valor;
    return 'https://www.linkedin.com/in/' . rawurlencode(trim($valor, '/'));
}

function urlInstagram($valor)
{
    $valor = trim((string) $valor);
    if ($valor === '') return '';
    if (preg_match('~^https?://~i', $valor)) return $valor;
    return 'https://www.instagram.com/' . rawurlencode(trim($valor, '/'));
}

function urlWhatsapp($valor, $mensagem)
{
    $numero = preg_replace('/\D/', '', (string) $valor);
    if ($numero === '') return '';
    return 'https://wa.me/' . $numero . '?text=' . rawurlencode($mensagem);
}

function urlEmail($email, $assunto, $corpo = '')
{
    $email = trim((string) $email);
    if ($email === '') return '';
    return 'https://mail.google.com/mail/?view=cm&fs=1'
        . '&to=' . rawurlencode($email)
        . '&su=' . rawurlencode($assunto)
        . ($corpo !== '' ? '&body=' . rawurlencode($corpo) : '');
}

$iconesRedes = [
    'linkedin'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M4.98 3.5a2.5 2.5 0 1 1-4.96 0 2.5 2.5 0 0 1 4.96 0ZM.5 8h4v16h-4V8Zm7.5 0h3.8v2.2h.05c.53-1 1.83-2.2 3.77-2.2 4.03 0 4.78 2.65 4.78 6.1V24h-4v-8.5c0-2.03-.04-4.64-2.83-4.64-2.83 0-3.27 2.2-3.27 4.5V24h-4V8Z"/></svg>',
    'instagram' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2.5" y="2.5" width="19" height="19" rx="5"/><circle cx="12" cy="12" r="4.2"/><circle cx="17.6" cy="6.4" r="1.2" fill="currentColor" stroke="none"/></svg>',
    'whatsapp'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.6 2 2.2 6.4 2.2 11.84c0 1.74.46 3.44 1.32 4.94L2 22l5.34-1.4a9.8 9.8 0 0 0 4.7 1.2h.01c5.43 0 9.84-4.4 9.84-9.84C21.89 6.4 17.48 2 12.04 2Zm5.77 14.06c-.24.68-1.42 1.3-1.96 1.35-.54.06-1.05.08-1.81-.11-.45-.11-1.03-.33-1.77-.65-3.11-1.34-5.14-4.47-5.3-4.68-.15-.2-1.26-1.68-1.26-3.2 0-1.53.8-2.28 1.08-2.6.28-.31.61-.39.82-.39.2 0 .41 0 .59.01.19.01.44-.07.69.53.25.6.85 2.08.93 2.23.07.15.12.33.02.53-.1.2-.2.33-.4.51-.2.18-.42.4-.6.54-.2.2-.41.42-.18.82.23.4 1.02 1.68 2.18 2.72 1.5 1.33 2.76 1.74 3.16 1.94.4.2.63.17.86-.1.23-.28.99-1.16 1.25-1.56.26-.4.53-.33.89-.2.36.13 2.3 1.08 2.7 1.28.4.2.66.3.76.47.1.16.1.96-.14 1.64Z"/></svg>',
    'email'     => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m3 6 9 7 9-7"/></svg>',
];
