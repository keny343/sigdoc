<?php
// api/endpoints/login.php - Endpoint de login para API REST
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../includes/db.php';

// Função para resposta JSON
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Receber dados do POST
$email = $_POST['email'] ?? '';
$senha = $_POST['senha'] ?? '';

if (empty($email) || empty($senha)) {
    json_response(['error' => 'Email e senha são obrigatórios.'], 400);
}

// Buscar usuário
$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = ?');
$stmt->execute([$email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario || !password_verify($senha, $usuario['senha'])) {
    json_response(['error' => 'Email ou senha inválidos!'], 401);
}

// Gerar token simples (em produção, use JWT ou similar)
$token = 'sigdoc_api_2025'; // Pode ser personalizado por usuário

// Retornar dados do usuário e token
json_response([
    'token' => $token,
    'usuario' => [
        'id' => $usuario['id'],
        'nome' => $usuario['nome'],
        'email' => $usuario['email'],
        'perfil' => $usuario['perfil']
    ],
    'mensagem' => 'Login realizado com sucesso'
]); 