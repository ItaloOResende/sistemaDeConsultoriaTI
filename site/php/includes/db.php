<?php
// ---------------------------------------------------------------------------
// Banco de dados (SQLite) e operações básicas de usuários/pedidos
// ---------------------------------------------------------------------------

require_once __DIR__ . '/config.php';

function obterConexao(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $pasta = __DIR__ . '/../dados';
    if (!is_dir($pasta)) {
        @mkdir($pasta, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $pasta . '/techcota.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    criarTabelas($pdo);
    semearAdmins($pdo);

    return $pdo;
}

function criarTabelas(PDO $pdo): void
{
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            senha TEXT NOT NULL,
            telefone TEXT NOT NULL DEFAULT '',
            cpf TEXT NOT NULL DEFAULT '',
            cep TEXT NOT NULL DEFAULT '',
            endereco TEXT NOT NULL DEFAULT '',
            numero TEXT NOT NULL DEFAULT '',
            complemento TEXT NOT NULL DEFAULT '',
            bairro TEXT NOT NULL DEFAULT '',
            cidade TEXT NOT NULL DEFAULT '',
            estado TEXT NOT NULL DEFAULT '',
            papel TEXT NOT NULL DEFAULT 'cliente',
            criado_em TEXT NOT NULL
        )
    SQL);

    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS pedidos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER NOT NULL,
            tipo TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'aguardando',
            custo_pecas REAL NOT NULL DEFAULT 0,
            taxa_montagem REAL NOT NULL DEFAULT 0,
            frete REAL NOT NULL DEFAULT 0,
            total REAL NOT NULL DEFAULT 0,
            lucro REAL NOT NULL DEFAULT 0,
            entrega_nome TEXT NOT NULL DEFAULT '',
            entrega_telefone TEXT NOT NULL DEFAULT '',
            entrega_cep TEXT NOT NULL DEFAULT '',
            entrega_endereco TEXT NOT NULL DEFAULT '',
            entrega_numero TEXT NOT NULL DEFAULT '',
            entrega_complemento TEXT NOT NULL DEFAULT '',
            entrega_bairro TEXT NOT NULL DEFAULT '',
            entrega_cidade TEXT NOT NULL DEFAULT '',
            entrega_estado TEXT NOT NULL DEFAULT '',
            observacoes TEXT NOT NULL DEFAULT '',
            criado_em TEXT NOT NULL,
            atualizado_em TEXT NOT NULL,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        )
    SQL);

    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS pedido_itens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pedido_id INTEGER NOT NULL,
            chave TEXT NOT NULL,
            rotulo TEXT NOT NULL,
            termo TEXT NOT NULL DEFAULT '',
            produto_titulo TEXT NOT NULL DEFAULT '',
            loja TEXT NOT NULL DEFAULT '',
            preco_custo REAL NOT NULL DEFAULT 0,
            link TEXT NOT NULL DEFAULT '',
            FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
        )
    SQL);

    // Endereço único de montagem, compartilhado entre todos os administradores.
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS endereco_montagem (
            id INTEGER PRIMARY KEY,
            responsavel TEXT NOT NULL DEFAULT '',
            cep TEXT NOT NULL DEFAULT '',
            endereco TEXT NOT NULL DEFAULT '',
            numero TEXT NOT NULL DEFAULT '',
            complemento TEXT NOT NULL DEFAULT '',
            bairro TEXT NOT NULL DEFAULT '',
            cidade TEXT NOT NULL DEFAULT '',
            estado TEXT NOT NULL DEFAULT '',
            atualizado_em TEXT NOT NULL DEFAULT ''
        )
    SQL);

    $totalEnderecos = (int) $pdo->query('SELECT COUNT(*) FROM endereco_montagem')->fetchColumn();
    if ($totalEnderecos === 0) {
        $pdo->exec(
            "INSERT INTO endereco_montagem
                (id, responsavel, cep, endereco, numero, complemento, bairro, cidade, estado, atualizado_em)
             VALUES
                (1, '', '', '', '', '', '', '', '', '')"
        );
    }
}

function semearAdmins(PDO $pdo): void
{
    global $adminsIniciais;

    // Só cria os admins iniciais quando ainda não existe nenhum admin.
    // Assim, se um admin trocar de e-mail, a conta antiga não é recriada.
    $total = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE papel = 'admin'")->fetchColumn();
    if ($total > 0) return;

    $ins = $pdo->prepare(
        'INSERT INTO usuarios (nome, email, senha, papel, criado_em)
         VALUES (:nome, :email, :senha, :papel, :criado)'
    );

    foreach ($adminsIniciais as $admin) {
        $ins->execute([
            ':nome'   => $admin['nome'],
            ':email'  => strtolower(trim($admin['email'])),
            ':senha'  => $admin['senha_hash'],
            ':papel'  => 'admin',
            ':criado' => date('Y-m-d H:i:s'),
        ]);
    }
}

function buscarUsuarioPorEmail(string $email): ?array
{
    $stmt = obterConexao()->prepare('SELECT * FROM usuarios WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => strtolower(trim($email))]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function buscarUsuarioPorId(int $id): ?array
{
    $stmt = obterConexao()->prepare('SELECT * FROM usuarios WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function criarUsuario(array $dados): int
{
    $senhaHash = password_hash($dados['senha'], PASSWORD_DEFAULT);
    $pdo = obterConexao();

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios
            (nome, email, senha, telefone, cpf, cep, endereco, numero, complemento, bairro, cidade, estado, papel, criado_em)
         VALUES
            (:nome, :email, :senha, :telefone, :cpf, :cep, :endereco, :numero, :complemento, :bairro, :cidade, :estado, :papel, :criado)'
    );
    $stmt->execute([
        ':nome'        => $dados['nome'],
        ':email'       => strtolower(trim($dados['email'])),
        ':senha'       => $senhaHash,
        ':telefone'    => $dados['telefone'] ?? '',
        ':cpf'         => $dados['cpf'] ?? '',
        ':cep'         => $dados['cep'] ?? '',
        ':endereco'    => $dados['endereco'] ?? '',
        ':numero'      => $dados['numero'] ?? '',
        ':complemento' => $dados['complemento'] ?? '',
        ':bairro'      => $dados['bairro'] ?? '',
        ':cidade'      => $dados['cidade'] ?? '',
        ':estado'      => $dados['estado'] ?? '',
        ':papel'       => 'cliente',
        ':criado'      => date('Y-m-d H:i:s'),
    ]);

    return (int) $pdo->lastInsertId();
}

function atualizarUsuario(int $id, array $dados): void
{
    $stmt = obterConexao()->prepare(
        'UPDATE usuarios SET
            nome = :nome, email = :email, telefone = :telefone, cpf = :cpf,
            cep = :cep, endereco = :endereco, numero = :numero,
            complemento = :complemento, bairro = :bairro, cidade = :cidade, estado = :estado
         WHERE id = :id'
    );
    $stmt->execute([
        ':nome'        => $dados['nome'],
        ':email'       => strtolower(trim($dados['email'])),
        ':telefone'    => $dados['telefone'] ?? '',
        ':cpf'         => $dados['cpf'] ?? '',
        ':cep'         => $dados['cep'] ?? '',
        ':endereco'    => $dados['endereco'] ?? '',
        ':numero'      => $dados['numero'] ?? '',
        ':complemento' => $dados['complemento'] ?? '',
        ':bairro'      => $dados['bairro'] ?? '',
        ':cidade'      => $dados['cidade'] ?? '',
        ':estado'      => $dados['estado'] ?? '',
        ':id'          => $id,
    ]);
}

function atualizarSenha(int $id, string $novaSenha): void
{
    $stmt = obterConexao()->prepare('UPDATE usuarios SET senha = :senha WHERE id = :id');
    $stmt->execute([':senha' => password_hash($novaSenha, PASSWORD_DEFAULT), ':id' => $id]);
}

function criarPedido(int $usuarioId, array $dados, array $itens): int
{
    $pdo = obterConexao();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO pedidos
                (usuario_id, tipo, status, custo_pecas, taxa_montagem, frete, total, lucro,
                 entrega_nome, entrega_telefone, entrega_cep, entrega_endereco, entrega_numero,
                 entrega_complemento, entrega_bairro, entrega_cidade, entrega_estado,
                 observacoes, criado_em, atualizado_em)
             VALUES
                (:usuario_id, :tipo, :status, :custo_pecas, :taxa_montagem, :frete, :total, :lucro,
                 :entrega_nome, :entrega_telefone, :entrega_cep, :entrega_endereco, :entrega_numero,
                 :entrega_complemento, :entrega_bairro, :entrega_cidade, :entrega_estado,
                 :observacoes, :criado, :atualizado)'
        );
        $agora = date('Y-m-d H:i:s');
        $stmt->execute([
            ':usuario_id'          => $usuarioId,
            ':tipo'                => $dados['tipo'],
            ':status'              => 'aguardando',
            ':custo_pecas'         => $dados['custo_pecas'],
            ':taxa_montagem'       => $dados['taxa_montagem'],
            ':frete'               => $dados['frete'],
            ':total'               => $dados['total'],
            ':lucro'               => $dados['lucro'],
            ':entrega_nome'        => $dados['entrega_nome'],
            ':entrega_telefone'    => $dados['entrega_telefone'],
            ':entrega_cep'         => $dados['entrega_cep'],
            ':entrega_endereco'    => $dados['entrega_endereco'],
            ':entrega_numero'      => $dados['entrega_numero'],
            ':entrega_complemento' => $dados['entrega_complemento'],
            ':entrega_bairro'      => $dados['entrega_bairro'],
            ':entrega_cidade'      => $dados['entrega_cidade'],
            ':entrega_estado'      => $dados['entrega_estado'],
            ':observacoes'         => $dados['observacoes'] ?? '',
            ':criado'              => $agora,
            ':atualizado'          => $agora,
        ]);

        $pedidoId = (int) $pdo->lastInsertId();

        $itemStmt = $pdo->prepare(
            'INSERT INTO pedido_itens
                (pedido_id, chave, rotulo, termo, produto_titulo, loja, preco_custo, link)
             VALUES
                (:pedido_id, :chave, :rotulo, :termo, :produto_titulo, :loja, :preco_custo, :link)'
        );

        foreach ($itens as $item) {
            $itemStmt->execute([
                ':pedido_id'      => $pedidoId,
                ':chave'          => $item['chave'],
                ':rotulo'         => $item['rotulo'],
                ':termo'          => $item['termo'],
                ':produto_titulo' => $item['produto_titulo'],
                ':loja'           => $item['loja'],
                ':preco_custo'    => $item['preco_custo'],
                ':link'           => $item['link'],
            ]);
        }

        $pdo->commit();
        return $pedidoId;
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

function buscarPedido(int $id): ?array
{
    $stmt = obterConexao()->prepare(
        'SELECT p.*, u.nome AS cliente_nome, u.email AS cliente_email, u.telefone AS cliente_telefone
         FROM pedidos p
         JOIN usuarios u ON u.id = p.usuario_id
         WHERE p.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $p = $stmt->fetch();
    return $p ?: null;
}

function listarItensPedido(int $pedidoId): array
{
    $stmt = obterConexao()->prepare('SELECT * FROM pedido_itens WHERE pedido_id = :id ORDER BY id');
    $stmt->execute([':id' => $pedidoId]);
    return $stmt->fetchAll();
}

function listarPedidosDoUsuario(int $usuarioId): array
{
    $stmt = obterConexao()->prepare('SELECT * FROM pedidos WHERE usuario_id = :id ORDER BY id DESC');
    $stmt->execute([':id' => $usuarioId]);
    return $stmt->fetchAll();
}

function listarTodosPedidos(): array
{
    return obterConexao()->query(
        'SELECT p.*, u.nome AS cliente_nome, u.email AS cliente_email
         FROM pedidos p
         JOIN usuarios u ON u.id = p.usuario_id
         ORDER BY p.id DESC'
    )->fetchAll();
}

function atualizarStatusPedido(int $pedidoId, string $status): void
{
    $stmt = obterConexao()->prepare(
        'UPDATE pedidos SET status = :status, atualizado_em = :agora WHERE id = :id'
    );
    $stmt->execute([
        ':status' => $status,
        ':agora'  => date('Y-m-d H:i:s'),
        ':id'     => $pedidoId,
    ]);
}

/** Rótulos amigáveis para os status do pedido. */
function statusPedidoLabel(string $status): string
{
    return [
        'aguardando'   => 'Aguardando contato',
        'pago'         => 'Pagamento confirmado',
        'comprando'    => 'Comprando peças',
        'montando'     => 'Em montagem',
        'enviado'      => 'Enviado',
        'entregue'     => 'Entregue',
        'cancelado'    => 'Cancelado',
    ][$status] ?? $status;
}

/** Endereço único de montagem, compartilhado entre todos os administradores. */
function obterEnderecoMontagem(): array
{
    $stmt = obterConexao()->query(
        'SELECT responsavel, cep, endereco, numero, complemento, bairro, cidade, estado
         FROM endereco_montagem WHERE id = 1 LIMIT 1'
    );
    $endereco = $stmt->fetch();

    if (!$endereco) {
        return [
            'responsavel' => '',
            'cep'         => '',
            'endereco'    => '',
            'numero'      => '',
            'complemento' => '',
            'bairro'      => '',
            'cidade'      => '',
            'estado'      => '',
        ];
    }

    return $endereco;
}

function salvarEnderecoMontagem(array $dados): void
{
    $stmt = obterConexao()->prepare(
        'UPDATE endereco_montagem SET
            responsavel = :responsavel,
            cep = :cep,
            endereco = :endereco,
            numero = :numero,
            complemento = :complemento,
            bairro = :bairro,
            cidade = :cidade,
            estado = :estado,
            atualizado_em = :atualizado
         WHERE id = 1'
    );
    $stmt->execute([
        ':responsavel' => $dados['responsavel'] ?? '',
        ':cep'         => $dados['cep'] ?? '',
        ':endereco'    => $dados['endereco'] ?? '',
        ':numero'      => $dados['numero'] ?? '',
        ':complemento' => $dados['complemento'] ?? '',
        ':bairro'      => $dados['bairro'] ?? '',
        ':cidade'      => $dados['cidade'] ?? '',
        ':estado'      => $dados['estado'] ?? '',
        ':atualizado'  => date('Y-m-d H:i:s'),
    ]);
}

function enderecoMontagemConfigurado(array $endereco): bool
{
    return trim((string) ($endereco['responsavel'] ?? '')) !== ''
        && trim((string) ($endereco['cep'] ?? '')) !== ''
        && trim((string) ($endereco['endereco'] ?? '')) !== ''
        && trim((string) ($endereco['numero'] ?? '')) !== ''
        && trim((string) ($endereco['bairro'] ?? '')) !== ''
        && trim((string) ($endereco['cidade'] ?? '')) !== ''
        && trim((string) ($endereco['estado'] ?? '')) !== '';
}
