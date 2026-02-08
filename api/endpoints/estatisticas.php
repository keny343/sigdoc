<?php
require_once __DIR__ . '/../../includes/db.php';
$method = $_SERVER['REQUEST_METHOD'];
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
// api/endpoints/estatisticas.php - Endpoint para Estatísticas

switch ($method) {
    case 'GET':
        try {
            // Estatísticas gerais
            $total_documentos = $pdo->query("SELECT COUNT(*) FROM documentos")->fetchColumn();
            $total_usuarios = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
            $total_movimentacoes = $pdo->query("SELECT COUNT(*) FROM movimentacao")->fetchColumn();
            
            // Documentos por estado
            $estados = ['pendente', 'em_analise', 'aprovado', 'arquivado'];
            $por_estado = [];
            foreach ($estados as $estado) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE estado = ?");
                $stmt->execute([$estado]);
                $por_estado[$estado] = $stmt->fetchColumn();
            }
            
            // Documentos por categoria
            $categorias = ['publico', 'privado', 'confidencial', 'secreto'];
            $por_categoria = [];
            foreach ($categorias as $categoria) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE categoria_acesso = ?");
                $stmt->execute([$categoria]);
                $por_categoria[$categoria] = $stmt->fetchColumn();
            }
            
            // Documentos por setor
            $setores = $pdo->query("SELECT setor, COUNT(*) as total FROM documentos GROUP BY setor ORDER BY total DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            
            // Documentos do mês atual
            $mes_atual = date('Y-m');
            $documentos_mes = $pdo->query("SELECT COUNT(*) FROM documentos WHERE DATE_FORMAT(data_upload, '%Y-%m') = '$mes_atual'")->fetchColumn();
            
            // Últimas movimentações
            $ultimas_movimentacoes = $pdo->query("
                SELECT m.*, d.titulo as documento_titulo, u.nome as usuario_nome 
                FROM movimentacao m 
                JOIN documentos d ON m.documento_id = d.id 
                JOIN usuarios u ON m.usuario_id = u.id 
                ORDER BY m.data_acao DESC 
                LIMIT 10
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            // Alertas (documentos com prazo próximo)
            $hoje = date('Y-m-d');
            $alertas = $pdo->query("
                SELECT id, titulo, prazo 
                FROM documentos 
                WHERE prazo IS NOT NULL AND prazo <> '' AND prazo <= DATE_ADD('$hoje', INTERVAL 3 DAY) 
                ORDER BY prazo ASC 
                LIMIT 10
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            json_response([
                'success' => true,
                'data' => [
                    'geral' => [
                        'total_documentos' => $total_documentos,
                        'total_usuarios' => $total_usuarios,
                        'total_movimentacoes' => $total_movimentacoes,
                        'documentos_mes_atual' => $documentos_mes
                    ],
                    'por_estado' => $por_estado,
                    'por_categoria' => $por_categoria,
                    'top_setores' => $setores,
                    'ultimas_movimentacoes' => $ultimas_movimentacoes,
                    'alertas' => $alertas
                ]
            ]);
            
        } catch (Exception $e) {
            json_response(['error' => 'Erro ao obter estatísticas', 'details' => $e->getMessage()], 500);
        }
        break;
        
    default:
        json_response(['error' => 'Método não permitido'], 405);
}
?> 