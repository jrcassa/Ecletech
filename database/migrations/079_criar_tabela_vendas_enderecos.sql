-- =====================================================
-- Migration: Criar tabela vendas_enderecos
-- Descrição: Endereços de entrega das vendas
-- Data: 2025-11-14
-- =====================================================

CREATE TABLE IF NOT EXISTS vendas_enderecos (
    -- Identificação
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    external_id VARCHAR(50) DEFAULT NULL COMMENT 'ID no sistema externo',

    -- ========================================
    -- RELACIONAMENTOS (ID interno + external)
    -- ========================================

    -- Venda
    venda_id BIGINT UNSIGNED NOT NULL,
    venda_external_id VARCHAR(50) DEFAULT NULL,

    -- Cidade - INT UNSIGNED pois cidades usa INT
    cidade_id INT UNSIGNED DEFAULT NULL,
    cidade_external_id VARCHAR(50) DEFAULT NULL,

    -- ========================================
    -- ENDEREÇO (snapshot - preserva dados mesmo se cliente mudar)
    -- ========================================
    cep VARCHAR(9) DEFAULT NULL,
    logradouro VARCHAR(255) DEFAULT NULL,
    numero VARCHAR(50) DEFAULT NULL,
    complemento VARCHAR(255) DEFAULT NULL,
    bairro VARCHAR(100) DEFAULT NULL,
    estado CHAR(2) DEFAULT NULL COMMENT 'UF',
    pais VARCHAR(100) DEFAULT NULL,
    referencia TEXT DEFAULT NULL,

    -- SNAPSHOT
    nome_cidade VARCHAR(255) DEFAULT NULL,

    -- ========================================
    -- AUDITORIA
    -- ========================================
    cadastrado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- ========================================
    -- ÍNDICES
    -- ========================================
    UNIQUE KEY uk_vendas_enderecos_external_id (external_id),
    INDEX idx_vendas_enderecos_venda_id (venda_id),
    INDEX idx_vendas_enderecos_venda_external_id (venda_external_id),
    INDEX idx_vendas_enderecos_cidade_id (cidade_id),
    INDEX idx_vendas_enderecos_cidade_external_id (cidade_external_id),
    INDEX idx_vendas_enderecos_cep (cep),

    -- ========================================
    -- FOREIGN KEYS
    -- ========================================
    CONSTRAINT fk_vendas_enderecos_venda FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE CASCADE,
    CONSTRAINT fk_vendas_enderecos_cidade FOREIGN KEY (cidade_id) REFERENCES cidades(id) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Endereços de entrega das vendas';
