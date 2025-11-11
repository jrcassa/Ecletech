-- Migration: Criar tabela tipos_contatos
-- Descrição: Cria a tabela de tipos de contatos com campos id, external_id e nome

CREATE TABLE IF NOT EXISTS tipos_contatos (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    external_id VARCHAR(50) NULL COMMENT 'ID externo do sistema',
    nome VARCHAR(100) NOT NULL COMMENT 'Nome do tipo de contato',

    -- Campos padrão do sistema
    ativo BOOLEAN DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deletado_em DATETIME NULL,

    -- Índices
    INDEX idx_external_id (external_id),
    INDEX idx_nome (nome),
    INDEX idx_ativo (ativo),
    INDEX idx_deletado_em (deletado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tabela de tipos de contatos';
