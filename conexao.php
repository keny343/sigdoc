<?php
// conexao.php — PDO connection (uses includes/config.local.php)
require_once __DIR__ . '/includes/config.php';

$host = sigdoc_config('db.host');
$dbname = sigdoc_config('db.name');
$username = sigdoc_config('db.user');
$password = sigdoc_config('db.pass');
$port = (int) sigdoc_config('db.port', 3306);
$charset = sigdoc_config('db.charset', 'utf8mb4');

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erro na conexão com a base de dados.');
}
