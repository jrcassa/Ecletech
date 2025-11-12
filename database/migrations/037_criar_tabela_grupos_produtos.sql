-- =====================================================
-- Migration: Criar tabela de grupos de produtos
-- Descrição: Tabela para gerenciamento de grupos/categorias de produtos
-- Data: 2025-11-12
-- =====================================================

CREATE TABLE IF NOT EXISTS `grupos_produtos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_id` VARCHAR(50) DEFAULT NULL COMMENT 'ID do sistema externo',
  `nome` VARCHAR(200) NOT NULL COMMENT 'Nome do grupo de produtos',
  `descricao` TEXT DEFAULT NULL COMMENT 'Descrição do grupo',

  -- Campos padrão do sistema
  `ativo` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Grupo ativo/inativo',
  `cadastrado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de cadastro',
  `modificado_em` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de modificação',
  `deletado_em` DATETIME NULL DEFAULT NULL COMMENT 'Data de exclusão (soft delete)',

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_external_id` (`external_id`),
  UNIQUE KEY `uk_nome` (`nome`),
  KEY `idx_nome` (`nome`),
  KEY `idx_ativo` (`ativo`),
  KEY `idx_deletado_em` (`deletado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cadastro de grupos de produtos';

-- =====================================================
-- Inserir permissões para o módulo de grupos de produtos
-- =====================================================

INSERT IGNORE INTO `colaborador_permissions` (`nome`, `codigo`, `descricao`, `modulo`, `ativo`, `criado_em`) VALUES
('Visualizar Grupos de Produtos', 'grupos_produtos.visualizar', 'Permite visualizar a lista de grupos de produtos e detalhes', 'grupos_produtos', 1, NOW()),
('Criar Grupos de Produtos', 'grupos_produtos.criar', 'Permite cadastrar novos grupos de produtos', 'grupos_produtos', 1, NOW()),
('Editar Grupos de Produtos', 'grupos_produtos.editar', 'Permite editar informações dos grupos de produtos', 'grupos_produtos', 1, NOW()),
('Deletar Grupos de Produtos', 'grupos_produtos.deletar', 'Permite remover grupos de produtos (soft delete)', 'grupos_produtos', 1, NOW());

-- =====================================================
-- Atribuir permissões aos roles
-- =====================================================

-- Obter IDs das permissões de grupos de produtos
SET @perm_grupos_produtos_visualizar = (SELECT id FROM colaborador_permissions WHERE codigo = 'grupos_produtos.visualizar');
SET @perm_grupos_produtos_criar = (SELECT id FROM colaborador_permissions WHERE codigo = 'grupos_produtos.criar');
SET @perm_grupos_produtos_editar = (SELECT id FROM colaborador_permissions WHERE codigo = 'grupos_produtos.editar');
SET @perm_grupos_produtos_deletar = (SELECT id FROM colaborador_permissions WHERE codigo = 'grupos_produtos.deletar');

-- Obter IDs dos roles
SET @role_superadmin = (SELECT id FROM colaborador_roles WHERE codigo = 'superadmin_full');
SET @role_admin_full = (SELECT id FROM colaborador_roles WHERE codigo = 'admin_full');
SET @role_gerente = (SELECT id FROM colaborador_roles WHERE codigo = 'gerente_usuarios');
SET @role_full_access = (SELECT id FROM colaborador_roles WHERE codigo = 'full_access_nivel_2');

-- Atribuir todas as permissões ao Super Admin (role_id = 1)
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_superadmin, @perm_grupos_produtos_visualizar, NOW()),
(@role_superadmin, @perm_grupos_produtos_criar, NOW()),
(@role_superadmin, @perm_grupos_produtos_editar, NOW()),
(@role_superadmin, @perm_grupos_produtos_deletar, NOW());

-- Atribuir todas as permissões ao Admin Full Access (role_id = 2)
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_admin_full, @perm_grupos_produtos_visualizar, NOW()),
(@role_admin_full, @perm_grupos_produtos_criar, NOW()),
(@role_admin_full, @perm_grupos_produtos_editar, NOW()),
(@role_admin_full, @perm_grupos_produtos_deletar, NOW());

-- Atribuir permissões de visualizar e editar ao Gerente (role_id = 3)
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_gerente, @perm_grupos_produtos_visualizar, NOW()),
(@role_gerente, @perm_grupos_produtos_editar, NOW());

-- Atribuir todas as permissões ao Full Access Nivel 2 (role_id = 6)
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_full_access, @perm_grupos_produtos_visualizar, NOW()),
(@role_full_access, @perm_grupos_produtos_criar, NOW()),
(@role_full_access, @perm_grupos_produtos_editar, NOW()),
(@role_full_access, @perm_grupos_produtos_deletar, NOW());
