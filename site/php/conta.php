<?php
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/db.php';

exigirLogin();

$usuario = buscarUsuarioPorId((int) usuarioAtual()['id']);
if (!$usuario) {
    sairUsuario();
    redirecionar('login.php');
}

$erroDados = '';
$erroSenha = '';
$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['csrf'] ?? null)) {
        $erroDados = $erroSenha = 'Sessão expirada. Tente novamente.';
    } else {
        $acao = $_POST['acao'] ?? '';

        if ($acao === 'dados') {
            $nome  = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if ($nome === '') {
                $erroDados = 'Informe seu nome.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $erroDados = 'Informe um e-mail válido.';
            } else {
                $outro = buscarUsuarioPorEmail($email);
                if ($outro && (int) $outro['id'] !== (int) $usuario['id']) {
                    $erroDados = 'Este e-mail já está em uso por outra conta.';
                }
            }

            if ($erroDados === '') {
                try {
                    atualizarUsuario((int) $usuario['id'], [
                        'nome'        => $nome,
                        'email'       => $email,
                        'telefone'    => trim($_POST['telefone'] ?? ''),
                        'cpf'         => trim($_POST['cpf'] ?? ''),
                        'cep'         => trim($_POST['cep'] ?? ''),
                        'endereco'    => trim($_POST['endereco'] ?? ''),
                        'numero'      => trim($_POST['numero'] ?? ''),
                        'complemento' => trim($_POST['complemento'] ?? ''),
                        'bairro'      => trim($_POST['bairro'] ?? ''),
                        'cidade'      => trim($_POST['cidade'] ?? ''),
                        'estado'      => strtoupper(trim($_POST['estado'] ?? '')),
                    ]);

                    $_SESSION['usuario']['nome']  = $nome;
                    $_SESSION['usuario']['email'] = strtolower($email);
                    flash('sucesso', 'Dados atualizados.');
                    redirecionar('conta.php');
                } catch (Throwable $ex) {
                    $erroDados = 'Não foi possível salvar os dados. Tente novamente.';
                }
            }
        }

        if ($acao === 'senha') {
            $atual   = (string) ($_POST['senha_atual'] ?? '');
            $nova    = (string) ($_POST['senha_nova'] ?? '');
            $confirm = (string) ($_POST['senha_confirmar'] ?? '');

            if (!password_verify($atual, $usuario['senha'])) {
                $erroSenha = 'A senha atual está incorreta.';
            } elseif (mb_strlen($nova) < 6) {
                $erroSenha = 'A nova senha deve ter pelo menos 6 caracteres.';
            } elseif ($nova !== $confirm) {
                $erroSenha = 'As senhas novas não conferem.';
            } else {
                atualizarSenha((int) $usuario['id'], $nova);
                flash('sucesso', 'Senha alterada com sucesso.');
                redirecionar('conta.php');
            }
        }

        // Recarrega para refletir possíveis alterações
        $usuario = buscarUsuarioPorId((int) $usuario['id']);
    }
}

// Valores exibidos no formulário de dados (mantém o que foi digitado em caso de erro)
$form = $usuario;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'dados' && $erroDados !== '') {
    foreach (['nome','email','telefone','cpf','cep','endereco','numero','complemento','bairro','cidade','estado'] as $campo) {
        $form[$campo] = trim($_POST[$campo] ?? '');
    }
}

renderHead('Minha conta');
?>
<main class="pagina-wrap">
    <div class="conta-topo">
        <div>
            <h1>Minha conta</h1>
            <p class="card-sub">Olá, <?= e($usuario['nome']) ?>. Gerencie seus dados pessoais e sua senha.</p>
        </div>
    </div>

    <?php renderFlashes(); ?>

    <div class="cards-duplos">
        <section class="card-painel">
            <h2>Meus dados</h2>
            <p class="card-sub">Usados no cadastro e no preenchimento automático do checkout.</p>

            <?php if ($erroDados !== ''): ?>
                <div class="alerta alerta-erro"><?= e($erroDados) ?></div>
            <?php endif; ?>

            <form method="post" class="form-padrao">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="acao" value="dados">

                <div class="form-grid">
                    <label class="campo campo-largo">
                        <span>Nome completo</span>
                        <input type="text" name="nome" value="<?= e($form['nome']) ?>" required>
                    </label>
                    <label class="campo">
                        <span>E-mail</span>
                        <input type="email" name="email" value="<?= e($form['email']) ?>" required>
                    </label>
                    <label class="campo">
                        <span>Telefone / WhatsApp</span>
                        <input type="tel" name="telefone" value="<?= e($form['telefone']) ?>">
                    </label>
                    <label class="campo">
                        <span>CPF (opcional)</span>
                        <input type="text" name="cpf" value="<?= e($form['cpf']) ?>" inputmode="numeric">
                    </label>
                    <label class="campo">
                        <span>CEP</span>
                        <input type="text" name="cep" value="<?= e($form['cep']) ?>" inputmode="numeric">
                    </label>
                    <label class="campo campo-largo">
                        <span>Endereço</span>
                        <input type="text" name="endereco" value="<?= e($form['endereco']) ?>">
                    </label>
                    <label class="campo">
                        <span>Número</span>
                        <input type="text" name="numero" value="<?= e($form['numero']) ?>">
                    </label>
                    <label class="campo">
                        <span>Complemento</span>
                        <input type="text" name="complemento" value="<?= e($form['complemento']) ?>">
                    </label>
                    <label class="campo">
                        <span>Bairro</span>
                        <input type="text" name="bairro" value="<?= e($form['bairro']) ?>">
                    </label>
                    <label class="campo">
                        <span>Cidade</span>
                        <input type="text" name="cidade" value="<?= e($form['cidade']) ?>">
                    </label>
                    <label class="campo">
                        <span>UF</span>
                        <select name="estado">
                            <option value="">Selecione</option>
                            <?php foreach ($ufs as $uf): ?>
                                <option value="<?= e($uf) ?>" <?= ($form['estado'] === $uf) ? 'selected' : '' ?>><?= e($uf) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <button type="submit" class="btn-primario">Salvar dados</button>
            </form>
        </section>

        <section class="card-painel">
            <h2>Alterar senha</h2>
            <p class="card-sub">Use uma senha forte e não compartilhe com ninguém.</p>

            <?php if ($erroSenha !== ''): ?>
                <div class="alerta alerta-erro"><?= e($erroSenha) ?></div>
            <?php endif; ?>

            <form method="post" class="form-padrao">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="acao" value="senha">

                <label class="campo">
                    <span>Senha atual</span>
                    <input type="password" name="senha_atual" autocomplete="current-password" required>
                </label>
                <label class="campo">
                    <span>Nova senha</span>
                    <input type="password" name="senha_nova" autocomplete="new-password" minlength="6" required>
                </label>
                <label class="campo">
                    <span>Confirmar nova senha</span>
                    <input type="password" name="senha_confirmar" autocomplete="new-password" minlength="6" required>
                </label>

                <button type="submit" class="btn-secundario">Alterar senha</button>
            </form>
        </section>
    </div>
</main>
<?php renderFooter(); ?>
