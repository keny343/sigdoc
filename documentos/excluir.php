<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/movimentacao.php';
if (!is_logged_in() || (!is_admin() && !is_gestor())) {
    header('Location: listar.php');
    exit;
}
$id = $_GET['id'] ?? null;
if ($id) {
    $stmt = $pdo->prepare("SELECT categoria_acesso, caminho_arquivo FROM documentos WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    $pode_excluir = false;
    if (is_admin() || is_gestor()) {
        $pode_excluir = true;
    }
    if (!$doc || !$pode_excluir) {
        header('Location: listar.php');
        exit;
    }
    if ($doc && file_exists("../uploads/" . $doc['caminho_arquivo'])) {
        unlink("../uploads/" . $doc['caminho_arquivo']);
    }

    registrar_movimentacao($id, $_SESSION['usuario_id'], 'excluido', 'Documento excluído.');
    $stmt = $pdo->prepare("DELETE FROM documentos WHERE id = ?");
    $stmt->execute([$id]);
    // Disparar webhook para evento de documento excluído
    require_once '../includes/webhook.php';
    $dados_webhook = [
        'id' => $id,
        'usuario_id' => $_SESSION['usuario_id'],
        'acao' => 'excluido'
    ];
    disparar_webhook('documento_excluido', $dados_webhook);
}
header('Location: listar.php');
exit; 