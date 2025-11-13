-- ==============================================================================
-- Migration: Criar tabelas de relatórios de abastecimentos
-- Descrição: Configurações, logs e snapshots de relatórios automáticos
-- Data: 2025-11-13
-- ==============================================================================

-- Tabela 1: Configurações de relatórios
CREATE TABLE IF NOT EXISTS frotas_abastecimentos_relatorios_configuracoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Configuração
    colaborador_id INT UNSIGNED NOT NULL COMMENT 'ID do colaborador que recebe relatórios',
    tipo_relatorio ENUM('semanal', 'mensal') NOT NULL COMMENT 'Tipo de relatório',
    ativo BOOLEAN DEFAULT TRUE COMMENT 'Indica se configuração está ativa',

    -- Agendamento
    dia_envio_semanal ENUM('segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo') DEFAULT 'segunda' COMMENT 'Dia da semana para envio',
    dia_envio_mensal INT DEFAULT 1 COMMENT 'Dia do mês para envio (1-28)',
    hora_envio TIME DEFAULT '08:00:00' COMMENT 'Horário de envio',

    -- Personalização
    filtros_personalizados JSON NULL COMMENT 'Filtros customizados (frotas, motoristas, etc)',
    formato_relatorio ENUM('resumido', 'detalhado', 'completo') DEFAULT 'detalhado' COMMENT 'Formato do relatório',

    -- Auditoria
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    criado_por INT UNSIGNED NULL COMMENT 'Quem criou a configuração',
    atualizado_por INT UNSIGNED NULL COMMENT 'Quem atualizou',

    -- Índices
    INDEX idx_colaborador_id (colaborador_id),
    INDEX idx_tipo_relatorio (tipo_relatorio),
    INDEX idx_ativo (ativo),
    INDEX idx_dia_envio_semanal (dia_envio_semanal),
    INDEX idx_dia_envio_mensal (dia_envio_mensal),
    UNIQUE INDEX uk_colaborador_tipo (colaborador_id, tipo_relatorio),

    -- Chaves estrangeiras
    CONSTRAINT fk_frotas_abastecimentos_relatorios_config_colaborador
        FOREIGN KEY (colaborador_id)
        REFERENCES colaboradores(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_frotas_abastecimentos_relatorios_config_criado_por
        FOREIGN KEY (criado_por)
        REFERENCES colaboradores(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_frotas_abastecimentos_relatorios_config_atualizado_por
        FOREIGN KEY (atualizado_por)
        REFERENCES colaboradores(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Configurações de relatórios automáticos';

-- Tabela 2: Log de envios de relatórios
CREATE TABLE IF NOT EXISTS frotas_abastecimentos_relatorios_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Dados do relatório
    tipo_relatorio ENUM('semanal', 'mensal') NOT NULL,
    periodo_inicio DATE NOT NULL COMMENT 'Data inicial do período',
    periodo_fim DATE NOT NULL COMMENT 'Data final do período',
    destinatario_id INT UNSIGNED NOT NULL COMMENT 'ID do colaborador destinatário',
    telefone VARCHAR(20) NOT NULL COMMENT 'Telefone para envio',

    -- Conteúdo
    formato ENUM('resumido', 'detalhado', 'completo') NOT NULL,
    mensagem TEXT NOT NULL COMMENT 'Conteúdo completo enviado',
    dados_relatorio JSON NOT NULL COMMENT 'Dados estruturados do relatório',

    -- Status
    status_envio ENUM('pendente', 'enviado', 'erro', 'cancelado') DEFAULT 'pendente',
    tentativas INT DEFAULT 0,
    erro_mensagem TEXT NULL,
    enviado_em DATETIME NULL,

    -- Metadata
    tamanho_mensagem INT NULL COMMENT 'Tamanho em caracteres',
    tempo_processamento DECIMAL(6,2) NULL COMMENT 'Tempo em segundos',

    -- Auditoria
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processado_em DATETIME NULL COMMENT 'Quando relatório foi gerado',

    -- Índices
    INDEX idx_tipo_relatorio (tipo_relatorio),
    INDEX idx_periodo (periodo_inicio, periodo_fim),
    INDEX idx_destinatario_id (destinatario_id),
    INDEX idx_status_envio (status_envio),
    INDEX idx_enviado_em (enviado_em),
    INDEX idx_criado_em (criado_em),

    -- Chave estrangeira
    CONSTRAINT fk_frotas_abastecimentos_relatorios_logs_destinatario
        FOREIGN KEY (destinatario_id)
        REFERENCES colaboradores(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Log de envios de relatórios';

-- Tabela 3: Snapshots pré-calculados
CREATE TABLE IF NOT EXISTS frotas_abastecimentos_relatorios_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Período
    tipo_periodo ENUM('semanal', 'mensal') NOT NULL,
    periodo_inicio DATE NOT NULL,
    periodo_fim DATE NOT NULL,
    ano YEAR NOT NULL,
    mes INT NULL COMMENT 'Para relatórios mensais (1-12)',
    semana INT NULL COMMENT 'Para relatórios semanais (1-52)',

    -- Dados agregados gerais
    total_abastecimentos INT DEFAULT 0,
    total_litros DECIMAL(12,3) DEFAULT 0,
    total_valor DECIMAL(12,2) DEFAULT 0,
    total_km_percorrido DECIMAL(12,2) DEFAULT 0,
    consumo_medio_geral DECIMAL(6,2) DEFAULT 0,
    custo_medio_por_km DECIMAL(6,2) DEFAULT 0,
    custo_medio_por_litro DECIMAL(6,3) DEFAULT 0,

    -- Comparativos
    variacao_consumo_vs_anterior DECIMAL(6,2) NULL COMMENT 'Percentual de variação',
    variacao_custo_vs_anterior DECIMAL(6,2) NULL,
    economia_vs_anterior DECIMAL(10,2) NULL COMMENT 'Valor em R$',

    -- Alertas
    total_alertas INT DEFAULT 0,
    alertas_criticos INT DEFAULT 0,
    alertas_altos INT DEFAULT 0,

    -- Dados detalhados (JSON)
    dados_por_frota JSON NULL COMMENT 'Dados agregados por veículo',
    dados_por_motorista JSON NULL COMMENT 'Dados agregados por motorista',
    dados_por_combustivel JSON NULL COMMENT 'Dados agregados por combustível',
    ranking_consumo JSON NULL COMMENT 'Ranking de melhor consumo',
    ranking_economia JSON NULL COMMENT 'Ranking de economia',

    -- Auditoria
    calculado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tempo_calculo DECIMAL(6,2) NULL COMMENT 'Segundos para calcular',

    -- Índices
    UNIQUE INDEX uk_tipo_periodo (tipo_periodo, periodo_inicio, periodo_fim),
    INDEX idx_ano (ano),
    INDEX idx_mes (mes),
    INDEX idx_semana (semana),
    INDEX idx_calculado_em (calculado_em)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Snapshots pré-calculados para relatórios';

-- ==============================================================================
-- Comentários sobre uso:
-- ==============================================================================
-- CONFIGURACOES: Quem recebe relatórios, quando e como
-- LOGS: Histórico completo de todos os envios
-- SNAPSHOTS: Dados pré-calculados para performance
-- ==============================================================================
