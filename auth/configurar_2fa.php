<?php
// Habilitar exibição de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$mensagem = '';
$erro = '';

// Função para verificar se coluna existe
function colunaExiste($pdo, $tabela, $coluna) {
    try {
        // Usar backticks para escapar nomes de tabela e coluna
        $sql = "SHOW COLUMNS FROM `" . str_replace('`', '``', $tabela) . "` LIKE ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$coluna]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro ao verificar coluna $coluna: " . $e->getMessage());
        return false;
    }
}

// Garantir que as colunas 2FA existam
try {
    $colunas_2fa = [
        'dois_fatores_ativado' => "ALTER TABLE usuarios ADD COLUMN dois_fatores_ativado BOOLEAN DEFAULT FALSE",
        'codigo_2fa' => "ALTER TABLE usuarios ADD COLUMN codigo_2fa VARCHAR(6) DEFAULT NULL",
        'data_codigo_2fa' => "ALTER TABLE usuarios ADD COLUMN data_codigo_2fa TIMESTAMP NULL",
        'data_ativacao_2fa' => "ALTER TABLE usuarios ADD COLUMN data_ativacao_2fa TIMESTAMP NULL"
    ];
    
    foreach ($colunas_2fa as $coluna => $sql) {
        if (!colunaExiste($pdo, 'usuarios', $coluna)) {
            try {
                $pdo->exec($sql);
                error_log("Coluna $coluna adicionada com sucesso");
            } catch (PDOException $e) {
                // Ignorar erro se coluna já existe (pode acontecer em concorrência)
                $errorMsg = $e->getMessage();
                if (strpos($errorMsg, 'Duplicate column name') === false && 
                    strpos($errorMsg, 'already exists') === false) {
                    error_log("Erro ao adicionar coluna $coluna: " . $errorMsg);
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Erro ao verificar/criar colunas 2FA: " . $e->getMessage());
}

// Buscar dados do usuário
try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        $erro = "Usuário não encontrado.";
    }
} catch (PDOException $e) {
    $erro = "Erro ao buscar dados do usuário: " . $e->getMessage();
    error_log("Erro na query: " . $e->getMessage());
    $usuario = ['dois_fatores_ativado' => false];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ativar_2fa'])) {
        try {
            // Verificar se a coluna data_ativacao_2fa existe
            if (colunaExiste($pdo, 'usuarios', 'data_ativacao_2fa')) {
                $stmt = $pdo->prepare("UPDATE usuarios SET dois_fatores_ativado = TRUE, data_ativacao_2fa = NOW() WHERE id = ?");
            } else {
                // Se não existir, atualizar apenas dois_fatores_ativado
                $stmt = $pdo->prepare("UPDATE usuarios SET dois_fatores_ativado = TRUE WHERE id = ?");
            }
            $stmt->execute([$usuario_id]);
            
            $mensagem = "2FA ativado com sucesso! Agora você receberá códigos por email ao fazer login.";
            
            // Recarregar dados do usuário
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $erro = "Erro ao ativar 2FA: " . $e->getMessage();
            error_log("Erro ao ativar 2FA: " . $e->getMessage());
        }
        
    } elseif (isset($_POST['desativar_2fa'])) {
        try {
            // Verificar quais colunas existem antes de atualizar
            $campos = ['dois_fatores_ativado = FALSE'];
            
            if (colunaExiste($pdo, 'usuarios', 'codigo_2fa')) {
                $campos[] = 'codigo_2fa = NULL';
            }
            if (colunaExiste($pdo, 'usuarios', 'data_codigo_2fa')) {
                $campos[] = 'data_codigo_2fa = NULL';
            }
            if (colunaExiste($pdo, 'usuarios', 'data_ativacao_2fa')) {
                $campos[] = 'data_ativacao_2fa = NULL';
            }
            
            $sql = "UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuario_id]);
            
            $mensagem = "2FA desativado com sucesso!";
            
            // Recarregar dados do usuário
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $erro = "Erro ao desativar 2FA: " . $e->getMessage();
            error_log("Erro ao desativar 2FA: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configurar 2FA - SIGDoc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="../includes/style.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/icons/file-earmark-text.svg">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="../documentos/listar.php">SIGDoc</a>
    <div class="d-flex">
      <a href="../documentos/listar.php" class="btn btn-outline-light me-2">Documentos</a>
      <a href="logout.php" class="btn btn-danger">Sair</a>
    </div>
  </div>
</nav>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Configurar Autenticação Multifator (2FA)</h3>
                </div>
                <div class="card-body">
                    <?php if ($mensagem): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($erro): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
                    <?php endif; ?>

                    <?php if (!$usuario['dois_fatores_ativado']): ?>
                        <!-- Configuração inicial do 2FA -->
                        <div class="alert alert-info">
                            <h5>🔐 Por que usar 2FA?</h5>
                            <p>A autenticação multifator adiciona uma camada extra de segurança ao seu acesso. 
                            Você receberá um código por email sempre que fizer login, garantindo que apenas você 
                            tenha acesso à sua conta.</p>
                        </div>
                        
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6>📧 Como Funciona o 2FA por Email</h6>
                            </div>
                            <div class="card-body">
                                <ol>
                                    <li>Você faz login normalmente com email e senha</li>
                                    <li>O sistema gera um código de 6 dígitos</li>
                                    <li>O código é enviado para seu email cadastrado</li>
                                    <li>Você digita o código recebido</li>
                                    <li>O sistema verifica e permite acesso</li>
                                    <li>O código expira em 10 minutos por segurança</li>
                                </ol>
                            </div>
                        </div>
                        
                        <form method="post">
                            <button type="submit" name="ativar_2fa" class="btn btn-primary">
                                <i class="bi bi-shield-lock"></i> Ativar 2FA por Email
                            </button>
                        </form>
                        
                    <?php else: ?>
                        <!-- 2FA já está ativado -->
                        <div class="alert alert-success">
                            <h5>✅ 2FA Ativado</h5>
                            <?php if (!empty($usuario['data_ativacao_2fa'])): ?>
                                <p>Sua autenticação multifator está ativa desde <?= date('d/m/Y H:i', strtotime($usuario['data_ativacao_2fa'])) ?></p>
                            <?php else: ?>
                                <p>Sua autenticação multifator está ativa.</p>
                            <?php endif; ?>
                            <p><strong>Email cadastrado:</strong> <?= htmlspecialchars($usuario['email'] ?? 'N/A') ?></p>
                        </div>
                        
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6>📋 Informações Importantes</h6>
                            </div>
                            <div class="card-body">
                                <ul>
                                    <li><strong>Códigos são enviados automaticamente</strong> quando você faz login</li>
                                    <li><strong>Códigos expiram em 10 minutos</strong> por segurança</li>
                                    <li><strong>Verifique sua caixa de spam</strong> se não receber o email</li>
                                    <li><strong>Documentos sigilosos</strong> requerem verificação 2FA</li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Desativar 2FA -->
                        <div class="card border-warning">
                            <div class="card-header bg-warning text-dark">
                                <h6>⚠️ Desativar 2FA</h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Atenção: Desativar o 2FA remove a camada extra de segurança da sua conta.</p>
                                <form method="post">
                                    <button type="submit" name="desativar_2fa" class="btn btn-warning">
                                        <i class="bi bi-x-circle"></i> Desativar 2FA
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html> 