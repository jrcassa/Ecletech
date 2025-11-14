-- Migration: 093 - Adicionar colunas faltantes em dashboard_templates
-- Descrição: Adiciona colunas ordem, criado_em e atualizado_em se não existirem
-- Data: 2025-01-14

-- Adiciona coluna 'ordem' se não existir
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'dashboard_templates'
AND COLUMN_NAME = 'ordem';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE dashboard_templates ADD COLUMN ordem INT NOT NULL DEFAULT 0 AFTER cor',
    'SELECT "Coluna ordem já existe" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adiciona coluna 'criado_em' se não existir
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'dashboard_templates'
AND COLUMN_NAME = 'criado_em';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE dashboard_templates ADD COLUMN criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_sistema',
    'SELECT "Coluna criado_em já existe" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adiciona coluna 'atualizado_em' se não existir
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'dashboard_templates'
AND COLUMN_NAME = 'atualizado_em';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE dashboard_templates ADD COLUMN atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER criado_em',
    'SELECT "Coluna atualizado_em já existe" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Colunas adicionadas/verificadas em dashboard_templates' AS status;
