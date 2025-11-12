-- =====================================================
-- Migration: Criar tabela de informações fiscais de produtos
-- Descrição: Tabela para armazenar informações fiscais (NCM, CEST, etc)
-- Data: 2025-11-12
-- =====================================================

CREATE TABLE IF NOT EXISTS `produto_fiscal` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `produto_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID do produto',
  `ncm` VARCHAR(20) DEFAULT NULL COMMENT 'Nomenclatura Comum do Mercosul',
  `cest` VARCHAR(20) DEFAULT NULL COMMENT 'Código Especificador da Substituição Tributária',
  `peso_liquido` DECIMAL(10,3) DEFAULT NULL COMMENT 'Peso líquido (kg)',
  `peso_bruto` DECIMAL(10,3) DEFAULT NULL COMMENT 'Peso bruto (kg)',
  `valor_aproximado_tributos` DECIMAL(10,2) DEFAULT NULL COMMENT 'Valor aproximado de tributos',

  `cadastrado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de cadastro',
  `modificado_em` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de modificação',

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_produto_id` (`produto_id`),
  KEY `idx_ncm` (`ncm`),
  KEY `idx_cest` (`cest`),

  CONSTRAINT `fk_produto_fiscal_produto` FOREIGN KEY (`produto_id`)
    REFERENCES `produtos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Informações fiscais dos produtos';
