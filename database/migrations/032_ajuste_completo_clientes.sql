-- =====================================================
-- Migration: Ajuste completo do módulo de clientees
-- Descrição: Cria tabela clientees_enderecos e associa permissões aos roles
-- Data: 2025-11-11
-- =====================================================

-- =====================================================
-- 1. Criar tabela clientees_enderecos (estava faltando)
-- =====================================================

CREATE TABLE IF NOT EXISTS `clientees_enderecos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cliente_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID do cliente',
  `cep` VARCHAR(10) DEFAULT NULL COMMENT 'CEP',
  `logradouro` VARCHAR(200) DEFAULT NULL COMMENT 'Rua, avenida, etc',
  `numero` VARCHAR(20) DEFAULT NULL COMMENT 'Número do endereço',
  `complemento` VARCHAR(100) DEFAULT NULL COMMENT 'Complemento (apto, sala, etc)',
  `bairro` VARCHAR(100) DEFAULT NULL COMMENT 'Bairro',
  `cidade_id` INT UNSIGNED DEFAULT NULL COMMENT 'ID da cidade (referência)',
  `estado` VARCHAR(2) DEFAULT NULL COMMENT 'Sigla do estado (UF)',

  -- Campos padrão do sistema
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de cadastro',
  `atualizado_em` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de atualização',

  PRIMARY KEY (`id`),
  KEY `idx_cliente_id` (`cliente_id`),
  KEY `idx_cidade_id` (`cidade_id`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `fk_clientees_enderecos_cliente`
    FOREIGN KEY (`cliente_id`)
    REFERENCES `clientees` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_clientees_enderecos_cidade`
    FOREIGN KEY (`cidade_id`)
    REFERENCES `cidades` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Endereços dos clientees';

-- =====================================================
-- 2. Associar permissões de clientees aos roles
-- =====================================================

-- Obter IDs das permissões de clientees
SET @perm_cliente_visualizar = (SELECT id FROM colaborador_permissions WHERE codigo = 'cliente.visualizar');
SET @perm_cliente_criar = (SELECT id FROM colaborador_permissions WHERE codigo = 'cliente.criar');
SET @perm_cliente_editar = (SELECT id FROM colaborador_permissions WHERE codigo = 'cliente.editar');
SET @perm_cliente_deletar = (SELECT id FROM colaborador_permissions WHERE codigo = 'cliente.deletar');

-- Obter IDs dos roles
SET @role_superadmin = (SELECT id FROM colaborador_roles WHERE codigo = 'superadmin_full');
SET @role_admin_full = (SELECT id FROM colaborador_roles WHERE codigo = 'admin_full');
SET @role_gerente = (SELECT id FROM colaborador_roles WHERE codigo = 'gerente_usuarios');
SET @role_full_access = (SELECT id FROM colaborador_roles WHERE codigo = 'full_access_nivel_2');

-- Atribuir todas as permissões ao Super Admin (role_id = 1)
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_superadmin, @perm_cliente_visualizar, NOW()),
(@role_superadmin, @perm_cliente_criar, NOW()),
(@role_superadmin, @perm_cliente_editar, NOW()),
(@role_superadmin, @perm_cliente_deletar, NOW());

-- Atribuir todas as permissões ao Admin Full Access (role_id = 2)
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_admin_full, @perm_cliente_visualizar, NOW()),
(@role_admin_full, @perm_cliente_criar, NOW()),
(@role_admin_full, @perm_cliente_editar, NOW()),
(@role_admin_full, @perm_cliente_deletar, NOW());

-- Atribuir permissões de visualizar e editar ao Gerente (role_id = 3)
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_gerente, @perm_cliente_visualizar, NOW()),
(@role_gerente, @perm_cliente_editar, NOW());

-- Atribuir todas as permissões ao Full Access Nivel 2 (role_id = 6)
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_full_access, @perm_cliente_visualizar, NOW()),
(@role_full_access, @perm_cliente_criar, NOW()),
(@role_full_access, @perm_cliente_editar, NOW()),
(@role_full_access, @perm_cliente_deletar, NOW());

-- =====================================================
-- 3. Verificar e corrigir permissões existentes
-- =====================================================

-- Adicionar permissões de cidades ao role Admin Full Access (estavam faltando)
SET @perm_cidade_deletar = (SELECT id FROM colaborador_permissions WHERE codigo = 'cidade.deletar');
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_admin_full, @perm_cidade_deletar, NOW());

-- Adicionar permissões de situações de vendas ao role Admin Full Access (estavam faltando)
SET @perm_situacao_deletar = (SELECT id FROM colaborador_permissions WHERE codigo = 'situacao_venda.deletar');
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_admin_full, @perm_situacao_deletar, NOW());

-- Adicionar permissões de tipos de endereços ao role Admin Full Access (estavam faltando)
SET @perm_tipo_endereco_deletar = (SELECT id FROM colaborador_permissions WHERE codigo = 'tipo_endereco.deletar');
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_admin_full, @perm_tipo_endereco_deletar, NOW());

-- Adicionar permissões de tipos de contatos ao role Admin Full Access (estavam faltando)
SET @perm_tipo_contato_deletar = (SELECT id FROM colaborador_permissions WHERE codigo = 'tipo_contato.deletar');
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_admin_full, @perm_tipo_contato_deletar, NOW());

-- Adicionar permissões de estados ao role Admin Full Access (estavam faltando)
SET @perm_estado_deletar = (SELECT id FROM colaborador_permissions WHERE codigo = 'estado.deletar');
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`) VALUES
(@role_admin_full, @perm_estado_deletar, NOW());

-- =====================================================
-- 4. Adicionar permissões faltantes aos roles Full Access
-- =====================================================

-- Adicionar todas as permissões faltantes ao role Full Access (role_id = 6)
INSERT IGNORE INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`)
SELECT @role_full_access, id, NOW()
FROM colaborador_permissions
WHERE id NOT IN (
    SELECT permission_id
    FROM colaborador_role_permissions
    WHERE role_id = @role_full_access
)
AND ativo = 1;

-- =====================================================
-- RESUMO DA MIGRATION
-- =====================================================
-- 1. ✅ Criada tabela clientees_enderecos
-- 2. ✅ Foreign keys para clientees e cidades
-- 3. ✅ Permissões de clientees associadas aos roles
-- 4. ✅ Permissões deletar faltantes adicionadas aos roles admin
-- 5. ✅ Role Full Access atualizado com todas as permissões
-- =====================================================
