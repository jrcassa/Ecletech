-- ==============================================================================
-- Migration: Criar tabela de configurações S3
-- Descrição: Armazena credenciais e configurações do AWS S3 (ou compatíveis)
-- Data: 2025-01-13
-- ==============================================================================

-- Criar tabela s3_configuracoes
CREATE TABLE IF NOT EXISTS s3_configuracoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) NOT NULL UNIQUE COMMENT 'Chave de configuração (ex: aws_access_key_id)',
    valor TEXT NULL COMMENT 'Valor da configuração',
    categoria VARCHAR(50) DEFAULT 'geral' COMMENT 'Categoria da configuração',
    descricao VARCHAR(255) NULL COMMENT 'Descrição da configuração',
    tipo ENUM('texto', 'numero', 'booleano', 'senha') DEFAULT 'texto' COMMENT 'Tipo do valor',
    obrigatorio TINYINT(1) DEFAULT 0 COMMENT 'Se é obrigatório para funcionamento',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_chave (chave),
    INDEX idx_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Configurações do AWS S3 ou serviços compatíveis';

-- Inserir configurações padrão
INSERT INTO s3_configuracoes (chave, valor, categoria, descricao, tipo, obrigatorio) VALUES
    ('aws_access_key_id', NULL, 'credenciais', 'AWS Access Key ID', 'senha', 1),
    ('aws_secret_access_key', NULL, 'credenciais', 'AWS Secret Access Key', 'senha', 1),
    ('aws_region', 'us-east-1', 'conexao', 'Região AWS (ex: us-east-1, eu-west-1)', 'texto', 1),
    ('aws_endpoint', NULL, 'conexao', 'Endpoint customizado (deixar vazio para AWS padrão)', 'texto', 0),
    ('aws_default_bucket', NULL, 'geral', 'Bucket padrão para uploads', 'texto', 1),
    ('aws_use_path_style_endpoint', '0', 'conexao', 'Usar path-style endpoint (necessário para Contabo, MinIO, etc)', 'booleano', 0),
    ('aws_s3_version', 'latest', 'conexao', 'Versão da API S3', 'texto', 0),
    ('aws_max_file_size', '52428800', 'upload', 'Tamanho máximo de arquivo em bytes (padrão: 50MB)', 'numero', 0),
    ('aws_default_acl', 'private', 'upload', 'ACL padrão (private, public-read, public-read-write)', 'texto', 0),
    ('aws_url_expiration', '7200', 'download', 'Tempo de expiração das URLs assinadas em segundos (padrão: 2h)', 'numero', 0),
    ('aws_s3_status', '1', 'geral', 'Status do serviço (0=desabilitado, 1=habilitado)', 'booleano', 0)
ON DUPLICATE KEY UPDATE valor=VALUES(valor);

-- ==============================================================================
-- Comentários sobre uso:
-- ==============================================================================
-- Para AWS S3 padrão:
--   - aws_endpoint: deixar vazio ou NULL
--   - aws_use_path_style_endpoint: 0
--   - aws_region: us-east-1, us-west-2, eu-west-1, etc
--
-- Para Contabo Storage:
--   - aws_endpoint: https://usc1.contabostorage.com/bucket-name
--   - aws_use_path_style_endpoint: 1
--   - aws_region: eu2 (ou conforme documentação)
--
-- Para MinIO:
--   - aws_endpoint: http://localhost:9000 ou URL do servidor
--   - aws_use_path_style_endpoint: 1
--   - aws_region: us-east-1 (qualquer valor)
-- ==============================================================================
