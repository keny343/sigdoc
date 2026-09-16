<?php
require_once 'includes/auth.php';
require_once 'includes/rate_limit.php';

if (isset($_SESSION['usuarioapi_id']) && !empty($_SESSION['usuarioapi_2fa_ok'])) {
    header('Location: meu_token_api.php');
    exit;
}

$erro = '';
$etapa = 'login';
$loginRlMaxIdentity = 5;
$loginRlMaxIp = 25;
$loginRlWindow = 900;
$otpRlMax = 5;
$otpRlWindow = 900;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $ip = rate_limit_client_ip();

    if (isset($_POST['etapa']) && $_POST['etapa'] === '2fa') {
        $apiUserId = (string) ($_SESSION['usuarioapi_id'] ?? '0');
        $bucketOtp = '2fa_api:' . $apiUserId . '|' . $ip;
        $otpStatus = rate_limit_status($bucketOtp, $otpRlMax, $otpRlWindow);
        $etapa = '2fa';

        if (!$otpStatus['allowed']) {
            $retry = $otpStatus['retry_after'];
            $erro = 'Demasiadas tentativas de código. Tente novamente em ' . $retry . ' segundos.';
            http_response_code(429);
            header('Retry-After: ' . max(1, $retry));
        } else {
            $codigo_digitado = $_POST['codigo_2fa'];
            $codigo_armazenado = $_SESSION['usuarioapi_2fa_codigo'] ?? '';
            $data_envio = $_SESSION['usuarioapi_2fa_data'] ?? '';
            if (!$codigo_digitado || !$codigo_armazenado || !$data_envio) {
                rate_limit_hit($bucketOtp, $otpRlMax, $otpRlWindow);
                $erro = 'Código de verificação inválido.';
            } elseif (!verificar_codigo_2fa_email($codigo_digitado, $codigo_armazenado, $data_envio)) {
                rate_limit_hit($bucketOtp, $otpRlMax, $otpRlWindow);
                $erro = 'Código incorreto ou expirado.';
            } else {
                rate_limit_clear($bucketOtp);
                $_SESSION['usuarioapi_2fa_ok'] = true;
                unset($_SESSION['usuarioapi_2fa_codigo'], $_SESSION['usuarioapi_2fa_data']);
                header('Location: meu_token_api.php');
                exit;
            }
        }
    } else {
        $email = trim($_POST['email'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        $bucketIdentity = 'login_api:' . strtolower($email) . '|' . $ip;
        $bucketIp = 'login_api_ip:' . $ip;
        $statusIdentity = rate_limit_status($bucketIdentity, $loginRlMaxIdentity, $loginRlWindow);
        $statusIp = rate_limit_status($bucketIp, $loginRlMaxIp, $loginRlWindow);

        if (!$statusIdentity['allowed'] || !$statusIp['allowed']) {
            $retry = max($statusIdentity['retry_after'], $statusIp['retry_after']);
            $erro = 'Demasiadas tentativas de login. Tente novamente em ' . $retry . ' segundos.';
            http_response_code(429);
            header('Retry-After: ' . max(1, $retry));
        } elseif (login_api($email, $senha)) {
            rate_limit_clear($bucketIdentity);
            rate_limit_clear($bucketIp);
            global $pdo;
            $stmt = $pdo->prepare('SELECT nome, email FROM usuariosapi WHERE id = ?');
            $stmt->execute([$_SESSION['usuarioapi_id']]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            $codigo = gerar_codigo_2fa_email();
            $_SESSION['usuarioapi_2fa_codigo'] = $codigo;
            $_SESSION['usuarioapi_2fa_data'] = date('Y-m-d H:i:s');
            enviar_codigo_2fa_email($usuario['email'], $usuario['nome'], $codigo);
            $etapa = '2fa';
        } else {
            rate_limit_hit($bucketIdentity, $loginRlMaxIdentity, $loginRlWindow);
            rate_limit_hit($bucketIp, $loginRlMaxIp, $loginRlWindow);
            $erro = 'Usuário ou senha inválidos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Usuário API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width:400px;">
    <div class="card p-4">
        <h3 class="mb-3">Login - Usuário API</h3>
        <?php if ($erro): ?>
            <div class="alert alert-danger"> <?= htmlspecialchars($erro) ?> </div>
        <?php endif; ?>
        <?php if ($etapa === '2fa'): ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="etapa" value="2fa">
                <div class="mb-3">
                    <label class="form-label">Código de Verificação (enviado para seu e-mail)</label>
                    <input type="text" name="codigo_2fa" class="form-control" required autofocus>
                </div>
                <button type="submit" class="btn btn-success w-100">Verificar e Acessar Token</button>
            </form>
        <?php else: ?>
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
            <a href="cadastro_api.php" class="btn btn-link mt-3">Não tem cadastro? Cadastre-se</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html> 