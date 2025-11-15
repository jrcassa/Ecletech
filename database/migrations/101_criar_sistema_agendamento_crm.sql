-- =====================================================
-- Migration: Sistema de Agendamento CRM Completo
-- Descrição: Cria tabelas para agendamento automático de sincronização
-- Data: 2025-11-15
-- =====================================================

-- =====================================================
-- 1. Tabela de Agendamentos de Sincronização
-- =====================================================
CREATE TABLE IF NOT EXISTS `crm_sync_schedules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `id_loja` INT UNSIGNED NOT NULL COMMENT 'ID da loja (ou external_id se configurado)',
  `entidade` ENUM('cliente', 'produto', 'venda') NOT NULL COMMENT 'Entidade a sincronizar',
  `direcao` ENUM('crm_para_ecletech', 'ecletech_para_crm', 'bidirecional') NOT NULL COMMENT 'Direção da sincronização',

  -- Configurações de execução
  `batch_size` INT UNSIGNED NOT NULL DEFAULT 10 COMMENT 'Quantidade de registros por lote',
  `frequencia_minutos` INT UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Frequência de execução (1, 5, 15, 60, etc)',
  `horario_inicio` TIME NULL COMMENT 'Horário de início permitido (NULL = sempre)',
  `horario_fim` TIME NULL COMMENT 'Horário de fim permitido (NULL = sempre)',

  -- Controle de execução
  `ativo` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Schedule ativo/inativo',
  `ultima_execucao` DATETIME NULL COMMENT 'Última vez que foi executado',
  `proxima_execucao` DATETIME NULL COMMENT 'Próxima execução agendada',
  `executando` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Flag para evitar execuções simultâneas',

  -- Estatísticas
  `total_execucoes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total de execuções realizadas',
  `total_registros_processados` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total de registros processados',
  `total_erros` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total de erros',

  -- Auditoria
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `deletado_em` DATETIME NULL COMMENT 'Soft delete',

  -- Índices
  INDEX `idx_loja_entidade` (`id_loja`, `entidade`),
  INDEX `idx_proxima_execucao` (`proxima_execucao`, `ativo`),
  INDEX `idx_ativo` (`ativo`),
  INDEX `idx_deletado_em` (`deletado_em`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Agendamentos automáticos de sincronização CRM';

-- =====================================================
-- 2. Atualizar tabela crm_sync_queue
-- =====================================================

-- Adiciona colunas para sistema de batches
ALTER TABLE `crm_sync_queue`
ADD COLUMN `batch_id` VARCHAR(36) NULL COMMENT 'ID do lote (UUID)' AFTER `id`,
ADD COLUMN `schedule_id` INT UNSIGNED NULL COMMENT 'ID do agendamento que criou este item' AFTER `batch_id`,
ADD COLUMN `status` ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending' AFTER `direcao`,
ADD COLUMN `started_at` DATETIME NULL COMMENT 'Quando começou o processamento',
ADD COLUMN `completed_at` DATETIME NULL COMMENT 'Quando completou',
ADD COLUMN `tempo_processamento_ms` INT UNSIGNED NULL COMMENT 'Tempo de processamento em milissegundos',
ADD COLUMN `proximo_retry` DATETIME NULL COMMENT 'Quando tentar novamente (em caso de falha)',
ADD COLUMN `erro_mensagem` TEXT NULL COMMENT 'Mensagem de erro detalhada';

-- Adiciona índices para performance
ALTER TABLE `crm_sync_queue`
ADD INDEX `idx_batch_id` (`batch_id`),
ADD INDEX `idx_schedule_id` (`schedule_id`),
ADD INDEX `idx_status` (`status`),
ADD INDEX `idx_proximo_retry` (`proximo_retry`);

-- Adiciona foreign key para schedule
ALTER TABLE `crm_sync_queue`
ADD CONSTRAINT `fk_queue_schedule`
FOREIGN KEY (`schedule_id`)
REFERENCES `crm_sync_schedules`(`id`)
ON DELETE SET NULL;

-- =====================================================
-- 3. Tabela de Logs de Sincronização
-- =====================================================
CREATE TABLE IF NOT EXISTS `crm_sync_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `batch_id` VARCHAR(36) NOT NULL COMMENT 'ID do lote processado',
  `schedule_id` INT UNSIGNED NULL COMMENT 'ID do agendamento',
  `id_loja` INT UNSIGNED NOT NULL,
  `entidade` ENUM('cliente', 'produto', 'venda') NOT NULL,
  `direcao` ENUM('crm_para_ecletech', 'ecletech_para_crm') NOT NULL,

  -- Estatísticas do batch
  `total_registros` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total de registros no batch',
  `registros_processados` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Registros processados com sucesso',
  `registros_criados` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Novos registros criados',
  `registros_atualizados` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Registros atualizados',
  `registros_com_erro` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Registros com erro',

  -- Tempo e performance
  `inicio` DATETIME NOT NULL COMMENT 'Início do processamento',
  `fim` DATETIME NULL COMMENT 'Fim do processamento',
  `tempo_total_ms` INT UNSIGNED NULL COMMENT 'Tempo total em milissegundos',

  -- Status e erros
  `status` ENUM('processing', 'completed', 'failed', 'partial') NOT NULL DEFAULT 'processing',
  `erro_geral` TEXT NULL COMMENT 'Erro geral do batch (se houver)',

  -- Dados adicionais
  `detalhes` JSON NULL COMMENT 'Detalhes adicionais do processamento',

  -- Auditoria
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- Índices
  INDEX `idx_batch_id` (`batch_id`),
  INDEX `idx_schedule_id` (`schedule_id`),
  INDEX `idx_loja_entidade` (`id_loja`, `entidade`),
  INDEX `idx_inicio` (`inicio`),
  INDEX `idx_status` (`status`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Logs detalhados de sincronização CRM';

-- =====================================================
-- RESUMO DA MIGRATION
-- =====================================================
-- 1. ✅ Criada tabela crm_sync_schedules (agendamentos)
-- 2. ✅ Atualizada tabela crm_sync_queue (batches e status)
-- 3. ✅ Criada tabela crm_sync_logs (auditoria)
-- =====================================================
