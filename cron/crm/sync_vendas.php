<?php

/**
 * Cron para importação de VENDAS do CRM
 * Busca vendas recentes do CRM e importa diretamente para o Ecletech
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
use App\Models\ModelCrmIntegracao;
use App\Models\Venda\ModelVenda;
use App\Services\ServiceCrm;

try {
    // Carrega variáveis de ambiente
    $carregadorEnv = CarregadorEnv::obterInstancia();
    $carregadorEnv->carregar(__DIR__ . '/../../.env');

    $modelIntegracao = new ModelCrmIntegracao();
    $modelVenda = new ModelVenda();
    $serviceCrm = new ServiceCrm();

    // Busca todas as integrações ativas
    $integracoes = $modelIntegracao->listarAtivas();

    $totalImportado = 0;
    $totalCriados = 0;
    $totalAtualizados = 0;
    $totalErros = 0;

    foreach ($integracoes as $integracao) {
        $idLoja = $integracao['id_loja'];

        echo "Loja {$idLoja}: Importando vendas do CRM...\n";

        // Obtém o provider configurado
        $provider = $serviceCrm->obterProvider($idLoja);
        $handler = $provider->obterHandler('venda');

        // Busca vendas do CRM via API (paginado)
        $pagina = 1;
        $limite = 100;

        do {
            try {
                $resultado = $serviceCrm->listar('venda', $idLoja, $pagina, $limite);

                if (!isset($resultado['data']) || !is_array($resultado['data'])) {
                    break;
                }

                $totalPagina = count($resultado['data']);
                $criadosPagina = 0;
                $atualizadosPagina = 0;
                $errosPagina = 0;

                // Importa cada venda do CRM IMEDIATAMENTE
                foreach ($resultado['data'] as $vendaCrm) {
                    try {
                        // ID da venda no CRM externo
                        $externalId = (string) ($vendaCrm['id'] ?? null);

                        if (!$externalId) {
                            echo "    ⚠️ Venda sem ID, pulando...\n";
                            $errosPagina++;
                            continue;
                        }

                        // Transforma dados do CRM para formato Ecletech
                        $dadosTransformados = $handler->transformarParaInterno($vendaCrm);
                        $dadosTransformados['external_id'] = $externalId;

                        // Verifica se venda já existe no Ecletech
                        $vendaExistente = $modelVenda->buscarPorExternalId($externalId);

                        if ($vendaExistente) {
                            // Atualiza venda existente
                            $modelVenda->atualizar($vendaExistente['id'], $dadosTransformados);
                            $atualizadosPagina++;
                            $totalAtualizados++;
                        } else {
                            // Cria nova venda
                            $modelVenda->criar($dadosTransformados);
                            $criadosPagina++;
                            $totalCriados++;
                        }

                        $totalImportado++;

                    } catch (\Exception $e) {
                        echo "    ⚠️ Erro ao importar venda ID {$externalId}: " . $e->getMessage() . "\n";
                        $errosPagina++;
                        $totalErros++;
                    }
                }

                echo "  Página {$pagina}: {$totalPagina} vendas | ✓ {$criadosPagina} criadas | ↻ {$atualizadosPagina} atualizadas";
                if ($errosPagina > 0) {
                    echo " | ⚠ {$errosPagina} erros";
                }
                echo "\n";

                $pagina++;
                $totalPaginas = $resultado['pagination']['total_pages'] ?? 1;

            } catch (\Exception $e) {
                echo "  ⚠️ Erro na página {$pagina}: " . $e->getMessage() . "\n";
                break;
            }

        } while ($pagina <= $totalPaginas);
    }

    echo "\n";
    echo "========================================\n";
    echo "✅ IMPORTAÇÃO CONCLUÍDA\n";
    echo "========================================\n";
    echo "Total processado: {$totalImportado} vendas\n";
    echo "  ✓ Criadas: {$totalCriados}\n";
    echo "  ↻ Atualizadas: {$totalAtualizados}\n";
    if ($totalErros > 0) {
        echo "  ⚠ Erros: {$totalErros}\n";
    }
    echo "========================================\n";

} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
