-- =====================================================
-- Migration: Criar tabela de produtos
-- Descrição: Tabela principal para gerenciamento de produtos
-- Data: 2025-11-12
-- =====================================================

CREATE TABLE IF NOT EXISTS `produtos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_id` VARCHAR(50) DEFAULT NULL COMMENT 'ID do sistema externo',
  `nome` VARCHAR(255) NOT NULL COMMENT 'Nome do produto',
  `codigo_interno` VARCHAR(100) DEFAULT NULL COMMENT 'Código interno do produto',
  `codigo_barra` VARCHAR(100) DEFAULT NULL COMMENT 'Código de barras',

  -- Características do produto
  `possui_variacao` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Produto possui variação',
  `possui_composicao` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Produto possui composição',
  `movimenta_estoque` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Movimenta estoque',

  -- Dimensões
  `peso` DECIMAL(10,3) DEFAULT NULL COMMENT 'Peso do produto (kg)',
  `largura` DECIMAL(10,2) DEFAULT NULL COMMENT 'Largura (cm)',
  `altura` DECIMAL(10,2) DEFAULT NULL COMMENT 'Altura (cm)',
  `comprimento` DECIMAL(10,2) DEFAULT NULL COMMENT 'Comprimento (cm)',

  -- Relacionamento com grupo
  `grupo_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID do grupo de produtos',

  -- Informações adicionais
  `descricao` TEXT DEFAULT NULL COMMENT 'Descrição do produto',
  `estoque` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Quantidade em estoque',

  -- Valores padrão
  `valor_custo` DECIMAL(10,2) DEFAULT NULL COMMENT 'Valor de custo',
  `valor_venda` DECIMAL(10,2) DEFAULT NULL COMMENT 'Valor de venda',

  -- Campos padrão do sistema
  `ativo` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Produto ativo/inativo',
  `cadastrado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de cadastro',
  `modificado_em` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de modificação',
  `deletado_em` DATETIME NULL DEFAULT NULL COMMENT 'Data de exclusão (soft delete)',

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_external_id` (`external_id`),
  UNIQUE KEY `uk_codigo_interno` (`codigo_interno`),
  KEY `idx_codigo_barra` (`codigo_barra`),
  KEY `idx_nome` (`nome`),
  KEY `idx_grupo_id` (`grupo_id`),
  KEY `idx_ativo` (`ativo`),
  KEY `idx_deletado_em` (`deletado_em`),

  CONSTRAINT `fk_produtos_grupo` FOREIGN KEY (`grupo_id`)
    REFERENCES `grupos_produtos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cadastro de produtos';

-- =====================================================
-- Inserir permissões para o módulo de produtos
-- =====================================================

INSERT IGNORE INTO `colaborador_permissions` (`nome`, `codigo`, `descricao`, `modulo`, `ativo`, `criado_em`) VALUES
('Visualizar Produtos', 'produtos.visualizar', 'Permite visualizar a lista de produtos e detalhes', 'produtos', 1, NOW()),
('Criar Produtos', 'produtos.criar', 'Permite cadastrar novos produtos', 'produtos', 1, NOW()),
('Editar Produtos', 'produtos.editar', 'Permite editar informações dos produtos', 'produtos', 1, NOW()),
('Deletar Produtos', 'produtos.deletar', 'Permite remover produtos (soft delete)', 'produtos', 1, NOW());

-- =====================================================
-- Atribuir permissões aos roles
-- =====================================================

-- Obter IDs das permissões de produtos
SET @perm_produtos_visualizar = (SELECT id FROM colaborador_permissions WHERE codigo = 'produtos.visualizar');
SET @perm_produtos_criar = (SELECT id FROM colaborador_permissions WHERE codigo = 'produtos.criar');
SET @perm_produtos_editar = (SELECT id FROM colaborador_permissions WHERE codigo = 'produtos.editar');
SET @perm_produtos_deletar = (SELECT id FROM colaborador_permissions WHERE codigo = 'produtos.deletar');

-- Obter IDs dos roles
SET @role_superadmin = (SELECT id FROM colaborador_roles WHERE codigo = 'superadmin_full');
SET @role_admin_full = (SELECT id FROM colaborador_roles WHERE codigo = 'admin_full');
SET @role_gerente = (SELECT id FROM colaborador_roles WHERE codigo = 'gerente_usuarios');
SET @role_full_access = (SELECT id FROM colaborador_roles WHERE codigo = 'full_access_nivel_2');

-- Atribuir todas as permissões ao Super Admin
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_superadmin, @perm_produtos_visualizar, NOW()),
(@role_superadmin, @perm_produtos_criar, NOW()),
(@role_superadmin, @perm_produtos_editar, NOW()),
(@role_superadmin, @perm_produtos_deletar, NOW());

-- Atribuir todas as permissões ao Admin Full Access
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_admin_full, @perm_produtos_visualizar, NOW()),
(@role_admin_full, @perm_produtos_criar, NOW()),
(@role_admin_full, @perm_produtos_editar, NOW()),
(@role_admin_full, @perm_produtos_deletar, NOW());

-- Atribuir permissões de visualizar e editar ao Gerente
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_gerente, @perm_produtos_visualizar, NOW()),
(@role_gerente, @perm_produtos_editar, NOW());

-- Atribuir todas as permissões ao Full Access Nivel 2
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_full_access, @perm_produtos_visualizar, NOW()),
(@role_full_access, @perm_produtos_criar, NOW()),
(@role_full_access, @perm_produtos_editar, NOW()),
(@role_full_access, @perm_produtos_deletar, NOW());
