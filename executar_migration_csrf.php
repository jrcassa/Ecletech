<?php
/**
 * Script para executar a migration da tabela csrf_tokens
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

use App\Helpers\ErrorLogger;

try {
    $db = App\Core\BancoDados::obterInstancia();
    $conexao = $db->obterConexao();

    echo "Executando migration 006_criar_tabela_csrf_tokens.sql...\n";

    $sql = file_get_contents(__DIR__ . '/database/migrations/006_criar_tabela_csrf_tokens.sql');

    // Remove o DELIMITER para execução via PDO
    $sql = preg_replace('/DELIMITER \$\$/', '', $sql);
    $sql = preg_replace('/DELIMITER ;/', '', $sql);
    $sql = str_replace('$$', '', $sql);

    // Divide em statements separados
    $statements = explode(';', $sql);

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $conexao->exec($statement);
                echo ".";
            } catch (PDOException $e) {
                // Ignora erros de "já existe"
                if (strpos($e->getMessage(), 'already exists') === false) {
                    ErrorLogger::log($e, [
                        'tipo_erro' => 'database',
                        'nivel' => 'medio',
                        'contexto' => [
                            'script' => 'migration_csrf',
                            'descricao' => 'Erro ao executar statement da migration 006'
                        ]
                    ]);

                    echo "\nErro: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    echo "\n\nMigration executada com sucesso!\n";

    // Verifica se a tabela foi criada
    $result = $db->buscarUm("SHOW TABLES LIKE 'csrf_tokens'");
    if ($result) {
        echo "✓ Tabela csrf_tokens criada/verificada\n";
    } else {
        echo "✗ Erro: Tabela csrf_tokens não foi criada\n";
    }

} catch (Exception $e) {
    ErrorLogger::log($e, [
        'tipo_erro' => 'database',
        'nivel' => 'critico',
        'contexto' => [
            'script' => 'migration_csrf',
            'descricao' => 'Erro fatal ao executar migration 006 - csrf tokens'
        ]
    ]);

    echo "Erro ao executar migration: " . $e->getMessage() . "\n";
    exit(1);
}
