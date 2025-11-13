-- =====================================================
-- MIGRATION: Atualizar Configurações de Modo de Envio
-- Data: 2025-01-13
-- Descrição: Adiciona/atualiza configurações para modo de envio (direto/fila)
-- =====================================================

-- Atualiza configurações existentes e adiciona novas
INSERT INTO whatsapp_configuracoes (chave, valor, tipo, descricao) VALUES

-- Modo de envio padrão
('modo_envio_padrao', 'fila', 'string', 'Modo de envio padrão: "direto" ou "fila"'),

-- Configurações do cron
('cron_limite_mensagens', '10', 'int', 'Número de mensagens processadas por execução do cron'),

-- Anti-ban
('antiban_delay_min', '3', 'int', 'Delay mínimo em segundos entre mensagens (anti-ban)'),
('antiban_delay_max', '7', 'int', 'Delay máximo em segundos entre mensagens (anti-ban)'),

-- Retry
('retry_base_delay', '60', 'int', 'Delay base em segundos para retry (backoff exponencial)'),
('retry_multiplicador', '2', 'int', 'Multiplicador para cálculo de backoff exponencial')

ON DUPLICATE KEY UPDATE
    valor = VALUES(valor),
    descricao = VALUES(descricao);

-- Remove configuração antiga se existir
DELETE FROM whatsapp_configuracoes WHERE chave = 'modo_envio' AND chave != 'modo_envio_padrao';
