<?php
require_once 'includes/auth.php';

$erro = '';
$sucesso = '';
$etapa = 'form';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['etapa']) && $_POST['etapa'] === '2fa') {
        // Etapa de verificação do código 2FA
        $nome = $_POST['nome'];
        $email = $_POST['email'];
        $senha = $_POST['senha'];
        $codigo_digitado = $_POST['codigo_2fa'];
        $codigo_armazenado = $_SESSION['cadastro_2fa_codigo'] ?? '';
        $data_envio = $_SESSION['cadastro_2fa_data'] ?? '';
        if (!$codigo_digitado || !$codigo_armazenado || !$data_envio) {
            $erro = 'Código de verificação inválido.';
            $etapa = '2fa';
        } elseif (!verificar_codigo_2fa_email($codigo_digitado, $codigo_armazenado, $data_envio)) {
            $erro = 'Código incorreto ou expirado.';
            $etapa = '2fa';
        } else {
            // Cadastra usuário
            $perfil = 'colaborador';
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO usuariosapi (nome, email, senha, perfil, dois_fatores_ativado) VALUES (?, ?, ?, ?, 1)');
            $stmt->execute([$nome, $email, $hash, $perfil]);
            unset($_SESSION['cadastro_2fa_codigo'], $_SESSION['cadastro_2fa_data']);
            $sucesso = 'Cadastro realizado com sucesso! Faça login para acessar seu token.';
            $etapa = 'final';
        }
    } else {
        // Etapa de preenchimento do formulário
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $senha2 = $_POST['senha2'] ?? '';
        $perfil = 'colaborador';
        if (!$nome || !$email || !$senha || !$senha2) {
            $erro = 'Preencha todos os campos.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'E-mail inválido.';
        } elseif ($senha !== $senha2) {
            $erro = 'As senhas não coincidem.';
        } elseif (strlen($senha) < 6) {
            $erro = 'A senha deve ter pelo menos 6 caracteres.';
        } else {
            // Verifica se email já existe
            $stmt = $pdo->prepare('SELECT id FROM usuariosapi WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $erro = 'E-mail já cadastrado.';
            } else {
                // Gera e envia código 2FA
                $codigo = gerar_codigo_2fa_email();
                $_SESSION['cadastro_2fa_codigo'] = $codigo;
                $_SESSION['cadastro_2fa_data'] = date('Y-m-d H:i:s');
                $_SESSION['cadastro_2fa_nome'] = $nome;
                $_SESSION['cadastro_2fa_email'] = $email;
                $_SESSION['cadastro_2fa_senha'] = $senha;
                enviar_codigo_2fa_email($email, $nome, $codigo);
                $etapa = '2fa';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro de Usuário - API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width:500px;">
    <div class="card p-4">
        <h3 class="mb-3">Cadastro de Usuário para API</h3>
        <?php if ($erro): ?>
            <div class="alert alert-danger"> <?= htmlspecialchars($erro) ?> </div>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <div class="alert alert-success"> <?= htmlspecialchars($sucesso) ?> </div>
            <a href="login_api.php" class="btn btn-primary w-100 mt-3">Ir para Login</a>
        <?php elseif ($etapa === '2fa'): ?>
            <form method="post">
                <input type="hidden" name="etapa" value="2fa">
                <input type="hidden" name="nome" value="<?= htmlspecialchars($_SESSION['cadastro_2fa_nome']) ?>">
                <input type="hidden" name="email" value="<?= htmlspecialchars($_SESSION['cadastro_2fa_email']) ?>">
                <input type="hidden" name="senha" value="<?= htmlspecialchars($_SESSION['cadastro_2fa_senha']) ?>">
                <div class="mb-3">
                    <label class="form-label">Código de Verificação (enviado para seu e-mail)</label>
                    <input type="text" name="codigo_2fa" class="form-control" required autofocus>
                </div>
                <button type="submit" class="btn btn-success w-100">Verificar e Concluir Cadastro</button>
            </form>
            <a href="login_api.php" class="btn btn-link mt-3">Já tem cadastro? Faça login</a>
        <?php else: ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Senha</label>
                    <input type="password" name="senha" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirmar Senha</label>
                    <input type="password" name="senha2" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-success w-100">Cadastrar</button>
            </form>
            <a href="login_api.php" class="btn btn-link mt-3">Já tem cadastro? Faça login</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html> 