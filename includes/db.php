<?php
/**
 * Database connection bootstrap.
 *
 * Schema + permission catalog are owned by SQL install scripts.
 * Avoid heavy per-request seeding (was ~100 remote queries → multi-second pages).
 */
declare(strict_types=1);

require_once __DIR__ . '/pdo_factory.php';

try {
    // Reuse connection if auth.php already opened one
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $pdo = sigdoc_pdo();
    }
} catch (PDOException $e) {
    die('Erro na conexão com a base de dados.');
}

try {
    $pdo->query('SELECT 1 FROM usuarios LIMIT 1');
} catch (PDOException $e) {
    http_response_code(500);
    $dbName = (string) sigdoc_config('db.name', '?');
    $dbHost = (string) sigdoc_config('db.host', '?');
    header('Content-Type: text/plain; charset=utf-8');
    die(
        "SIGDoc: não foi possível ler a tabela usuarios.\n"
        . "Host: {$dbHost}\n"
        . "Database: {$dbName}\n"
        . "PDO: {$e->getCode()} " . $e->getMessage() . "\n\n"
        . "Importa database/install_aiven.sql na base Aiven."
    );
}

// One-time cheap seed only when catalog is empty (not on every request)
try {
    $count = (int) $pdo->query('SELECT COUNT(*) FROM permissoes')->fetchColumn();
    if ($count === 0) {
        require_once __DIR__ . '/seed_permissoes.php';
        sigdoc_seed_permissoes($pdo);
    }
} catch (PDOException $e) {
    // Optional tables — ignore
}
