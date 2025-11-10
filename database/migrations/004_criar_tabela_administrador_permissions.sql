-- Migration: Criar tabela de permissões de administrador
-- Data: 2025-11-10

CREATE TABLE IF NOT EXISTS `administrador_permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(100) NOT NULL COMMENT 'Nome da permissão',
    `codigo` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Código único da permissão',
    `descricao` TEXT NULL COMMENT 'Descrição da permissão',
    `modulo` VARCHAR(50) NOT NULL DEFAULT 'geral' COMMENT 'Módulo ao qual a permissão pertence',
    `ativo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Status ativo/inativo',
    `criado_em` DATETIME NOT NULL COMMENT 'Data de criação',
    `atualizado_em` DATETIME NULL COMMENT 'Data de atualização',
    `deletado_em` DATETIME NULL COMMENT 'Data de exclusão (soft delete)',
    INDEX `idx_codigo` (`codigo`),
    INDEX `idx_modulo` (`modulo`),
    INDEX `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Permissões do sistema';

-- Insere permissões padrão

-- Módulo: Usuários
INSERT INTO `administrador_permissions` (`nome`, `codigo`, `descricao`, `modulo`, `ativo`, `criado_em`) VALUES
('Visualizar Usuários', 'usuarios.visualizar', 'Permite visualizar usuários', 'usuarios', 1, NOW()),
('Criar Usuários', 'usuarios.criar', 'Permite criar novos usuários', 'usuarios', 1, NOW()),
('Editar Usuários', 'usuarios.editar', 'Permite editar usuários', 'usuarios', 1, NOW()),
('Deletar Usuários', 'usuarios.deletar', 'Permite deletar usuários', 'usuarios', 1, NOW());

-- Módulo: Administradores
INSERT INTO `administrador_permissions` (`nome`, `codigo`, `descricao`, `modulo`, `ativo`, `criado_em`) VALUES
('Visualizar Administradores', 'admins.visualizar', 'Permite visualizar administradores', 'administradores', 1, NOW()),
('Criar Administradores', 'admins.criar', 'Permite criar novos administradores', 'administradores', 1, NOW()),
('Editar Administradores', 'admins.editar', 'Permite editar administradores', 'administradores', 1, NOW()),
('Deletar Administradores', 'admins.deletar', 'Permite deletar administradores', 'administradores', 1, NOW());

-- Módulo: Níveis
INSERT INTO `administrador_permissions` (`nome`, `codigo`, `descricao`, `modulo`, `ativo`, `criado_em`) VALUES
('Visualizar Níveis', 'niveis.visualizar', 'Permite visualizar níveis', 'niveis', 1, NOW()),
('Criar Níveis', 'niveis.criar', 'Permite criar novos níveis', 'niveis', 1, NOW()),
('Editar Níveis', 'niveis.editar', 'Permite editar níveis', 'niveis', 1, NOW()),
('Deletar Níveis', 'niveis.deletar', 'Permite deletar níveis', 'niveis', 1, NOW());

-- Módulo: Roles
INSERT INTO `administrador_permissions` (`nome`, `codigo`, `descricao`, `modulo`, `ativo`, `criado_em`) VALUES
('Visualizar Roles', 'roles.visualizar', 'Permite visualizar roles', 'roles', 1, NOW()),
('Criar Roles', 'roles.criar', 'Permite criar novos roles', 'roles', 1, NOW()),
('Editar Roles', 'roles.editar', 'Permite editar roles', 'roles', 1, NOW()),
('Deletar Roles', 'roles.deletar', 'Permite deletar roles', 'roles', 1, NOW());

-- Módulo: Permissões
INSERT INTO `administrador_permissions` (`nome`, `codigo`, `descricao`, `modulo`, `ativo`, `criado_em`) VALUES
('Visualizar Permissões', 'permissoes.visualizar', 'Permite visualizar permissões', 'permissoes', 1, NOW()),
('Criar Permissões', 'permissoes.criar', 'Permite criar novas permissões', 'permissoes', 1, NOW()),
('Editar Permissões', 'permissoes.editar', 'Permite editar permissões', 'permissoes', 1, NOW()),
('Deletar Permissões', 'permissoes.deletar', 'Permite deletar permissões', 'permissoes', 1, NOW());

-- Módulo: Configurações
INSERT INTO `administrador_permissions` (`nome`, `codigo`, `descricao`, `modulo`, `ativo`, `criado_em`) VALUES
('Visualizar Configurações', 'config.visualizar', 'Permite visualizar configurações', 'configuracoes', 1, NOW()),
('Editar Configurações', 'config.editar', 'Permite editar configurações', 'configuracoes', 1, NOW());

-- Módulo: Auditoria
INSERT INTO `administrador_permissions` (`nome`, `codigo`, `descricao`, `modulo`, `ativo`, `criado_em`) VALUES
('Visualizar Auditoria', 'auditoria.visualizar', 'Permite visualizar logs de auditoria', 'auditoria', 1, NOW()),
('Deletar Auditoria', 'auditoria.deletar', 'Permite deletar logs de auditoria', 'auditoria', 1, NOW());

-- Módulo: Relatórios
INSERT INTO `administrador_permissions` (`nome`, `codigo`, `descricao`, `modulo`, `ativo`, `criado_em`) VALUES
('Visualizar Relatórios', 'relatorios.visualizar', 'Permite visualizar relatórios', 'relatorios', 1, NOW()),
('Exportar Relatórios', 'relatorios.exportar', 'Permite exportar relatórios', 'relatorios', 1, NOW());
