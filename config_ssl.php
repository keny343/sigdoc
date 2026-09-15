<?php
// config_ssl.php - Configurações de Segurança SSL/HTTPS

define('FORCE_HTTPS', true);
define('SECURE_COOKIES', true);
define('HTTP_STRICT_TRANSPORT_SECURITY', true);

function is_https()
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    // InfinityFree / proxies: HTTPS termina no edge, o PHP vê HTTP
    $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwarded === 'https') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
        return true;
    }
    if (!empty($_SERVER['REQUEST_SCHEME']) && strtolower((string) $_SERVER['REQUEST_SCHEME']) === 'https') {
        return true;
    }
    return false;
}

function force_https()
{
    if (!FORCE_HTTPS || is_https()) {
        return;
    }
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    if ($host === '') {
        return;
    }
    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

function set_security_headers()
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header(
        "Content-Security-Policy: default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; " .
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; " .
        "img-src 'self' data: https://*.tile.openstreetmap.org https://unpkg.com https://cdn.jsdelivr.net; " .
        "font-src 'self' https://cdn.jsdelivr.net; " .
        "connect-src 'self' https://cdn.jsdelivr.net https://router.project-osrm.org;"
    );

    if (HTTP_STRICT_TRANSPORT_SECURITY && is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function set_secure_cookies()
{
    if (SECURE_COOKIES && is_https()) {
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Lax');
    }
}

if (FORCE_HTTPS) {
    force_https();
}

set_security_headers();
set_secure_cookies();
