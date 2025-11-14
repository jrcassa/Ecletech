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
-- ATRIBUIR PERMISSÕES AO ADMINISTRADOR
-- ========================================

-- Buscar ID do nível Administrador
SET @nivel_admin_id = (SELECT id FROM colaborador_niveis WHERE nome = 'Administrador' LIMIT 1);

-- Inserir permissões para o nível Administrador (se existir)
INSERT INTO colaborador_niveis_permissions (nivel_id, permission_id)
SELECT
    @nivel_admin_id,
    cp.id
FROM
    colaborador_permissions cp
WHERE
    cp.modulo = 'venda'
    AND @nivel_admin_id IS NOT NULL
    AND NOT EXISTS (
        SELECT 1
        FROM colaborador_niveis_permissions cnp
        WHERE cnp.nivel_id = @nivel_admin_id
        AND cnp.permission_id = cp.id
    );
