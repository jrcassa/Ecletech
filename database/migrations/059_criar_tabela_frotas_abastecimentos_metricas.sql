-- ==============================================================================
-- Migration: Criar tabela de métricas de abastecimentos
-- Descrição: Armazena métricas calculadas para consultas rápidas
-- Data: 2025-11-13
-- ==============================================================================

-- Criar tabela frotas_abastecimentos_metricas
CREATE TABLE IF NOT EXISTS frotas_abastecimentos_metricas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    abastecimento_id BIGINT UNSIGNED NOT NULL UNIQUE COMMENT 'ID do abastecimento relacionado',

    -- Métricas calculadas
    km_percorrido DECIMAL(10,2) NULL COMMENT 'KM atual - KM anterior',
    consumo_km_por_litro DECIMAL(6,2) NULL COMMENT 'km_percorrido / litros',
    custo_por_km DECIMAL(6,2) NULL COMMENT 'valor / km_percorrido',
    custo_por_litro DECIMAL(6,3) NULL COMMENT 'valor / litros',
    dias_desde_ultimo INT NULL COMMENT 'Dias entre este e último abastecimento',
    media_km_por_dia DECIMAL(8,2) NULL COMMENT 'km_percorrido / dias_desde_ultimo',

    -- Auditoria
    calculado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de cálculo das métricas',

    -- Índices
    UNIQUE INDEX uk_abastecimento_id (abastecimento_id),
    INDEX idx_consumo (consumo_km_por_litro),
    INDEX idx_custo_por_km (custo_por_km),
    INDEX idx_calculado_em (calculado_em),

    -- Chave estrangeira
    CONSTRAINT fk_frotas_abastecimentos_metricas_abastecimento
        FOREIGN KEY (abastecimento_id)
        REFERENCES frotas_abastecimentos(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Métricas calculadas dos abastecimentos';

-- ==============================================================================
-- Comentários sobre uso:
-- ==============================================================================
-- Métricas são calculadas automaticamente após finalização do abastecimento
-- Permite consultas rápidas sem recalcular a cada requisição
-- ==============================================================================
