-- Migration: Adicionar permissões de estados
-- Descrição: Adiciona as permissões necessárias para gerenciar estados

-- Inserir permissões de estados
INSERT INTO permissoes (nome, descricao, categoria, criado_em) VALUES
('estado.visualizar', 'Visualizar estados', 'estado', NOW()),
('estado.criar', 'Criar estados', 'estado', NOW()),
('estado.editar', 'Editar estados', 'estado', NOW()),
('estado.deletar', 'Deletar estados', 'estado', NOW())
ON DUPLICATE KEY UPDATE
    descricao = VALUES(descricao),
    categoria = VALUES(categoria);

-- Obter IDs das permissões de estados
SET @perm_estado_visualizar = (SELECT id FROM permissoes WHERE nome = 'estado.visualizar');
SET @perm_estado_criar = (SELECT id FROM permissoes WHERE nome = 'estado.criar');
SET @perm_estado_editar = (SELECT id FROM permissoes WHERE nome = 'estado.editar');
SET @perm_estado_deletar = (SELECT id FROM permissoes WHERE nome = 'estado.deletar');

-- Obter IDs dos grupos
SET @grupo_admin = (SELECT id FROM grupos WHERE nome = 'Administrador');
SET @grupo_gerente = (SELECT id FROM grupos WHERE nome = 'Gerente');
SET @grupo_operador = (SELECT id FROM grupos WHERE nome = 'Operador');
SET @grupo_colaborador = (SELECT id FROM grupos WHERE nome = 'Colaborador');

-- Atribuir permissões ao grupo Administrador (acesso total)
INSERT IGNORE INTO grupos_permissoes (grupo_id, permissao_id) VALUES
(@grupo_admin, @perm_estado_visualizar),
(@grupo_admin, @perm_estado_criar),
(@grupo_admin, @perm_estado_editar),
(@grupo_admin, @perm_estado_deletar);

-- Atribuir permissões ao grupo Gerente (acesso total)
INSERT IGNORE INTO grupos_permissoes (grupo_id, permissao_id) VALUES
(@grupo_gerente, @perm_estado_visualizar),
(@grupo_gerente, @perm_estado_criar),
(@grupo_gerente, @perm_estado_editar),
(@grupo_gerente, @perm_estado_deletar);

-- Atribuir permissões ao grupo Operador (visualizar, criar e editar)
INSERT IGNORE INTO grupos_permissoes (grupo_id, permissao_id) VALUES
(@grupo_operador, @perm_estado_visualizar),
(@grupo_operador, @perm_estado_criar),
(@grupo_operador, @perm_estado_editar);

-- Atribuir permissões ao grupo Colaborador (apenas visualizar)
INSERT IGNORE INTO grupos_permissoes (grupo_id, permissao_id) VALUES
(@grupo_colaborador, @perm_estado_visualizar);
