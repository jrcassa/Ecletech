-- =====================================================
-- Migration: Tabelas CRM
-- Descrição: Cria as tabelas necessárias para integração CRM
-- Data: 2025-11-15
-- =====================================================

-- Tabela de configurações de integração CRM
CREATE TABLE IF NOT EXISTS `crm_integracoes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_loja` INT NOT NULL,
    `provider` VARCHAR(50) NOT NULL COMMENT 'Nome do provider (gestao_click, pipedrive, bling, etc)',
    `credenciais` TEXT NOT NULL COMMENT 'Credenciais criptografadas (AES-256-CBC)',
    `configuracoes` JSON NULL COMMENT 'Configurações adicionais específicas do provider',
    `ativo` TINYINT(1) DEFAULT 1 COMMENT '1 = ativo, 0 = inativo',
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deletado_em` TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY `unique_loja` (`id_loja`),
    INDEX `idx_provider` (`provider`),
    INDEX `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Configurações de integração com CRM por loja';

-- Tabela de fila de sincronização
CREATE TABLE IF NOT EXISTS `crm_sync_queue` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_loja` INT NOT NULL,
    `entidade` VARCHAR(50) NOT NULL COMMENT 'Tipo de entidade (cliente, produto, venda, atividade, etc)',
    `id_registro` INT NOT NULL COMMENT 'ID do registro na tabela local',
    `direcao` ENUM('ecletech_para_crm', 'crm_para_ecletech') NOT NULL,
    `prioridade` INT DEFAULT 0 COMMENT 'Maior = mais importante',
    `tentativas` INT DEFAULT 0 COMMENT 'Número de tentativas de processamento',
    `processado` TINYINT(1) DEFAULT 0 COMMENT '0 = pendente, 1 = processado',
    `erro` TEXT NULL COMMENT 'Mensagem de erro se houver',
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `processado_em` TIMESTAMP NULL DEFAULT NULL,
    `deletado_em` TIMESTAMP NULL DEFAULT NULL,
    INDEX `idx_processar` (`processado`, `id_loja`, `prioridade` DESC, `criado_em` ASC),
    INDEX `idx_entidade` (`entidade`, `id_registro`),
    INDEX `idx_loja` (`id_loja`),
    INDEX `idx_processado_em` (`processado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Fila de sincronização CRM (processada pelo cron)';

-- Tabela de logs de sincronização
CREATE TABLE IF NOT EXISTS `crm_sync_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_loja` INT NOT NULL,
    `entidade` VARCHAR(50) NOT NULL,
    `id_registro` INT NOT NULL COMMENT 'ID do registro no Ecletech',
    `direcao` ENUM('ecletech_para_crm', 'crm_para_ecletech') NOT NULL,
    `status` ENUM('sucesso', 'erro', 'alerta') NOT NULL,
    `mensagem` TEXT NULL,
    `dados_enviados` JSON NULL COMMENT 'Dados que foram enviados',
    `dados_recebidos` JSON NULL COMMENT 'Dados que foram recebidos',
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_entidade_registro` (`entidade`, `id_registro`),
    INDEX `idx_loja` (`id_loja`),
    INDEX `idx_status` (`status`),
    INDEX `idx_criado_em` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico de operações de sincronização CRM';

-- =====================================================
-- Adiciona campo external_id nas tabelas existentes
-- =====================================================

-- Clientes
ALTER TABLE `clientes`
ADD COLUMN IF NOT EXISTS `external_id` VARCHAR(100) NULL COMMENT 'ID no CRM externo',
ADD INDEX `idx_external_id` (`external_id`);

-- Produtos
ALTER TABLE `produtos`
ADD COLUMN IF NOT EXISTS `external_id` VARCHAR(100) NULL COMMENT 'ID no CRM externo',
ADD INDEX `idx_external_id` (`external_id`);

-- Vendas (ajustar nome da tabela conforme necessário)
ALTER TABLE `vendas`
ADD COLUMN IF NOT EXISTS `external_id` VARCHAR(100) NULL COMMENT 'ID no CRM externo',
ADD INDEX `idx_external_id` (`external_id`);

-- Atividades (se existir)
-- ALTER TABLE `atividades`
-- ADD COLUMN IF NOT EXISTS `external_id` VARCHAR(100) NULL COMMENT 'ID no CRM externo',
-- ADD INDEX `idx_external_id` (`external_id`);

-- =====================================================
-- Triggers para limpeza automática de logs antigos
-- (Opcional - pode ser feito via cron)
-- =====================================================

-- Event scheduler precisa estar ativado: SET GLOBAL event_scheduler = ON;

-- DELIMITER $$
-- CREATE EVENT IF NOT EXISTS `evt_limpar_crm_sync_log`
-- ON SCHEDULE EVERY 1 DAY
-- STARTS CURRENT_TIMESTAMP
-- DO
-- BEGIN
--     DELETE FROM crm_sync_log WHERE criado_em < DATE_SUB(NOW(), INTERVAL 30 DAY);
-- END$$
-- DELIMITER ;

-- =====================================================
-- Dados de exemplo (comentado - descomente para testar)
-- =====================================================

-- INSERT INTO crm_integracoes (id_loja, provider, credenciais, ativo)
-- VALUES (
--     1,
--     'gestao_click',
--     'CREDENCIAIS_CRIPTOGRAFADAS_AQUI',
--     1
-- );
