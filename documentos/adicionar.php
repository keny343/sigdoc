<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/movimentacao.php';
require_once '../includes/lang.php';

// Definir cookie de idioma se necessário (deve ser feito antes de qualquer saída)
if (isset($_GET['lang']) && in_array($_GET['lang'], ['pt', 'en'])) {
    set_language_cookie($_GET['lang']);
}

if (!is_logged_in() || is_visitante()) {
    header('Location: listar.php');
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS metadados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    chave VARCHAR(100) NOT NULL,
    valor TEXT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_metadados_documento_id (documento_id),
    INDEX idx_metadados_chave (chave),
    FOREIGN KEY (documento_id) REFERENCES documentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $tipo = $_POST['tipo'];
    $setor = $_POST['setor'];
    $prioridade = $_POST['prioridade'];
    $estado = $_POST['estado'];
    $geo_lat = $_POST['geo_lat'] ?? '';
    $geo_lng = $_POST['geo_lng'] ?? '';
    $usuario_id = $_SESSION['usuario_id'];
    $arquivo = $_FILES['arquivo'];
    $nome_arquivo = uniqid() . '_' . basename($arquivo['name']);
    $destino = "../uploads/" . $nome_arquivo;

    // NOVO: Verificar email da área de destino
    $email_destino = $_POST['area_destino'];
    $categoria = $_POST['categoria_acesso'];
    $erro = null;
    $usuario_destino = null;
    if (!filter_var($email_destino, FILTER_VALIDATE_EMAIL)) {
        $erro = "O campo 'Área de Destino' deve ser um email válido.";
    } else {
        $stmt = $pdo->prepare("SELECT nome, perfil FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email_destino]);
        $usuario_destino = $stmt->fetch();
        if (!$usuario_destino) {
            $erro = "O email informado na área de destino não está cadastrado no sistema.";
        } else {
            // Verificar permissão conforme categoria
            $perfil_destino = $usuario_destino['perfil'];
            $permissao = false;
            if ($categoria === 'publico') {
                $permissao = true;
            } elseif ($categoria === 'privado' && in_array($perfil_destino, ['colaborador','gestor','admin'])) {
                $permissao = true;
            } elseif ($categoria === 'confidencial' && in_array($perfil_destino, ['gestor','admin'])) {
                $permissao = true;
            } elseif ($categoria === 'secreto' && $perfil_destino === 'admin') {
                $permissao = true;
            }
            if (!$permissao) {
                $erro = "O usuário de destino não tem permissão para acessar documentos dessa categoria.";
            }
        }
    }

    if (!$erro && move_uploaded_file($arquivo['tmp_name'], $destino)) {
        $lat_ok = false;
        $lng_ok = false;
        if ($geo_lat !== '' && $geo_lng !== '') {
            $lat = (float)$geo_lat;
            $lng = (float)$geo_lng;
            $lat_ok = ($lat >= -90 && $lat <= 90);
            $lng_ok = ($lng >= -180 && $lng <= 180);
        }

        if ($lat_ok && $lng_ok) {
            $stmt = $pdo->prepare("INSERT INTO documentos (titulo, descricao, tipo, setor, prioridade, estado, caminho_arquivo, usuario_id, prazo, categoria_acesso, area_origem, area_destino, localizacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ST_GeomFromText(CONCAT('POINT(', ?, ' ', ?, ')')))");
            $stmt->execute([$titulo, $descricao, $tipo, $setor, $prioridade, $estado, $nome_arquivo, $usuario_id, $_POST['prazo'], $categoria, $_POST['area_origem'], $email_destino, (string)$lng, (string)$lat]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO documentos (titulo, descricao, tipo, setor, prioridade, estado, caminho_arquivo, usuario_id, prazo, categoria_acesso, area_origem, area_destino, localizacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)");
            $stmt->execute([$titulo, $descricao, $tipo, $setor, $prioridade, $estado, $nome_arquivo, $usuario_id, $_POST['prazo'], $categoria, $_POST['area_origem'], $email_destino]);
        }
        $documento_id = $pdo->lastInsertId();
        // Palavras-chave
        if (!empty($_POST['palavras_chave'])) {
            $palavras = array_map('trim', explode(',', $_POST['palavras_chave']));
            foreach ($palavras as $palavra) {
                if ($palavra) {
                    $stmt = $pdo->prepare("INSERT INTO metadados (documento_id, chave, valor) VALUES (?, 'palavra_chave', ?)");
                    $stmt->execute([$documento_id, $palavra]);
                }
            }
        }
        registrar_movimentacao($documento_id, $usuario_id, 'criado', 'Documento cadastrado no sistema.');
        require_once '../includes/notificar.php';
        $nome_destino = $usuario_destino ? $usuario_destino['nome'] : 'Usuário da Área de Destino';
        notificar_area_destino(
            $email_destino,
            $nome_destino,
            $titulo
        );
        // Disparar webhook para evento de documento criado
        require_once '../includes/webhook.php';
        $dados_webhook = [
            'id' => $documento_id,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'tipo' => $tipo,
            'setor' => $setor,
            'prioridade' => $prioridade,
            'estado' => $estado,
            'usuario_id' => $usuario_id,
            'area_origem' => $_POST['area_origem'],
            'area_destino' => $email_destino,
            'categoria_acesso' => $categoria,
            'prazo' => $_POST['prazo']
        ];
        disparar_webhook('documento_criado', $dados_webhook);
        header('Location: listar.php');
        exit;
    } elseif (!$erro) {
        $erro = "Erro ao fazer upload do arquivo.";
    }
}
?>
<!DOCTYPE html>
<html lang="<?= get_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= t('new_document') ?> - SIGDoc</title>
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
    <h2 class="mb-4"><?= t('new_document') ?></h2>
    <?php if (isset($erro)) echo "<div class='alert alert-danger'>$erro</div>"; ?>
    <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label"><?= t('title') ?></label>
            <input type="text" name="titulo" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('description') ?></label>
            <textarea name="descricao" class="form-control"></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('type') ?></label>
            <input type="text" name="tipo" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('sector') ?></label>
            <input type="text" name="setor" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('priority') ?></label>
            <select name="prioridade" class="form-select">
                <option value="baixa"><?= t('low') ?></option>
                <option value="media" selected><?= t('medium') ?></option>
                <option value="alta"><?= t('high') ?></option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('status') ?></label>
            <select name="estado" class="form-select">
                <option value="pendente" selected><?= t('pending') ?></option>
                <option value="em_analise"><?= t('in_analysis') ?></option>
                <option value="aprovado"><?= t('approved') ?></option>
                <option value="arquivado"><?= t('archived') ?></option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('access_category') ?></label>
            <select name="categoria_acesso" class="form-select" required>
                <option value="publico"><?= t('public') ?></option>
                <option value="privado"><?= t('private') ?></option>
                <option value="confidencial"><?= t('confidential') ?></option>
                <option value="secreto"><?= t('secret') ?></option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('keywords') ?> (<?= t('separated_by_comma') ?>)</label>
            <input type="text" name="palavras_chave" class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('deadline') ?> (<?= t('due_date') ?>)</label>
            <input type="date" name="prazo" class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('origin_area') ?></label>
            <input type="text" name="area_origem" class="form-control" value="<?= $_SESSION['usuario_email'] ?? '' ?>" required readonly>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('destination_area') ?></label>
            <input type="text" name="area_destino" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('file') ?></label>
            <input type="file" name="arquivo" class="form-control" required>
        </div>
        <input type="hidden" name="geo_lat" id="geo_lat">
        <input type="hidden" name="geo_lng" id="geo_lng">
        <div class="mb-3">
            <div id="geo_status" class="form-text"></div>
        </div>
        <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
    </form>
</div>
<footer>
  <span><?= t('developed_in') ?> 2025</span>
</footer>
<script>
  (function() {
    const statusEl = document.getElementById('geo_status');
    const latEl = document.getElementById('geo_lat');
    const lngEl = document.getElementById('geo_lng');

    function setStatus(msg) {
      if (statusEl) statusEl.textContent = msg || '';
    }

    if (!navigator.geolocation) {
      setStatus('Geolocalização não suportada pelo navegador.');
      return;
    }

    setStatus('Obtendo sua localização automaticamente...');

    navigator.geolocation.getCurrentPosition(
      function(pos) {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        if (latEl) latEl.value = lat;
        if (lngEl) lngEl.value = lng;
        setStatus('Localização capturada.');
      },
      function(err) {
        setStatus('Não foi possível obter sua localização (permissão negada ou indisponível).');
      },
      { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
    );
  })();
</script>
</body>
</html>