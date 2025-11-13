<?php
/**
 * Script para executar as migrations da tabela estados
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

    // Migration 1: Criar tabela estados
    echo "Executando migration 023_criar_tabela_estados.sql...\n";

    $sql = file_get_contents(__DIR__ . '/database/migrations/023_criar_tabela_estados.sql');

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
                            'script' => 'migration_estados',
                            'descricao' => 'Erro ao executar statement da migration 023'
                        ]
                    ]);

                    echo "\nErro: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    echo "\n\n✓ Migration 023 executada com sucesso!\n";

    // Verifica se a tabela foi criada
    $result = $db->buscarUm("SHOW TABLES LIKE 'estados'");
    if ($result) {
        echo "✓ Tabela estados criada/verificada\n\n";
    } else {
        echo "✗ Erro: Tabela estados não foi criada\n\n";
    }

    // Migration 2: Adicionar permissões
    echo "Executando migration 024_adicionar_permissoes_estados.sql...\n";

    $sql = file_get_contents(__DIR__ . '/database/migrations/024_adicionar_permissoes_estados.sql');

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
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    ErrorLogger::log($e, [
                        'tipo_erro' => 'database',
                        'nivel' => 'medio',
                        'contexto' => [
                            'script' => 'migration_estados',
                            'descricao' => 'Erro ao executar statement da migration 024 (permissões)'
                        ]
                    ]);

                    echo "\nErro: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    echo "\n\n✓ Migration 024 executada com sucesso!\n";

    // Verifica se as permissões foram criadas
    $result = $db->buscarUm("SELECT COUNT(*) as total FROM permissoes WHERE categoria = 'estado'");
    if ($result && $result['total'] > 0) {
        echo "✓ Permissões de estados criadas/verificadas (" . $result['total'] . " permissões)\n\n";
    } else {
        echo "✗ Erro: Permissões de estados não foram criadas\n\n";
    }

    echo "Todas as migrations de estados foram executadas com sucesso!\n";

} catch (Exception $e) {
    ErrorLogger::log($e, [
        'tipo_erro' => 'database',
        'nivel' => 'critico',
        'contexto' => [
            'script' => 'migration_estados',
            'descricao' => 'Erro fatal ao executar migrations de estados'
        ]
    ]);

    echo "Erro ao executar migration: " . $e->getMessage() . "\n";
    exit(1);
}
