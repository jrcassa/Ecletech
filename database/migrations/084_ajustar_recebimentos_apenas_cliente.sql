-- =====================================================
-- Migration: Ajustar tabela recebimentos para apenas Cliente
-- Descrição: Remove suporte a Fornecedor e Transportadora
--            Recebimentos são apenas contas a RECEBER (clientes)
-- Data: 2025-01-14
-- =====================================================

-- Remove campos de fornecedor e transportadora
ALTER TABLE recebimentos
    DROP COLUMN IF EXISTS fornecedor_id,
    DROP COLUMN IF EXISTS fornecedor_external_id,
    DROP COLUMN IF EXISTS transportadora_id,
    DROP COLUMN IF EXISTS transportadora_external_id;

-- Modifica entidade para aceitar apenas 'C' (Cliente)
ALTER TABLE recebimentos
    MODIFY COLUMN entidade ENUM('C') NOT NULL DEFAULT 'C' COMMENT 'C=Cliente (Recebimentos são apenas contas a receber)';

-- Torna cliente_id obrigatório
ALTER TABLE recebimentos
    MODIFY COLUMN cliente_id BIGINT UNSIGNED NOT NULL;

-- Remove índices desnecessários
ALTER TABLE recebimentos
    DROP INDEX IF EXISTS idx_fornecedor,
    DROP INDEX IF EXISTS idx_fornecedor_external,
    DROP INDEX IF EXISTS idx_transportadora,
    DROP INDEX IF EXISTS idx_transportadora_external;

-- Remove constraints antigos se existirem
ALTER TABLE recebimentos
    DROP CONSTRAINT IF EXISTS chk_entidade_fornecedor,
    DROP CONSTRAINT IF EXISTS chk_entidade_transportadora;

-- Atualiza constraint de cliente
ALTER TABLE recebimentos
    DROP CONSTRAINT IF EXISTS chk_entidade_cliente;

ALTER TABLE recebimentos
    ADD CONSTRAINT chk_entidade_cliente CHECK (entidade = 'C' AND cliente_id IS NOT NULL);

-- Comentário atualizado
ALTER TABLE recebimentos COMMENT = 'Recebimentos (contas a receber) - Apenas clientes';
