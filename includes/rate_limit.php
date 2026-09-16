<?php
/**
 * File-based rate limiting for auth endpoints (login / 2FA).
 * Storage: logs/rate_limit/ (writable, gitignored contents).
 */
declare(strict_types=1);

function rate_limit_client_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];
    foreach ($candidates as $raw) {
        if (!is_string($raw) || $raw === '') {
            continue;
        }
        // X-Forwarded-For may be a list
        $ip = trim(explode(',', $raw)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return '0.0.0.0';
}

function rate_limit_dir(): string
{
    $dir = __DIR__ . '/../logs/rate_limit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

/**
 * @return array{allowed: bool, remaining: int, retry_after: int, count: int}
 */
function rate_limit_status(string $bucket, int $maxAttempts, int $windowSeconds): array
{
    $file = rate_limit_dir() . '/' . hash('sha256', $bucket) . '.json';
    $now = time();
    $attempts = [];

    if (is_readable($file)) {
        $raw = @file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($data) && isset($data['attempts']) && is_array($data['attempts'])) {
            foreach ($data['attempts'] as $ts) {
                $t = (int) $ts;
                if ($t > $now - $windowSeconds) {
                    $attempts[] = $t;
                }
            }
        }
    }

    $count = count($attempts);
    $allowed = $count < $maxAttempts;
    $retryAfter = 0;
    if (!$allowed && $attempts !== []) {
        $oldest = min($attempts);
        $retryAfter = max(1, ($oldest + $windowSeconds) - $now);
    }

    return [
        'allowed' => $allowed,
        'remaining' => max(0, $maxAttempts - $count),
        'retry_after' => $retryAfter,
        'count' => $count,
    ];
}

/**
 * Record a failed attempt for the bucket.
 *
 * @return array{allowed: bool, remaining: int, retry_after: int, count: int}
 */
function rate_limit_hit(string $bucket, int $maxAttempts, int $windowSeconds): array
{
    $file = rate_limit_dir() . '/' . hash('sha256', $bucket) . '.json';
    $now = time();
    $attempts = [];

    if (is_readable($file)) {
        $raw = @file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($data) && isset($data['attempts']) && is_array($data['attempts'])) {
            foreach ($data['attempts'] as $ts) {
                $t = (int) $ts;
                if ($t > $now - $windowSeconds) {
                    $attempts[] = $t;
                }
            }
        }
    }

    $attempts[] = $now;
    @file_put_contents(
        $file,
        json_encode(['bucket' => $bucket, 'attempts' => $attempts], JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );

    return rate_limit_status($bucket, $maxAttempts, $windowSeconds);
}

function rate_limit_clear(string $bucket): void
{
    $file = rate_limit_dir() . '/' . hash('sha256', $bucket) . '.json';
    if (is_file($file)) {
        @unlink($file);
    }
}

/**
 * Block request with HTTP 429 when over limit (for early exit before auth work).
 */
function rate_limit_reject(int $retryAfter, string $message): void
{
    http_response_code(429);
    header('Retry-After: ' . max(1, $retryAfter));
    header('Content-Type: text/plain; charset=utf-8');
    exit($message);
}
