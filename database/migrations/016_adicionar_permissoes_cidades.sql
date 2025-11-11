-- Migration: Adicionar permissões do módulo Cidades
-- Descrição: Cria as permissões para visualizar, criar, editar e deletar cidades

-- Inserir permissões para o módulo cidades
INSERT INTO colaborador_permissions (nome, codigo, descricao, modulo, ativo, criado_em) VALUES
('Visualizar Cidades', 'cidade.visualizar', 'Permite visualizar a lista de cidades', 'cidade', 1, NOW()),
('Criar Cidade', 'cidade.criar', 'Permite criar novas cidades', 'cidade', 1, NOW()),
('Editar Cidade', 'cidade.editar', 'Permite editar cidades existentes', 'cidade', 1, NOW()),
('Deletar Cidade', 'cidade.deletar', 'Permite deletar cidades', 'cidade', 1, NOW())
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    modulo = VALUES(modulo),
    ativo = VALUES(ativo),
    atualizado_em = NOW();

-- Associar todas as permissões de cidade ao role Super Admin (ID 1)
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 1, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 'cidade'
AND NOT EXISTS (
    SELECT 1 FROM colaborador_role_permissions crp
    WHERE crp.role_id = 1 AND crp.permission_id = p.id
);

-- Associar permissões de visualizar e criar ao role Admin (ID 2)
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 2, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 'cidade'
AND p.codigo IN ('cidade.visualizar', 'cidade.criar', 'cidade.editar')
AND NOT EXISTS (
    SELECT 1 FROM colaborador_role_permissions crp
    WHERE crp.role_id = 2 AND crp.permission_id = p.id
);
