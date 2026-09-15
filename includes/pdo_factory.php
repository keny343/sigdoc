<?php
/**
 * Shared PDO factory for SIGDoc (Aiven / managed MySQL TLS).
 *
 * Env: SIGDOC_DB_SSL=1|true|require — or auto-enabled for *.aivencloud.com hosts
 */
declare(strict_types=1);

/**
 * @return array{0: string, 1: string, 2: string, 3: array<int, mixed>, 4: bool}
 */
function sigdoc_pdo_params(): array
{
    require_once __DIR__ . '/config.php';

    $host = (string) sigdoc_config('db.host');
    $db = (string) sigdoc_config('db.name');
    $user = (string) sigdoc_config('db.user');
    $pass = (string) sigdoc_config('db.pass');
    $port = (int) sigdoc_config('db.port', 3306);
    $charset = (string) sigdoc_config('db.charset', 'utf8mb4');
    $ssl = sigdoc_config('db.ssl', false);

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10,
    ];

    $sslFlag = $ssl === true
        || $ssl === 1
        || $ssl === '1'
        || (is_string($ssl) && in_array(strtolower($ssl), ['true', 'require', 'required', 'yes'], true));

    // Aiven always needs TLS even if env var was forgotten
    if (!$sslFlag && str_contains(strtolower($host), 'aivencloud.com')) {
        $sslFlag = true;
    }

    return [$dsn, $user, $pass, $options, $sslFlag];
}

/**
 * @param array<int, mixed> $base
 * @return array<int, mixed>
 */
function sigdoc_pdo_ssl_options(array $base): array
{
    $options = $base;
    $caCandidates = [
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
        __DIR__ . '/../certs/aiven-ca.pem',
    ];
    foreach ($caCandidates as $ca) {
        if (is_readable($ca)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
            break;
        }
    }
    if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
    // Ensure TLS is requested even without a CA file (mysqlnd)
    if (!isset($options[PDO::MYSQL_ATTR_SSL_CA]) && defined('PDO::MYSQL_ATTR_SSL_CA')) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = '';
    }
    return $options;
}

function sigdoc_pdo(): PDO
{
    [$dsn, $user, $pass, $base, $sslFlag] = sigdoc_pdo_params();

    $errors = [];

    if ($sslFlag) {
        try {
            return new PDO($dsn, $user, $pass, sigdoc_pdo_ssl_options($base));
        } catch (PDOException $e) {
            $errors[] = 'ssl: ' . $e->getMessage();
        }
    }

    try {
        return new PDO($dsn, $user, $pass, $base);
    } catch (PDOException $e) {
        $errors[] = 'plain: ' . $e->getMessage();
        throw new PDOException('DB connect failed (' . implode(' | ', $errors) . ')', (int) $e->getCode(), $e);
    }
}
