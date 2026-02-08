<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/movimentacao.php';
require_once '../includes/notificar.php';
require_once '../includes/lang.php';

// Definir cookie de idioma se necessário (deve ser feito antes de qualquer saída)
if (isset($_GET['lang']) && in_array($_GET['lang'], ['pt', 'en'])) {
    set_language_cookie($_GET['lang']);
}
if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit;
}
$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: listar.php');
    exit;
}
$stmt = $pdo->prepare("SELECT d.*, ST_Y(d.localizacao) AS lat, ST_X(d.localizacao) AS lng FROM documentos d WHERE d.id = ?");
$stmt->execute([$id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);
$pode_editar = false;
if (is_admin() || is_gestor()) {
    $pode_editar = true;
} elseif (is_colaborador() && in_array($doc['categoria_acesso'], ['publico','privado']) && $doc['usuario_id'] == $_SESSION['usuario_id']) {
    $pode_editar = true;
}
if (!$doc || !$pode_editar) {
    header('Location: listar.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $tipo = $_POST['tipo'];
    $setor = $_POST['setor'];
    $prioridade = $_POST['prioridade'];
    $estado = $_POST['estado'];
    $prazo = $_POST['prazo'];
    $categoria_acesso = $_POST['categoria_acesso'];
    $area_destino_novo = $_POST['area_destino'];
    $area_origem = $_POST['area_origem'];
    $geo_lat = $_POST['geo_lat'] ?? '';
    $geo_lng = $_POST['geo_lng'] ?? '';
    $tramitar = false;
    if (($area_destino_novo !== $doc['area_destino']) && (is_admin() || is_gestor())) {
        $tramitar = true;
    }

    $sqlUpdate = "UPDATE documentos SET titulo=?, descricao=?, tipo=?, setor=?, prioridade=?, estado=?, prazo=?, categoria_acesso=?, area_origem=?, area_destino=?";
    $paramsUpdate = [$titulo, $descricao, $tipo, $setor, $prioridade, $estado, $prazo, $categoria_acesso, $area_origem, $area_destino_novo];

    if ($geo_lat !== '' && $geo_lng !== '') {
        $lat = (float)$geo_lat;
        $lng = (float)$geo_lng;
        if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
            $sqlUpdate .= ", localizacao = ST_GeomFromText(CONCAT('POINT(', ?, ' ', ?, ')'))";
            $paramsUpdate[] = (string)$lng;
            $paramsUpdate[] = (string)$lat;
        }
    }

    $sqlUpdate .= " WHERE id=?";
    $paramsUpdate[] = $id;
    $stmt = $pdo->prepare($sqlUpdate);
    $stmt->execute($paramsUpdate);
    require_once '../includes/webhook.php';
    // Webhook documento_tramitado
    if ($tramitar) {
        $dados_webhook = [
            'id' => $id,
            'usuario_id' => $_SESSION['usuario_id'],
            'area_destino' => $area_destino_novo
        ];
        disparar_webhook('documento_tramitado', $dados_webhook);
    }
    // Webhook documento_arquivado
    if ($estado === 'arquivado' && $doc['estado'] !== 'arquivado') {
        $dados_webhook = [
            'id' => $id,
            'usuario_id' => $_SESSION['usuario_id']
        ];
        disparar_webhook('documento_arquivado', $dados_webhook);
    }
    // Webhook documento_aprovado
    if ($estado === 'aprovado' && $doc['estado'] !== 'aprovado') {
        $dados_webhook = [
            'id' => $id,
            'usuario_id' => $_SESSION['usuario_id']
        ];
        disparar_webhook('documento_aprovado', $dados_webhook);
    }
    // Webhook documento_em_analise
    if ($estado === 'em_analise' && $doc['estado'] !== 'em_analise') {
        $dados_webhook = [
            'id' => $id,
            'usuario_id' => $_SESSION['usuario_id']
        ];
        disparar_webhook('documento_em_analise', $dados_webhook);
    }
    $dados_webhook = [
        'id' => $id,
        'titulo' => $titulo,
        'descricao' => $descricao,
        'tipo' => $tipo,
        'setor' => $setor,
        'prioridade' => $prioridade,
        'estado' => $estado,
        'usuario_id' => $_SESSION['usuario_id'],
        'area_origem' => $area_origem,
        'area_destino' => $area_destino_novo,
        'categoria_acesso' => $categoria_acesso,
        'prazo' => $prazo
    ];
    disparar_webhook('documento_editado', $dados_webhook);
    if ($tramitar) {
        registrar_movimentacao($id, $_SESSION['usuario_id'], 'tramitação', 'Documento tramitado para área: ' . $area_destino_novo);
        // Buscar e-mail do responsável pela área de destino (usuário com nome igual à área_destino)
        $stmtUser = $pdo->prepare("SELECT email FROM usuarios WHERE nome = ? LIMIT 1");
        $stmtUser->execute([$area_destino_novo]);
        $userDestino = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($userDestino && !empty($userDestino['email'])) {
            notificar_area_destino($userDestino['email'], $area_destino_novo, $titulo);
        }
    } else {
        registrar_movimentacao($id, $_SESSION['usuario_id'], 'editado', 'Documento editado.');
    }
    header('Location: listar.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= get_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= t('edit_document') ?> - SIGDoc</title>
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
<div class="container card p-4">
    <h2 class="mb-4"><?= t('edit_document') ?></h2>
    <form method="post">
        <div class="mb-3">
            <label class="form-label"><?= t('title') ?></label>
            <input type="text" name="titulo" value="<?= htmlspecialchars($doc['titulo']) ?>" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('description') ?></label>
            <textarea name="descricao" class="form-control"><?= htmlspecialchars($doc['descricao']) ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('type') ?></label>
            <input type="text" name="tipo" value="<?= htmlspecialchars($doc['tipo']) ?>" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('sector') ?></label>
            <input type="text" name="setor" value="<?= htmlspecialchars($doc['setor']) ?>" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('priority') ?></label>
            <select name="prioridade" class="form-select">
                <option value="baixa" <?= $doc['prioridade']=='baixa'?'selected':'' ?>><?= t('low') ?></option>
                <option value="media" <?= $doc['prioridade']=='media'?'selected':'' ?>><?= t('medium') ?></option>
                <option value="alta" <?= $doc['prioridade']=='alta'?'selected':'' ?>><?= t('high') ?></option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('status') ?></label>
            <select name="estado" class="form-select">
                <option value="pendente" <?= $doc['estado']=='pendente'?'selected':'' ?>><?= t('pending') ?></option>
                <option value="em_analise" <?= $doc['estado']=='em_analise'?'selected':'' ?>><?= t('in_analysis') ?></option>
                <option value="aprovado" <?= $doc['estado']=='aprovado'?'selected':'' ?>><?= t('approved') ?></option>
                <option value="arquivado" <?= $doc['estado']=='arquivado'?'selected':'' ?>><?= t('archived') ?></option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('deadline') ?> (<?= t('due_date') ?>)</label>
            <input type="date" name="prazo" class="form-control" value="<?= htmlspecialchars($doc['prazo'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('access_category') ?></label>
            <select name="categoria_acesso" class="form-select" required>
                <option value="publico" <?= ($doc['categoria_acesso'] ?? '')=='publico'?'selected':'' ?>><?= t('public') ?></option>
                <option value="privado" <?= ($doc['categoria_acesso'] ?? '')=='privado'?'selected':'' ?>><?= t('private') ?></option>
                <option value="confidencial" <?= ($doc['categoria_acesso'] ?? '')=='confidencial'?'selected':'' ?>><?= t('confidential') ?></option>
                <option value="secreto" <?= ($doc['categoria_acesso'] ?? '')=='secreto'?'selected':'' ?>><?= t('secret') ?></option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('origin_area') ?></label>
            <input type="text" name="area_origem" class="form-control" value="<?= htmlspecialchars($doc['area_origem'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('destination_area') ?></label>
            <input type="text" name="area_destino" class="form-control" value="<?= htmlspecialchars($doc['area_destino'] ?? '') ?>" required <?= (is_admin() || is_gestor()) ? '' : 'readonly' ?>>
        </div>
        <div class="mb-3">
            <label class="form-label">Latitude</label>
            <input type="number" name="geo_lat" step="0.000001" class="form-control" value="<?= htmlspecialchars($doc['lat'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Longitude</label>
            <input type="number" name="geo_lng" step="0.000001" class="form-control" value="<?= htmlspecialchars($doc['lng'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
    </form>
</div>
<footer>
  <span><?= t('developed_in') ?> 2025</span>
</footer>
</body>
</html> 