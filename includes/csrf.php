<?php
/**
 * CSRF protection for HTML form POSTs (session synchronizer token).
 *
 * Usage:
 *   In forms:  <?= csrf_field() ?>
 *   On POST:   csrf_require();
 */
declare(strict_types=1);

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf" value="' . $token . '">';
}

function csrf_verify(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || $sent === '' || empty($_SESSION['_csrf'])) {
        return false;
    }
    return hash_equals((string) $_SESSION['_csrf'], $sent);
}

/**
 * Reject invalid CSRF on state-changing requests.
 */
function csrf_require(): void
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return;
    }
    if (!csrf_verify()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('CSRF token inválido ou em falta.');
    }
}
