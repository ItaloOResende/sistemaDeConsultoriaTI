<?php
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/db.php';

exigirLogin();

$admin  = ehAdmin();
$id     = (int) ($_GET['id'] ?? 0);
$pedido = $id > 0 ? buscarPedido($id) : null;
$enderecoMontagem = $admin ? obterEnderecoMontagem() : [];

if (!$pedido) {
    http_response_code(404);
    renderHead('Pedido não encontrado');
    ?>
    <main class="pagina-wrap pagina-estreita">
        <div class="card-painel">
            <h1>Pedido não encontrado</h1>
            <p class="card-sub">O pedido informado não existe ou foi removido.</p>
            <a class="btn-primario" href="conta.php">Voltar para minha conta</a>
        </div>
    </main>
    <?php
    renderFooter();
    exit;
}

$proprio = (int) $pedido['usuario_id'] === (int) usuarioAtual()['id'];
if (!$proprio && !$admin) {
    http_response_code(403);
    renderHead('Acesso restrito');
    ?>
    <main class="pagina-wrap pagina-estreita">
        <div class="card-painel">
            <h1>Acesso restrito</h1>
            <p class="card-sub">Você não tem permissão para ver este pedido.</p>
            <a class="btn-primario" href="conta.php">Voltar para minha conta</a>
        </div>
    </main>
    <?php
    renderFooter();
    exit;
}

$statusValidos = ['aguardando', 'pago', 'comprando', 'montando', 'enviado', 'entregue', 'cancelado'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $admin) {
    if (!csrfValido($_POST['csrf'] ?? null)) {
        flash('erro', 'Sessão expirada. Tente novamente.');
    } else {
        $novoStatus = $_POST['status'] ?? '';
        if (in_array($novoStatus, $statusValidos, true)) {
            atualizarStatusPedido($id, $novoStatus);
            flash('sucesso', 'Status do pedido atualizado.');
        } else {
            flash('erro', 'Status inválido.');
        }
    }
    redirecionar('pedido.php?id=' . $id);
}

$itens = listarItensPedido($id);

renderHead('Pedido #' . (int) $pedido['id']);
?>
<main class="pagina-wrap">
    <div class="conta-topo">
        <div>
            <h1>Pedido #<?= (int) $pedido['id'] ?></h1>
            <p class="card-sub">
                <?= date('d/m/Y', strtotime($pedido['criado_em'])) ?> às <?= date('H:i', strtotime($pedido['criado_em'])) ?>
                · <?= $pedido['tipo'] === 'pc_completo' ? 'PC completo montado' : 'Peças avulsas' ?>
            </p>
        </div>
        <div class="conta-acoes">
            <span class="status status-<?= e($pedido['status']) ?>"><?= e(statusPedidoLabel($pedido['status'])) ?></span>
            <?php if ($admin): ?>
                <a class="btn-secundario" href="admin.php">Todos os pedidos</a>
            <?php else: ?>
                <a class="btn-secundario" href="pedidos.php">Meus pedidos</a>
            <?php endif; ?>
            <a class="btn-secundario" href="conta.php">Minha conta</a>
        </div>
    </div>

    <?php renderFlashes(); ?>

    <?php if ($admin): ?>
        <section class="card-painel">
            <h2>Atualizar status</h2>
            <form method="post" class="form-inline">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <select name="status">
                    <?php foreach ($statusValidos as $s): ?>
                        <option value="<?= e($s) ?>" <?= $pedido['status'] === $s ? 'selected' : '' ?>><?= e(statusPedidoLabel($s)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-secundario">Salvar status</button>
            </form>
        </section>
    <?php endif; ?>

    <div class="cards-duplos">
        <section class="card-painel">
            <h2>Itens do pedido</h2>
            <ul class="lista-itens">
                <?php foreach ($itens as $item): ?>
                    <li>
                        <div class="item-info">
                            <strong><?= e($item['rotulo']) ?></strong>
                            <span><?= e($item['termo']) ?></span>
                            <?php if ($admin): ?>
                                <small>
                                    <?= e($item['produto_titulo']) ?>
                                    · <?= e($item['loja']) ?>
                                    · custo <?= formatarPreco((float) $item['preco_custo']) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        <?php if ($admin && $item['link'] !== ''): ?>
                            <a class="item-link" href="<?= e($item['link']) ?>" target="_blank" rel="noopener">Ver oferta</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="card-painel">
            <h2>Entrega</h2>
            <p class="card-sub">
                <?= $pedido['tipo'] === 'pc_completo'
                    ? 'O PC montado será enviado para este endereço.'
                    : 'As peças serão enviadas para este endereço.' ?>
            </p>

            <address class="endereco-entrega">
                <strong><?= e($pedido['entrega_nome']) ?></strong><br>
                <?= e($pedido['entrega_endereco']) ?>, <?= e($pedido['entrega_numero']) ?>
                <?php if ($pedido['entrega_complemento'] !== ''): ?>
                    — <?= e($pedido['entrega_complemento']) ?>
                <?php endif; ?><br>
                <?= e($pedido['entrega_bairro']) ?> — <?= e($pedido['entrega_cidade']) ?>/<?= e($pedido['entrega_estado']) ?><br>
                CEP <?= e($pedido['entrega_cep']) ?><br>
                Telefone: <?= e($pedido['entrega_telefone']) ?>
            </address>

            <?php if ($pedido['observacoes'] !== ''): ?>
                <div class="observacao">
                    <strong>Observações:</strong> <?= nl2br(e($pedido['observacoes'])) ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="cards-duplos">
        <section class="card-painel">
            <h2>Resumo financeiro</h2>
            <div class="resumo-linhas">
                <?php if ($admin): ?>
                    <div class="resumo-linha"><span>Custo das peças</span><span><?= formatarPreco((float) $pedido['custo_pecas']) ?></span></div>
                    <div class="resumo-linha"><span>Montagem</span><span><?= formatarPreco((float) $pedido['taxa_montagem']) ?></span></div>
                    <div class="resumo-linha">
                        <span>Frete</span>
                        <span><?= (float) $pedido['frete'] > 0 ? formatarPreco((float) $pedido['frete']) : 'A combinar' ?></span>
                    </div>
                    <div class="resumo-linha resumo-total"><span>Total</span><strong><?= formatarPreco((float) $pedido['total']) ?></strong></div>
                    <div class="resumo-linha resumo-lucro"><span>Lucro bruto</span><span><?= formatarPreco((float) $pedido['lucro']) ?></span></div>
                <?php else: ?>
                    <?php if ($pedido['tipo'] === 'pc_completo'): ?>
                        <div class="resumo-linha"><span>Peças</span><span><?= formatarPreco((float) $pedido['custo_pecas']) ?></span></div>
                        <div class="resumo-linha"><span>Montagem, testes e embalagem</span><span><?= formatarPreco((float) $pedido['taxa_montagem']) ?></span></div>
                        <div class="resumo-linha">
                            <span>Frete</span>
                            <span><?= (float) $pedido['frete'] > 0 ? formatarPreco((float) $pedido['frete']) : 'A combinar' ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="resumo-linha resumo-total"><span>Total</span><strong><?= formatarPreco((float) $pedido['total']) ?></strong></div>
                <?php endif; ?>
            </div>

            <p class="pagamento-nota">
                Pagamento combinado pelo WhatsApp. O pedido só entra em produção após a confirmação.
            </p>

            <?php
            $foneSuporte = $contatos[0]['whatsapp'] ?? '';
            $msgSuporte = 'Olá! Quero falar sobre o pedido #' . (int) $pedido['id'] . ' no ' . $nomeSistema . '.';
            $linkSuporte = urlWhatsapp($foneSuporte, $msgSuporte);
            ?>
            <?php if ($linkSuporte !== ''): ?>
                <a class="btn-secundario" href="<?= e($linkSuporte) ?>" target="_blank" rel="noopener">Falar no WhatsApp</a>
            <?php endif; ?>
        </section>

        <?php if ($admin): ?>
            <section class="card-painel card-interno">
                <h2>Informações internas</h2>
                <p class="card-sub">Não exibir para o cliente.</p>

                <div class="resumo-linhas">
                    <div class="resumo-linha"><span>Cliente</span><span><?= e($pedido['cliente_nome']) ?></span></div>
                    <div class="resumo-linha"><span>E-mail</span><span><?= e($pedido['cliente_email']) ?></span></div>
                    <div class="resumo-linha"><span>Telefone</span><span><?= e($pedido['cliente_telefone']) ?></span></div>
                </div>

                <?php if ($pedido['tipo'] === 'pc_completo'): ?>
                    <h3 class="subtitulo-interno">Endereço de montagem (receber as peças)</h3>
                    <?php if (!enderecoMontagemConfigurado($enderecoMontagem)): ?>
                        <div class="alerta alerta-aviso">
                            Endereço de montagem não configurado. <a href="admin.php">Preencha no painel de pedidos</a>.
                        </div>
                    <?php else: ?>
                        <address class="endereco-entrega">
                            <strong><?= e($enderecoMontagem['responsavel']) ?></strong><br>
                            <?= e($enderecoMontagem['endereco']) ?>, <?= e($enderecoMontagem['numero']) ?>
                            <?php if ($enderecoMontagem['complemento'] !== ''): ?>
                                — <?= e($enderecoMontagem['complemento']) ?>
                            <?php endif; ?><br>
                            <?= e($enderecoMontagem['bairro']) ?> — <?= e($enderecoMontagem['cidade']) ?>/<?= e($enderecoMontagem['estado']) ?><br>
                            CEP <?= e($enderecoMontagem['cep']) ?>
                        </address>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>
<?php renderFooter(); ?>
