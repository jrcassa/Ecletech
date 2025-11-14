-- Migration: 066 - Criar tabela de pagamentos (contas a pagar e receber)
-- Descrição: Cria a estrutura para gerenciar pagamentos, contas a pagar e contas a receber
-- Data: 2025-11-14
-- Autor: Sistema

-- =====================================================
-- TABELA: pagamentos
-- =====================================================

CREATE TABLE IF NOT EXISTS `pagamentos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_id` VARCHAR(50) DEFAULT NULL COMMENT 'ID do sistema externo',
  `codigo` VARCHAR(50) DEFAULT NULL COMMENT 'Código sequencial ou identificador único',

  -- Descrição e valores
  `descricao` VARCHAR(500) NOT NULL COMMENT 'Descrição detalhada do pagamento',
  `valor` DECIMAL(15,2) NOT NULL COMMENT 'Valor original do pagamento',
  `juros` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor de juros aplicado',
  `desconto` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor de desconto aplicado',
  `taxa_banco` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa bancária cobrada',
  `taxa_operadora` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa da operadora de cartão/pagamento',
  `valor_total` DECIMAL(15,2) NOT NULL COMMENT 'Valor final calculado',

  -- Relacionamentos financeiros
  `plano_contas_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID do plano de contas',
  `plano_contas_external_id` VARCHAR(50) DEFAULT NULL COMMENT 'External ID do plano de contas',
  `centro_custo_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID do centro de custo',
  `centro_custo_external_id` VARCHAR(50) DEFAULT NULL COMMENT 'External ID do centro de custo',
  `conta_bancaria_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID da conta bancária',
  `conta_bancaria_external_id` VARCHAR(50) DEFAULT NULL COMMENT 'External ID da conta bancária',
  `forma_pagamento_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID da forma de pagamento',
  `forma_pagamento_external_id` VARCHAR(50) DEFAULT NULL COMMENT 'External ID da forma de pagamento',

  -- Entidade relacionada (Cliente, Fornecedor, Transportadora, Funcionário)
  `entidade` ENUM('C', 'F', 'T', 'U') NOT NULL COMMENT 'Tipo de entidade: C=Cliente, F=Fornecedor, T=Transportadora, U=Funcionário',

  -- Cliente (quando entidade = C)
  `cliente_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID do cliente (quando entidade=C)',
  `cliente_external_id` VARCHAR(50) DEFAULT NULL COMMENT 'External ID do cliente',

  -- Fornecedor (quando entidade = F)
  `fornecedor_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID do fornecedor (quando entidade=F)',
  `fornecedor_external_id` VARCHAR(50) DEFAULT NULL COMMENT 'External ID do fornecedor',

  -- Transportadora (quando entidade = T)
  `transportadora_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID da transportadora (quando entidade=T)',
  `transportadora_external_id` VARCHAR(50) DEFAULT NULL COMMENT 'External ID da transportadora',

  -- Funcionário (quando entidade = U)
  `funcionario_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID do funcionário (quando entidade=U)',
  `funcionario_external_id` VARCHAR(50) DEFAULT NULL COMMENT 'External ID do funcionário',

  -- Status e liquidação
  `liquidado` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Indica se o pagamento foi liquidado: 0=Não, 1=Sim',

  -- Datas
  `data_vencimento` DATE NOT NULL COMMENT 'Data de vencimento do pagamento',
  `data_liquidacao` DATE DEFAULT NULL COMMENT 'Data em que o pagamento foi liquidado',
  `data_competencia` DATE DEFAULT NULL COMMENT 'Data de competência contábil',

  -- Usuário responsável e loja
  `usuario_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID do usuário responsável',
  `usuario_external_id` VARCHAR(50) DEFAULT NULL COMMENT 'External ID do usuário',
  `loja_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID da loja/filial',
  `loja_external_id` VARCHAR(50) DEFAULT NULL COMMENT 'External ID da loja',

  -- Controle
  `cadastrado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modificado_em` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `deletado_em` DATETIME NULL COMMENT 'Soft delete',

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_external_id` (`external_id`),
  KEY `idx_codigo` (`codigo`),
  KEY `idx_descricao` (`descricao`(255)),
  KEY `idx_entidade` (`entidade`),
  KEY `idx_cliente_id` (`cliente_id`),
  KEY `idx_fornecedor_id` (`fornecedor_id`),
  KEY `idx_transportadora_id` (`transportadora_id`),
  KEY `idx_funcionario_id` (`funcionario_id`),
  KEY `idx_liquidado` (`liquidado`),
  KEY `idx_data_vencimento` (`data_vencimento`),
  KEY `idx_data_liquidacao` (`data_liquidacao`),
  KEY `idx_data_competencia` (`data_competencia`),
  KEY `idx_plano_contas_id` (`plano_contas_id`),
  KEY `idx_centro_custo_id` (`centro_custo_id`),
  KEY `idx_conta_bancaria_id` (`conta_bancaria_id`),
  KEY `idx_forma_pagamento_id` (`forma_pagamento_id`),
  KEY `idx_usuario_id` (`usuario_id`),
  KEY `idx_loja_id` (`loja_id`),
  KEY `idx_deletado_em` (`deletado_em`),
  KEY `idx_cadastrado_em` (`cadastrado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pagamentos (contas a pagar e contas a receber)';

-- =====================================================
-- TABELA: pagamentos_atributos (atributos customizados)
-- =====================================================

CREATE TABLE IF NOT EXISTS `pagamentos_atributos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pagamento_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID do pagamento',
  `chave` VARCHAR(100) NOT NULL COMMENT 'Nome do atributo customizado',
  `valor` TEXT DEFAULT NULL COMMENT 'Valor do atributo customizado',
  `cadastrado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modificado_em` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_pagamento_id` (`pagamento_id`),
  KEY `idx_chave` (`chave`),

  CONSTRAINT `fk_pagamentos_atributos_pagamento`
    FOREIGN KEY (`pagamento_id`)
    REFERENCES `pagamentos` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Atributos customizados dos pagamentos';

-- =====================================================
-- PERMISSÕES ACL
-- =====================================================

-- Inserir permissões para pagamentos
INSERT INTO `colaborador_permissions` (`nome`, `codigo`, `descricao`, `modulo`, `ativo`, `criado_em`) VALUES
('Visualizar Pagamentos', 'pagamento.visualizar', 'Permite visualizar pagamentos (contas a pagar e receber)', 'pagamento', 1, NOW()),
('Criar Pagamentos', 'pagamento.criar', 'Permite cadastrar novos pagamentos', 'pagamento', 1, NOW()),
('Editar Pagamentos', 'pagamento.editar', 'Permite editar informações de pagamentos', 'pagamento', 1, NOW()),
('Deletar Pagamentos', 'pagamento.deletar', 'Permite remover pagamentos (soft delete)', 'pagamento', 1, NOW()),
('Liquidar Pagamentos', 'pagamento.liquidar', 'Permite liquidar/baixar pagamentos', 'pagamento', 1, NOW());

-- =====================================================
-- ATRIBUIR PERMISSÕES AOS ROLES
-- =====================================================

-- Atribuir todas as permissões de pagamentos ao superadmin
INSERT INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`)
SELECT
    r.id,
    p.id,
    NOW()
FROM `colaborador_roles` r
CROSS JOIN `colaborador_permissions` p
WHERE r.codigo = 'superadmin_full'
AND p.modulo = 'pagamento'
AND NOT EXISTS (
    SELECT 1 FROM `colaborador_role_permissions` rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
);

-- Atribuir todas as permissões de pagamentos ao admin
INSERT INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`)
SELECT
    r.id,
    p.id,
    NOW()
FROM `colaborador_roles` r
CROSS JOIN `colaborador_permissions` p
WHERE r.codigo = 'admin_full'
AND p.modulo = 'pagamento'
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
AND p.modulo = 'pagamento'
AND p.codigo IN ('pagamento.visualizar', 'pagamento.editar', 'pagamento.liquidar')
AND NOT EXISTS (
    SELECT 1 FROM `colaborador_role_permissions` rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
);
