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
        $documento_id = isset($_GET['documento_id']) ? (int)$_GET['documento_id'] : 0;
        if (!$documento_id) {
            json_response(['error' => 'documento_id é obrigatório'], 400);
        }
        $stmt = $pdo->prepare('SELECT * FROM documento_versoes WHERE documento_id = ? ORDER BY numero_versao DESC');
        $stmt->execute([$documento_id]);
        $versoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_response(['success' => true, 'data' => $versoes]);
        break;
    default:
        json_response(['error' => 'Método não suportado'], 405);
} 