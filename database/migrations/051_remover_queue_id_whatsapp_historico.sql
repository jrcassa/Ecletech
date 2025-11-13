-- =====================================================
-- MIGRATION: Remove campo queue_id do whatsapp_historico
-- Data: 2025-01-13
-- Descrição: Remove queue_id pois mensagens são deletadas da fila após envio
-- =====================================================

-- Remove foreign key primeiro
ALTER TABLE whatsapp_historico
DROP FOREIGN KEY whatsapp_historico_ibfk_1;

-- Remove índice relacionado ao queue_id
ALTER TABLE whatsapp_historico
DROP INDEX idx_queue;

-- Remove a coluna queue_id
ALTER TABLE whatsapp_historico
DROP COLUMN queue_id;
