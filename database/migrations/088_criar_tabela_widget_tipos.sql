-- Migration: 088 - Criar tabela widget_tipos
-- Descrição: Cria tabela catálogo de tipos de widgets disponíveis
-- Data: 2025-01-14

CREATE TABLE IF NOT EXISTS widget_tipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    categoria VARCHAR(50) NOT NULL,
    subcategoria VARCHAR(50) NULL,
    tipo_visual ENUM(
        'grafico_linha',
        'grafico_barra',
        'grafico_pizza',
        'grafico_area',
        'grafico_donut',
        'tabela',
        'card',
        'lista',
        'contador',
        'gauge',
        'timeline',
        'feed',
        'cards_multiplos'
    ) NOT NULL,
    icone VARCHAR(50) NULL,
    imagem_preview VARCHAR(255) NULL,
    permissoes_requeridas JSON NULL,
    nivel_minimo INT NULL,
    config_schema JSON NULL,
    config_padrao JSON NULL,
    largura_padrao INT NOT NULL DEFAULT 4,
    altura_padrao INT NOT NULL DEFAULT 3,
    largura_minima INT NOT NULL DEFAULT 2,
    altura_minima INT NOT NULL DEFAULT 2,
    largura_maxima INT NOT NULL DEFAULT 12,
    altura_maxima INT NOT NULL DEFAULT 12,
    intervalo_atualizacao INT NOT NULL DEFAULT 300,
    endpoint_dados VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Índices
    INDEX idx_widget_tipos_categoria (categoria),
    INDEX idx_widget_tipos_tipo_visual (tipo_visual),
    INDEX idx_widget_tipos_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
