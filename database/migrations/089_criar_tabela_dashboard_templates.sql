-- Migration: 089 - Criar tabela dashboard_templates
-- Descrição: Cria tabela de templates pré-configurados de dashboards
-- Data: 2025-01-14

CREATE TABLE IF NOT EXISTS dashboard_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    categoria VARCHAR(50) NULL,
    imagem_preview VARCHAR(255) NULL,
    nivel_minimo INT NULL,
    permissoes_requeridas JSON NULL,
    icone VARCHAR(50) NULL,
    cor VARCHAR(20) NULL,
    ordem INT NOT NULL DEFAULT 0,
    config_layout JSON NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    is_sistema TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Índices
    INDEX idx_dashboard_templates_categoria (categoria),
    INDEX idx_dashboard_templates_ativo (ativo),
    INDEX idx_dashboard_templates_sistema (is_sistema)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
