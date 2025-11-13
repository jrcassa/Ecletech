-- =====================================================
-- MIGRATION 052: Adicionar Permissões de Email
-- Data: 2025-01-13
-- Descrição: Cria permissões ACL para o sistema de email
-- Padrão: Seguindo estrutura do WhatsApp (migration 047)
-- =====================================================

-- =====================================================
-- INSERIR PERMISSÕES
-- =====================================================

INSERT INTO colaborador_permissions (nome, codigo, descricao, modulo, ativo, criado_em) VALUES
('Acessar Email', 'email.acessar', 'Permite visualizar painel, fila, histórico e configurações de email', 'email', 1, NOW()),
('Alterar Email', 'email.alterar', 'Permite enviar emails, processar fila e alterar configurações', 'email', 1, NOW()),
('Deletar Email', 'email.deletar', 'Permissão de deletar (SEMPRE BLOQUEADA por segurança)', 'email', 0, NOW())
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    modulo = VALUES(modulo),
    ativo = VALUES(ativo),
    atualizado_em = NOW();

-- =====================================================
-- ATRIBUIR PERMISSÕES AOS PAPÉIS PADRÃO
-- =====================================================

-- Associar permissões ao papel de Super Admin (ID 1)
-- Verifica se o papel existe antes de inserir
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 1, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 'email'
AND p.codigo IN ('email.acessar', 'email.alterar')
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
WHERE p.modulo = 'email'
AND p.codigo IN ('email.acessar', 'email.alterar')
AND NOT EXISTS (
    SELECT 1
    FROM colaborador_role_permissions crp
    WHERE crp.role_id = 2
    AND crp.permission_id = p.id
);

-- =====================================================
-- OBSERVAÇÕES
-- =====================================================
--
-- PERMISSÕES:
-- - email.acessar: Ver painel, fila, histórico (somente leitura)
-- - email.alterar: Enviar emails, alterar configs, processar fila
-- - email.deletar: SEMPRE BLOQUEADA (ativo=0)
--
-- ATRIBUIÇÃO PADRÃO:
-- - Super Admin e Admin: acessar + alterar
-- - Outras roles: configurar manualmente conforme necessidade
--
-- VALIDAÇÃO:
-- - Controllers validam permissões via middleware ACL
-- - JavaScript valida e desabilita UI se sem permissão
-- - Operações DELETE são bloqueadas mesmo com permissão
--
-- IMPORTANTE: A permissão email.deletar existe apenas para manter o padrão do sistema ACL,
-- mas está marcada como inativa (ativo = 0) e NÃO deve ser atribuída a nenhum usuário ou role.
-- O middleware sempre bloqueia operações de DELETE no módulo Email.
--
-- =====================================================

-- =====================================================
-- FIM DA MIGRATION
-- =====================================================
