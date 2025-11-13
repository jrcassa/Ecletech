<?php
/**
 * Script para executar a migration de atualização das configurações de modo de envio
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

    echo "Executando migration 048_atualizar_configuracoes_whatsapp_modo_envio.sql...\n";

    $sql = file_get_contents(__DIR__ . '/database/migrations/048_atualizar_configuracoes_whatsapp_modo_envio.sql');

    // Remove comentários multi-linha
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

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
                if (strpos($e->getMessage(), 'already exists') === false &&
                    strpos($e->getMessage(), 'Duplicate entry') === false) {
                    ErrorLogger::log($e, [
                        'tipo_erro' => 'database',
                        'nivel' => 'medio',
                        'contexto' => [
                            'script' => 'migration_whatsapp_modo_envio',
                            'descricao' => 'Erro ao executar statement da migration 048'
                        ]
                    ]);

                    echo "\nErro: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    echo "\n\nMigration executada com sucesso!\n";

    // Verifica as configurações
    $configs = $db->buscarTodos("SELECT chave, valor FROM whatsapp_configuracoes WHERE chave IN ('modo_envio_padrao', 'cron_limite_mensagens', 'antiban_delay_min', 'antiban_delay_max', 'retry_base_delay', 'retry_multiplicador')");

    if ($configs) {
        echo "\n✓ Configurações atualizadas:\n";
        foreach ($configs as $config) {
            echo "  - {$config['chave']}: {$config['valor']}\n";
        }
    } else {
        echo "\n✗ Erro: Configurações não foram criadas\n";
    }

} catch (Exception $e) {
    ErrorLogger::log($e, [
        'tipo_erro' => 'database',
        'nivel' => 'critico',
        'contexto' => [
            'script' => 'migration_whatsapp_modo_envio',
            'descricao' => 'Erro fatal ao executar migration 048 - modo envio whatsapp'
        ]
    ]);

    echo "Erro ao executar migration: " . $e->getMessage() . "\n";
    exit(1);
}
