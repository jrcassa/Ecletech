<?php
/**
 * Script para executar migrations de recebimentos
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
use App\Helpers\ErrorLogger;

try {
    echo "=== Executando Migrations de Recebimentos ===\n\n";

    $db = BancoDados::obterInstancia();

    // Lista de arquivos de migration
    $migrations = [
        '082_criar_tabela_recebimentos.sql',
        '083_inserir_permissoes_recebimentos.sql',
        '084_ajustar_recebimentos_apenas_cliente.sql'
    ];

    $totalSucesso = 0;
    $totalErros = 0;

    foreach ($migrations as $migrationFile) {
        echo "\n--- Executando: $migrationFile ---\n";

        $sqlFile = __DIR__ . '/database/migrations/' . $migrationFile;

        if (!file_exists($sqlFile)) {
            echo "✗ Arquivo não encontrado: $sqlFile\n";
            continue;
        }

        $sql = file_get_contents($sqlFile);

        // Remove comentários e quebra em statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($statement) {
                return !empty($statement) && !preg_match('/^--/', $statement);
            }
        );

        foreach ($statements as $statement) {
            if (strlen(trim($statement)) > 10) {
                try {
                    $db->executar($statement);
                    $totalSucesso++;

                    // Extrai nome da tabela ou ação do statement
                    if (preg_match('/CREATE TABLE.*`?(\w+)`?/i', $statement, $matches)) {
                        echo "✓ Tabela '{$matches[1]}' criada\n";
                    } elseif (preg_match('/ALTER TABLE.*`?(\w+)`?/i', $statement, $matches)) {
                        echo "✓ Tabela '{$matches[1]}' alterada\n";
                    } elseif (preg_match('/CREATE INDEX.*ON `?(\w+)`?/i', $statement, $matches)) {
                        echo "✓ Índice criado na tabela '{$matches[1]}'\n";
                    } elseif (preg_match('/INSERT INTO.*`?(\w+)`?/i', $statement, $matches)) {
                        echo "✓ Dados inseridos na tabela '{$matches[1]}'\n";
                    } else {
                        echo "✓ Statement executado\n";
                    }
                } catch (Exception $e) {
                    $totalErros++;
                    $erro = $e->getMessage();

                    // Ignora erros de "já existe"
                    if (
                        stripos($erro, 'already exists') !== false ||
                        stripos($erro, 'Duplicate') !== false ||
                        stripos($erro, 'já existe') !== false
                    ) {
                        echo "⚠ Item já existe (ignorado)\n";
                        $totalErros--; // Não conta como erro real
                    } else {
                        ErrorLogger::log($e, [
                            'tipo_erro' => 'database',
                            'nivel' => 'medio',
                            'contexto' => [
                                'script' => 'migration_recebimentos',
                                'descricao' => 'Erro ao executar statement da migration de recebimentos',
                                'arquivo' => $migrationFile
                            ]
                        ]);

                        echo "✗ ERRO: $erro\n";
                    }
                }
            }
        }
    }

    echo "\n=== Migrations Concluídas ===\n";
    echo "Sucesso: $totalSucesso\n";
    echo "Erros: $totalErros\n";

    if ($totalErros === 0) {
        echo "\n✓ Todas as migrations foram executadas com sucesso!\n";
        echo "\nPróximos passos:\n";
        echo "1. Acesse o sistema como superadmin\n";
        echo "2. Verifique se as permissões foram criadas em: Colaboradores > Permissões\n";
        echo "3. Atribua as permissões necessárias aos usuários\n";
        echo "4. Acesse o módulo de recebimentos em: /recebimentos.html\n";
    }

} catch (Exception $e) {
    ErrorLogger::log($e, [
        'tipo_erro' => 'database',
        'nivel' => 'critico',
        'contexto' => [
            'script' => 'migration_recebimentos',
            'descricao' => 'Erro fatal ao executar migrations de recebimentos'
        ]
    ]);

    echo "ERRO FATAL: " . $e->getMessage() . "\n";
    exit(1);
}
