<?php
require_once __DIR__ . '/../../includes/db.php';
$method = $_SERVER['REQUEST_METHOD'];
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($method) {
    case 'GET':
        $stmt = $pdo->query('SELECT m.id, m.documento_id, m.usuario_id, m.acao, m.observacao, m.data_acao, u.nome as usuario_nome FROM movimentacao m JOIN usuarios u ON m.usuario_id = u.id ORDER BY m.data_acao DESC LIMIT 50');
        $movs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_response(['success' => true, 'data' => $movs]);
        break;
    default:
        json_response(['error' => 'Método não suportado'], 405);
} 