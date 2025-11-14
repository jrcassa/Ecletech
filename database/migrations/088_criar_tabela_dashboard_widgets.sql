-- Migration: 087 - Criar tabela dashboard_widgets
-- Descrição: Cria tabela para armazenar widgets dos dashboards
-- Data: 2025-01-14

CREATE TABLE IF NOT EXISTS dashboard_widgets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dashboard_id INT UNSIGNED NOT NULL,
    widget_tipo_id INT UNSIGNED NOT NULL,
    titulo VARCHAR(100) NULL,
    config JSON NULL,
    posicao_x INT NOT NULL DEFAULT 0,
    posicao_y INT NOT NULL DEFAULT 0,
    largura INT NOT NULL DEFAULT 4,
    altura INT NOT NULL DEFAULT 3,
    ordem INT NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Keys
    CONSTRAINT fk_dashboard_widgets_dashboard
        FOREIGN KEY (dashboard_id)
        REFERENCES dashboards(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_dashboard_widgets_tipo
        FOREIGN KEY (widget_tipo_id)
        REFERENCES widget_tipos(id)
        ON DELETE RESTRICT,

    -- Índices
    INDEX idx_dashboard_widgets_dashboard (dashboard_id),
    INDEX idx_dashboard_widgets_tipo (widget_tipo_id),
    INDEX idx_dashboard_widgets_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
