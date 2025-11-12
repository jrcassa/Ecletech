-- Migration: 046 - Criar tabela de forma de pagamento
-- Descrição: Cria a estrutura para gerenciar formas de pagamento
-- Data: 2025-11-12
-- Autor: Sistema

-- =====================================================
-- TABELA: forma_de_pagamento
-- =====================================================

CREATE TABLE IF NOT EXISTS `forma_de_pagamento` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_id` VARCHAR(50) DEFAULT NULL COMMENT 'ID do sistema externo',
  `nome` VARCHAR(200) NOT NULL COMMENT 'Nome da forma de pagamento',
  `conta_bancaria_id` BIGINT UNSIGNED NOT NULL COMMENT 'Conta bancária associada',
  `maximo_parcelas` INT NOT NULL DEFAULT 1 COMMENT 'Número máximo de parcelas permitidas',
  `intervalo_parcelas` INT NOT NULL DEFAULT 0 COMMENT 'Intervalo em dias entre parcelas',
  `intervalo_primeira_parcela` INT NOT NULL DEFAULT 0 COMMENT 'Intervalo em dias até a primeira parcela',
  `ativo` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Forma de pagamento ativa ou inativa',
  `observacoes` TEXT DEFAULT NULL COMMENT 'Observações adicionais',
  `cadastrado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modificado_em` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `deletado_em` DATETIME NULL COMMENT 'Soft delete',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_external_id` (`external_id`),
  UNIQUE KEY `uk_nome` (`nome`),
  KEY `idx_nome` (`nome`),
  KEY `idx_conta_bancaria_id` (`conta_bancaria_id`),
  KEY `idx_ativo` (`ativo`),
  KEY `idx_deletado_em` (`deletado_em`),
  CONSTRAINT `fk_forma_de_pagamento_conta_bancaria`
    FOREIGN KEY (`conta_bancaria_id`)
    REFERENCES `contas_bancarias` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Formas de pagamento disponíveis';

-- =====================================================
-- PERMISSÕES ACL
-- =====================================================

-- Inserir permissões para forma de pagamento
INSERT INTO `colaborador_permissions` (`nome`, `codigo`, `descricao`, `modulo`, `ativo`, `criado_em`) VALUES
('Visualizar Forma de Pagamento', 'forma_de_pagamento.visualizar', 'Permite visualizar formas de pagamento', 'forma_de_pagamento', 1, NOW()),
('Criar Forma de Pagamento', 'forma_de_pagamento.criar', 'Permite cadastrar novas formas de pagamento', 'forma_de_pagamento', 1, NOW()),
('Editar Forma de Pagamento', 'forma_de_pagamento.editar', 'Permite editar informações de formas de pagamento', 'forma_de_pagamento', 1, NOW()),
('Deletar Forma de Pagamento', 'forma_de_pagamento.deletar', 'Permite remover formas de pagamento (soft delete)', 'forma_de_pagamento', 1, NOW());

-- =====================================================
-- ATRIBUIR PERMISSÕES AOS ROLES
-- =====================================================

-- Atribuir todas as permissões de forma de pagamento ao superadmin
INSERT INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`)
SELECT
    r.id,
    p.id,
    NOW()
FROM `colaborador_roles` r
CROSS JOIN `colaborador_permissions` p
WHERE r.codigo = 'superadmin_full'
AND p.modulo = 'forma_de_pagamento'
AND NOT EXISTS (
    SELECT 1 FROM `colaborador_role_permissions` rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
);

-- Atribuir todas as permissões de forma de pagamento ao admin
INSERT INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`)
SELECT
    r.id,
    p.id,
    NOW()
FROM `colaborador_roles` r
CROSS JOIN `colaborador_permissions` p
WHERE r.codigo = 'admin_full'
AND p.modulo = 'forma_de_pagamento'
AND NOT EXISTS (
    SELECT 1 FROM `colaborador_role_permissions` rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
);

-- Atribuir permissões de visualizar e editar ao gerente
INSERT INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`)
SELECT
    r.id,
    p.id,
    NOW()
FROM `colaborador_roles` r
CROSS JOIN `colaborador_permissions` p
WHERE r.codigo = 'gerente_usuarios'
AND p.modulo = 'forma_de_pagamento'
AND p.codigo IN ('forma_de_pagamento.visualizar', 'forma_de_pagamento.editar')
AND NOT EXISTS (
    SELECT 1 FROM `colaborador_role_permissions` rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
);

-- =====================================================
-- DADOS DE EXEMPLO (OPCIONAL - REMOVER EM PRODUÇÃO)
-- =====================================================

-- Exemplos de formas de pagamento
-- Nota: Ajuste os conta_bancaria_id conforme as contas existentes no seu banco
INSERT INTO `forma_de_pagamento` (`external_id`, `nome`, `conta_bancaria_id`, `maximo_parcelas`, `intervalo_parcelas`, `intervalo_primeira_parcela`, `ativo`, `observacoes`)
SELECT '1', 'Dinheiro à Vista', cb.id, 1, 0, 0, 1, 'Pagamento em dinheiro à vista'
FROM `contas_bancarias` cb
WHERE cb.deletado_em IS NULL
LIMIT 1;

INSERT INTO `forma_de_pagamento` (`external_id`, `nome`, `conta_bancaria_id`, `maximo_parcelas`, `intervalo_parcelas`, `intervalo_primeira_parcela`, `ativo`, `observacoes`)
SELECT '2', 'Cartão de Crédito até 3x', cb.id, 3, 30, 0, 1, 'Pagamento parcelado em até 3 vezes no cartão de crédito'
FROM `contas_bancarias` cb
WHERE cb.deletado_em IS NULL
LIMIT 1;

INSERT INTO `forma_de_pagamento` (`external_id`, `nome`, `conta_bancaria_id`, `maximo_parcelas`, `intervalo_parcelas`, `intervalo_primeira_parcela`, `ativo`, `observacoes`)
SELECT '3', 'Boleto Bancário', cb.id, 1, 0, 0, 1, 'Pagamento via boleto bancário'
FROM `contas_bancarias` cb
WHERE cb.deletado_em IS NULL
LIMIT 1;

INSERT INTO `forma_de_pagamento` (`external_id`, `nome`, `conta_bancaria_id`, `maximo_parcelas`, `intervalo_parcelas`, `intervalo_primeira_parcela`, `ativo`, `observacoes`)
SELECT '4', 'PIX', cb.id, 1, 0, 0, 1, 'Pagamento via PIX'
FROM `contas_bancarias` cb
WHERE cb.deletado_em IS NULL
LIMIT 1;
