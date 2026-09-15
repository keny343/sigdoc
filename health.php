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

try {
    require_once __DIR__ . '/includes/pdo_factory.php';
    $pdo = sigdoc_pdo();
    $pdo->query('SELECT 1');
    $payload['db'] = 'up';
} catch (Throwable $e) {
    http_response_code(503);
    $payload['status'] = 'degraded';
    $payload['db'] = 'down';
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
