-- ============================================
-- SCRIPT COMPLETO - RECRIAR TABELAS DE DASHBOARD
-- Remove e recria todas as tabelas com todas as colunas
-- ============================================

-- ============================================
-- 1. REMOVER TABELAS EXISTENTES (ordem reversa por causa de FKs)
-- ============================================
DROP TABLE IF EXISTS dashboard_widgets;
DROP TABLE IF EXISTS dashboards;
DROP TABLE IF EXISTS dashboard_templates;
DROP TABLE IF EXISTS widget_tipos;

-- ============================================
-- 2. CRIAR TABELA widget_tipos (primeiro - sem dependências)
-- ============================================
CREATE TABLE widget_tipos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    categoria ENUM('vendas', 'financeiro', 'frota', 'clientes', 'produtos', 'geral') NOT NULL,
    tipo_visual ENUM(
        'grafico_linha',
        'grafico_barra',
        'grafico_pizza',
        'grafico_donut',
        'grafico_area',
        'card',
        'contador',
        'lista',
        'tabela',
        'cards_multiplos'
    ) NOT NULL,
    icone VARCHAR(50) NULL,
    cor VARCHAR(20) NULL,
    largura_padrao INT NOT NULL DEFAULT 4,
    altura_padrao INT NOT NULL DEFAULT 3,
    intervalo_atualizacao_padrao INT NOT NULL DEFAULT 300,
    config_padrao JSON NULL,
    permissoes_requeridas JSON NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_widget_tipos_categoria (categoria),
    INDEX idx_widget_tipos_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. CRIAR TABELA dashboards (depende de colaboradores)
-- ============================================
CREATE TABLE dashboards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT UNSIGNED NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    is_padrao TINYINT(1) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_dashboards_colaborador
        FOREIGN KEY (colaborador_id)
        REFERENCES colaboradores(id)
        ON DELETE CASCADE,

    INDEX idx_dashboards_colaborador (colaborador_id),
    INDEX idx_dashboards_padrao (is_padrao),
    INDEX idx_dashboards_ativo (ativo),
    UNIQUE KEY unique_nome_colaborador (colaborador_id, nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. CRIAR TABELA dashboard_widgets (depende de dashboards e widget_tipos)
-- ============================================
CREATE TABLE dashboard_widgets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dashboard_id INT UNSIGNED NOT NULL,
    widget_tipo_id INT UNSIGNED NOT NULL,
    titulo VARCHAR(100) NULL,
    config JSON NULL,
    posicao_x INT NOT NULL DEFAULT 0,
    posicao_y INT NOT NULL DEFAULT 0,
    largura INT NOT NULL DEFAULT 4,
    altura INT NOT NULL DEFAULT 3,
    ordem INT NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_dashboard_widgets_dashboard
        FOREIGN KEY (dashboard_id)
        REFERENCES dashboards(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_dashboard_widgets_tipo
        FOREIGN KEY (widget_tipo_id)
        REFERENCES widget_tipos(id)
        ON DELETE RESTRICT,

    INDEX idx_dashboard_widgets_dashboard (dashboard_id),
    INDEX idx_dashboard_widgets_tipo (widget_tipo_id),
    INDEX idx_dashboard_widgets_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. CRIAR TABELA dashboard_templates (sem dependências)
-- ============================================
CREATE TABLE dashboard_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    categoria VARCHAR(50) NULL,
    imagem_preview VARCHAR(255) NULL,
    nivel_minimo INT NULL,
    permissoes_requeridas JSON NULL,
    icone VARCHAR(50) NULL,
    cor VARCHAR(20) NULL,
    ordem INT NOT NULL DEFAULT 0,
    config_layout JSON NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    is_sistema TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_dashboard_templates_categoria (categoria),
    INDEX idx_dashboard_templates_ativo (ativo),
    INDEX idx_dashboard_templates_sistema (is_sistema)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. POPULAR widget_tipos
-- ============================================

-- WIDGETS DE VENDAS
INSERT INTO widget_tipos (codigo, nome, descricao, categoria, tipo_visual, icone, cor, largura_padrao, altura_padrao, ordem) VALUES
('vendas_total_periodo', 'Total de Vendas do Período', 'Valor total de vendas em um período específico', 'vendas', 'card', 'fa-dollar-sign', '#2ecc71', 3, 2, 1),
('vendas_quantidade_periodo', 'Quantidade de Vendas', 'Número de vendas realizadas no período', 'vendas', 'contador', 'fa-shopping-cart', '#3498db', 3, 2, 2),
('vendas_evolucao_mensal', 'Evolução de Vendas Mensal', 'Gráfico de evolução das vendas mês a mês', 'vendas', 'grafico_linha', 'fa-chart-line', '#3498db', 6, 4, 3),
('vendas_por_vendedor', 'Vendas por Vendedor', 'Ranking de vendas por vendedor', 'vendas', 'grafico_barra', 'fa-user-tie', '#9b59b6', 6, 4, 4),
('vendas_por_produto', 'Produtos Mais Vendidos', 'Top produtos mais vendidos', 'vendas', 'grafico_pizza', 'fa-box', '#e74c3c', 4, 4, 5),
('vendas_ticket_medio', 'Ticket Médio', 'Valor médio das vendas', 'vendas', 'card', 'fa-receipt', '#f39c12', 3, 2, 6),
('vendas_ultimas', 'Últimas Vendas', 'Lista das vendas mais recentes', 'vendas', 'lista', 'fa-list', '#34495e', 6, 4, 7),
('vendas_metas', 'Vendas vs Meta', 'Comparativo de vendas realizadas vs meta', 'vendas', 'grafico_barra', 'fa-bullseye', '#16a085', 6, 4, 8);

-- WIDGETS FINANCEIRO
INSERT INTO widget_tipos (codigo, nome, descricao, categoria, tipo_visual, icone, cor, largura_padrao, altura_padrao, ordem) VALUES
('financeiro_saldo_atual', 'Saldo Atual', 'Saldo disponível em contas', 'financeiro', 'card', 'fa-wallet', '#2ecc71', 3, 2, 10),
('financeiro_receber_periodo', 'Contas a Receber', 'Total a receber no período', 'financeiro', 'card', 'fa-arrow-down', '#3498db', 3, 2, 11),
('financeiro_pagar_periodo', 'Contas a Pagar', 'Total a pagar no período', 'financeiro', 'card', 'fa-arrow-up', '#e74c3c', 3, 2, 12),
('financeiro_fluxo_caixa', 'Fluxo de Caixa', 'Entradas e saídas ao longo do tempo', 'financeiro', 'grafico_area', 'fa-chart-area', '#9b59b6', 8, 4, 13),
('financeiro_recebimentos_vencidos', 'Recebimentos Vencidos', 'Total de recebimentos em atraso', 'financeiro', 'card', 'fa-exclamation-triangle', '#e67e22', 3, 2, 14),
('financeiro_pagamentos_vencidos', 'Pagamentos Vencidos', 'Total de pagamentos em atraso', 'financeiro', 'card', 'fa-exclamation-circle', '#c0392b', 3, 2, 15),
('financeiro_contas_bancarias', 'Saldo por Conta', 'Saldo em cada conta bancária', 'financeiro', 'cards_multiplos', 'fa-university', '#34495e', 6, 3, 16),
('financeiro_despesas_categoria', 'Despesas por Categoria', 'Distribuição de despesas por categoria', 'financeiro', 'grafico_pizza', 'fa-chart-pie', '#95a5a6', 5, 4, 17);

-- WIDGETS FROTA
INSERT INTO widget_tipos (codigo, nome, descricao, categoria, tipo_visual, icone, cor, largura_padrao, altura_padrao, ordem) VALUES
('frota_total_veiculos', 'Total de Veículos', 'Quantidade total de veículos na frota', 'frota', 'contador', 'fa-truck', '#3498db', 3, 2, 20),
('frota_manutencoes_pendentes', 'Manutenções Pendentes', 'Número de manutenções aguardando', 'frota', 'card', 'fa-wrench', '#e74c3c', 3, 2, 21),
('frota_custo_mensal', 'Custo Mensal da Frota', 'Total de gastos com frota no mês', 'frota', 'card', 'fa-dollar-sign', '#e67e22', 3, 2, 22),
('frota_abastecimentos', 'Evolução de Abastecimentos', 'Custo com combustível ao longo do tempo', 'frota', 'grafico_linha', 'fa-gas-pump', '#f39c12', 6, 4, 23),
('frota_veiculos_status', 'Veículos por Status', 'Distribuição de veículos por status', 'frota', 'grafico_donut', 'fa-circle', '#9b59b6', 4, 4, 24),
('frota_ultimas_manutencoes', 'Últimas Manutenções', 'Lista das manutenções mais recentes', 'frota', 'lista', 'fa-tools', '#34495e', 6, 4, 25),
('frota_km_rodados', 'KM Rodados no Período', 'Total de quilometragem no período', 'frota', 'card', 'fa-road', '#16a085', 3, 2, 26);

-- WIDGETS CLIENTES
INSERT INTO widget_tipos (codigo, nome, descricao, categoria, tipo_visual, icone, cor, largura_padrao, altura_padrao, ordem) VALUES
('clientes_total_ativos', 'Total de Clientes Ativos', 'Quantidade de clientes ativos', 'clientes', 'contador', 'fa-users', '#2ecc71', 3, 2, 30),
('clientes_novos_mes', 'Novos Clientes no Mês', 'Clientes cadastrados no mês atual', 'clientes', 'card', 'fa-user-plus', '#3498db', 3, 2, 31),
('clientes_evolucao', 'Evolução de Clientes', 'Crescimento da base de clientes', 'clientes', 'grafico_linha', 'fa-chart-line', '#9b59b6', 6, 4, 32),
('clientes_por_cidade', 'Clientes por Cidade', 'Distribuição geográfica de clientes', 'clientes', 'grafico_barra', 'fa-map-marker-alt', '#e74c3c', 6, 4, 33),
('clientes_top_compradores', 'Top Compradores', 'Clientes que mais compram', 'clientes', 'tabela', 'fa-crown', '#f39c12', 6, 4, 34),
('clientes_inativos', 'Clientes Inativos', 'Clientes sem compras recentes', 'clientes', 'card', 'fa-user-slash', '#95a5a6', 3, 2, 35);

-- WIDGETS PRODUTOS
INSERT INTO widget_tipos (codigo, nome, descricao, categoria, tipo_visual, icone, cor, largura_padrao, altura_padrao, ordem) VALUES
('produtos_total_cadastrados', 'Total de Produtos', 'Quantidade de produtos cadastrados', 'produtos', 'contador', 'fa-boxes', '#3498db', 3, 2, 40),
('produtos_estoque_baixo', 'Produtos com Estoque Baixo', 'Produtos abaixo do estoque mínimo', 'produtos', 'card', 'fa-exclamation-triangle', '#e74c3c', 3, 2, 41),
('produtos_valor_estoque', 'Valor Total em Estoque', 'Valor monetário do estoque', 'produtos', 'card', 'fa-dollar-sign', '#2ecc71', 3, 2, 42),
('produtos_mais_vendidos', 'Produtos Mais Vendidos', 'Ranking de produtos por venda', 'produtos', 'grafico_barra', 'fa-trophy', '#f39c12', 6, 4, 43),
('produtos_por_grupo', 'Produtos por Grupo', 'Distribuição por grupo de produtos', 'produtos', 'grafico_pizza', 'fa-layer-group', '#9b59b6', 5, 4, 44),
('produtos_margem_lucro', 'Produtos com Melhor Margem', 'Top produtos por margem de lucro', 'produtos', 'tabela', 'fa-percentage', '#16a085', 6, 4, 45);

-- WIDGETS GERAIS
INSERT INTO widget_tipos (codigo, nome, descricao, categoria, tipo_visual, icone, cor, largura_padrao, altura_padrao, ordem) VALUES
('geral_resumo_dashboard', 'Resumo do Dia', 'Cards com resumo geral do dia', 'geral', 'cards_multiplos', 'fa-chart-bar', '#34495e', 12, 3, 50),
('geral_avisos', 'Avisos e Alertas', 'Lista de avisos importantes', 'geral', 'lista', 'fa-bell', '#e74c3c', 6, 4, 51);

-- ============================================
-- 7. POPULAR dashboard_templates
-- ============================================

-- Template Geral (Dashboard Padrão)
INSERT INTO dashboard_templates (codigo, nome, descricao, categoria, icone, cor, ordem, config_layout) VALUES
('template_geral', 'Dashboard Geral', 'Visão geral com principais indicadores do negócio', 'geral', 'fa-th-large', '#3498db', 1,
'[
    {"widget_tipo_codigo": "vendas_total_periodo", "posicao_x": 0, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "financeiro_saldo_atual", "posicao_x": 3, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "clientes_total_ativos", "posicao_x": 6, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "produtos_total_cadastrados", "posicao_x": 9, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "vendas_evolucao_mensal", "posicao_x": 0, "posicao_y": 2, "largura": 6, "altura": 4},
    {"widget_tipo_codigo": "financeiro_fluxo_caixa", "posicao_x": 6, "posicao_y": 2, "largura": 6, "altura": 4}
]');

-- Template Vendas
INSERT INTO dashboard_templates (codigo, nome, descricao, categoria, icone, cor, ordem, config_layout) VALUES
('template_vendas', 'Dashboard de Vendas', 'Focado em métricas e análises de vendas', 'vendas', 'fa-chart-line', '#2ecc71', 2,
'[
    {"widget_tipo_codigo": "vendas_total_periodo", "posicao_x": 0, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "vendas_quantidade_periodo", "posicao_x": 3, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "vendas_ticket_medio", "posicao_x": 6, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "vendas_evolucao_mensal", "posicao_x": 0, "posicao_y": 2, "largura": 6, "altura": 4},
    {"widget_tipo_codigo": "vendas_por_vendedor", "posicao_x": 6, "posicao_y": 2, "largura": 6, "altura": 4},
    {"widget_tipo_codigo": "vendas_por_produto", "posicao_x": 0, "posicao_y": 6, "largura": 4, "altura": 4},
    {"widget_tipo_codigo": "vendas_ultimas", "posicao_x": 4, "posicao_y": 6, "largura": 8, "altura": 4}
]');

-- Template Financeiro
INSERT INTO dashboard_templates (codigo, nome, descricao, categoria, icone, cor, ordem, config_layout) VALUES
('template_financeiro', 'Dashboard Financeiro', 'Controle financeiro completo', 'financeiro', 'fa-dollar-sign', '#f39c12', 3,
'[
    {"widget_tipo_codigo": "financeiro_saldo_atual", "posicao_x": 0, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "financeiro_receber_periodo", "posicao_x": 3, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "financeiro_pagar_periodo", "posicao_x": 6, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "financeiro_recebimentos_vencidos", "posicao_x": 9, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "financeiro_fluxo_caixa", "posicao_x": 0, "posicao_y": 2, "largura": 8, "altura": 4},
    {"widget_tipo_codigo": "financeiro_despesas_categoria", "posicao_x": 8, "posicao_y": 2, "largura": 4, "altura": 4},
    {"widget_tipo_codigo": "financeiro_contas_bancarias", "posicao_x": 0, "posicao_y": 6, "largura": 12, "altura": 3}
]');

-- Template Frota
INSERT INTO dashboard_templates (codigo, nome, descricao, categoria, icone, cor, ordem, config_layout) VALUES
('template_frota', 'Dashboard de Frota', 'Gestão completa da frota de veículos', 'frota', 'fa-truck', '#9b59b6', 4,
'[
    {"widget_tipo_codigo": "frota_total_veiculos", "posicao_x": 0, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "frota_manutencoes_pendentes", "posicao_x": 3, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "frota_custo_mensal", "posicao_x": 6, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "frota_km_rodados", "posicao_x": 9, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "frota_abastecimentos", "posicao_x": 0, "posicao_y": 2, "largura": 6, "altura": 4},
    {"widget_tipo_codigo": "frota_veiculos_status", "posicao_x": 6, "posicao_y": 2, "largura": 6, "altura": 4},
    {"widget_tipo_codigo": "frota_ultimas_manutencoes", "posicao_x": 0, "posicao_y": 6, "largura": 12, "altura": 4}
]');

-- Template Clientes
INSERT INTO dashboard_templates (codigo, nome, descricao, categoria, icone, cor, ordem, config_layout) VALUES
('template_clientes', 'Dashboard de Clientes', 'Análise e gestão de clientes', 'clientes', 'fa-users', '#16a085', 5,
'[
    {"widget_tipo_codigo": "clientes_total_ativos", "posicao_x": 0, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "clientes_novos_mes", "posicao_x": 3, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "clientes_inativos", "posicao_x": 6, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "clientes_evolucao", "posicao_x": 0, "posicao_y": 2, "largura": 6, "altura": 4},
    {"widget_tipo_codigo": "clientes_por_cidade", "posicao_x": 6, "posicao_y": 2, "largura": 6, "altura": 4},
    {"widget_tipo_codigo": "clientes_top_compradores", "posicao_x": 0, "posicao_y": 6, "largura": 12, "altura": 4}
]');

-- Template Produtos
INSERT INTO dashboard_templates (codigo, nome, descricao, categoria, icone, cor, ordem, config_layout) VALUES
('template_produtos', 'Dashboard de Produtos', 'Gestão de produtos e estoque', 'produtos', 'fa-boxes', '#e74c3c', 6,
'[
    {"widget_tipo_codigo": "produtos_total_cadastrados", "posicao_x": 0, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "produtos_estoque_baixo", "posicao_x": 3, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "produtos_valor_estoque", "posicao_x": 6, "posicao_y": 0, "largura": 3, "altura": 2},
    {"widget_tipo_codigo": "produtos_mais_vendidos", "posicao_x": 0, "posicao_y": 2, "largura": 6, "altura": 4},
    {"widget_tipo_codigo": "produtos_por_grupo", "posicao_x": 6, "posicao_y": 2, "largura": 6, "altura": 4},
    {"widget_tipo_codigo": "produtos_margem_lucro", "posicao_x": 0, "posicao_y": 6, "largura": 12, "altura": 4}
]');

-- ============================================
-- VERIFICAÇÃO FINAL
-- ============================================
SELECT 'Tabelas criadas e populadas com sucesso!' AS Status;

SELECT 'widget_tipos' AS Tabela, COUNT(*) AS Total FROM widget_tipos
UNION ALL
SELECT 'dashboard_templates', COUNT(*) FROM dashboard_templates
UNION ALL
SELECT 'dashboards', COUNT(*) FROM dashboards
UNION ALL
SELECT 'dashboard_widgets', COUNT(*) FROM dashboard_widgets;
