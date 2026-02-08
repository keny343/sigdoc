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
        $stmt = $pdo->query('SELECT * FROM acessos ORDER BY data_acao DESC LIMIT 50');
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_response(['success' => true, 'logs' => $logs]);
        break;
    default:
        json_response(['error' => 'Método não suportado'], 405);
} 