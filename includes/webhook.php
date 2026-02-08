<?php
require_once __DIR__ . '/db.php';

/**
 * Dispara webhooks para todas as URLs cadastradas para um evento.
 * @param string $evento Nome do evento (ex: 'documento_criado')
 * @param array $dados Dados a serem enviados no payload (array associativo)
 */
function disparar_webhook($evento, $dados) {
    global $pdo;

    $pdo->exec("CREATE TABLE IF NOT EXISTS webhooks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        evento VARCHAR(100) NOT NULL,
        url VARCHAR(500) NOT NULL,
        token VARCHAR(255) DEFAULT NULL,
        ativo TINYINT(1) DEFAULT 1,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_webhooks_evento (evento),
        INDEX idx_webhooks_ativo (ativo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare("SELECT url, token FROM webhooks WHERE evento = ? AND ativo = 1");
    $stmt->execute([$evento]);
    $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($webhooks as $webhook) {
        $ch = curl_init($webhook['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        $headers = ['Content-Type: application/json'];
        if (!empty($webhook['token'])) {
            $headers[] = 'X-Webhook-Token: ' . $webhook['token'];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
    }
} 