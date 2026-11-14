<?php
// ---------------------------------------------------------------------------
// Sessão e autenticação
// ---------------------------------------------------------------------------

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

function usuarioAtual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function ehAdmin(): bool
{
    $u = usuarioAtual();
    return $u !== null && ($u['papel'] ?? '') === 'admin';
}

function entrarUsuario(array $usuario): void
{
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id'     => (int) $usuario['id'],
        'nome'   => $usuario['nome'],
        'email'  => $usuario['email'],
        'papel'  => $usuario['papel'] ?? 'cliente',
    ];
}

function sairUsuario(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? true);
    }
    session_destroy();
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfValido(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

/** Escapa texto para HTML. */
function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/** Garante que o "next" é um caminho local (evita open redirect). */
function destinoSeguro(?string $next): string
{
    $next = trim((string) $next);
    if ($next === '') return 'index.php';
    // Apenas caminhos locais (bloqueia http://, //host, barras invertidas etc.)
    // O "+" é aceito porque http_build_query usa "+" para espaços.
    if (!preg_match('~^[A-Za-z0-9_\-./?=&%+]+$~', $next)) return 'index.php';
    if (str_starts_with($next, '//')) return 'index.php';
    if (!preg_match('~^(/|[A-Za-z0-9_\-]+\.php)~', $next)) return 'index.php';
    return $next;
}

function exigirLogin(): void
{
    if (!usuarioAtual()) {
        $destino = $_SERVER['REQUEST_URI'] ?? 'conta.php';
        header('Location: login.php?next=' . urlencode($destino));
        exit;
    }
}

function exigirAdmin(): void
{
    exigirLogin();
    if (!ehAdmin()) {
        http_response_code(403);
        renderAcessoNegado();
        exit;
    }
}

function renderAcessoNegado(): void
{
    require_once __DIR__ . '/layout.php';
    renderHead('Acesso restrito');
    ?>
    <main class="pagina-wrap">
        <div class="card-painel">
            <h1>Acesso restrito</h1>
            <p>Esta área é exclusiva dos administradores do <?= e($GLOBALS['nomeSistema'] ?? 'TechCota') ?>.</p>
            <a class="btn-primario" href="index.php">Voltar ao site</a>
        </div>
    </main>
    <?php
    renderFooter();
}

function flash(string $tipo, string $mensagem): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'msg' => $mensagem];
}

function consumirFlashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function redirecionar(string $url): void
{
    header('Location: ' . $url);
    exit;
}
