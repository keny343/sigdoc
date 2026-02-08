<?php
require_once 'includes/auth.php';

if (isset($_SESSION['usuarioapi_id']) && !empty($_SESSION['usuarioapi_2fa_ok'])) {
    header('Location: meu_token_api.php');
    exit;
}

$erro = '';
$etapa = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['etapa']) && $_POST['etapa'] === '2fa') {
        // Verificação do código 2FA
        $codigo_digitado = $_POST['codigo_2fa'];
        $codigo_armazenado = $_SESSION['usuarioapi_2fa_codigo'] ?? '';
        $data_envio = $_SESSION['usuarioapi_2fa_data'] ?? '';
        if (!$codigo_digitado || !$codigo_armazenado || !$data_envio) {
            $erro = 'Código de verificação inválido.';
            $etapa = '2fa';
        } elseif (!verificar_codigo_2fa_email($codigo_digitado, $codigo_armazenado, $data_envio)) {
            $erro = 'Código incorreto ou expirado.';
            $etapa = '2fa';
        } else {
            $_SESSION['usuarioapi_2fa_ok'] = true;
            unset($_SESSION['usuarioapi_2fa_codigo'], $_SESSION['usuarioapi_2fa_data']);
            header('Location: meu_token_api.php');
            exit;
        }
    } else {
        // Login inicial
        $email = trim($_POST['email'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        if (login_api($email, $senha)) {
            // Buscar nome do usuário
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
                <input type="hidden" name="etapa" value="2fa">
                <div class="mb-3">
                    <label class="form-label">Código de Verificação (enviado para seu e-mail)</label>
                    <input type="text" name="codigo_2fa" class="form-control" required autofocus>
                </div>
                <button type="submit" class="btn btn-success w-100">Verificar e Acessar Token</button>
            </form>
        <?php else: ?>
            <form method="post">
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