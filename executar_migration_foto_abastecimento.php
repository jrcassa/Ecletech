#!/usr/bin/env php
<?php
/**
 * Script para executar migration de foto de abastecimento
 */

// Carrega o autoloader do Composer (se existir)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

// Autoloader personalizado
spl_autoload_register(function ($classe) {
    $prefixo = 'App\\';
    $diretorioBase = __DIR__ . '/App/';

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

// Carrega variáveis de ambiente
$caminhoEnv = __DIR__ . '/.env';
$carregadorEnv = \App\Core\CarregadorEnv::obterInstancia();
$carregadorEnv->carregar($caminhoEnv);

use App\Core\BancoDados;

try {
    echo "=== Executando Migration 065 - Foto Abastecimento ===\n\n";

    $db = BancoDados::obterInstancia();
    $sqlFile = __DIR__ . '/database/migrations/065_adicionar_campo_foto_abastecimento.sql';

    if (!file_exists($sqlFile)) {
        throw new Exception("Arquivo de migration não encontrado: $sqlFile");
    }

    $sql = file_get_contents($sqlFile);

    // Remove comentários e quebra em statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($statement) {
            return !empty($statement) && !preg_match('/^--/', $statement);
        }
    );

    $sucesso = 0;
    $erros = 0;

    foreach ($statements as $statement) {
        if (strlen(trim($statement)) > 10) {
            try {
                $db->executar($statement);
                $sucesso++;

                if (preg_match('/ALTER TABLE.*ADD COLUMN (\w+)/i', $statement, $matches)) {
                    echo "✓ Coluna '{$matches[1]}' adicionada\n";
                } elseif (preg_match('/ADD INDEX (\w+)/i', $statement, $matches)) {
                    echo "✓ Índice '{$matches[1]}' criado\n";
                } else {
                    echo "✓ Statement executado\n";
                }
            } catch (Exception $e) {
                $erros++;
                $erro = $e->getMessage();

                // Ignora erros de "já existe"
                if (
                    stripos($erro, 'already exists') !== false ||
                    stripos($erro, 'Duplicate') !== false ||
                    stripos($erro, 'já existe') !== false
                ) {
                    echo "⚠ Item já existe (ignorado)\n";
                } else {
                    echo "✗ ERRO: $erro\n";
                }
            }
        }
    }

    echo "\n=== Migration Concluída ===\n";
    echo "Sucesso: $sucesso\n";
    echo "Erros: $erros\n";

} catch (Exception $e) {
    echo "ERRO FATAL: " . $e->getMessage() . "\n";
    exit(1);
}
