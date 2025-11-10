-- Migration: Criar tabela de níveis de administrador
-- Data: 2025-11-10

CREATE TABLE IF NOT EXISTS `administrador_niveis` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(100) NOT NULL COMMENT 'Nome do nível',
    `codigo` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Código único do nível',
    `descricao` TEXT NULL COMMENT 'Descrição do nível',
    `ordem` INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
    `ativo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Status ativo/inativo',
    `criado_em` DATETIME NOT NULL COMMENT 'Data de criação',
    `atualizado_em` DATETIME NULL COMMENT 'Data de atualização',
    `deletado_em` DATETIME NULL COMMENT 'Data de exclusão (soft delete)',
    INDEX `idx_codigo` (`codigo`),
    INDEX `idx_ativo` (`ativo`),
    INDEX `idx_ordem` (`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Níveis de acesso de administradores';

-- Insere níveis padrão
INSERT INTO `administrador_niveis` (`nome`, `codigo`, `descricao`, `ordem`, `ativo`, `criado_em`) VALUES
('Super Administrador', 'superadmin', 'Acesso total ao sistema', 1, 1, NOW()),
('Administrador', 'admin', 'Acesso administrativo completo', 2, 1, NOW()),
('Gerente', 'gerente', 'Acesso de gerenciamento', 3, 1, NOW()),
('Operador', 'operador', 'Acesso operacional', 4, 1, NOW()),
('Visualizador', 'visualizador', 'Acesso apenas leitura', 5, 1, NOW());
