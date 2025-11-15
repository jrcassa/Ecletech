-- =====================================================
-- Migration: Adicionar coluna external_id
-- EXECUTE ESTE ARQUIVO MANUALMENTE NO PHPMYADMIN
-- =====================================================

-- 1. Adiciona external_id na tabela CLIENTES
ALTER TABLE `clientes`
ADD COLUMN `external_id` VARCHAR(50) NULL COMMENT 'ID do sistema externo (CRM)' AFTER `id`,
ADD UNIQUE KEY `uk_clientes_external_id` (`external_id`);

-- 2. Adiciona external_id na tabela PRODUTOS
ALTER TABLE `produtos`
ADD COLUMN `external_id` VARCHAR(50) NULL COMMENT 'ID do sistema externo (CRM)' AFTER `id`,
ADD UNIQUE KEY `uk_produtos_external_id` (`external_id`);

-- 3. Adiciona external_id na tabela VENDAS
ALTER TABLE `vendas`
ADD COLUMN `external_id` VARCHAR(50) NULL COMMENT 'ID do sistema externo (CRM)' AFTER `id`,
ADD UNIQUE KEY `uk_vendas_external_id` (`external_id`);

-- =====================================================
-- VERIFICAÇÃO (execute após as alterações acima)
-- =====================================================

-- Verifica se as colunas foram criadas
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    DATA_TYPE,
    CHARACTER_MAXIMUM_LENGTH
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('clientes', 'produtos', 'vendas')
  AND COLUMN_NAME = 'external_id'
ORDER BY TABLE_NAME;

-- Se retornar 3 linhas, tudo está correto! ✅
