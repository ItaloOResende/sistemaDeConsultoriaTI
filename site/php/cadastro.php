<?php
// BF-TEMP: site sem login até depois da Black Friday. Remover estas 2 linhas para restaurar.
header('Location: index.php');
exit;

require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/db.php';

if (usuarioAtual()) {
    redirecionar('conta.php');
}

$next = destinoSeguro($_GET['next'] ?? $_POST['next'] ?? 'conta.php');
$erro = '';

$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

$valor = function (string $campo): string {
    return e($_POST[$campo] ?? '');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['csrf'] ?? null)) {
        $erro = 'Sessão expirada. Tente novamente.';
    } else {
        $obrigatorios = ['nome', 'email', 'telefone', 'cep', 'endereco', 'numero', 'bairro', 'cidade', 'estado', 'senha', 'confirmar'];
        foreach ($obrigatorios as $campo) {
            if (trim((string) ($_POST[$campo] ?? '')) === '') {
                $erro = 'Preencha todos os campos obrigatórios.';
                break;
            }
        }

        $email = trim($_POST['email'] ?? '');
        $senha = (string) ($_POST['senha'] ?? '');
        $confirmar = (string) ($_POST['confirmar'] ?? '');

        if ($erro === '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe um e-mail válido.';
        }
        if ($erro === '' && preg_match('/^\d{5}-?\d{3}$/', trim((string) ($_POST['cep'] ?? ''))) !== 1) {
            $erro = 'Informe um CEP válido.';
        }
        if ($erro === '' && mb_strlen($senha) < 6) {
            $erro = 'A senha deve ter pelo menos 6 caracteres.';
        }
        if ($erro === '' && $senha !== $confirmar) {
            $erro = 'As senhas não conferem.';
        }
        if ($erro === '' && buscarUsuarioPorEmail($email) !== null) {
            $erro = 'Já existe uma conta com este e-mail. Faça login.';
        }

        if ($erro === '') {
            try {
                $id = criarUsuario([
                    'nome'        => trim($_POST['nome'] ?? ''),
                    'email'       => $email,
                    'senha'       => $senha,
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

                $u = buscarUsuarioPorId($id);
                if ($u) {
                    entrarUsuario($u);
                    flash('sucesso', 'Conta criada com sucesso. Bem-vindo(a), ' . $u['nome'] . '!');
                    redirecionar($next);
                }
            } catch (Throwable $ex) {
                $erro = 'Não foi possível criar a conta. Tente novamente.';
            }
        }
    }
}

renderHead('Criar conta');
?>
<main class="pagina-wrap">
    <div class="card-painel card-largo">
        <h1>Criar conta</h1>
        <p class="card-sub">
            Seus dados ficam salvos para agilizar a compra. O endereço informado é o endereço de entrega do PC montado ou das peças.
        </p>

        <?php renderFlashes(); ?>
        <?php if ($erro !== ''): ?>
            <div class="alerta alerta-erro"><?= e($erro) ?></div>
        <?php endif; ?>

        <form method="post" class="form-padrao">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="next" value="<?= e($next) ?>">

            <h2 class="form-secao">Dados de acesso</h2>
            <div class="form-grid">
                <label class="campo campo-largo">
                    <span>Nome completo *</span>
                    <input type="text" name="nome" value="<?= $valor('nome') ?>" autocomplete="name" required>
                </label>
                <label class="campo">
                    <span>E-mail *</span>
                    <input type="email" name="email" value="<?= $valor('email') ?>" autocomplete="email" required>
                </label>
                <label class="campo">
                    <span>Telefone / WhatsApp *</span>
                    <input type="tel" name="telefone" value="<?= $valor('telefone') ?>" placeholder="(31) 99999-9999" autocomplete="tel" required>
                </label>
                <label class="campo">
                    <span>CPF (opcional)</span>
                    <input type="text" name="cpf" value="<?= $valor('cpf') ?>" placeholder="000.000.000-00" inputmode="numeric">
                </label>
                <label class="campo">
                    <span>Senha *</span>
                    <input type="password" name="senha" autocomplete="new-password" minlength="6" required>
                </label>
                <label class="campo">
                    <span>Confirmar senha *</span>
                    <input type="password" name="confirmar" autocomplete="new-password" minlength="6" required>
                </label>
            </div>

            <h2 class="form-secao">Endereço de entrega</h2>
            <div class="form-grid">
                <label class="campo">
                    <span>CEP *</span>
                    <input type="text" name="cep" value="<?= $valor('cep') ?>" placeholder="00000-000" inputmode="numeric" autocomplete="postal-code" data-cep required>
                </label>
                <label class="campo campo-largo">
                    <span>Endereço *</span>
                    <input type="text" name="endereco" value="<?= $valor('endereco') ?>" placeholder="Rua, avenida..." autocomplete="address-line1" required>
                </label>
                <label class="campo">
                    <span>Número *</span>
                    <input type="text" name="numero" value="<?= $valor('numero') ?>" autocomplete="address-line2" required>
                </label>
                <label class="campo">
                    <span>Complemento</span>
                    <input type="text" name="complemento" value="<?= $valor('complemento') ?>" placeholder="Apto, bloco..." autocomplete="address-line3">
                </label>
                <label class="campo">
                    <span>Bairro *</span>
                    <input type="text" name="bairro" value="<?= $valor('bairro') ?>" autocomplete="address-level3" required>
                </label>
                <label class="campo">
                    <span>Cidade *</span>
                    <input type="text" name="cidade" value="<?= $valor('cidade') ?>" autocomplete="address-level2" required>
                </label>
                <label class="campo">
                    <span>UF *</span>
                    <select name="estado" required>
                        <option value="">Selecione</option>
                        <?php foreach ($ufs as $uf): ?>
                            <option value="<?= e($uf) ?>" <?= (($_POST['estado'] ?? '') === $uf) ? 'selected' : '' ?>><?= e($uf) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <button type="submit" class="btn-primario btn-bloco">Criar conta</button>
        </form>

        <p class="card-rodape">Já tem conta? <a href="login.php?next=<?= e(urlencode($next)) ?>">Entrar</a></p>
    </div>
</main>
<?php renderFooter(); ?>
