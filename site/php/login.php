<?php
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/db.php';

if (usuarioAtual()) {
    redirecionar('conta.php');
}

$next  = destinoSeguro($_GET['next'] ?? $_POST['next'] ?? 'index.php');
$erro  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['csrf'] ?? null)) {
        $erro = 'Sessão expirada. Tente novamente.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $senha = (string) ($_POST['senha'] ?? '');
        $usuario = buscarUsuarioPorEmail($email);

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            entrarUsuario($usuario);
            flash('sucesso', 'Bem-vindo(a), ' . $usuario['nome'] . '!');
            redirecionar($next);
        } else {
            $erro = 'E-mail ou senha inválidos.';
        }
    }
}

renderHead('Entrar');
?>
<main class="pagina-wrap pagina-estreita">
    <div class="card-painel">
        <h1>Entrar</h1>
        <p class="card-sub">
            Acesse sua conta para comprar. Administradores veem aqui os custos reais das peças.
        </p>

        <?php renderFlashes(); ?>
        <?php if ($erro !== ''): ?>
            <div class="alerta alerta-erro"><?= e($erro) ?></div>
        <?php endif; ?>

        <form method="post" class="form-padrao">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="next" value="<?= e($next) ?>">

            <label class="campo">
                <span>E-mail</span>
                <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required autofocus>
            </label>

            <label class="campo">
                <span>Senha</span>
                <input type="password" name="senha" autocomplete="current-password" required>
            </label>

            <button type="submit" class="btn-primario btn-bloco">Entrar</button>
        </form>

        <p class="card-rodape">
            Ainda não tem conta?
            <a href="cadastro.php?next=<?= e(urlencode($next)) ?>">Criar conta</a>
        </p>
    </div>
</main>
<?php renderFooter(); ?>
