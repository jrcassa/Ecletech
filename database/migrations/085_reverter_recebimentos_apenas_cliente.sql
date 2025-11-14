-- =====================================================
-- Migration: Reverter ajuste de recebimentos apenas cliente
-- Descrição: Recebimentos podem ser de Cliente, Fornecedor ou Transportadora
-- Data: 2025-01-14
-- =====================================================

-- Restaura campos de fornecedor e transportadora
ALTER TABLE recebimentos
    ADD COLUMN IF NOT EXISTS fornecedor_id BIGINT UNSIGNED DEFAULT NULL AFTER cliente_external_id,
    ADD COLUMN IF NOT EXISTS fornecedor_external_id VARCHAR(50) DEFAULT NULL AFTER fornecedor_id,
    ADD COLUMN IF NOT EXISTS transportadora_id BIGINT UNSIGNED DEFAULT NULL AFTER fornecedor_external_id,
    ADD COLUMN IF NOT EXISTS transportadora_external_id VARCHAR(50) DEFAULT NULL AFTER transportadora_id;

-- Modifica entidade para aceitar C, F e T
ALTER TABLE recebimentos
    MODIFY COLUMN entidade ENUM('C', 'F', 'T') NOT NULL COMMENT 'C=Cliente, F=Fornecedor, T=Transportadora';

-- Torna cliente_id opcional novamente (depende da entidade)
ALTER TABLE recebimentos
    MODIFY COLUMN cliente_id BIGINT UNSIGNED DEFAULT NULL;

-- Adiciona índices
ALTER TABLE recebimentos
    ADD INDEX IF NOT EXISTS idx_fornecedor (fornecedor_id),
    ADD INDEX IF NOT EXISTS idx_fornecedor_external (fornecedor_external_id),
    ADD INDEX IF NOT EXISTS idx_transportadora (transportadora_id),
    ADD INDEX IF NOT EXISTS idx_transportadora_external (transportadora_external_id);

-- Remove constraint antigo
ALTER TABLE recebimentos
    DROP CONSTRAINT IF EXISTS chk_entidade_cliente;

-- Adiciona constraints corretos
ALTER TABLE recebimentos
    ADD CONSTRAINT chk_entidade_cliente CHECK (
        (entidade = 'C' AND cliente_id IS NOT NULL) OR entidade != 'C'
    );

ALTER TABLE recebimentos
    ADD CONSTRAINT chk_entidade_fornecedor CHECK (
        (entidade = 'F' AND fornecedor_id IS NOT NULL) OR entidade != 'F'
    );

ALTER TABLE recebimentos
    ADD CONSTRAINT chk_entidade_transportadora CHECK (
        (entidade = 'T' AND transportadora_id IS NOT NULL) OR entidade != 'T'
    );

-- Comentário atualizado
ALTER TABLE recebimentos COMMENT = 'Recebimentos (contas a receber) - Cliente, Fornecedor ou Transportadora';
