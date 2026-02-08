<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!is_logged_in() || !is_admin()) {
    header('Location: webhooks_login.php');
    exit;
}

// Adicionar webhook
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'], $_POST['evento'])) {
    $url = trim($_POST['url']);
    $evento = trim($_POST['evento']);
    $token = trim($_POST['token'] ?? '');
    if ($url && $evento) {
        $stmt = $pdo->prepare('INSERT INTO webhooks (url, evento, ativo, token) VALUES (?, ?, 1, ?)');
        $stmt->execute([$url, $evento, $token]);
        $msg = 'Webhook adicionado com sucesso!';
    }
}
// Editar token
if (isset($_POST['edit_token_id'], $_POST['edit_token_value'])) {
    $id = (int)$_POST['edit_token_id'];
    $token = trim($_POST['edit_token_value']);
    $stmt = $pdo->prepare('UPDATE webhooks SET token = ? WHERE id = ?');
    $stmt->execute([$token, $id]);
    $msg = 'Token atualizado!';
}

// Ativar/desativar webhook
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare('UPDATE webhooks SET ativo = NOT ativo WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: webhooks_admin.php');
    exit;
}

// Remover webhook
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM webhooks WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: webhooks_admin.php');
    exit;
}

// Listar webhooks
$stmt = $pdo->query('SELECT * FROM webhooks ORDER BY id DESC');
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administração de Webhooks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2 class="mb-4">Administração de Webhooks</h2>
    <?php if (!empty($msg)): ?>
        <div class="alert alert-success"> <?= htmlspecialchars($msg) ?> </div>
    <?php endif; ?>
    <form method="post" class="row g-3 mb-4 card card-body">
        <div class="col-md-4">
            <input type="url" name="url" class="form-control" placeholder="URL do Webhook" required>
        </div>
        <div class="col-md-3">
            <input type="text" name="evento" class="form-control" placeholder="Evento (ex: documento_criado)" required>
        </div>
        <div class="col-md-3">
            <input type="text" name="token" class="form-control" placeholder="Token (opcional)">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Adicionar Webhook</button>
        </div>
    </form>
    <div class="card">
        <div class="card-header">Webhooks Cadastrados</div>
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>URL</th>
                        <th>Evento</th>
                        <th>Status</th>
                        <th>Token</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($webhooks as $wh): ?>
                    <tr>
                        <td><?= $wh['id'] ?></td>
                        <td><?= htmlspecialchars($wh['url']) ?></td>
                        <td><?= htmlspecialchars($wh['evento']) ?></td>
                        <td>
                            <span class="badge bg-<?= $wh['ativo'] ? 'success' : 'secondary' ?>">
                                <?= $wh['ativo'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" class="d-flex align-items-center gap-2 mb-0">
                                <input type="hidden" name="edit_token_id" value="<?= $wh['id'] ?>">
                                <input type="text" name="edit_token_value" value="<?= htmlspecialchars($wh['token'] ?? '') ?>" class="form-control form-control-sm" style="max-width:120px;">
                                <button type="submit" class="btn btn-sm btn-secondary">Salvar</button>
                            </form>
                        </td>
                        <td>
                            <a href="?toggle=<?= $wh['id'] ?>" class="btn btn-sm btn-warning">Ativar/Desativar</a>
                            <a href="?delete=<?= $wh['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Remover este webhook?')">Remover</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <a href="./" class="btn btn-link mt-4">Voltar ao início</a>
</div>
</body>
</html> 