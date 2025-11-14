-- =====================================================
-- Migration: Criar tabela vendas_itens
-- Descrição: Itens de venda (produtos e serviços unificados)
-- Data: 2025-11-14
-- =====================================================

CREATE TABLE IF NOT EXISTS vendas_itens (
    -- Identificação
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    external_id VARCHAR(50) DEFAULT NULL COMMENT 'ID no sistema externo',

    -- ========================================
    -- RELACIONAMENTOS (ID interno + external)
    -- ========================================

    -- Venda
    venda_id BIGINT UNSIGNED NOT NULL,
    venda_external_id VARCHAR(50) DEFAULT NULL,

    -- Tipo do item
    tipo ENUM('produto', 'servico') NOT NULL DEFAULT 'produto' COMMENT 'Tipo: produto ou servico',

    -- Produto (quando tipo='produto')
    produto_id BIGINT UNSIGNED DEFAULT NULL,
    produto_external_id VARCHAR(50) DEFAULT NULL,

    -- Variação (quando tipo='produto' e tem variação)
    variacao_id BIGINT UNSIGNED DEFAULT NULL,
    variacao_external_id VARCHAR(50) DEFAULT NULL,

    -- Serviço (quando tipo='servico')
    servico_id BIGINT UNSIGNED DEFAULT NULL,
    servico_external_id VARCHAR(50) DEFAULT NULL,

    -- Tipo de Valor (Varejo, Atacado, etc)
    tipo_valor_id VARCHAR(50) DEFAULT NULL,
    tipo_valor_external_id VARCHAR(50) DEFAULT NULL,

    -- ========================================
    -- SNAPSHOT (dados na época da venda)
    -- ========================================
    nome_produto VARCHAR(255) DEFAULT NULL COMMENT 'Nome do produto/serviço',
    detalhes TEXT DEFAULT NULL COMMENT 'Detalhes importantes (ex: 1 DE 25KG)',
    nome_tipo_valor VARCHAR(100) DEFAULT NULL COMMENT 'Ex: Varejo, Atacado',
    sigla_unidade VARCHAR(10) DEFAULT NULL COMMENT 'UN, KG, M, etc',

    -- ========================================
    -- CONTROLE
    -- ========================================
    movimenta_estoque BOOLEAN DEFAULT 1 COMMENT 'Se movimenta estoque',
    possui_variacao BOOLEAN DEFAULT 0 COMMENT 'Se possui variação',

    -- ========================================
    -- QUANTIDADES E VALORES
    -- ========================================
    quantidade DECIMAL(15,3) NOT NULL COMMENT 'Pode ser fracionado (25.00 KG)',

    valor_custo DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Custo UNITÁRIO',
    valor_venda DECIMAL(15,2) NOT NULL COMMENT 'Preço UNITÁRIO',

    tipo_desconto VARCHAR(2) DEFAULT 'R$' COMMENT 'R$ ou %',
    desconto_valor DECIMAL(15,2) DEFAULT NULL,
    desconto_porcentagem DECIMAL(5,2) DEFAULT NULL,

    valor_total DECIMAL(15,2) NOT NULL COMMENT 'Total da linha (qtd * valor - desconto)',

    -- ========================================
    -- AUDITORIA
    -- ========================================
    cadastrado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modificado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- ========================================
    -- ÍNDICES
    -- ========================================
    UNIQUE KEY uk_vendas_itens_external_id (external_id),
    INDEX idx_vendas_itens_venda_id (venda_id),
    INDEX idx_vendas_itens_venda_external_id (venda_external_id),
    INDEX idx_vendas_itens_tipo (tipo),
    INDEX idx_vendas_itens_produto_id (produto_id),
    INDEX idx_vendas_itens_produto_external_id (produto_external_id),
    INDEX idx_vendas_itens_servico_id (servico_id),
    INDEX idx_vendas_itens_servico_external_id (servico_external_id),
    INDEX idx_vendas_itens_variacao_id (variacao_id),

    -- ========================================
    -- FOREIGN KEYS
    -- ========================================
    CONSTRAINT fk_vendas_itens_venda FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE CASCADE,
    CONSTRAINT fk_vendas_itens_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE SET NULL,
    CONSTRAINT fk_vendas_itens_servico FOREIGN KEY (servico_id) REFERENCES servicos(id) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Itens de venda (produtos e serviços unificados)';
