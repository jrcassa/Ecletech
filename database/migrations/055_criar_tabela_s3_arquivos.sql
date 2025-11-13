-- ==============================================================================
-- Migration: Criar tabela de arquivos S3
-- Descrição: Registra todos os arquivos armazenados no S3
-- Data: 2025-01-13
-- ==============================================================================

-- Criar tabela s3_arquivos
CREATE TABLE IF NOT EXISTS s3_arquivos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE COMMENT 'UUID único do arquivo',

    -- Identificação do arquivo
    nome_original VARCHAR(255) NOT NULL COMMENT 'Nome original do arquivo enviado',
    nome_s3 VARCHAR(255) NOT NULL COMMENT 'Nome do arquivo no S3',
    caminho_s3 VARCHAR(500) NOT NULL COMMENT 'Caminho completo no S3 (sem bucket)',
    bucket VARCHAR(100) NOT NULL COMMENT 'Nome do bucket S3',

    -- Propriedades do arquivo
    tipo_mime VARCHAR(100) NULL COMMENT 'Tipo MIME do arquivo',
    extensao VARCHAR(10) NULL COMMENT 'Extensão do arquivo',
    tamanho_bytes BIGINT UNSIGNED NULL COMMENT 'Tamanho em bytes',
    hash_md5 VARCHAR(32) NULL COMMENT 'Hash MD5 do conteúdo',

    -- Controle de acesso
    acl VARCHAR(50) DEFAULT 'private' COMMENT 'Access Control List (private, public-read, etc)',
    url_publica TEXT NULL COMMENT 'URL pública se ACL for public-read',

    -- Metadados adicionais
    metadata JSON NULL COMMENT 'Metadados customizados em JSON',

    -- Relacionamento com entidades
    entidade_tipo VARCHAR(50) NULL COMMENT 'Tipo da entidade relacionada (cliente, produto, nfe, etc)',
    entidade_id INT UNSIGNED NULL COMMENT 'ID da entidade relacionada',
    categoria VARCHAR(50) NULL COMMENT 'Categoria do arquivo (documento, imagem, anexo, etc)',

    -- Auditoria
    criado_por INT UNSIGNED NULL COMMENT 'ID do colaborador que fez upload',
    status ENUM('ativo', 'deletado', 'arquivado') DEFAULT 'ativo' COMMENT 'Status do arquivo',

    -- Timestamps
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deletado_em TIMESTAMP NULL COMMENT 'Data de deleção (soft delete)',

    -- Índices
    INDEX idx_uuid (uuid),
    INDEX idx_bucket (bucket),
    INDEX idx_nome_s3 (nome_s3),
    INDEX idx_entidade (entidade_tipo, entidade_id),
    INDEX idx_categoria (categoria),
    INDEX idx_criado_por (criado_por),
    INDEX idx_status (status),
    INDEX idx_tipo_mime (tipo_mime),
    INDEX idx_criado_em (criado_em),

    -- Chave estrangeira
    FOREIGN KEY (criado_por) REFERENCES colaboradores(id) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registro de arquivos armazenados no S3';

-- ==============================================================================
-- Comentários sobre uso:
-- ==============================================================================
-- uuid: Gerado automaticamente (UUID v4) para identificação única
-- caminho_s3: Ex: "uploads/2025/01/arquivo.pdf"
-- bucket: Ex: "meu-bucket-producao"
-- entidade_tipo/entidade_id: Para vincular arquivo a cliente, produto, etc
-- metadata: JSON com dados adicionais, ex: {"largura": 1920, "altura": 1080}
-- ==============================================================================
