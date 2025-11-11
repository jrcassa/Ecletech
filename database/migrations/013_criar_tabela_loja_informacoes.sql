-- Migration: Criar tabela de informações da loja
-- Data: 2025-11-11
-- Descrição: Tabela para armazenar informações da loja (singleton - apenas 1 registro)

CREATE TABLE IF NOT EXISTS loja_informacoes (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

    -- Dados principais
    nome_fantasia VARCHAR(150) NOT NULL DEFAULT '',
    razao_social VARCHAR(200) NOT NULL DEFAULT '',
    cnpj CHAR(14) NOT NULL UNIQUE DEFAULT '',
    inscricao_estadual VARCHAR(20) DEFAULT NULL,
    inscricao_municipal VARCHAR(20) DEFAULT NULL,

    -- Contatos
    email VARCHAR(150) DEFAULT NULL,
    telefone VARCHAR(20) DEFAULT NULL,
    celular VARCHAR(20) DEFAULT NULL,
    site VARCHAR(150) DEFAULT NULL,

    -- Responsável
    responsavel VARCHAR(100) DEFAULT NULL,
    cpf_responsavel CHAR(11) DEFAULT NULL,

    -- Endereço
    endereco_logradouro VARCHAR(150) DEFAULT NULL,
    endereco_numero VARCHAR(10) DEFAULT NULL,
    endereco_complemento VARCHAR(50) DEFAULT NULL,
    endereco_bairro VARCHAR(100) DEFAULT NULL,
    endereco_cidade VARCHAR(100) DEFAULT NULL,
    endereco_uf CHAR(2) DEFAULT NULL,
    endereco_cep CHAR(8) DEFAULT NULL,

    -- Campos padrão do sistema
    ativo BOOLEAN DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deletado_em DATETIME NULL,

    -- Índices
    INDEX idx_cnpj (cnpj),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir registro inicial (singleton)
INSERT INTO loja_informacoes (
    nome_fantasia,
    razao_social,
    cnpj,
    ativo
) VALUES (
    'Nome da Loja',
    'Razão Social da Empresa',
    '00000000000000',
    1
);

-- Comentários sobre a tabela
ALTER TABLE loja_informacoes COMMENT = 'Informações da loja - Apenas 1 registro ativo (Singleton)';
