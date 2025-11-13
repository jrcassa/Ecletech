-- =====================================================
-- MIGRATION: Adiciona coluna tipo_evento na tabela whatsapp_historico
-- Data: 2025-01-13
-- Descrição: Adiciona campo para identificar o tipo de evento no histórico
-- =====================================================

ALTER TABLE whatsapp_historico
ADD COLUMN tipo_evento VARCHAR(50) AFTER queue_id,
ADD INDEX idx_tipo_evento (tipo_evento);

-- Atualiza registros existentes (se houver)
UPDATE whatsapp_historico
SET tipo_evento = 'enviado'
WHERE tipo_evento IS NULL AND status = 'enviado';

UPDATE whatsapp_historico
SET tipo_evento = 'erro'
WHERE tipo_evento IS NULL AND status = 'erro';

UPDATE whatsapp_historico
SET tipo_evento = 'processado'
WHERE tipo_evento IS NULL;
