<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Carrega o autoloader do Composer (PHPMailer)
require_once __DIR__ . '/../vendor/autoload.php';

// Carrega as configurações de e-mail
require_once __DIR__ . '/../config_email.php';

/**
 * Envia uma notificação de novo documento por e-mail.
 *
 * @param string $email_destino  E-mail de quem vai receber
 * @param string $nome_destino   Nome de quem vai receber
 * @param string $titulo_doc     Título do documento
 * @return bool                  True se enviado com sucesso, False se erro
 */
function notificar_area_destino($email_destino, $nome_destino, $titulo_doc) {
    $mail = new PHPMailer(true);

    try {
        // Configurações do servidor SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        // De/Para
        $mail->setFrom(SMTP_USER, 'SIGDoc');
        $mail->addAddress($email_destino, $nome_destino);

        // Conteúdo do e-mail
        $mail->isHTML(false); // use true se quiser HTML
        $mail->Subject = 'Novo documento recebido no SIGDoc';
        $mail->Body    = "Olá $nome_destino,\n\nVocê recebeu um novo documento: '$titulo_doc' no SIGDoc.\nAcesse o sistema para visualizar e confirmar o recebimento.";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Erro ao enviar e-mail: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Envia uma notificação de nova versão de documento por e-mail.
 *
 * @param string $email_destino  E-mail de quem vai receber
 * @param string $nome_destino   Nome de quem vai receber
 * @param string $titulo_doc     Título do documento
 * @param int $numero_versao     Número da nova versão
 * @return bool                  True se enviado com sucesso, False se erro
 */
function notificar_nova_versao($email_destino, $nome_destino, $titulo_doc, $numero_versao) {
    $mail = new PHPMailer(true);

    try {
        // Configurações do servidor SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        // De/Para
        $mail->setFrom(SMTP_USER, 'SIGDoc');
        $mail->addAddress($email_destino, $nome_destino);

        // Conteúdo do e-mail
        $mail->isHTML(false);
        $mail->Subject = 'Nova versão de documento no SIGDoc';
        $mail->Body    = "Olá $nome_destino,\n\nUma nova versão (v$numero_versao) do documento '$titulo_doc' foi disponibilizada no SIGDoc.\nAcesse o sistema para visualizar a nova versão.";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Erro ao enviar e-mail de nova versão: ' . $mail->ErrorInfo);
        return false;
    }
}

// Função genérica para envio de e-mail
function enviarEmail($destino, $assunto, $mensagem) {
    $mail = new PHPMailer(true);
    try {
        // Configurações do servidor SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        // De/Para
        $mail->setFrom(SMTP_USER, 'SIGDoc');
        $mail->addAddress($destino);
        // Conteúdo do e-mail
        $mail->isHTML(false);
        $mail->Subject = $assunto;
        $mail->Body    = $mensagem;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Erro ao enviar e-mail genérico: ' . $mail->ErrorInfo);
        return false;
    }
}
?>
 