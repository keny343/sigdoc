<?php
/**
 * SIGDoc — configuration loader.
 *
 * Priority:
 * 1. defaults from config.example.php
 * 2. includes/config.local.php (local / InfinityFree upload — never commit)
 * 3. environment variables (Render / Docker) — override everything
 *
 * Env vars:
 *   SIGDOC_DB_HOST, SIGDOC_DB_PORT, SIGDOC_DB_NAME, SIGDOC_DB_USER, SIGDOC_DB_PASS
 *   SIGDOC_DB_SSL=1|true|require  (required for Aiven)
 *   SIGDOC_SMTP_HOST, SIGDOC_SMTP_USER, SIGDOC_SMTP_PASS, SIGDOC_SMTP_PORT, SIGDOC_SMTP_SECURE
 *   SIGDOC_API_TOKENS  (comma-separated)
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
 * Read a non-empty environment variable.
 */
function sigdoc_env(string $key): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return null;
    }
    return $value;
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

$host = (string) ($config['db']['host'] ?? '');
$name = (string) ($config['db']['name'] ?? '');
$user = (string) ($config['db']['user'] ?? '');
$pass = (string) ($config['db']['pass'] ?? '');

$missingLocal = !is_file($configFile);
$placeholderPass = $pass === '' || str_starts_with($pass, 'CHANGE_ME');
$incomplete = $host === '' || $name === '' || $user === '' || $placeholderPass;

if ($incomplete) {
    $hint = $missingLocal
        ? 'Set SIGDOC_DB_* environment variables (Render) or copy config.example.php → config.local.php.'
        : 'Database credentials look incomplete or still use CHANGE_ME placeholders.';
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "SIGDoc config error: {$hint}\n");
    }
    http_response_code(500);
    exit('SIGDoc: database configuration missing. ' . $hint);
}

if (!function_exists('sigdoc_config')) {
    function sigdoc_config(?string $key = null, $default = null)
    {
        global $config;
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
