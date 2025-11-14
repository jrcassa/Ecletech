-- Migration: 090 - Popular tabela widget_tipos
-- Descrição: Insere tipos de widgets disponíveis no sistema
-- Data: 2025-01-14

-- CATEGORIA: VENDAS
INSERT INTO widget_tipos (codigo, nome, descricao, categoria, subcategoria, tipo_visual, icone, permissoes_requeridas, config_padrao, largura_padrao, altura_padrao, intervalo_atualizacao) VALUES
('vendas_mes_linha', 'Vendas do Mês', 'Evolução diária das vendas no mês atual', 'vendas', 'graficos', 'grafico_linha', 'fa-chart-line', '["vendas.visualizar"]', '{"periodo": "mes_atual", "mostrar_legenda": true, "cor": "#3498db"}', 6, 4, 300),
('vendas_mes_barra', 'Vendas do Mês - Barras', 'Vendas por dia em gráfico de barras', 'vendas', 'graficos', 'grafico_barra', 'fa-chart-bar', '["vendas.visualizar"]', '{"periodo": "mes_atual", "cor": "#e74c3c"}', 6, 4, 300),
('vendas_comparativo_ano', 'Comparativo Anual', 'Compara vendas do ano atual vs ano anterior', 'vendas', 'graficos', 'grafico_linha', 'fa-chart-area', '["vendas.visualizar"]', '{"periodo": "ano_atual"}', 8, 4, 600),
('vendas_top_produtos', 'Top 10 Produtos', 'Produtos mais vendidos do período', 'vendas', 'tabelas', 'tabela', 'fa-trophy', '["vendas.visualizar", "produtos.visualizar"]', '{"periodo": "mes_atual", "limite": 10}', 4, 5, 300),
('vendas_por_cliente_pizza', 'Vendas por Cliente', 'Distribuição de vendas por cliente em pizza', 'vendas', 'graficos', 'grafico_pizza', 'fa-chart-pie', '["vendas.visualizar", "clientes.visualizar"]', '{"periodo": "mes_atual", "limite": 5}', 4, 4, 300),
('vendas_por_produto_donut', 'Vendas por Produto', 'Participação de cada produto em donut', 'vendas', 'graficos', 'grafico_donut', 'fa-chart-pie', '["vendas.visualizar", "produtos.visualizar"]', '{"periodo": "mes_atual", "limite": 8}', 4, 4, 300),
('vendas_valor_total_card', 'Valor Total Vendas', 'Card com valor total de vendas do período', 'vendas', 'cards', 'card', 'fa-dollar-sign', '["vendas.visualizar"]', '{"periodo": "mes_atual"}', 3, 2, 300),
('vendas_hoje_contador', 'Vendas Hoje', 'Quantidade e valor vendido hoje', 'vendas', 'cards', 'contador', 'fa-calendar-day', '["vendas.visualizar"]', '{"periodo": "hoje"}', 3, 2, 60),
('vendas_ultimas_lista', 'Últimas Vendas', 'Lista das vendas mais recentes', 'vendas', 'listas', 'lista', 'fa-list', '["vendas.visualizar"]', '{"limite": 10}', 4, 5, 60),
('vendas_ticket_medio_card', 'Ticket Médio', 'Ticket médio calculado do período', 'vendas', 'cards', 'card', 'fa-receipt', '["vendas.visualizar"]', '{"periodo": "mes_atual"}', 3, 2, 300);

-- CATEGORIA: FINANCEIRO
INSERT INTO widget_tipos (codigo, nome, descricao, categoria, subcategoria, tipo_visual, icone, permissoes_requeridas, config_padrao, largura_padrao, altura_padrao, intervalo_atualizacao) VALUES
('financeiro_fluxo_caixa_linha', 'Fluxo de Caixa', 'Gráfico de entradas vs saídas', 'financeiro', 'graficos', 'grafico_linha', 'fa-chart-line', '["pagamentos.visualizar", "recebimentos.visualizar"]', '{"periodo": "mes_atual"}', 8, 4, 300),
('financeiro_pagar_tabela', 'Contas a Pagar', 'Tabela de contas pendentes de pagamento', 'financeiro', 'tabelas', 'tabela', 'fa-file-invoice-dollar', '["pagamentos.visualizar"]', '{"limite": 10, "apenas_pendentes": true}', 5, 5, 300),
('financeiro_receber_tabela', 'Contas a Receber', 'Tabela de contas a receber', 'financeiro', 'tabelas', 'tabela', 'fa-hand-holding-usd', '["recebimentos.visualizar"]', '{"limite": 10, "apenas_pendentes": true}', 5, 5, 300),
('financeiro_saldo_card', 'Saldo Atual', 'Card com saldo consolidado', 'financeiro', 'cards', 'card', 'fa-wallet', '["pagamentos.visualizar", "recebimentos.visualizar"]', '{}', 3, 2, 300),
('financeiro_pagamentos_hoje_lista', 'Pagamentos Hoje', 'Lista de pagamentos realizados hoje', 'financeiro', 'listas', 'lista', 'fa-money-bill-wave', '["pagamentos.visualizar"]', '{"periodo": "hoje"}', 4, 4, 60),
('financeiro_recebimentos_hoje_lista', 'Recebimentos Hoje', 'Lista de recebimentos do dia', 'financeiro', 'listas', 'lista', 'fa-coins', '["recebimentos.visualizar"]', '{"periodo": "hoje"}', 4, 4, 60),
('financeiro_resultado_barra', 'Resultado Mensal', 'Receitas vs Despesas em barras', 'financeiro', 'graficos', 'grafico_barra', 'fa-chart-bar', '["pagamentos.visualizar", "recebimentos.visualizar"]', '{"periodo": "mes_atual"}', 6, 4, 300);

-- CATEGORIA: FROTA
INSERT INTO widget_tipos (codigo, nome, descricao, categoria, subcategoria, tipo_visual, icone, permissoes_requeridas, config_padrao, largura_padrao, altura_padrao, intervalo_atualizacao) VALUES
('frota_consumo_linha', 'Consumo de Combustível', 'Evolução do consumo de combustível', 'frota', 'graficos', 'grafico_linha', 'fa-gas-pump', '["frota.visualizar"]', '{"periodo": "mes_atual"}', 6, 4, 600),
('frota_km_contador', 'Km Rodados', 'Total de Km rodados no período', 'frota', 'cards', 'contador', 'fa-tachometer-alt', '["frota.visualizar"]', '{"periodo": "mes_atual"}', 3, 2, 600),
('frota_custo_veiculo_tabela', 'Custo por Veículo', 'Tabela de custos detalhados por veículo', 'frota', 'tabelas', 'tabela', 'fa-truck', '["frota.visualizar"]', '{"periodo": "mes_atual"}', 5, 5, 600),
('frota_alertas_lista', 'Alertas de Manutenção', 'Veículos com manutenção próxima', 'frota', 'listas', 'lista', 'fa-wrench', '["frota.visualizar"]', '{"dias_antecedencia": 30}', 4, 4, 600),
('frota_abastecimentos_timeline', 'Abastecimentos Recentes', 'Timeline de abastecimentos', 'frota', 'listas', 'timeline', 'fa-clock', '["frota.visualizar"]', '{"limite": 10}', 4, 6, 300),
('frota_custo_total_card', 'Custo Total Frota', 'Card com custo total da frota', 'frota', 'cards', 'card', 'fa-money-bill-wave', '["frota.visualizar"]', '{"periodo": "mes_atual"}', 3, 2, 600),
('frota_media_consumo_card', 'Média Km/L', 'Média geral de consumo da frota', 'frota', 'cards', 'card', 'fa-gas-pump', '["frota.visualizar"]', '{"periodo": "mes_atual"}', 3, 2, 600),
('frota_ativos_contador', 'Veículos Ativos', 'Quantidade de veículos ativos', 'frota', 'cards', 'contador', 'fa-truck', '["frota.visualizar"]', '{}', 3, 2, 600);

-- CATEGORIA: CLIENTES
INSERT INTO widget_tipos (codigo, nome, descricao, categoria, subcategoria, tipo_visual, icone, permissoes_requeridas, config_padrao, largura_padrao, altura_padrao, intervalo_atualizacao) VALUES
('clientes_novos_contador', 'Novos Clientes', 'Clientes cadastrados no período', 'clientes', 'cards', 'contador', 'fa-user-plus', '["clientes.visualizar"]', '{"periodo": "mes_atual"}', 3, 2, 600),
('clientes_total_card', 'Total de Clientes', 'Total de clientes ativos', 'clientes', 'cards', 'card', 'fa-users', '["clientes.visualizar"]', '{}', 3, 2, 600),
('clientes_top_tabela', 'Top 10 Clientes', 'Clientes que mais compraram', 'clientes', 'tabelas', 'tabela', 'fa-star', '["clientes.visualizar", "vendas.visualizar"]', '{"periodo": "mes_atual", "limite": 10}', 5, 5, 600),
('clientes_evolucao_linha', 'Evolução de Cadastros', 'Crescimento da base de clientes', 'clientes', 'graficos', 'grafico_linha', 'fa-chart-line', '["clientes.visualizar"]', '{"periodo": "ano_atual"}', 6, 4, 600),
('clientes_ultimos_lista', 'Últimos Cadastros', 'Clientes recém cadastrados', 'clientes', 'listas', 'lista', 'fa-list', '["clientes.visualizar"]', '{"limite": 10}', 4, 4, 600);

-- CATEGORIA: PRODUTOS
INSERT INTO widget_tipos (codigo, nome, descricao, categoria, subcategoria, tipo_visual, icone, permissoes_requeridas, config_padrao, largura_padrao, altura_padrao, intervalo_atualizacao) VALUES
('produtos_estoque_tabela', 'Produtos em Estoque', 'Lista de produtos e quantidades', 'produtos', 'tabelas', 'tabela', 'fa-boxes', '["produtos.visualizar"]', '{"limite": 20}', 6, 5, 600),
('produtos_estoque_baixo_lista', 'Estoque Baixo', 'Produtos abaixo do estoque mínimo', 'produtos', 'listas', 'lista', 'fa-exclamation-triangle', '["produtos.visualizar"]', '{"limite": 10}', 4, 4, 300),
('produtos_mais_vendidos_barra', 'Produtos Mais Vendidos', 'Top produtos por quantidade vendida', 'produtos', 'graficos', 'grafico_barra', 'fa-chart-bar', '["produtos.visualizar", "vendas.visualizar"]', '{"periodo": "mes_atual", "limite": 10}', 6, 4, 600),
('produtos_valor_estoque_card', 'Valor em Estoque', 'Valor total do estoque', 'produtos', 'cards', 'card', 'fa-dollar-sign', '["produtos.visualizar"]', '{}', 3, 2, 600),
('produtos_total_contador', 'Total de Produtos', 'Quantidade total de produtos cadastrados', 'produtos', 'cards', 'contador', 'fa-box', '["produtos.visualizar"]', '{}', 3, 2, 600);

-- CATEGORIA: GERAL
INSERT INTO widget_tipos (codigo, nome, descricao, categoria, subcategoria, tipo_visual, icone, config_padrao, largura_padrao, altura_padrao, intervalo_atualizacao) VALUES
('geral_resumo_dia_cards', 'Resumo do Dia', 'Cards múltiplos com métricas principais', 'geral', 'cards', 'cards_multiplos', 'fa-th', '{}', 12, 2, 300),
('geral_atividade_feed', 'Atividade Recente', 'Feed de atividades do sistema', 'geral', 'listas', 'feed', 'fa-stream', '{"limite": 20}', 4, 6, 60),
('geral_notificacoes_lista', 'Notificações', 'Notificações pendentes', 'geral', 'listas', 'lista', 'fa-bell', '{"limite": 10}', 3, 4, 60);
