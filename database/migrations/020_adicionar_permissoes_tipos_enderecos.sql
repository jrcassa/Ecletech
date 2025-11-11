-- Migration: Adicionar permissões do módulo Tipos de Endereços
-- Descrição: Cria as permissões para visualizar, criar, editar e deletar tipos de endereços

-- Inserir permissões para o módulo tipos_enderecos
INSERT INTO colaborador_permissions (nome, codigo, descricao, modulo, ativo, criado_em) VALUES
('Visualizar Tipos de Endereços', 'tipo_endereco.visualizar', 'Permite visualizar a lista de tipos de endereços', 'tipo_endereco', 1, NOW()),
('Criar Tipo de Endereço', 'tipo_endereco.criar', 'Permite criar novos tipos de endereços', 'tipo_endereco', 1, NOW()),
('Editar Tipo de Endereço', 'tipo_endereco.editar', 'Permite editar tipos de endereços existentes', 'tipo_endereco', 1, NOW()),
('Deletar Tipo de Endereço', 'tipo_endereco.deletar', 'Permite deletar tipos de endereços', 'tipo_endereco', 1, NOW())
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    modulo = VALUES(modulo),
    ativo = VALUES(ativo),
    atualizado_em = NOW();

-- Associar todas as permissões de tipo_endereco ao role Super Admin (ID 1)
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 1, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 'tipo_endereco'
AND NOT EXISTS (
    SELECT 1 FROM colaborador_role_permissions crp
    WHERE crp.role_id = 1 AND crp.permission_id = p.id
);

-- Associar permissões de visualizar e criar ao role Admin (ID 2)
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 2, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 'tipo_endereco'
AND p.codigo IN ('tipo_endereco.visualizar', 'tipo_endereco.criar', 'tipo_endereco.editar')
AND NOT EXISTS (
    SELECT 1 FROM colaborador_role_permissions crp
    WHERE crp.role_id = 2 AND crp.permission_id = p.id
);
