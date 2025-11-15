#!/usr/bin/env php
<?php

/**
 * Script para executar migration de external_id
 *
 * Adiciona a coluna external_id nas tabelas clientes, produtos e vendas
 * para suportar integração CRM
 */

// Define o caminho base
define('BASE_PATH', dirname(__DIR__));

// Carrega o autoloader do Composer (se existir)
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
}

// Autoloader personalizado
spl_autoload_register(function ($classe) {
    $prefixo = 'App\\';
    $diretorioBase = BASE_PATH . '/App/';

    $tamanho = strlen($prefixo);
    if (strncmp($prefixo, $classe, $tamanho) !== 0) {
        return;
    }

    $classeRelativa = substr($classe, $tamanho);
    $arquivo = $diretorioBase . str_replace('\\', '/', $classeRelativa) . '.php';

    if (file_exists($arquivo)) {
        require $arquivo;
    }
});

use App\Core\BancoDados;

try {
    echo "==============================================\n";
    echo "Executando Migration: external_id\n";
    echo "==============================================\n\n";

    $db = BancoDados::obterInstancia();
    $conexao = $db->obterConexao();

    // Lê o arquivo de migração
    $migrationFile = BASE_PATH . '/database/migrations/100_adicionar_external_id_tabelas_crm.sql';

    if (!file_exists($migrationFile)) {
        throw new Exception("Arquivo de migração não encontrado: $migrationFile");
    }

    $sql = file_get_contents($migrationFile);

    echo "Executando migração...\n\n";

    // Divide em statements individuais (ignorando comentários)
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) &&
                   !str_starts_with($stmt, '--') &&
                   !str_starts_with($stmt, '/*');
        }
    );

    foreach ($statements as $index => $statement) {
        if (empty(trim($statement))) {
            continue;
        }

        try {
            $conexao->exec($statement);
            echo "✓ Statement " . ($index + 1) . " executado com sucesso\n";
        } catch (PDOException $e) {
            // Ignora erros de coluna já existente
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "⚠ Statement " . ($index + 1) . " - Coluna já existe (ignorado)\n";
            } else {
                throw $e;
            }
        }
    }

    echo "\n==============================================\n";
    echo "✅ Migration executada com sucesso!\n";
    echo "==============================================\n\n";

    // Verifica se as colunas foram criadas
    echo "Verificando colunas criadas:\n\n";

    $tabelas = ['clientes', 'produtos', 'vendas'];

    foreach ($tabelas as $tabela) {
        $stmt = $conexao->prepare("
            SELECT COUNT(*) as existe
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = 'external_id'
        ");
        $stmt->execute([$tabela]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($resultado['existe'] > 0) {
            echo "✓ Tabela '$tabela' possui coluna 'external_id'\n";
        } else {
            echo "✗ Tabela '$tabela' NÃO possui coluna 'external_id'\n";
        }
    }

    echo "\n";

} catch (Exception $e) {
    echo "\n==============================================\n";
    echo "❌ ERRO ao executar migration:\n";
    echo "==============================================\n";
    echo $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
