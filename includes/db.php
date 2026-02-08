<?php
// Configurações para Infinity Free
$host = 'sql106.infinityfree.com';
$db   = 'if0_40919058_sigdoc';
$user = 'if0_40919058';
$pass = 'Kenykeny2003';
$port = 3306;

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Auditoria de acessos (login/logout)
$pdo->exec("CREATE TABLE IF NOT EXISTS acessos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    acao ENUM('login','logout') NOT NULL,
    ip VARCHAR(45),
    data_acao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS permissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) NOT NULL UNIQUE,
    descricao VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS perfil_permissoes (
    perfil VARCHAR(50) NOT NULL,
    permissao_id INT NOT NULL,
    PRIMARY KEY (perfil, permissao_id),
    FOREIGN KEY (permissao_id) REFERENCES permissoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$permissoesPadrao = [
    ['documentos.ver', 'Listar e visualizar documentos'],
    ['documentos.criar', 'Criar documentos'],
    ['documentos.editar', 'Editar documentos'],
    ['documentos.excluir', 'Excluir documentos'],
    ['documentos.tramitar', 'Tramitar documentos'],
    ['documentos.exportar', 'Exportar documentos'],
    ['documentos.importar', 'Importar documentos'],
    ['documentos.versoes.ver', 'Listar versões de documentos'],
    ['documentos.versoes.criar', 'Criar nova versão de documento'],
    ['documentos.versoes.restaurar', 'Restaurar versão de documento'],
    ['usuarios.ver', 'Listar usuários'],
    ['usuarios.criar', 'Criar usuários'],
    ['usuarios.editar', 'Editar usuários'],
    ['usuarios.excluir', 'Excluir usuários'],
    ['auditoria.ver', 'Ver auditoria'],
    ['perfis.ver', 'Listar perfis'],
    ['areas.ver', 'Listar áreas'],
    ['notificacoes.ver', 'Listar notificações'],
    ['notificacoes.confirmar', 'Confirmar notificação'],
    ['geolocalizacao.documentos.editar', 'Atualizar geolocalização de documento']
];

$stmtPerm = $pdo->prepare('INSERT IGNORE INTO permissoes (chave, descricao) VALUES (?, ?)');
foreach ($permissoesPadrao as $p) {
    $stmtPerm->execute([$p[0], $p[1]]);
}

$perfilPerms = [
    'admin' => [
        'documentos.ver','documentos.criar','documentos.editar','documentos.excluir','documentos.tramitar','documentos.exportar','documentos.importar',
        'documentos.versoes.ver','documentos.versoes.criar','documentos.versoes.restaurar',
        'usuarios.ver','usuarios.criar','usuarios.editar','usuarios.excluir',
        'auditoria.ver','perfis.ver','areas.ver','notificacoes.ver','notificacoes.confirmar','geolocalizacao.documentos.editar'
    ],
    'administrador' => [
        'documentos.ver','documentos.criar','documentos.editar','documentos.excluir','documentos.tramitar','documentos.exportar','documentos.importar',
        'documentos.versoes.ver','documentos.versoes.criar','documentos.versoes.restaurar',
        'usuarios.ver','usuarios.criar','usuarios.editar','usuarios.excluir',
        'auditoria.ver','perfis.ver','areas.ver','notificacoes.ver','notificacoes.confirmar','geolocalizacao.documentos.editar'
    ],
    'gestor' => [
        'documentos.ver','documentos.criar','documentos.editar','documentos.tramitar','documentos.exportar','documentos.importar',
        'documentos.versoes.ver','documentos.versoes.criar','documentos.versoes.restaurar',
        'usuarios.ver',
        'perfis.ver','areas.ver','notificacoes.ver','notificacoes.confirmar','geolocalizacao.documentos.editar'
    ],
    'colaborador' => [
        'documentos.ver','documentos.criar','documentos.editar','documentos.tramitar','documentos.exportar',
        'documentos.versoes.ver','documentos.versoes.criar',
        'perfis.ver','areas.ver','notificacoes.ver','notificacoes.confirmar','geolocalizacao.documentos.editar'
    ],
    'visitante' => [
        'documentos.ver','documentos.versoes.ver','perfis.ver','areas.ver'
    ]
];

$stmtId = $pdo->prepare('SELECT id FROM permissoes WHERE chave = ?');
$stmtLink = $pdo->prepare('INSERT IGNORE INTO perfil_permissoes (perfil, permissao_id) VALUES (?, ?)');
foreach ($perfilPerms as $perfil => $perms) {
    foreach ($perms as $chave) {
        $stmtId->execute([$chave]);
        $permId = $stmtId->fetchColumn();
        if ($permId) {
            $stmtLink->execute([$perfil, $permId]);
        }
    }
}
?>