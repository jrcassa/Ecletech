<?php

/**
 * Cron para sincronização manual de CLIENTES
 * Enfileira todos os clientes ativos para sincronização
 */

// Carrega autoloader
require __DIR__ . '/../../vendor/autoload.php';

// Autoloader personalizado
spl_autoload_register(function ($classe) {
    $prefixo = 'App\\';
    $diretorioBase = __DIR__ . '/../../App/';
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

use App\Core\CarregadorEnv;
use App\Models\ModelCrmSyncQueue;
use App\Models\ModelCrmIntegracao;
use App\Core\BancoDados;

try {
    // Carrega variáveis de ambiente
    $carregadorEnv = CarregadorEnv::obterInstancia();
    $carregadorEnv->carregar(__DIR__ . '/../../.env');

    $modelQueue = new ModelCrmSyncQueue();
    $modelIntegracao = new ModelCrmIntegracao();
    $db = BancoDados::obterInstancia();

    // Busca todas as integrações ativas
    $integracoes = $modelIntegracao->listarAtivas();

    $totalEnfileirado = 0;

    foreach ($integracoes as $integracao) {
        $idLoja = $integracao['id_loja'];

        // Busca todos os clientes ativos da loja
        $clientes = $db->buscarTodos(
            "SELECT id FROM clientes
             WHERE deletado_em IS NULL
             AND (id_loja = ? OR 1 = 1)
             ORDER BY id ASC",
            [$idLoja]
        );

        echo "Loja {$idLoja}: " . count($clientes) . " clientes encontrados\n";

        // Enfileira cada cliente para sincronização
        foreach ($clientes as $cliente) {
            $modelQueue->enfileirar(
                $idLoja,
                'cliente',
                $cliente['id'],
                'ecletech_para_crm',
                3 // Prioridade média
            );
            $totalEnfileirado++;
        }
    }

    echo "\n✅ Total enfileirado: {$totalEnfileirado} clientes\n";
    echo "Os registros serão processados pelo cron principal (crm_sync.php)\n";

} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
