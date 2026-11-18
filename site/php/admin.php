<?php
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/db.php';

exigirAdmin();

$erroEndereco = '';
$dadosEndereco = null;
$erroPrecos = '';
$dadosPrecos = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'endereco') {
    if (!csrfValido($_POST['csrf'] ?? null)) {
        $erroEndereco = 'Sessão expirada. Tente novamente.';
    } else {
        $dadosEndereco = [
            'responsavel' => trim($_POST['responsavel'] ?? ''),
            'cep'         => trim($_POST['cep'] ?? ''),
            'endereco'    => trim($_POST['endereco'] ?? ''),
            'numero'      => trim($_POST['numero'] ?? ''),
            'complemento' => trim($_POST['complemento'] ?? ''),
            'bairro'      => trim($_POST['bairro'] ?? ''),
            'cidade'      => trim($_POST['cidade'] ?? ''),
            'estado'      => strtoupper(trim($_POST['estado'] ?? '')),
        ];

        foreach (['responsavel', 'cep', 'endereco', 'numero', 'bairro', 'cidade', 'estado'] as $campo) {
            if ($dadosEndereco[$campo] === '') {
                $erroEndereco = 'Preencha todos os campos obrigatórios do endereço de montagem.';
                break;
            }
        }

        if ($erroEndereco === '') {
            try {
                salvarEnderecoMontagem($dadosEndereco);
                flash('sucesso', 'Endereço de montagem atualizado.');
                redirecionar('admin.php');
            } catch (Throwable $ex) {
                $erroEndereco = 'Não foi possível salvar o endereço. Tente novamente.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'precos') {
    if (!csrfValido($_POST['csrf'] ?? null)) {
        $erroPrecos = 'Sessão expirada. Tente novamente.';
    } else {
        $dadosPrecos = [];
        foreach (['margem_peca_pct', 'lucro_min_peca', 'lucro_max_peca', 'montagem_pc', 'frete_pc'] as $campoPreco) {
            $valor = str_replace(',', '.', trim((string) ($_POST[$campoPreco] ?? '')));
            if ($valor === '' || !is_numeric($valor) || (float) $valor < 0) {
                $erroPrecos = 'Informe valores numéricos maiores ou iguais a zero.';
                break;
            }
            $dadosPrecos[$campoPreco] = (float) $valor;
        }

        if ($erroPrecos === '') {
            if (salvarConfigPrecos($dadosPrecos)) {
                flash('sucesso', 'Preços e lucro atualizados.');
                redirecionar('admin.php');
            } else {
                $erroPrecos = 'Não foi possível salvar. Verifique a permissão da pasta dados.';
            }
        }
    }
}

$enderecoMontagem = obterEnderecoMontagem();
if (is_array($dadosEndereco) && $erroEndereco !== '') {
    $enderecoMontagem = $dadosEndereco;
}
if (is_array($dadosPrecos) && $erroPrecos !== '') {
    $configPrecos = array_merge($configPrecos, $dadosPrecos);
}

$statusValidos = ['aguardando', 'pago', 'comprando', 'montando', 'enviado', 'entregue', 'cancelado'];

$todos = listarTodosPedidos();

$faturamento = 0.0;
$lucroTotal  = 0.0;
$pendentes   = 0;
$producao    = 0;
foreach ($todos as $p) {
    if ($p['status'] === 'cancelado') continue;
    $faturamento += (float) $p['total'];
    $lucroTotal  += (float) $p['lucro'];
    if ($p['status'] === 'aguardando') $pendentes++;
    if (in_array($p['status'], ['pago', 'comprando', 'montando'], true)) $producao++;
}

$totalClientes = (int) obterConexao()->query("SELECT COUNT(*) FROM usuarios WHERE papel = 'cliente'")->fetchColumn();

$filtro = $_GET['status'] ?? '';
$pedidos = $todos;
if ($filtro !== '' && in_array($filtro, $statusValidos, true)) {
    $pedidos = array_values(array_filter($todos, fn($p) => $p['status'] === $filtro));
}

renderHead('Painel administrativo');
?>
<main class="pagina-wrap">
    <div class="conta-topo">
        <div>
            <h1>Painel de pedidos</h1>
            <p class="card-sub">Visão interna do <?= e($nomeSistema) ?>. O cliente não vê estes valores de custo.</p>
        </div>
    </div>

    <?php renderFlashes(); ?>

    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-label">Pedidos</span>
            <strong><?= count($todos) ?></strong>
        </div>
        <div class="stat-card">
            <span class="stat-label">Aguardando contato</span>
            <strong><?= $pendentes ?></strong>
        </div>
        <div class="stat-card">
            <span class="stat-label">Em produção</span>
            <strong><?= $producao ?></strong>
        </div>
        <div class="stat-card">
            <span class="stat-label">Clientes cadastrados</span>
            <strong><?= $totalClientes ?></strong>
        </div>
        <div class="stat-card destaque">
            <span class="stat-label">Faturamento previsto</span>
            <strong><?= formatarPreco($faturamento) ?></strong>
        </div>
        <div class="stat-card destaque-verde">
            <span class="stat-label">Lucro bruto previsto</span>
            <strong><?= formatarPreco($lucroTotal) ?></strong>
        </div>
    </div>

    <div class="cards-duplos">
        <section class="card-painel">
            <h2>Preços e lucro</h2>
            <p class="card-sub">O lucro por peça é uma porcentagem sobre o custo de cada componente. No PC completo, ele é somado à montagem.</p>

            <?php if ($erroPrecos !== ''): ?>
                <div class="alerta alerta-erro"><?= e($erroPrecos) ?></div>
            <?php endif; ?>

            <form method="post" class="form-padrao">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="acao" value="precos">

                <div class="form-grid">
                    <label class="campo">
                        <span>Lucro por peça (%)</span>
                        <input type="number" name="margem_peca_pct" min="0" step="0.01" value="<?= e(number_format((float) $configPrecos['margem_peca_pct'], 2, '.', '')) ?>" required>
                    </label>
                    <label class="campo">
                        <span>Lucro mínimo por peça (R$)</span>
                        <input type="number" name="lucro_min_peca" min="0" step="0.01" value="<?= e(number_format((float) $configPrecos['lucro_min_peca'], 2, '.', '')) ?>" required>
                    </label>
                    <label class="campo">
                        <span>Lucro máximo por peça (R$)</span>
                        <input type="number" name="lucro_max_peca" min="0" step="0.01" value="<?= e(number_format((float) $configPrecos['lucro_max_peca'], 2, '.', '')) ?>" required>
                    </label>
                    <label class="campo">
                        <span>Montagem do PC (R$)</span>
                        <input type="number" name="montagem_pc" min="0" step="0.01" value="<?= e(number_format((float) $configPrecos['montagem_pc'], 2, '.', '')) ?>" required>
                    </label>
                    <label class="campo">
                        <span>Frete do PC (R$)</span>
                        <input type="number" name="frete_pc" min="0" step="0.01" value="<?= e(number_format((float) $configPrecos['frete_pc'], 2, '.', '')) ?>" required>
                    </label>
                </div>

                <?php
                $exemploLucro = lucroPecasCliente(100.0);
                $exemploVenda = precoPecaCliente(100.0);
                $exemploCaroLucro = lucroPecasCliente(2686.0);
                $exemploCaroVenda = precoPecaCliente(2686.0);
                ?>
                <p class="card-sub">
                    Exemplo: peça de R$ 100,00 → lucro de R$ <?= number_format($exemploLucro, 2, ',', '.') ?>
                    → venda de R$ <?= number_format($exemploVenda, 2, ',', '.') ?>.<br>
                    Peça de R$ 2.686,00 → lucro de R$ <?= number_format($exemploCaroLucro, 2, ',', '.') ?>
                    → venda de R$ <?= number_format($exemploCaroVenda, 2, ',', '.') ?>.
                </p>

                <button type="submit" class="btn-primario">Salvar preços</button>
            </form>
        </section>

        <section class="card-painel card-interno">
            <h2>Endereço de montagem</h2>
            <p class="card-sub">Para onde as peças dos PCs completos devem ser enviadas. Este endereço é único e compartilhado entre todos os administradores.</p>

            <?php if (!enderecoMontagemConfigurado($enderecoMontagem)): ?>
                <div class="alerta alerta-aviso">
                    Preencha o endereço abaixo. Ele será usado em todos os pedidos de PC completo.
                </div>
            <?php endif; ?>

            <?php if ($erroEndereco !== ''): ?>
                <div class="alerta alerta-erro"><?= e($erroEndereco) ?></div>
            <?php endif; ?>

            <form method="post" class="form-padrao">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="acao" value="endereco">

                <div class="form-grid">
                    <label class="campo campo-largo">
                        <span>Responsável *</span>
                        <input type="text" name="responsavel" value="<?= e($enderecoMontagem['responsavel']) ?>" required>
                    </label>
                    <label class="campo">
                        <span>CEP *</span>
                        <input type="text" name="cep" value="<?= e($enderecoMontagem['cep']) ?>" inputmode="numeric" required>
                    </label>
                    <label class="campo">
                        <span>Número *</span>
                        <input type="text" name="numero" value="<?= e($enderecoMontagem['numero']) ?>" required>
                    </label>
                    <label class="campo campo-largo">
                        <span>Endereço *</span>
                        <input type="text" name="endereco" value="<?= e($enderecoMontagem['endereco']) ?>" required>
                    </label>
                    <label class="campo campo-largo">
                        <span>Complemento</span>
                        <input type="text" name="complemento" value="<?= e($enderecoMontagem['complemento']) ?>">
                    </label>
                    <label class="campo">
                        <span>Bairro *</span>
                        <input type="text" name="bairro" value="<?= e($enderecoMontagem['bairro']) ?>" required>
                    </label>
                    <label class="campo">
                        <span>Cidade *</span>
                        <input type="text" name="cidade" value="<?= e($enderecoMontagem['cidade']) ?>" required>
                    </label>
                    <label class="campo">
                        <span>UF *</span>
                        <input type="text" name="estado" value="<?= e($enderecoMontagem['estado']) ?>" maxlength="2" required>
                    </label>
                </div>

                <button type="submit" class="btn-primario">Salvar endereço</button>
            </form>
        </section>
    </div>

    <section class="card-painel">
        <div class="painel-cabecalho">
            <h2>Pedidos</h2>
            <form method="get" class="form-inline">
                <select name="status" onchange="this.form.submit()">
                    <option value="">Todos os status</option>
                    <?php foreach ($statusValidos as $s): ?>
                        <option value="<?= e($s) ?>" <?= $filtro === $s ? 'selected' : '' ?>><?= e(statusPedidoLabel($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (empty($pedidos)): ?>
            <p class="card-sub">Nenhum pedido encontrado.</p>
        <?php else: ?>
            <div class="tabela-wrap">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Tipo</th>
                            <th>Total</th>
                            <th>Lucro</th>
                            <th>Status</th>
                            <th>Data</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $p): ?>
                            <tr>
                                <td>#<?= (int) $p['id'] ?></td>
                                <td>
                                    <?= e($p['cliente_nome']) ?><br>
                                    <small><?= e($p['cliente_email']) ?></small>
                                </td>
                                <td><?= $p['tipo'] === 'pc_completo' ? 'PC montado' : 'Peças' ?></td>
                                <td><?= formatarPreco((float) $p['total']) ?></td>
                                <td><?= formatarPreco((float) $p['lucro']) ?></td>
                                <td><span class="status status-<?= e($p['status']) ?>"><?= e(statusPedidoLabel($p['status'])) ?></span></td>
                                <td><?= date('d/m/Y H:i', strtotime($p['criado_em'])) ?></td>
                                <td><a href="pedido.php?id=<?= (int) $p['id'] ?>">Abrir</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</main>
<?php renderFooter(); ?>
