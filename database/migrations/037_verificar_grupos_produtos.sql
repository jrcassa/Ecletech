-- =====================================================
-- Script de Verificação: Grupos de Produtos
-- Descrição: Verifica se o módulo foi instalado corretamente
-- Data: 2025-11-12
-- =====================================================

-- Verifica se a tabela existe
SELECT
    'Tabela grupos_produtos' as item,
    CASE
        WHEN COUNT(*) > 0 THEN '✓ EXISTE'
        ELSE '✗ NÃO EXISTE'
    END as status
FROM information_schema.tables
WHERE table_schema = DATABASE()
AND table_name = 'grupos_produtos';

-- Verifica permissões criadas
SELECT
    'Permissões criadas' as item,
    CONCAT(COUNT(*), ' de 4') as status
FROM colaborador_permissions
WHERE codigo LIKE 'grupos_produtos.%';

-- Lista todas as permissões de grupos de produtos
SELECT
    id,
    nome,
    codigo,
    ativo
FROM colaborador_permissions
WHERE codigo LIKE 'grupos_produtos.%'
ORDER BY codigo;

-- Verifica atribuições de permissões aos roles
SELECT
    'Atribuições Super Admin' as role,
    COUNT(*) as total_permissoes
FROM colaborador_role_permissions crp
INNER JOIN colaborador_permissions cp ON crp.permission_id = cp.id
INNER JOIN colaborador_roles cr ON crp.role_id = cr.id
WHERE cr.codigo = 'superadmin_full' AND cp.codigo LIKE 'grupos_produtos.%'

UNION ALL

SELECT
    'Atribuições Admin Full' as role,
    COUNT(*) as total_permissoes
FROM colaborador_role_permissions crp
INNER JOIN colaborador_permissions cp ON crp.permission_id = cp.id
INNER JOIN colaborador_roles cr ON crp.role_id = cr.id
WHERE cr.codigo = 'admin_full' AND cp.codigo LIKE 'grupos_produtos.%'

UNION ALL

SELECT
    'Atribuições Gerente' as role,
    COUNT(*) as total_permissoes
FROM colaborador_role_permissions crp
INNER JOIN colaborador_permissions cp ON crp.permission_id = cp.id
INNER JOIN colaborador_roles cr ON crp.role_id = cr.id
WHERE cr.codigo = 'gerente_usuarios' AND cp.codigo LIKE 'grupos_produtos.%'

UNION ALL

SELECT
    'Atribuições Full Access Nivel 2' as role,
    COUNT(*) as total_permissoes
FROM colaborador_role_permissions crp
INNER JOIN colaborador_permissions cp ON crp.permission_id = cp.id
INNER JOIN colaborador_roles cr ON crp.role_id = cr.id
WHERE cr.codigo = 'full_access_nivel_2' AND cp.codigo LIKE 'grupos_produtos.%';

-- Verifica suas permissões pessoais (substitua SEU_EMAIL pelo seu email)
-- SELECT
--     c.nome as colaborador,
--     c.email,
--     GROUP_CONCAT(cp.codigo SEPARATOR ', ') as permissoes_grupos_produtos
-- FROM colaboradores c
-- INNER JOIN colaborador_role_assignments cra ON c.id = cra.colaborador_id
-- INNER JOIN colaborador_role_permissions crp ON cra.role_id = crp.role_id
-- INNER JOIN colaborador_permissions cp ON crp.permission_id = cp.id
-- WHERE c.email = 'SEU_EMAIL@exemplo.com'
-- AND cp.codigo LIKE 'grupos_produtos.%'
-- GROUP BY c.id;
