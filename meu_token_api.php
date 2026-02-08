<?php
require_once 'includes/auth.php';

if (!isset($_SESSION['usuarioapi_id'])) {
    header('Location: login_api.php');
    exit;
}

// Verifica se o campo api_token existe na tabela usuariosapi
try {
    $pdo->query("SELECT api_token FROM usuariosapi LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE usuariosapi ADD COLUMN api_token VARCHAR(64) DEFAULT NULL");
}

// Gera token seguro
function gerar_token_api() {
    return bin2hex(random_bytes(32));
}

// Busca token do usuário
$stmt = $pdo->prepare("SELECT api_token FROM usuariosapi WHERE id = ?");
$stmt->execute([$_SESSION['usuarioapi_id']]);
$token = $stmt->fetchColumn();

// Gera e salva token se não existir
if (!$token) {
    $token = gerar_token_api();
    $stmt = $pdo->prepare("UPDATE usuariosapi SET api_token = ? WHERE id = ?");
    $stmt->execute([$token, $_SESSION['usuarioapi_id']]);
}

// Permite regenerar token
if (isset($_POST['regenerar'])) {
    $token = gerar_token_api();
    $stmt = $pdo->prepare("UPDATE usuariosapi SET api_token = ? WHERE id = ?");
    $stmt->execute([$token, $_SESSION['usuarioapi_id']]);
    $msg = 'Token regenerado com sucesso!';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meu Token de API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
    function copiarToken() {
        var copyText = document.getElementById('tokenApi');
        navigator.clipboard.writeText(copyText.value);
        alert('Token copiado!');
    }
    </script>
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width:500px;">
    <div class="card p-4">
        <h3 class="mb-3">Meu Token de API</h3>
        <?php if (!empty($msg)): ?>
            <div class="alert alert-success"> <?= htmlspecialchars($msg) ?> </div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Token de API</label>
                <div class="input-group">
                    <input type="text" id="tokenApi" class="form-control" value="<?= htmlspecialchars($token) ?>" readonly>
                    <button type="button" class="btn btn-outline-secondary" onclick="copiarToken()">Copiar</button>
                </div>
            </div>
            <button type="submit" name="regenerar" class="btn btn-warning mt-2" onclick="return confirm('Tem certeza que deseja gerar um novo token? O antigo deixará de funcionar.')">Regenerar Token</button>
        </form>
        <hr>
        <p class="small text-muted">Use este token no header <code>Authorization: Bearer SEU_TOKEN</code> ao consumir a API REST.<br>Guarde-o com segurança!</p>
    </div>
</div>
</body>
</html> 