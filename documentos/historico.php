<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit;
}

$documento_id = $_GET['id'] ?? null;

if (!$documento_id) {
    header('Location: listar.php');
    exit;
}

// Verifica o nível de acesso ao documento
$stmt = $pdo->prepare("SELECT categoria_acesso, area_destino FROM documentos WHERE id = ?");
$stmt->execute([$documento_id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

$pode_ver = false;

if (is_admin() || is_gestor()) {
    $pode_ver = true;
} elseif (is_colaborador() && in_array($doc['categoria_acesso'], ['publico','privado'])) {
    $pode_ver = true;
} elseif (is_visitante() && $doc['categoria_acesso'] == 'publico') {
    $pode_ver = true;
}

if (!$doc || !$pode_ver) {
    header('Location: listar.php');
    exit;
}

// Consulta histórico de movimentações
$stmt = $pdo->prepare("
    SELECT 
        m.*, 
        u.nome AS usuario_nome, 
        d.area_origem, 
        d.area_destino 
    FROM movimentacao m 
    JOIN usuarios u ON m.usuario_id = u.id 
    JOIN documentos d ON m.documento_id = d.id 
    WHERE m.documento_id = ? 
    ORDER BY m.data_acao DESC
");
$stmt->execute([$documento_id]);
$movs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verifica se o usuário pode confirmar recebimento
$pode_confirmar = false;
if (
    is_logged_in() && 
    isset($doc['area_destino']) && 
    strtolower(trim($doc['area_destino'])) === strtolower(trim($_SESSION['usuario_nome'] ?? ''))
) {
    // Só pode confirmar se ainda não houver confirmação registrada
    $stmtConf = $pdo->prepare("SELECT COUNT(*) FROM movimentacao WHERE documento_id = ? AND acao = 'recebido'");
    $stmtConf->execute([$documento_id]);
    if ($stmtConf->fetchColumn() == 0) {
        $pode_confirmar = true;
    }
}

// Trata o envio do formulário de confirmação
if ($pode_confirmar && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_recebimento'])) {
    csrf_require();
    registrar_movimentacao(
        $documento_id, 
        $_SESSION['usuario_id'], 
        'recebido', 
        'Recebimento confirmado por ' . ($_SESSION['usuario_nome'] ?? '')
    );
    header('Location: historico.php?id=' . $documento_id);
    exit;
}
?>
<?php
require_once '../includes/lang.php';
$sigdoc_base = '../';
$page_title = 'Histórico de Movimentação';
$sigdoc_active = 'documentos';
ob_start();
?>
<a href="visualizar.php?id=<?= (int) $documento_id ?>" class="btn btn-outline-primary btn-sm"><?= t('back') ?></a>
<?php
$sigdoc_top_actions = ob_get_clean();
require_once '../includes/theme_config.php';
require '../includes/layout_header.php';
?>
<div class="page-header">
  <div>
    <h2>Histórico de Movimentação</h2>
    <p class="page-sub">Documento #<?= htmlspecialchars((string) $documento_id) ?></p>
  </div>
</div>
<div class="card p-4">
    <?php if ($pode_confirmar): ?>
    <form method="post" class="mb-3">
        <?= csrf_field() ?>
        <button type="submit" name="confirmar_recebimento" class="btn btn-success">
            Confirmar Recebimento
        </button>
    </form>
    <?php endif; ?>

    <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
        <thead>
        <tr>
            <th>Data</th>
            <th>Usuário</th>
            <th>Ação</th>
            <th>Área Origem</th>
            <th>Área Destino</th>
            <th>Observação</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($movs as $mov): ?>
        <tr>
            <td><?= htmlspecialchars($mov['data_acao']) ?></td>
            <td><?= htmlspecialchars($mov['usuario_nome']) ?></td>
            <td><?= htmlspecialchars($mov['acao']) ?></td>
            <td><?= htmlspecialchars($mov['area_origem']) ?></td>
            <td><?= htmlspecialchars($mov['area_destino']) ?></td>
            <td><?= htmlspecialchars($mov['observacao'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php require '../includes/layout_footer.php'; ?>

