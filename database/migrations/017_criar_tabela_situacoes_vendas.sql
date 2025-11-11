-- Migration: Criar tabela situacoes_vendas
-- Descrição: Cria a tabela de situações de vendas com campos id, external_id, nome e cor

CREATE TABLE IF NOT EXISTS situacoes_vendas (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    external_id VARCHAR(50) NULL COMMENT 'ID externo do sistema',
    nome VARCHAR(100) NOT NULL COMMENT 'Nome da situação de venda',
    cor VARCHAR(7) NOT NULL COMMENT 'Cor em hexadecimal (#RRGGBB)',

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
COMMENT='Tabela de situações de vendas';
