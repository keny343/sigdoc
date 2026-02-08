<?php
// Configurações de cabeçalho para permitir requisições de qualquer origem (CORS)
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Max-Age: 3600');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// Inclui os arquivos necessários
require_once '../conexao.php';

try {
    // Garantir que a tabela exista (evita erro 500 em instalações onde ela ainda não foi criada)
    $pdo->exec("CREATE TABLE IF NOT EXISTS categorias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        cor VARCHAR(20) DEFAULT '#3498db',
        icone VARCHAR(100) DEFAULT NULL,
        ativo TINYINT(1) DEFAULT 1,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Prepara a consulta SQL para buscar as categorias
    $query = "SELECT id, nome, cor, icone FROM categorias WHERE ativo = 1 ORDER BY nome";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    // Busca os resultados
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Retorna as categorias em formato JSON
    http_response_code(200);
    echo json_encode($categorias, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    // Em caso de erro, retorna uma mensagem de erro
    http_response_code(500);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro ao buscar categorias: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
