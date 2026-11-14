<?php
// Rodapé do site. Espera config.php carregado.
?>
<footer class="site-footer" id="contato">
    <div class="footer-inner">
        <div class="footer-col footer-brand">
            <a class="brand" href="index.php" aria-label="<?= e($nomeSistema) ?> — página inicial">
                <span class="brand-logo"><?= e($siglaSistema) ?></span>
                <span class="brand-name"><?= e($nomeSistema) ?></span>
            </a>
            <p>Comparador de preços de hardware: encontre a peça mais barata nas principais lojas e monte seu setup com economia.</p>
        </div>

        <?php foreach ($contatos as $pessoa): ?>
            <div class="footer-col">
                <h4><?= e($pessoa['nome']) ?></h4>
                <div class="social-links">
                    <?php
                    $linksPessoa = [
                        'linkedin'  => ['rotulo' => 'LinkedIn',  'url' => urlLinkedin($pessoa['linkedin'])],
                        'instagram' => ['rotulo' => 'Instagram', 'url' => urlInstagram($pessoa['instagram'])],
                        'whatsapp'  => ['rotulo' => 'WhatsApp',  'url' => urlWhatsapp($pessoa['whatsapp'], $mensagemContato)],
                        'email'     => ['rotulo' => 'E-mail',    'url' => urlEmail($pessoa['email'], $assuntoContato, $mensagemContato)],
                    ];
                    ?>
                    <?php foreach ($linksPessoa as $tipo => $link): ?>
                        <?php if ($link['url'] !== ''): ?>
                            <a class="social-link social-<?= $tipo ?>"
                               href="<?= e($link['url']) ?>"
                               target="_blank" rel="noopener"
                               title="<?= e($link['rotulo']) ?> de <?= e($pessoa['nome']) ?>">
                                <?= $iconesRedes[$tipo] ?>
                                <span><?= e($link['rotulo']) ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="footer-bottom">
        &copy; <?= $anoAtual ?> <strong><?= e($nomeSistema) ?></strong> — Consultoria &amp; Cotação de Hardware. Todos os direitos reservados.
    </div>
</footer>
