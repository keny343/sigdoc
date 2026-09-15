-- SIGDoc — canonical MySQL / MariaDB schema
-- For InfinityFree: select your database in phpMyAdmin, then Import this file.
-- Do NOT run CREATE DATABASE on shared hosting (DB already exists).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Users
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `senha` VARCHAR(255) NOT NULL,
  `perfil` VARCHAR(50) NOT NULL DEFAULT 'colaborador',
  `ultima_localizacao` POINT NULL,
  `dois_fatores_ativado` TINYINT(1) NOT NULL DEFAULT 0,
  `codigo_2fa` VARCHAR(6) DEFAULT NULL,
  `data_codigo_2fa` TIMESTAMP NULL DEFAULT NULL,
  `data_ativacao_2fa` TIMESTAMP NULL DEFAULT NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuarios_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Documents
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documentos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(255) NOT NULL,
  `descricao` TEXT NULL,
  `tipo` VARCHAR(100) DEFAULT NULL,
  `setor` VARCHAR(100) DEFAULT NULL,
  `prioridade` VARCHAR(20) DEFAULT 'media',
  `estado` VARCHAR(50) DEFAULT 'pendente',
  `caminho_arquivo` VARCHAR(500) DEFAULT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `prazo` DATE DEFAULT NULL,
  `categoria_acesso` VARCHAR(30) NOT NULL DEFAULT 'publico',
  `area_origem` VARCHAR(190) DEFAULT NULL,
  `area_destino` VARCHAR(190) DEFAULT NULL,
  `localizacao` POINT NULL,
  `endereco` VARCHAR(255) DEFAULT NULL,
  `versao_atual` INT NOT NULL DEFAULT 1,
  `data_upload` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_documentos_usuario` (`usuario_id`),
  KEY `idx_documentos_categoria` (`categoria_acesso`),
  KEY `idx_documentos_estado` (`estado`),
  KEY `idx_documentos_setor` (`setor`),
  CONSTRAINT `fk_documentos_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `documento_versoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `documento_id` INT UNSIGNED NOT NULL,
  `numero_versao` INT NOT NULL,
  `nome_arquivo` VARCHAR(255) NOT NULL,
  `caminho_arquivo` VARCHAR(500) NOT NULL,
  `observacoes` TEXT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `data_upload` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_versao` (`documento_id`, `numero_versao`),
  KEY `idx_versoes_usuario` (`usuario_id`),
  CONSTRAINT `fk_versoes_documento`
    FOREIGN KEY (`documento_id`) REFERENCES `documentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_versoes_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `movimentacao` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `documento_id` INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `acao` VARCHAR(50) NOT NULL,
  `observacao` TEXT NULL,
  `data_acao` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mov_documento` (`documento_id`),
  KEY `idx_mov_usuario` (`usuario_id`),
  CONSTRAINT `fk_mov_documento`
    FOREIGN KEY (`documento_id`) REFERENCES `documentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mov_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `metadados` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `documento_id` INT UNSIGNED NOT NULL,
  `chave` VARCHAR(100) NOT NULL,
  `valor` TEXT NOT NULL,
  `data_criacao` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_metadados_documento_id` (`documento_id`),
  KEY `idx_metadados_chave` (`chave`),
  CONSTRAINT `fk_metadados_documento`
    FOREIGN KEY (`documento_id`) REFERENCES `documentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Audit / permissions
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `acessos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED NOT NULL,
  `acao` ENUM('login','logout') NOT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `data_acao` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_acessos_usuario` (`usuario_id`),
  CONSTRAINT `fk_acessos_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tentativas_acesso_sigiloso` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED NOT NULL,
  `documento_id` INT UNSIGNED DEFAULT NULL,
  `sucesso` TINYINT(1) NOT NULL DEFAULT 0,
  `data_tentativa` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` VARCHAR(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tentativas_usuario` (`usuario_id`),
  KEY `idx_tentativas_documento` (`documento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chave` VARCHAR(100) NOT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permissoes_chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `perfil_permissoes` (
  `perfil` VARCHAR(50) NOT NULL,
  `permissao_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`perfil`, `permissao_id`),
  CONSTRAINT `fk_perfil_perm`
    FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional group ACL (used by auth helpers when present)
CREATE TABLE IF NOT EXISTS `grupos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_grupos_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `usuario_grupos` (
  `usuario_id` INT UNSIGNED NOT NULL,
  `grupo_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`usuario_id`, `grupo_id`),
  CONSTRAINT `fk_ug_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ug_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `grupo_permissoes` (
  `grupo_id` INT UNSIGNED NOT NULL,
  `permissao_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`grupo_id`, `permissao_id`),
  CONSTRAINT `fk_gp_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gp_perm` FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- API / webhooks / notifications
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuariosapi` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `senha` VARCHAR(255) NOT NULL,
  `perfil` VARCHAR(50) NOT NULL DEFAULT 'api',
  `api_token` VARCHAR(64) DEFAULT NULL,
  `dois_fatores_ativado` TINYINT(1) NOT NULL DEFAULT 0,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuariosapi_email` (`email`),
  KEY `idx_usuariosapi_token` (`api_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `webhooks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `evento` VARCHAR(100) NOT NULL,
  `url` VARCHAR(500) NOT NULL,
  `token` VARCHAR(255) DEFAULT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_webhooks_evento` (`evento`),
  KEY `idx_webhooks_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notificacoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario` VARCHAR(190) NOT NULL,
  `titulo` VARCHAR(255) DEFAULT NULL,
  `mensagem` TEXT NULL,
  `lida` TINYINT(1) NOT NULL DEFAULT 0,
  `data_envio` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_leitura` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notif_usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional geospatial helpers
CREATE TABLE IF NOT EXISTS `acessos_geograficos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED NOT NULL,
  `documento_id` INT UNSIGNED DEFAULT NULL,
  `localizacao` POINT NOT NULL,
  `endereco_ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  SPATIAL KEY `idx_ag_localizacao` (`localizacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `limites_geograficos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(120) NOT NULL,
  `area` POLYGON NOT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  SPATIAL KEY `idx_lg_area` (`area`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- Seed admin (change password after first login)
-- email: admin@sigdoc.local
-- password: Admin@123
-- ---------------------------------------------------------------------------
INSERT INTO `usuarios` (`nome`, `email`, `senha`, `perfil`)
SELECT 'Administrador', 'admin@sigdoc.local',
       '$2y$10$CQw8R/4NI6NVvUghc7uOhOJ9aPqeviv4uJSUYa27iSjeavpCMo9py',
       'admin'
WHERE NOT EXISTS (
  SELECT 1 FROM `usuarios` WHERE `email` = 'admin@sigdoc.local'
);

-- A second user so "area destino" email checks work when creating documents
INSERT INTO `usuarios` (`nome`, `email`, `senha`, `perfil`)
SELECT 'Gestor Demo', 'gestor@sigdoc.local',
       '$2y$10$CQw8R/4NI6NVvUghc7uOhOJ9aPqeviv4uJSUYa27iSjeavpCMo9py',
       'gestor'
WHERE NOT EXISTS (
  SELECT 1 FROM `usuarios` WHERE `email` = 'gestor@sigdoc.local'
);
