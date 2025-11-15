-- =====================================================
-- Migration: Adicionar coluna external_id em tabelas CRM
-- Descrição: Garante que as tabelas clientes, produtos e vendas
--            tenham a coluna external_id para integração CRM
-- Data: 2025-11-14
-- =====================================================

-- Adiciona coluna external_id na tabela clientes se não existir
SET @dbname = DATABASE();
SET @tablename = 'clientes';
SET @columnname = 'external_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 'Column external_id already exists in clientes' AS msg;",
  CONCAT(
    "ALTER TABLE `", @tablename, "` ",
    "ADD COLUMN `external_id` VARCHAR(50) NULL COMMENT 'ID do sistema externo (CRM)' AFTER `id`, ",
    "ADD UNIQUE KEY `uk_external_id` (`external_id`);"
  )
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona coluna external_id na tabela produtos se não existir
SET @tablename = 'produtos';
SET @columnname = 'external_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 'Column external_id already exists in produtos' AS msg;",
  CONCAT(
    "ALTER TABLE `", @tablename, "` ",
    "ADD COLUMN `external_id` VARCHAR(50) NULL COMMENT 'ID do sistema externo (CRM)' AFTER `id`, ",
    "ADD UNIQUE KEY `uk_external_id` (`external_id`);"
  )
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona coluna external_id na tabela vendas se não existir
SET @tablename = 'vendas';
SET @columnname = 'external_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 'Column external_id already exists in vendas' AS msg;",
  CONCAT(
    "ALTER TABLE `", @tablename, "` ",
    "ADD COLUMN `external_id` VARCHAR(50) NULL COMMENT 'ID do sistema externo (CRM)' AFTER `id`, ",
    "ADD UNIQUE KEY `uk_vendas_external_id` (`external_id`);"
  )
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- =====================================================
-- RESUMO DA MIGRATION
-- =====================================================
-- Adiciona coluna `external_id` VARCHAR(50) NULL em:
-- 1. ✅ Tabela clientes (com UNIQUE KEY)
-- 2. ✅ Tabela produtos (com UNIQUE KEY)
-- 3. ✅ Tabela vendas (com UNIQUE KEY)
--
-- NOTA: A migration verifica se a coluna já existe antes de adicionar
-- =====================================================
