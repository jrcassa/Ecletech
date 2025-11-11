-- Migration: Adicionar permissões do módulo Situações de Vendas
-- Descrição: Cria as permissões para visualizar, criar, editar e deletar situações de vendas

-- Inserir permissões para o módulo situacoes_vendas
INSERT INTO colaborador_permissions (nome, codigo, descricao, modulo, ativo, criado_em) VALUES
('Visualizar Situações de Vendas', 'situacao_venda.visualizar', 'Permite visualizar a lista de situações de vendas', 'situacao_venda', 1, NOW()),
('Criar Situação de Venda', 'situacao_venda.criar', 'Permite criar novas situações de vendas', 'situacao_venda', 1, NOW()),
('Editar Situação de Venda', 'situacao_venda.editar', 'Permite editar situações de vendas existentes', 'situacao_venda', 1, NOW()),
('Deletar Situação de Venda', 'situacao_venda.deletar', 'Permite deletar situações de vendas', 'situacao_venda', 1, NOW())
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    modulo = VALUES(modulo),
    ativo = VALUES(ativo),
    atualizado_em = NOW();

-- Associar todas as permissões de situacao_venda ao role Super Admin (ID 1)
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 1, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 'situacao_venda'
AND NOT EXISTS (
    SELECT 1 FROM colaborador_role_permissions crp
    WHERE crp.role_id = 1 AND crp.permission_id = p.id
);

-- Associar permissões de visualizar e criar ao role Admin (ID 2)
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 2, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 'situacao_venda'
AND p.codigo IN ('situacao_venda.visualizar', 'situacao_venda.criar', 'situacao_venda.editar')
AND NOT EXISTS (
    SELECT 1 FROM colaborador_role_permissions crp
    WHERE crp.role_id = 2 AND crp.permission_id = p.id
);
