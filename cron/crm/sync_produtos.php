<?php

/**
 * Cron para sincronização manual de PRODUTOS
 * Enfileira todos os produtos ativos para sincronização
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

        // Busca todos os produtos ativos da loja
        $produtos = $db->buscarTodos(
            "SELECT id FROM produtos
             WHERE deletado_em IS NULL
             AND ativo = 1
             ORDER BY id ASC"
        );

        echo "Loja {$idLoja}: " . count($produtos) . " produtos encontrados\n";

        // Enfileira cada produto para sincronização
        foreach ($produtos as $produto) {
            $modelQueue->enfileirar(
                $idLoja,
                'produto',
                $produto['id'],
                'ecletech_para_crm',
                3 // Prioridade média
            );
            $totalEnfileirado++;
        }
    }

    echo "\n✅ Total enfileirado: {$totalEnfileirado} produtos\n";
    echo "Os registros serão processados pelo cron principal (crm_sync.php)\n";

} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
