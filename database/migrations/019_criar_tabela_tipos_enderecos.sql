-- Migration: Criar tabela tipos_enderecos
-- Descrição: Cria a tabela de tipos de endereços com campos id, id_externo e nome

CREATE TABLE IF NOT EXISTS tipos_enderecos (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    id_externo VARCHAR(50) NULL COMMENT 'ID externo do sistema',
    nome VARCHAR(100) NOT NULL COMMENT 'Nome do tipo de endereço',

    -- Campos padrão do sistema
    ativo BOOLEAN DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deletado_em DATETIME NULL,

    -- Índices
    INDEX idx_id_externo (id_externo),
    INDEX idx_nome (nome),
    INDEX idx_ativo (ativo),
    INDEX idx_deletado_em (deletado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tabela de tipos de endereços';
