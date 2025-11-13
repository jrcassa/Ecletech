<?php
/**
 * Script para executar migration de campos de data no histórico WhatsApp
 * Adiciona data_enviado e data_entregue ao whatsapp_historico
 */

require_once __DIR__ . '/App/Core/BancoDados.php';

use App\Core\BancoDados;
use App\Helpers\ErrorLogger;

echo "===========================================\n";
echo "Migration: Campos de Data - WhatsApp Histórico\n";
echo "===========================================\n\n";

try {
    $db = BancoDados::obterInstancia();

    echo "1. Lendo arquivo de migration...\n";
    $sql = file_get_contents(__DIR__ . '/database/migrations/050_adicionar_datas_whatsapp_historico.sql');

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
            // Ignora erro se coluna já existe
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "   ⚠ Coluna já existe (ignorado)\n\n";
            } else {
                ErrorLogger::log($e, [
                    'tipo_erro' => 'database',
                    'nivel' => 'medio',
                    'contexto' => [
                        'script' => 'migration_datas_historico',
                        'descricao' => 'Erro ao executar statement da migration 050'
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
    echo "2. Testar envio de mensagens WhatsApp\n";
    echo "3. Verificar que os webhooks atualizam as datas\n\n";

} catch (Exception $e) {
    ErrorLogger::log($e, [
        'tipo_erro' => 'database',
        'nivel' => 'critico',
        'contexto' => [
            'script' => 'migration_datas_historico',
            'descricao' => 'Erro fatal ao executar migration 050 - datas whatsapp historico'
        ]
    ]);

    echo "\n===========================================\n";
    echo "✗ ERRO: " . $e->getMessage() . "\n";
    echo "===========================================\n";
    exit(1);
}
