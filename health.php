<?php
/**
 * Lightweight health endpoint for Render / load balancers.
 * Does not expose credentials or SQL error details.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$payload = [
    'status' => 'ok',
    'service' => 'sigdoc',
    'time' => gmdate('c'),
    'db' => 'unchecked',
];

$host = getenv('SIGDOC_DB_HOST') ?: null;
$port = (int) (getenv('SIGDOC_DB_PORT') ?: 3306);
$name = getenv('SIGDOC_DB_NAME') ?: null;
$user = getenv('SIGDOC_DB_USER') ?: null;
$pass = getenv('SIGDOC_DB_PASS') ?: null;
$charset = getenv('SIGDOC_DB_CHARSET') ?: 'utf8mb4';

// Fallback: local config file (dev / InfinityFree), without triggering hard exit helpers.
$localFile = __DIR__ . '/includes/config.local.php';
if (($host === null || $host === '') && is_file($localFile)) {
    /** @var array $local */
    $local = require $localFile;
    $host = $local['db']['host'] ?? null;
    $port = (int) ($local['db']['port'] ?? 3306);
    $name = $local['db']['name'] ?? null;
    $user = $local['db']['user'] ?? null;
    $pass = $local['db']['pass'] ?? null;
    $charset = $local['db']['charset'] ?? 'utf8mb4';
}

if (!$host || !$name || !$user || $pass === null || $pass === '') {
    http_response_code(503);
    $payload['status'] = 'degraded';
    $payload['db'] = 'unconfigured';
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset={$charset}",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]
    );
    $pdo->query('SELECT 1');
    $payload['db'] = 'up';
} catch (Throwable $e) {
    http_response_code(503);
    $payload['status'] = 'degraded';
    $payload['db'] = 'down';
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
