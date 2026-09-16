<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/rate_limit.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

if (!precisa_completar_2fa()) {
    header('Location: ../documentos/listar.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$erro = '';
$mensagem = '';
$documento_id = $_GET['documento_id'] ?? null;
$url_retorno = $_GET['retorno'] ?? '../documentos/listar.php';
$otpRlMax = 5;
$otpRlWindow = 900;
$bucketOtp = '2fa:' . $usuario_id . '|' . rate_limit_client_ip();

$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

$codigo_existe = !empty($usuario['codigo_2fa']) && !empty($usuario['data_codigo_2fa']);
$codigo_expirado = false;

if ($codigo_existe) {
    $tempo_expiracao = 600;
    $tempo_atual = time();
    $tempo_envio = strtotime($usuario['data_codigo_2fa']);
    $codigo_expirado = (($tempo_atual - $tempo_envio) > $tempo_expiracao);
}

if (!$codigo_existe || $codigo_expirado) {
    if (gerar_e_enviar_codigo_2fa($usuario_id)) {
        $mensagem = 'Código de verificação enviado para o seu email.';
        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $erro = 'Erro ao enviar código por email. Tente novamente.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $otpStatus = rate_limit_status($bucketOtp, $otpRlMax, $otpRlWindow);
    if (!$otpStatus['allowed']) {
        $retry = $otpStatus['retry_after'];
        $erro = 'Demasiadas tentativas de código. Tente novamente em ' . $retry . ' segundos.';
        http_response_code(429);
        header('Retry-After: ' . max(1, $retry));
    } else {
        $codigo_digitado = $_POST['codigo_2fa'] ?? '';

        if (verificar_codigo_2fa_email($codigo_digitado, $usuario['codigo_2fa'], $usuario['data_codigo_2fa'])) {
            rate_limit_clear($bucketOtp);
            marcar_2fa_verificado();
            $stmt = $pdo->prepare('UPDATE usuarios SET codigo_2fa = NULL, data_codigo_2fa = NULL WHERE id = ?');
            $stmt->execute([$usuario_id]);

            if ($documento_id) {
                registrar_tentativa_acesso_sigiloso($usuario_id, $documento_id, true);
            }

            header('Location: ' . $url_retorno);
            exit;
        }

        rate_limit_hit($bucketOtp, $otpRlMax, $otpRlWindow);
        $erro = 'Código inválido ou expirado.';
        if ($documento_id) {
            registrar_tentativa_acesso_sigiloso($usuario_id, $documento_id, false);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verificação 2FA — SIGDoc</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../includes/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-shell" style="grid-template-columns:1fr">
  <main class="auth-main" style="min-height:100vh">
    <div class="auth-panel">
      <div class="auth-panel-head">
        <div class="sig-mark" aria-hidden="true">SD</div>
        <h1>Verificação 2FA</h1>
        <p>Introduza o código de 6 dígitos enviado por email.</p>
      </div>

      <?php if ($mensagem): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div>
      <?php endif; ?>
      <?php if ($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
      <?php endif; ?>

      <div class="alert alert-info">
        Enviado para <strong><?= htmlspecialchars($usuario['email'] ?? '') ?></strong>.
        Verifique também o spam. Expira em 10 minutos.
      </div>

      <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label" for="codigo_2fa">Código</label>
          <input type="text" name="codigo_2fa" id="codigo_2fa" class="form-control form-control-lg text-center"
                 maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="000000" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary w-100 mb-3">Verificar</button>
      </form>

      <div class="d-flex gap-2 justify-content-center">
        <a href="configurar_2fa.php" class="btn btn-outline-secondary btn-sm">Configurar 2FA</a>
        <a href="logout.php" class="btn btn-outline-secondary btn-sm">Sair</a>
      </div>
    </div>
  </main>
</div>
<script>
document.querySelector('input[name="codigo_2fa"]').addEventListener('input', function () {
  this.value = this.value.replace(/[^0-9]/g, '').substring(0, 6);
});
</script>
</body>
</html>
