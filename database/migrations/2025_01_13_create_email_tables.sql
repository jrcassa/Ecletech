-- =====================================================
-- MIGRATION: Sistema de Email com PHPMailer
-- Data: 2025-01-13
-- Descrição: Cria estrutura completa de email seguindo padrão WhatsApp
-- Tabelas: 6 (configuracoes, queue, historico, entidades, logs, cron_logs)
-- =====================================================

-- =====================================================
-- TABELA 1: email_configuracoes
-- Armazena todas as configurações do sistema de email
-- =====================================================
CREATE TABLE IF NOT EXISTS `email_configuracoes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `chave` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Nome único da configuração',
    `valor` TEXT NULL COMMENT 'Valor da configuração',
    `tipo` ENUM('string', 'int', 'bool', 'json') NOT NULL DEFAULT 'string' COMMENT 'Tipo de dado',
    `descricao` TEXT NULL COMMENT 'Descrição da configuração',
    `categoria` VARCHAR(50) NULL COMMENT 'Categoria para organização',
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_categoria` (`categoria`),
    INDEX `idx_chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configurações do sistema de email';

-- =====================================================
-- TABELA 2: email_queue
-- Fila de emails pendentes de envio
-- =====================================================
CREATE TABLE IF NOT EXISTS `email_queue` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Entidade de origem
    `tipo_entidade` ENUM('cliente', 'colaborador', 'fornecedor', 'transportadora', 'outro') NULL COMMENT 'Tipo da entidade',
    `entidade_id` INT UNSIGNED NULL COMMENT 'ID da entidade',
    `entidade_nome` VARCHAR(255) NULL COMMENT 'Nome da entidade (cache)',

    -- Destinatários
    `destinatario_email` VARCHAR(255) NOT NULL COMMENT 'Email do destinatário principal',
    `destinatario_nome` VARCHAR(255) NULL COMMENT 'Nome do destinatário',
    `cc` TEXT NULL COMMENT 'Emails CC (JSON array)',
    `bcc` TEXT NULL COMMENT 'Emails BCC (JSON array)',
    `reply_to` VARCHAR(255) NULL COMMENT 'Email para resposta',

    -- Conteúdo
    `assunto` VARCHAR(500) NOT NULL COMMENT 'Assunto do email',
    `corpo_texto` LONGTEXT NULL COMMENT 'Corpo em texto plano',
    `corpo_html` LONGTEXT NULL COMMENT 'Corpo em HTML',
    `template` VARCHAR(100) NULL COMMENT 'Nome do template usado',
    `dados_template` TEXT NULL COMMENT 'Variáveis do template (JSON)',

    -- Anexos
    `anexos` TEXT NULL COMMENT 'Lista de anexos (JSON array)',

    -- Controle de fila
    `prioridade` ENUM('baixa', 'normal', 'alta', 'urgente') NOT NULL DEFAULT 'normal',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '0=erro, 1=pendente, 2=processando, 3=enviado',
    `tentativas` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Número de tentativas de envio',
    `max_tentativas` TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT 'Máximo de tentativas permitidas',
    `ultimo_erro` TEXT NULL COMMENT 'Última mensagem de erro',
    `smtp_response` TEXT NULL COMMENT 'Resposta do servidor SMTP',

    -- Dados extras
    `dados_extras` TEXT NULL COMMENT 'Dados adicionais em JSON',
    `ip_origem` VARCHAR(45) NULL COMMENT 'IP de origem da requisição',
    `user_agent` VARCHAR(500) NULL COMMENT 'User agent da requisição',

    -- Agendamento
    `agendado_para` TIMESTAMP NULL COMMENT 'Data/hora agendada para envio',

    -- Rastreamento
    `message_id` VARCHAR(255) NULL COMMENT 'ID da mensagem PHPMailer',
    `tracking_code` VARCHAR(32) NULL UNIQUE COMMENT 'Código único para tracking',

    -- Timestamps
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `enviado_em` TIMESTAMP NULL,

    -- Índices para performance
    INDEX `idx_status_prioridade` (`status`, `prioridade`, `agendado_para`),
    INDEX `idx_entidade` (`tipo_entidade`, `entidade_id`),
    INDEX `idx_destinatario` (`destinatario_email`),
    INDEX `idx_criado` (`criado_em`),
    INDEX `idx_agendado` (`agendado_para`),
    INDEX `idx_tracking` (`tracking_code`),
    INDEX `idx_message_id` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fila de emails para envio';

-- =====================================================
-- TABELA 3: email_historico
-- Histórico completo de todos os emails enviados
-- =====================================================
CREATE TABLE IF NOT EXISTS `email_historico` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Identificação
    `message_id` VARCHAR(255) NULL COMMENT 'ID único da mensagem PHPMailer',
    `tracking_code` VARCHAR(32) NULL COMMENT 'Código de rastreamento',

    -- Tipo de evento
    `tipo_evento` ENUM('enviado', 'enviado_direto', 'erro_envio', 'bounce', 'aberto', 'clicado', 'spam') NOT NULL DEFAULT 'enviado',

    -- Entidade de origem
    `tipo_entidade` ENUM('cliente', 'colaborador', 'fornecedor', 'transportadora', 'outro') NULL,
    `entidade_id` INT UNSIGNED NULL,
    `entidade_nome` VARCHAR(255) NULL,

    -- Destinatários
    `destinatario_email` VARCHAR(255) NOT NULL,
    `destinatario_nome` VARCHAR(255) NULL,
    `cc` TEXT NULL COMMENT 'Emails CC (JSON)',
    `bcc` TEXT NULL COMMENT 'Emails BCC (JSON)',

    -- Conteúdo
    `assunto` VARCHAR(500) NOT NULL,
    `corpo_resumo` TEXT NULL COMMENT 'Primeiros 200 caracteres do corpo',
    `template` VARCHAR(100) NULL,
    `anexos_count` TINYINT UNSIGNED DEFAULT 0 COMMENT 'Quantidade de anexos',

    -- Status e erro
    `status` VARCHAR(50) NOT NULL COMMENT 'enviado, erro, bounce, aberto, etc',
    `status_code` TINYINT NOT NULL COMMENT '0=erro, 1=pendente, 2=enviado, 3=bounce, 4=aberto, 5=clicado',
    `erro_mensagem` TEXT NULL,
    `smtp_response` TEXT NULL COMMENT 'Resposta completa do servidor SMTP',
    `bounce_type` ENUM('hard', 'soft', 'block') NULL COMMENT 'Tipo de bounce',

    -- Tracking
    `ip_origem` VARCHAR(45) NULL COMMENT 'IP que solicitou o envio',
    `ip_abertura` VARCHAR(45) NULL COMMENT 'IP onde foi aberto',
    `user_agent` VARCHAR(500) NULL COMMENT 'User agent da abertura',

    -- Timestamps
    `data_enviado` TIMESTAMP NULL,
    `data_bounce` TIMESTAMP NULL,
    `data_aberto` TIMESTAMP NULL,
    `data_clicado` TIMESTAMP NULL,
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Índices
    INDEX `idx_message_id` (`message_id`),
    INDEX `idx_tracking_code` (`tracking_code`),
    INDEX `idx_tipo_evento` (`tipo_evento`),
    INDEX `idx_entidade` (`tipo_entidade`, `entidade_id`),
    INDEX `idx_destinatario` (`destinatario_email`),
    INDEX `idx_status_code` (`status_code`),
    INDEX `idx_data_enviado` (`data_enviado`),
    INDEX `idx_criado` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico completo de emails';

-- =====================================================
-- TABELA 4: email_entidades
-- Mapeamento entre entidades do sistema e emails
-- =====================================================
CREATE TABLE IF NOT EXISTS `email_entidades` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Identificação da entidade
    `tipo_entidade` ENUM('cliente', 'colaborador', 'fornecedor', 'transportadora', 'outro') NOT NULL,
    `entidade_id` INT UNSIGNED NOT NULL COMMENT 'ID na tabela de origem',

    -- Dados de contato
    `email` VARCHAR(255) NOT NULL COMMENT 'Email da entidade',
    `nome` VARCHAR(255) NULL COMMENT 'Nome da entidade',

    -- Validação
    `email_valido` BOOLEAN DEFAULT TRUE COMMENT 'Email passou por validação sintática',
    `verificado` BOOLEAN DEFAULT FALSE COMMENT 'Email foi verificado (bounce check)',

    -- Bloqueio
    `bloqueado` BOOLEAN DEFAULT FALSE COMMENT 'Entidade bloqueada para envio',
    `motivo_bloqueio` VARCHAR(500) NULL COMMENT 'Motivo do bloqueio',

    -- Métricas
    `bounce_count` TINYINT UNSIGNED DEFAULT 0 COMMENT 'Contador de bounces',
    `total_envios` INT UNSIGNED DEFAULT 0 COMMENT 'Total de emails enviados',
    `total_abertos` INT UNSIGNED DEFAULT 0 COMMENT 'Total de emails abertos',
    `total_clicados` INT UNSIGNED DEFAULT 0 COMMENT 'Total de cliques',

    -- Timestamps
    `ultimo_envio` TIMESTAMP NULL,
    `ultimo_aberto` TIMESTAMP NULL,
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Índices
    UNIQUE KEY `unique_entidade` (`tipo_entidade`, `entidade_id`),
    INDEX `idx_email` (`email`),
    INDEX `idx_bloqueado` (`bloqueado`),
    INDEX `idx_tipo` (`tipo_entidade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Entidades associadas ao sistema de email';

-- =====================================================
-- TABELA 5: email_logs
-- Logs detalhados de operações do sistema
-- =====================================================
CREATE TABLE IF NOT EXISTS `email_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Tipo de log
    `tipo` VARCHAR(50) NOT NULL COMMENT 'smtp_connection, smtp_send, queue_process, error, etc',
    `nivel` ENUM('debug', 'info', 'warning', 'error') NOT NULL DEFAULT 'info',

    -- Conteúdo
    `mensagem` TEXT NOT NULL COMMENT 'Mensagem do log',
    `contexto` TEXT NULL COMMENT 'Dados adicionais em JSON',

    -- Rastreamento
    `message_id` VARCHAR(255) NULL COMMENT 'ID da mensagem relacionada',
    `queue_id` INT UNSIGNED NULL COMMENT 'ID da fila relacionada',
    `ip` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,

    -- Timestamp
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Índices
    INDEX `idx_tipo` (`tipo`),
    INDEX `idx_nivel` (`nivel`),
    INDEX `idx_message_id` (`message_id`),
    INDEX `idx_criado` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logs do sistema de email';

-- =====================================================
-- TABELA 6: email_cron_logs
-- Logs de execução do cron job
-- =====================================================
CREATE TABLE IF NOT EXISTS `email_cron_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Execução
    `iniciado_em` TIMESTAMP NOT NULL,
    `finalizado_em` TIMESTAMP NULL,
    `tempo_execucao` DECIMAL(10,2) NULL COMMENT 'Tempo em segundos',

    -- Métricas
    `mensagens_processadas` INT UNSIGNED DEFAULT 0,
    `mensagens_enviadas` INT UNSIGNED DEFAULT 0,
    `erros` INT UNSIGNED DEFAULT 0,

    -- Recursos
    `memoria_pico` VARCHAR(20) NULL COMMENT 'Memória pico utilizada',

    -- Status
    `status` ENUM('sucesso', 'erro', 'timeout') NOT NULL DEFAULT 'sucesso',
    `detalhes` TEXT NULL COMMENT 'Detalhes da execução em JSON',

    -- Timestamp
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Índices
    INDEX `idx_iniciado` (`iniciado_em`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logs de execução do cron de email';

-- =====================================================
-- INSERIR CONFIGURAÇÕES PADRÃO
-- =====================================================

INSERT INTO `email_configuracoes` (`chave`, `valor`, `tipo`, `descricao`, `categoria`) VALUES

-- SMTP - Conexão (8 configurações)
('smtp_host', '', 'string', 'Servidor SMTP (ex: smtp.gmail.com)', 'SMTP'),
('smtp_port', '587', 'int', 'Porta SMTP (25, 465, 587)', 'SMTP'),
('smtp_secure', 'tls', 'string', 'Tipo de criptografia (tls, ssl, none)', 'SMTP'),
('smtp_usuario', '', 'string', 'Usuário de autenticação SMTP', 'SMTP'),
('smtp_senha', '', 'string', 'Senha de autenticação SMTP', 'SMTP'),
('smtp_timeout', '30', 'int', 'Timeout de conexão em segundos', 'SMTP'),
('smtp_debug', '0', 'int', 'Nível de debug (0=off, 1=client, 2=server, 3=connection, 4=low)', 'SMTP'),
('smtp_auth', 'true', 'bool', 'Usar autenticação SMTP', 'SMTP'),

-- Remetente (4 configurações)
('from_email', '', 'string', 'Email do remetente padrão', 'Remetente'),
('from_name', 'Ecletech Sistemas', 'string', 'Nome do remetente padrão', 'Remetente'),
('reply_to_email', '', 'string', 'Email para resposta (reply-to)', 'Remetente'),
('reply_to_name', '', 'string', 'Nome para resposta', 'Remetente'),

-- Formato (3 configurações)
('charset', 'UTF-8', 'string', 'Charset dos emails', 'Formato'),
('encoding', 'base64', 'string', 'Encoding (base64, 8bit, 7bit, binary, quoted-printable)', 'Formato'),
('html_habilitado', 'true', 'bool', 'Permitir envio de emails em HTML', 'Formato'),

-- Modo de Envio (1 configuração)
('modo_envio', 'fila', 'string', 'Modo padrão de envio (fila, direto)', 'Modo'),

-- Fila - Processamento (4 configurações)
('fila_habilitada', 'true', 'bool', 'Habilitar sistema de fila', 'Fila'),
('fila_prioridade_padrao', 'normal', 'string', 'Prioridade padrão (baixa, normal, alta, urgente)', 'Fila'),
('fila_intervalo_entre_envios', '2', 'int', 'Intervalo em segundos entre envios consecutivos', 'Fila'),
('fila_processamento_habilitado', 'true', 'bool', 'Habilitar processamento automático da fila', 'Fila'),

-- Retry (5 configurações)
('retry_habilitado', 'true', 'bool', 'Habilitar sistema de retry', 'Retry'),
('retry_max_tentativas', '3', 'int', 'Máximo de tentativas de reenvio', 'Retry'),
('retry_delay_inicial', '60', 'int', 'Delay inicial entre tentativas (segundos)', 'Retry'),
('retry_delay_multiplicador', '2', 'int', 'Multiplicador de delay (backoff exponencial)', 'Retry'),
('retry_erros_permanentes', '["invalid_email", "mailbox_full", "blocked", "spam"]', 'json', 'Lista de erros que não devem ser retentados', 'Retry'),

-- Limites (4 configurações)
('limite_envios_por_hora', '100', 'int', 'Máximo de emails por hora (0=ilimitado)', 'Limites'),
('limite_envios_por_dia', '1000', 'int', 'Máximo de emails por dia (0=ilimitado)', 'Limites'),
('limite_tamanho_anexo', '10485760', 'int', 'Tamanho máximo de anexo em bytes (10MB)', 'Limites'),
('limite_total_anexos', '26214400', 'int', 'Tamanho total máximo de anexos em bytes (25MB)', 'Limites'),

-- Anexos (3 configurações)
('anexos_habilitados', 'true', 'bool', 'Permitir envio de anexos', 'Anexos'),
('anexos_tipos_permitidos', '["pdf", "doc", "docx", "xls", "xlsx", "jpg", "jpeg", "png", "gif", "zip", "txt"]', 'json', 'Tipos de arquivo permitidos como anexo', 'Anexos'),
('anexos_diretorio', '/uploads/email_attachments', 'string', 'Diretório para armazenar anexos temporários', 'Anexos'),

-- Entidade: Cliente (4 configurações)
('entidade_cliente_tabela', 'clientes', 'string', 'Nome da tabela de clientes', 'Entidade'),
('entidade_cliente_campo_id', 'id', 'string', 'Campo ID da tabela', 'Entidade'),
('entidade_cliente_campo_nome', 'nome', 'string', 'Campo nome da tabela', 'Entidade'),
('entidade_cliente_campo_email', 'email', 'string', 'Campo email da tabela', 'Entidade'),

-- Entidade: Colaborador (4 configurações)
('entidade_colaborador_tabela', 'colaboradores', 'string', 'Nome da tabela de colaboradores', 'Entidade'),
('entidade_colaborador_campo_id', 'id', 'string', 'Campo ID da tabela', 'Entidade'),
('entidade_colaborador_campo_nome', 'nome', 'string', 'Campo nome da tabela', 'Entidade'),
('entidade_colaborador_campo_email', 'email', 'string', 'Campo email da tabela', 'Entidade'),

-- Entidade: Fornecedor (4 configurações)
('entidade_fornecedor_tabela', 'fornecedores', 'string', 'Nome da tabela de fornecedores', 'Entidade'),
('entidade_fornecedor_campo_id', 'id', 'string', 'Campo ID da tabela', 'Entidade'),
('entidade_fornecedor_campo_nome', 'nome_fornecedor', 'string', 'Campo nome da tabela', 'Entidade'),
('entidade_fornecedor_campo_email', 'email', 'string', 'Campo email da tabela', 'Entidade'),

-- Entidade: Transportadora (4 configurações)
('entidade_transportadora_tabela', 'transportadoras', 'string', 'Nome da tabela de transportadoras', 'Entidade'),
('entidade_transportadora_campo_id', 'id', 'string', 'Campo ID da tabela', 'Entidade'),
('entidade_transportadora_campo_nome', 'nome', 'string', 'Campo nome da tabela', 'Entidade'),
('entidade_transportadora_campo_email', 'email', 'string', 'Campo email da tabela', 'Entidade'),

-- Cron (5 configurações)
('cron_habilitado', 'true', 'bool', 'Habilitar processamento via cron', 'Cron'),
('cron_limite_mensagens', '20', 'int', 'Limite de mensagens processadas por execução', 'Cron'),
('cron_intervalo_execucao', '1', 'int', 'Intervalo em minutos entre execuções', 'Cron'),
('cron_horario_inicio', '00:00', 'string', 'Horário de início do processamento (HH:MM)', 'Cron'),
('cron_horario_fim', '23:59', 'string', 'Horário de fim do processamento (HH:MM)', 'Cron'),

-- Templates (3 configurações)
('templates_habilitados', 'true', 'bool', 'Habilitar sistema de templates', 'Templates'),
('templates_diretorio', 'App/Views/Email/', 'string', 'Diretório dos templates', 'Templates'),
('templates_engine', 'php', 'string', 'Engine de templates (php, twig)', 'Templates'),

-- Tracking (3 configurações)
('tracking_habilitado', 'true', 'bool', 'Habilitar tracking de abertura e cliques', 'Tracking'),
('tracking_pixel_habilitado', 'true', 'bool', 'Habilitar pixel de rastreamento de abertura', 'Tracking'),
('tracking_links_habilitado', 'true', 'bool', 'Habilitar rastreamento de cliques em links', 'Tracking'),

-- Logs (2 configurações)
('log_habilitado', 'true', 'bool', 'Habilitar sistema de logs', 'Logs'),
('log_nivel', 'error', 'string', 'Nível de log (debug, info, warning, error)', 'Logs'),

-- Limpeza Automática (3 configurações)
('limpeza_habilitada', 'true', 'bool', 'Habilitar limpeza automática de dados antigos', 'Limpeza'),
('limpeza_dias_historico', '90', 'int', 'Dias para manter histórico', 'Limpeza'),
('limpeza_dias_fila', '7', 'int', 'Dias para manter fila processada', 'Limpeza');

-- =====================================================
-- FIM DA MIGRATION
-- =====================================================
