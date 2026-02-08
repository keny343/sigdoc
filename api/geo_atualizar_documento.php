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

$documento_id = isset($payload['documento_id']) ? (int)$payload['documento_id'] : 0;
$lat = isset($payload['lat']) ? (float)$payload['lat'] : null;
$lng = isset($payload['lng']) ? (float)$payload['lng'] : null;

if ($documento_id <= 0) {
    json_response(['error' => 'Parâmetro obrigatório: documento_id'], 400);
}

if ($lat === null || $lng === null) {
    json_response(['error' => 'Parâmetros obrigatórios: lat, lng'], 400);
}

if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    json_response(['error' => 'Coordenadas inválidas'], 400);
}

try {
    // Verificar permissão (admin/gestor ou dono do documento)
    $stmt = $pdo->prepare('SELECT id, usuario_id FROM documentos WHERE id = ?');
    $stmt->execute([$documento_id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        json_response(['error' => 'Documento não encontrado'], 404);
    }

    $usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
    $perfil = $_SESSION['perfil'] ?? '';

    $pode = false;
    if (in_array($perfil, ['admin', 'administrador', 'gestor'], true)) {
        $pode = true;
    } elseif ($usuario_id > 0 && (int)$doc['usuario_id'] === $usuario_id) {
        $pode = true;
    }

    if (!$pode) {
        json_response(['error' => 'Sem permissão para alterar este documento'], 403);
    }

    $stmtUp = $pdo->prepare("UPDATE documentos SET localizacao = ST_GeomFromText(CONCAT('POINT(', ?, ' ', ?, ')')) WHERE id = ?");
    $stmtUp->execute([(string)$lng, (string)$lat, $documento_id]);

    json_response([
        'success' => true,
        'message' => 'Localização do documento atualizada',
        'documento_id' => $documento_id,
        'lat' => $lat,
        'lng' => $lng
    ]);
} catch (Exception $e) {
    json_response(['error' => 'Erro ao atualizar localização do documento', 'details' => $e->getMessage()], 500);
}
