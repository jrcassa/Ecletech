-- =====================================================
-- Migration: Criar tabela de transportadoras
-- Descrição: Tabela para gerenciamento de transportadoras (PF e PJ)
-- Data: 2025-11-12
-- =====================================================

CREATE TABLE IF NOT EXISTS `transportadoras` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_id` VARCHAR(50) DEFAULT NULL COMMENT 'ID do sistema externo',
  `tipo_pessoa` ENUM('PF', 'PJ') NOT NULL COMMENT 'Tipo de pessoa: Física ou Jurídica',
  `nome` VARCHAR(200) NOT NULL COMMENT 'Nome fantasia ou nome completo',
  `razao_social` VARCHAR(200) DEFAULT NULL COMMENT 'Razão social (para PJ)',
  `cnpj` VARCHAR(18) DEFAULT NULL COMMENT 'CNPJ (apenas números ou formatado)',
  `inscricao_estadual` VARCHAR(20) DEFAULT NULL COMMENT 'Inscrição estadual',
  `inscricao_municipal` VARCHAR(20) DEFAULT NULL COMMENT 'Inscrição municipal',
  `tipo_contribuinte` VARCHAR(50) DEFAULT NULL COMMENT 'Tipo de contribuinte',
  `cpf` VARCHAR(14) DEFAULT NULL COMMENT 'CPF (apenas números ou formatado)',
  `rg` VARCHAR(20) DEFAULT NULL COMMENT 'RG',
  `data_nascimento` DATE DEFAULT NULL COMMENT 'Data de nascimento (para PF)',
  `telefone` VARCHAR(20) DEFAULT NULL COMMENT 'Telefone principal',
  `celular` VARCHAR(20) DEFAULT NULL COMMENT 'Celular',
  `email` VARCHAR(100) DEFAULT NULL COMMENT 'E-mail',

  -- Campos padrão do sistema
  `ativo` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Transportadora ativa/inativa',
  `cadastrado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de cadastro',
  `modificado_em` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de modificação',
  `deletado_em` DATETIME NULL DEFAULT NULL COMMENT 'Data de exclusão (soft delete)',

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_external_id` (`external_id`),
  UNIQUE KEY `uk_cnpj` (`cnpj`),
  UNIQUE KEY `uk_cpf` (`cpf`),
  KEY `idx_tipo_pessoa` (`tipo_pessoa`),
  KEY `idx_nome` (`nome`),
  KEY `idx_ativo` (`ativo`),
  KEY `idx_email` (`email`),
  KEY `idx_deletado_em` (`deletado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cadastro de transportadoras (PF e PJ)';

-- =====================================================
-- Inserir permissões para o módulo de transportadoras
-- =====================================================

INSERT INTO `colaborador_permissions` (`nome`, `codigo`, `descricao`, `modulo`, `ativo`, `criado_em`) VALUES
('Visualizar Transportadoras', 'transportadora.visualizar', 'Permite visualizar a lista de transportadoras e detalhes', 'transportadora', 1, NOW()),
('Criar Transportadoras', 'transportadora.criar', 'Permite cadastrar novas transportadoras', 'transportadora', 1, NOW()),
('Editar Transportadoras', 'transportadora.editar', 'Permite editar informações das transportadoras', 'transportadora', 1, NOW()),
('Deletar Transportadoras', 'transportadora.deletar', 'Permite remover transportadoras (soft delete)', 'transportadora', 1, NOW());

-- =====================================================
-- Atribuir permissões aos roles
-- =====================================================

-- Obter IDs das permissões de transportadoras
SET @perm_transportadora_visualizar = (SELECT id FROM colaborador_permissions WHERE codigo = 'transportadora.visualizar');
SET @perm_transportadora_criar = (SELECT id FROM colaborador_permissions WHERE codigo = 'transportadora.criar');
SET @perm_transportadora_editar = (SELECT id FROM colaborador_permissions WHERE codigo = 'transportadora.editar');
SET @perm_transportadora_deletar = (SELECT id FROM colaborador_permissions WHERE codigo = 'transportadora.deletar');

-- Obter IDs dos roles
SET @role_superadmin = (SELECT id FROM colaborador_roles WHERE codigo = 'superadmin_full');
SET @role_admin_full = (SELECT id FROM colaborador_roles WHERE codigo = 'admin_full');
SET @role_gerente = (SELECT id FROM colaborador_roles WHERE codigo = 'gerente_usuarios');
SET @role_full_access = (SELECT id FROM colaborador_roles WHERE codigo = 'full_access_nivel_2');

-- Atribuir todas as permissões ao Super Admin (role_id = 1)
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_superadmin, @perm_transportadora_visualizar, NOW()),
(@role_superadmin, @perm_transportadora_criar, NOW()),
(@role_superadmin, @perm_transportadora_editar, NOW()),
(@role_superadmin, @perm_transportadora_deletar, NOW());

-- Atribuir todas as permissões ao Admin Full Access (role_id = 2)
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_admin_full, @perm_transportadora_visualizar, NOW()),
(@role_admin_full, @perm_transportadora_criar, NOW()),
(@role_admin_full, @perm_transportadora_editar, NOW()),
(@role_admin_full, @perm_transportadora_deletar, NOW());

-- Atribuir permissões de visualizar e editar ao Gerente (role_id = 3)
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_gerente, @perm_transportadora_visualizar, NOW()),
(@role_gerente, @perm_transportadora_editar, NOW());

-- Atribuir todas as permissões ao Full Access Nivel 2 (role_id = 6)
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_full_access, @perm_transportadora_visualizar, NOW()),
(@role_full_access, @perm_transportadora_criar, NOW()),
(@role_full_access, @perm_transportadora_editar, NOW()),
(@role_full_access, @perm_transportadora_deletar, NOW());
