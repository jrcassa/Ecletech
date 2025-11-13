-- Migration: Adicionar coluna UUID em loja_informacoes
-- Data: 2025-11-13
-- Descrição: Adiciona coluna UUID com geração automática quando NULL

-- Adicionar coluna UUID
ALTER TABLE loja_informacoes
ADD COLUMN uuid CHAR(36) NULL DEFAULT NULL AFTER id,
ADD UNIQUE INDEX idx_uuid (uuid);

-- Atualizar registros existentes com UUID
UPDATE loja_informacoes
SET uuid = UUID()
WHERE uuid IS NULL;

-- Criar trigger para gerar UUID automaticamente antes de inserir
DELIMITER $$

CREATE TRIGGER before_insert_loja_informacoes_uuid
BEFORE INSERT ON loja_informacoes
FOR EACH ROW
BEGIN
    IF NEW.uuid IS NULL THEN
        SET NEW.uuid = UUID();
    END IF;
END$$

DELIMITER ;

-- Comentário da coluna
ALTER TABLE loja_informacoes
MODIFY COLUMN uuid CHAR(36) NULL DEFAULT NULL COMMENT 'UUID único gerado automaticamente';
