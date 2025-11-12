-- =====================================================
-- Migration: Renomear tabelas de clientes
-- Descrição: Corrige nomenclatura de clientees para clientes
-- Data: 2025-11-12
-- =====================================================

-- Renomeia a tabela principal de clientees para clientes
RENAME TABLE `clientees` TO `clientes`;

-- Renomeia a tabela de contatos de clientees_contatos para clientes_contatos
RENAME TABLE `clientees_contatos` TO `clientes_contatos`;

-- Renomeia a tabela de endereços de clientees_enderecos para clientes_enderecos
RENAME TABLE `clientees_enderecos` TO `clientes_enderecos`;

-- =====================================================
-- RESUMO DA MIGRATION
-- =====================================================
-- 1. ✅ Renomeada tabela clientees → clientes
-- 2. ✅ Renomeada tabela clientees_contatos → clientes_contatos
-- 3. ✅ Renomeada tabela clientees_enderecos → clientes_enderecos
-- =====================================================
