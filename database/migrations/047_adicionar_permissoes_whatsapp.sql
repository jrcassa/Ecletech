-- Migration: Adicionar permissões ACL para o módulo WhatsApp
-- Data: 2025-11-12
-- Descrição: Adiciona as permissões necessárias para gerenciar o sistema WhatsApp

-- Inserir permissões para o módulo WhatsApp
INSERT INTO colaborador_permissions (nome, codigo, descricao, modulo, ativo, criado_em) VALUES
('Acessar WhatsApp', 'whatsapp.acessar', 'Permite visualizar painel, status, fila e histórico do WhatsApp', 'whatsapp', 1, NOW()),
('Alterar WhatsApp', 'whatsapp.alterar', 'Permite enviar mensagens, desconectar, processar fila e configurar o WhatsApp', 'whatsapp', 1, NOW()),
('Deletar WhatsApp', 'whatsapp.deletar', 'Permissão de deletar (SEMPRE BLOQUEADA por segurança)', 'whatsapp', 0, NOW())
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    modulo = VALUES(modulo),
    ativo = VALUES(ativo),
    atualizado_em = NOW();

-- Associar permissões ao papel de Super Admin (ID 1)
-- Verifica se o papel existe antes de inserir
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 1, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 'whatsapp'
AND p.codigo IN ('whatsapp.acessar', 'whatsapp.alterar')
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
WHERE p.modulo = 'whatsapp'
AND p.codigo IN ('whatsapp.acessar', 'whatsapp.alterar')
AND NOT EXISTS (
    SELECT 1
    FROM colaborador_role_permissions crp
    WHERE crp.role_id = 2
    AND crp.permission_id = p.id
);

-- Comentário sobre as permissões
-- whatsapp.acessar: Permite visualizar o painel, status da conexão, fila de mensagens e histórico
-- whatsapp.alterar: Permite enviar mensagens, gerenciar conexão, processar fila e alterar configurações
-- whatsapp.deletar: Permissão SEMPRE INATIVA por segurança - deletar mensagens/histórico não é permitido
--
-- IMPORTANTE: A permissão whatsapp.deletar existe apenas para manter o padrão do sistema ACL,
-- mas está marcada como inativa (ativo = 0) e NÃO deve ser atribuída a nenhum usuário ou role.
-- O middleware sempre bloqueia operações de DELETE no módulo WhatsApp.
