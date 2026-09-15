<?php
/**
 * Example configuration — safe to commit.
 * Copy to config.local.php and replace placeholders.
 */
declare(strict_types=1);

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'sigdoc',
        'user' => 'sigdoc_user',
        'pass' => 'CHANGE_ME_DB_PASSWORD',
        'charset' => 'utf8mb4',
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
