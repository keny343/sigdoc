<?php
require_once __DIR__ . '/../../includes/db.php';
// api/endpoints/documentos.php - Endpoint para Documentos

$method = $_SERVER['REQUEST_METHOD'];
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Função para obter dados do corpo da requisição
function get_request_data() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

// Função para validar dados do documento
function validate_document_data($data) {
    $errors = [];
    
    if (empty($data['titulo'])) {
        $errors[] = 'Título é obrigatório';
    }
    
    if (empty($data['tipo'])) {
        $errors[] = 'Tipo é obrigatório';
    }
    
    if (empty($data['setor'])) {
        $errors[] = 'Setor é obrigatório';
    }
    
    if (empty($data['categoria_acesso'])) {
        $errors[] = 'Categoria de acesso é obrigatória';
    }
    
    return $errors;
}

switch ($method) {
    case 'GET':
        // Listar documentos
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $search = $_GET['search'] ?? '';
        $estado = $_GET['estado'] ?? '';
        $categoria = $_GET['categoria'] ?? '';
        
        $where = [];
        $params = [];
        
        if (!empty($search)) {
            $where[] = "(d.titulo LIKE ? OR d.tipo LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if (!empty($estado)) {
            $where[] = "d.estado = ?";
            $params[] = $estado;
        }
        
        if (!empty($categoria)) {
            $where[] = "d.categoria_acesso = ?";
            $params[] = $categoria;
        }
        
        $sql = "SELECT d.*, u.nome as usuario_nome 
                FROM documentos d 
                JOIN usuarios u ON d.usuario_id = u.id";
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY d.data_upload DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Contar total para paginação
        $sql_count = "SELECT COUNT(*) FROM documentos d";
        if (!empty($where)) {
            $sql_count .= " WHERE " . implode(" AND ", $where);
        }
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute(array_slice($params, 0, -2));
        $total = $stmt_count->fetchColumn();
        
        json_response([
            'success' => true,
            'data' => $documentos,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ]
        ]);
        break;
        
    case 'POST':
        // Criar novo documento
        $data = get_request_data();
        $errors = validate_document_data($data);
        
        if (!empty($errors)) {
            json_response(['error' => 'Dados inválidos', 'details' => $errors], 400);
        }
        
        try {
            $geo_lat = $data['geo_lat'] ?? null;
            $geo_lng = $data['geo_lng'] ?? null;
            $lat_ok = false;
            $lng_ok = false;
            if ($geo_lat !== null && $geo_lng !== null && $geo_lat !== '' && $geo_lng !== '') {
                $lat = (float)$geo_lat;
                $lng = (float)$geo_lng;
                $lat_ok = ($lat >= -90 && $lat <= 90);
                $lng_ok = ($lng >= -180 && $lng <= 180);
            }

            if ($lat_ok && $lng_ok) {
                $stmt = $pdo->prepare("
                    INSERT INTO documentos (titulo, tipo, setor, categoria_acesso, area_origem, 
                                         area_destino, prioridade, estado, usuario_id, data_upload, localizacao)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ST_GeomFromText(CONCAT('POINT(', ?, ' ', ?, ')')))
                ");
                
                $stmt->execute([
                    $data['titulo'],
                    $data['tipo'],
                    $data['setor'],
                    $data['categoria_acesso'],
                    $data['area_origem'] ?? '',
                    $data['area_destino'] ?? '',
                    $data['prioridade'] ?? 'media',
                    $data['estado'] ?? 'pendente',
                    1,
                    (string)$lng,
                    (string)$lat
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO documentos (titulo, tipo, setor, categoria_acesso, area_origem, 
                                         area_destino, prioridade, estado, usuario_id, data_upload, localizacao)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NULL)
                ");
                
                $stmt->execute([
                    $data['titulo'],
                    $data['tipo'],
                    $data['setor'],
                    $data['categoria_acesso'],
                    $data['area_origem'] ?? '',
                    $data['area_destino'] ?? '',
                    $data['prioridade'] ?? 'media',
                    $data['estado'] ?? 'pendente',
                    1
                ]);
            }
            
            $documento_id = $pdo->lastInsertId();
            
            // Registrar movimentação
            $stmt_mov = $pdo->prepare("
                INSERT INTO movimentacao (documento_id, usuario_id, acao, observacoes, data_acao)
                VALUES (?, ?, 'criado', 'Documento criado via API', NOW())
            ");
            $stmt_mov->execute([$documento_id, 1]);
            
            json_response([
                'success' => true,
                'message' => 'Documento criado com sucesso',
                'documento_id' => $documento_id
            ], 201);
            
        } catch (Exception $e) {
            json_response(['error' => 'Erro ao criar documento', 'details' => $e->getMessage()], 500);
        }
        break;
        
    case 'PUT':
        // Atualizar documento
        $documento_id = $_GET['id'] ?? null;
        if (!$documento_id) {
            json_response(['error' => 'ID do documento é obrigatório'], 400);
        }
        
        $data = get_request_data();
        $errors = validate_document_data($data);
        
        if (!empty($errors)) {
            json_response(['error' => 'Dados inválidos', 'details' => $errors], 400);
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE documentos 
                SET titulo = ?, tipo = ?, setor = ?, categoria_acesso = ?, 
                    area_origem = ?, area_destino = ?, prioridade = ?, estado = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['titulo'],
                $data['tipo'],
                $data['setor'],
                $data['categoria_acesso'],
                $data['area_origem'] ?? '',
                $data['area_destino'] ?? '',
                $data['prioridade'] ?? 'media',
                $data['estado'] ?? 'pendente',
                $documento_id
            ]);
            
            // Registrar movimentação
            $stmt_mov = $pdo->prepare("
                INSERT INTO movimentacao (documento_id, usuario_id, acao, observacoes, data_acao)
                VALUES (?, ?, 'editado', 'Documento editado via API', NOW())
            ");
            $stmt_mov->execute([$documento_id, 1]);
            
            json_response([
                'success' => true,
                'message' => 'Documento atualizado com sucesso'
            ]);
            
        } catch (Exception $e) {
            json_response(['error' => 'Erro ao atualizar documento', 'details' => $e->getMessage()], 500);
        }
        break;
        
    case 'DELETE':
        // Excluir documento
        $documento_id = $_GET['id'] ?? null;
        if (!$documento_id) {
            json_response(['error' => 'ID do documento é obrigatório'], 400);
        }
        
        try {
            // Verificar se documento existe
            $stmt = $pdo->prepare("SELECT id FROM documentos WHERE id = ?");
            $stmt->execute([$documento_id]);
            if (!$stmt->fetch()) {
                json_response(['error' => 'Documento não encontrado'], 404);
            }
            
            // Excluir movimentações primeiro
            $stmt = $pdo->prepare("DELETE FROM movimentacao WHERE documento_id = ?");
            $stmt->execute([$documento_id]);
            
            // Excluir documento
            $stmt = $pdo->prepare("DELETE FROM documentos WHERE id = ?");
            $stmt->execute([$documento_id]);
            
            json_response([
                'success' => true,
                'message' => 'Documento excluído com sucesso'
            ]);
            
        } catch (Exception $e) {
            json_response(['error' => 'Erro ao excluir documento', 'details' => $e->getMessage()], 500);
        }
        break;
        
    default:
        json_response(['error' => 'Método não permitido'], 405);
}
?> 