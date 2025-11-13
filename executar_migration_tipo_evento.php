<?php
/**
 * Script para executar a migration de adição do campo tipo_evento
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

try {
    $db = App\Core\BancoDados::obterInstancia();
    $conexao = $db->obterConexao();

    echo "Executando migration 049_adicionar_tipo_evento_whatsapp_historico.sql...\n";

    $sql = file_get_contents(__DIR__ . '/database/migrations/049_adicionar_tipo_evento_whatsapp_historico.sql');

    // Remove comentários multi-linha
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    // Remove comentários de linha única
    $sql = preg_replace('/--.*$/m', '', $sql);

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
                if (strpos($e->getMessage(), 'Duplicate column') === false &&
                    strpos($e->getMessage(), 'already exists') === false) {
                    echo "\nErro: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    echo "\n\nMigration executada com sucesso!\n";

    // Verifica se a coluna foi criada
    $result = $db->buscarUm("SHOW COLUMNS FROM whatsapp_historico LIKE 'tipo_evento'");
    if ($result) {
        echo "✓ Coluna tipo_evento criada/verificada\n";
    } else {
        echo "✗ Erro: Coluna tipo_evento não foi criada\n";
    }

} catch (Exception $e) {
    echo "Erro ao executar migration: " . $e->getMessage() . "\n";
    exit(1);
}
