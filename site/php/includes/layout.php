<?php
// ---------------------------------------------------------------------------
// Layout compartilhado das páginas internas (login, cadastro, conta, etc.)
// ---------------------------------------------------------------------------

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

function renderHead(string $titulo, string $descricao = ''): void
{
    global $nomeSistema, $sloganSistema, $siglaSistema;

    if ($descricao === '') {
        $descricao = $nomeSistema . ' — ' . $sloganSistema;
    }
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="<?= e($descricao) ?>">
        <meta name="theme-color" content="#0b1120">
        <link rel="stylesheet" href="../css/style.css">
        <title><?= e($titulo) ?> — <?= e($nomeSistema) ?></title>
    </head>
    <body>
    <?php require __DIR__ . '/topbar.php'; ?>
    <?php
}

function renderFooter(): void
{
    global $contatos, $mensagemContato, $assuntoContato, $iconesRedes,
           $anoAtual, $nomeSistema, $siglaSistema;

    require __DIR__ . '/footer.php';
    ?>
    <script src="../js/formularios.js"></script>
    <script>
    // Best-effort: o link abre em nova guia e tentamos devolver o foco para esta.
    // Quem decide é o navegador; em alguns ele ignora essa chamada.
    document.querySelectorAll('.social-link').forEach(function (link) {
        link.addEventListener('click', function () {
            setTimeout(function () { try { window.focus(); } catch (err) {} }, 80);
        });
    });
    </script>
    <?php
    echo "\n</body>\n</html>\n";
}

function renderFlashes(): void
{
    foreach (consumirFlashes() as $f) {
        $tipo = in_array($f['tipo'], ['sucesso', 'erro', 'aviso', 'info'], true) ? $f['tipo'] : 'info';
        echo '<div class="alerta alerta-' . e($tipo) . '">' . e($f['msg']) . '</div>';
    }
}
