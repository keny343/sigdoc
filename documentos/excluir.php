<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/movimentacao.php';

if (!is_logged_in() || (!is_admin() && !is_gestor())) {
    header('Location: listar.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Location: listar.php');
    exit;
}

csrf_require();

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    header('Location: listar.php');
    exit;
}

$stmt = $pdo->prepare('SELECT categoria_acesso, caminho_arquivo FROM documentos WHERE id = ?');
$stmt->execute([$id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    header('Location: listar.php');
    exit;
}

if ($doc['caminho_arquivo'] && file_exists('../uploads/' . $doc['caminho_arquivo'])) {
    unlink('../uploads/' . $doc['caminho_arquivo']);
}

registrar_movimentacao($id, $_SESSION['usuario_id'], 'excluido', 'Documento excluído.');
$stmt = $pdo->prepare('DELETE FROM documentos WHERE id = ?');
$stmt->execute([$id]);

require_once '../includes/webhook.php';
disparar_webhook('documento_excluido', [
    'id' => $id,
    'usuario_id' => $_SESSION['usuario_id'],
    'acao' => 'excluido',
]);

header('Location: listar.php');
exit;
