-- Migration: Renomear estrutura de Administrador para Colaborador
-- Data: 2025-11-10
-- Descrição: Renomeia todas as tabelas e constraints de "administrador" para "colaborador"

-- IMPORTANTE: Executar esta migration DEPOIS de todas as anteriores

-- 1. Remove constraints de foreign keys que dependem das tabelas que serão renomeadas
ALTER TABLE `administrador_tokens` DROP FOREIGN KEY `fk_tokens_usuario`;
ALTER TABLE `administrador_roles` DROP FOREIGN KEY `fk_roles_nivel`;
ALTER TABLE `administrador_role_permissions` DROP FOREIGN KEY `fk_role_permissions_role`;
ALTER TABLE `administrador_role_permissions` DROP FOREIGN KEY `fk_role_permissions_permission`;
ALTER TABLE `auditoria` DROP FOREIGN KEY `fk_auditoria_usuario`;
ALTER TABLE `auditoria_login` DROP FOREIGN KEY `fk_auditoria_login_usuario`;

-- 2. Renomeia as tabelas
RENAME TABLE `administrador_niveis` TO `colaborador_niveis`;
RENAME TABLE `administradores` TO `colaboradores`;
RENAME TABLE `administrador_tokens` TO `colaborador_tokens`;
RENAME TABLE `administrador_permissions` TO `colaborador_permissions`;
RENAME TABLE `administrador_roles` TO `colaborador_roles`;
RENAME TABLE `administrador_role_permissions` TO `colaborador_role_permissions`;

-- 3. Atualiza os comentários das tabelas
ALTER TABLE `colaborador_niveis` COMMENT='Níveis de acesso de colaboradores';
ALTER TABLE `colaboradores` COMMENT='Colaboradores do sistema';
ALTER TABLE `colaborador_tokens` COMMENT='Tokens JWT de colaboradores';
ALTER TABLE `colaborador_permissions` COMMENT='Permissões do sistema';
ALTER TABLE `colaborador_roles` COMMENT='Roles (funções) de colaboradores';
ALTER TABLE `colaborador_role_permissions` COMMENT='Relação entre roles e permissões';

-- 4. Atualiza comentários de colunas
ALTER TABLE `colaborador_niveis` MODIFY COLUMN `nome` VARCHAR(100) NOT NULL COMMENT 'Nome do nível';
ALTER TABLE `colaboradores` MODIFY COLUMN `nome` VARCHAR(150) NOT NULL COMMENT 'Nome completo do colaborador';
ALTER TABLE `colaborador_tokens` MODIFY COLUMN `usuario_id` INT UNSIGNED NOT NULL COMMENT 'ID do colaborador';

-- 5. Recria as constraints de foreign keys com novos nomes
ALTER TABLE `colaborador_tokens`
    ADD CONSTRAINT `fk_tokens_colaborador`
        FOREIGN KEY (`usuario_id`)
        REFERENCES `colaboradores` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE;

ALTER TABLE `colaborador_roles`
    ADD CONSTRAINT `fk_roles_nivel`
        FOREIGN KEY (`nivel_id`)
        REFERENCES `colaborador_niveis` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE;

ALTER TABLE `colaborador_role_permissions`
    ADD CONSTRAINT `fk_role_permissions_role`
        FOREIGN KEY (`role_id`)
        REFERENCES `colaborador_roles` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE;

ALTER TABLE `colaborador_role_permissions`
    ADD CONSTRAINT `fk_role_permissions_permission`
        FOREIGN KEY (`permission_id`)
        REFERENCES `colaborador_permissions` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE;

ALTER TABLE `auditoria`
    ADD CONSTRAINT `fk_auditoria_colaborador`
        FOREIGN KEY (`usuario_id`)
        REFERENCES `colaboradores` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

ALTER TABLE `auditoria_login`
    ADD CONSTRAINT `fk_auditoria_login_colaborador`
        FOREIGN KEY (`usuario_id`)
        REFERENCES `colaboradores` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

-- 6. Atualiza dados de permissões que fazem referência a "administradores"
UPDATE `colaborador_permissions`
SET `modulo` = 'colaboradores',
    `nome` = REPLACE(`nome`, 'Administradores', 'Colaboradores'),
    `codigo` = REPLACE(`codigo`, 'admins.', 'colaboradores.'),
    `descricao` = REPLACE(`descricao`, 'administradores', 'colaboradores')
WHERE `modulo` = 'administradores';
