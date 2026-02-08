<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/movimentacao.php';
require_once '../includes/lang.php';

// Definir cookie de idioma se necessário (deve ser feito antes de qualquer saída)
if (isset($_GET['lang']) && in_array($_GET['lang'], ['pt', 'en'])) {
    set_language_cookie($_GET['lang']);
}
require_once '../includes/notificar.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit;
}

$documento_id = $_GET['id'] ?? null;
if (!$documento_id) {
    header('Location: listar.php');
    exit;
}

// Buscar documento
$stmt = $pdo->prepare("SELECT * FROM documentos WHERE id = ?");
$stmt->execute([$documento_id]);
$documento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$documento) {
    header('Location: listar.php');
    exit;
}

// Verificar permissões
$pode_gerenciar_versoes = false;
if (is_admin() || is_gestor()) {
    $pode_gerenciar_versoes = true;
} elseif (is_colaborador() && in_array($documento['categoria_acesso'], ['publico','privado']) && $documento['usuario_id'] == $_SESSION['usuario_id']) {
    $pode_gerenciar_versoes = true;
}

if (!$pode_gerenciar_versoes) {
    header('Location: listar.php');
    exit;
}

// Processar upload de nova versão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_versao'])) {
    $observacoes = $_POST['observacoes'] ?? '';
    $arquivo = $_FILES['arquivo'];
    
    if ($arquivo['error'] === UPLOAD_ERR_OK) {
        // Buscar próxima versão
        $stmt = $pdo->prepare("SELECT MAX(numero_versao) as max_versao FROM documento_versoes WHERE documento_id = ?");
        $stmt->execute([$documento_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nova_versao = ($result['max_versao'] ?? 0) + 1;
        
        // Upload do arquivo
        $nome_arquivo = uniqid() . '_v' . $nova_versao . '_' . basename($arquivo['name']);
        $destino = "../uploads/" . $nome_arquivo;
        
        if (move_uploaded_file($arquivo['tmp_name'], $destino)) {
            // Inserir nova versão
            $stmt = $pdo->prepare("INSERT INTO documento_versoes (documento_id, numero_versao, nome_arquivo, caminho_arquivo, observacoes, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$documento_id, $nova_versao, $nome_arquivo, $nome_arquivo, $observacoes, $_SESSION['usuario_id']]);
            // Disparar webhook para nova versão
            require_once '../includes/webhook.php';
            $dados_webhook = [
                'documento_id' => $documento_id,
                'nova_versao' => $nova_versao,
                'usuario_id' => $_SESSION['usuario_id']
            ];
            disparar_webhook('nova_versao', $dados_webhook);
            
            // Atualizar versão atual do documento
            $stmt = $pdo->prepare("UPDATE documentos SET versao_atual = ?, caminho_arquivo = ? WHERE id = ?");
            $stmt->execute([$nova_versao, $nome_arquivo, $documento_id]);
            
            // Registrar movimentação
            registrar_movimentacao($documento_id, $_SESSION['usuario_id'], 'nova_versao', "Nova versão $nova_versao: $observacoes");
            
            // Notificar área de destino sobre nova versão
            if (!empty($documento['area_destino'])) {
                $stmtUser = $pdo->prepare("SELECT email, nome FROM usuarios WHERE email = ? LIMIT 1");
                $stmtUser->execute([$documento['area_destino']]);
                $userDestino = $stmtUser->fetch(PDO::FETCH_ASSOC);
                if ($userDestino) {
                    notificar_nova_versao($userDestino['email'], $userDestino['nome'], $documento['titulo'], $nova_versao);
                }
            }
            
            $sucesso = "Nova versão $nova_versao criada com sucesso!";
        } else {
            $erro = "Erro ao fazer upload do arquivo.";
        }
    } else {
        $erro = "Erro no upload do arquivo.";
    }
}

// Processar restauração de versão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restaurar_versao'])) {
    $versao_restaurar = $_POST['versao_restaurar'];
    
    $stmt = $pdo->prepare("SELECT * FROM documento_versoes WHERE documento_id = ? AND numero_versao = ?");
    $stmt->execute([$documento_id, $versao_restaurar]);
    $versao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($versao) {
        // Atualizar documento para usar a versão restaurada
        $stmt = $pdo->prepare("UPDATE documentos SET caminho_arquivo = ?, versao_atual = ? WHERE id = ?");
        $stmt->execute([$versao['caminho_arquivo'], $versao['numero_versao'], $documento_id]);
        
        // Registrar movimentação
        registrar_movimentacao($documento_id, $_SESSION['usuario_id'], 'restaurado', "Versão $versao_restaurar restaurada");
        
        // Webhook documento_restaurado
        require_once '../includes/webhook.php';
        $dados_webhook = [
            'documento_id' => $documento_id,
            'versao_restaurada' => $versao_restaurar,
            'usuario_id' => $_SESSION['usuario_id']
        ];
        disparar_webhook('documento_restaurado', $dados_webhook);
        
        $sucesso = "Versão $versao_restaurar restaurada com sucesso!";
    } else {
        $erro = "Versão não encontrada.";
    }
}

// Buscar versões do documento
$stmt = $pdo->prepare("SELECT dv.*, u.nome AS usuario_nome FROM documento_versoes dv 
                       JOIN usuarios u ON dv.usuario_id = u.id 
                       WHERE dv.documento_id = ? 
                       ORDER BY dv.numero_versao DESC");
$stmt->execute([$documento_id]);
$versoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="<?= get_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= t('document_versions') ?> - SIGDoc</title>
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
      <a href="listar.php" class="btn btn-outline-light me-2"><?= t('documents') ?></a>
      <a href="../painel.php" class="btn btn-outline-light me-2"><?= t('dashboard') ?></a>
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
                <div class="card-header">
                    <h4><?= t('document_versions') ?>: <?= htmlspecialchars($documento['titulo']) ?></h4>
                </div>
                <div class="card-body">
                    <?php if (isset($sucesso)): ?>
                        <div class="alert alert-success"><?= $sucesso ?></div>
                    <?php endif; ?>
                    <?php if (isset($erro)): ?>
                        <div class="alert alert-danger"><?= $erro ?></div>
                    <?php endif; ?>
                    
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th><?= t('version') ?></th>
                                    <th><?= t('file') ?></th>
                                    <th><?= t('user') ?></th>
                                    <th><?= t('date') ?></th>
                                    <th><?= t('observations') ?></th>
                                    <th><?= t('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($versoes as $versao): ?>
                                <tr <?= ($versao['numero_versao'] == $documento['versao_atual']) ? 'class="table-primary"' : '' ?>>
                                    <td>
                                        <strong>v<?= $versao['numero_versao'] ?></strong>
                                        <?php if ($versao['numero_versao'] == $documento['versao_atual']): ?>
                                            <span class="badge bg-success"><?= t('current') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="../uploads/<?= htmlspecialchars($versao['caminho_arquivo']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-download"></i> <?= t('download') ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($versao['usuario_nome']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($versao['data_upload'])) ?></td>
                                    <td><?= htmlspecialchars($versao['observacoes']) ?></td>
                                    <td>
                                        <?php if ($versao['numero_versao'] != $documento['versao_atual']): ?>
                                            <form method="post" style="display: inline;" onsubmit="return confirm('<?= t('restore_version_confirm') ?> <?= $versao['numero_versao'] ?>?')">
                                                <input type="hidden" name="versao_restaurar" value="<?= $versao['numero_versao'] ?>">
                                                <button type="submit" name="restaurar_versao" class="btn btn-sm btn-warning">
                                                    <?= t('restore') ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><?= t('upload_new_version') ?></h5>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label"><?= t('file') ?></label>
                            <input type="file" name="arquivo" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= t('observations') ?></label>
                            <textarea name="observacoes" class="form-control" rows="3" placeholder="<?= t('describe_changes_placeholder') ?>"></textarea>
                        </div>
                        <button type="submit" name="upload_versao" class="btn btn-primary">
                            <?= t('upload_new_version') ?>
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h5><?= t('document_information') ?></h5>
                </div>
                <div class="card-body">
                    <p><strong><?= t('title') ?>:</strong> <?= htmlspecialchars($documento['titulo']) ?></p>
                    <p><strong><?= t('type') ?>:</strong> <?= htmlspecialchars($documento['tipo']) ?></p>
                    <p><strong><?= t('sector') ?>:</strong> <?= htmlspecialchars($documento['setor']) ?></p>
                    <p><strong><?= t('category') ?>:</strong> <?= t($documento['categoria_acesso']) ?></p>
                    <p><strong><?= t('current_version') ?>:</strong> v<?= $documento['versao_atual'] ?></p>
                    <p><strong><?= t('total_versions') ?>:</strong> <?= count($versoes) ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<footer>
  <span><?= t('developed_in') ?> 2025</span>
</footer>
</body>
</html> 