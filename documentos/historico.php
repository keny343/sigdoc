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
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Histórico de Movimentação</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="../includes/style.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/icons/file-earmark-text.svg">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="listar.php">SIGDoc</a>
    <div class="d-flex">
      <a href="listar.php" class="btn btn-outline-light me-2">Voltar</a>
      <a href="../auth/logout.php" class="btn btn-danger">Sair</a>
    </div>
  </div>
</nav>
<div class="container card p-4">
    <h2 class="mb-4">Histórico de Movimentação do Documento #<?= htmlspecialchars($documento_id) ?></h2>

    <?php if ($pode_confirmar): ?>
    <form method="post" class="mb-3">
        <button type="submit" name="confirmar_recebimento" class="btn btn-success">
            Confirmar Recebimento
        </button>
    </form>
    <?php endif; ?>

    <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
        <thead class="table-primary">
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
<footer class="text-center mt-4">
  <span>Desenvolvido em 2025</span>
</footer>

<!-- Botão Dark Mode -->
<button id="toggle-dark" class="btn btn-secondary position-fixed bottom-0 end-0 m-3" style="z-index:9999">
    🌙 Alternar Dark Mode
</button>
<script>
  function toggleDarkMode() {
    document.body.classList.toggle('bg-dark');
    document.body.classList.toggle('text-light');
    localStorage.setItem('darkmode', document.body.classList.contains('bg-dark'));
  }
  document.getElementById('toggle-dark').onclick = toggleDarkMode;
  if(localStorage.getItem('darkmode') === 'true') {
    document.body.classList.add('bg-dark', 'text-light');
  }
</script>
</body>
</html>
