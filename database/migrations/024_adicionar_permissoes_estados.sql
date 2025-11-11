-- Migration: Adicionar permissões do módulo Estados
-- Descrição: Cria as permissões para visualizar, criar, editar e deletar estados

-- Inserir permissões para o módulo estados
INSERT INTO colaborador_permissions (nome, codigo, descricao, modulo, ativo, criado_em) VALUES
('Visualizar Estados', 'estado.visualizar', 'Permite visualizar a lista de estados', 'estado', 1, NOW()),
('Criar Estado', 'estado.criar', 'Permite criar novos estados', 'estado', 1, NOW()),
('Editar Estado', 'estado.editar', 'Permite editar estados existentes', 'estado', 1, NOW()),
('Deletar Estado', 'estado.deletar', 'Permite deletar estados', 'estado', 1, NOW())
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    modulo = VALUES(modulo),
    ativo = VALUES(ativo),
    atualizado_em = NOW();

-- Associar todas as permissões de estado ao role Super Admin (ID 1)
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 1, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 'estado'
AND NOT EXISTS (
    SELECT 1 FROM colaborador_role_permissions crp
    WHERE crp.role_id = 1 AND crp.permission_id = p.id
);

-- Associar permissões de visualizar e criar ao role Admin (ID 2)
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 2, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 'estado'
AND p.codigo IN ('estado.visualizar', 'estado.criar', 'estado.editar')
AND NOT EXISTS (
    SELECT 1 FROM colaborador_role_permissions crp
    WHERE crp.role_id = 2 AND crp.permission_id = p.id
);
