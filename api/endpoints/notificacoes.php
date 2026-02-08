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
        // Simulação de notificações
        $notificacoes = [
            ['id' => 1, 'mensagem' => 'Novo documento disponível', 'data' => date('Y-m-d H:i:s')],
            ['id' => 2, 'mensagem' => 'Prazo de documento expirando', 'data' => date('Y-m-d H:i:s')]
        ];
        json_response(['success' => true, 'notificacoes' => $notificacoes]);
        break;
    default:
        json_response(['error' => 'Método não suportado'], 405);
} 