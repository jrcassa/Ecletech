-- Migration: 044 - Criar tabela de plano de contas
-- Descrição: Cria a estrutura para gerenciar plano de contas contábil
-- Data: 2025-11-12
-- Autor: Sistema

-- =====================================================
-- TABELA: plano_de_contas
-- =====================================================

CREATE TABLE IF NOT EXISTS `plano_de_contas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_id` VARCHAR(50) DEFAULT NULL COMMENT 'ID do sistema externo',
  `conta_mae_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID da conta mãe (hierarquia)',
  `classificacao` VARCHAR(50) NOT NULL COMMENT 'Classificação contábil (ex: 1.1.1)',
  `tipo` ENUM('D', 'C') NOT NULL COMMENT 'Tipo: D=Débito, C=Crédito',
  `nome` VARCHAR(200) NOT NULL COMMENT 'Nome da conta',
  `nome_tipo` VARCHAR(100) DEFAULT NULL COMMENT 'Nome do tipo (ex: Pagamentos, Receitas)',
  `ativo` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Conta ativa ou inativa',
  `observacoes` TEXT DEFAULT NULL COMMENT 'Observações adicionais',
  `cadastrado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modificado_em` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `deletado_em` DATETIME NULL COMMENT 'Soft delete',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_external_id` (`external_id`),
  UNIQUE KEY `uk_classificacao` (`classificacao`),
  KEY `idx_conta_mae_id` (`conta_mae_id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_nome` (`nome`),
  KEY `idx_ativo` (`ativo`),
  KEY `idx_deletado_em` (`deletado_em`),
  CONSTRAINT `fk_plano_de_contas_conta_mae` FOREIGN KEY (`conta_mae_id`) REFERENCES `plano_de_contas` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Plano de contas contábil';

-- =====================================================
-- PERMISSÕES ACL
-- =====================================================

-- Inserir permissões para plano de contas
INSERT INTO `colaborador_permissions` (`nome`, `codigo`, `descricao`, `modulo`, `ativo`, `criado_em`) VALUES
('Visualizar Plano de Contas', 'plano_de_contas.visualizar', 'Permite visualizar o plano de contas', 'plano_de_contas', 1, NOW()),
('Criar Plano de Contas', 'plano_de_contas.criar', 'Permite cadastrar novas contas no plano de contas', 'plano_de_contas', 1, NOW()),
('Editar Plano de Contas', 'plano_de_contas.editar', 'Permite editar informações do plano de contas', 'plano_de_contas', 1, NOW()),
('Deletar Plano de Contas', 'plano_de_contas.deletar', 'Permite remover contas do plano de contas (soft delete)', 'plano_de_contas', 1, NOW());

-- =====================================================
-- ATRIBUIR PERMISSÕES AOS ROLES
-- =====================================================

-- Atribuir todas as permissões de plano de contas ao superadmin
INSERT INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`)
SELECT
    r.id,
    p.id,
    NOW()
FROM `colaborador_roles` r
CROSS JOIN `colaborador_permissions` p
WHERE r.codigo = 'superadmin_full'
AND p.modulo = 'plano_de_contas'
AND NOT EXISTS (
    SELECT 1 FROM `colaborador_role_permissions` rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
);

-- Atribuir todas as permissões de plano de contas ao admin
INSERT INTO `colaborador_role_permissions` (`role_id`, `permission_id`, `criado_em`)
SELECT
    r.id,
    p.id,
    NOW()
FROM `colaborador_roles` r
CROSS JOIN `colaborador_permissions` p
WHERE r.codigo = 'admin_full'
AND p.modulo = 'plano_de_contas'
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
AND p.modulo = 'plano_de_contas'
AND p.codigo IN ('plano_de_contas.visualizar', 'plano_de_contas.editar')
AND NOT EXISTS (
    SELECT 1 FROM `colaborador_role_permissions` rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
);

-- =====================================================
-- DADOS DE EXEMPLO (OPCIONAL - REMOVER EM PRODUÇÃO)
-- =====================================================

-- Exemplos de contas principais
INSERT INTO `plano_de_contas` (`external_id`, `conta_mae_id`, `classificacao`, `tipo`, `nome`, `nome_tipo`, `ativo`) VALUES
('1', NULL, '1', 'D', 'Ativo', 'Ativo', 1),
('2', NULL, '2', 'C', 'Passivo', 'Passivo', 1),
('3', NULL, '3', 'C', 'Patrimônio Líquido', 'Patrimônio Líquido', 1),
('4', NULL, '4', 'C', 'Receitas', 'Receitas', 1),
('5', NULL, '5', 'D', 'Despesas', 'Despesas', 1);

-- Obter IDs das contas principais
SET @ativo_id = (SELECT id FROM plano_de_contas WHERE classificacao = '1' AND deletado_em IS NULL);
SET @passivo_id = (SELECT id FROM plano_de_contas WHERE classificacao = '2' AND deletado_em IS NULL);
SET @despesas_id = (SELECT id FROM plano_de_contas WHERE classificacao = '5' AND deletado_em IS NULL);

-- Exemplos de subcontas
INSERT INTO `plano_de_contas` (`conta_mae_id`, `classificacao`, `tipo`, `nome`, `nome_tipo`, `ativo`) VALUES
(@ativo_id, '1.1', 'D', 'Ativo Circulante', 'Ativo Circulante', 1),
(@ativo_id, '1.2', 'D', 'Ativo Não Circulante', 'Ativo Não Circulante', 1),
(@passivo_id, '2.1', 'C', 'Passivo Circulante', 'Passivo Circulante', 1),
(@passivo_id, '2.2', 'C', 'Passivo Não Circulante', 'Passivo Não Circulante', 1),
(@despesas_id, '5.1', 'D', 'Despesas Operacionais', 'Despesas Operacionais', 1),
(@despesas_id, '5.2', 'D', 'Despesas Administrativas', 'Despesas Administrativas', 1);

-- Obter ID de uma subconta para exemplo
SET @ativo_circulante_id = (SELECT id FROM plano_de_contas WHERE classificacao = '1.1' AND deletado_em IS NULL);

-- Exemplo de sub-subconta
INSERT INTO `plano_de_contas` (`conta_mae_id`, `classificacao`, `tipo`, `nome`, `nome_tipo`, `ativo`) VALUES
(@ativo_circulante_id, '1.1.1', 'D', 'Caixa e Equivalentes de Caixa', 'Caixa', 1);
