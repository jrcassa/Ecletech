-- ==============================================================================
-- Migration: Criar tabela de notificações de abastecimentos
-- Descrição: Log de todas as notificações WhatsApp enviadas
-- Data: 2025-11-13
-- ==============================================================================

-- Criar tabela frotas_abastecimentos_notificacoes
CREATE TABLE IF NOT EXISTS frotas_abastecimentos_notificacoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    abastecimento_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do abastecimento relacionado',

    -- Tipo e destinatário
    tipo_notificacao ENUM('ordem_criada', 'abastecimento_finalizado', 'ordem_cancelada', 'alerta_vencimento') NOT NULL COMMENT 'Tipo de notificação',
    destinatario_id INT UNSIGNED NOT NULL COMMENT 'ID do colaborador destinatário',
    telefone VARCHAR(20) NOT NULL COMMENT 'Telefone para envio WhatsApp',

    -- Conteúdo
    mensagem TEXT NOT NULL COMMENT 'Conteúdo da mensagem enviada',

    -- Status de envio
    status_envio ENUM('pendente', 'enviado', 'erro') DEFAULT 'pendente' COMMENT 'Status do envio',
    tentativas INT DEFAULT 0 COMMENT 'Número de tentativas de envio',
    erro_mensagem TEXT NULL COMMENT 'Mensagem de erro se houver falha',
    enviado_em DATETIME NULL COMMENT 'Data/hora do envio bem-sucedido',

    -- Auditoria
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação do registro',

    -- Índices
    INDEX idx_abastecimento_id (abastecimento_id),
    INDEX idx_tipo_notificacao (tipo_notificacao),
    INDEX idx_destinatario_id (destinatario_id),
    INDEX idx_status_envio (status_envio),
    INDEX idx_enviado_em (enviado_em),
    INDEX idx_criado_em (criado_em),

    -- Chaves estrangeiras
    CONSTRAINT fk_frotas_abastecimentos_notificacoes_abastecimento
        FOREIGN KEY (abastecimento_id)
        REFERENCES frotas_abastecimentos(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_frotas_abastecimentos_notificacoes_destinatario
        FOREIGN KEY (destinatario_id)
        REFERENCES colaboradores(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Log de notificações WhatsApp dos abastecimentos';

-- ==============================================================================
-- Comentários sobre uso:
-- ==============================================================================
-- Registra TODAS as notificações enviadas via WhatsApp
-- Permite reprocessamento em caso de erro (até 3 tentativas)
-- Histórico completo de comunicações
-- ==============================================================================
