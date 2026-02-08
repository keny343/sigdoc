<?php
/**
 * API REST - SIGDoc
 * 
 * Endpoints disponíveis:
 * - GET /api/documentos - Listar documentos
 * - GET /api/documentos/{id} - Obter documento específico
 * - POST /api/documentos - Criar documento
 * - PUT /api/documentos/{id} - Atualizar documento
 * - DELETE /api/documentos/{id} - Excluir documento
 * - GET /api/usuarios - Listar usuários
 * - GET /api/estatisticas - Obter estatísticas
 * - POST /api/auth/login - Autenticação
 * - POST /api/auth/logout - Logout
 */

require_once 'includes/db.php';
require_once 'includes/auth.php';

class APIRest {
    private $pdo;
    private $requestMethod;
    private $endpoint;
    private $params;
    private $headers;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->requestMethod = $_SERVER['REQUEST_METHOD'];
        $this->endpoint = $this->getEndpoint();
        $this->params = $this->getParams();
        $this->headers = getallheaders();
        
        // Configurar headers CORS
        $this->setCORSHeaders();
    }
    
    /**
     * Processar requisição
     */
    public function processar() {
        try {
            // Verificar autenticação para endpoints protegidos
            if (!$this->isPublicEndpoint() && !$this->verificarAutenticacao()) {
                $this->responder(401, ['erro' => 'Não autorizado']);
                return;
            }

            // RBAC: checar permissões por rota/método
            if (!$this->isPublicEndpoint()) {
                $required = $this->getRequiredPermission();
                if ($required && !pode($required)) {
                    $this->responder(403, ['erro' => 'Sem permissão']);
                    return;
                }
            }

            // Novo roteamento para versões de documento
            $rawPath = $this->getRawPath();
            if (preg_match('#^documentos/(\\d+)/versoes$#', $rawPath, $matches)) {
                $this->listarVersoesDocumento($matches[1]);
                return;
            }

            // Novo roteamento para upload de nova versão
            if ($this->requestMethod === 'POST' && preg_match('#^documentos/(\\d+)/versoes$#', $rawPath, $matches)) {
                $this->uploadNovaVersaoDocumento($matches[1]);
                return;
            }

            // Novo roteamento para restaurar versão
            if ($this->requestMethod === 'POST' && preg_match('#^documentos/(\\d+)/versoes/(\\d+)/restaurar$#', $rawPath, $matches)) {
                $this->restaurarVersaoDocumento($matches[1], $matches[2]);
                return;
            }

            // Novo roteamento para tramitar documento
            if ($this->requestMethod === 'POST' && preg_match('#^documentos/(\\d+)/tramitar$#', $rawPath, $matches)) {
                $this->tramitarDocumento($matches[1]);
                return;
            }

            // Novo roteamento para exportação de documentos em lote
            if ($this->requestMethod === 'GET' && preg_match('#^documentos/exportar$#', $rawPath)) {
                $this->exportarDocumentos();
                return;
            }

            // Novo roteamento para exportação individual de documento
            if ($this->requestMethod === 'GET' && preg_match('#^documentos/(\\d+)/exportar$#', $rawPath, $matches)) {
                $this->exportarDocumentoIndividual($matches[1]);
                return;
            }

            // Novo roteamento para importação de documentos
            if ($this->requestMethod === 'POST' && preg_match('#^documentos/importar$#', $rawPath)) {
                $this->importarDocumentos();
                return;
            }

            // Novo roteamento para gerenciamento de usuários
            if ($this->endpoint === 'usuarios' && $this->requestMethod === 'POST') {
                $this->criarUsuario();
                return;
            }
            if ($this->endpoint === 'usuarios' && $this->requestMethod === 'PUT' && isset($this->params['id'])) {
                $this->atualizarUsuario($this->params['id']);
                return;
            }
            if ($this->endpoint === 'usuarios' && $this->requestMethod === 'DELETE' && isset($this->params['id'])) {
                $this->excluirUsuario($this->params['id']);
                return;
            }

            // Novo roteamento para notificações e movimentações
            if ($this->requestMethod === 'GET' && preg_match('#^notificacoes$#', $rawPath)) {
                $this->listarNotificacoes();
                return;
            }
            if ($this->requestMethod === 'POST' && preg_match('#^notificacoes/(\\d+)/confirmar$#', $rawPath, $matches)) {
                $this->confirmarNotificacao($matches[1]);
                return;
            }
            if ($this->requestMethod === 'GET' && preg_match('#^documentos/(\\d+)/movimentacoes$#', $rawPath, $matches)) {
                $this->listarMovimentacoesDocumento($matches[1]);
                return;
            }

            // Novo roteamento para auditoria/logs
            if ($this->requestMethod === 'GET' && preg_match('#^auditoria$#', $rawPath)) {
                $this->listarAuditoria();
                return;
            }

            // Novo roteamento para perfis e áreas
            if ($this->requestMethod === 'GET' && preg_match('#^perfis$#', $rawPath)) {
                $this->listarPerfis();
                return;
            }
            if ($this->requestMethod === 'GET' && preg_match('#^areas$#', $rawPath)) {
                $this->listarAreas();
                return;
            }

            // Roteamento
            switch ($this->endpoint) {
                case 'documentos':
                    $this->processarDocumentos();
                    break;
                case 'usuarios':
                    $this->processarUsuarios();
                    break;
                case 'estatisticas':
                    $this->processarEstatisticas();
                    break;
                case 'auth':
                    $this->processarAuth();
                    break;
                default:
                    $this->responder(404, ['erro' => 'Endpoint não encontrado']);
            }

        } catch (Exception $e) {
            $this->responder(500, ['erro' => $e->getMessage()]);
        }
    }
    
    /**
     * Obter endpoint da URL
     */
    private function getEndpoint() {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = str_replace('/api/', '', $path);
        $parts = explode('/', $path);
        return $parts[0] ?? '';
    }
    
    /**
     * Obter parâmetros da requisição
     */
    private function getParams() {
        $params = [];
        
        // Parâmetros da URL
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = str_replace('/api/', '', $path);
        $parts = explode('/', $path);
        
        if (isset($parts[1])) {
            $params['id'] = $parts[1];
        }
        
        // Parâmetros GET
        $params = array_merge($params, $_GET);
        
        // Parâmetros POST/PUT
        if (in_array($this->requestMethod, ['POST', 'PUT', 'PATCH'])) {
            $input = file_get_contents('php://input');
            $json = json_decode($input, true);
            if ($json) {
                $params = array_merge($params, $json);
            } else {
                $params = array_merge($params, $_POST);
            }
        }
        
        return $params;
    }

    // Adiciona método para obter o path completo da requisição
    private function getRawPath() {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = str_replace('/api/', '', $path);
        return $path;
    }
    
    /**
     * Configurar headers CORS
     */
    private function setCORSHeaders() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
    
    /**
     * Verificar se endpoint é público
     */
    private function isPublicEndpoint() {
        $publicEndpoints = ['auth'];
        return in_array($this->endpoint, $publicEndpoints);
    }
    
    /**
     * Resolver permissão necessária para a rota atual
     */
    private function getRequiredPermission() {
        $rawPath = $this->getRawPath();

        // Rotas específicas
        if ($this->requestMethod === 'GET' && preg_match('#^auditoria$#', $rawPath)) return 'auditoria.ver';
        if ($this->requestMethod === 'GET' && preg_match('#^perfis$#', $rawPath)) return 'perfis.ver';
        if ($this->requestMethod === 'GET' && preg_match('#^areas$#', $rawPath)) return 'areas.ver';

        if ($this->requestMethod === 'GET' && preg_match('#^notificacoes$#', $rawPath)) return 'notificacoes.ver';
        if ($this->requestMethod === 'POST' && preg_match('#^notificacoes/(\\d+)/confirmar$#', $rawPath)) return 'notificacoes.confirmar';

        if ($this->requestMethod === 'GET' && preg_match('#^documentos/(\\d+)/movimentacoes$#', $rawPath)) return 'documentos.ver';
        if ($this->requestMethod === 'POST' && preg_match('#^documentos/(\\d+)/tramitar$#', $rawPath)) return 'documentos.tramitar';

        if ($this->requestMethod === 'GET' && preg_match('#^documentos/exportar$#', $rawPath)) return 'documentos.exportar';
        if ($this->requestMethod === 'GET' && preg_match('#^documentos/(\\d+)/exportar$#', $rawPath)) return 'documentos.exportar';
        if ($this->requestMethod === 'POST' && preg_match('#^documentos/importar$#', $rawPath)) return 'documentos.importar';

        if ($this->requestMethod === 'GET' && preg_match('#^documentos/(\\d+)/versoes$#', $rawPath)) return 'documentos.versoes.ver';
        if ($this->requestMethod === 'POST' && preg_match('#^documentos/(\\d+)/versoes$#', $rawPath)) return 'documentos.versoes.criar';
        if ($this->requestMethod === 'POST' && preg_match('#^documentos/(\\d+)/versoes/(\\d+)/restaurar$#', $rawPath)) return 'documentos.versoes.restaurar';

        // Rotas por endpoint
        switch ($this->endpoint) {
            case 'documentos':
                switch ($this->requestMethod) {
                    case 'GET': return 'documentos.ver';
                    case 'POST': return 'documentos.criar';
                    case 'PUT': return 'documentos.editar';
                    case 'DELETE': return 'documentos.excluir';
                }
                break;
            case 'usuarios':
                switch ($this->requestMethod) {
                    case 'GET': return 'usuarios.ver';
                    case 'POST': return 'usuarios.criar';
                    case 'PUT': return 'usuarios.editar';
                    case 'DELETE': return 'usuarios.excluir';
                }
                break;
        }

        return null;
    }
    
    /**
     * Verificar autenticação
     */
    private function verificarAutenticacao() {
        // Autenticação por token na tabela usuariosapi
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        $token = null;
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        }

        $stmt = $this->pdo->prepare("SELECT * FROM usuariosapi WHERE api_token = ?");
        $stmt->execute([$token]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$usuario) {
            return false;
        }
        
        // Iniciar sessão
        session_start();
        $_SESSION['usuario'] = $usuario['nome'];
        $_SESSION['email'] = $usuario['email'];
        $_SESSION['perfil'] = $usuario['perfil'];
        $_SESSION['api_token'] = $token;
        
        return true;
    }
    
    /**
     * Processar endpoints de documentos
     */
    private function processarDocumentos() {
        $id = $this->params['id'] ?? null;
        
        switch ($this->requestMethod) {
            case 'GET':
                if ($id) {
                    $this->getDocumento($id);
                } else {
                    $this->listarDocumentos();
                }
                break;
            case 'POST':
                $this->criarDocumento();
                break;
            case 'PUT':
                $this->atualizarDocumento($id);
                break;
            case 'DELETE':
                $this->excluirDocumento($id);
                break;
            default:
                $this->responder(405, ['erro' => 'Método não permitido']);
        }
    }
    
    /**
     * Listar documentos
     */
    private function listarDocumentos() {
        $filtros = [];
        $where = "WHERE 1=1";
        $params = [];
        
        // Filtros
        if (isset($this->params['categoria'])) {
            $where .= " AND categoria = ?";
            $params[] = $this->params['categoria'];
        }
        
        if (isset($this->params['estado'])) {
            $where .= " AND estado = ?";
            $params[] = $this->params['estado'];
        }
        
        if (isset($this->params['email_origem'])) {
            $where .= " AND email_origem = ?";
            $params[] = $this->params['email_origem'];
        }
        
        if (isset($this->params['busca'])) {
            $where .= " AND (nome LIKE ? OR descricao LIKE ?)";
            $params[] = '%' . $this->params['busca'] . '%';
            $params[] = '%' . $this->params['busca'] . '%';
        }
        
        // Paginação
        $limit = isset($this->params['limit']) ? (int)$this->params['limit'] : 50;
        $offset = isset($this->params['offset']) ? (int)$this->params['offset'] : 0;
        
        $sql = "SELECT * FROM documentos {$where} ORDER BY data_upload DESC LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Contar total
        $sqlCount = "SELECT COUNT(*) FROM documentos {$where}";
        $stmtCount = $this->pdo->prepare($sqlCount);
        $stmtCount->execute($params);
        $total = $stmtCount->fetchColumn();
        
        $this->responder(200, [
            'documentos' => $documentos,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * Obter documento específico
     */
    private function getDocumento($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM documentos WHERE id = ?");
        $stmt->execute([$id]);
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$documento) {
            $this->responder(404, ['erro' => 'Documento não encontrado']);
            return;
        }
        
        // Obter histórico de movimentações
        $stmt = $this->pdo->prepare("SELECT * FROM movimentacao WHERE documento_id = ? ORDER BY data_acao DESC");
        $stmt->execute([$id]);
        $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $documento['movimentacoes'] = $movimentacoes;
        
        $this->responder(200, $documento);
    }
    
    /**
     * Criar documento
     */
    private function criarDocumento() {
        $dados = $this->params;
        
        // Validações
        if (empty($dados['nome']) || empty($dados['categoria'])) {
            $this->responder(400, ['erro' => 'Nome e categoria são obrigatórios']);
            return;
        }
        
        // Upload de arquivo
        $arquivo = null;
        if (isset($_FILES['arquivo'])) {
            $arquivo = $this->uploadArquivo($_FILES['arquivo']);
        }
        
        $geo_lat = $dados['geo_lat'] ?? null;
        $geo_lng = $dados['geo_lng'] ?? null;
        $lat_ok = false;
        $lng_ok = false;
        if ($geo_lat !== null && $geo_lng !== null && $geo_lat !== '' && $geo_lng !== '') {
            $lat = (float)$geo_lat;
            $lng = (float)$geo_lng;
            $lat_ok = ($lat >= -90 && $lat <= 90);
            $lng_ok = ($lng >= -180 && $lng <= 180);
        }

        if ($lat_ok && $lng_ok) {
            $sql = "INSERT INTO documentos (nome, descricao, categoria, tipo, email_origem, email_destino, prazo, arquivo, data_upload, localizacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ST_GeomFromText(CONCAT('POINT(', ?, ' ', ?, ')')))";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $dados['nome'],
                $dados['descricao'] ?? '',
                $dados['categoria'],
                $dados['tipo'] ?? '',
                $dados['email_origem'] ?? $_SESSION['email'] ?? '',
                $dados['email_destino'] ?? '',
                $dados['prazo'] ?? null,
                $arquivo,
                (string)$lng,
                (string)$lat
            ]);
        } else {
            $sql = "INSERT INTO documentos (nome, descricao, categoria, tipo, email_origem, email_destino, prazo, arquivo, data_upload, localizacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NULL)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $dados['nome'],
                $dados['descricao'] ?? '',
                $dados['categoria'],
                $dados['tipo'] ?? '',
                $dados['email_origem'] ?? $_SESSION['email'] ?? '',
                $dados['email_destino'] ?? '',
                $dados['prazo'] ?? null,
                $arquivo
            ]);
        }
        
        $id = $this->pdo->lastInsertId();
        
        // Registrar movimentação
        $this->registrarMovimentacao($id, 'criado', $_SESSION['usuario'] ?? 'API');
        
        $this->responder(201, [
            'id' => $id,
            'mensagem' => 'Documento criado com sucesso'
        ]);
    }
    
    /**
     * Atualizar documento
     */
    private function atualizarDocumento($id) {
        $dados = $this->params;
        
        // Verificar se documento existe
        $stmt = $this->pdo->prepare("SELECT id FROM documentos WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            $this->responder(404, ['erro' => 'Documento não encontrado']);
            return;
        }
        
        // Campos que podem ser atualizados
        $campos = ['nome', 'descricao', 'categoria', 'tipo', 'email_destino', 'prazo', 'estado'];
        $updates = [];
        $params = [];
        
        foreach ($campos as $campo) {
            if (isset($dados[$campo])) {
                $updates[] = "{$campo} = ?";
                $params[] = $dados[$campo];
            }
        }
        
        if (empty($updates)) {
            $this->responder(400, ['erro' => 'Nenhum campo para atualizar']);
            return;
        }
        
        $params[] = $id;
        $sql = "UPDATE documentos SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        // Registrar movimentação
        $this->registrarMovimentacao($id, 'editado', $_SESSION['usuario'] ?? 'API');
        
        $this->responder(200, [
            'mensagem' => 'Documento atualizado com sucesso'
        ]);
    }
    
    /**
     * Excluir documento
     */
    private function excluirDocumento($id) {
        // Verificar se documento existe
        $stmt = $this->pdo->prepare("SELECT id FROM documentos WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            $this->responder(404, ['erro' => 'Documento não encontrado']);
            return;
        }
        
        // Excluir movimentações
        $stmt = $this->pdo->prepare("DELETE FROM movimentacao WHERE documento_id = ?");
        $stmt->execute([$id]);
        
        // Excluir documento
        $stmt = $this->pdo->prepare("DELETE FROM documentos WHERE id = ?");
        $stmt->execute([$id]);
        
        $this->responder(200, [
            'mensagem' => 'Documento excluído com sucesso'
        ]);
    }
    
    /**
     * Processar endpoints de usuários
     */
    private function processarUsuarios() {
        switch ($this->requestMethod) {
            case 'GET':
                $this->listarUsuarios();
                break;
            default:
                $this->responder(405, ['erro' => 'Método não permitido']);
        }
    }
    
    /**
     * Listar usuários
     */
    private function listarUsuarios() {
        $sql = "SELECT id, nome, email, perfil FROM usuarios ORDER BY nome";
        $stmt = $this->pdo->query($sql);
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->responder(200, ['usuarios' => $usuarios]);
    }
    
    /**
     * Processar estatísticas
     */
    private function processarEstatisticas() {
        switch ($this->requestMethod) {
            case 'GET':
                $this->getEstatisticas();
                break;
            default:
                $this->responder(405, ['erro' => 'Método não permitido']);
        }
    }
    
    /**
     * Obter estatísticas
     */
    private function getEstatisticas() {
        // Total de documentos
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM documentos");
        $totalDocumentos = $stmt->fetchColumn();
        
        // Documentos por categoria
        $stmt = $this->pdo->query("SELECT categoria, COUNT(*) as total FROM documentos GROUP BY categoria");
        $documentosPorCategoria = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Documentos por estado
        $stmt = $this->pdo->query("SELECT estado, COUNT(*) as total FROM documentos GROUP BY estado");
        $documentosPorEstado = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Usuários ativos
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil != 'inativo'");
        $usuariosAtivos = $stmt->fetchColumn();
        
        // Movimentações hoje
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM movimentacao WHERE DATE(data_acao) = CURDATE()");
        $movimentacoesHoje = $stmt->fetchColumn();
        
        $this->responder(200, [
            'total_documentos' => $totalDocumentos,
            'documentos_por_categoria' => $documentosPorCategoria,
            'documentos_por_estado' => $documentosPorEstado,
            'usuarios_ativos' => $usuariosAtivos,
            'movimentacoes_hoje' => $movimentacoesHoje,
            'data_geracao' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Processar autenticação
     */
    private function processarAuth() {
        $acao = $this->params['acao'] ?? 'login';
        
        switch ($acao) {
            case 'login':
                $this->login();
                break;
            case 'logout':
                $this->logout();
                break;
            default:
                $this->responder(404, ['erro' => 'Ação não encontrada']);
        }
    }
    
    /**
     * Login via API
     */
    private function login() {
        $email = $this->params['email'] ?? '';
        $senha = $this->params['senha'] ?? '';
        
        if (empty($email) || empty($senha)) {
            $this->responder(400, ['erro' => 'Email e senha são obrigatórios']);
            return;
        }
        
        // Verificar credenciais
        $stmt = $this->pdo->prepare("SELECT id, nome, email, perfil, senha FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario || !password_verify($senha, $usuario['senha'])) {
            $this->responder(401, ['erro' => 'Credenciais inválidas']);
            return;
        }
        
        // Gerar token
        $token = bin2hex(random_bytes(32));
        
        // Iniciar sessão
        session_start();
        $_SESSION['usuario'] = $usuario['nome'];
        $_SESSION['email'] = $usuario['email'];
        $_SESSION['perfil'] = $usuario['perfil'];
        $_SESSION['api_token'] = $token;
        
        $this->responder(200, [
            'token' => $token,
            'usuario' => [
                'id' => $usuario['id'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
                'perfil' => $usuario['perfil']
            ],
            'mensagem' => 'Login realizado com sucesso'
        ]);
    }
    
    /**
     * Logout via API
     */
    private function logout() {
        session_start();
        session_destroy();
        
        $this->responder(200, [
            'mensagem' => 'Logout realizado com sucesso'
        ]);
    }
    
    /**
     * Upload de arquivo
     */
    private function uploadArquivo($arquivo) {
        $uploadDir = 'uploads/';
        $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
        $nomeArquivo = uniqid() . '_' . $arquivo['name'];
        $caminhoCompleto = $uploadDir . $nomeArquivo;
        
        if (move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
            return $nomeArquivo;
        }
        
        return null;
    }
    
    /**
     * Registrar movimentação
     */
    private function registrarMovimentacao($documentoId, $acao, $usuario) {
        $sql = "INSERT INTO movimentacao (documento_id, acao, usuario, data_acao) VALUES (?, ?, ?, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$documentoId, $acao, $usuario]);
    }

    // Novo método para listar versões de documento
    private function listarVersoesDocumento($documentoId) {
        $stmt = $this->pdo->prepare("SELECT dv.id, dv.documento_id, dv.numero_versao, dv.nome_arquivo, dv.caminho_arquivo, dv.observacoes, dv.usuario_id, u.nome AS usuario_nome, dv.data_upload FROM documento_versoes dv JOIN usuarios u ON dv.usuario_id = u.id WHERE dv.documento_id = ? ORDER BY dv.numero_versao DESC");
        $stmt->execute([$documentoId]);
        $versoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->responder(200, ['versoes' => $versoes]);
    }
    
    // Novo método para upload de nova versão de documento
    private function uploadNovaVersaoDocumento($documentoId) {
        // Verificar se documento existe
        $stmt = $this->pdo->prepare("SELECT * FROM documentos WHERE id = ?");
        $stmt->execute([$documentoId]);
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$documento) {
            $this->responder(404, ['erro' => 'Documento não encontrado']);
            return;
        }

        // Verificar se arquivo foi enviado
        if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            $this->responder(400, ['erro' => 'Arquivo não enviado ou inválido']);
            return;
        }

        $observacoes = $this->params['observacoes'] ?? '';

        // Buscar próxima versão
        $stmt = $this->pdo->prepare("SELECT MAX(numero_versao) as max_versao FROM documento_versoes WHERE documento_id = ?");
        $stmt->execute([$documentoId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nova_versao = ($result['max_versao'] ?? 0) + 1;

        // Upload do arquivo
        $arquivo = $_FILES['arquivo'];
        $nome_arquivo = uniqid() . '_v' . $nova_versao . '_' . basename($arquivo['name']);
        $destino = 'uploads/' . $nome_arquivo;

        if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
            $this->responder(500, ['erro' => 'Erro ao salvar arquivo']);
            return;
        }

        // Inserir nova versão
        $usuario_id = $_SESSION['usuario_id'] ?? null;
        $stmt = $this->pdo->prepare("INSERT INTO documento_versoes (documento_id, numero_versao, nome_arquivo, caminho_arquivo, observacoes, usuario_id, data_upload) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$documentoId, $nova_versao, $nome_arquivo, $nome_arquivo, $observacoes, $usuario_id]);

        // Atualizar versão atual do documento
        $stmt = $this->pdo->prepare("UPDATE documentos SET versao_atual = ?, caminho_arquivo = ? WHERE id = ?");
        $stmt->execute([$nova_versao, $nome_arquivo, $documentoId]);

        // Registrar movimentação
        $this->registrarMovimentacao($documentoId, 'nova_versao', $_SESSION['usuario'] ?? 'API');

        $this->responder(201, [
            'mensagem' => 'Nova versão criada com sucesso',
            'numero_versao' => $nova_versao,
            'arquivo' => $nome_arquivo
        ]);
    }
    
    // Novo método para restaurar versão de documento
    private function restaurarVersaoDocumento($documentoId, $numeroVersao) {
        // Verificar se documento existe
        $stmt = $this->pdo->prepare("SELECT * FROM documentos WHERE id = ?");
        $stmt->execute([$documentoId]);
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$documento) {
            $this->responder(404, ['erro' => 'Documento não encontrado']);
            return;
        }

        // Buscar versão
        $stmt = $this->pdo->prepare("SELECT * FROM documento_versoes WHERE documento_id = ? AND numero_versao = ?");
        $stmt->execute([$documentoId, $numeroVersao]);
        $versao = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$versao) {
            $this->responder(404, ['erro' => 'Versão não encontrada']);
            return;
        }

        // Atualizar documento para usar a versão restaurada
        $stmt = $this->pdo->prepare("UPDATE documentos SET caminho_arquivo = ?, versao_atual = ? WHERE id = ?");
        $stmt->execute([$versao['caminho_arquivo'], $versao['numero_versao'], $documentoId]);

        // Registrar movimentação
        $this->registrarMovimentacao($documentoId, 'restaurado', $_SESSION['usuario'] ?? 'API');

        $this->responder(200, [
            'mensagem' => 'Versão restaurada com sucesso',
            'numero_versao' => $versao['numero_versao'],
            'arquivo' => $versao['caminho_arquivo']
        ]);
    }
    
    // Novo método para tramitar documento
    private function tramitarDocumento($documentoId) {
        $dados = $this->params;
        $email_destino = $dados['email_destino'] ?? null;
        $observacoes = $dados['observacoes'] ?? '';

        if (!$email_destino) {
            $this->responder(400, ['erro' => 'Email de destino é obrigatório']);
            return;
        }

        // Verificar se documento existe
        $stmt = $this->pdo->prepare("SELECT * FROM documentos WHERE id = ?");
        $stmt->execute([$documentoId]);
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$documento) {
            $this->responder(404, ['erro' => 'Documento não encontrado']);
            return;
        }

        // Verificar se usuário de destino existe
        $stmt = $this->pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email_destino]);
        $usuario_destino = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$usuario_destino) {
            $this->responder(404, ['erro' => 'Usuário de destino não encontrado']);
            return;
        }

        // Atualizar área de destino do documento
        $stmt = $this->pdo->prepare("UPDATE documentos SET email_destino = ? WHERE id = ?");
        $stmt->execute([$email_destino, $documentoId]);

        // Registrar movimentação
        $this->registrarMovimentacao($documentoId, 'tramitado', $_SESSION['usuario'] ?? 'API');

        $this->responder(200, [
            'mensagem' => 'Documento tramitado com sucesso',
            'email_destino' => $email_destino
        ]);
    }
    
    // Novo método para exportação de documentos em lote
    private function exportarDocumentos() {
        $where = "WHERE 1=1";
        $params = [];

        // Filtros
        if (isset($this->params['categoria'])) {
            $where .= " AND categoria = ?";
            $params[] = $this->params['categoria'];
        }
        if (isset($this->params['estado'])) {
            $where .= " AND estado = ?";
            $params[] = $this->params['estado'];
        }
        if (isset($this->params['email_origem'])) {
            $where .= " AND email_origem = ?";
            $params[] = $this->params['email_origem'];
        }
        if (isset($this->params['busca'])) {
            $where .= " AND (nome LIKE ? OR descricao LIKE ?)";
            $params[] = '%' . $this->params['busca'] . '%';
            $params[] = '%' . $this->params['busca'] . '%';
        }

        $sql = "SELECT * FROM documentos {$where} ORDER BY data_upload DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->responder(200, [
            'documentos' => $documentos,
            'total' => count($documentos)
        ]);
    }
    
    // Novo método para exportação individual de documento
    private function exportarDocumentoIndividual($documentoId) {
        $stmt = $this->pdo->prepare("SELECT * FROM documentos WHERE id = ?");
        $stmt->execute([$documentoId]);
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$documento) {
            $this->responder(404, ['erro' => 'Documento não encontrado']);
            return;
        }
        $this->responder(200, $documento);
    }
    
    // Novo método para criar usuário
    private function criarUsuario() {
        $dados = $this->params;
        if (empty($dados['nome']) || empty($dados['email']) || empty($dados['senha']) || empty($dados['perfil'])) {
            $this->responder(400, ['erro' => 'Todos os campos são obrigatórios']);
            return;
        }
        // Verificar se email já existe
        $stmt = $this->pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$dados['email']]);
        if ($stmt->fetch()) {
            $this->responder(400, ['erro' => 'Email já cadastrado']);
            return;
        }
        $senhaHash = password_hash($dados['senha'], PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO usuarios (nome, email, senha, perfil, ultima_localizacao) VALUES (?, ?, ?, ?, ST_GeomFromText('POINT(0 0)'))");
        $stmt->execute([$dados['nome'], $dados['email'], $senhaHash, $dados['perfil']]);
        $id = $this->pdo->lastInsertId();
        $this->responder(201, [
            'id' => $id,
            'nome' => $dados['nome'],
            'email' => $dados['email'],
            'perfil' => $dados['perfil']
        ]);
    }

    // Novo método para atualizar usuário
    private function atualizarUsuario($id) {
        $dados = $this->params;
        $campos = [];
        $params = [];
        if (isset($dados['nome'])) {
            $campos[] = "nome = ?";
            $params[] = $dados['nome'];
        }
        if (isset($dados['email'])) {
            $campos[] = "email = ?";
            $params[] = $dados['email'];
        }
        if (isset($dados['senha'])) {
            $campos[] = "senha = ?";
            $params[] = password_hash($dados['senha'], PASSWORD_DEFAULT);
        }
        if (isset($dados['perfil'])) {
            $campos[] = "perfil = ?";
            $params[] = $dados['perfil'];
        }
        if (empty($campos)) {
            $this->responder(400, ['erro' => 'Nenhum campo para atualizar']);
            return;
        }
        $params[] = $id;
        $sql = "UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $this->responder(200, ['mensagem' => 'Usuário atualizado com sucesso']);
    }

    // Novo método para excluir usuário
    private function excluirUsuario($id) {
        $stmt = $this->pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        $this->responder(200, ['mensagem' => 'Usuário excluído com sucesso']);
    }
    
    // Novo método para listar notificações
    private function listarNotificacoes() {
        $usuario = $_SESSION['usuario'] ?? null;
        if (!$usuario) {
            $this->responder(401, ['erro' => 'Não autenticado']);
            return;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM notificacoes WHERE usuario = ? ORDER BY data_envio DESC");
        $stmt->execute([$usuario]);
        $notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->responder(200, ['notificacoes' => $notificacoes]);
    }

    // Novo método para confirmar leitura de notificação
    private function confirmarNotificacao($id) {
        $usuario = $_SESSION['usuario'] ?? null;
        if (!$usuario) {
            $this->responder(401, ['erro' => 'Não autenticado']);
            return;
        }
        $stmt = $this->pdo->prepare("UPDATE notificacoes SET lida = 1, data_leitura = NOW() WHERE id = ? AND usuario = ?");
        $stmt->execute([$id, $usuario]);
        $this->responder(200, ['mensagem' => 'Notificação confirmada']);
    }

    // Novo método para listar movimentações de documento
    private function listarMovimentacoesDocumento($documentoId) {
        $stmt = $this->pdo->prepare("SELECT * FROM movimentacao WHERE documento_id = ? ORDER BY data_acao DESC");
        $stmt->execute([$documentoId]);
        $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->responder(200, ['movimentacoes' => $movimentacoes]);
    }
    
    // Novo método para listar auditoria/logs
    private function listarAuditoria() {
        $usuario = $_SESSION['usuario'] ?? null;
        if (!$usuario) {
            $this->responder(401, ['erro' => 'Não autenticado']);
            return;
        }
        // Exemplo: últimos 50 logs do usuário
        $stmt = $this->pdo->prepare("SELECT * FROM movimentacao WHERE usuario = ? ORDER BY data_acao DESC LIMIT 50");
        $stmt->execute([$usuario]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->responder(200, ['auditoria' => $logs]);
    }
    
    // Novo método para listar perfis de usuário
    private function listarPerfis() {
        // Perfis fixos do sistema
        $perfis = [
            'administrador',
            'gestor',
            'colaborador',
            'visitante'
        ];
        $this->responder(200, ['perfis' => $perfis]);
    }

    // Novo método para listar áreas/setores
    private function listarAreas() {
        $stmt = $this->pdo->query("SELECT DISTINCT email_origem AS area FROM documentos UNION SELECT DISTINCT email_destino AS area FROM documentos");
        $areas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->responder(200, ['areas' => $areas]);
    }
    
    // Novo método para importação de documentos em lote
    private function importarDocumentos() {
        $dados = $this->params['documentos'] ?? null;
        if (!$dados || !is_array($dados)) {
            $this->responder(400, ['erro' => 'Dados de documentos ausentes ou inválidos']);
            return;
        }
        $importados = 0;
        foreach ($dados as $doc) {
            if (empty($doc['nome']) || empty($doc['categoria'])) {
                continue;
            }

            $geo_lat = $doc['geo_lat'] ?? null;
            $geo_lng = $doc['geo_lng'] ?? null;
            $lat_ok = false;
            $lng_ok = false;
            if ($geo_lat !== null && $geo_lng !== null && $geo_lat !== '' && $geo_lng !== '') {
                $lat = (float)$geo_lat;
                $lng = (float)$geo_lng;
                $lat_ok = ($lat >= -90 && $lat <= 90);
                $lng_ok = ($lng >= -180 && $lng <= 180);
            }

            if ($lat_ok && $lng_ok) {
                $stmt = $this->pdo->prepare("INSERT INTO documentos (nome, descricao, categoria, tipo, email_origem, email_destino, prazo, data_upload, localizacao) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ST_GeomFromText(CONCAT('POINT(', ?, ' ', ?, ')')))");
                $stmt->execute([
                    $doc['nome'],
                    $doc['descricao'] ?? '',
                    $doc['categoria'],
                    $doc['tipo'] ?? '',
                    $doc['email_origem'] ?? '',
                    $doc['email_destino'] ?? '',
                    $doc['prazo'] ?? null,
                    (string)$lng,
                    (string)$lat
                ]);
            } else {
                $stmt = $this->pdo->prepare("INSERT INTO documentos (nome, descricao, categoria, tipo, email_origem, email_destino, prazo, data_upload, localizacao) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NULL)");
                $stmt->execute([
                    $doc['nome'],
                    $doc['descricao'] ?? '',
                    $doc['categoria'],
                    $doc['tipo'] ?? '',
                    $doc['email_origem'] ?? '',
                    $doc['email_destino'] ?? '',
                    $doc['prazo'] ?? null
                ]);
            }
            $importados++;
        }
        $this->responder(201, [
            'mensagem' => 'Importação concluída',
            'importados' => $importados
        ]);
    }
    
    /**
     * Responder requisição
     */
    private function responder($statusCode, $dados) {
        http_response_code($statusCode);
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// Executar API
$api = new APIRest();
$api->processar();
?> 