<?php
// conexao.php — PDO connection (config.local.php and/or SIGDOC_DB_* env)
require_once __DIR__ . '/includes/pdo_factory.php';

try {
    $pdo = sigdoc_pdo();
} catch (PDOException $e) {
    die('Erro na conexão com a base de dados.');
}
