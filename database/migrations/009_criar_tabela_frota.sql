-- =====================================================
-- Migration: Criar tabela de frota
-- Descrição: Tabela para gerenciamento de veículos da frota
-- Data: 2025-11-10
-- =====================================================

CREATE TABLE IF NOT EXISTS `frota` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL COMMENT 'Nome/identificação do veículo',
  `tipo` ENUM('motocicleta', 'automovel', 'caminhonete', 'caminhao', 'onibus', 'van') NOT NULL COMMENT 'Tipo do veículo',
  `placa` VARCHAR(8) NOT NULL COMMENT 'Placa no formato Mercosul (ABC1D23) ou antigo (ABC-1234)',
  `status` ENUM('ativo', 'inativo', 'manutencao', 'reservado', 'vendido') NOT NULL DEFAULT 'ativo' COMMENT 'Status operacional do veículo',
  `marca` VARCHAR(50) DEFAULT NULL COMMENT 'Fabricante do veículo',
  `modelo` VARCHAR(50) DEFAULT NULL COMMENT 'Modelo do veículo',
  `ano_fabricacao` YEAR DEFAULT NULL COMMENT 'Ano de fabricação',
  `ano_modelo` YEAR DEFAULT NULL COMMENT 'Ano do modelo',
  `cor` VARCHAR(30) DEFAULT NULL COMMENT 'Cor do veículo',
  `chassi` VARCHAR(17) DEFAULT NULL COMMENT 'Número do chassi (VIN)',
  `renavam` VARCHAR(11) DEFAULT NULL COMMENT 'Código RENAVAM',
  `quilometragem` INT UNSIGNED DEFAULT 0 COMMENT 'Quilometragem atual',
  `capacidade_tanque` DECIMAL(5,2) DEFAULT NULL COMMENT 'Capacidade do tanque em litros',
  `data_aquisicao` DATE DEFAULT NULL COMMENT 'Data de aquisição do veículo',
  `valor_aquisicao` DECIMAL(10,2) DEFAULT NULL COMMENT 'Valor pago na aquisição',
  `observacoes` TEXT DEFAULT NULL COMMENT 'Observações gerais',
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de cadastro no sistema',
  `atualizado_em` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última atualização',
  `deletado_em` DATETIME NULL DEFAULT NULL COMMENT 'Data de exclusão lógica',
  `ativo` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Registro ativo no sistema',

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_placa` (`placa`),
  UNIQUE KEY `uk_chassi` (`chassi`),
  UNIQUE KEY `uk_renavam` (`renavam`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_status` (`status`),
  KEY `idx_marca_modelo` (`marca`, `modelo`),
  KEY `idx_criado_em` (`criado_em`),
  KEY `idx_ativo` (`ativo`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cadastro de veículos da frota';

-- =====================================================
-- Inserir permissões para o módulo de frota
-- =====================================================

-- Inserir permissões do módulo frota
INSERT INTO `colaborador_permissions` (`nome`, `codigo`, `descricao`, `modulo`, `ativo`, `criado_em`) VALUES
('Visualizar Frota', 'frota.visualizar', 'Permite visualizar a lista de veículos e detalhes', 'frota', 1, NOW()),
('Criar Frota', 'frota.criar', 'Permite cadastrar novos veículos na frota', 'frota', 1, NOW()),
('Editar Frota', 'frota.editar', 'Permite editar informações dos veículos', 'frota', 1, NOW()),
('Deletar Frota', 'frota.deletar', 'Permite remover veículos da frota', 'frota', 1, NOW());

-- =====================================================
-- Associar permissões aos roles existentes
-- =====================================================

-- Obter IDs das permissões recém-criadas
SET @perm_visualizar = (SELECT id FROM colaborador_permissions WHERE codigo = 'frota.visualizar');
SET @perm_criar = (SELECT id FROM colaborador_permissions WHERE codigo = 'frota.criar');
SET @perm_editar = (SELECT id FROM colaborador_permissions WHERE codigo = 'frota.editar');
SET @perm_deletar = (SELECT id FROM colaborador_permissions WHERE codigo = 'frota.deletar');

-- Obter IDs dos roles
SET @role_superadmin = (SELECT id FROM colaborador_roles WHERE codigo = 'superadmin_full');
SET @role_admin = (SELECT id FROM colaborador_roles WHERE codigo = 'admin_full');
SET @role_gerente = (SELECT id FROM colaborador_roles WHERE codigo = 'gerente_usuarios');

-- Atribuir todas as permissões ao Super Admin
INSERT INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_superadmin, @perm_visualizar, NOW()),
(@role_superadmin, @perm_criar, NOW()),
(@role_superadmin, @perm_editar, NOW()),
(@role_superadmin, @perm_deletar, NOW());

-- Atribuir todas as permissões ao Admin
INSERT INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_admin, @perm_visualizar, NOW()),
(@role_admin, @perm_criar, NOW()),
(@role_admin, @perm_editar, NOW()),
(@role_admin, @perm_deletar, NOW());

-- Atribuir apenas visualizar e editar ao Gerente
INSERT INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_gerente, @perm_visualizar, NOW()),
(@role_gerente, @perm_editar, NOW());
