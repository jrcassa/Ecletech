-- ==============================================================================
-- Migration: Adicionar campo foto_comprovante na tabela frotas_abastecimentos
-- Descrição: Adiciona suporte para armazenar foto do comprovante de abastecimento
-- Data: 2025-11-13
-- ==============================================================================

ALTER TABLE frotas_abastecimentos
ADD COLUMN foto_comprovante VARCHAR(255) NULL COMMENT 'Caminho da foto do comprovante de abastecimento'
AFTER observacao_motorista;

-- Criar índice para facilitar buscas
ALTER TABLE frotas_abastecimentos
ADD INDEX idx_foto_comprovante (foto_comprovante);
