-- =====================================================
-- Migration: Criar tabela de valores de produtos
-- Descrição: Tabela para gerenciar múltiplos tipos de preço por produto
-- Data: 2025-11-12
-- =====================================================

CREATE TABLE IF NOT EXISTS `produto_valores` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `produto_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID do produto',
  `tipo_id` VARCHAR(50) NOT NULL COMMENT 'ID do tipo de preço (ex: 203440)',
  `nome_tipo` VARCHAR(100) NOT NULL COMMENT 'Nome do tipo (ex: Varejo, Atacado)',
  `lucro_utilizado` DECIMAL(5,2) DEFAULT NULL COMMENT 'Percentual de lucro utilizado',
  `valor_custo` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor de custo',
  `valor_venda` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor de venda',

  `cadastrado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de cadastro',
  `modificado_em` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de modificação',

  PRIMARY KEY (`id`),
  KEY `idx_produto_id` (`produto_id`),
  KEY `idx_tipo_id` (`tipo_id`),
  UNIQUE KEY `uk_produto_tipo` (`produto_id`, `tipo_id`),

  CONSTRAINT `fk_produto_valores_produto` FOREIGN KEY (`produto_id`)
    REFERENCES `produtos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Valores e tipos de preço dos produtos';
