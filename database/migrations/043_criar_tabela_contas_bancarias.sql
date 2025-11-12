-- Migration: 043 - Criar tabela de contas bancárias
-- Descrição: Cria a estrutura para gerenciar contas bancárias da empresa
-- Data: 2025-11-12
-- Autor: Sistema

-- =====================================================
-- TABELA: contas_bancarias
-- =====================================================

CREATE TABLE IF NOT EXISTS `contas_bancarias` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_id` VARCHAR(50) DEFAULT NULL COMMENT 'ID do sistema externo',
  `nome` VARCHAR(200) NOT NULL COMMENT 'Nome identificador da conta',
  `banco_codigo` VARCHAR(10) DEFAULT NULL COMMENT 'Código do banco (001, 237, etc)',
  `banco_nome` VARCHAR(100) DEFAULT NULL COMMENT 'Nome do banco',
  `agencia` VARCHAR(20) DEFAULT NULL COMMENT 'Número da agência',
  `agencia_dv` VARCHAR(2) DEFAULT NULL COMMENT 'Dígito verificador da agência',
  `conta` VARCHAR(30) DEFAULT NULL COMMENT 'Número da conta',
  `conta_dv` VARCHAR(2) DEFAULT NULL COMMENT 'Dígito verificador da conta',
  `tipo_conta` ENUM('corrente', 'poupanca', 'investimento', 'outro') DEFAULT 'corrente' COMMENT 'Tipo da conta',
  `saldo_inicial` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Saldo inicial da conta',
  `ativo` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Conta ativa ou inativa',
  `observacoes` TEXT DEFAULT NULL COMMENT 'Observações adicionais',
  `cadastrado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modificado_em` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `deletado_em` DATETIME NULL COMMENT 'Soft delete',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_external_id` (`external_id`),
  KEY `idx_nome` (`nome`),
  KEY `idx_banco_codigo` (`banco_codigo`),
  KEY `idx_ativo` (`ativo`),
  KEY `idx_deletado_em` (`deletado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contas bancárias da empresa';

-- =====================================================
-- PERMISSÕES ACL
-- =====================================================

-- Inserir permissões para contas bancárias
INSERT INTO `colaborador_permissions` (`name`, `slug`, `description`, `resource`, `ativo`, `cadastrado_em`) VALUES
('Visualizar Contas Bancárias', 'conta_bancaria.visualizar', 'Permite visualizar a lista de contas bancárias', 'conta_bancaria', 1, NOW()),
('Criar Contas Bancárias', 'conta_bancaria.criar', 'Permite cadastrar novas contas bancárias', 'conta_bancaria', 1, NOW()),
('Editar Contas Bancárias', 'conta_bancaria.editar', 'Permite editar informações das contas bancárias', 'conta_bancaria', 1, NOW()),
('Deletar Contas Bancárias', 'conta_bancaria.deletar', 'Permite remover contas bancárias (soft delete)', 'conta_bancaria', 1, NOW());

-- =====================================================
-- ATRIBUIR PERMISSÕES AOS ROLES
-- =====================================================

-- Atribuir todas as permissões de contas bancárias ao superadmin
INSERT INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `cadastrado_em`)
SELECT
    r.id,
    p.id,
    NOW()
FROM `colaborador_roles` r
CROSS JOIN `colaborador_permissions` p
WHERE r.slug = 'superadmin'
AND p.resource = 'conta_bancaria'
AND NOT EXISTS (
    SELECT 1 FROM `colaborador_role_permissions` rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
);

-- Atribuir todas as permissões de contas bancárias ao admin
INSERT INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `cadastrado_em`)
SELECT
    r.id,
    p.id,
    NOW()
FROM `colaborador_roles` r
CROSS JOIN `colaborador_permissions` p
WHERE r.slug = 'admin'
AND p.resource = 'conta_bancaria'
AND NOT EXISTS (
    SELECT 1 FROM `colaborador_role_permissions` rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
);

-- Atribuir permissões de visualizar e editar ao gerente
INSERT INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `cadastrado_em`)
SELECT
    r.id,
    p.id,
    NOW()
FROM `colaborador_roles` r
CROSS JOIN `colaborador_permissions` p
WHERE r.slug = 'gerente'
AND p.resource = 'conta_bancaria'
AND p.slug IN ('conta_bancaria.visualizar', 'conta_bancaria.editar')
AND NOT EXISTS (
    SELECT 1 FROM `colaborador_role_permissions` rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
);

-- =====================================================
-- DADOS DE EXEMPLO (OPCIONAL - REMOVER EM PRODUÇÃO)
-- =====================================================

-- Exemplos de contas bancárias
INSERT INTO `contas_bancarias` (`external_id`, `nome`, `banco_codigo`, `banco_nome`, `agencia`, `agencia_dv`, `conta`, `conta_dv`, `tipo_conta`, `saldo_inicial`, `ativo`) VALUES
('1', 'Conta Caixa Adiel', '104', 'Caixa Econômica Federal', '0001', NULL, '00001234', '5', 'corrente', 0.00, 1);
