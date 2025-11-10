-- Migration: Criar tabela de roles (funções) de administrador
-- Data: 2025-11-10

CREATE TABLE IF NOT EXISTS `administrador_roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(100) NOT NULL COMMENT 'Nome da role',
    `codigo` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Código único da role',
    `descricao` TEXT NULL COMMENT 'Descrição da role',
    `nivel_id` INT UNSIGNED NOT NULL COMMENT 'ID do nível associado',
    `ativo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Status ativo/inativo',
    `criado_em` DATETIME NOT NULL COMMENT 'Data de criação',
    `atualizado_em` DATETIME NULL COMMENT 'Data de atualização',
    `deletado_em` DATETIME NULL COMMENT 'Data de exclusão (soft delete)',
    INDEX `idx_codigo` (`codigo`),
    INDEX `idx_nivel_id` (`nivel_id`),
    INDEX `idx_ativo` (`ativo`),
    CONSTRAINT `fk_roles_nivel`
        FOREIGN KEY (`nivel_id`)
        REFERENCES `administrador_niveis` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Roles (funções) de administradores';

-- Insere roles padrão
INSERT INTO `administrador_roles` (`nome`, `codigo`, `descricao`, `nivel_id`, `ativo`, `criado_em`) VALUES
('Super Admin Full Access', 'superadmin_full', 'Acesso completo sem restrições', 1, 1, NOW()),
('Admin Full Access', 'admin_full', 'Acesso administrativo completo', 2, 1, NOW()),
('Gerente de Usuários', 'gerente_usuarios', 'Gerenciamento de usuários', 3, 1, NOW()),
('Operador de Sistema', 'operador_sistema', 'Operações básicas do sistema', 4, 1, NOW()),
('Visualizador de Relatórios', 'visualizador_relatorios', 'Visualização de relatórios', 5, 1, NOW());
