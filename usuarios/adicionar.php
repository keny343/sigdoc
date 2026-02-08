<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
if (!is_logged_in() || !is_admin()) {
    header('Location: ../auth/login.php');
    exit;
}
$sucesso = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    $perfil = $_POST['perfil'];
    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, perfil, ultima_localizacao) VALUES (?, ?, ?, ?, ST_GeomFromText('POINT(0 0)'))");
    $stmt->execute([$nome, $email, $senha, $perfil]);
    $sucesso = true;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastrar Usuário - SIGDoc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="../includes/style.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/icons/file-earmark-text.svg">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="../painel.php">SIGDoc</a>
    <div class="d-flex">
      <a href="../painel.php" class="btn btn-outline-light me-2">Painel</a>
      <a href="../auth/logout.php" class="btn btn-danger">Sair</a>
    </div>
  </div>
</nav>
<div class="container card p-4">
    <h2 class="mb-4">Cadastrar Novo Usuário</h2>
    <?php if ($sucesso): ?>
        <div class="alert alert-success">Usuário cadastrado com sucesso!</div>
    <?php endif; ?>
    <form method="post">
        <div class="mb-3">
            <label class="form-label">Nome</label>
            <input type="text" name="nome" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Senha</label>
            <input type="password" name="senha" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Perfil</label>
            <select name="perfil" class="form-select" required>
                <option value="admin">Administrador</option>
                <option value="gestor">Gestor</option>
                <option value="colaborador">Colaborador</option>
                <option value="visitante">Visitante</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Cadastrar</button>
    </form>
</div>
<footer>
  <span>Desenvolvido para SIGDoc &copy; <?php echo date('Y'); ?> | Sistema de Gestão Documental</span>
</footer>
</body>
</html> 