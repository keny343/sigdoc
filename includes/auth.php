<?php
// Carregar configurações de segurança SSL/HTTPS
require_once __DIR__ . '/../config_ssl.php';

session_start();

// Importar PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Carrega o autoloader do Composer (PHPMailer)
require_once __DIR__ . '/../vendor/autoload.php';

// Carregar configurações de email
require_once __DIR__ . '/../config_email.php';
require_once __DIR__ . '/config.php';

$host = sigdoc_config('db.host');
$dbname = sigdoc_config('db.name');
$username = sigdoc_config('db.user');
$password = sigdoc_config('db.pass');
$port = (int) sigdoc_config('db.port', 3306);
$charset = sigdoc_config('db.charset', 'utf8mb4');

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erro na conexão com a base de dados.');
}

// Função para verificar se o usuário está logado
function is_logged_in()
{
    return isset($_SESSION['usuario_id']);
}

// Função para verificar se é administrador
function is_admin()
{
    return isset($_SESSION['perfil']) && in_array($_SESSION['perfil'], ['admin', 'administrador']);
}

// Função para verificar se é gestor
function is_gestor()
{
    return isset($_SESSION['perfil']) && in_array($_SESSION['perfil'], ['gestor']);
}

// Função para verificar se é colaborador
function is_colaborador()
{
    return isset($_SESSION['perfil']) && in_array($_SESSION['perfil'], ['colaborador']);
}

// Função para verificar se é visitante
function is_visitante()
{
    return isset($_SESSION['perfil']) && in_array($_SESSION['perfil'], ['visitante']);
}

// Função de login
function login($email, $senha)
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($senha, $usuario['senha'])) {
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['perfil'] = $usuario['perfil'];

        // Limpar cache de permissões ao logar
        unset($_SESSION['permissoes_cache']);

        // Verificar se o usuário tem 2FA ativado
        if ($usuario['dois_fatores_ativado']) {
            $_SESSION['aguardando_2fa'] = true;
            return true;
        }

        return true;
    }

    return false;
}

// Função de logout
function logout()
{
    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}

// Função para gerar código 2FA por email
function gerar_codigo_2fa_email()
{
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Função para enviar código 2FA por email
function enviar_codigo_2fa_email($email_destino, $nome_destino, $codigo)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_USER, 'SIGDoc');
        $mail->addAddress($email_destino, $nome_destino);

        $mail->isHTML(true);
        $mail->Subject = 'Código de Verificação 2FA - SIGDoc';
        $mail->Body = "
        <html>
        <body>
            <h2>🔐 Código de Verificação 2FA</h2>
            <p>Olá <strong>$nome_destino</strong>,</p>
            <p>Seu código de verificação para acessar o SIGDoc é:</p>
            <div style='background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 24px; font-weight: bold; color: #007bff; border-radius: 5px; margin: 20px 0;'>
                <strong>$codigo</strong>
            </div>
            <p><strong>Este código expira em 10 minutos.</strong></p>
            <p>Se você não solicitou este código, ignore este e-mail.</p>
            <hr>
            <p><small>Sistema de Gestão Documental - SIGDoc</small></p>
        </body>
        </html>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Erro ao enviar e-mail 2FA: ' . $mail->ErrorInfo);
        return false;
    }
}

function verificar_codigo_2fa_email($codigo_digitado, $codigo_armazenado, $data_envio)
{
    $tempo_expiracao = 600;
    $tempo_atual = time();
    $tempo_envio = strtotime($data_envio);

    if (($tempo_atual - $tempo_envio) > $tempo_expiracao) {
        return false;
    }

    return $codigo_digitado === $codigo_armazenado;
}

function documento_requer_2fa($categoria_acesso)
{
    return in_array($categoria_acesso, ['confidencial', 'secreto']);
}

function pode_acessar_documento_sigiloso($categoria_acesso)
{
    if (!is_logged_in()) {
        return false;
    }

    if (!documento_requer_2fa($categoria_acesso)) {
        return true;
    }

    if (!isset($_SESSION['2fa_verificado']) || !$_SESSION['2fa_verificado']) {
        return false;
    }

    switch ($categoria_acesso) {
        case 'confidencial':
            return is_gestor() || is_admin();
        case 'secreto':
            return is_admin();
        default:
            return true;
    }
}

function registrar_tentativa_acesso_sigiloso($usuario_id, $documento_id, $sucesso)
{
    global $pdo;

    $stmt = $pdo->prepare("INSERT INTO tentativas_acesso_sigiloso (usuario_id, documento_id, sucesso, data_tentativa, ip) VALUES (?, ?, ?, NOW(), ?)");
    $stmt->execute([$usuario_id, $documento_id, $sucesso ? 1 : 0, $_SERVER['REMOTE_ADDR'] ?? '']);
}

function precisa_completar_2fa()
{
    return isset($_SESSION['aguardando_2fa']) && $_SESSION['aguardando_2fa'];
}

function marcar_2fa_verificado()
{
    $_SESSION['aguardando_2fa'] = false;
    $_SESSION['2fa_verificado'] = true;
}

function gerar_e_enviar_codigo_2fa($usuario_id)
{
    global $pdo;

    $codigo = gerar_codigo_2fa_email();
    $data_envio = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("UPDATE usuarios SET codigo_2fa = ?, data_codigo_2fa = ? WHERE id = ?");
    $stmt->execute([$codigo, $data_envio, $usuario_id]);

    $stmt = $pdo->prepare("SELECT email, nome FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    return enviar_codigo_2fa_email($usuario['email'], $usuario['nome'], $codigo);
}

function login_api($email, $senha)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM usuariosapi WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($usuario && password_verify($senha, $usuario['senha'])) {
        $_SESSION['usuarioapi_id'] = $usuario['id'];
        $_SESSION['usuarioapi_email'] = $usuario['email'];
        $_SESSION['usuarioapi_nome'] = $usuario['nome'];
        return true;
    }
    return false;
}
function is_logged_in_api()
{
    return isset($_SESSION['usuarioapi_id']);
}
function get_usuarioapi_id()
{
    return $_SESSION['usuarioapi_id'] ?? null;
}

// --- SISTEMA RBAC (CONTROLE DE ACESSO BASEADO EM GRUPOS) ---

function permissoes_do_usuario()
{
    global $pdo;

    if (!is_logged_in()) {
        return [];
    }

    // Cache de sessão para evitar query a cada chamada
    if (isset($_SESSION['permissoes_cache']) && is_array($_SESSION['permissoes_cache'])) {
        return $_SESSION['permissoes_cache'];
    }

    $usuario_id = $_SESSION['usuario_id'];

    try {
        // Query otimizada para buscar todas as permissões de todos os grupos do usuário
        $sql = "SELECT DISTINCT p.chave
                FROM usuario_grupos ug
                JOIN grupo_permissoes gp ON gp.grupo_id = ug.grupo_id
                JOIN permissoes p ON p.id = gp.permissao_id
                WHERE ug.usuario_id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        $perms = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $_SESSION['permissoes_cache'] = $perms ?: [];
        return $_SESSION['permissoes_cache'];
    } catch (Exception $e) {
        // Em caso de erro (ex: tabelas ainda não existem), segura a sessão sem permissões
        $_SESSION['permissoes_cache'] = [];
        return [];
    }
}

function pode($permissao_chave)
{
    // 1. Superusuários (Admin) têm acesso irrestrito
    $perfil = $_SESSION['perfil'] ?? '';
    // Mapeamento legado para garantir que admins continuem admins
    if ($perfil === 'admin' || $perfil === 'administrador') {
        return true;
    }

    // 2. Verificar permissões granulares dos grupos
    $perms = permissoes_do_usuario();
    return in_array($permissao_chave, $perms, true);
}

function exigir_permissao($permissao_chave)
{
    if (!is_logged_in()) {
        header('Location: ../auth/login.php');
        exit;
    }
    if (!pode($permissao_chave)) {
        http_response_code(403);
        echo '<div style="font-family:sans-serif; text-align:center; padding:50px; background:#f8d7da; color:#721c24;">
                <h1>⛔ Acesso Negado</h1>
                <p>Você não tem permissão para realizar esta ação.</p>
                <p><strong>Permissão necessária:</strong> ' . htmlspecialchars($permissao_chave) . '</p>
                <p style="margin-top:20px;"><a href="javascript:history.back()" style="color:#721c24; text-decoration:underline;">Voltar</a></p>
              </div>';
        exit;
    }
}
?>