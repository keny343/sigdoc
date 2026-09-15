-- SIGDoc — fix Aiven / legacy DB missing usuarios.perfil
-- Run in DBeaver on database `defaultdb` if you see:
--   Error SQL [1054]: Unknown column 'perfil' in 'field list'
--
-- CREATE TABLE IF NOT EXISTS does NOT add columns to existing tables.

SET NAMES utf8mb4;

-- Add perfil if missing
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'usuarios'
    AND COLUMN_NAME = 'perfil'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `usuarios` ADD COLUMN `perfil` VARCHAR(50) NOT NULL DEFAULT ''colaborador'' AFTER `senha`',
  'SELECT ''usuarios.perfil already exists'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Optional columns used by the app (safe if already present)
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'dois_fatores_ativado'
);
SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `usuarios` ADD COLUMN `dois_fatores_ativado` TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'codigo_2fa'
);
SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `usuarios` ADD COLUMN `codigo_2fa` VARCHAR(6) DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'data_codigo_2fa'
);
SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `usuarios` ADD COLUMN `data_codigo_2fa` TIMESTAMP NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'data_ativacao_2fa'
);
SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `usuarios` ADD COLUMN `data_ativacao_2fa` TIMESTAMP NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Seed admin / gestor (same as schema.sql)
INSERT INTO `usuarios` (`nome`, `email`, `senha`, `perfil`)
SELECT 'Administrador', 'admin@sigdoc.local',
       '$2y$10$CQw8R/4NI6NVvUghc7uOhOJ9aPqeviv4uJSUYa27iSjeavpCMo9py',
       'admin'
WHERE NOT EXISTS (
  SELECT 1 FROM `usuarios` WHERE `email` = 'admin@sigdoc.local'
);

INSERT INTO `usuarios` (`nome`, `email`, `senha`, `perfil`)
SELECT 'Gestor Demo', 'gestor@sigdoc.local',
       '$2y$10$CQw8R/4NI6NVvUghc7uOhOJ9aPqeviv4uJSUYa27iSjeavpCMo9py',
       'gestor'
WHERE NOT EXISTS (
  SELECT 1 FROM `usuarios` WHERE `email` = 'gestor@sigdoc.local'
);
