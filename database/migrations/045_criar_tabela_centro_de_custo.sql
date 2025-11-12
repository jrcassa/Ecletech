-- Migration: 045 - Criar tabela de centro de custo
-- Descrição: Cria a estrutura para gerenciar centros de custo
-- Data: 2025-11-12
-- Autor: Sistema

-- =====================================================
-- TABELA: centro_de_custo
-- =====================================================

CREATE TABLE IF NOT EXISTS `centro_de_custo` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_id` VARCHAR(50) DEFAULT NULL COMMENT 'ID do sistema externo',
  `nome` VARCHAR(200) NOT NULL COMMENT 'Nome do centro de custo',
  `ativo` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Centro de custo ativo ou inativo',
  `observacoes` TEXT DEFAULT NULL COMMENT 'Observações adicionais',
  `cadastrado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modificado_em` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `deletado_em` DATETIME NULL COMMENT 'Soft delete',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_external_id` (`external_id`),
  UNIQUE KEY `uk_nome` (`nome`),
  KEY `idx_nome` (`nome`),
  KEY `idx_ativo` (`ativo`),
  KEY `idx_deletado_em` (`deletado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Centros de custo para controle gerencial';

-- =====================================================
-- PERMISSÕES ACL
-- =====================================================

-- Inserir permissões para centro de custo
INSERT INTO `colaborador_permissions` (`nome`, `codigo`, `descricao`, `modulo`, `ativo`, `criado_em`) VALUES
('Visualizar Centro de Custo', 'centro_de_custo.visualizar', 'Permite visualizar centros de custo', 'centro_de_custo', 1, NOW()),
('Criar Centro de Custo', 'centro_de_custo.criar', 'Permite cadastrar novos centros de custo', 'centro_de_custo', 1, NOW()),
('Editar Centro de Custo', 'centro_de_custo.editar', 'Permite editar informações de centros de custo', 'centro_de_custo', 1, NOW()),
('Deletar Centro de Custo', 'centro_de_custo.deletar', 'Permite remover centros de custo (soft delete)', 'centro_de_custo', 1, NOW());

-- =====================================================
-- ATRIBUIR PERMISSÕES AOS ROLES
-- =====================================================

-- Atribuir todas as permissões de centro de custo ao superadmin
INSERT INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`)
SELECT
    r.id,
    p.id,
    NOW()
FROM `colaborador_roles` r
CROSS JOIN `colaborador_permissions` p
WHERE r.codigo = 'superadmin_full'
AND p.modulo = 'centro_de_custo'
AND NOT EXISTS (
    SELECT 1 FROM `colaborador_role_permissions` rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
);

-- Atribuir todas as permissões de centro de custo ao admin
INSERT INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`)
SELECT
    r.id,
    p.id,
    NOW()
FROM `colaborador_roles` r
CROSS JOIN `colaborador_permissions` p
WHERE r.codigo = 'admin_full'
AND p.modulo = 'centro_de_custo'
AND NOT EXISTS (
    SELECT 1 FROM `colaborador_role_permissions` rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
);

-- Atribuir permissões de visualizar e editar ao gerente
INSERT INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`)
SELECT
    r.id,
    p.id,
    NOW()
FROM `colaborador_roles` r
CROSS JOIN `colaborador_permissions` p
WHERE r.codigo = 'gerente_usuarios'
AND p.modulo = 'centro_de_custo'
AND p.codigo IN ('centro_de_custo.visualizar', 'centro_de_custo.editar')
AND NOT EXISTS (
    SELECT 1 FROM `colaborador_role_permissions` rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
);

-- =====================================================
-- DADOS DE EXEMPLO (OPCIONAL - REMOVER EM PRODUÇÃO)
-- =====================================================

-- Exemplos de centros de custo
INSERT INTO `centro_de_custo` (`external_id`, `nome`, `ativo`, `observacoes`) VALUES
('1', 'Centro de Custo 01', 1, 'Centro de custo exemplo'),
('2', 'Administrativo', 1, 'Despesas administrativas'),
('3', 'Vendas', 1, 'Despesas com vendas'),
('4', 'Marketing', 1, 'Despesas com marketing'),
('5', 'Produção', 1, 'Custos de produção');
