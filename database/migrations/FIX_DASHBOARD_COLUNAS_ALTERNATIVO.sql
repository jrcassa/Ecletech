-- ============================================
-- FIX ALTERNATIVO - Para MySQL que não suporta ADD COLUMN IF NOT EXISTS
-- Execute linha por linha no phpMyAdmin
-- Se der erro "Duplicate column name", ignore e continue
-- ============================================

-- Para dashboard_widgets
ALTER TABLE dashboard_widgets ADD COLUMN ordem INT NOT NULL DEFAULT 0 AFTER altura;
ALTER TABLE dashboard_widgets ADD COLUMN criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER ativo;
ALTER TABLE dashboard_widgets ADD COLUMN atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER criado_em;

-- Para dashboard_templates
ALTER TABLE dashboard_templates ADD COLUMN ordem INT NOT NULL DEFAULT 0 AFTER cor;
ALTER TABLE dashboard_templates ADD COLUMN criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_sistema;
ALTER TABLE dashboard_templates ADD COLUMN atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER criado_em;

-- Verificação
SELECT 'Se chegou até aqui, as colunas foram adicionadas!' AS Status;
