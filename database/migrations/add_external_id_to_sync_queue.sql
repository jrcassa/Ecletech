-- =====================================================
-- Migration: Adicionar external_id à fila de sincronização
-- Descrição: Permite sincronização CRM -> Ecletech de registros que ainda não existem localmente
-- Data: 2025-11-15
-- =====================================================

-- Adiciona campo external_id na fila de sincronização
ALTER TABLE `crm_sync_queue`
ADD COLUMN `external_id` VARCHAR(100) NULL COMMENT 'ID no CRM (para sync CRM -> Ecletech)' AFTER `id_registro`,
ADD INDEX `idx_external_id` (`external_id`);

-- Permite id_registro ser NULL (quando sincronizando CRM -> Ecletech e registro ainda não existe)
ALTER TABLE `crm_sync_queue`
MODIFY COLUMN `id_registro` INT NULL COMMENT 'ID do registro na tabela local (NULL se ainda não existe)';

-- Atualiza comentário da tabela
ALTER TABLE `crm_sync_queue`
COMMENT='Fila de sincronização CRM (processada pelo cron). external_id é usado para sync CRM->Ecletech';
