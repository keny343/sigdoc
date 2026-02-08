<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// Se não precisa completar 2FA, redirecionar
if (!precisa_completar_2fa()) {
    header('Location: ../documentos/listar.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$erro = '';
$mensagem = '';
$documento_id = $_GET['documento_id'] ?? null;
$url_retorno = $_GET['retorno'] ?? '../documentos/listar.php';

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Gerar e enviar código se não existir ou se expirou
$codigo_existe = !empty($usuario['codigo_2fa']) && !empty($usuario['data_codigo_2fa']);
$codigo_expirado = false;

if ($codigo_existe) {
    $tempo_expiracao = 600; // 10 minutos
    $tempo_atual = time();
    $tempo_envio = strtotime($usuario['data_codigo_2fa']);
    $codigo_expirado = (($tempo_atual - $tempo_envio) > $tempo_expiracao);
}

if (!$codigo_existe || $codigo_expirado) {
    if (gerar_e_enviar_codigo_2fa($usuario_id)) {
        $mensagem = "Código de verificação enviado para seu email!";
        // Atualizar dados do usuário
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $erro = "Erro ao enviar código por email. Tente novamente.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo_digitado = $_POST['codigo_2fa'];
    
    if (verificar_codigo_2fa_email($codigo_digitado, $usuario['codigo_2fa'], $usuario['data_codigo_2fa'])) {
        marcar_2fa_verificado();
        
        // Limpar código usado
        $stmt = $pdo->prepare("UPDATE usuarios SET codigo_2fa = NULL, data_codigo_2fa = NULL WHERE id = ?");
        $stmt->execute([$usuario_id]);
        
        // Registrar tentativa bem-sucedida se for para documento específico
        if ($documento_id) {
            registrar_tentativa_acesso_sigiloso($usuario_id, $documento_id, true);
        }
        
        header('Location: ' . $url_retorno);
        exit;
    } else {
        $erro = "Código inválido ou expirado. Verifique seu email e tente novamente.";
        
        // Registrar tentativa mal-sucedida se for para documento específico
        if ($documento_id) {
            registrar_tentativa_acesso_sigiloso($usuario_id, $documento_id, false);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificação 2FA - SIGDoc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="../includes/style.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/icons/file-earmark-text.svg">
</head>
<body>
<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card p-4 shadow" style="min-width:400px;">
        <div class="text-center mb-4">
            <h2>🔐 Verificação 2FA</h2>
            <p class="text-muted">Digite o código enviado para seu email</p>
        </div>
        
        <?php if ($mensagem): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>
        
        <?php if ($erro): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        
        <div class="alert alert-info">
            <h6>📧 Código Enviado</h6>
            <p>Um código de 6 dígitos foi enviado para: <strong><?= htmlspecialchars($usuario['email']) ?></strong></p>
            <p><small>Verifique sua caixa de spam se não receber o email.</small></p>
        </div>
        
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Código de 6 dígitos:</label>
                <input type="text" name="codigo_2fa" class="form-control form-control-lg text-center" 
                       maxlength="6" pattern="[0-9]{6}" placeholder="000000" required autofocus>
                <div class="form-text">Digite o código recebido por email</div>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 mb-3">
                <i class="bi bi-shield-check"></i> Verificar Código
            </button>
        </form>
        
        <div class="text-center">
            <a href="configurar_2fa.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-gear"></i> Configurar 2FA
            </a>
            <a href="logout.php" class="btn btn-outline-danger btn-sm ms-2">
                <i class="bi bi-box-arrow-right"></i> Sair
            </a>
        </div>
        
        <div class="alert alert-warning mt-3">
            <h6>⏰ Código Expira em 10 Minutos</h6>
            <p class="mb-0">Por segurança, o código expira automaticamente. Se não receber o email, verifique sua caixa de spam.</p>
        </div>
    </div>
</div>

<script>
  function toggleDarkMode() {
    document.body.classList.toggle('bg-dark');
    document.body.classList.toggle('text-light');
    localStorage.setItem('darkmode', document.body.classList.contains('bg-dark'));
  }
</script>

<script>
// Formatação automática do código
document.querySelectorAll('input[pattern]').forEach(input => {
    input.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').substring(0, 6);
    });
});

// Foco automático no campo de código
document.querySelector('input[name="codigo_2fa"]').focus();
</script>
</body>
</html> 