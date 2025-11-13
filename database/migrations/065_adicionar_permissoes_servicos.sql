-- Migration: Adicionar permissões ACL para o módulo Serviços
-- Data: 2025-11-13
-- Descrição: Adiciona as permissões necessárias para gerenciar serviços

-- Inserir permissões para o módulo Serviços
INSERT INTO colaborador_permissions (nome, codigo, descricao, modulo, ativo, criado_em) VALUES
('Visualizar Serviços', 'servicos.visualizar', 'Permite visualizar listagem e detalhes de serviços', 'servicos', 1, NOW()),
('Criar Serviços', 'servicos.criar', 'Permite criar novos serviços', 'servicos', 1, NOW()),
('Editar Serviços', 'servicos.editar', 'Permite editar serviços existentes', 'servicos', 1, NOW()),
('Deletar Serviços', 'servicos.deletar', 'Permite deletar serviços (soft delete)', 'servicos', 1, NOW())
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    modulo = VALUES(modulo),
    ativo = VALUES(ativo),
    atualizado_em = NOW();

-- Associar permissões ao papel de Super Admin (ID 1)
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 1, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 'servicos'
AND p.codigo IN ('servicos.visualizar', 'servicos.criar', 'servicos.editar', 'servicos.deletar')
AND NOT EXISTS (
    SELECT 1
    FROM colaborador_role_permissions crp
    WHERE crp.role_id = 1
    AND crp.permission_id = p.id
);

-- Associar permissões ao papel de Admin (ID 2)
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 2, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 'servicos'
AND p.codigo IN ('servicos.visualizar', 'servicos.criar', 'servicos.editar', 'servicos.deletar')
AND NOT EXISTS (
    SELECT 1
    FROM colaborador_role_permissions crp
    WHERE crp.role_id = 2
    AND crp.permission_id = p.id
);

-- Comentário sobre as permissões
-- servicos.visualizar: Permite visualizar listagem e detalhes de serviços
-- servicos.criar: Permite criar novos serviços
-- servicos.editar: Permite editar serviços existentes
-- servicos.deletar: Permite deletar serviços (soft delete)
