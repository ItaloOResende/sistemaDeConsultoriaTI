<?php
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/cotacao.php';

exigirLogin();

$raizProjeto = dirname(dirname(__DIR__));
$csvsDir     = $raizProjeto . DIRECTORY_SEPARATOR . 'csvs' . DIRECTORY_SEPARATOR;

$perfilBusca    = $_GET['perfil_busca'] ?? 'barato';
$modoSetup      = ($_GET['montar_setup'] ?? '') === '1';
$completarSetup = ($_GET['completar_setup'] ?? '') === '1';

$cotacao    = calcularCotacao($_GET, $csvsDir, $modoSetup, $perfilBusca, $completarSetup);
$resultados = $cotacao['resultados'];
$custo      = (float) $cotacao['total'];
$faltantes  = (int) $cotacao['faltantes'];

// Lucro aplicado peça a peça, nunca no total.
$lucroPecas = 0.0;
foreach ($resultados as $dados) {
    if ($dados['melhor'] === null) continue;
    $lucroPecas += lucroPecasCliente((float) $dados['melhor']['precoNum']);
}
$lucroPecas      = round($lucroPecas, 2);
$totalPecasLucro = round($custo + $lucroPecas, 2);

$tipo   = $modoSetup ? 'pc_completo' : 'peca';
$taxa   = $modoSetup ? (float) $configPrecos['montagem_pc'] : 0.0;
$frete  = $modoSetup ? (float) $configPrecos['frete_pc'] : 0.0;
$total  = round($totalPecasLucro + $taxa + $frete, 2);
$lucro  = round($total - $custo - $frete, 2);

$itens = [];
foreach ($resultados as $chave => $dados) {
    if ($dados['melhor'] === null) continue;
    $itens[] = [
        'chave'          => $chave,
        'rotulo'         => $dados['rotulo'],
        'termo'          => $dados['termo'],
        'produto_titulo' => $dados['melhor']['titulo'],
        'loja'           => $dados['melhor']['loja']['nome'],
        'preco_custo'    => $dados['melhor']['precoNum'],
        'link'           => $dados['melhor']['link'],
    ];
}

$podeFinalizar = $custo > 0 && !empty($itens) && $faltantes === 0;

$usuario = buscarUsuarioPorId((int) usuarioAtual()['id']);
if (!$usuario) {
    sairUsuario();
    redirecionar('login.php');
}

// Valores iniciais do formulário de entrega
$form = [
    'entrega_nome'        => $usuario['nome'],
    'entrega_telefone'    => $usuario['telefone'],
    'entrega_cep'         => $usuario['cep'],
    'entrega_endereco'    => $usuario['endereco'],
    'entrega_numero'      => $usuario['numero'],
    'entrega_complemento' => $usuario['complemento'],
    'entrega_bairro'      => $usuario['bairro'],
    'entrega_cidade'      => $usuario['cidade'],
    'entrega_estado'      => $usuario['estado'],
    'observacoes'         => '',
];

$erro = '';
$urlCheckout = 'checkout.php?' . http_build_query($_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['csrf'] ?? null)) {
        $erro = 'Sessão expirada. Revise os dados e tente novamente.';
    }

    foreach (array_keys($form) as $campo) {
        $form[$campo] = trim($_POST[$campo] ?? '');
    }

    $obrigatorios = [
        'entrega_nome', 'entrega_telefone', 'entrega_cep', 'entrega_endereco',
        'entrega_numero', 'entrega_bairro', 'entrega_cidade', 'entrega_estado',
    ];
    if ($erro === '') {
        foreach ($obrigatorios as $campo) {
            if ($form[$campo] === '') {
                $erro = 'Preencha todos os campos obrigatórios do endereço de entrega.';
                break;
            }
        }
    }

    if ($erro === '' && !$podeFinalizar) {
        $erro = 'Não é possível finalizar: há componentes sem oferta na configuração.';
    }

    if ($erro === '') {
        try {
            $pedidoId = criarPedido(
                (int) $usuario['id'],
                [
                    'tipo'          => $tipo,
                    'custo_pecas'   => $custo,
                    'taxa_montagem' => $taxa,
                    'frete'         => $frete,
                    'total'         => $total,
                    'lucro'         => $lucro,
                    'entrega_nome'        => $form['entrega_nome'],
                    'entrega_telefone'    => $form['entrega_telefone'],
                    'entrega_cep'         => $form['entrega_cep'],
                    'entrega_endereco'    => $form['entrega_endereco'],
                    'entrega_numero'      => $form['entrega_numero'],
                    'entrega_complemento' => $form['entrega_complemento'],
                    'entrega_bairro'      => $form['entrega_bairro'],
                    'entrega_cidade'      => $form['entrega_cidade'],
                    'entrega_estado'      => strtoupper($form['entrega_estado']),
                    'observacoes'         => $form['observacoes'],
                ],
                $itens
            );

            flash('sucesso', 'Pedido #' . $pedidoId . ' criado! Vamos combinar o pagamento pelo WhatsApp.');
            redirecionar('pedido.php?id=' . $pedidoId);
        } catch (Throwable $ex) {
            $erro = 'Não foi possível registrar o pedido. Tente novamente.';
        }
    }
}

renderHead('Finalizar pedido');
?>
<main class="pagina-wrap">
    <div class="conta-topo">
        <div>
            <h1>Finalizar pedido</h1>
            <p class="card-sub">Confira a configuração e informe o endereço de entrega.</p>
        </div>
        <a class="btn-secundario" href="index.php?<?= e(http_build_query($_GET)) ?>">Voltar e ajustar</a>
    </div>

    <?php renderFlashes(); ?>
    <?php if ($erro !== ''): ?>
        <div class="alerta alerta-erro"><?= e($erro) ?></div>
    <?php endif; ?>

    <?php if (empty($itens)): ?>
        <div class="card-painel">
            <h2>Nenhum item para finalizar</h2>
            <p class="card-sub">Volte ao site e selecione os componentes do seu setup.</p>
            <a class="btn-primario" href="index.php">Montar setup</a>
        </div>
    <?php else: ?>
        <form method="post" action="<?= e($urlCheckout) ?>" class="checkout-grid">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

            <div class="checkout-col">
                <section class="card-painel">
                    <h2><?= $modoSetup ? 'PC completo montado' : 'Peças selecionadas' ?></h2>
                    <p class="card-sub">
                        <?= $modoSetup
                            ? 'As peças serão enviadas para nossa bancada. Depois de montar, testar e embalar, enviamos o PC para o endereço informado abaixo.'
                            : 'As peças selecionadas serão compradas e enviadas para o endereço informado abaixo.' ?>
                    </p>

                    <ul class="lista-itens">
                        <?php foreach ($itens as $item): ?>
                            <li>
                                <div class="item-info">
                                    <strong><?= e($item['rotulo']) ?></strong>
                                    <span><?= e($item['termo']) ?></span>
                                    <?php if (ehAdmin()): ?>
                                        <small><?= e($item['produto_titulo']) ?> — <?= e($item['loja']) ?> — <?= formatarPreco((float) $item['preco_custo']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php if (ehAdmin()): ?>
                                    <a class="item-link" href="<?= e($item['link']) ?>" target="_blank" rel="noopener">Ver oferta</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($faltantes > 0): ?>
                        <div class="alerta alerta-aviso">
                            <?= $faltantes ?> componente(s) sem oferta nos dados atuais. Ajuste a configuração antes de finalizar.
                        </div>
                    <?php endif; ?>
                </section>

                <section class="card-painel">
                    <h2>Endereço de entrega</h2>
                    <p class="card-sub">Você pode entregar em um endereço diferente do cadastro.</p>

                    <div class="form-grid">
                        <label class="campo campo-largo">
                            <span>Nome de quem recebe *</span>
                            <input type="text" name="entrega_nome" value="<?= e($form['entrega_nome']) ?>" required>
                        </label>
                        <label class="campo">
                            <span>Telefone / WhatsApp *</span>
                            <input type="tel" name="entrega_telefone" value="<?= e($form['entrega_telefone']) ?>" required>
                        </label>
                        <label class="campo">
                            <span>CEP *</span>
                            <input type="text" name="entrega_cep" value="<?= e($form['entrega_cep']) ?>" inputmode="numeric" required>
                        </label>
                        <label class="campo campo-largo">
                            <span>Endereço *</span>
                            <input type="text" name="entrega_endereco" value="<?= e($form['entrega_endereco']) ?>" required>
                        </label>
                        <label class="campo">
                            <span>Número *</span>
                            <input type="text" name="entrega_numero" value="<?= e($form['entrega_numero']) ?>" required>
                        </label>
                        <label class="campo">
                            <span>Complemento</span>
                            <input type="text" name="entrega_complemento" value="<?= e($form['entrega_complemento']) ?>">
                        </label>
                        <label class="campo">
                            <span>Bairro *</span>
                            <input type="text" name="entrega_bairro" value="<?= e($form['entrega_bairro']) ?>" required>
                        </label>
                        <label class="campo">
                            <span>Cidade *</span>
                            <input type="text" name="entrega_cidade" value="<?= e($form['entrega_cidade']) ?>" required>
                        </label>
                        <label class="campo">
                            <span>UF *</span>
                            <input type="text" name="entrega_estado" value="<?= e($form['entrega_estado']) ?>" maxlength="2" required>
                        </label>
                        <label class="campo campo-largo">
                            <span>Observações</span>
                            <textarea name="observacoes" rows="3" placeholder="Alguma preferência, prazo ou detalhe sobre a montagem?"><?= e($form['observacoes']) ?></textarea>
                        </label>
                    </div>
                </section>
            </div>

            <aside class="checkout-col">
                <section class="card-painel resumo-pedido">
                    <h2>Resumo</h2>

                    <div class="resumo-linhas">
                        <?php if ($modoSetup): ?>
                            <div class="resumo-linha"><span>Peças</span><span><?= formatarPreco($totalPecasLucro) ?></span></div>
                            <div class="resumo-linha"><span>Montagem, testes e embalagem</span><span><?= formatarPreco($taxa) ?></span></div>
                            <div class="resumo-linha">
                                <span>Frete</span>
                                <span><?= $frete > 0 ? formatarPreco($frete) : 'A combinar' ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="resumo-linha resumo-total">
                            <span>Total</span>
                            <strong><?= formatarPreco($total) ?></strong>
                        </div>
                    </div>

                    <?php if (ehAdmin()): ?>
                        <div class="resumo-admin">
                            <div class="resumo-linha"><span>Custo das peças</span><span><?= formatarPreco($custo) ?></span></div>
                            <div class="resumo-linha">
                                <span>Lucro das peças</span>
                                <span><?= formatarPreco($lucroPecas) ?></span>
                            </div>
                            <?php if ($modoSetup): ?>
                                <div class="resumo-linha"><span>Montagem</span><span><?= formatarPreco($taxa) ?></span></div>
                            <?php endif; ?>
                            <div class="resumo-linha"><span>Lucro bruto total</span><span><?= formatarPreco($lucro) ?></span></div>
                        </div>
                    <?php endif; ?>

                    <p class="pagamento-nota">
                        Pagamento e frete (quando não incluso) são combinados pelo WhatsApp após a confirmação do pedido.
                    </p>

                    <button type="submit" class="btn-primario btn-bloco" <?= $podeFinalizar ? '' : 'disabled' ?>>
                        Confirmar pedido
                    </button>

                    <?php if (!$podeFinalizar): ?>
                        <p class="card-sub" style="margin-top:0.75rem;">Existem componentes sem oferta. Ajuste a configuração para continuar.</p>
                    <?php endif; ?>
                </section>
            </aside>
        </form>
    <?php endif; ?>
</main>
<?php renderFooter(); ?>
