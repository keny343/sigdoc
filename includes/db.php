<?php
/**
 * Database connection + optional permission seed.
 *
 * Schema is owned by database/reset_and_install.sql (or schema.sql).
 * Do NOT CREATE TABLE with foreign keys at runtime — that breaks on Aiven
 * when column types / spatial tables differ (MySQL error 1824).
 */
require_once __DIR__ . '/pdo_factory.php';

try {
    $pdo = sigdoc_pdo();
} catch (PDOException $e) {
    die('Erro na conexão com a base de dados.');
}

// Ensure core schema was imported
try {
    $pdo->query('SELECT 1 FROM usuarios LIMIT 1');
} catch (PDOException $e) {
    http_response_code(500);
    die('SIGDoc: tabela usuarios em falta. Importa database/reset_and_install.sql na base Aiven.');
}

// Seed permission catalog when those tables exist (safe / idempotent)
try {
    $pdo->query('SELECT 1 FROM permissoes LIMIT 1');
    $pdo->query('SELECT 1 FROM perfil_permissoes LIMIT 1');
} catch (PDOException $e) {
    // Permissions tables optional until schema import is complete
    return;
}

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
    ['geolocalizacao.documentos.editar', 'Atualizar geolocalização de documento'],
];

try {
    $stmtPerm = $pdo->prepare('INSERT IGNORE INTO permissoes (chave, descricao) VALUES (?, ?)');
    foreach ($permissoesPadrao as $p) {
        $stmtPerm->execute([$p[0], $p[1]]);
    }

    $perfilPerms = [
        'admin' => [
            'documentos.ver', 'documentos.criar', 'documentos.editar', 'documentos.excluir', 'documentos.tramitar', 'documentos.exportar', 'documentos.importar',
            'documentos.versoes.ver', 'documentos.versoes.criar', 'documentos.versoes.restaurar',
            'usuarios.ver', 'usuarios.criar', 'usuarios.editar', 'usuarios.excluir',
            'auditoria.ver', 'perfis.ver', 'areas.ver', 'notificacoes.ver', 'notificacoes.confirmar', 'geolocalizacao.documentos.editar',
        ],
        'administrador' => [
            'documentos.ver', 'documentos.criar', 'documentos.editar', 'documentos.excluir', 'documentos.tramitar', 'documentos.exportar', 'documentos.importar',
            'documentos.versoes.ver', 'documentos.versoes.criar', 'documentos.versoes.restaurar',
            'usuarios.ver', 'usuarios.criar', 'usuarios.editar', 'usuarios.excluir',
            'auditoria.ver', 'perfis.ver', 'areas.ver', 'notificacoes.ver', 'notificacoes.confirmar', 'geolocalizacao.documentos.editar',
        ],
        'gestor' => [
            'documentos.ver', 'documentos.criar', 'documentos.editar', 'documentos.tramitar', 'documentos.exportar', 'documentos.importar',
            'documentos.versoes.ver', 'documentos.versoes.criar', 'documentos.versoes.restaurar',
            'usuarios.ver',
            'perfis.ver', 'areas.ver', 'notificacoes.ver', 'notificacoes.confirmar', 'geolocalizacao.documentos.editar',
        ],
        'colaborador' => [
            'documentos.ver', 'documentos.criar', 'documentos.editar', 'documentos.tramitar', 'documentos.exportar',
            'documentos.versoes.ver', 'documentos.versoes.criar',
            'perfis.ver', 'areas.ver', 'notificacoes.ver', 'notificacoes.confirmar', 'geolocalizacao.documentos.editar',
        ],
        'visitante' => [
            'documentos.ver', 'documentos.versoes.ver', 'perfis.ver', 'areas.ver',
        ],
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
} catch (PDOException $e) {
    // Do not block login / pages if seed fails
}
