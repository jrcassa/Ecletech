-- =====================================================
-- Migration: Criar tabela vendas_pagamentos
-- Descrição: Parcelas de pagamento das vendas
-- Data: 2025-11-14
-- =====================================================

CREATE TABLE IF NOT EXISTS vendas_pagamentos (
    -- Identificação
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    external_id VARCHAR(50) DEFAULT NULL COMMENT 'ID no sistema externo',

    -- ========================================
    -- RELACIONAMENTOS (ID interno + external)
    -- ========================================

    -- Venda
    venda_id BIGINT UNSIGNED NOT NULL,
    venda_external_id VARCHAR(50) DEFAULT NULL,

    -- Forma de Pagamento
    forma_pagamento_id BIGINT UNSIGNED DEFAULT NULL,
    forma_pagamento_external_id VARCHAR(50) DEFAULT NULL,

    -- Plano de Contas
    plano_contas_id BIGINT UNSIGNED DEFAULT NULL,
    plano_contas_external_id VARCHAR(50) DEFAULT NULL,

    -- ========================================
    -- SNAPSHOT
    -- ========================================
    nome_forma_pagamento VARCHAR(255) DEFAULT NULL COMMENT 'Ex: Dinheiro à Vista',
    nome_plano_conta VARCHAR(255) DEFAULT NULL COMMENT 'Ex: Vendas de produtos',

    -- ========================================
    -- DADOS DO PAGAMENTO
    -- ========================================
    data_vencimento DATE NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    observacao TEXT DEFAULT NULL,

    -- ========================================
    -- STATUS (opcional - para controlar pagamento)
    -- ========================================
    pago BOOLEAN DEFAULT 0,
    data_pagamento DATE DEFAULT NULL,
    valor_pago DECIMAL(15,2) DEFAULT NULL,

    -- ========================================
    -- AUDITORIA
    -- ========================================
    cadastrado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modificado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- ========================================
    -- ÍNDICES
    -- ========================================
    UNIQUE KEY uk_vendas_pagamentos_external_id (external_id),
    INDEX idx_vendas_pagamentos_venda_id (venda_id),
    INDEX idx_vendas_pagamentos_venda_external_id (venda_external_id),
    INDEX idx_vendas_pagamentos_forma_pagamento_id (forma_pagamento_id),
    INDEX idx_vendas_pagamentos_forma_pagamento_external_id (forma_pagamento_external_id),
    INDEX idx_vendas_pagamentos_plano_contas_external_id (plano_contas_external_id),
    INDEX idx_vendas_pagamentos_data_vencimento (data_vencimento),
    INDEX idx_vendas_pagamentos_pago (pago),

    -- ========================================
    -- FOREIGN KEYS
    -- ========================================
    CONSTRAINT fk_vendas_pagamentos_venda FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE CASCADE,
    CONSTRAINT fk_vendas_pagamentos_forma_pagamento FOREIGN KEY (forma_pagamento_id) REFERENCES formas_pagamento(id) ON DELETE SET NULL,
    CONSTRAINT fk_vendas_pagamentos_plano_contas FOREIGN KEY (plano_contas_id) REFERENCES plano_contas(id) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Parcelas de pagamento das vendas';
