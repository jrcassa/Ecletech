-- ==============================================================================
-- Migration: Criar tabela de alertas de abastecimentos
-- Descrição: Registra alertas de consumo anormal e situações críticas
-- Data: 2025-11-13
-- ==============================================================================

-- Criar tabela frotas_abastecimentos_alertas
CREATE TABLE IF NOT EXISTS frotas_abastecimentos_alertas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    abastecimento_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do abastecimento relacionado',

    -- Tipo e severidade do alerta
    tipo_alerta ENUM('consumo_muito_alto', 'consumo_muito_baixo', 'preco_acima_media', 'data_limite_vencendo', 'km_decrescente', 'intervalo_muito_longo') NOT NULL COMMENT 'Tipo de alerta detectado',
    severidade ENUM('baixa', 'media', 'alta', 'critica') NOT NULL COMMENT 'Nível de severidade',

    -- Descrição do alerta
    titulo VARCHAR(200) NOT NULL COMMENT 'Título resumido do alerta',
    descricao TEXT NOT NULL COMMENT 'Descrição detalhada do alerta',
    valor_esperado VARCHAR(50) NULL COMMENT 'Valor esperado ou referência',
    valor_real VARCHAR(50) NULL COMMENT 'Valor real detectado',

    -- Controle de visualização
    visualizado BOOLEAN DEFAULT FALSE COMMENT 'Indica se alerta foi visualizado',
    visualizado_por INT UNSIGNED NULL COMMENT 'ID do colaborador que visualizou',
    visualizado_em DATETIME NULL COMMENT 'Data/hora da visualização',

    -- Auditoria
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação do alerta',

    -- Índices
    INDEX idx_abastecimento_id (abastecimento_id),
    INDEX idx_tipo_alerta (tipo_alerta),
    INDEX idx_severidade (severidade),
    INDEX idx_visualizado (visualizado),
    INDEX idx_criado_em (criado_em),

    -- Chaves estrangeiras
    CONSTRAINT fk_frotas_abastecimentos_alertas_abastecimento
        FOREIGN KEY (abastecimento_id)
        REFERENCES frotas_abastecimentos(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_frotas_abastecimentos_alertas_visualizado_por
        FOREIGN KEY (visualizado_por)
        REFERENCES colaboradores(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Alertas de consumo e anomalias nos abastecimentos';

-- ==============================================================================
-- Comentários sobre uso:
-- ==============================================================================
-- Alertas são gerados automaticamente após finalização do abastecimento
-- Sistema compara com histórico e detecta anomalias
-- Alertas críticos podem acionar notificações
-- ==============================================================================
