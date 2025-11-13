-- ============================================
-- SCRIPT DE DIAGNÓSTICO DO WHATSAPP
-- ============================================

-- 1. Verifica se a tabela whatsapp_queue existe
SHOW TABLES LIKE 'whatsapp_queue';

-- 2. Verifica estrutura da tabela
DESCRIBE whatsapp_queue;

-- 3. Verifica se há mensagens na fila
SELECT * FROM whatsapp_queue ORDER BY criado_em DESC LIMIT 10;

-- 4. Busca último abastecimento criado
SELECT
    fa.id,
    fa.status,
    fa.notificacao_motorista_enviada_em,
    fa.colaborador_id,
    c.nome as motorista_nome,
    c.celular as motorista_celular,
    f.placa,
    f.modelo
FROM frotas_abastecimentos fa
INNER JOIN colaboradores c ON fa.colaborador_id = c.id
INNER JOIN frotas f ON fa.frota_id = f.id
ORDER BY fa.criado_em DESC
LIMIT 1;

-- 5. Verifica mensagens de erro no histórico do WhatsApp
SELECT * FROM whatsapp_historico
WHERE tipo_evento LIKE '%erro%'
ORDER BY criado_em DESC
LIMIT 10;

-- 6. Verifica configuração do WhatsApp
SELECT * FROM whatsapp_configuracoes;

-- 7. Conta mensagens por status
SELECT status, COUNT(*) as total
FROM whatsapp_queue
GROUP BY status;

-- 8. Verifica se há registros com metadata de abastecimento
SELECT * FROM whatsapp_queue
WHERE dados_extras LIKE '%frota_abastecimento%'
ORDER BY criado_em DESC
LIMIT 10;
