-- ============================================
-- SCRIPT DE EXECUÇÃO DAS MIGRATIONS DASHBOARD
-- ============================================
-- Executa todas as migrations do sistema de dashboards em ordem correta
-- Data: 2025-01-14

-- ============================================
-- 1. CRIAR TABELA widget_tipos (sem dependências)
-- ============================================
source 086_criar_tabela_widget_tipos.sql;

-- ============================================
-- 2. CRIAR TABELA dashboards (depende de colaboradores)
-- ============================================
source 087_criar_tabela_dashboards.sql;

-- ============================================
-- 3. CRIAR TABELA dashboard_widgets (depende de dashboards e widget_tipos)
-- ============================================
source 088_criar_tabela_dashboard_widgets.sql;

-- ============================================
-- 4. CRIAR TABELA dashboard_templates (sem dependências)
-- ============================================
source 089_criar_tabela_dashboard_templates.sql;

-- ============================================
-- 5. POPULAR widget_tipos (40+ widgets)
-- ============================================
source 090_popular_widget_tipos.sql;

-- ============================================
-- 6. POPULAR dashboard_templates (6 templates)
-- ============================================
source 091_popular_dashboard_templates.sql;

-- ============================================
-- VERIFICAÇÃO
-- ============================================
SELECT 'Migrations executadas com sucesso!' AS status;
SELECT COUNT(*) AS total_widget_tipos FROM widget_tipos;
SELECT COUNT(*) AS total_templates FROM dashboard_templates;
