-- Migration: 086 - Criar tabela dashboards
-- Descrição: Cria tabela para armazenar dashboards customizáveis dos colaboradores
-- Data: 2025-01-14

CREATE TABLE IF NOT EXISTS dashboards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT UNSIGNED NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    icone VARCHAR(50) NULL DEFAULT 'fa-chart-line',
    cor VARCHAR(20) NULL DEFAULT '#3498db',
    is_padrao TINYINT(1) NOT NULL DEFAULT 0,
    ordem INT NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Keys
    CONSTRAINT fk_dashboards_colaborador
        FOREIGN KEY (colaborador_id)
        REFERENCES colaboradores(id)
        ON DELETE CASCADE,

    -- Índices
    INDEX idx_dashboards_colaborador (colaborador_id),
    INDEX idx_dashboards_padrao (colaborador_id, is_padrao),

    -- Constraints
    UNIQUE KEY uk_dashboards_colaborador_nome (colaborador_id, nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
