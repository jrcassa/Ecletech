-- =====================================================
-- Migration: Adicionar coluna external_id em TODAS as tabelas CRM
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

-- 4. Adiciona external_id na tabela LOJA_INFORMACOES
ALTER TABLE `loja_informacoes`
ADD COLUMN `external_id` VARCHAR(50) NULL COMMENT 'ID da loja no sistema externo (CRM)' AFTER `id`,
ADD UNIQUE KEY `uk_loja_informacoes_external_id` (`external_id`);

-- 5. Adiciona external_id na tabela CRM_SYNC_QUEUE
ALTER TABLE `crm_sync_queue`
ADD COLUMN `external_id` VARCHAR(100) NULL COMMENT 'ID no CRM (para sync CRM -> Ecletech)' AFTER `id_registro`,
ADD INDEX `idx_external_id` (`external_id`);

-- 6. Permite id_registro ser NULL na CRM_SYNC_QUEUE (quando registro ainda não existe localmente)
ALTER TABLE `crm_sync_queue`
MODIFY COLUMN `id_registro` INT NULL COMMENT 'ID do registro na tabela local (NULL se ainda não existe)';

-- =====================================================
-- VERIFICAÇÃO (execute após as alterações acima)
-- =====================================================

-- Verifica se as colunas foram criadas
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    DATA_TYPE,
    CHARACTER_MAXIMUM_LENGTH,
    IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('clientes', 'produtos', 'vendas', 'loja_informacoes', 'crm_sync_queue')
  AND COLUMN_NAME = 'external_id'
ORDER BY TABLE_NAME;

-- Se retornar 5 linhas, tudo está correto! ✅

-- Verifica também se id_registro ficou nullable
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    IS_NULLABLE,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'crm_sync_queue'
  AND COLUMN_NAME = 'id_registro';

-- Deve mostrar IS_NULLABLE = YES ✅
