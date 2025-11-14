-- ============================================
-- FIX URGENTE - Adicionar colunas faltantes em dashboards
-- Execute este script AGORA no phpMyAdmin
-- ============================================

-- Adiciona coluna 'icone' se não existir
ALTER TABLE dashboards ADD COLUMN icone VARCHAR(50) NULL DEFAULT 'fa-chart-line' AFTER descricao;

-- Adiciona coluna 'cor' se não existir
ALTER TABLE dashboards ADD COLUMN cor VARCHAR(20) NULL DEFAULT '#3498db' AFTER icone;

-- Adiciona coluna 'ordem' se não existir
ALTER TABLE dashboards ADD COLUMN ordem INT NOT NULL DEFAULT 0 AFTER cor;

-- Verificação
SELECT 'Colunas adicionadas com sucesso em dashboards!' AS Status;
DESCRIBE dashboards;
