-- =====================================================
-- Migration: Inserir permissões ACL para vendas
-- Descrição: Adiciona permissões granulares para módulo de vendas
-- Data: 2025-11-14
-- =====================================================

-- ========================================
-- PERMISSÕES DO MÓDULO VENDAS
-- ========================================

INSERT INTO colaborador_permissions (modulo, acao, descricao, ativo) VALUES
('venda', 'listar', 'Listar vendas', 1),
('venda', 'visualizar', 'Visualizar detalhes de uma venda', 1),
('venda', 'criar', 'Criar nova venda', 1),
('venda', 'atualizar', 'Atualizar venda existente', 1),
('venda', 'deletar', 'Deletar venda', 1),
('venda', 'exportar', 'Exportar vendas para Excel/PDF', 1),
('venda', 'imprimir', 'Imprimir venda/pedido', 1),
('venda', 'importar', 'Importar vendas de sistema externo', 1)
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
