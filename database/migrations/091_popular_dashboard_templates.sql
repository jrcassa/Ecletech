-- Migration: 091 - Popular tabela dashboard_templates
-- Descrição: Insere templates pré-configurados de dashboards
-- Data: 2025-01-14

-- Template 1: Dashboard Geral (Padrão para todos)
INSERT INTO dashboard_templates (codigo, nome, descricao, categoria, icone, cor, ordem, config_layout, is_sistema) VALUES
('template_geral', 'Dashboard Geral', 'Dashboard inicial padrão com visão geral do sistema', 'geral', 'fa-th-large', '#95a5a6', 1,
'{
  "grid": {"colunas": 12},
  "widgets": [
    {
      "widget_tipo_codigo": "geral_resumo_dia_cards",
      "titulo": null,
      "posicao_x": 0,
      "posicao_y": 0,
      "largura": 12,
      "altura": 2,
      "config": {}
    },
    {
      "widget_tipo_codigo": "geral_notificacoes_lista",
      "titulo": "Notificações",
      "posicao_x": 0,
      "posicao_y": 2,
      "largura": 4,
      "altura": 5,
      "config": {"limite": 10}
    },
    {
      "widget_tipo_codigo": "geral_atividade_feed",
      "titulo": "Atividades Recentes",
      "posicao_x": 4,
      "posicao_y": 2,
      "largura": 8,
      "altura": 5,
      "config": {"limite": 15}
    }
  ]
}', 1);

-- Template 2: Dashboard de Vendas
INSERT INTO dashboard_templates (codigo, nome, descricao, categoria, icone, cor, ordem, permissoes_requeridas, config_layout, is_sistema) VALUES
('template_vendas', 'Dashboard de Vendas', 'Acompanhamento completo de vendas e performance', 'vendas', 'fa-shopping-cart', '#e74c3c', 2, '["vendas.visualizar"]',
'{
  "grid": {"colunas": 12},
  "widgets": [
    {
      "widget_tipo_codigo": "vendas_hoje_contador",
      "titulo": null,
      "posicao_x": 0,
      "posicao_y": 0,
      "largura": 3,
      "altura": 2,
      "config": {"periodo": "hoje"}
    },
    {
      "widget_tipo_codigo": "vendas_ticket_medio_card",
      "titulo": null,
      "posicao_x": 3,
      "posicao_y": 0,
      "largura": 3,
      "altura": 2,
      "config": {"periodo": "mes_atual"}
    },
    {
      "widget_tipo_codigo": "vendas_valor_total_card",
      "titulo": "Total do Mês",
      "posicao_x": 6,
      "posicao_y": 0,
      "largura": 3,
      "altura": 2,
      "config": {"periodo": "mes_atual"}
    },
    {
      "widget_tipo_codigo": "vendas_valor_total_card",
      "titulo": "Total do Ano",
      "posicao_x": 9,
      "posicao_y": 0,
      "largura": 3,
      "altura": 2,
      "config": {"periodo": "ano_atual"}
    },
    {
      "widget_tipo_codigo": "vendas_mes_linha",
      "titulo": "Vendas do Mês",
      "posicao_x": 0,
      "posicao_y": 2,
      "largura": 12,
      "altura": 4,
      "config": {"periodo": "mes_atual", "mostrar_legenda": true}
    },
    {
      "widget_tipo_codigo": "vendas_por_cliente_pizza",
      "titulo": "Top 5 Clientes",
      "posicao_x": 0,
      "posicao_y": 6,
      "largura": 4,
      "altura": 4,
      "config": {"periodo": "mes_atual", "limite": 5}
    },
    {
      "widget_tipo_codigo": "vendas_top_produtos",
      "titulo": "Top 10 Produtos",
      "posicao_x": 4,
      "posicao_y": 6,
      "largura": 4,
      "altura": 5,
      "config": {"periodo": "mes_atual", "limite": 10}
    },
    {
      "widget_tipo_codigo": "vendas_ultimas_lista",
      "titulo": "Últimas Vendas",
      "posicao_x": 8,
      "posicao_y": 6,
      "largura": 4,
      "altura": 5,
      "config": {"limite": 10}
    }
  ]
}', 1);

-- Template 3: Dashboard Financeiro
INSERT INTO dashboard_templates (codigo, nome, descricao, categoria, icone, cor, ordem, permissoes_requeridas, config_layout, is_sistema) VALUES
('template_financeiro', 'Dashboard Financeiro', 'Controle completo de entradas, saídas e saldo', 'financeiro', 'fa-dollar-sign', '#27ae60', 3, '["pagamentos.visualizar", "recebimentos.visualizar"]',
'{
  "grid": {"colunas": 12},
  "widgets": [
    {
      "widget_tipo_codigo": "financeiro_saldo_card",
      "titulo": "Saldo Atual",
      "posicao_x": 0,
      "posicao_y": 0,
      "largura": 3,
      "altura": 2,
      "config": {}
    },
    {
      "widget_tipo_codigo": "financeiro_saldo_card",
      "titulo": "Contas a Pagar",
      "posicao_x": 3,
      "posicao_y": 0,
      "largura": 3,
      "altura": 2,
      "config": {"tipo": "pagar"}
    },
    {
      "widget_tipo_codigo": "financeiro_saldo_card",
      "titulo": "Contas a Receber",
      "posicao_x": 6,
      "posicao_y": 0,
      "largura": 3,
      "altura": 2,
      "config": {"tipo": "receber"}
    },
    {
      "widget_tipo_codigo": "financeiro_saldo_card",
      "titulo": "Resultado do Mês",
      "posicao_x": 9,
      "posicao_y": 0,
      "largura": 3,
      "altura": 2,
      "config": {"periodo": "mes_atual"}
    },
    {
      "widget_tipo_codigo": "financeiro_fluxo_caixa_linha",
      "titulo": "Fluxo de Caixa",
      "posicao_x": 0,
      "posicao_y": 2,
      "largura": 12,
      "altura": 4,
      "config": {"periodo": "mes_atual"}
    },
    {
      "widget_tipo_codigo": "financeiro_pagar_tabela",
      "titulo": "Contas a Pagar",
      "posicao_x": 0,
      "posicao_y": 6,
      "largura": 6,
      "altura": 5,
      "config": {"limite": 10, "apenas_pendentes": true}
    },
    {
      "widget_tipo_codigo": "financeiro_receber_tabela",
      "titulo": "Contas a Receber",
      "posicao_x": 6,
      "posicao_y": 6,
      "largura": 6,
      "altura": 5,
      "config": {"limite": 10, "apenas_pendentes": true}
    }
  ]
}', 1);

-- Template 4: Dashboard de Frota
INSERT INTO dashboard_templates (codigo, nome, descricao, categoria, icone, cor, ordem, permissoes_requeridas, config_layout, is_sistema) VALUES
('template_frota', 'Dashboard de Frota', 'Gestão completa da frota de veículos', 'frota', 'fa-truck', '#f39c12', 4, '["frota.visualizar"]',
'{
  "grid": {"colunas": 12},
  "widgets": [
    {
      "widget_tipo_codigo": "frota_ativos_contador",
      "titulo": "Veículos Ativos",
      "posicao_x": 0,
      "posicao_y": 0,
      "largura": 3,
      "altura": 2,
      "config": {}
    },
    {
      "widget_tipo_codigo": "frota_km_contador",
      "titulo": "Km Rodados",
      "posicao_x": 3,
      "posicao_y": 0,
      "largura": 3,
      "altura": 2,
      "config": {"periodo": "mes_atual"}
    },
    {
      "widget_tipo_codigo": "frota_custo_total_card",
      "titulo": "Custo Total",
      "posicao_x": 6,
      "posicao_y": 0,
      "largura": 3,
      "altura": 2,
      "config": {"periodo": "mes_atual"}
    },
    {
      "widget_tipo_codigo": "frota_media_consumo_card",
      "titulo": "Média Km/L",
      "posicao_x": 9,
      "posicao_y": 0,
      "largura": 3,
      "altura": 2,
      "config": {"periodo": "mes_atual"}
    },
    {
      "widget_tipo_codigo": "frota_consumo_linha",
      "titulo": "Consumo de Combustível",
      "posicao_x": 0,
      "posicao_y": 2,
      "largura": 12,
      "altura": 4,
      "config": {"periodo": "mes_atual"}
    },
    {
      "widget_tipo_codigo": "frota_custo_veiculo_tabela",
      "titulo": "Custo por Veículo",
      "posicao_x": 0,
      "posicao_y": 6,
      "largura": 6,
      "altura": 5,
      "config": {"periodo": "mes_atual"}
    },
    {
      "widget_tipo_codigo": "frota_alertas_lista",
      "titulo": "Alertas de Manutenção",
      "posicao_x": 6,
      "posicao_y": 6,
      "largura": 6,
      "altura": 5,
      "config": {"dias_antecedencia": 30}
    }
  ]
}', 1);

-- Template 5: Dashboard de Clientes
INSERT INTO dashboard_templates (codigo, nome, descricao, categoria, icone, cor, ordem, permissoes_requeridas, config_layout, is_sistema) VALUES
('template_clientes', 'Dashboard de Clientes', 'Gestão e acompanhamento de clientes', 'clientes', 'fa-users', '#3498db', 5, '["clientes.visualizar"]',
'{
  "grid": {"colunas": 12},
  "widgets": [
    {
      "widget_tipo_codigo": "clientes_total_card",
      "titulo": "Total de Clientes",
      "posicao_x": 0,
      "posicao_y": 0,
      "largura": 4,
      "altura": 2,
      "config": {}
    },
    {
      "widget_tipo_codigo": "clientes_novos_contador",
      "titulo": "Novos este Mês",
      "posicao_x": 4,
      "posicao_y": 0,
      "largura": 4,
      "altura": 2,
      "config": {"periodo": "mes_atual"}
    },
    {
      "widget_tipo_codigo": "clientes_novos_contador",
      "titulo": "Novos este Ano",
      "posicao_x": 8,
      "posicao_y": 0,
      "largura": 4,
      "altura": 2,
      "config": {"periodo": "ano_atual"}
    },
    {
      "widget_tipo_codigo": "clientes_evolucao_linha",
      "titulo": "Evolução de Cadastros",
      "posicao_x": 0,
      "posicao_y": 2,
      "largura": 12,
      "altura": 4,
      "config": {"periodo": "ano_atual"}
    },
    {
      "widget_tipo_codigo": "clientes_top_tabela",
      "titulo": "Top 10 Clientes",
      "posicao_x": 0,
      "posicao_y": 6,
      "largura": 6,
      "altura": 5,
      "config": {"periodo": "mes_atual", "limite": 10}
    },
    {
      "widget_tipo_codigo": "clientes_ultimos_lista",
      "titulo": "Últimos Cadastros",
      "posicao_x": 6,
      "posicao_y": 6,
      "largura": 6,
      "altura": 5,
      "config": {"limite": 10}
    }
  ]
}', 1);

-- Template 6: Dashboard de Produtos
INSERT INTO dashboard_templates (codigo, nome, descricao, categoria, icone, cor, ordem, permissoes_requeridas, config_layout, is_sistema) VALUES
('template_produtos', 'Dashboard de Produtos', 'Gestão de estoque e produtos', 'produtos', 'fa-box', '#9b59b6', 6, '["produtos.visualizar"]',
'{
  "grid": {"colunas": 12},
  "widgets": [
    {
      "widget_tipo_codigo": "produtos_total_contador",
      "titulo": "Total de Produtos",
      "posicao_x": 0,
      "posicao_y": 0,
      "largura": 4,
      "altura": 2,
      "config": {}
    },
    {
      "widget_tipo_codigo": "produtos_valor_estoque_card",
      "titulo": "Valor em Estoque",
      "posicao_x": 4,
      "posicao_y": 0,
      "largura": 4,
      "altura": 2,
      "config": {}
    },
    {
      "widget_tipo_codigo": "produtos_estoque_baixo_lista",
      "titulo": "Estoque Baixo",
      "posicao_x": 8,
      "posicao_y": 0,
      "largura": 4,
      "altura": 4,
      "config": {"limite": 5}
    },
    {
      "widget_tipo_codigo": "produtos_mais_vendidos_barra",
      "titulo": "Produtos Mais Vendidos",
      "posicao_x": 0,
      "posicao_y": 2,
      "largura": 8,
      "altura": 4,
      "config": {"periodo": "mes_atual", "limite": 10}
    },
    {
      "widget_tipo_codigo": "produtos_estoque_tabela",
      "titulo": "Produtos em Estoque",
      "posicao_x": 0,
      "posicao_y": 6,
      "largura": 12,
      "altura": 5,
      "config": {"limite": 20}
    }
  ]
}', 1);
