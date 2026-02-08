<?php
require_once __DIR__ . '/../../includes/db.php';
$method = $_SERVER['REQUEST_METHOD'];
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($method) {
    case 'POST':
        // Simulação de importação
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $importados = isset($data['documentos']) ? count($data['documentos']) : 0;
        json_response(['success' => true, 'mensagem' => 'Importação concluída', 'importados' => $importados]);
        break;
    default:
        json_response(['error' => 'Método não suportado'], 405);
} 