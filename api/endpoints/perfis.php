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
        $perfis = [
            ['id' => 1, 'nome' => 'admin'],
            ['id' => 2, 'nome' => 'gestor'],
            ['id' => 3, 'nome' => 'colaborador'],
            ['id' => 4, 'nome' => 'visitante']
        ];
        json_response(['success' => true, 'perfis' => $perfis]);
        break;
    default:
        json_response(['error' => 'Método não suportado'], 405);
} 