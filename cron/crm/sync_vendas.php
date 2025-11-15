<?php

/**
 * Cron para importação de VENDAS do CRM
 * Busca vendas recentes do CRM e enfileira para importação no Ecletech
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
use App\Services\ServiceCrm;

try {
    // Carrega variáveis de ambiente
    $carregadorEnv = CarregadorEnv::obterInstancia();
    $carregadorEnv->carregar(__DIR__ . '/../../.env');

    $modelQueue = new ModelCrmSyncQueue();
    $modelIntegracao = new ModelCrmIntegracao();
    $serviceCrm = new ServiceCrm();

    // Busca todas as integrações ativas
    $integracoes = $modelIntegracao->listarAtivas();

    $totalEnfileirado = 0;

    foreach ($integracoes as $integracao) {
        $idLoja = $integracao['id_loja'];

        echo "Loja {$idLoja}: Buscando vendas do CRM...\n";

        // Busca vendas do CRM via API (paginado)
        // Nota: Alguns CRMs permitem filtrar por data, outros não
        $pagina = 1;
        $limite = 100;
        $totalPagina = 0;

        do {
            try {
                $resultado = $serviceCrm->listar('venda', $idLoja, $pagina, $limite);

                if (!isset($resultado['data']) || !is_array($resultado['data'])) {
                    break;
                }

                // Enfileira cada venda do CRM
                foreach ($resultado['data'] as $venda) {
                    $externalId = $venda['id'] ?? $venda['external_id'] ?? null;

                    if ($externalId) {
                        $modelQueue->enfileirar(
                            $idLoja,
                            'venda',
                            null, // Não temos ID local ainda
                            'crm_para_ecletech',
                            5, // Prioridade alta para vendas
                            (string) $externalId
                        );
                        $totalEnfileirado++;
                        $totalPagina++;
                    }
                }

                echo "  Página {$pagina}: {$totalPagina} vendas enfileiradas\n";

                $pagina++;
                $totalPaginas = $resultado['pagination']['total_pages'] ?? 1;

            } catch (\Exception $e) {
                echo "  ⚠️ Erro na página {$pagina}: " . $e->getMessage() . "\n";
                break;
            }

        } while ($pagina <= $totalPaginas);
    }

    echo "\n✅ Total enfileirado: {$totalEnfileirado} vendas do CRM\n";
    echo "Os registros serão importados pelo cron principal (crm_sync.php)\n";

} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
