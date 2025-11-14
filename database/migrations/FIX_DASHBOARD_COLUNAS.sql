-- ============================================
-- FIX RÁPIDO - Adicionar colunas faltantes
-- Execute este script no phpMyAdmin ou MySQL
-- ============================================

-- Adiciona colunas em dashboard_widgets
ALTER TABLE dashboard_widgets
ADD COLUMN IF NOT EXISTS ordem INT NOT NULL DEFAULT 0 AFTER altura,
ADD COLUMN IF NOT EXISTS criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER ativo,
ADD COLUMN IF NOT EXISTS atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER criado_em;

-- Adiciona colunas em dashboard_templates
ALTER TABLE dashboard_templates
ADD COLUMN IF NOT EXISTS ordem INT NOT NULL DEFAULT 0 AFTER cor,
ADD COLUMN IF NOT EXISTS criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_sistema,
ADD COLUMN IF NOT EXISTS atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER criado_em;

-- Verificação
SELECT 'Colunas adicionadas com sucesso!' AS Status;
DESCRIBE dashboard_widgets;
DESCRIBE dashboard_templates;
