-- =====================================================
-- Migration: Criar tabela de contatos de transportadoras
-- Descrição: Tabela para gerenciamento de contatos das transportadoras
-- Data: 2025-11-12
-- =====================================================

CREATE TABLE IF NOT EXISTS `transportadoras_contatos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transportadora_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID da transportadora',
  `nome` VARCHAR(100) NOT NULL COMMENT 'Nome do contato',
  `contato` VARCHAR(20) NOT NULL COMMENT 'Telefone/celular do contato',
  `cargo` VARCHAR(50) DEFAULT NULL COMMENT 'Cargo do contato',
  `observacao` TEXT DEFAULT NULL COMMENT 'Observações sobre o contato',

  -- Campos padrão do sistema
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de cadastro',
  `atualizado_em` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de atualização',

  PRIMARY KEY (`id`),
  KEY `idx_transportadora_id` (`transportadora_id`),
  CONSTRAINT `fk_transportadoras_contatos_transportadora`
    FOREIGN KEY (`transportadora_id`)
    REFERENCES `transportadoras` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contatos das transportadoras';
