-- ============================================
-- SCRIPT DE CORREÇÃO - ADICIONAR COLUNAS FALTANTES
-- ============================================
-- Adiciona colunas que podem estar faltando nas tabelas de dashboard
-- Data: 2025-01-14

-- ============================================
-- 1. ADICIONAR COLUNAS EM dashboard_widgets
-- ============================================
source 092_adicionar_colunas_faltantes_dashboard_widgets.sql;

-- ============================================
-- 2. ADICIONAR COLUNAS EM dashboard_templates
-- ============================================
source 093_adicionar_colunas_faltantes_dashboard_templates.sql;

-- ============================================
-- VERIFICAÇÃO
-- ============================================
SELECT 'Correções aplicadas com sucesso!' AS status;

-- Verifica colunas em dashboard_widgets
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'dashboard_widgets'
AND COLUMN_NAME IN ('ordem', 'criado_em', 'atualizado_em')
ORDER BY ORDINAL_POSITION;

-- Verifica colunas em dashboard_templates
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'dashboard_templates'
AND COLUMN_NAME IN ('ordem', 'criado_em', 'atualizado_em')
ORDER BY ORDINAL_POSITION;
