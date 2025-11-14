-- =====================================================
-- Migration: Inserir Permissões de Recebimentos
-- Descrição: Adiciona as permissões do módulo de
--            recebimentos no sistema de ACL
-- Data: 2025-01-14
-- =====================================================

-- Inserir permissões do módulo recebimentos
INSERT INTO colaborador_permissions (nome, codigo, descricao, modulo, ativo, criado_em) VALUES
('Visualizar Recebimentos', 'recebimento.visualizar', 'Permite visualizar a lista de recebimentos e seus detalhes', 'recebimento', 1, NOW()),
('Criar Recebimentos', 'recebimento.criar', 'Permite criar novos recebimentos', 'recebimento', 1, NOW()),
('Editar Recebimentos', 'recebimento.editar', 'Permite editar recebimentos existentes', 'recebimento', 1, NOW()),
('Deletar Recebimentos', 'recebimento.deletar', 'Permite remover recebimentos do sistema', 'recebimento', 1, NOW()),
('Baixar Recebimentos', 'recebimento.baixar', 'Permite baixar/liquidar recebimentos (marcar como recebido)', 'recebimento', 1, NOW());

-- Atribuir todas as permissões de recebimentos ao papel superadmin_full
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em)
SELECT
    r.id AS role_id,
    p.id AS permission_id,
    NOW() AS criado_em
FROM colaborador_roles r
CROSS JOIN colaborador_permissions p
WHERE r.codigo = 'superadmin_full'
  AND p.modulo = 'recebimento'
  AND NOT EXISTS (
      SELECT 1
      FROM colaborador_role_permissions rp
      WHERE rp.role_id = r.id
        AND rp.permission_id = p.id
  );

-- Mensagem de confirmação (comentário)
-- Permissões de recebimentos inseridas com sucesso!
-- Total de permissões: 5
--   - recebimento.visualizar
--   - recebimento.criar
--   - recebimento.editar
--   - recebimento.deletar
--   - recebimento.baixar
