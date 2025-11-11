-- Migration: Criar tabela cidades
-- Descrição: Cria a tabela de cidades com campos id, external_id, codigo (IBGE) e nome

CREATE TABLE IF NOT EXISTS cidades (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    external_id VARCHAR(50) NULL COMMENT 'ID externo do sistema',
    codigo VARCHAR(20) NOT NULL UNIQUE COMMENT 'Código IBGE da cidade',
    nome VARCHAR(150) NOT NULL COMMENT 'Nome da cidade',

    -- Campos padrão do sistema
    ativo BOOLEAN DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deletado_em DATETIME NULL,

    -- Índices
    INDEX idx_external_id (external_id),
    INDEX idx_codigo (codigo),
    INDEX idx_nome (nome),
    INDEX idx_ativo (ativo),
    INDEX idx_deletado_em (deletado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tabela de cidades';
