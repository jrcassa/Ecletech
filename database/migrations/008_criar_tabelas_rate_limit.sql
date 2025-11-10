-- Migration: Criar tabelas para Rate Limiting
-- Data: 2025-11-10

-- Tabela para armazenar bloqueios de rate limit
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `identificador` VARCHAR(64) NOT NULL UNIQUE COMMENT 'Hash do identificador (IP ou usuário)',
    `bloqueado_ate` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Timestamp até quando está bloqueado',
    `criado_em` DATETIME NOT NULL COMMENT 'Data de criação do registro',
    `atualizado_em` DATETIME NOT NULL COMMENT 'Data da última atualização',
    INDEX `idx_identificador` (`identificador`),
    INDEX `idx_bloqueado_ate` (`bloqueado_ate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Controle de bloqueios para rate limiting';

-- Tabela para armazenar requisições
CREATE TABLE IF NOT EXISTS `rate_limit_requisicoes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `identificador` VARCHAR(64) NOT NULL COMMENT 'Hash do identificador (IP ou usuário)',
    `timestamp` INT UNSIGNED NOT NULL COMMENT 'Timestamp da requisição',
    `criado_em` DATETIME NOT NULL COMMENT 'Data de criação do registro',
    INDEX `idx_identificador` (`identificador`),
    INDEX `idx_timestamp` (`timestamp`),
    INDEX `idx_identificador_timestamp` (`identificador`, `timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de requisições para rate limiting';

-- Criar evento para limpeza automática de registros antigos (executado diariamente)
DELIMITER $$

CREATE EVENT IF NOT EXISTS `limpar_rate_limit_registros_antigos`
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    -- Remove requisições mais antigas que 24 horas
    DELETE FROM `rate_limit_requisicoes`
    WHERE `timestamp` < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 24 HOUR));

    -- Remove bloqueios expirados há mais de 1 dia
    DELETE FROM `rate_limits`
    WHERE `bloqueado_ate` > 0
    AND `bloqueado_ate` < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 DAY));
END$$

DELIMITER ;
