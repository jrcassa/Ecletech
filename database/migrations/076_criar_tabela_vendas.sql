-- =====================================================
-- Migration: Criar tabela vendas
-- Descrição: Tabela principal de vendas/pedidos
-- Data: 2025-11-14
-- =====================================================

CREATE TABLE IF NOT EXISTS vendas (
    -- Identificação
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    external_id VARCHAR(50) DEFAULT NULL COMMENT 'ID no sistema externo',
    codigo VARCHAR(20) NOT NULL COMMENT 'Número do pedido',
    hash VARCHAR(10) NOT NULL COMMENT 'Código curto compartilhável',

    -- ========================================
    -- RELACIONAMENTOS (ID interno + external)
    -- ========================================

    -- Cliente
    cliente_id BIGINT UNSIGNED DEFAULT NULL,
    cliente_external_id VARCHAR(50) DEFAULT NULL,
    nome_cliente VARCHAR(255) DEFAULT NULL COMMENT 'Snapshot',

    -- Vendedor (colaborador) - INT UNSIGNED pois colaboradores usa INT
    vendedor_id INT UNSIGNED DEFAULT NULL,
    vendedor_external_id VARCHAR(50) DEFAULT NULL,
    nome_vendedor VARCHAR(255) DEFAULT NULL COMMENT 'Snapshot',

    -- Técnico (colaborador) - INT UNSIGNED pois colaboradores usa INT
    tecnico_id INT UNSIGNED DEFAULT NULL,
    tecnico_external_id VARCHAR(50) DEFAULT NULL,
    nome_tecnico VARCHAR(255) DEFAULT NULL COMMENT 'Snapshot',

    -- Situação
    situacao_venda_id BIGINT UNSIGNED DEFAULT NULL,
    situacao_venda_external_id VARCHAR(50) DEFAULT NULL,
    nome_situacao VARCHAR(100) DEFAULT NULL COMMENT 'Snapshot',

    -- Transportadora
    transportadora_id BIGINT UNSIGNED DEFAULT NULL,
    transportadora_external_id VARCHAR(50) DEFAULT NULL,
    nome_transportadora VARCHAR(255) DEFAULT NULL COMMENT 'Snapshot',

    -- Centro de Custo
    centro_custo_id BIGINT UNSIGNED DEFAULT NULL,
    centro_custo_external_id VARCHAR(50) DEFAULT NULL,
    nome_centro_custo VARCHAR(255) DEFAULT NULL COMMENT 'Snapshot',

    -- Loja
    loja_id BIGINT UNSIGNED DEFAULT NULL,
    loja_external_id VARCHAR(50) DEFAULT NULL,
    nome_loja VARCHAR(255) DEFAULT NULL COMMENT 'Snapshot',

    -- Forma de Pagamento (pode estar vazio - ver pagamentos[])
    forma_pagamento_id BIGINT UNSIGNED DEFAULT NULL,
    forma_pagamento_external_id VARCHAR(50) DEFAULT NULL,
    nome_forma_pagamento VARCHAR(255) DEFAULT NULL COMMENT 'Snapshot',

    -- ========================================
    -- DATAS
    -- ========================================
    data_venda DATE NOT NULL,
    prazo_entrega DATE DEFAULT NULL,
    validade DATE DEFAULT NULL,
    data_primeira_parcela DATE DEFAULT NULL,

    -- ========================================
    -- VALORES
    -- ========================================
    valor_produtos DECIMAL(15,2) DEFAULT 0.00,
    valor_servicos DECIMAL(15,2) DEFAULT 0.00,
    valor_frete DECIMAL(15,2) DEFAULT 0.00,
    desconto_valor DECIMAL(15,2) DEFAULT 0.00,
    desconto_porcentagem DECIMAL(5,2) DEFAULT 0.00,
    valor_total DECIMAL(15,2) NOT NULL,
    valor_custo DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Custo total',

    -- ========================================
    -- CONFIGURAÇÕES DE PAGAMENTO
    -- ========================================
    condicao_pagamento VARCHAR(20) DEFAULT 'a_vista' COMMENT 'a_vista, parcelado',
    numero_parcelas INT DEFAULT 1,
    intervalo_dias INT DEFAULT NULL,

    -- ========================================
    -- STATUS/CONTROLE
    -- ========================================
    situacao_financeiro TINYINT DEFAULT 0 COMMENT '0=Pendente, 1=Pago, 2=Parcial',
    situacao_estoque TINYINT DEFAULT 0 COMMENT '0=Pendente, 1=Separado, 2=Expedido',
    canal_venda VARCHAR(50) DEFAULT NULL COMMENT 'Presencial, WhatsApp, Site',
    exibir_endereco BOOLEAN DEFAULT 1,

    -- ========================================
    -- TEXTOS
    -- ========================================
    aos_cuidados_de VARCHAR(255) DEFAULT NULL,
    introducao TEXT DEFAULT NULL,
    observacoes TEXT DEFAULT NULL COMMENT 'Para o cliente',
    observacoes_interna TEXT DEFAULT NULL COMMENT 'Interna',

    -- ========================================
    -- NOTAS FISCAIS
    -- ========================================
    nota_fiscal_id VARCHAR(50) DEFAULT NULL,
    nota_fiscal_servico_id VARCHAR(50) DEFAULT NULL,

    -- ========================================
    -- AUDITORIA
    -- ========================================
    ativo BOOLEAN DEFAULT 1,
    cadastrado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modificado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deletado_em TIMESTAMP NULL DEFAULT NULL,

    -- ========================================
    -- ÍNDICES
    -- ========================================
    UNIQUE KEY uk_vendas_codigo (codigo),
    UNIQUE KEY uk_vendas_hash (hash),
    UNIQUE KEY uk_vendas_external_id (external_id),
    INDEX idx_vendas_cliente_id (cliente_id),
    INDEX idx_vendas_cliente_external_id (cliente_external_id),
    INDEX idx_vendas_vendedor_id (vendedor_id),
    INDEX idx_vendas_situacao_venda_id (situacao_venda_id),
    INDEX idx_vendas_data_venda (data_venda),
    INDEX idx_vendas_situacao_financeiro (situacao_financeiro),
    INDEX idx_vendas_situacao_estoque (situacao_estoque),
    INDEX idx_vendas_ativo (ativo),
    INDEX idx_vendas_deletado_em (deletado_em),

    -- ========================================
    -- FOREIGN KEYS
    -- ========================================
    CONSTRAINT fk_vendas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    CONSTRAINT fk_vendas_vendedor FOREIGN KEY (vendedor_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
    CONSTRAINT fk_vendas_tecnico FOREIGN KEY (tecnico_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
    CONSTRAINT fk_vendas_situacao FOREIGN KEY (situacao_venda_id) REFERENCES situacoes_vendas(id) ON DELETE SET NULL,
    CONSTRAINT fk_vendas_transportadora FOREIGN KEY (transportadora_id) REFERENCES transportadoras(id) ON DELETE SET NULL,
    CONSTRAINT fk_vendas_centro_custo FOREIGN KEY (centro_custo_id) REFERENCES centro_custo(id) ON DELETE SET NULL,
    CONSTRAINT fk_vendas_loja FOREIGN KEY (loja_id) REFERENCES lojas(id) ON DELETE SET NULL,
    CONSTRAINT fk_vendas_forma_pagamento FOREIGN KEY (forma_pagamento_id) REFERENCES formas_pagamento(id) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela principal de vendas/pedidos';
