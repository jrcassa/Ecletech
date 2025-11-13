<?php
/**
 * Script para executar migration de remoção de queue_id do histórico WhatsApp
 * Remove coluna queue_id da tabela whatsapp_historico
 */

require_once __DIR__ . '/App/Core/BancoDados.php';

use App\Core\BancoDados;
use App\Helpers\ErrorLogger;

echo "===========================================\n";
echo "Migration: Remover queue_id - WhatsApp Histórico\n";
echo "===========================================\n\n";

try {
    $db = BancoDados::obterInstancia();

    echo "1. Lendo arquivo de migration...\n";
    $sql = file_get_contents(__DIR__ . '/database/migrations/051_remover_queue_id_whatsapp_historico.sql');

    if (!$sql) {
        throw new Exception("Erro ao ler arquivo de migration");
    }

    echo "2. Executando migration...\n\n";

    // Separa comandos SQL
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($stmt) => !empty($stmt) && !str_starts_with($stmt, '--')
    );

    foreach ($statements as $index => $statement) {
        if (empty($statement)) continue;

        echo "   Comando " . ($index + 1) . "...\n";

        try {
            $db->executar($statement);
            echo "   ✓ Sucesso\n\n";
        } catch (Exception $e) {
            // Ignora erro se já foi executado
            if (strpos($e->getMessage(), "Can't DROP") !== false ||
                strpos($e->getMessage(), "check that column") !== false ||
                strpos($e->getMessage(), "Unknown column") !== false) {
                echo "   ⚠ Já foi removido (ignorado)\n\n";
            } else {
                ErrorLogger::log($e, [
                    'tipo_erro' => 'database',
                    'nivel' => 'medio',
                    'contexto' => [
                        'script' => 'migration_remover_queue_id',
                        'descricao' => 'Erro ao executar statement da migration 051'
                    ]
                ]);

                throw $e;
            }
        }
    }

    echo "===========================================\n";
    echo "✓ Migration executada com sucesso!\n";
    echo "===========================================\n\n";

    echo "Próximos passos:\n";
    echo "1. Verificar a estrutura da tabela whatsapp_historico\n";
    echo "2. Confirmar que queue_id foi removido\n";
    echo "3. Testar envio de mensagens WhatsApp\n\n";

} catch (Exception $e) {
    ErrorLogger::log($e, [
        'tipo_erro' => 'database',
        'nivel' => 'critico',
        'contexto' => [
            'script' => 'migration_remover_queue_id',
            'descricao' => 'Erro fatal ao executar migration 051 - remover queue_id'
        ]
    ]);

    echo "\n===========================================\n";
    echo "✗ ERRO: " . $e->getMessage() . "\n";
    echo "===========================================\n";
    exit(1);
}
