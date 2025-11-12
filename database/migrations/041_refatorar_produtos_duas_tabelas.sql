-- =====================================================
-- Migration: Refatoração Produtos - 2 Tabelas
-- Descrição: Simplifica a estrutura de produtos para apenas 2 tabelas
-- Data: 2025-11-12
-- =====================================================

-- Remove tabelas antigas se existirem
DROP TABLE IF EXISTS `produto_valores`;
DROP TABLE IF EXISTS `produto_fiscal`;

-- Recria a tabela produtos com TODOS os campos consolidados
DROP TABLE IF EXISTS `produtos`;

CREATE TABLE `produtos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_id` VARCHAR(50) DEFAULT NULL COMMENT 'ID do sistema externo',

  -- Informações básicas
  `nome` VARCHAR(255) NOT NULL COMMENT 'Nome do produto',
  `codigo_interno` VARCHAR(100) DEFAULT NULL COMMENT 'Código interno do produto',
  `codigo_barra` VARCHAR(100) DEFAULT NULL COMMENT 'Código de barras',
  `descricao` TEXT DEFAULT NULL COMMENT 'Descrição do produto',

  -- Dimensões
  `largura` DECIMAL(10,2) DEFAULT NULL COMMENT 'Largura (cm)',
  `altura` DECIMAL(10,2) DEFAULT NULL COMMENT 'Altura (cm)',
  `comprimento` DECIMAL(10,2) DEFAULT NULL COMMENT 'Comprimento (cm)',

  -- Relacionamento com grupo
  `grupo_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID do grupo de produtos',
  `nome_grupo` VARCHAR(255) DEFAULT NULL COMMENT 'Nome do grupo (desnormalizado)',

  -- Estoque e valores
  `estoque` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Quantidade em estoque',
  `valor_custo` DECIMAL(10,2) DEFAULT NULL COMMENT 'Valor de custo',
  `valor_venda` DECIMAL(10,2) DEFAULT NULL COMMENT 'Valor de venda',

  -- Informações fiscais
  `ncm` VARCHAR(20) DEFAULT NULL COMMENT 'NCM',
  `cest` VARCHAR(20) DEFAULT NULL COMMENT 'CEST',
  `peso_liquido` DECIMAL(10,3) DEFAULT NULL COMMENT 'Peso líquido (kg)',
  `peso_bruto` DECIMAL(10,3) DEFAULT NULL COMMENT 'Peso bruto (kg)',
  `valor_aproximado_tributos` DECIMAL(10,2) DEFAULT NULL COMMENT 'Valor aproximado de tributos',
  `valor_fixo_pis` DECIMAL(10,4) DEFAULT NULL COMMENT 'Valor fixo PIS',
  `valor_fixo_pis_st` DECIMAL(10,2) DEFAULT NULL COMMENT 'Valor fixo PIS ST',
  `valor_fixo_confins` DECIMAL(10,2) DEFAULT NULL COMMENT 'Valor fixo COFINS',
  `valor_fixo_confins_st` DECIMAL(10,2) DEFAULT NULL COMMENT 'Valor fixo COFINS ST',

  -- Status
  `ativo` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Produto ativo/inativo',

  -- Auditoria
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de atualização',
  `deleted_at` DATETIME NULL DEFAULT NULL COMMENT 'Data de exclusão (soft delete)',

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_external_id` (`external_id`),
  UNIQUE KEY `uk_codigo_interno` (`codigo_interno`),
  KEY `idx_codigo_barra` (`codigo_barra`),
  KEY `idx_nome` (`nome`),
  KEY `idx_grupo_id` (`grupo_id`),
  KEY `idx_ativo` (`ativo`),
  KEY `idx_deleted_at` (`deleted_at`),

  CONSTRAINT `fk_produtos_grupo` FOREIGN KEY (`grupo_id`)
    REFERENCES `grupos_produtos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cadastro de produtos consolidado';

-- =====================================================
-- Tabela: produtos_fornecedores
-- =====================================================

CREATE TABLE IF NOT EXISTS `produtos_fornecedores` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `produto_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID do produto',
  `fornecedor_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID do fornecedor',

  -- Auditoria
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de atualização',

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_produto_fornecedor` (`produto_id`, `fornecedor_id`),
  KEY `idx_produto_id` (`produto_id`),
  KEY `idx_fornecedor_id` (`fornecedor_id`),

  CONSTRAINT `fk_produtos_fornecedores_produto` FOREIGN KEY (`produto_id`)
    REFERENCES `produtos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_produtos_fornecedores_fornecedor` FOREIGN KEY (`fornecedor_id`)
    REFERENCES `fornecedores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relacionamento entre produtos e fornecedores';

-- =====================================================
-- Permissões já foram criadas anteriormente
-- =====================================================
