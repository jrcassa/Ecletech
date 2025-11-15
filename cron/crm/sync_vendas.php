<?php

/**
 * Cron para sincronização manual de VENDAS
 * Enfileira todas as vendas recentes para sincronização
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

        // Busca vendas dos últimos 30 dias
        $vendas = $db->buscarTodos(
            "SELECT id FROM vendas
             WHERE deletado_em IS NULL
             AND criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY id DESC"
        );

        echo "Loja {$idLoja}: " . count($vendas) . " vendas encontradas (últimos 30 dias)\n";

        // Enfileira cada venda para sincronização
        foreach ($vendas as $venda) {
            $modelQueue->enfileirar(
                $idLoja,
                'venda',
                $venda['id'],
                'ecletech_para_crm',
                5 // Prioridade alta
            );
            $totalEnfileirado++;
        }
    }

    echo "\n✅ Total enfileirado: {$totalEnfileirado} vendas\n";
    echo "Os registros serão processados pelo cron principal (crm_sync.php)\n";

} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
