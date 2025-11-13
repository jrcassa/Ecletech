-- Migration: Adicionar campo celular na tabela colaboradores
-- Data: 2025-11-13
-- Descrição: Adiciona campo celular para envio de notificações WhatsApp

ALTER TABLE `colaboradores`
ADD COLUMN `celular` VARCHAR(20) NULL COMMENT 'Número de celular com WhatsApp' AFTER `email`,
ADD INDEX `idx_celular` (`celular`);
