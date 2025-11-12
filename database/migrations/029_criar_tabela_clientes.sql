-- =====================================================
-- Migration: Criar tabela de clientees
-- Descrição: Tabela para gerenciamento de clientees (PF e PJ)
-- Data: 2025-11-11
-- =====================================================

CREATE TABLE IF NOT EXISTS `clientees` (
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
  `ativo` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Cliente ativo/inativo',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cadastro de clientees (PF e PJ)';

-- =====================================================
-- Inserir permissões para o módulo de clientees
-- =====================================================

INSERT INTO `colaborador_permissions` (`nome`, `codigo`, `descricao`, `modulo`, `ativo`, `criado_em`) VALUES
('Visualizar Clientees', 'cliente.visualizar', 'Permite visualizar a lista de clientees e detalhes', 'cliente', 1, NOW()),
('Criar Clientees', 'cliente.criar', 'Permite cadastrar novos clientees', 'cliente', 1, NOW()),
('Editar Clientees', 'cliente.editar', 'Permite editar informações dos clientees', 'cliente', 1, NOW()),
('Deletar Clientees', 'cliente.deletar', 'Permite remover clientees (soft delete)', 'cliente', 1, NOW());

-- =====================================================
-- Atribuir permissões aos roles
-- =====================================================

-- Obter IDs das permissões
SET @perm_visualizar = (SELECT id FROM colaborador_permissions WHERE codigo = 'cliente.visualizar');
SET @perm_criar = (SELECT id FROM colaborador_permissions WHERE codigo = 'cliente.criar');
SET @perm_editar = (SELECT id FROM colaborador_permissions WHERE codigo = 'cliente.editar');
SET @perm_deletar = (SELECT id FROM colaborador_permissions WHERE codigo = 'cliente.deletar');

-- Obter IDs dos roles
SET @role_admin = (SELECT id FROM colaborador_roles WHERE codigo = 'admin');
SET @role_gerente = (SELECT id FROM colaborador_roles WHERE codigo = 'gerente');
SET @role_usuario = (SELECT id FROM colaborador_roles WHERE codigo = 'usuario');

-- Atribuir todas as permissões ao Admin
INSERT INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_admin, @perm_visualizar, NOW()),
(@role_admin, @perm_criar, NOW()),
(@role_admin, @perm_editar, NOW()),
(@role_admin, @perm_deletar, NOW());

-- Atribuir permissões de visualizar, criar e editar ao Gerente
INSERT INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_gerente, @perm_visualizar, NOW()),
(@role_gerente, @perm_criar, NOW()),
(@role_gerente, @perm_editar, NOW());

-- Atribuir apenas visualizar ao Usuário
INSERT INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_usuario, @perm_visualizar, NOW());
