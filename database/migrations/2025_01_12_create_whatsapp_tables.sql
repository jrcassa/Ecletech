-- =====================================================
-- MIGRATION: Sistema WhatsApp Completo
-- Data: 2025-01-12
-- Descrição: Cria todas as tabelas e configurações do sistema WhatsApp
-- =====================================================

-- =====================================================
-- TABELA: Configurações do WhatsApp
-- =====================================================

CREATE TABLE IF NOT EXISTS whatsapp_configuracoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    tipo ENUM('string', 'int', 'bool', 'json') DEFAULT 'string',
    descricao TEXT,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: Fila de Mensagens
-- =====================================================

CREATE TABLE IF NOT EXISTS whatsapp_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,

    -- Entidade
    tipo_entidade VARCHAR(50),
    entidade_id INT,
    entidade_nome VARCHAR(255),

    -- Tipo e destino
    tipo_mensagem ENUM('text', 'image', 'pdf', 'audio', 'video', 'document', 'button', 'location') DEFAULT 'text',
    destinatario VARCHAR(50) NOT NULL,
    is_grupo TINYINT(1) DEFAULT 0,

    -- Conteúdo
    mensagem TEXT,
    arquivo_url TEXT,
    arquivo_base64 LONGTEXT,
    arquivo_nome VARCHAR(255),
    dados_extras JSON,

    -- Controle
    prioridade ENUM('baixa', 'normal', 'alta', 'urgente') DEFAULT 'normal',
    status ENUM('pendente', 'processando', 'enviado', 'erro', 'cancelado') DEFAULT 'pendente',

    -- Tentativas
    tentativas INT DEFAULT 0,
    max_tentativas INT DEFAULT 3,
    erro_mensagem TEXT,

    -- Agendamento
    agendado_para DATETIME,

    -- Response API
    message_id VARCHAR(100),
    api_remote_jid VARCHAR(100),
    api_response JSON,
    api_status VARCHAR(20),

    -- Status de leitura
    status_code INT DEFAULT 0,
    data_envio DATETIME,
    data_entrega DATETIME,
    data_leitura DATETIME,

    -- Timestamps
    processado_em DATETIME,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Índices
    INDEX idx_status_prioridade (status, prioridade, agendado_para),
    INDEX idx_message_id (message_id),
    INDEX idx_destinatario (destinatario),
    INDEX idx_entidade (tipo_entidade, entidade_id),
    INDEX idx_processamento (status, tentativas, agendado_para),
    INDEX idx_status_code (status_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: Histórico de Envios
-- =====================================================

CREATE TABLE IF NOT EXISTS whatsapp_historico (
    id INT PRIMARY KEY AUTO_INCREMENT,
    queue_id INT,

    -- Entidade
    tipo_entidade VARCHAR(50),
    entidade_id INT,
    entidade_nome VARCHAR(255),

    -- Dados
    tipo_mensagem VARCHAR(20),
    destinatario VARCHAR(50),
    mensagem TEXT,

    -- API Response
    message_id VARCHAR(100),
    api_remote_jid VARCHAR(100),
    api_response JSON,
    api_status VARCHAR(20),

    -- Resultado
    status VARCHAR(20),
    status_code INT DEFAULT 0,
    tempo_envio DECIMAL(10,3),
    data_leitura DATETIME,

    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (queue_id) REFERENCES whatsapp_queue(id) ON DELETE SET NULL,
    INDEX idx_message_id (message_id),
    INDEX idx_destinatario (destinatario),
    INDEX idx_data (criado_em),
    INDEX idx_queue (queue_id),
    INDEX idx_entidade (tipo_entidade, entidade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: Mapeamento de Entidades
-- =====================================================

CREATE TABLE IF NOT EXISTS whatsapp_entidades (
    id INT PRIMARY KEY AUTO_INCREMENT,

    -- Identificação da entidade
    tipo_entidade ENUM('cliente', 'colaborador', 'fornecedor', 'transportadora', 'outro') NOT NULL,
    entidade_id INT NOT NULL,

    -- Dados de contato
    numero_whatsapp VARCHAR(20) NOT NULL,
    numero_formatado VARCHAR(30),
    nome VARCHAR(255),
    email VARCHAR(255),

    -- WhatsApp Info
    whatsapp_valido TINYINT(1) DEFAULT 1,
    whatsapp_business TINYINT(1) DEFAULT 0,
    ultimo_envio DATETIME,
    total_envios INT DEFAULT 0,

    -- Status
    bloqueado TINYINT(1) DEFAULT 0,
    motivo_bloqueio TEXT,
    ativo TINYINT(1) DEFAULT 1,

    -- Timestamps
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Índices
    UNIQUE KEY unique_entidade (tipo_entidade, entidade_id),
    INDEX idx_tipo (tipo_entidade),
    INDEX idx_numero (numero_whatsapp),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: Webhooks Recebidos
-- =====================================================

CREATE TABLE IF NOT EXISTS whatsapp_webhooks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo VARCHAR(50) NOT NULL,
    payload JSON NOT NULL,
    processado TINYINT(1) DEFAULT 0,
    processado_em DATETIME,
    erro_processamento TEXT,
    ip_origem VARCHAR(45),
    user_agent TEXT,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tipo (tipo),
    INDEX idx_processado (processado),
    INDEX idx_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: Status das Mensagens (tracking)
-- =====================================================

CREATE TABLE IF NOT EXISTS whatsapp_message_status (
    id INT PRIMARY KEY AUTO_INCREMENT,
    message_id VARCHAR(100) NOT NULL,
    remote_jid VARCHAR(100),
    queue_id INT,

    -- Status codes
    status_code INT,
    status_nome VARCHAR(20),

    -- Timestamps
    data_envio DATETIME,
    data_entrega DATETIME,
    data_leitura DATETIME,

    webhook_id INT,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_message (message_id),
    FOREIGN KEY (queue_id) REFERENCES whatsapp_queue(id) ON DELETE SET NULL,
    FOREIGN KEY (webhook_id) REFERENCES whatsapp_webhooks(id) ON DELETE SET NULL,
    INDEX idx_message_id (message_id),
    INDEX idx_remote_jid (remote_jid),
    INDEX idx_queue (queue_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: Logs de Execução do Cron
-- =====================================================

CREATE TABLE IF NOT EXISTS whatsapp_cron_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_name VARCHAR(50),
    iniciado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    finalizado_em DATETIME,
    mensagens_processadas INT DEFAULT 0,
    mensagens_enviadas INT DEFAULT 0,
    mensagens_erro INT DEFAULT 0,
    tempo_execucao DECIMAL(10,3),
    status ENUM('sucesso', 'erro', 'executando') DEFAULT 'executando',
    mensagem_erro TEXT,
    dados_extras JSON,
    INDEX idx_data (iniciado_em),
    INDEX idx_task (task_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CONFIGURAÇÕES PADRÃO
-- =====================================================

INSERT INTO whatsapp_configuracoes (chave, valor, tipo, descricao) VALUES

-- ============ CREDENCIAIS E CONEXÃO ============
('instancia_token', '', 'string', 'Token único da instância WhatsApp'),
('instancia_status', 'desconectado', 'string', 'Status: desconectado|conectado|qrcode|erro'),
('instancia_telefone', '', 'string', 'Número do WhatsApp conectado'),
('instancia_nome', '', 'string', 'Nome do perfil WhatsApp'),
('instancia_data_conexao', '', 'string', 'Data/hora da última conexão bem-sucedida'),

-- ============ API BAILEYS ============
('api_base_url', 'https://whatsapp.ecletech.com.br', 'string', 'URL base da API Baileys'),
('api_secure_token', '205e8ecac97670e7e60578ac8a2217a04572742cd15', 'string', 'Token de autenticação da API'),
('api_timeout', '30', 'int', 'Timeout em segundos para requisições à API'),
('api_retry_tentativas', '3', 'int', 'Número de tentativas em caso de falha na API'),
('api_retry_intervalo', '5', 'int', 'Intervalo em segundos entre tentativas de retry'),

-- ============ WEBHOOK ============
('webhook_url', 'https://inovar.com.br/services/whatsapp/webhook', 'string', 'URL do webhook para receber eventos'),
('webhook_secret', '', 'string', 'Token de segurança para validar webhooks'),
('webhook_habilitado', 'true', 'bool', 'Ativar/desativar processamento de webhooks'),
('webhook_validar_ip', 'false', 'bool', 'Validar IP de origem dos webhooks'),

-- ============ MODO DE ENVIO ============
('modo_envio', 'queue', 'string', 'Modo de envio: "direto" ou "queue"'),

-- ============ FILA - PROCESSAMENTO ============
('fila_cron_habilitado', 'true', 'bool', 'Ativar/desativar processamento automático da fila'),
('fila_mensagens_por_ciclo', '50', 'int', 'Máximo de mensagens processadas por execução do cron'),
('fila_intervalo_entre_mensagens', '2', 'int', 'Intervalo em segundos entre cada envio (anti-ban)'),
('fila_max_tentativas', '3', 'int', 'Número máximo de tentativas de envio antes de marcar erro'),

-- ============ RETRY ============
('retry_habilitado', 'true', 'bool', 'Ativar sistema de retry automático'),
('retry_max_tentativas', '3', 'int', 'Número máximo de tentativas de envio'),
('retry_delay_inicial', '60', 'int', 'Delay inicial em segundos antes do primeiro retry'),
('retry_delay_multiplicador', '2', 'int', 'Multiplicador do delay a cada tentativa (backoff exponencial)'),
('retry_delay_maximo', '3600', 'int', 'Delay máximo em segundos entre retries'),

-- ============ LIMITES E ANTI-BAN ============
('limite_mensagens_por_hora', '100', 'int', 'Máximo de mensagens enviadas por hora (0=ilimitado)'),
('limite_mensagens_por_dia', '1000', 'int', 'Máximo de mensagens enviadas por dia (0=ilimitado)'),
('antiban_intervalo_minimo', '1', 'int', 'Intervalo mínimo em segundos entre mensagens'),
('antiban_intervalo_maximo', '5', 'int', 'Intervalo máximo em segundos entre mensagens (randomizado)'),
('antiban_aleatorizar', 'true', 'bool', 'Aleatorizar intervalo entre min e max'),
('antiban_pausa_noturna', 'false', 'bool', 'Pausar envios durante horário noturno'),
('antiban_horario_inicio', '08:00', 'string', 'Horário de início dos envios (formato HH:MM)'),
('antiban_horario_fim', '22:00', 'string', 'Horário de término dos envios (formato HH:MM)'),

-- ============ TIPOS DE MENSAGEM ============
('tipo_text_habilitado', 'true', 'bool', 'Permitir envio de mensagens de texto'),
('tipo_image_habilitado', 'true', 'bool', 'Permitir envio de imagens'),
('tipo_pdf_habilitado', 'true', 'bool', 'Permitir envio de PDFs'),
('tipo_audio_habilitado', 'true', 'bool', 'Permitir envio de áudios'),
('tipo_video_habilitado', 'true', 'bool', 'Permitir envio de vídeos'),
('tipo_document_habilitado', 'true', 'bool', 'Permitir envio de documentos'),

-- ============ VALIDAÇÕES ============
('validar_numero_formato', 'true', 'bool', 'Validar formato do número antes de enviar'),
('validar_tamanho_texto', 'true', 'bool', 'Validar tamanho máximo de texto'),
('validar_tamanho_texto_max', '4096', 'int', 'Tamanho máximo de caracteres em mensagens de texto'),
('validar_arquivo_tamanho', 'true', 'bool', 'Validar tamanho de arquivos'),
('validar_arquivo_tamanho_max', '16777216', 'int', 'Tamanho máximo de arquivo em bytes (16MB padrão)'),

-- ============ ENTIDADES ============
('entidade_auto_sync', 'true', 'bool', 'Sincronizar automaticamente entidades ao enviar'),
('entidade_permitir_numero_direto', 'true', 'bool', 'Permitir envio direto por número (sem entidade)'),
('entidade_cliente_tabela', 'clientes', 'string', 'Nome da tabela de clientes'),
('entidade_cliente_campo_id', 'id', 'string', 'Campo ID na tabela clientes'),
('entidade_cliente_campo_nome', 'nome', 'string', 'Campo nome na tabela clientes'),
('entidade_cliente_campo_telefone', 'celular', 'string', 'Campo telefone na tabela clientes'),
('entidade_cliente_campo_email', 'email', 'string', 'Campo email na tabela clientes'),
('entidade_colaborador_tabela', 'colaboradores', 'string', 'Nome da tabela de colaboradores'),
('entidade_colaborador_campo_id', 'id', 'string', 'Campo ID'),
('entidade_colaborador_campo_nome', 'nome', 'string', 'Campo nome'),
('entidade_colaborador_campo_telefone', 'celular', 'string', 'Campo telefone'),
('entidade_colaborador_campo_email', 'email', 'string', 'Campo email'),

-- ============ CRON ============
('cron_habilitado', 'true', 'bool', 'Ativar/desativar todos os cron jobs'),
('cron_fila_habilitado', 'true', 'bool', 'Ativar cron de processamento da fila'),
('cron_fila_intervalo', '*/1 * * * *', 'string', 'Expressão cron para processar fila (padrão: a cada 1 min)'),
('cron_webhook_habilitado', 'true', 'bool', 'Ativar cron de reprocessamento de webhooks'),
('cron_webhook_intervalo', '*/5 * * * *', 'string', 'Expressão cron para webhooks (padrão: a cada 5 min)'),
('cron_limpeza_habilitado', 'true', 'bool', 'Ativar cron de limpeza automática'),
('cron_limpeza_intervalo', '0 3 * * *', 'string', 'Expressão cron para limpeza (padrão: 3h da manhã)'),
('cron_status_habilitado', 'true', 'bool', 'Ativar cron de verificação de status'),
('cron_status_intervalo', '*/10 * * * *', 'string', 'Expressão cron para status (padrão: a cada 10 min)'),

-- ============ LIMPEZA AUTOMÁTICA ============
('limpeza_auto_habilitada', 'true', 'bool', 'Ativar limpeza automática de registros antigos'),
('limpeza_fila_dias', '30', 'int', 'Dias para manter mensagens enviadas na fila (0=nunca limpar)'),
('limpeza_historico_dias', '90', 'int', 'Dias para manter histórico (0=nunca limpar)'),
('limpeza_webhooks_dias', '7', 'int', 'Dias para manter webhooks processados (0=nunca limpar)'),
('limpeza_cron_logs_dias', '15', 'int', 'Dias para manter logs do cron (0=nunca limpar)'),

-- ============ LOGS ============
('log_habilitado', 'true', 'bool', 'Ativar logs detalhados'),
('log_nivel', 'info', 'string', 'Nível de log: debug|info|warning|error'),

-- ============ SISTEMA ============
('sistema_manutencao', 'false', 'bool', 'Modo manutenção (bloqueia envios)'),
('sistema_versao', '1.0.0', 'string', 'Versão do sistema de WhatsApp')

ON DUPLICATE KEY UPDATE valor=valor;
