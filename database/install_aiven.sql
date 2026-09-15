-- SIGDoc core schema for Aiven (no fragile comment splitting)
-- Safe to re-run: drops and recreates.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS grupo_permissoes;
DROP TABLE IF EXISTS usuario_grupos;
DROP TABLE IF EXISTS grupos;
DROP TABLE IF EXISTS perfil_permissoes;
DROP TABLE IF EXISTS permissoes;
DROP TABLE IF EXISTS tentativas_acesso_sigiloso;
DROP TABLE IF EXISTS acessos;
DROP TABLE IF EXISTS metadados;
DROP TABLE IF EXISTS movimentacao;
DROP TABLE IF EXISTS documento_versoes;
DROP TABLE IF EXISTS documentos;
DROP TABLE IF EXISTS notificacoes;
DROP TABLE IF EXISTS webhooks;
DROP TABLE IF EXISTS usuariosapi;
DROP TABLE IF EXISTS acessos_geograficos;
DROP TABLE IF EXISTS limites_geograficos;
DROP TABLE IF EXISTS usuarios;

CREATE TABLE usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  senha VARCHAR(255) NOT NULL,
  perfil VARCHAR(50) NOT NULL DEFAULT 'colaborador',
  ultima_localizacao POINT NULL,
  dois_fatores_ativado TINYINT(1) NOT NULL DEFAULT 0,
  codigo_2fa VARCHAR(6) DEFAULT NULL,
  data_codigo_2fa TIMESTAMP NULL DEFAULT NULL,
  data_ativacao_2fa TIMESTAMP NULL DEFAULT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documentos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo VARCHAR(255) NOT NULL,
  descricao TEXT NULL,
  tipo VARCHAR(100) DEFAULT NULL,
  setor VARCHAR(100) DEFAULT NULL,
  prioridade VARCHAR(20) DEFAULT 'media',
  estado VARCHAR(50) DEFAULT 'pendente',
  caminho_arquivo VARCHAR(500) DEFAULT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  prazo DATE DEFAULT NULL,
  categoria_acesso VARCHAR(30) NOT NULL DEFAULT 'publico',
  area_origem VARCHAR(190) DEFAULT NULL,
  area_destino VARCHAR(190) DEFAULT NULL,
  localizacao POINT NULL,
  endereco VARCHAR(255) DEFAULT NULL,
  versao_atual INT NOT NULL DEFAULT 1,
  data_upload TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_documentos_usuario (usuario_id),
  CONSTRAINT fk_documentos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documento_versoes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  documento_id INT UNSIGNED NOT NULL,
  numero_versao INT NOT NULL,
  nome_arquivo VARCHAR(255) NOT NULL,
  caminho_arquivo VARCHAR(500) NOT NULL,
  observacoes TEXT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  data_upload TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_versao (documento_id, numero_versao),
  CONSTRAINT fk_versoes_documento FOREIGN KEY (documento_id) REFERENCES documentos (id) ON DELETE CASCADE,
  CONSTRAINT fk_versoes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE movimentacao (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  documento_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  acao VARCHAR(50) NOT NULL,
  observacao TEXT NULL,
  data_acao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_mov_documento FOREIGN KEY (documento_id) REFERENCES documentos (id) ON DELETE CASCADE,
  CONSTRAINT fk_mov_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE metadados (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  documento_id INT UNSIGNED NOT NULL,
  chave VARCHAR(100) NOT NULL,
  valor TEXT NOT NULL,
  data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_metadados_documento FOREIGN KEY (documento_id) REFERENCES documentos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE acessos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  acao ENUM('login','logout') NOT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  data_acao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_acessos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tentativas_acesso_sigiloso (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  documento_id INT UNSIGNED DEFAULT NULL,
  sucesso TINYINT(1) NOT NULL DEFAULT 0,
  data_tentativa TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip VARCHAR(45) DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissoes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  chave VARCHAR(100) NOT NULL,
  descricao VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_permissoes_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE perfil_permissoes (
  perfil VARCHAR(50) NOT NULL,
  permissao_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (perfil, permissao_id),
  CONSTRAINT fk_perfil_perm FOREIGN KEY (permissao_id) REFERENCES permissoes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grupos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_grupos_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuario_grupos (
  usuario_id INT UNSIGNED NOT NULL,
  grupo_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (usuario_id, grupo_id),
  CONSTRAINT fk_ug_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_ug_grupo FOREIGN KEY (grupo_id) REFERENCES grupos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grupo_permissoes (
  grupo_id INT UNSIGNED NOT NULL,
  permissao_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (grupo_id, permissao_id),
  CONSTRAINT fk_gp_grupo FOREIGN KEY (grupo_id) REFERENCES grupos (id) ON DELETE CASCADE,
  CONSTRAINT fk_gp_perm FOREIGN KEY (permissao_id) REFERENCES permissoes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuariosapi (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  senha VARCHAR(255) NOT NULL,
  perfil VARCHAR(50) NOT NULL DEFAULT 'api',
  api_token VARCHAR(64) DEFAULT NULL,
  dois_fatores_ativado TINYINT(1) NOT NULL DEFAULT 0,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_usuariosapi_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webhooks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  evento VARCHAR(100) NOT NULL,
  url VARCHAR(500) NOT NULL,
  token VARCHAR(255) DEFAULT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notificacoes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario VARCHAR(190) NOT NULL,
  titulo VARCHAR(255) DEFAULT NULL,
  mensagem TEXT NULL,
  lida TINYINT(1) NOT NULL DEFAULT 0,
  data_envio TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_leitura TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO usuarios (nome, email, senha, perfil) VALUES
('Administrador', 'admin@sigdoc.local', '$2y$10$CQw8R/4NI6NVvUghc7uOhOJ9aPqeviv4uJSUYa27iSjeavpCMo9py', 'admin'),
('Gestor Demo', 'gestor@sigdoc.local', '$2y$10$CQw8R/4NI6NVvUghc7uOhOJ9aPqeviv4uJSUYa27iSjeavpCMo9py', 'gestor');
