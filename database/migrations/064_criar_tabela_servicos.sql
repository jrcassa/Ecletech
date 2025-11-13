-- ==============================================================================
-- Migration: Criar tabela de serviços
-- Descrição: Registra serviços oferecidos pela empresa
-- Data: 2025-11-13
-- ==============================================================================

-- Criar tabela servicos
CREATE TABLE IF NOT EXISTS servicos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Dados do Serviço
    external_id VARCHAR(50) NULL COMMENT 'ID externo do serviço (integração)',
    codigo VARCHAR(100) NOT NULL COMMENT 'Código único do serviço',
    external_codigo VARCHAR(100) NULL COMMENT 'Código externo do serviço (integração)',
    nome VARCHAR(255) NOT NULL COMMENT 'Nome do serviço',
    valor_venda DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor de venda do serviço',
    observacoes TEXT NULL COMMENT 'Observações sobre o serviço',

    -- Status
    ativo BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Indica se o serviço está ativo',

    -- Auditoria
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de última atualização',
    deletado_em DATETIME NULL COMMENT 'Data de exclusão lógica (soft delete)',
    criado_por INT UNSIGNED NULL COMMENT 'ID do usuário que criou',
    atualizado_por INT UNSIGNED NULL COMMENT 'ID do usuário que atualizou',

    -- Índices
    UNIQUE INDEX idx_codigo (codigo),
    INDEX idx_external_id (external_id),
    INDEX idx_external_codigo (external_codigo),
    INDEX idx_nome (nome),
    INDEX idx_ativo (ativo),
    INDEX idx_deletado_em (deletado_em),
    INDEX idx_criado_em (criado_em),

    -- Chaves estrangeiras
    CONSTRAINT fk_servicos_criado_por
        FOREIGN KEY (criado_por)
        REFERENCES colaboradores(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_servicos_atualizado_por
        FOREIGN KEY (atualizado_por)
        REFERENCES colaboradores(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Cadastro de serviços oferecidos pela empresa';

-- ==============================================================================
-- Comentários sobre uso:
-- ==============================================================================
-- CAMPOS:
-- - codigo: Código único do serviço (obrigatório)
-- - nome: Nome descritivo do serviço
-- - valor_venda: Valor padrão de venda do serviço
-- - observacoes: Informações adicionais sobre o serviço
-- - external_id e external_codigo: Para integração com sistemas externos
-- ==============================================================================
