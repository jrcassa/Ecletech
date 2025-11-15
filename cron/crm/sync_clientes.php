<?php

/**
 * Cron para importação de CLIENTES do CRM
 * Busca todos os clientes do CRM e enfileira para importação no Ecletech
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
use App\CRM\ServiceCrm;

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

        echo "Loja {$idLoja}: Buscando clientes do CRM...\n";

        // Busca clientes do CRM via API (paginado)
        $pagina = 1;
        $limite = 100;
        $totalPagina = 0;

        do {
            try {
                $resultado = $serviceCrm->listar('cliente', $idLoja, $pagina, $limite);

                if (!isset($resultado['data']) || !is_array($resultado['data'])) {
                    break;
                }

                // Enfileira cada cliente do CRM
                foreach ($resultado['data'] as $cliente) {
                    $externalId = $cliente['id'] ?? $cliente['external_id'] ?? null;

                    if ($externalId) {
                        $modelQueue->enfileirar(
                            $idLoja,
                            'cliente',
                            null, // Não temos ID local ainda
                            'crm_para_ecletech',
                            3, // Prioridade média
                            (string) $externalId
                        );
                        $totalEnfileirado++;
                        $totalPagina++;
                    }
                }

                echo "  Página {$pagina}: {$totalPagina} clientes enfileirados\n";

                $pagina++;
                $totalPaginas = $resultado['pagination']['total_pages'] ?? 1;

            } catch (\Exception $e) {
                echo "  ⚠️ Erro na página {$pagina}: " . $e->getMessage() . "\n";
                break;
            }

        } while ($pagina <= $totalPaginas);
    }

    echo "\n✅ Total enfileirado: {$totalEnfileirado} clientes do CRM\n";
    echo "Os registros serão importados pelo cron principal (crm_sync.php)\n";

} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
