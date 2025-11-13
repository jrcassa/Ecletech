-- ==============================================================================
-- Migration: Criar tabela de economia de abastecimentos
-- Descrição: Comparativos de economia entre períodos
-- Data: 2025-11-13
-- ==============================================================================

-- Criar tabela frotas_abastecimentos_economia
CREATE TABLE IF NOT EXISTS frotas_abastecimentos_economia (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    frota_id INT UNSIGNED NOT NULL COMMENT 'ID do veículo',

    -- Período
    periodo_inicio DATE NOT NULL COMMENT 'Data inicial do período',
    periodo_fim DATE NOT NULL COMMENT 'Data final do período',

    -- Dados agregados
    total_abastecimentos INT DEFAULT 0 COMMENT 'Total de abastecimentos no período',
    total_litros DECIMAL(12,3) DEFAULT 0 COMMENT 'Total de litros abastecidos',
    total_valor DECIMAL(12,2) DEFAULT 0 COMMENT 'Valor total gasto',
    total_km_percorrido DECIMAL(12,2) DEFAULT 0 COMMENT 'Total de KM percorridos',

    -- Médias
    consumo_medio DECIMAL(6,2) DEFAULT 0 COMMENT 'Consumo médio km/l do período',
    custo_medio_por_km DECIMAL(6,2) DEFAULT 0 COMMENT 'Custo médio por KM',
    custo_medio_por_litro DECIMAL(6,3) DEFAULT 0 COMMENT 'Custo médio por litro',

    -- Comparativo
    economia_vs_periodo_anterior DECIMAL(8,2) NULL COMMENT 'Percentual de economia/gasto comparado',

    -- Auditoria
    calculado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de cálculo',

    -- Índices
    INDEX idx_frota_id (frota_id),
    INDEX idx_periodo (periodo_inicio, periodo_fim),
    INDEX idx_calculado_em (calculado_em),
    UNIQUE INDEX uk_frota_periodo (frota_id, periodo_inicio, periodo_fim),

    -- Chave estrangeira
    CONSTRAINT fk_frotas_abastecimentos_economia_frota
        FOREIGN KEY (frota_id)
        REFERENCES frotas(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Análise de economia por período';

-- ==============================================================================
-- Comentários sobre uso:
-- ==============================================================================
-- Calculado automaticamente (mensal/semanal)
-- Usado para relatórios e análise de tendências
-- ==============================================================================
