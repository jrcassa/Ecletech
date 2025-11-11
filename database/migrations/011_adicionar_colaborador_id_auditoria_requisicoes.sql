-- Migration: Adicionar colaborador_id na tabela auditoria_requisicoes
-- Data: 2025-11-11
-- Descrição: Adiciona coluna para rastrear qual colaborador fez cada requisição

-- Adiciona a coluna colaborador_id
ALTER TABLE `auditoria_requisicoes`
ADD COLUMN `colaborador_id` INT UNSIGNED NULL COMMENT 'ID do colaborador que fez a requisição' AFTER `id`,
ADD INDEX `idx_colaborador_id` (`colaborador_id`),
ADD CONSTRAINT `fk_auditoria_requisicoes_colaborador`
    FOREIGN KEY (`colaborador_id`)
    REFERENCES `colaboradores` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE;
