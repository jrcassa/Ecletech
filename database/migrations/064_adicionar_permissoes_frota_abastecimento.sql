-- ==============================================================================
-- Migration: Adicionar permissões de frota_abastecimento
-- Descrição: Cria permissões ACL para o módulo de abastecimentos
-- Data: 2025-11-13
-- ==============================================================================

-- Inserir permissões do módulo frota_abastecimento
INSERT INTO colaborador_permissions (nome, codigo, descricao, modulo, ativo, criado_em) VALUES
('Visualizar Abastecimentos', 'frota_abastecimento.visualizar', 'Permite visualizar TODOS os abastecimentos da frota', 'frota_abastecimento', 1, NOW()),
('Criar Ordem de Abastecimento', 'frota_abastecimento.criar', 'Permite criar ordens de abastecimento para motoristas', 'frota_abastecimento', 1, NOW()),
('Editar Abastecimentos', 'frota_abastecimento.editar', 'Permite editar ordens de abastecimento (antes de finalizar)', 'frota_abastecimento', 1, NOW()),
('Cancelar Abastecimentos', 'frota_abastecimento.cancelar', 'Permite cancelar ordens de abastecimento', 'frota_abastecimento', 1, NOW()),
('Deletar Abastecimentos', 'frota_abastecimento.deletar', 'Permite deletar (soft delete) abastecimentos', 'frota_abastecimento', 1, NOW()),
('Abastecer Veículo', 'frota_abastecimento.abastecer', 'Permite motorista ver SUAS ordens e finalizar abastecimentos', 'frota_abastecimento', 1, NOW()),
('Receber Notificação de Abastecimento', 'frota_abastecimento.receber_notificacao', 'Recebe WhatsApp quando motorista finaliza abastecimento', 'frota_abastecimento', 1, NOW()),
('Receber Relatórios Automáticos', 'frota_abastecimento.receber_relatorio', 'Recebe relatórios semanais/mensais via WhatsApp', 'frota_abastecimento', 1, NOW())
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    modulo = VALUES(modulo),
    ativo = VALUES(ativo);

-- ==============================================================================
-- Associar permissões aos roles existentes
-- ==============================================================================

-- Obter IDs das permissões recém-criadas
SET @perm_visualizar = (SELECT id FROM colaborador_permissions WHERE codigo = 'frota_abastecimento.visualizar');
SET @perm_criar = (SELECT id FROM colaborador_permissions WHERE codigo = 'frota_abastecimento.criar');
SET @perm_editar = (SELECT id FROM colaborador_permissions WHERE codigo = 'frota_abastecimento.editar');
SET @perm_cancelar = (SELECT id FROM colaborador_permissions WHERE codigo = 'frota_abastecimento.cancelar');
SET @perm_deletar = (SELECT id FROM colaborador_permissions WHERE codigo = 'frota_abastecimento.deletar');
SET @perm_abastecer = (SELECT id FROM colaborador_permissions WHERE codigo = 'frota_abastecimento.abastecer');
SET @perm_receber_notificacao = (SELECT id FROM colaborador_permissions WHERE codigo = 'frota_abastecimento.receber_notificacao');
SET @perm_receber_relatorio = (SELECT id FROM colaborador_permissions WHERE codigo = 'frota_abastecimento.receber_relatorio');

-- Obter IDs dos roles
SET @role_superadmin = (SELECT id FROM colaborador_roles WHERE codigo = 'superadmin_full');
SET @role_admin = (SELECT id FROM colaborador_roles WHERE codigo = 'admin_full');
SET @role_gerente = (SELECT id FROM colaborador_roles WHERE codigo = 'gerente_usuarios');

-- Atribuir TODAS as permissões ao Super Admin
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em) VALUES
(@role_superadmin, @perm_visualizar, NOW()),
(@role_superadmin, @perm_criar, NOW()),
(@role_superadmin, @perm_editar, NOW()),
(@role_superadmin, @perm_cancelar, NOW()),
(@role_superadmin, @perm_deletar, NOW()),
(@role_superadmin, @perm_abastecer, NOW()),
(@role_superadmin, @perm_receber_notificacao, NOW()),
(@role_superadmin, @perm_receber_relatorio, NOW())
ON DUPLICATE KEY UPDATE criado_em = NOW();

-- Atribuir TODAS as permissões ao Admin
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em) VALUES
(@role_admin, @perm_visualizar, NOW()),
(@role_admin, @perm_criar, NOW()),
(@role_admin, @perm_editar, NOW()),
(@role_admin, @perm_cancelar, NOW()),
(@role_admin, @perm_deletar, NOW()),
(@role_admin, @perm_receber_notificacao, NOW()),
(@role_admin, @perm_receber_relatorio, NOW())
ON DUPLICATE KEY UPDATE criado_em = NOW();

-- Atribuir permissões ao Gerente (visualizar, criar, editar, cancelar, receber notificações e relatórios)
INSERT INTO colaborador_role_permissions (role_id, permission_id, criado_em) VALUES
(@role_gerente, @perm_visualizar, NOW()),
(@role_gerente, @perm_criar, NOW()),
(@role_gerente, @perm_editar, NOW()),
(@role_gerente, @perm_cancelar, NOW()),
(@role_gerente, @perm_receber_notificacao, NOW()),
(@role_gerente, @perm_receber_relatorio, NOW())
ON DUPLICATE KEY UPDATE criado_em = NOW();

-- ==============================================================================
-- Observações sobre as permissões:
-- ==============================================================================
-- frota_abastecimento.visualizar: Ver TODOS os abastecimentos (admin/gerente)
-- frota_abastecimento.criar: Criar ordens para motoristas (admin/gerente)
-- frota_abastecimento.editar: Editar ordens antes de finalizar (admin/gerente)
-- frota_abastecimento.cancelar: Cancelar ordens (admin/gerente)
-- frota_abastecimento.deletar: Soft delete (apenas admin/superadmin)
-- frota_abastecimento.abastecer: Motorista finaliza SEUS abastecimentos
-- frota_abastecimento.receber_notificacao: Recebe WhatsApp ao finalizar
-- frota_abastecimento.receber_relatorio: Recebe relatórios semanais/mensais
--
-- MOTORISTAS: Recebem apenas permissão "abastecer" (atribuída manualmente)
-- ==============================================================================
