-- ==============================================================================
-- Migration: Criar tabela de histórico S3
-- Descrição: Registra auditoria de todas operações realizadas no S3
-- Data: 2025-01-13
-- ==============================================================================

-- Criar tabela s3_historico
CREATE TABLE IF NOT EXISTS s3_historico (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relacionamento com arquivo
    arquivo_id INT UNSIGNED NULL COMMENT 'ID do arquivo na tabela s3_arquivos',

    -- Operação realizada
    operacao VARCHAR(50) NOT NULL COMMENT 'Tipo de operação (upload, download, delete, update, presigned_url)',
    status VARCHAR(20) NOT NULL COMMENT 'Status da operação (sucesso, falha)',

    -- Detalhes da operação
    bucket VARCHAR(100) NULL COMMENT 'Bucket usado na operação',
    caminho_s3 VARCHAR(500) NULL COMMENT 'Caminho do arquivo no S3',
    tamanho_bytes BIGINT UNSIGNED NULL COMMENT 'Tamanho do arquivo em bytes',

    -- Dados adicionais
    detalhes JSON NULL COMMENT 'Detalhes adicionais da operação em JSON',
    erro TEXT NULL COMMENT 'Mensagem de erro se status=falha',
    tempo_execucao_ms INT UNSIGNED NULL COMMENT 'Tempo de execução em milissegundos',

    -- Auditoria de usuário
    colaborador_id INT UNSIGNED NULL COMMENT 'ID do colaborador que realizou a operação',
    ip_address VARCHAR(45) NULL COMMENT 'Endereço IP de origem',
    user_agent VARCHAR(255) NULL COMMENT 'User Agent do navegador/aplicação',

    -- Timestamp
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Índices
    INDEX idx_arquivo_id (arquivo_id),
    INDEX idx_operacao (operacao),
    INDEX idx_status (status),
    INDEX idx_colaborador_id (colaborador_id),
    INDEX idx_criado_em (criado_em),
    INDEX idx_bucket (bucket),
    INDEX idx_operacao_status (operacao, status),

    -- Chaves estrangeiras
    FOREIGN KEY (arquivo_id) REFERENCES s3_arquivos(id) ON DELETE SET NULL,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico de auditoria de operações S3';

-- ==============================================================================
-- Comentários sobre uso:
-- ==============================================================================
-- operacao:
--   - upload: Envio de arquivo para S3
--   - download: Download de arquivo do S3
--   - delete: Deleção de arquivo
--   - update: Atualização de arquivo ou metadados
--   - presigned_url: Geração de URL assinada
--   - list: Listagem de arquivos
--
-- status:
--   - sucesso: Operação completada com êxito
--   - falha: Operação falhou (ver campo 'erro')
--
-- detalhes: JSON com informações extras, exemplos:
--   {"acl": "public-read", "content_type": "image/jpeg"}
--   {"url_expiracao": "2025-01-13 15:30:00"}
--
-- tempo_execucao_ms: Útil para monitoramento de performance
-- ==============================================================================
