<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Método não suportado'], 405);
}

if (!is_logged_in()) {
    json_response(['error' => 'Não autenticado'], 401);
}

$input = file_get_contents('php://input');
$payload = json_decode($input, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$lat = isset($payload['lat']) ? (float)$payload['lat'] : null;
$lng = isset($payload['lng']) ? (float)$payload['lng'] : null;

if ($lat === null || $lng === null) {
    json_response(['error' => 'Parâmetros obrigatórios: lat, lng'], 400);
}

if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    json_response(['error' => 'Coordenadas inválidas'], 400);
}

try {
    $stmt = $pdo->prepare("UPDATE usuarios SET ultima_localizacao = ST_GeomFromText(CONCAT('POINT(', ?, ' ', ?, ')')), ultimo_acesso_geo = NOW() WHERE id = ?");
    $stmt->execute([(string)$lng, (string)$lat, $_SESSION['usuario_id']]);

    json_response([
        'success' => true,
        'message' => 'Localização do usuário atualizada',
        'usuario_id' => $_SESSION['usuario_id'],
        'lat' => $lat,
        'lng' => $lng
    ]);
} catch (Exception $e) {
    json_response(['error' => 'Erro ao atualizar localização', 'details' => $e->getMessage()], 500);
}
