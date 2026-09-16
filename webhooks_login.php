<?php
require_once 'includes/auth.php';
require_once 'includes/rate_limit.php';

if (is_logged_in() && is_admin()) {
    header('Location: webhooks_admin.php');
    exit;
}

$erro = '';
$loginRlMaxIdentity = 5;
$loginRlMaxIp = 25;
$loginRlWindow = 900;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $ip = rate_limit_client_ip();
    $bucketIdentity = 'login:' . strtolower($email) . '|' . $ip;
    $bucketIp = 'login_ip:' . $ip;

    $statusIdentity = rate_limit_status($bucketIdentity, $loginRlMaxIdentity, $loginRlWindow);
    $statusIp = rate_limit_status($bucketIp, $loginRlMaxIp, $loginRlWindow);

    if (!$statusIdentity['allowed'] || !$statusIp['allowed']) {
        $retry = max($statusIdentity['retry_after'], $statusIp['retry_after']);
        $erro = 'Demasiadas tentativas de login. Tente novamente em ' . $retry . ' segundos.';
        http_response_code(429);
        header('Retry-After: ' . max(1, $retry));
    } elseif (login($email, $senha)) {
        rate_limit_clear($bucketIdentity);
        rate_limit_clear($bucketIp);
        if (is_admin()) {
            header('Location: webhooks_admin.php');
            exit;
        }
        logout();
        $erro = 'Apenas administradores podem acessar este painel.';
    } else {
        rate_limit_hit($bucketIdentity, $loginRlMaxIdentity, $loginRlWindow);
        rate_limit_hit($bucketIp, $loginRlMaxIp, $loginRlWindow);
        $erro = 'Usuário ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Administração de Webhooks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width:400px;">
    <div class="card p-4">
        <h3 class="mb-3">Login - Webhooks Admin</h3>
        <?php if ($erro): ?>
            <div class="alert alert-danger"> <?= htmlspecialchars($erro) ?> </div>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">E-mail</label>
                <input type="email" name="email" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Senha</label>
                <input type="password" name="senha" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Entrar</button>
        </form>
    </div>
</div>
</body>
</html> 