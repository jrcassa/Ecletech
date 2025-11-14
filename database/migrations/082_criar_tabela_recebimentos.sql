-- =====================================================
-- Migration: Criar Tabela de Recebimentos
-- Descrição: Cria a tabela recebimentos para gerenciar
--            contas a receber de clientes, fornecedores
--            e transportadoras
-- Data: 2025-01-14
-- =====================================================

CREATE TABLE IF NOT EXISTS recebimentos (
    -- Identificação
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    external_id VARCHAR(50) DEFAULT NULL,
    codigo VARCHAR(50) DEFAULT NULL,

    -- Descrição
    descricao VARCHAR(500) NOT NULL,

    -- Valores
    valor DECIMAL(15,2) NOT NULL,
    juros DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    desconto DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    valor_total DECIMAL(15,2) NOT NULL,

    -- Datas
    data_vencimento DATE NOT NULL,
    data_liquidacao DATE DEFAULT NULL,
    data_competencia DATE DEFAULT NULL,

    -- Status
    liquidado BOOLEAN NOT NULL DEFAULT 0,

    -- Plano de Contas (OBRIGATÓRIO)
    plano_contas_id BIGINT UNSIGNED NOT NULL,
    plano_contas_external_id VARCHAR(50) DEFAULT NULL,

    -- Centro de Custo (OBRIGATÓRIO)
    centro_custo_id BIGINT UNSIGNED NOT NULL,
    centro_custo_external_id VARCHAR(50) DEFAULT NULL,

    -- Conta Bancária (OBRIGATÓRIO)
    conta_bancaria_id BIGINT UNSIGNED NOT NULL,
    conta_bancaria_external_id VARCHAR(50) DEFAULT NULL,

    -- Forma de Pagamento (OBRIGATÓRIO)
    forma_pagamento_id BIGINT UNSIGNED NOT NULL,
    forma_pagamento_external_id VARCHAR(50) DEFAULT NULL,

    -- Entidade (quem vai pagar)
    entidade ENUM('C', 'F', 'T') NOT NULL COMMENT 'C=Cliente, F=Fornecedor, T=Transportadora',

    -- Cliente (quando entidade = 'C')
    cliente_id BIGINT UNSIGNED DEFAULT NULL,
    cliente_external_id VARCHAR(50) DEFAULT NULL,

    -- Fornecedor (quando entidade = 'F')
    fornecedor_id BIGINT UNSIGNED DEFAULT NULL,
    fornecedor_external_id VARCHAR(50) DEFAULT NULL,

    -- Transportadora (quando entidade = 'T')
    transportadora_id BIGINT UNSIGNED DEFAULT NULL,
    transportadora_external_id VARCHAR(50) DEFAULT NULL,

    -- Auditoria
    usuario_id BIGINT UNSIGNED DEFAULT NULL,
    usuario_external_id VARCHAR(50) DEFAULT NULL,
    loja_id BIGINT UNSIGNED DEFAULT NULL,
    loja_external_id VARCHAR(50) DEFAULT NULL,

    cadastrado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deletado_em DATETIME DEFAULT NULL,

    -- Índices para performance
    INDEX idx_external_id (external_id),
    INDEX idx_entidade (entidade),
    INDEX idx_cliente (cliente_id),
    INDEX idx_cliente_external (cliente_external_id),
    INDEX idx_fornecedor (fornecedor_id),
    INDEX idx_fornecedor_external (fornecedor_external_id),
    INDEX idx_transportadora (transportadora_id),
    INDEX idx_transportadora_external (transportadora_external_id),
    INDEX idx_data_vencimento (data_vencimento),
    INDEX idx_data_liquidacao (data_liquidacao),
    INDEX idx_liquidado (liquidado),
    INDEX idx_plano_contas (plano_contas_id),
    INDEX idx_centro_custo (centro_custo_id),
    INDEX idx_conta_bancaria (conta_bancaria_id),
    INDEX idx_forma_pagamento (forma_pagamento_id),
    INDEX idx_deletado (deletado_em),
    INDEX idx_usuario (usuario_id),
    INDEX idx_loja (loja_id),

    -- Constraints para garantir integridade
    CONSTRAINT chk_entidade_cliente CHECK (
        (entidade = 'C' AND cliente_id IS NOT NULL) OR entidade != 'C'
    ),
    CONSTRAINT chk_entidade_fornecedor CHECK (
        (entidade = 'F' AND fornecedor_id IS NOT NULL) OR entidade != 'F'
    ),
    CONSTRAINT chk_entidade_transportadora CHECK (
        (entidade = 'T' AND transportadora_id IS NOT NULL) OR entidade != 'T'
    ),
    CONSTRAINT chk_valor_positivo CHECK (valor >= 0),
    CONSTRAINT chk_valor_total_positivo CHECK (valor_total >= 0),
    CONSTRAINT chk_juros_positivo CHECK (juros >= 0),
    CONSTRAINT chk_desconto_positivo CHECK (desconto >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Comentários nas colunas
-- =====================================================
ALTER TABLE recebimentos
    MODIFY COLUMN external_id VARCHAR(50) DEFAULT NULL COMMENT 'ID do sistema externo (Bling, Omie, etc)',
    MODIFY COLUMN codigo VARCHAR(50) DEFAULT NULL COMMENT 'Código sequencial interno',
    MODIFY COLUMN valor DECIMAL(15,2) NOT NULL COMMENT 'Valor original do recebimento',
    MODIFY COLUMN juros DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Juros a adicionar',
    MODIFY COLUMN desconto DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Desconto a subtrair',
    MODIFY COLUMN valor_total DECIMAL(15,2) NOT NULL COMMENT 'Valor final = valor + juros - desconto',
    MODIFY COLUMN liquidado BOOLEAN NOT NULL DEFAULT 0 COMMENT '0=Pendente, 1=Recebido',
    MODIFY COLUMN data_vencimento DATE NOT NULL COMMENT 'Data de vencimento do recebimento',
    MODIFY COLUMN data_liquidacao DATE DEFAULT NULL COMMENT 'Data em que foi recebido',
    MODIFY COLUMN data_competencia DATE DEFAULT NULL COMMENT 'Data de competência contábil';
