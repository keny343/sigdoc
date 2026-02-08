<?php
// Habilitar exibição de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 0); // Desabilitar em produção, mas logar erros
ini_set('log_errors', 1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Função para verificar se coluna existe
function colunaExiste($pdo, $tabela, $coluna) {
    try {
        $sql = "SHOW COLUMNS FROM `" . str_replace('`', '``', $tabela) . "` LIKE ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$coluna]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro ao verificar coluna $coluna: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Método não suportado'], 405);
}

if (!is_logged_in()) {
    json_response(['error' => 'Não autenticado'], 401);
}

// Verificar se a coluna localizacao existe
if (!colunaExiste($pdo, 'documentos', 'localizacao')) {
    json_response([
        'success' => true,
        'origin' => [
            'lat' => isset($_GET['lat']) ? (float)$_GET['lat'] : null,
            'lng' => isset($_GET['lng']) ? (float)$_GET['lng'] : null,
            'raio_km' => isset($_GET['raio_km']) ? (float)$_GET['raio_km'] : 5.0
        ],
        'total' => 0,
        'data' => [],
        'message' => 'Funcionalidade geoespacial não disponível. A coluna localizacao não existe na tabela documentos.'
    ]);
}

$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;
$raio_km = isset($_GET['raio_km']) ? (float)$_GET['raio_km'] : 5.0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;

if ($lat === null || $lng === null) {
    json_response(['error' => 'Parâmetros obrigatórios: lat, lng'], 400);
}

if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    json_response(['error' => 'Coordenadas inválidas'], 400);
}

if ($raio_km <= 0 || $raio_km > 200) {
    json_response(['error' => 'raio_km inválido (use entre 0 e 200)'], 400);
}

if ($limit <= 0 || $limit > 1000) {
    $limit = 200;
}

try {
    // Verificar se há documentos com localização
    $checkSql = "SELECT COUNT(*) FROM documentos WHERE localizacao IS NOT NULL";
    $checkStmt = $pdo->query($checkSql);
    $totalComLocalizacao = $checkStmt->fetchColumn();
    
    if ($totalComLocalizacao == 0) {
        json_response([
            'success' => true,
            'origin' => [
                'lat' => $lat,
                'lng' => $lng,
                'raio_km' => $raio_km
            ],
            'total' => 0,
            'data' => [],
            'message' => 'Nenhum documento com localização encontrado.'
        ]);
    }
    
    // Tentativa 1: ST_Distance_Sphere (mais rápido quando disponível)
    try {
        $sql = "
            SELECT
                d.id,
                d.titulo,
                d.descricao,
                d.endereco,
                ST_Y(d.localizacao) AS lat,
                ST_X(d.localizacao) AS lng,
                (ST_Distance_Sphere(POINT(:lng, :lat), d.localizacao) / 1000) AS distancia_km
            FROM documentos d
            WHERE
                d.localizacao IS NOT NULL
                AND NOT (ST_Y(d.localizacao) = 0 AND ST_X(d.localizacao) = 0)
                AND ST_Distance_Sphere(POINT(:lng, :lat), d.localizacao) <= (:raio_m)
            ORDER BY distancia_km ASC
            LIMIT {$limit}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':lat' => $lat,
            ':lng' => $lng,
            ':raio_m' => (int)round($raio_km * 1000)
        ]);

        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_response([
            'success' => true,
            'origin' => [
                'lat' => $lat,
                'lng' => $lng,
                'raio_km' => $raio_km
            ],
            'total' => count($docs),
            'data' => $docs
        ]);
    } catch (PDOException $e) {
        error_log("Erro ST_Distance_Sphere: " . $e->getMessage());
        
        // Fallback: Haversine em SQL usando ST_X/ST_Y
        try {
            $sql = "
                SELECT
                    d.id,
                    d.titulo,
                    d.descricao,
                    d.endereco,
                    ST_Y(d.localizacao) AS lat,
                    ST_X(d.localizacao) AS lng,
                    (
                        6371 * 2 * ASIN(
                            SQRT(
                                POWER(SIN(RADIANS(:lat - ST_Y(d.localizacao)) / 2), 2)
                                + COS(RADIANS(:lat)) * COS(RADIANS(ST_Y(d.localizacao)))
                                * POWER(SIN(RADIANS(:lng - ST_X(d.localizacao)) / 2), 2)
                            )
                        )
                    ) AS distancia_km
                FROM documentos d
                WHERE
                    d.localizacao IS NOT NULL
                    AND NOT (ST_Y(d.localizacao) = 0 AND ST_X(d.localizacao) = 0)
                HAVING distancia_km <= :raio_km
                ORDER BY distancia_km ASC
                LIMIT {$limit}
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':lat' => $lat,
                ':lng' => $lng,
                ':raio_km' => $raio_km
            ]);

            $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_response([
                'success' => true,
                'origin' => [
                    'lat' => $lat,
                    'lng' => $lng,
                    'raio_km' => $raio_km
                ],
                'total' => count($docs),
                'data' => $docs,
                'fallback' => 'haversine'
            ]);
        } catch (PDOException $e2) {
            error_log("Erro Haversine: " . $e2->getMessage());
            
            // Último fallback: retornar vazio com mensagem
            json_response([
                'success' => true,
                'origin' => [
                    'lat' => $lat,
                    'lng' => $lng,
                    'raio_km' => $raio_km
                ],
                'total' => 0,
                'data' => [],
                'message' => 'Erro ao processar consulta geoespacial. Verifique se as funções ST_X, ST_Y estão disponíveis.',
                'error_details' => $e2->getMessage()
            ]);
        }
    }
} catch (Exception $e) {
    error_log("Erro geral em geo_buscar_documentos_proximos: " . $e->getMessage());
    json_response([
        'error' => 'Erro ao buscar documentos próximos',
        'details' => $e->getMessage()
    ], 500);
}
