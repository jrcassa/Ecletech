-- Migration: Criar tabela de tokens CSRF
-- Data: 2025-11-10

CREATE TABLE IF NOT EXISTS `csrf_tokens` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `token` VARCHAR(64) NOT NULL UNIQUE COMMENT 'Token CSRF único',
    `session_id` VARCHAR(255) NULL COMMENT 'ID da sessão PHP',
    `usuario_id` INT UNSIGNED NULL COMMENT 'ID do usuário (se autenticado)',
    `ip_address` VARCHAR(45) NULL COMMENT 'Endereço IP do cliente',
    `user_agent` VARCHAR(500) NULL COMMENT 'User Agent do navegador',
    `usado` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indica se o token já foi usado',
    `criado_em` DATETIME NOT NULL COMMENT 'Data de criação do token',
    `expira_em` DATETIME NOT NULL COMMENT 'Data de expiração do token',
    `usado_em` DATETIME NULL COMMENT 'Data em que o token foi usado',
    INDEX `idx_token` (`token`),
    INDEX `idx_session_id` (`session_id`),
    INDEX `idx_usuario_id` (`usuario_id`),
    INDEX `idx_expira_em` (`expira_em`),
    INDEX `idx_criado_em` (`criado_em`),
    INDEX `idx_usado` (`usado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tokens CSRF para validação de requisições';

-- Criar evento para limpeza automática de tokens expirados (executado diariamente)
DELIMITER $$

CREATE EVENT IF NOT EXISTS `limpar_csrf_tokens_expirados`
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    -- Remove tokens expirados há mais de 7 dias
    DELETE FROM `csrf_tokens`
    WHERE `expira_em` < DATE_SUB(NOW(), INTERVAL 7 DAY);

    -- Remove tokens usados há mais de 1 dia
    DELETE FROM `csrf_tokens`
    WHERE `usado` = 1 AND `usado_em` < DATE_SUB(NOW(), INTERVAL 1 DAY);
END$$

DELIMITER ;
