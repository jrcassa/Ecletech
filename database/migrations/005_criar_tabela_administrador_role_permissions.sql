-- Migration: Criar tabela de relação entre roles e permissões
-- Data: 2025-11-10

CREATE TABLE IF NOT EXISTS `administrador_role_permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT UNSIGNED NOT NULL COMMENT 'ID da role',
    `permission_id` INT UNSIGNED NOT NULL COMMENT 'ID da permissão',
    `criado_em` DATETIME NOT NULL COMMENT 'Data de criação',
    UNIQUE KEY `unique_role_permission` (`role_id`, `permission_id`),
    INDEX `idx_role_id` (`role_id`),
    INDEX `idx_permission_id` (`permission_id`),
    CONSTRAINT `fk_role_permissions_role`
        FOREIGN KEY (`role_id`)
        REFERENCES `administrador_roles` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `fk_role_permissions_permission`
        FOREIGN KEY (`permission_id`)
        REFERENCES `administrador_permissions` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relação entre roles e permissões';

-- Cria tabelas de auditoria
CREATE TABLE IF NOT EXISTS `auditoria` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT UNSIGNED NULL COMMENT 'ID do usuário que realizou a ação',
    `acao` VARCHAR(50) NOT NULL COMMENT 'Tipo de ação (criar, atualizar, deletar, etc)',
    `tabela` VARCHAR(100) NOT NULL COMMENT 'Nome da tabela afetada',
    `registro_id` INT UNSIGNED NULL COMMENT 'ID do registro afetado',
    `dados_antigos` JSON NULL COMMENT 'Dados antes da alteração',
    `dados_novos` JSON NULL COMMENT 'Dados depois da alteração',
    `ip` VARCHAR(45) NULL COMMENT 'Endereço IP',
    `user_agent` TEXT NULL COMMENT 'User Agent do navegador',
    `criado_em` DATETIME NOT NULL COMMENT 'Data de criação',
    INDEX `idx_usuario_id` (`usuario_id`),
    INDEX `idx_acao` (`acao`),
    INDEX `idx_tabela` (`tabela`),
    INDEX `idx_registro_id` (`registro_id`),
    INDEX `idx_criado_em` (`criado_em`),
    CONSTRAINT `fk_auditoria_usuario`
        FOREIGN KEY (`usuario_id`)
        REFERENCES `administradores` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de auditoria do sistema';

CREATE TABLE IF NOT EXISTS `auditoria_login` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT UNSIGNED NULL COMMENT 'ID do usuário',
    `acao` VARCHAR(50) NOT NULL DEFAULT 'login' COMMENT 'Tipo de ação (login, logout)',
    `sucesso` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Se a ação foi bem sucedida',
    `ip` VARCHAR(45) NULL COMMENT 'Endereço IP',
    `user_agent` TEXT NULL COMMENT 'User Agent do navegador',
    `criado_em` DATETIME NOT NULL COMMENT 'Data de criação',
    INDEX `idx_usuario_id` (`usuario_id`),
    INDEX `idx_sucesso` (`sucesso`),
    INDEX `idx_criado_em` (`criado_em`),
    CONSTRAINT `fk_auditoria_login_usuario`
        FOREIGN KEY (`usuario_id`)
        REFERENCES `administradores` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de login/logout';

CREATE TABLE IF NOT EXISTS `auditoria_requisicoes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `metodo` VARCHAR(10) NOT NULL COMMENT 'Método HTTP',
    `uri` VARCHAR(500) NOT NULL COMMENT 'URI da requisição',
    `ip` VARCHAR(45) NULL COMMENT 'Endereço IP',
    `user_agent` TEXT NULL COMMENT 'User Agent do navegador',
    `payload` LONGTEXT NULL COMMENT 'Dados enviados na requisição',
    `criado_em` DATETIME NOT NULL COMMENT 'Data de criação',
    INDEX `idx_metodo` (`metodo`),
    INDEX `idx_ip` (`ip`),
    INDEX `idx_criado_em` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de requisições HTTP';

-- Atribui todas as permissões ao Super Admin
INSERT INTO `administrador_role_permissions` (`role_id`, `permission_id`, `criado_em`)
SELECT 1, id, NOW() FROM `administrador_permissions` WHERE ativo = 1;

-- Atribui permissões básicas ao Admin
INSERT INTO `administrador_role_permissions` (`role_id`, `permission_id`, `criado_em`)
SELECT 2, id, NOW() FROM `administrador_permissions`
WHERE codigo LIKE 'usuarios.%' OR codigo LIKE 'relatorios.%' AND ativo = 1;

-- Atribui permissões ao Gerente
INSERT INTO `administrador_role_permissions` (`role_id`, `permission_id`, `criado_em`)
SELECT 3, id, NOW() FROM `administrador_permissions`
WHERE codigo IN ('usuarios.visualizar', 'usuarios.criar', 'usuarios.editar', 'relatorios.visualizar') AND ativo = 1;

-- Atribui permissões ao Operador
INSERT INTO `administrador_role_permissions` (`role_id`, `permission_id`, `criado_em`)
SELECT 4, id, NOW() FROM `administrador_permissions`
WHERE codigo IN ('usuarios.visualizar', 'relatorios.visualizar') AND ativo = 1;

-- Atribui permissões ao Visualizador
INSERT INTO `administrador_role_permissions` (`role_id`, `permission_id`, `criado_em`)
SELECT 5, id, NOW() FROM `administrador_permissions`
WHERE codigo = 'relatorios.visualizar' AND ativo = 1;
