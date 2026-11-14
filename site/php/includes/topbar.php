<?php
// Barra superior fixa. Espera config.php e auth.php já carregados.
$__usuario = usuarioAtual();
?>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="index.php" aria-label="<?= e($nomeSistema) ?> — página inicial">
            <span class="brand-logo"><?= e($siglaSistema) ?></span>
            <span class="brand-text">
                <span class="brand-name"><?= e($nomeSistema) ?></span>
                <span class="brand-tag"><?= e($sloganSistema) ?></span>
            </span>
        </a>

        <nav class="auth-nav" aria-label="Conta">
            <?php if ($__usuario): ?>
                <?php if (ehAdmin()): ?>
                    <span class="auth-badge">Admin</span>
                <?php endif; ?>
                <a class="auth-user" href="index.php">Olá, <?= e(explode(' ', trim($__usuario['nome']))[0]) ?></a>
                <a href="conta.php">Minha conta</a>
                <?php if (ehAdmin()): ?>
                    <a href="admin.php">Pedidos</a>
                <?php else: ?>
                    <a href="pedidos.php">Pedidos</a>
                <?php endif; ?>
                <form method="post" action="logout.php" class="logout-form">
                    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                    <button type="submit" class="link-btn">Sair</button>
                </form>
            <?php else: ?>
                <a href="login.php">Entrar</a>
                <a href="cadastro.php" class="btn-mini">Criar conta</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
