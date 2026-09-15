<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
    require_once 'includes/auth.php';
    require_once 'includes/db.php';
    require_once 'includes/notificar.php';
    require_once 'includes/theme_config.php';
    require_once 'includes/cache.php';
    require_once 'includes/lang.php';
} catch (Exception $e) {
    die('Erro ao carregar ficheiros.');
}

// Definir cookie de idioma se necessário (deve ser feito antes de qualquer saída)
if (isset($_GET['lang']) && in_array($_GET['lang'], ['pt', 'en'])) {
    set_language_cookie($_GET['lang']);
}

if (!is_logged_in()) {
    header('Location: /auth/login.php');
    exit;
}

// Verificar se é a primeira vez que o usuário acessa após o login
$mostrar_boas_vindas = false;
if (isset($_SESSION['usuario_nome']) && !isset($_SESSION['boas_vindas_exibida'])) {
    $mostrar_boas_vindas = true;
    $_SESSION['boas_vindas_exibida'] = true;
}

// Função auxiliar para executar queries com tratamento de erro
function safeQuery($pdo, $sql, $default = 0) {
    try {
        $result = $pdo->query($sql);
        return $result ? $result->fetchColumn() : $default;
    } catch (PDOException $e) {
        error_log("Erro na query: " . $e->getMessage());
        return $default;
    }
}

function safeQueryAll($pdo, $sql, $default = []) {
    try {
        $result = $pdo->query($sql);
        return $result ? $result->fetchAll(PDO::FETCH_ASSOC) : $default;
    } catch (PDOException $e) {
        error_log("Erro na query: " . $e->getMessage());
        return $default;
    }
}

// Usar cache para otimizar consultas
try {
    $total = cache_remember('total_documentos', function() use ($pdo) {
        return safeQuery($pdo, "SELECT COUNT(*) FROM documentos", 0);
    }, 300);
} catch (Exception $e) {
    $total = 0;
    error_log("Erro ao buscar total de documentos: " . $e->getMessage());
}

// Verificar se a coluna 'estado' existe antes de consultar
try {
    $por_estado = cache_remember('documentos_por_estado', function() use ($pdo) {
        // Verificar se a coluna estado existe
        $colunas = $pdo->query("SHOW COLUMNS FROM documentos LIKE 'estado'")->fetchAll();
        if (empty($colunas)) {
            return ['pendente' => 0, 'em_analise' => 0, 'aprovado' => 0, 'arquivado' => 0];
        }
        
        $estados = ['pendente', 'em_analise', 'aprovado', 'arquivado'];
        $result = [];
        foreach ($estados as $estado) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE estado = ?");
                $stmt->execute([$estado]);
                $result[$estado] = $stmt->fetchColumn() ?: 0;
            } catch (PDOException $e) {
                $result[$estado] = 0;
            }
        }
        return $result;
    }, 300);
} catch (Exception $e) {
    $por_estado = ['pendente' => 0, 'em_analise' => 0, 'aprovado' => 0, 'arquivado' => 0];
}

// Verificar se a coluna 'setor' existe
try {
    $setores = cache_remember('documentos_por_setor', function() use ($pdo) {
        $colunas = $pdo->query("SHOW COLUMNS FROM documentos LIKE 'setor'")->fetchAll();
        if (empty($colunas)) {
            return [];
        }
        return safeQueryAll($pdo, "SELECT setor, COUNT(*) as total FROM documentos WHERE setor IS NOT NULL GROUP BY setor ORDER BY total DESC LIMIT 10", []);
    }, 600);
} catch (Exception $e) {
    $setores = [];
}

$mes_atual = date('Y-m');
try {
    $no_mes = cache_remember('documentos_mes_atual', function() use ($pdo, $mes_atual) {
        return safeQuery($pdo, "SELECT COUNT(*) FROM documentos WHERE DATE_FORMAT(data_upload, '%Y-%m') = '$mes_atual'", 0);
    }, 300);
} catch (Exception $e) {
    $no_mes = 0;
}

$hoje = date('Y-m-d');
try {
    $alertas = cache_remember('documentos_alertas', function() use ($pdo, $hoje) {
        $colunas = $pdo->query("SHOW COLUMNS FROM documentos LIKE 'prazo'")->fetchAll();
        if (empty($colunas)) {
            return [];
        }
        return safeQueryAll($pdo, "SELECT id, titulo, prazo FROM documentos WHERE prazo IS NOT NULL AND prazo <> '' AND prazo <= DATE_ADD('$hoje', INTERVAL 3 DAY) ORDER BY prazo ASC", []);
    }, 180);
} catch (Exception $e) {
    $alertas = [];
}

// Notificações automáticas para documentos importantes não lidos após 3 dias
try {
    $colunas = $pdo->query("SHOW COLUMNS FROM documentos LIKE 'area_destino'")->fetchAll();
    if (!empty($colunas)) {
        $docsImportantes = safeQueryAll($pdo, "SELECT d.id, d.titulo, d.area_destino, d.prioridade, d.categoria_acesso, d.data_upload FROM documentos d WHERE (d.prioridade = 'alta' OR d.categoria_acesso IN ('confidencial','secreto')) AND d.data_upload <= DATE_SUB('$hoje', INTERVAL 3 DAY)", []);
        foreach ($docsImportantes as $docImp) {
            try {
                // Verifica se já foi confirmado recebimento
                $stmtConf = $pdo->prepare("SELECT COUNT(*) FROM movimentacao WHERE documento_id = ? AND acao = 'recebido'");
                $stmtConf->execute([$docImp['id']]);
                if ($stmtConf->fetchColumn() == 0) {
                    // Notifica o responsável pela área de destino
                    $stmtUser = $pdo->prepare("SELECT email, nome FROM usuarios WHERE nome = ? LIMIT 1");
                    $stmtUser->execute([$docImp['area_destino']]);
                    $userDestino = $stmtUser->fetch(PDO::FETCH_ASSOC);
                    if ($userDestino && !empty($userDestino['email'])) {
                        notificar_area_destino($userDestino['email'], $userDestino['nome'], $docImp['titulo']);
                    }
                }
            } catch (Exception $e) {
                error_log("Erro ao processar notificação: " . $e->getMessage());
            }
        }
    }
} catch (Exception $e) {
    error_log("Erro ao buscar documentos importantes: " . $e->getMessage());
}
?>
<?php
$sigdoc_base = '';
$page_title = t('management_panel');
$sigdoc_active = 'painel';
$sigdoc_extra_head = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
ob_start();
?>
      <?php if (!is_visitante()): ?>
      <a href="documentos/adicionar.php" class="btn btn-primary btn-sm"><?= t('new_document') ?></a>
      <?php endif; ?>
      <?php if (is_admin()): ?>
      <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCriarUsuario"><?= t('new_user') ?></button>
      <?php endif; ?>
<?php
$sigdoc_top_actions = ob_get_clean();
require 'includes/layout_header.php';
?>

<?php if ($mostrar_boas_vindas): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <strong><?= str_replace('{name}', htmlspecialchars($_SESSION['usuario_nome']), t('welcome_message')) ?></strong>
  <div class="small"><?= t('system_welcome') ?></div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (is_admin()): ?>
<!-- Modal de Cadastro de Usuário -->
<div class="modal fade" id="modalCriarUsuario" tabindex="-1" aria-labelledby="modalCriarUsuarioLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <form method="post" action="usuarios/adicionar.php">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title" id="modalCriarUsuarioLabel"><?= t('create_new_user') ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('close') ?>"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"><?= t('full_name') ?></label>
                <input type="text" name="nome" class="form-control" placeholder="<?= t('full_name') ?>" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"><?= t('email') ?></label>
                <input type="email" name="email" class="form-control" placeholder="exemplo@email.com" required>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"><?= t('password') ?></label>
                <input type="password" name="senha" class="form-control" placeholder="<?= t('password') ?>" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"><?= t('access_profile') ?></label>
                <select name="perfil" class="form-select" required>
                  <option value=""><?= t('select_option') ?></option>
                  <option value="admin"><?= t('administrator_description') ?></option>
                  <option value="gestor"><?= t('manager_description') ?></option>
                  <option value="colaborador"><?= t('collaborator_description') ?></option>
                  <option value="visitante"><?= t('visitor_description') ?></option>
                </select>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
          <button type="submit" class="btn btn-primary"><?= t('create_new_user') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="page-header">
  <div>
    <h2><?= t('management_panel') ?></h2>
    <p class="page-sub">Visão geral de documentos, estados e auditoria</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <button type="button" class="btn btn-outline-primary btn-sm" onclick="refreshStats()">
      <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
    </button>
    <div class="btn-group">
      <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-download"></i> <?= t('export') ?>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="exportar_documentos.php?format=csv" target="_blank">CSV</a></li>
        <li><a class="dropdown-item" href="exportar_documentos.php?format=pdf" target="_blank">PDF</a></li>
      </ul>
    </div>
  </div>
</div>

<div class="stats-grid mb-4">
  <?= generate_stat_card(t('total_documents'), $total, '', 'brand') ?>
  <?= generate_stat_card(t('documents_this_month'), $no_mes, '', 'sucesso') ?>
  <?= generate_stat_card(t('active_users'), safeQuery($pdo, "SELECT COUNT(*) FROM usuarios", 0), '', 'info') ?>
  <?= generate_stat_card(t('movements'), safeQuery($pdo, "SELECT COUNT(*) FROM movimentacao", 0), '', 'aviso') ?>
</div>

    <?php if (count($alertas) > 0): ?>
    <div class="alert alert-danger mb-4">
        <strong><?= t('attention') ?> — <?= t('documents') ?> <?= t('overdue') ?> / <?= t('expires_soon') ?></strong>
        <div class="row mt-2">
            <?php foreach ($alertas as $a): ?>
            <div class="col-md-6 mb-2">
                <div class="d-flex justify-content-between align-items-center border rounded p-2 bg-white">
                    <div>
                        <a href="documentos/editar.php?id=<?= (int) $a['id'] ?>" class="fw-semibold text-decoration-none" style="color:var(--erro)">
                            <?= htmlspecialchars($a['titulo']) ?>
                        </a>
                        <div class="small text-muted"><?= t('deadline') ?>: <?= date('d/m/Y', strtotime($a['prazo'])) ?></div>
                    </div>
                    <span class="badge bg-danger"><?= t('urgent') ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php if (is_admin()): ?>
<div class="card mb-4">
    <div class="card-header"><?= t('audit') ?> · <?= t('access_log') ?> (<?= t('last') ?> 20)</div>
    <div class="card-body p-0">
        <div class="table-responsive" style="border:none;border-radius:0">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th><?= t('user') ?></th>
                        <th><?= t('action') ?></th>
                        <th><?= t('ip_address') ?></th>
                        <th><?= t('datetime') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                try {
                    $acessos = safeQueryAll($pdo, "SELECT a.*, u.nome FROM acessos a JOIN usuarios u ON a.usuario_id = u.id ORDER BY a.data_acao DESC LIMIT 20", []);
                } catch (Exception $e) {
                    $acessos = [];
                }
                foreach ($acessos as $ac): ?>
                    <tr>
                        <td><?= htmlspecialchars($ac['nome']) ?></td>
                        <td><?= $ac['acao'] == 'login' ? '<span class="badge bg-success">'.t('login').'</span>' : '<span class="badge bg-danger">'.t('logout').'</span>' ?></td>
                        <td><?= htmlspecialchars($ac['ip']) ?></td>
                        <td><?= date('d/m/Y H:i:s', strtotime($ac['data_acao'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card p-3 h-100">
                <h5 class="mb-3" style="font-size:var(--text-sm);font-weight:650"><?= t('documents_by_status') ?></h5>
                <canvas id="estadoChart" height="200"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-3 h-100">
                <h5 class="mb-3" style="font-size:var(--text-sm);font-weight:650"><?= t('documents_by_sector') ?></h5>
                <canvas id="setorChart" height="200"></canvas>
            </div>
        </div>
    </div>
<?php
ob_start();
?>
<script>
function refreshStats() {
    location.reload();
}
document.addEventListener('DOMContentLoaded', function() {
    const estadoChartEl = document.getElementById('estadoChart');
    const setorChartEl = document.getElementById('setorChart');
    if (estadoChartEl) {
        new Chart(estadoChartEl, {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_map(function($e){return ucfirst(str_replace('_',' ',$e));}, array_keys($por_estado))) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($por_estado)) ?>,
                    backgroundColor: ['#d97706', '#0284c7', '#059669', '#64748b'],
                }]
            },
            options: { plugins: { legend: { position: 'bottom' } } }
        });
    }
    if (setorChartEl && <?= json_encode(!empty($setores)) ?>) {
        new Chart(setorChartEl, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($setores, 'setor')) ?>,
                datasets: [{
                    label: 'Documentos',
                    data: <?= json_encode(array_column($setores, 'total')) ?>,
                    backgroundColor: '#0f766e'
                }]
            },
            options: {
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });
    } else if (setorChartEl) {
        setorChartEl.parentElement.innerHTML = '<p class="text-muted mb-0">Nenhum dado de setor disponível.</p>';
    }
});
</script>
<?php
$sigdoc_extra_scripts = ob_get_clean();
require 'includes/layout_footer.php';
 