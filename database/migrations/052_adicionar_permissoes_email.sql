-- =====================================================
-- MIGRATION 052: Adicionar Permissões de Email
-- Data: 2025-01-13
-- Descrição: Cria permissões ACL para o sistema de email
-- Padrão: Seguindo estrutura do WhatsApp (migration 047)
-- =====================================================

-- =====================================================
-- INSERIR PERMISSÕES
-- =====================================================

-- 1. Permissão de ACESSAR (visualizar)
INSERT INTO permissoes (nome, descricao, categoria, ativo)
VALUES (
    'email.acessar',
    'Permite visualizar o painel de emails, fila, histórico e configurações (somente leitura)',
    'Email',
    1
);

-- 2. Permissão de ALTERAR (enviar e configurar)
INSERT INTO permissoes (nome, descricao, categoria, ativo)
VALUES (
    'email.alterar',
    'Permite enviar emails, processar fila manualmente e alterar configurações',
    'Email',
    1
);

-- 3. Permissão de DELETAR (bloqueada por segurança)
INSERT INTO permissoes (nome, descricao, categoria, ativo)
VALUES (
    'email.deletar',
    'Permissão bloqueada por segurança - não deve ser usada',
    'Email',
    0
);

-- =====================================================
-- ATRIBUIR PERMISSÕES AOS PAPÉIS PADRÃO
-- =====================================================

-- Super Admin (role_id = 1) - Todas as permissões exceto deletar
INSERT INTO role_permissoes (role_id, permissao_id)
SELECT 1, id FROM permissoes WHERE nome IN ('email.acessar', 'email.alterar');

-- Admin (role_id = 2) - Todas as permissões exceto deletar
INSERT INTO role_permissoes (role_id, permissao_id)
SELECT 2, id FROM permissoes WHERE nome IN ('email.acessar', 'email.alterar');

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
-- =====================================================

-- =====================================================
-- FIM DA MIGRATION
-- =====================================================
