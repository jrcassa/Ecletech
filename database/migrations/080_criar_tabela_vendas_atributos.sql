-- =====================================================
-- Migration: Criar tabela vendas_atributos
-- Descrição: Campos customizados/atributos dinâmicos das vendas
-- Data: 2025-11-14
-- =====================================================

CREATE TABLE IF NOT EXISTS vendas_atributos (
    -- Identificação
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    external_id VARCHAR(50) DEFAULT NULL COMMENT 'ID no sistema externo',

    -- ========================================
    -- RELACIONAMENTOS
    -- ========================================

    -- Venda
    venda_id BIGINT UNSIGNED NOT NULL,
    venda_external_id VARCHAR(50) DEFAULT NULL,

    -- Atributo
    atributo_id VARCHAR(50) DEFAULT NULL COMMENT 'ID do atributo no sistema externo',
    atributo_external_id VARCHAR(50) DEFAULT NULL,

    -- ========================================
    -- DADOS DO ATRIBUTO
    -- ========================================
    descricao VARCHAR(255) NOT NULL COMMENT 'Ex: id venda, Nome do motorista',
    tipo VARCHAR(20) DEFAULT 'texto' COMMENT 'texto, numeros, data, boolean',
    conteudo TEXT DEFAULT NULL COMMENT 'Valor armazenado como string',

    -- ========================================
    -- AUDITORIA
    -- ========================================
    cadastrado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modificado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- ========================================
    -- ÍNDICES
    -- ========================================
    UNIQUE KEY uk_vendas_atributos_external_id (external_id),
    INDEX idx_vendas_atributos_venda_id (venda_id),
    INDEX idx_vendas_atributos_venda_external_id (venda_external_id),
    INDEX idx_vendas_atributos_atributo_id (atributo_id),
    INDEX idx_vendas_atributos_atributo_external_id (atributo_external_id),

    -- ========================================
    -- FOREIGN KEYS
    -- ========================================
    CONSTRAINT fk_vendas_atributos_venda FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Campos customizados/atributos dinâmicos das vendas';
