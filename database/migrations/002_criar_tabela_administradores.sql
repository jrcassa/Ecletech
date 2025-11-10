-- Migration: Criar tabela de administradores
-- Data: 2025-11-10

CREATE TABLE IF NOT EXISTS `administradores` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(150) NOT NULL COMMENT 'Nome completo do administrador',
    `email` VARCHAR(150) NOT NULL UNIQUE COMMENT 'Email único',
    `senha` VARCHAR(255) NOT NULL COMMENT 'Senha criptografada',
    `nivel_id` INT UNSIGNED NOT NULL COMMENT 'ID do nível de acesso',
    `ativo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Status ativo/inativo',
    `ultimo_login` DATETIME NULL COMMENT 'Data do último login',
    `criado_em` DATETIME NOT NULL COMMENT 'Data de criação',
    `atualizado_em` DATETIME NULL COMMENT 'Data de atualização',
    `deletado_em` DATETIME NULL COMMENT 'Data de exclusão (soft delete)',
    INDEX `idx_email` (`email`),
    INDEX `idx_nivel_id` (`nivel_id`),
    INDEX `idx_ativo` (`ativo`),
    CONSTRAINT `fk_administradores_nivel`
        FOREIGN KEY (`nivel_id`)
        REFERENCES `administrador_niveis` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Administradores do sistema';

-- Cria tabela de tokens
CREATE TABLE IF NOT EXISTS `administrador_tokens` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT UNSIGNED NOT NULL COMMENT 'ID do administrador',
    `token` TEXT NOT NULL COMMENT 'Token JWT',
    `tipo` ENUM('access', 'refresh') NOT NULL DEFAULT 'refresh' COMMENT 'Tipo do token',
    `revogado` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Se o token foi revogado',
    `expira_em` DATETIME NOT NULL COMMENT 'Data de expiração',
    `criado_em` DATETIME NOT NULL COMMENT 'Data de criação',
    INDEX `idx_usuario_id` (`usuario_id`),
    INDEX `idx_tipo` (`tipo`),
    INDEX `idx_revogado` (`revogado`),
    INDEX `idx_expira_em` (`expira_em`),
    CONSTRAINT `fk_tokens_usuario`
        FOREIGN KEY (`usuario_id`)
        REFERENCES `administradores` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tokens JWT de administradores';

-- Insere administrador padrão
-- Senha padrão: Admin@123
INSERT INTO `administradores` (`nome`, `email`, `senha`, `nivel_id`, `ativo`, `criado_em`) VALUES
('Administrador', 'admin@ecletech.com', '$argon2id$v=19$m=65536,t=4,p=1$MzdlNGJhYzJmOWQ1ZTM3Zg$vZHqKvV5V8xFJH0c9F7qXqY0YQZhGf7kF8L6JdF8L6I', 1, 1, NOW());
