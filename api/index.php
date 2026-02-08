<?php
// api/index.php - API REST Principal do SIGDoc

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Permitir requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../includes/auth.php';
require_once '../includes/db.php';

// Função para retornar resposta JSON
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Função para validar token de API
function validate_api_token() {
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? '';
    
    if (empty($token)) {
        return false;
    }
    
    // Remover "Bearer " se presente
    $token = str_replace('Bearer ', '', $token);
    
    // Validar token (implementação básica)
    // Em produção, use JWT ou similar
    $valid_tokens = ['sigdoc_api_2025', 'admin_token_123'];
    return in_array($token, $valid_tokens);
}

// Verificar autenticação da API
if (!validate_api_token()) {
    json_response(['error' => 'Token de API inválido'], 401);
}

// Obter método HTTP e endpoint
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['endpoint'] ?? '';

// Roteamento da API
switch ($path) {
    case 'documentos':
        require_once 'endpoints/documentos.php';
        break;
        
    case 'usuarios':
        require_once 'endpoints/usuarios.php';
        break;
        
    case 'estatisticas':
        require_once 'endpoints/estatisticas.php';
        break;
        
    case 'movimentacao':
        require_once 'endpoints/movimentacao.php';
        break;
        
    default:
        json_response([
            'error' => 'Endpoint não encontrado',
            'available_endpoints' => [
                'documentos' => 'GET, POST, PUT, DELETE - Gerenciar documentos',
                'usuarios' => 'GET - Listar usuários',
                'estatisticas' => 'GET - Estatísticas do sistema',
                'movimentacao' => 'GET - Histórico de movimentações'
            ]
        ], 404);
}
?> 