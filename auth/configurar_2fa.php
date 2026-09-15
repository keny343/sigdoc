<?php
// Habilitar exibição de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/lang.php';
require_once '../includes/theme_config.php';

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
    csrf_require();
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
<?php
$sigdoc_base = '../';
$page_title = 'Autenticação 2FA';
$sigdoc_active = '2fa';
require '../includes/layout_header.php';
?>

<div class="page-header">
  <div>
    <h2>Autenticação multifator</h2>
    <p class="page-sub">Códigos por email para acesso sensível</p>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">Configurar 2FA</div>
      <div class="card-body p-4">
                    <?php if ($mensagem): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($erro): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
                    <?php endif; ?>

                    <?php if (!$usuario['dois_fatores_ativado']): ?>
                        <div class="alert alert-info">
                            <strong>Porquê usar 2FA?</strong>
                            <p class="mb-0 mt-2">Adiciona uma verificação por email após o login e protege documentos classificados.</p>
                        </div>
                        
                        <div class="card mb-3">
                            <div class="card-header">Como funciona</div>
                            <div class="card-body">
                                <ol class="mb-0">
                                    <li>Login com email e palavra-passe</li>
                                    <li>Código de 6 dígitos enviado por email</li>
                                    <li>Introduz o código (válido 10 minutos)</li>
                                </ol>
                            </div>
                        </div>
                        
                        <form method="post">
                            <?= csrf_field() ?>
                            <button type="submit" name="ativar_2fa" class="btn btn-primary">Ativar 2FA por email</button>
                        </form>
                        
                    <?php else: ?>
                        <div class="alert alert-success">
                            <strong>2FA activo</strong>
                            <?php if (!empty($usuario['data_ativacao_2fa'])): ?>
                                <p class="mb-1 mt-2">Desde <?= date('d/m/Y H:i', strtotime($usuario['data_ativacao_2fa'])) ?></p>
                            <?php endif; ?>
                            <p class="mb-0"><strong>Email:</strong> <?= htmlspecialchars($usuario['email'] ?? 'N/A') ?></p>
                        </div>
                        
                        <div class="card mb-3 border-warning">
                            <div class="card-header">Desactivar 2FA</div>
                            <div class="card-body">
                                <p class="text-muted small">Remove a camada extra de segurança.</p>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <button type="submit" name="desativar_2fa" class="btn btn-outline-secondary">Desactivar 2FA</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require '../includes/layout_footer.php'; ?>
 