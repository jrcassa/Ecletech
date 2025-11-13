-- ==============================================================================
-- Migration: Criar tabela de abastecimentos da frota
-- Descrição: Registra ordens e abastecimentos dos veículos da frota
-- Data: 2025-11-13
-- ==============================================================================

-- Criar tabela frotas_abastecimentos
CREATE TABLE IF NOT EXISTS frotas_abastecimentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relacionamentos (obrigatórios na criação pelo admin)
    frota_id INT UNSIGNED NOT NULL COMMENT 'ID do veículo',
    colaborador_id INT UNSIGNED NOT NULL COMMENT 'ID do motorista designado',
    forma_pagamento_id BIGINT UNSIGNED NULL COMMENT 'ID da forma de pagamento (preenchido pelo motorista)',
    conta_pagar_id BIGINT UNSIGNED NULL COMMENT 'ID da conta a pagar (opcional)',

    -- Dados do Abastecimento (preenchidos pelo motorista ao finalizar)
    km DECIMAL(10,2) NULL COMMENT 'Quilometragem no momento do abastecimento',
    litros DECIMAL(10,3) NULL COMMENT 'Quantidade de litros abastecidos',
    combustivel ENUM('gasolina', 'etanol', 'diesel', 'gnv', 'flex') NULL COMMENT 'Tipo de combustível',
    valor DECIMAL(10,2) NULL COMMENT 'Valor total do abastecimento',
    preco_por_litro DECIMAL(6,3) NULL COMMENT 'Calculado automaticamente: valor/litros',

    -- Geolocalização (capturada pelo motorista)
    latitude VARCHAR(30) NULL COMMENT 'Latitude do local do abastecimento',
    longitude VARCHAR(30) NULL COMMENT 'Longitude do local do abastecimento',
    endereco_formatado VARCHAR(255) NULL COMMENT 'Endereço formatado do local',

    -- Datas
    data_limite DATE NULL COMMENT 'Prazo definido pelo admin para realizar o abastecimento',
    data_abastecimento DATETIME NULL COMMENT 'Data/hora real do abastecimento (preenchida pelo motorista)',

    -- Status e Observações
    status ENUM('aguardando', 'abastecido', 'cancelado', 'expirado') NOT NULL DEFAULT 'aguardando' COMMENT 'Status do abastecimento',
    observacao_admin TEXT NULL COMMENT 'Observações do administrador ao criar a ordem',
    observacao_motorista TEXT NULL COMMENT 'Observações do motorista ao finalizar',

    -- Auditoria
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação da ordem',
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de última atualização',
    finalizado_em DATETIME NULL COMMENT 'Data/hora em que o motorista finalizou',
    deletado_em DATETIME NULL COMMENT 'Data de exclusão lógica (soft delete)',
    criado_por INT UNSIGNED NOT NULL COMMENT 'ID do admin/gestor que criou a ordem',
    finalizado_por INT UNSIGNED NULL COMMENT 'ID do motorista que finalizou',

    -- Campos de Notificação
    notificacao_motorista_enviada BOOLEAN DEFAULT FALSE COMMENT 'Indica se WhatsApp foi enviado ao motorista',
    notificacao_motorista_enviada_em DATETIME NULL COMMENT 'Data/hora do envio ao motorista',
    notificacao_admin_enviada BOOLEAN DEFAULT FALSE COMMENT 'Indica se WhatsApp foi enviado aos admins',
    notificacao_admin_enviada_em DATETIME NULL COMMENT 'Data/hora do envio aos admins',

    -- Índices
    INDEX idx_frota_id (frota_id),
    INDEX idx_colaborador_id (colaborador_id),
    INDEX idx_forma_pagamento_id (forma_pagamento_id),
    INDEX idx_status (status),
    INDEX idx_combustivel (combustivel),
    INDEX idx_data_limite (data_limite),
    INDEX idx_data_abastecimento (data_abastecimento),
    INDEX idx_criado_em (criado_em),
    INDEX idx_finalizado_em (finalizado_em),
    INDEX idx_deletado_em (deletado_em),
    INDEX idx_criado_por (criado_por),
    INDEX idx_finalizado_por (finalizado_por),
    INDEX idx_colaborador_status (colaborador_id, status),
    INDEX idx_frota_status (frota_id, status),
    INDEX idx_notificacao_motorista (notificacao_motorista_enviada),
    INDEX idx_notificacao_admin (notificacao_admin_enviada),

    -- Chaves estrangeiras
    CONSTRAINT fk_frotas_abastecimentos_frota
        FOREIGN KEY (frota_id)
        REFERENCES frotas(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_frotas_abastecimentos_colaborador
        FOREIGN KEY (colaborador_id)
        REFERENCES colaboradores(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_frotas_abastecimentos_forma_pagamento
        FOREIGN KEY (forma_pagamento_id)
        REFERENCES forma_de_pagamento(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_frotas_abastecimentos_criado_por
        FOREIGN KEY (criado_por)
        REFERENCES colaboradores(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_frotas_abastecimentos_finalizado_por
        FOREIGN KEY (finalizado_por)
        REFERENCES colaboradores(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registro de ordens e abastecimentos da frota';

-- ==============================================================================
-- Comentários sobre uso:
-- ==============================================================================
-- FLUXO:
-- 1. Admin cria ordem: status='aguardando', campos de dados NULL
-- 2. Motorista finaliza: preenche dados, status='abastecido'
-- 3. Sistema calcula: preco_por_litro automaticamente
-- 4. Notificações: WhatsApp para motorista (criação) e admins (finalização)
-- ==============================================================================
