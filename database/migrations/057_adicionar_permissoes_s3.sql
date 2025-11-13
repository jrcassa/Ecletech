-- =====================================================
-- MIGRATION 057: Adicionar Permissões de S3
-- Data: 2025-01-13
-- Descrição: Cria permissões ACL para o sistema de S3
-- Padrão: Seguindo estrutura do Email (migration 052)
-- =====================================================

-- =====================================================
-- INSERIR PERMISSÕES
-- =====================================================

INSERT INTO colaborador_permissions (nome, codigo, descricao, modulo, ativo, criado_em) VALUES
('Acessar S3', 's3.acessar', 'Permite visualizar arquivos, histórico, estatísticas e configurações do S3', 's3', 1, NOW()),
('Upload S3', 's3.upload', 'Permite fazer upload de arquivos para o S3', 's3', 1, NOW()),
('Alterar S3', 's3.alterar', 'Permite atualizar metadados de arquivos e restaurar arquivos deletados', 's3', 1, NOW()),
('Deletar S3', 's3.deletar', 'Permite deletar arquivos do S3', 's3', 1, NOW()),
('Configurar S3', 's3.configurar', 'Permite configurar credenciais AWS, testar conexão e gerenciar configurações', 's3', 1, NOW())
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
-- Super Admin tem todas as permissões
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 1, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 's3'
AND p.codigo IN ('s3.acessar', 's3.upload', 's3.alterar', 's3.deletar', 's3.configurar')
AND NOT EXISTS (
    SELECT 1
    FROM colaborador_role_permissions crp
    WHERE crp.role_id = 1
    AND crp.permission_id = p.id
);

-- Associar permissões ao papel de Admin (ID 2)
-- Admin tem todas exceto configurar (credenciais sensíveis)
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT 2, p.id, NOW()
FROM colaborador_permissions p
WHERE p.modulo = 's3'
AND p.codigo IN ('s3.acessar', 's3.upload', 's3.alterar', 's3.deletar')
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
-- - s3.acessar: Visualizar arquivos, downloads, histórico, estatísticas
-- - s3.upload: Fazer upload de arquivos e pastas
-- - s3.alterar: Atualizar metadados, categorias, entidades, restaurar deletados
-- - s3.deletar: Deletar arquivos (soft e hard delete)
-- - s3.configurar: Gerenciar credenciais AWS, configurações, testar conexão
--
-- ATRIBUIÇÃO PADRÃO:
-- - Super Admin: TODAS as permissões (inclusive configurar)
-- - Admin: Todas exceto configurar (credenciais são sensíveis)
-- - Outras roles: configurar manualmente conforme necessidade
--
-- VALIDAÇÃO:
-- - Controllers validam permissões via middleware ACL
-- - Rotas protegidas com MiddlewareAcl::requer('s3.PERMISSAO')
-- - Frontend deve validar e ocultar/desabilitar funcionalidades sem permissão
--
-- SEGURANÇA:
-- - s3.configurar contém credenciais sensíveis (AWS keys)
-- - Valores de senha são mascarados ao retornar configurações
-- - s3.deletar permite remoção permanente de arquivos
--
-- CASOS DE USO:
-- - Usuário comum: s3.acessar (somente visualização)
-- - Colaborador: s3.acessar + s3.upload (pode subir arquivos)
-- - Gerente: s3.acessar + s3.upload + s3.alterar + s3.deletar (gestão completa)
-- - Administrador: todas exceto s3.configurar
-- - Super Admin: todas incluindo s3.configurar (apenas ele mexe em credenciais)
--
-- =====================================================

-- =====================================================
-- FIM DA MIGRATION
-- =====================================================
