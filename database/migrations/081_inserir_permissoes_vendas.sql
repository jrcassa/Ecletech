-- =====================================================
-- Migration: Inserir permissões ACL para vendas
-- Descrição: Adiciona permissões granulares para módulo de vendas
-- Data: 2025-11-14
-- =====================================================

-- ========================================
-- PERMISSÕES DO MÓDULO VENDAS
-- ========================================

INSERT INTO colaborador_permissions (nome, codigo, descricao, modulo, ativo, criado_em) VALUES
('Listar Vendas', 'venda.listar', 'Listar vendas', 'venda', 1, NOW()),
('Visualizar Venda', 'venda.visualizar', 'Visualizar detalhes de uma venda', 'venda', 1, NOW()),
('Criar Venda', 'venda.criar', 'Criar nova venda', 'venda', 1, NOW()),
('Editar Venda', 'venda.editar', 'Atualizar venda existente', 'venda', 1, NOW()),
('Deletar Venda', 'venda.deletar', 'Deletar venda', 'venda', 1, NOW()),
('Exportar Vendas', 'venda.exportar', 'Exportar vendas para Excel/PDF', 'venda', 1, NOW()),
('Imprimir Venda', 'venda.imprimir', 'Imprimir venda/pedido', 'venda', 1, NOW()),
('Importar Vendas', 'venda.importar', 'Importar vendas de sistema externo', 'venda', 1, NOW())
ON DUPLICATE KEY UPDATE
    descricao = VALUES(descricao),
    ativo = VALUES(ativo);

-- ========================================
-- ATRIBUIR PERMISSÕES AOS ROLES
-- ========================================

-- Associar todas as permissões de venda ao role Super Admin (ID 1)
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 1, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 'venda'
AND NOT EXISTS (
    SELECT 1 FROM colaborador_role_permissions crp
    WHERE crp.role_id = 1 AND crp.permission_id = p.id
);

-- Associar permissões ao role Admin (ID 2)
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 2, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 'venda'
AND p.codigo IN ('venda.listar', 'venda.visualizar', 'venda.criar', 'venda.editar')
AND NOT EXISTS (
    SELECT 1 FROM colaborador_role_permissions crp
    WHERE crp.role_id = 2 AND crp.permission_id = p.id
);
