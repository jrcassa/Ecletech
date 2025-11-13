-- =====================================================
-- MIGRATION: Adiciona campos de data no whatsapp_historico
-- Data: 2025-01-13
-- Descrição: Adiciona data_enviado e data_entregue para rastreamento único
-- =====================================================

ALTER TABLE whatsapp_historico
ADD COLUMN data_enviado DATETIME AFTER status_code,
ADD COLUMN data_entregue DATETIME AFTER data_enviado;

-- Migra dados existentes (se houver)
UPDATE whatsapp_historico
SET data_enviado = criado_em
WHERE (status = 'enviado' OR tipo_evento = 'enviado' OR tipo_evento = 'enviado_direto')
AND data_enviado IS NULL
AND message_id IS NOT NULL;

-- Remove eventos duplicados (mantém apenas 1 registro por message_id)
-- Mantém o registro mais recente de cada message_id
DELETE h1 FROM whatsapp_historico h1
INNER JOIN (
    SELECT message_id, MAX(id) as max_id
    FROM whatsapp_historico
    WHERE message_id IS NOT NULL
    GROUP BY message_id
    HAVING COUNT(*) > 1
) h2 ON h1.message_id = h2.message_id
WHERE h1.id < h2.max_id;
