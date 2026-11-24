<?php
// BF-TEMP: site sem login até depois da Black Friday. Remover estas 2 linhas para restaurar.
header('Location: index.php');
exit;

require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/db.php';

exigirLogin();

$usuario = usuarioAtual();
$pedidos = listarPedidosDoUsuario((int) $usuario['id']);

renderHead('Meus pedidos');
?>
<main class="pagina-wrap">
    <div class="conta-topo">
        <div>
            <h1>Meus pedidos</h1>
            <p class="card-sub">Acompanhe o status e os detalhes dos seus pedidos.</p>
        </div>
        <?php if (ehAdmin()): ?>
            <div class="conta-acoes">
                <a class="btn-secundario" href="admin.php">Painel de pedidos</a>
            </div>
        <?php endif; ?>
    </div>

    <?php renderFlashes(); ?>

    <section class="card-painel">
        <?php if (empty($pedidos)): ?>
            <h2>Nenhum pedido ainda</h2>
            <p class="card-sub">Você ainda não fez nenhum pedido. Monte seu setup e solicite uma cotação.</p>
            <a class="btn-primario" href="index.php">Montar setup</a>
        <?php else: ?>
            <div class="tabela-wrap">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $p): ?>
                            <tr>
                                <td>#<?= (int) $p['id'] ?></td>
                                <td><?= $p['tipo'] === 'pc_completo' ? 'PC montado' : 'Peças avulsas' ?></td>
                                <td><span class="status status-<?= e($p['status']) ?>"><?= e(statusPedidoLabel($p['status'])) ?></span></td>
                                <td><?= formatarPreco((float) $p['total']) ?></td>
                                <td><a href="pedido.php?id=<?= (int) $p['id'] ?>">Detalhes</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php renderFooter(); ?>
