-- Migration: Adicionar permissões do módulo Tipos de Contatos
-- Descrição: Cria as permissões para visualizar, criar, editar e deletar tipos de contatos

-- Inserir permissões para o módulo tipos_contatos
INSERT INTO colaborador_permissions (nome, codigo, descricao, modulo, ativo, criado_em) VALUES
('Visualizar Tipos de Contatos', 'tipo_contato.visualizar', 'Permite visualizar a lista de tipos de contatos', 'tipo_contato', 1, NOW()),
('Criar Tipo de Contato', 'tipo_contato.criar', 'Permite criar novos tipos de contatos', 'tipo_contato', 1, NOW()),
('Editar Tipo de Contato', 'tipo_contato.editar', 'Permite editar tipos de contatos existentes', 'tipo_contato', 1, NOW()),
('Deletar Tipo de Contato', 'tipo_contato.deletar', 'Permite deletar tipos de contatos', 'tipo_contato', 1, NOW())
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    modulo = VALUES(modulo),
    ativo = VALUES(ativo),
    atualizado_em = NOW();

-- Associar todas as permissões de tipo_contato ao role Super Admin (ID 1)
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 1, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 'tipo_contato'
AND NOT EXISTS (
    SELECT 1 FROM colaborador_role_permissions crp
    WHERE crp.role_id = 1 AND crp.permission_id = p.id
);

-- Associar permissões de visualizar e criar ao role Admin (ID 2)
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 2, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 'tipo_contato'
AND p.codigo IN ('tipo_contato.visualizar', 'tipo_contato.criar', 'tipo_contato.editar')
AND NOT EXISTS (
    SELECT 1 FROM colaborador_role_permissions crp
    WHERE crp.role_id = 2 AND crp.permission_id = p.id
);
