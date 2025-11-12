-- =====================================================
-- Migration: Criar tabela de endereços de clientees
-- Descrição: Tabela para gerenciamento de endereços dos clientees
-- Data: 2025-11-11
-- =====================================================

CREATE TABLE IF NOT EXISTS `clientees_enderecos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cliente_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID do cliente',
  `cep` VARCHAR(10) DEFAULT NULL COMMENT 'CEP',
  `logradouro` VARCHAR(200) DEFAULT NULL COMMENT 'Rua, avenida, etc',
  `numero` VARCHAR(20) DEFAULT NULL COMMENT 'Número do endereço',
  `complemento` VARCHAR(100) DEFAULT NULL COMMENT 'Complemento (apto, sala, etc)',
  `bairro` VARCHAR(100) DEFAULT NULL COMMENT 'Bairro',
  `cidade_id` INT UNSIGNED DEFAULT NULL COMMENT 'ID da cidade (referência)',
  `estado` VARCHAR(2) DEFAULT NULL COMMENT 'Sigla do estado (UF)',

  -- Campos padrão do sistema
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de cadastro',
  `atualizado_em` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de atualização',

  PRIMARY KEY (`id`),
  KEY `idx_cliente_id` (`cliente_id`),
  KEY `idx_cidade_id` (`cidade_id`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `fk_clientees_enderecos_cliente`
    FOREIGN KEY (`cliente_id`)
    REFERENCES `clientees` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_clientees_enderecos_cidade`
    FOREIGN KEY (`cidade_id`)
    REFERENCES `cidades` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Endereços dos clientees';
