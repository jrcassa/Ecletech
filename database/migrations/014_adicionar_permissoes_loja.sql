-- Migration: Adicionar permissões ACL para o módulo de Loja
-- Data: 2025-11-11
-- Descrição: Adiciona as permissões necessárias para gerenciar informações da loja

-- Inserir permissões para o módulo de loja
INSERT INTO colaborador_permissions (nome, descricao, modulo, criado_em) VALUES
('loja.visualizar', 'Visualizar informações da loja', 'loja', NOW()),
('loja.editar', 'Editar informações da loja', 'loja', NOW())
ON DUPLICATE KEY UPDATE
    descricao = VALUES(descricao),
    modulo = VALUES(modulo),
    atualizado_em = NOW();

-- Associar permissões ao papel de Super Admin (ID 1)
-- Verifica se o papel existe antes de inserir
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 1, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 'loja'
AND NOT EXISTS (
    SELECT 1
    FROM colaborador_role_permissions crp
    WHERE crp.role_id = 1
    AND crp.permission_id = p.id
);

-- Associar permissões ao papel de Admin (ID 2)
-- Verifica se o papel existe antes de inserir
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 2, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 'loja'
AND NOT EXISTS (
    SELECT 1
    FROM colaborador_role_permissions crp
    WHERE crp.role_id = 2
    AND crp.permission_id = p.id
);

-- Comentário sobre as permissões
-- loja.visualizar: Permite visualizar as informações da loja (qualquer usuário autenticado pode ter)
-- loja.editar: Permite editar as informações da loja (apenas administradores)
