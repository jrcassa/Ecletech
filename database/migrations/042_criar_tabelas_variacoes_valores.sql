-- =====================================================
-- Migration: Criar tabelas de variações e valores de produtos
-- Descrição: Adiciona suporte a variações e múltiplos valores por tipo de preço
-- Data: 2025-11-12
-- =====================================================

-- =====================================================
-- Tabela: produto_valores
-- Descrição: Armazena múltiplos valores/preços por tipo (Varejo, Atacado, etc)
-- =====================================================

CREATE TABLE IF NOT EXISTS `produto_valores` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `produto_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID do produto',
  `tipo_id` VARCHAR(50) NOT NULL COMMENT 'ID do tipo de preço',
  `nome_tipo` VARCHAR(100) NOT NULL COMMENT 'Nome do tipo (Varejo, Atacado, etc)',
  `lucro_utilizado` DECIMAL(10,2) DEFAULT NULL COMMENT 'Percentual de lucro utilizado',
  `valor_custo` DECIMAL(10,4) NOT NULL COMMENT 'Valor de custo',
  `valor_venda` DECIMAL(10,4) NOT NULL COMMENT 'Valor de venda',

  -- Auditoria
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de atualização',

  PRIMARY KEY (`id`),
  KEY `idx_produto_id` (`produto_id`),
  KEY `idx_tipo_id` (`tipo_id`),

  CONSTRAINT `fk_produto_valores_produto` FOREIGN KEY (`produto_id`)
    REFERENCES `produtos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Valores/preços por tipo de produto';

-- =====================================================
-- Tabela: produto_variacoes
-- Descrição: Armazena variações dos produtos (cores, tamanhos, etc)
-- =====================================================

CREATE TABLE IF NOT EXISTS `produto_variacoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `produto_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID do produto',
  `nome` VARCHAR(255) NOT NULL COMMENT 'Nome da variação (ex: Creme, Marrom, Azul Escuro)',
  `estoque` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Estoque específico da variação',

  -- Auditoria
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de atualização',

  PRIMARY KEY (`id`),
  KEY `idx_produto_id` (`produto_id`),
  KEY `idx_nome` (`nome`),

  CONSTRAINT `fk_produto_variacoes_produto` FOREIGN KEY (`produto_id`)
    REFERENCES `produtos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Variações dos produtos';

-- =====================================================
-- Tabela: produto_variacao_valores
-- Descrição: Valores específicos para cada variação por tipo de preço
-- =====================================================

CREATE TABLE IF NOT EXISTS `produto_variacao_valores` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `variacao_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID da variação',
  `tipo_id` VARCHAR(50) NOT NULL COMMENT 'ID do tipo de preço',
  `nome_tipo` VARCHAR(100) NOT NULL COMMENT 'Nome do tipo (Varejo, Atacado, etc)',
  `lucro_utilizado` DECIMAL(10,2) DEFAULT NULL COMMENT 'Percentual de lucro utilizado',
  `valor_custo` DECIMAL(10,4) NOT NULL COMMENT 'Valor de custo',
  `valor_venda` DECIMAL(10,4) NOT NULL COMMENT 'Valor de venda',

  -- Auditoria
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de atualização',

  PRIMARY KEY (`id`),
  KEY `idx_variacao_id` (`variacao_id`),
  KEY `idx_tipo_id` (`tipo_id`),

  CONSTRAINT `fk_produto_variacao_valores_variacao` FOREIGN KEY (`variacao_id`)
    REFERENCES `produto_variacoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Valores específicos das variações por tipo de preço';

-- =====================================================
-- Adicionar campos faltantes na tabela produtos
-- =====================================================

-- Adicionar campos de controle (use apenas se as colunas não existirem)
-- Se der erro "Duplicate column name", comente essas linhas

ALTER TABLE `produtos`
ADD COLUMN `possui_variacao` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Produto possui variação' AFTER `codigo_barra`;

ALTER TABLE `produtos`
ADD COLUMN `possui_composicao` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Produto possui composição' AFTER `possui_variacao`;

ALTER TABLE `produtos`
ADD COLUMN `movimenta_estoque` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Movimenta estoque' AFTER `possui_composicao`;

ALTER TABLE `produtos`
ADD COLUMN `peso` DECIMAL(10,3) DEFAULT NULL COMMENT 'Peso do produto (kg)' AFTER `movimenta_estoque`;

-- =====================================================
-- Índices adicionais para otimização
-- =====================================================

-- Índice composto para busca de valores por produto e tipo
ALTER TABLE `produto_valores`
ADD INDEX `idx_produto_tipo` (`produto_id`, `tipo_id`);

-- Índice composto para busca de valores de variação
ALTER TABLE `produto_variacao_valores`
ADD INDEX `idx_variacao_tipo` (`variacao_id`, `tipo_id`);
