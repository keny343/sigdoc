<?php
/**
 * Auth helpers + single shared PDO (Aiven TLS via pdo_factory).
 * PHPMailer is loaded lazily only when sending 2FA email.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config_ssl.php';
require_once __DIR__ . '/pdo_factory.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

try {
    $pdo = sigdoc_pdo();
} catch (PDOException $e) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    $host = function_exists('sigdoc_config') ? (string) sigdoc_config('db.host', '?') : '?';
    $name = function_exists('sigdoc_config') ? (string) sigdoc_config('db.name', '?') : '?';
    die("Erro na conexão com a base de dados.\nHost: {$host}\nDatabase: {$name}\n");
}

function is_logged_in(): bool
{
    return isset($_SESSION['usuario_id']);
}

function is_admin(): bool
{
    return isset($_SESSION['perfil']) && in_array($_SESSION['perfil'], ['admin', 'administrador'], true);
}

function is_gestor(): bool
{
    return isset($_SESSION['perfil']) && $_SESSION['perfil'] === 'gestor';
}

function is_colaborador(): bool
{
    return isset($_SESSION['perfil']) && $_SESSION['perfil'] === 'colaborador';
}

function is_visitante(): bool
{
    return isset($_SESSION['perfil']) && $_SESSION['perfil'] === 'visitante';
}

function login(string $email, string $senha): bool
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($senha, $usuario['senha'])) {
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['perfil'] = $usuario['perfil'];
        unset($_SESSION['permissoes_cache']);

        if (!empty($usuario['dois_fatores_ativado'])) {
            $_SESSION['aguardando_2fa'] = true;
        }

        return true;
    }

    return false;
}

function logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();
}

function gerar_codigo_2fa_email(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function enviar_codigo_2fa_email(string $email_destino, string $nome_destino, string $codigo): bool
{
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../config_email.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

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
        $mail->Body = '
        <html><body>
            <h2>Código de Verificação 2FA</h2>
            <p>Olá <strong>' . htmlspecialchars($nome_destino, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
            <p>Seu código de verificação para acessar o SIGDoc é:</p>
            <div style="background:#f8f9fa;padding:20px;text-align:center;font-size:24px;font-weight:bold;color:#007bff;border-radius:5px;margin:20px 0;">
                <strong>' . htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') . '</strong>
            </div>
            <p><strong>Este código expira em 10 minutos.</strong></p>
        </body></html>';

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('Erro ao enviar e-mail 2FA: ' . $e->getMessage());
        return false;
    }
}

function verificar_codigo_2fa_email(string $codigo_digitado, string $codigo_armazenado, string $data_envio): bool
{
    $tempo_expiracao = 600;
    if ((time() - strtotime($data_envio)) > $tempo_expiracao) {
        return false;
    }
    return $codigo_digitado === $codigo_armazenado;
}

function documento_requer_2fa(string $categoria_acesso): bool
{
    return in_array($categoria_acesso, ['confidencial', 'secreto'], true);
}

function pode_acessar_documento_sigiloso(string $categoria_acesso): bool
{
    if (!is_logged_in()) {
        return false;
    }
    if (!documento_requer_2fa($categoria_acesso)) {
        return true;
    }
    if (empty($_SESSION['2fa_verificado'])) {
        return false;
    }
    return match ($categoria_acesso) {
        'confidencial' => is_gestor() || is_admin(),
        'secreto' => is_admin(),
        default => true,
    };
}

function registrar_tentativa_acesso_sigiloso($usuario_id, $documento_id, bool $sucesso): void
{
    global $pdo;
    $stmt = $pdo->prepare(
        'INSERT INTO tentativas_acesso_sigiloso (usuario_id, documento_id, sucesso, data_tentativa, ip) VALUES (?, ?, ?, NOW(), ?)'
    );
    $stmt->execute([$usuario_id, $documento_id, $sucesso ? 1 : 0, $_SERVER['REMOTE_ADDR'] ?? '']);
}

function precisa_completar_2fa(): bool
{
    return !empty($_SESSION['aguardando_2fa']);
}

function marcar_2fa_verificado(): void
{
    $_SESSION['aguardando_2fa'] = false;
    $_SESSION['2fa_verificado'] = true;
}

function gerar_e_enviar_codigo_2fa($usuario_id): bool
{
    global $pdo;

    $codigo = gerar_codigo_2fa_email();
    $data_envio = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare('UPDATE usuarios SET codigo_2fa = ?, data_codigo_2fa = ? WHERE id = ?');
    $stmt->execute([$codigo, $data_envio, $usuario_id]);

    $stmt = $pdo->prepare('SELECT email, nome FROM usuarios WHERE id = ?');
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$usuario) {
        return false;
    }

    return enviar_codigo_2fa_email($usuario['email'], $usuario['nome'], $codigo);
}

function login_api(string $email, string $senha): bool
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM usuariosapi WHERE email = ? LIMIT 1');
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

function is_logged_in_api(): bool
{
    return isset($_SESSION['usuarioapi_id']);
}

function get_usuarioapi_id()
{
    return $_SESSION['usuarioapi_id'] ?? null;
}

function permissoes_do_usuario(): array
{
    global $pdo;

    if (!is_logged_in()) {
        return [];
    }
    if (isset($_SESSION['permissoes_cache']) && is_array($_SESSION['permissoes_cache'])) {
        return $_SESSION['permissoes_cache'];
    }

    try {
        $sql = 'SELECT DISTINCT p.chave
                FROM usuario_grupos ug
                JOIN grupo_permissoes gp ON gp.grupo_id = ug.grupo_id
                JOIN permissoes p ON p.id = gp.permissao_id
                WHERE ug.usuario_id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['usuario_id']]);
        $perms = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $_SESSION['permissoes_cache'] = $perms ?: [];
        return $_SESSION['permissoes_cache'];
    } catch (Throwable $e) {
        $_SESSION['permissoes_cache'] = [];
        return [];
    }
}

function pode(string $permissao_chave): bool
{
    $perfil = $_SESSION['perfil'] ?? '';
    if ($perfil === 'admin' || $perfil === 'administrador') {
        return true;
    }
    return in_array($permissao_chave, permissoes_do_usuario(), true);
}

function exigir_permissao(string $permissao_chave): void
{
    if (!is_logged_in()) {
        header('Location: ../auth/login.php');
        exit;
    }
    if (!pode($permissao_chave)) {
        http_response_code(403);
        echo '<div style="font-family:sans-serif;text-align:center;padding:50px;background:#f8d7da;color:#721c24;">
                <h1>Acesso Negado</h1>
                <p>Você não tem permissão para realizar esta ação.</p>
                <p><strong>Permissão necessária:</strong> ' . htmlspecialchars($permissao_chave, ENT_QUOTES, 'UTF-8') . '</p>
              </div>';
        exit;
    }
}
