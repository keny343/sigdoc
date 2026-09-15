<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/lang.php';

if (isset($_GET['lang']) && in_array($_GET['lang'], ['pt', 'en'], true)) {
    set_language_cookie($_GET['lang']);
}

if (is_logged_in()) {
    if (function_exists('precisa_completar_2fa') && precisa_completar_2fa()) {
        header('Location: /auth/verificar_2fa.php');
        exit;
    }
    header('Location: /painel.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');

    if ($email === '' || $senha === '') {
        $erro = 'Email e palavra-passe são obrigatórios.';
    } elseif (login($email, $senha)) {
        try {
            if (isset($pdo, $_SESSION['usuario_id'])) {
                $stmt = $pdo->prepare("INSERT INTO acessos (usuario_id, acao, ip) VALUES (?, 'login', ?)");
                $stmt->execute([$_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'] ?? null]);
            }
        } catch (Throwable $e) {
            // audit is best-effort
        }

        if (function_exists('precisa_completar_2fa') && precisa_completar_2fa()) {
            header('Location: /auth/verificar_2fa.php');
            exit;
        }
        header('Location: /painel.php');
        exit;
    } else {
        $erro = 'Credenciais inválidas.';
    }
}

$lang = get_lang();
?>
<!DOCTYPE html>
<html lang="<?= $lang === 'en' ? 'en' : 'pt' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Entrar — SIGDoc</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/includes/style.css" rel="stylesheet">
  <style>
    /* Fallback mínimo se /includes/style.css não carregar */
    body { margin: 0; font-family: system-ui, sans-serif; }
    .auth-panel { max-width: 24rem; }
    .mb-3, .mb-4 { margin-bottom: 1rem; }
    .form-control { display: block; width: 100%; box-sizing: border-box; padding: .6rem .75rem; }
    .btn.w-100 { width: 100%; padding: .7rem; }
  </style>
</head>
<body>
<div class="auth-shell">
  <aside class="auth-aside">
    <div class="auth-aside-body">
      <p class="auth-kicker">Document management</p>
      <h1 class="auth-brand">SIGDoc</h1>
      <p class="auth-lede">
        Plataforma de gestão documental com controlo de acesso, auditoria e mapa.
      </p>
    </div>
    <p class="auth-aside-foot">Access control · PHP · MySQL</p>
  </aside>

  <main class="auth-main">
    <div class="auth-panel">
      <div class="auth-panel-head">
        <div class="sig-mark" aria-hidden="true">SD</div>
        <h1>Entrar</h1>
        <p>Use a sua conta institucional para continuar.</p>
      </div>

      <?php if ($erro): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="post" class="auth-form" novalidate>
        <div class="mb-3">
          <label class="form-label" for="email">Email</label>
          <input class="form-control" type="email" id="email" name="email" required autocomplete="username"
                 value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="mb-4">
          <label class="form-label" for="senha">Palavra-passe</label>
          <input class="form-control" type="password" id="senha" name="senha" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary w-100">Entrar</button>
      </form>

      <form method="get" class="mt-4">
        <select name="lang" onchange="this.form.submit()" class="form-select form-select-sm" style="width:auto" aria-label="Idioma">
          <option value="pt"<?= $lang === 'pt' ? ' selected' : '' ?>>PT</option>
          <option value="en"<?= $lang === 'en' ? ' selected' : '' ?>>EN</option>
        </select>
      </form>
    </div>
  </main>
</div>
</body>
</html>
