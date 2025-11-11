-- Migration 010: Criar tabela de tentativas de login (Proteção Brute Force)
-- Data: 2025-11-11
-- Descrição: Tabela para rastrear tentativas de login e implementar proteção contra brute force

-- Tabela para registrar todas as tentativas de login
CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL COMMENT 'Email utilizado na tentativa de login',
    ip_address VARCHAR(45) NOT NULL COMMENT 'Endereço IP da tentativa (suporta IPv4 e IPv6)',
    user_agent VARCHAR(500) NULL COMMENT 'User agent do navegador',
    tentativa_sucesso TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = Falha, 1 = Sucesso',
    motivo_falha ENUM('senha_invalida', 'usuario_nao_encontrado', 'conta_inativa', 'bloqueado', 'outro') NULL COMMENT 'Motivo da falha no login',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora da tentativa',

    INDEX idx_email (email),
    INDEX idx_ip_address (ip_address),
    INDEX idx_criado_em (criado_em),
    INDEX idx_tentativa_sucesso (tentativa_sucesso),
    INDEX idx_email_criado_em (email, criado_em),
    INDEX idx_ip_criado_em (ip_address, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de tentativas de login para proteção contra brute force';

-- Tabela para gerenciar bloqueios ativos
CREATE TABLE IF NOT EXISTS login_bloqueios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo_bloqueio ENUM('ip', 'email', 'ambos') NOT NULL COMMENT 'Tipo de bloqueio aplicado',
    email VARCHAR(150) NULL COMMENT 'Email bloqueado (se aplicável)',
    ip_address VARCHAR(45) NULL COMMENT 'IP bloqueado (se aplicável)',
    tentativas_falhadas INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Número de tentativas que causaram o bloqueio',
    bloqueado_ate DATETIME NOT NULL COMMENT 'Data e hora até quando o bloqueio é válido',
    bloqueado_permanente TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = Temporário, 1 = Permanente',
    motivo VARCHAR(500) NULL COMMENT 'Motivo do bloqueio',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_email (email),
    INDEX idx_ip_address (ip_address),
    INDEX idx_bloqueado_ate (bloqueado_ate),
    INDEX idx_tipo_bloqueio (tipo_bloqueio),
    UNIQUE KEY uk_email_ip (email, ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Gerenciamento de bloqueios de login';

-- Event para limpeza automática de tentativas antigas (mantém últimos 30 dias)
DELIMITER $$

CREATE EVENT IF NOT EXISTS limpar_login_attempts_antigos
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    -- Remove tentativas com mais de 30 dias
    DELETE FROM login_attempts
    WHERE criado_em < DATE_SUB(NOW(), INTERVAL 30 DAY);

    -- Remove bloqueios temporários expirados
    DELETE FROM login_bloqueios
    WHERE bloqueado_permanente = 0
    AND bloqueado_ate < NOW();
END$$

DELIMITER ;

-- Verificar se o event scheduler está habilitado
SET GLOBAL event_scheduler = ON;

-- Inserir configurações padrão na tabela de configurações (se existir)
-- Estas configurações podem ser gerenciadas via painel administrativo

-- Comentários sobre as configurações:
-- BRUTE_FORCE_MAX_TENTATIVAS: Número máximo de tentativas antes do bloqueio (padrão: 5)
-- BRUTE_FORCE_JANELA_TEMPO: Janela de tempo em minutos para contar tentativas (padrão: 15)
-- BRUTE_FORCE_TEMPO_BLOQUEIO: Tempo de bloqueio em minutos após exceder tentativas (padrão: 30)
-- BRUTE_FORCE_RASTREAR_POR_IP: Rastrear e bloquear por IP (padrão: 1)
-- BRUTE_FORCE_RASTREAR_POR_EMAIL: Rastrear e bloquear por email (padrão: 1)
