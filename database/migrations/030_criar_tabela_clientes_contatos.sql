-- =====================================================
-- Migration: Criar tabela de contatos de clientees
-- Descrição: Tabela para gerenciamento de contatos dos clientes
-- NOTA: Tabela foi criada como 'clientees_contatos' mas renomeada para 'clientes_contatos' na migration 033
-- Data: 2025-11-11
-- =====================================================

CREATE TABLE IF NOT EXISTS `clientees_contatos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cliente_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID do cliente',
  `nome` VARCHAR(100) NOT NULL COMMENT 'Nome do contato',
  `contato` VARCHAR(20) NOT NULL COMMENT 'Telefone/celular do contato',
  `cargo` VARCHAR(50) DEFAULT NULL COMMENT 'Cargo do contato',
  `observacao` TEXT DEFAULT NULL COMMENT 'Observações sobre o contato',

  -- Campos padrão do sistema
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de cadastro',
  `atualizado_em` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de atualização',

  PRIMARY KEY (`id`),
  KEY `idx_cliente_id` (`cliente_id`),
  CONSTRAINT `fk_clientees_contatos_cliente`
    FOREIGN KEY (`cliente_id`)
    REFERENCES `clientees` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contatos dos clientees';
