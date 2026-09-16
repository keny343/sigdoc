<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/lang.php';

// Definir cookie de idioma se necessário (deve ser feito antes de qualquer saída)
if (isset($_GET['lang']) && in_array($_GET['lang'], ['pt', 'en'])) {
    set_language_cookie($_GET['lang']);
}

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit;
}

// Verificar se precisa completar 2FA
if (precisa_completar_2fa()) {
    header('Location: ../auth/verificar_2fa.php?retorno=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Verificar se é a primeira vez que o usuário acessa após o login
$mostrar_boas_vindas = false;
if (isset($_SESSION['usuario_nome']) && !isset($_SESSION['boas_vindas_exibida'])) {
    $mostrar_boas_vindas = true;
    $_SESSION['boas_vindas_exibida'] = true;
}
?>
<?php
$sigdoc_base = '../';
$page_title = t('documents');
$sigdoc_active = 'documentos';
ob_start();
?>
      <?php if (!is_visitante()): ?>
      <a href="adicionar.php" class="btn btn-primary btn-sm"><?= t('new_document') ?></a>
      <?php endif; ?>
<?php
$sigdoc_top_actions = ob_get_clean();
require '../includes/theme_config.php';
require '../includes/layout_header.php';
?>

<?php if ($mostrar_boas_vindas): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <strong><?= str_replace('{name}', htmlspecialchars($_SESSION['usuario_nome']), t('welcome_message')) ?></strong>
  <div class="small"><?= t('system_welcome') ?></div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="page-header">
  <div>
    <h2><?= t('documents') ?></h2>
    <p class="page-sub">Filtros, classificação e tramitação</p>
  </div>
</div>

<div class="card p-4 mb-4">
    <?php if (isset($_GET['erro'])): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($_GET['erro']) ?>
        </div>
    <?php endif; ?>
    <form method="get" class="row g-3">
        <div class="col-md-2">
            <input type="text" name="busca" class="form-control" placeholder="<?= t('title') ?> <?= t('or') ?> <?= t('type') ?>" value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <select name="estado" class="form-select">
                <option value=""><?= t('all') ?> <?= t('status') ?></option>
                <option value="pendente" <?= (($_GET['estado'] ?? '')=='pendente')?'selected':'' ?>><?= t('pending') ?></option>
                <option value="em_analise" <?= (($_GET['estado'] ?? '')=='em_analise')?'selected':'' ?>><?= t('in_analysis') ?></option>
                <option value="aprovado" <?= (($_GET['estado'] ?? '')=='aprovado')?'selected':'' ?>><?= t('approved') ?></option>
                <option value="arquivado" <?= (($_GET['estado'] ?? '')=='arquivado')?'selected':'' ?>><?= t('archived') ?></option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="categoria_acesso" class="form-select">
                <option value=""><?= t('all') ?> <?= t('categories') ?></option>
                <option value="publico" <?= (($_GET['categoria_acesso'] ?? '')=='publico')?'selected':'' ?>><?= t('public') ?></option>
                <option value="privado" <?= (($_GET['categoria_acesso'] ?? '')=='privado')?'selected':'' ?>><?= t('private') ?></option>
                <option value="confidencial" <?= (($_GET['categoria_acesso'] ?? '')=='confidencial')?'selected':'' ?>><?= t('confidential') ?></option>
                <option value="secreto" <?= (($_GET['categoria_acesso'] ?? '')=='secreto')?'selected':'' ?>><?= t('secret') ?></option>
            </select>
        </div>
        <div class="col-md-2">
            <input type="text" name="setor" class="form-control" placeholder="<?= t('sector') ?>" value="<?= htmlspecialchars($_GET['setor'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <input type="text" name="area_destino" class="form-control" placeholder="<?= t('destination_area') ?>" value="<?= htmlspecialchars($_GET['area_destino'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <select name="prioridade" class="form-select">
                <option value=""><?= t('all') ?> <?= t('priorities') ?></option>
                <option value="baixa" <?= (($_GET['prioridade'] ?? '')=='baixa')?'selected':'' ?>><?= t('low') ?></option>
                <option value="media" <?= (($_GET['prioridade'] ?? '')=='media')?'selected':'' ?>><?= t('medium') ?></option>
                <option value="alta" <?= (($_GET['prioridade'] ?? '')=='alta')?'selected':'' ?>><?= t('high') ?></option>
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" name="data_upload" class="form-control" value="<?= htmlspecialchars($_GET['data_upload'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <input type="text" name="palavra_chave" class="form-control" placeholder="<?= t('keyword') ?>" value="<?= htmlspecialchars($_GET['palavra_chave'] ?? '') ?>">
        </div>
        <div class="col-md-12 text-end">
            <button type="submit" class="btn btn-primary"><?= t('filter') ?></button>
        </div>
    </form>
<?php
// Configuração de paginação
$itens_por_pagina = 20;
$pagina_atual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Filtros PHP
$where = [];
$params = [];
if (!empty($_GET['busca'])) {
    $where[] = "(d.titulo LIKE ? OR d.tipo LIKE ?)";
    $params[] = '%' . $_GET['busca'] . '%';
    $params[] = '%' . $_GET['busca'] . '%';
}
if (!empty($_GET['estado'])) {
    $where[] = "d.estado = ?";
    $params[] = $_GET['estado'];
}
if (!empty($_GET['categoria_acesso'])) {
    $where[] = "d.categoria_acesso = ?";
    $params[] = $_GET['categoria_acesso'];
}
if (!empty($_GET['setor'])) {
    $where[] = "d.setor LIKE ?";
    $params[] = '%' . $_GET['setor'] . '%';
}
if (!empty($_GET['area_destino'])) {
    $where[] = "d.area_destino LIKE ?";
    $params[] = '%' . $_GET['area_destino'] . '%';
}
if (!empty($_GET['prioridade'])) {
    $where[] = "d.prioridade = ?";
    $params[] = $_GET['prioridade'];
}
if (!empty($_GET['data_upload'])) {
    $where[] = "DATE(d.data_upload) = ?";
    $params[] = $_GET['data_upload'];
}
$join = "";
if (!empty($_GET['palavra_chave'])) {
    $join = " JOIN metadados m ON d.id = m.documento_id AND m.chave = 'palavra_chave'";
    $where[] = "m.valor LIKE ?";
    $params[] = '%' . $_GET['palavra_chave'] . '%';
}

// Query para contar total de registros
$sql_count = "SELECT COUNT(*) FROM documentos d JOIN usuarios u ON d.usuario_id = u.id" . $join;
if ($where) {
    $sql_count .= " WHERE " . implode(" AND ", $where);
}
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $itens_por_pagina);

// Query principal com paginação
$sql = "SELECT d.*, u.nome AS usuario_nome FROM documentos d JOIN usuarios u ON d.usuario_id = u.id" . $join;
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY d.data_upload DESC LIMIT $itens_por_pagina OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
    <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
        <thead class="table-primary">
        <tr>
            <th>ID</th>
            <th><?= t('title') ?></th>
            <th><?= t('type') ?></th>
            <th><?= t('sector') ?></th>
            <th><?= t('category') ?></th>
            <th><?= t('origin_area') ?></th>
            <th><?= t('destination_area') ?></th>
            <th><?= t('priority') ?></th>
            <th><?= t('status') ?></th>
            <th><?= t('version') ?></th>
            <th><?= t('user') ?></th>
            <th><?= t('file') ?></th>
            <th><?= t('actions') ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($documentos as $doc): ?>
        <tr>
            <td><?= $doc['id'] ?></td>
            <td><?= htmlspecialchars($doc['titulo']) ?></td>
            <td><?= htmlspecialchars($doc['tipo']) ?></td>
            <td><?= htmlspecialchars($doc['setor']) ?></td>
            <td><?= t($doc['categoria_acesso'] ?? '') ?></td>
            <td><?= htmlspecialchars($doc['area_origem'] ?? '') ?></td>
            <td><?= htmlspecialchars($doc['area_destino'] ?? '') ?></td>
            <td><?= t($doc['prioridade']) ?></td>
            <td><?= t($doc['estado']) ?></td>
            <td>
                <span class="badge bg-primary">v<?= $doc['versao_atual'] ?? 1 ?></span>
            </td>
            <td><?= htmlspecialchars($doc['usuario_nome']) ?></td>
            <td>
                <?php
                $pode_ver = false;
                if (is_admin() || is_gestor()) {
                    $pode_ver = true;
                } elseif (is_colaborador() && in_array($doc['categoria_acesso'], ['publico','privado'])) {
                    $pode_ver = true;
                } elseif (is_visitante() && $doc['categoria_acesso'] == 'publico') {
                    $pode_ver = true;
                }
                ?>
                <?php if ($pode_ver): ?>
<a href="../uploads/<?= htmlspecialchars($doc['caminho_arquivo']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><?= t('download') ?></a>
                <?php else: ?>
                    <span class="text-muted"><?= t('restricted') ?></span>
                <?php endif; ?>
            </td>
            <td class="acoes-cell">
                <div class="acoes-buttons">
                    <?php if ($pode_ver): ?>
                        <a href="visualizar.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-primary acao-btn"><?= t('view') ?></a>
                    <?php endif; ?>
                    <?php if ($pode_ver && !is_visitante()): ?>
                        <a href="editar.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-warning acao-btn"><?= t('edit') ?></a> 
                    <?php endif; ?>
                    <?php if ($pode_ver && (is_admin() || is_gestor())): ?>
                        <form method="post" action="excluir.php" class="d-inline" onsubmit="return confirm('<?= htmlspecialchars(t('confirm_delete'), ENT_QUOTES) ?>')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger acao-btn"><?= t('delete') ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($pode_ver): ?>
                        <a href="historico.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-info acao-btn"><?= t('history') ?></a>
                        <?php if ($pode_ver && !is_visitante()): ?>
                            <a href="versoes.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-secondary acao-btn"><?= t('versions') ?></a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- Paginação -->
    <?php if ($total_paginas > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-4">
        <div class="text-muted">
            <?= t('showing') ?> <?= ($offset + 1) ?> <?= t('to') ?> <?= min($offset + $itens_por_pagina, $total_registros) ?> 
            <?= t('of') ?> <?= $total_registros ?> <?= t('documents') ?>
        </div>
        
        <nav aria-label="Paginação de documentos">
            <ul class="pagination mb-0">
                <!-- Botão Anterior -->
                <?php if ($pagina_atual > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina_atual - 1])) ?>">
                            &laquo; <?= t('previous') ?>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link">&laquo; <?= t('previous') ?></span>
                    </li>
                <?php endif; ?>

                <!-- Números das páginas -->
                <?php
                $inicio = max(1, $pagina_atual - 2);
                $fim = min($total_paginas, $pagina_atual + 2);
                
                if ($inicio > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => 1])) ?>">1</a>
                    </li>
                    <?php if ($inicio > 2): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $inicio; $i <= $fim; $i++): ?>
                    <li class="page-item <?= $i == $pagina_atual ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($fim < $total_paginas): ?>
                    <?php if ($fim < $total_paginas - 1): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $total_paginas])) ?>"><?= $total_paginas ?></a>
                    </li>
                <?php endif; ?>

                <!-- Botão Próximo -->
                <?php if ($pagina_atual < $total_paginas): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina_atual + 1])) ?>">
                            <?= t('next') ?> &raquo;
                        </a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link"><?= t('next') ?> &raquo;</span>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
<?php require '../includes/layout_footer.php'; ?>
 