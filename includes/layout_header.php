<?php
/**
 * Shared app shell — set before include:
 *   $sigdoc_base   string  '' or '../'
 *   $page_title    string
 *   $sigdoc_active string  painel|documentos|mapa|2fa|backup|''
 *   $sigdoc_extra_head string optional HTML for <head>
 */
if (!isset($sigdoc_base)) {
    $sigdoc_base = '';
}
if (!isset($page_title)) {
    $page_title = 'SIGDoc';
}
if (!isset($sigdoc_active)) {
    $sigdoc_active = '';
}
if (!isset($sigdoc_extra_head)) {
    $sigdoc_extra_head = '';
}

$nome = $_SESSION['usuario_nome'] ?? '';
$perfil = $_SESSION['perfil'] ?? '';
$lang = function_exists('get_lang') ? get_lang() : 'pt';

$nav = [
    ['id' => 'documentos', 'href' => $sigdoc_base . 'documentos/listar.php', 'label' => function_exists('t') ? t('documents') : 'Documentos', 'icon' => 'bi-folder2-open'],
    ['id' => 'painel', 'href' => $sigdoc_base . 'painel.php', 'label' => function_exists('t') ? t('management_panel') : 'Painel', 'icon' => 'bi-speedometer2'],
    ['id' => 'mapa', 'href' => $sigdoc_base . 'mapa.php', 'label' => 'Mapa', 'icon' => 'bi-geo-alt'],
    ['id' => '2fa', 'href' => $sigdoc_base . 'auth/configurar_2fa.php', 'label' => '2FA', 'icon' => 'bi-shield-lock'],
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang === 'en' ? 'en' : 'pt', ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?> — SIGDoc</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= htmlspecialchars($sigdoc_base, ENT_QUOTES, 'UTF-8') ?>includes/style.css" rel="stylesheet">
  <?= $sigdoc_extra_head ?>
  <?php if (function_exists('generate_custom_css')) echo generate_custom_css(); ?>
</head>
<body class="app-shell">
<div class="sig-backdrop" id="sigBackdrop" aria-hidden="true"></div>
<aside class="sig-sidebar" id="sigSidebar" aria-label="Navegação">
  <a class="sig-sidebar-brand" href="<?= htmlspecialchars($sigdoc_base . 'painel.php', ENT_QUOTES, 'UTF-8') ?>">
    <span class="sig-mark" aria-hidden="true">SD</span>
    <span>
      <div class="sig-brand-title">SIGDoc</div>
      <div class="sig-brand-sub">Document control</div>
    </span>
  </a>
  <nav class="sig-nav">
    <?php foreach ($nav as $item): ?>
      <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
         class="<?= $sigdoc_active === $item['id'] ? 'active' : '' ?>">
        <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
        <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
      </a>
    <?php endforeach; ?>
    <?php if (function_exists('is_admin') && is_admin()): ?>
      <a href="<?= htmlspecialchars($sigdoc_base . 'backup_system.php', ENT_QUOTES, 'UTF-8') ?>"
         class="<?= $sigdoc_active === 'backup' ? 'active' : '' ?>">
        <i class="bi bi-hdd" aria-hidden="true"></i>
        Backup
      </a>
    <?php endif; ?>
  </nav>
  <div class="sig-sidebar-foot">
    <div class="sig-user">
      <strong><?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?></strong>
      <span><?= htmlspecialchars($perfil, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <nav class="sig-nav">
      <a href="<?= htmlspecialchars($sigdoc_base . 'auth/logout.php', ENT_QUOTES, 'UTF-8') ?>">
        <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
        <?= function_exists('t') ? t('logout') : 'Sair' ?>
      </a>
    </nav>
  </div>
</aside>
<div class="sig-main">
  <header class="sig-topbar">
    <div class="d-flex align-items-center gap-2">
      <button type="button" class="sig-mobile-toggle" id="sigMenuBtn" aria-label="Abrir menu">
        <i class="bi bi-list" aria-hidden="true"></i>
      </button>
      <h1 class="sig-topbar-title"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
    <div class="sig-topbar-actions">
      <?php if (function_exists('set_language_cookie')): ?>
      <form method="get" class="m-0">
        <select name="lang" onchange="this.form.submit()" class="form-select form-select-sm" aria-label="Idioma" style="width:auto">
          <option value="pt"<?= $lang === 'pt' ? ' selected' : '' ?>>PT</option>
          <option value="en"<?= $lang === 'en' ? ' selected' : '' ?>>EN</option>
        </select>
      </form>
      <?php endif; ?>
      <?php if (!empty($sigdoc_top_actions)) echo $sigdoc_top_actions; ?>
    </div>
  </header>
  <main class="sig-content">
