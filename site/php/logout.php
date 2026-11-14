<?php
require __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfValido($_POST['csrf'] ?? null)) {
    redirecionar('index.php');
}

sairUsuario();
redirecionar('index.php');
