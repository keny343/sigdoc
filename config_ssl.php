<?php
// config_ssl.php - Configurações de Segurança SSL/HTTPS

// Forçar HTTPS em produção
define('FORCE_HTTPS', true); // Mude para true em produção

// Configurações de segurança
define('SECURE_COOKIES', true); // Mude para true em produção
define('HTTP_STRICT_TRANSPORT_SECURITY', true); // Mude para true em produção

// Função para verificar se está usando HTTPS
function is_https()
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        $_SERVER['SERVER_PORT'] == 443;
}

// Função para forçar redirecionamento HTTPS
function force_https()
{
    if (FORCE_HTTPS && !is_https()) {
        $redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header("Location: $redirect_url", true, 301);
        exit;
    }
}

// Função para configurar headers de segurança
function set_security_headers()
{
    // Prevenir clickjacking
    header('X-Frame-Options: DENY');

    // Prevenir MIME type sniffing
    header('X-Content-Type-Options: nosniff');

    // Prevenir XSS
    header('X-XSS-Protection: 1; mode=block');

    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Content Security Policy
    // Permitir scripts/estilos de CDN (jsdelivr, unpkg) e conexões para API de rotas OSRM
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; img-src 'self' data: https://*.tile.openstreetmap.org https://unpkg.com https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; connect-src 'self' https://cdn.jsdelivr.net https://router.project-osrm.org;");

    // HSTS (HTTP Strict Transport Security)
    if (HTTP_STRICT_TRANSPORT_SECURITY && is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Função para configurar cookies seguros
function set_secure_cookies()
{
    if (SECURE_COOKIES && is_https()) {
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Strict');
    }
}

// Aplicar configurações de segurança
if (FORCE_HTTPS) {
    force_https();
}

set_security_headers();
set_secure_cookies();
?>