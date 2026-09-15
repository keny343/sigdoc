<?php
/**
 * Shared PDO factory for SIGDoc (supports Aiven SSL).
 *
 * Env: SIGDOC_DB_SSL=1|true|require  → enable TLS for MySQL
 */
declare(strict_types=1);

/**
 * @return array{0: string, 1: string, 2: string, 3: array<int, mixed>}
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
    ];

    $sslEnabled = $ssl === true
        || $ssl === 1
        || $ssl === '1'
        || (is_string($ssl) && in_array(strtolower($ssl), ['true', 'require', 'required', 'yes'], true));

    if ($sslEnabled) {
        // php:*-apache image ships system CAs; Aiven requires TLS.
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
        // Enable TLS even if CA path is missing (mysqlnd).
        if (!isset($options[PDO::MYSQL_ATTR_SSL_CA])) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = '';
        }
        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            // Portfolio/demo: TLS on; pin Aiven CA later for stricter verify.
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
    }

    return [$dsn, $user, $pass, $options];
}

function sigdoc_pdo(): PDO
{
    [$dsn, $user, $pass, $options] = sigdoc_pdo_params();
    return new PDO($dsn, $user, $pass, $options);
}
