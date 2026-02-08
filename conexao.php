<?php
// conexao.php - Conexão padrão com banco de dados MySQL usando PDO
// Configurações para Infinity Free
$host = 'sql106.infinityfree.com';
$dbname = 'if0_40919058_sigdoc';
$username = 'if0_40919058';
$password = 'Kenykeny2003';
$port = 3306;

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}
?> 