<?php
/**
 * Logout — session only (no database round-trip).
 */
declare(strict_types=1);

require_once __DIR__ . '/../config_ssl.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        (bool) $params['secure'],
        (bool) $params['httponly']
    );
}

session_destroy();

header('Location: /auth/login.php', true, 302);
exit;
