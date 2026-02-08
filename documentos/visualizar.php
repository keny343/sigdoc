<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/movimentacao.php';
require_once '../includes/lang.php';

if (isset($_GET['lang']) && in_array($_GET['lang'], ['pt', 'en'])) {
    set_language_cookie($_GET['lang']);
}

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit;
}

if (precisa_completar_2fa()) {
    header('Location: ../auth/verificar_2fa.php?retorno=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: listar.php');
    exit;
}

// Buscar documento
$stmt = $pdo->prepare("SELECT d.*, u.nome AS usuario_nome FROM documentos d JOIN usuarios u ON d.usuario_id = u.id WHERE d.id = ?");
$stmt->execute([$id]);
$documento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$documento) {
    header('Location: listar.php');
    exit;
}

// Verificar se pode acessar documento sigiloso
if (!pode_acessar_documento_sigiloso($documento['categoria_acesso'])) {
    registrar_tentativa_acesso_sigiloso($_SESSION['usuario_id'], $id, false);
    $erro = "Acesso negado. Este documento requer autenticação multifator.";
    header('Location: listar.php?erro=' . urlencode($erro));
    exit;
}

// Registrar visualização
registrar_movimentacao($id, $_SESSION['usuario_id'], 'visualizado', 'Documento visualizado.');
require_once '../includes/webhook.php';
$dados_webhook_visualizado = [
    'id' => $id,
    'usuario_id' => $_SESSION['usuario_id']
];
disparar_webhook('documento_visualizado', $dados_webhook_visualizado);

if (documento_requer_2fa($documento['categoria_acesso'])) {
    registrar_tentativa_acesso_sigiloso($_SESSION['usuario_id'], $id, true);
}

// Verificar se é área de destino para confirmar recebimento
$pode_confirmar = false;
if ($documento['area_destino'] === $_SESSION['usuario_email']) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM movimentacao WHERE documento_id = ? AND acao = 'recebido'");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() == 0) {
        $pode_confirmar = true;
    }
}

// Processar confirmação de recebimento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_recebimento']) && $pode_confirmar) {
    registrar_movimentacao($id, $_SESSION['usuario_id'], 'recebido', 'Recebimento confirmado.');
    // Webhook documento_recebido
    require_once '../includes/webhook.php';
    $dados_webhook_recebido = [
        'id' => $id,
        'usuario_id' => $_SESSION['usuario_id']
    ];
    disparar_webhook('documento_recebido', $dados_webhook_recebido);
    // Enviar e-mail para a área de origem (corrigido: usar area_origem como e-mail)
    require_once '../includes/notificar.php';
    $email_origem = $documento['area_origem'];
    $email_recebedor = $_SESSION['usuario_email'];
    $mensagem = "O documento foi recebido por: $email_recebedor";
    if (filter_var($email_origem, FILTER_VALIDATE_EMAIL)) {
        enviarEmail($email_origem, "Documento Recebido", $mensagem);
    }
    header('Location: visualizar.php?id=' . $id);
    exit;
}

// Buscar histórico de movimentações
$stmt = $pdo->prepare("SELECT m.*, u.nome AS usuario_nome FROM movimentacao m JOIN usuarios u ON m.usuario_id = u.id WHERE m.documento_id = ? ORDER BY m.data_acao DESC");
$stmt->execute([$id]);
$movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar versões do documento
$stmt = $pdo->prepare("SELECT * FROM documento_versoes WHERE documento_id = ? ORDER BY numero_versao DESC");
$stmt->execute([$id]);
$versoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?= get_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= t('view_document') ?> - SIGDoc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="../includes/style.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/icons/file-earmark-text.svg">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="listar.php">SIGDoc</a>
    <div class="d-flex align-items-center">
      <a href="listar.php" class="btn btn-outline-light me-2"><?= t('back') ?></a>
      <?php if (is_admin() || is_gestor() || (is_colaborador() && $documento['usuario_id'] == $_SESSION['usuario_id'])): ?>
        <a href="editar.php?id=<?= $id ?>" class="btn btn-warning me-2"><?= t('edit') ?></a>
      <?php endif; ?>
      <a href="versoes.php?id=<?= $id ?>" class="btn btn-info me-2"><?= t('versions') ?></a>
      <a href="../auth/logout.php" class="btn btn-danger"><?= t('logout') ?></a>
      <form method="get" class="d-flex align-items-center me-2" style="margin: 0;">
        <select name="lang" onchange="this.form.submit()" class="form-select form-select-sm" style="width: auto;">
          <option value="pt"<?= (get_lang() == 'pt') ? ' selected' : '' ?>>🇧🇷 PT</option>
          <option value="en"<?= (get_lang() == 'en') ? ' selected' : '' ?>>🇺🇸 EN</option>
        </select>
      </form>
    </div>
  </div>
</nav>

<div class="container">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3><?= htmlspecialchars($documento['titulo']) ?></h3>
                    <span class="badge bg-<?= $documento['categoria_acesso'] === 'publico' ? 'success' : ($documento['categoria_acesso'] === 'privado' ? 'warning' : ($documento['categoria_acesso'] === 'confidencial' ? 'danger' : 'dark')) ?>">
                        <?= t($documento['categoria_acesso']) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong><?= t('type') ?>:</strong> <?= htmlspecialchars($documento['tipo']) ?><br>
                            <strong><?= t('sector') ?>:</strong> <?= htmlspecialchars($documento['setor']) ?><br>
                            <strong><?= t('priority') ?>:</strong> 
                            <span class="badge bg-<?= $documento['prioridade'] === 'alta' ? 'danger' : ($documento['prioridade'] === 'media' ? 'warning' : 'success') ?>">
                                <?= t($documento['prioridade']) ?>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <strong><?= t('status') ?>:</strong> 
                            <span class="badge bg-<?= $documento['estado'] === 'aprovado' ? 'success' : ($documento['estado'] === 'em_analise' ? 'warning' : ($documento['estado'] === 'arquivado' ? 'secondary' : 'primary')) ?>">
                                <?= t($documento['estado']) ?>
                            </span><br>
                            <strong><?= t('current_version') ?>:</strong> <?= $documento['versao_atual'] ?? 1 ?><br>
                            <strong><?= t('upload') ?>:</strong> <?= date('d/m/Y H:i', strtotime($documento['data_upload'])) ?>
                        </div>
                    </div>
                    
                    <?php if ($documento['descricao']): ?>
                        <div class="mb-3">
                            <strong><?= t('description') ?>:</strong><br>
                            <?= nl2br(htmlspecialchars($documento['descricao'])) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong><?= t('origin_area') ?>:</strong> <?= htmlspecialchars($documento['area_origem']) ?><br>
                            <strong><?= t('destination_area') ?>:</strong> <?= htmlspecialchars($documento['area_destino']) ?>
                        </div>
                        <div class="col-md-6">
                            <strong><?= t('user') ?>:</strong> <?= htmlspecialchars($documento['usuario_nome']) ?><br>
                            <?php if ($documento['prazo']): ?>
                                <strong><?= t('deadline') ?>:</strong> 
                                <span class="text-<?= strtotime($documento['prazo']) < time() ? 'danger' : 'success' ?>">
                                    <?= date('d/m/Y', strtotime($documento['prazo'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <strong><?= t('file') ?>:</strong> 
                        <a href="../uploads/<?= htmlspecialchars($documento['caminho_arquivo']) ?>" 
                           target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-download"></i> <?= t('download_file') ?>
                        </a>
                    </div>
                    
                    <?php if ($pode_confirmar): ?>
                        <form method="post" class="mt-3">
                            <button type="submit" name="confirmar_recebimento" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> <?= t('confirm_receipt') ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Histórico de Movimentações -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6>📋 <?= t('movement_history') ?></h6>
                </div>
                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                    <?php if ($movimentacoes): ?>
                        <?php foreach ($movimentacoes as $mov): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($mov['data_acao'])) ?></small><br>
                                <strong><?= htmlspecialchars($mov['usuario_nome']) ?></strong><br>
                                <span class="badge bg-info"><?= ucfirst($mov['acao']) ?></span><br>
                                <small><?= htmlspecialchars($mov['observacao'] ?? '—') ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted"><?= t('no_movements_recorded') ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Versões do Documento -->
            <?php if ($versoes): ?>
            <div class="card">
                <div class="card-header">
                    <h6>📄 <?= t('versions') ?></h6>
                </div>
                <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                    <?php foreach ($versoes as $versao): ?>
                        <div class="border-bottom pb-2 mb-2">
                            <strong><?= t('version') ?> <?= $versao['numero_versao'] ?></strong><br>
                            <small class="text-muted"><?= date('d/m/Y H:i', strtotime($versao['data_upload'])) ?></small><br>
                            <a href="../uploads/<?= htmlspecialchars($versao['caminho_arquivo']) ?>" 
                               target="_blank" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-download"></i> <?= t('download') ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
