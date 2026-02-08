<?php
require_once 'db.php';
function registrar_movimentacao($documento_id, $usuario_id, $acao, $observacao = null) {
    global $pdo;
    try {
        $stmtCheck = $pdo->prepare("SELECT 1 FROM documentos WHERE id = ?");
        $stmtCheck->execute([$documento_id]);
        if (!$stmtCheck->fetchColumn()) {
            return false;
        }

        $stmt = $pdo->prepare("INSERT INTO movimentacao (documento_id, usuario_id, acao, observacao) VALUES (?, ?, ?, ?)");
        $stmt->execute([$documento_id, $usuario_id, $acao, $observacao]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>