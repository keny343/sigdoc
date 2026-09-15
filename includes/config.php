<?php
/**
 * SIGDoc — configuration loader.
 *
 * Priority:
 * 1. defaults from config.example.php
 * 2. includes/config.local.php (local / InfinityFree — never commit)
 * 3. environment variables (Render / Docker) — override everything
 *
 * IMPORTANT: this file may be required from inside a function (pdo_factory).
 * Always write to $GLOBALS['config'] so sigdoc_config() sees the values.
 */
declare(strict_types=1);

$exampleFile = __DIR__ . '/config.example.php';
$configFile = __DIR__ . '/config.local.php';

/** @var array $config */
$config = is_file($exampleFile) ? (require $exampleFile) : [];

if (is_file($configFile)) {
    /** @var array $local */
    $local = require $configFile;
    $config = array_replace_recursive($config, $local);
}

/**
 * Read env from getenv, $_ENV, or $_SERVER (Apache/Docker often skips getenv).
 */
function sigdoc_env(string $key): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            $value = (string) $_ENV[$key];
        } elseif (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            $value = (string) $_SERVER[$key];
        } else {
            return null;
        }
    }
    return (string) $value;
}

$dbHost = sigdoc_env('SIGDOC_DB_HOST');
$dbName = sigdoc_env('SIGDOC_DB_NAME');
$dbUser = sigdoc_env('SIGDOC_DB_USER');
$dbPass = sigdoc_env('SIGDOC_DB_PASS');
$dbPort = sigdoc_env('SIGDOC_DB_PORT');
$dbCharset = sigdoc_env('SIGDOC_DB_CHARSET');
$dbSsl = sigdoc_env('SIGDOC_DB_SSL');

if ($dbHost !== null) {
    $config['db']['host'] = $dbHost;
}
if ($dbPort !== null) {
    $config['db']['port'] = (int) $dbPort;
}
if ($dbName !== null) {
    $config['db']['name'] = $dbName;
}
if ($dbUser !== null) {
    $config['db']['user'] = $dbUser;
}
if ($dbPass !== null) {
    $config['db']['pass'] = $dbPass;
}
if ($dbCharset !== null) {
    $config['db']['charset'] = $dbCharset;
}
if ($dbSsl !== null) {
    $config['db']['ssl'] = $dbSsl;
}

$smtpHost = sigdoc_env('SIGDOC_SMTP_HOST');
$smtpUser = sigdoc_env('SIGDOC_SMTP_USER');
$smtpPass = sigdoc_env('SIGDOC_SMTP_PASS');
$smtpPort = sigdoc_env('SIGDOC_SMTP_PORT');
$smtpSecure = sigdoc_env('SIGDOC_SMTP_SECURE');

if ($smtpHost !== null) {
    $config['smtp']['host'] = $smtpHost;
}
if ($smtpUser !== null) {
    $config['smtp']['user'] = $smtpUser;
}
if ($smtpPass !== null) {
    $config['smtp']['pass'] = $smtpPass;
}
if ($smtpPort !== null) {
    $config['smtp']['port'] = (int) $smtpPort;
}
if ($smtpSecure !== null) {
    $config['smtp']['secure'] = $smtpSecure;
}

$apiTokens = sigdoc_env('SIGDOC_API_TOKENS');
if ($apiTokens !== null) {
    $tokens = array_values(array_filter(array_map('trim', explode(',', $apiTokens)), static fn ($t) => $t !== ''));
    if ($tokens !== []) {
        $config['api']['tokens'] = $tokens;
    }
}

// Publish for sigdoc_config() even when this file is required inside a function
$GLOBALS['config'] = $config;

$host = (string) ($config['db']['host'] ?? '');
$name = (string) ($config['db']['name'] ?? '');
$user = (string) ($config['db']['user'] ?? '');
$pass = (string) ($config['db']['pass'] ?? '');

$missingLocal = !is_file($configFile);
$placeholderPass = $pass === '' || str_starts_with($pass, 'CHANGE_ME');
$incomplete = $host === '' || $name === '' || $user === '' || $placeholderPass
    || $host === '127.0.0.1' && $missingLocal && sigdoc_env('SIGDOC_DB_HOST') === null;

if ($incomplete) {
    $hint = 'Set SIGDOC_DB_HOST/NAME/USER/PASS (and SIGDOC_DB_SSL=1 for Aiven) in Render Environment, or use config.local.php.';
    $seen = [];
    foreach (['SIGDOC_DB_HOST', 'SIGDOC_DB_NAME', 'SIGDOC_DB_USER', 'SIGDOC_DB_PASS', 'SIGDOC_DB_SSL'] as $k) {
        $seen[] = $k . '=' . (sigdoc_env($k) !== null ? 'set' : 'missing');
    }
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "SIGDoc config error: {$hint}\n" . implode(', ', $seen) . "\n");
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("SIGDoc: database configuration missing.\n{$hint}\n" . implode("\n", $seen) . "\n");
}

if (!function_exists('sigdoc_config')) {
    function sigdoc_config(?string $key = null, $default = null)
    {
        $config = $GLOBALS['config'] ?? [];
        if ($key === null) {
            return $config;
        }
        $parts = explode('.', $key);
        $value = $config;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}

return $config;
