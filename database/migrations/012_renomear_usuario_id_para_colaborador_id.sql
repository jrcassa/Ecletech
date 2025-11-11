-- Migration: Renomear usuario_id para colaborador_id
-- Data: 2025-11-11
-- Descrição: Renomeia todas as colunas usuario_id para colaborador_id em todo o sistema

-- IMPORTANTE: Executar esta migration após a 007 e 011

-- 1. Remove constraints de foreign keys que dependem das colunas usuario_id
ALTER TABLE `colaborador_tokens` DROP FOREIGN KEY `fk_tokens_colaborador`;
ALTER TABLE `auditoria` DROP FOREIGN KEY `fk_auditoria_colaborador`;
ALTER TABLE `auditoria_login` DROP FOREIGN KEY `fk_auditoria_login_colaborador`;

-- 2. Remove índices das colunas usuario_id
ALTER TABLE `csrf_tokens` DROP INDEX `idx_usuario_id`;
ALTER TABLE `colaborador_tokens` DROP INDEX `idx_usuario_id`;
ALTER TABLE `auditoria` DROP INDEX `idx_usuario_id`;
ALTER TABLE `auditoria_login` DROP INDEX `idx_usuario_id`;

-- 3. Renomeia as colunas de usuario_id para colaborador_id
ALTER TABLE `csrf_tokens`
    CHANGE COLUMN `usuario_id` `colaborador_id` INT UNSIGNED NULL COMMENT 'ID do colaborador (se autenticado)';

ALTER TABLE `colaborador_tokens`
    CHANGE COLUMN `usuario_id` `colaborador_id` INT UNSIGNED NOT NULL COMMENT 'ID do colaborador';

ALTER TABLE `auditoria`
    CHANGE COLUMN `usuario_id` `colaborador_id` INT UNSIGNED NULL COMMENT 'ID do colaborador que realizou a ação';

ALTER TABLE `auditoria_login`
    CHANGE COLUMN `usuario_id` `colaborador_id` INT UNSIGNED NULL COMMENT 'ID do colaborador';

-- 4. Recria os índices com novo nome
ALTER TABLE `csrf_tokens` ADD INDEX `idx_colaborador_id` (`colaborador_id`);
ALTER TABLE `colaborador_tokens` ADD INDEX `idx_colaborador_id` (`colaborador_id`);
ALTER TABLE `auditoria` ADD INDEX `idx_colaborador_id` (`colaborador_id`);
ALTER TABLE `auditoria_login` ADD INDEX `idx_colaborador_id` (`colaborador_id`);

-- 5. Recria as constraints de foreign keys
ALTER TABLE `colaborador_tokens`
    ADD CONSTRAINT `fk_tokens_colaborador`
        FOREIGN KEY (`colaborador_id`)
        REFERENCES `colaboradores` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE;

ALTER TABLE `auditoria`
    ADD CONSTRAINT `fk_auditoria_colaborador`
        FOREIGN KEY (`colaborador_id`)
        REFERENCES `colaboradores` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

ALTER TABLE `auditoria_login`
    ADD CONSTRAINT `fk_auditoria_login_colaborador`
        FOREIGN KEY (`colaborador_id`)
        REFERENCES `colaboradores` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE;
