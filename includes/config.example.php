<?php
/**
 * Example configuration — safe to commit.
 *
 * Local / InfinityFree:
 *   copy to config.local.php and replace placeholders.
 *
 * Render / Docker:
 *   set SIGDOC_DB_* (and optional SIGDOC_SMTP_*, SIGDOC_API_TOKENS) env vars.
 *   See docs/RENDER.md — no need for config.local.php on the server.
 */
declare(strict_types=1);

return [
    'db' => [
        // InfinityFree example host: sqlXXX.infinityfree.com (NOT localhost)
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'sigdoc',
        'user' => 'sigdoc_user',
        'pass' => 'CHANGE_ME_DB_PASSWORD',
        'charset' => 'utf8mb4',
        // Aiven / managed MySQL with required TLS:
        'ssl' => false,
    ],
    'smtp' => [
        'host' => 'smtp.example.com',
        'user' => 'noreply@example.com',
        'pass' => 'CHANGE_ME_SMTP_APP_PASSWORD',
        'port' => 587,
        'secure' => 'tls',
    ],
    'api' => [
        // Comma-separated bearer tokens accepted by api/index.php (dev only).
        // Prefer per-user tokens in usuariosapi for production.
        'tokens' => [
            'CHANGE_ME_API_TOKEN',
        ],
    ],
];
