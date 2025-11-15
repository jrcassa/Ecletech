<?php

/**
 * Script para executar migration das tabelas CRM
 *
 * Este script cria as tabelas necessárias para integração CRM:
 * - crm_integracoes: Configurações de CRM por loja
 * - crm_sync_queue: Fila de sincronização
 * - crm_sync_log: Histórico de sincronizações
 *
 * Também adiciona o campo external_id nas tabelas existentes
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\BancoDados;

try {
    echo "========================================\n";
    echo "Migration: Tabelas CRM\n";
    echo "========================================\n\n";

    $db = BancoDados::obterInstancia();

    // Lê o arquivo SQL
    $sqlFile = __DIR__ . '/database/migrations/crm_tables.sql';

    if (!file_exists($sqlFile)) {
        throw new Exception("Arquivo SQL não encontrado: {$sqlFile}");
    }

    $sql = file_get_contents($sqlFile);

    if (empty($sql)) {
        throw new Exception("Arquivo SQL está vazio");
    }

    echo "Executando migration...\n\n";

    // Divide o SQL em statements individuais
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            // Remove comentários e linhas vazias
            return !empty($stmt) &&
                   !preg_match('/^--/', $stmt) &&
                   !preg_match('/^\/\*/', $stmt);
        }
    );

    $executados = 0;
    $erros = 0;

    foreach ($statements as $statement) {
        // Remove comentários inline
        $statement = preg_replace('/--.*$/m', '', $statement);
        $statement = trim($statement);

        if (empty($statement)) {
            continue;
        }

        try {
            // Detecta o tipo de operação
            if (preg_match('/CREATE TABLE.*`(\w+)`/i', $statement, $matches)) {
                $tabela = $matches[1];
                echo "Criando tabela '{$tabela}'... ";
                $db->executar($statement);
                echo "✓ OK\n";
                $executados++;
            } elseif (preg_match('/ALTER TABLE.*`(\w+)`/i', $statement, $matches)) {
                $tabela = $matches[1];
                echo "Alterando tabela '{$tabela}'... ";
                $db->executar($statement);
                echo "✓ OK\n";
                $executados++;
            } elseif (preg_match('/CREATE EVENT/i', $statement)) {
                echo "Criando evento... ";
                $db->executar($statement);
                echo "✓ OK\n";
                $executados++;
            } else {
                // Outro tipo de statement
                $db->executar($statement);
                $executados++;
            }
        } catch (Exception $e) {
            // Verifica se é erro de "já existe"
            if (
                stripos($e->getMessage(), 'already exists') !== false ||
                stripos($e->getMessage(), 'Duplicate column') !== false ||
                stripos($e->getMessage(), 'já existe') !== false
            ) {
                echo "⚠ Já existe (pulando)\n";
            } else {
                echo "✗ ERRO\n";
                echo "  Mensagem: " . $e->getMessage() . "\n";
                $erros++;
            }
        }
    }

    echo "\n========================================\n";
    echo "Resumo:\n";
    echo "  Statements executados: {$executados}\n";
    echo "  Erros: {$erros}\n";
    echo "========================================\n\n";

    if ($erros > 0) {
        echo "⚠ Migration concluída com erros\n\n";
        exit(1);
    } else {
        echo "✓ Migration concluída com sucesso!\n\n";
        echo "Próximos passos:\n";
        echo "1. Configure o cron para executar a sincronização:\n";
        echo "   * * * * * /usr/bin/php " . __DIR__ . "/cron/crm_sync.php\n\n";
        echo "2. Configure limpeza diária:\n";
        echo "   0 3 * * * /usr/bin/php " . __DIR__ . "/cron/crm_cleanup.php\n\n";
        echo "3. Configure as credenciais do CRM em crm_integracoes\n\n";
        exit(0);
    }
} catch (Exception $e) {
    echo "\n✗ ERRO FATAL:\n";
    echo "  " . $e->getMessage() . "\n\n";
    exit(1);
}
