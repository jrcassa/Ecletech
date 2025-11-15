<?php

/**
 * Cron para importação de PRODUTOS do CRM
 * Busca todos os produtos do CRM e importa diretamente para o Ecletech
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
use App\Models\Produtos\ModelProdutos;
use App\Services\ServiceCrm;

try {
    // Carrega variáveis de ambiente
    $carregadorEnv = CarregadorEnv::obterInstancia();
    $carregadorEnv->carregar(__DIR__ . '/../../.env');

    $modelIntegracao = new ModelCrmIntegracao();
    $modelProduto = new ModelProdutos();
    $serviceCrm = new ServiceCrm();

    // Busca todas as integrações ativas
    $integracoes = $modelIntegracao->listarAtivas();

    $totalImportado = 0;
    $totalCriados = 0;
    $totalAtualizados = 0;
    $totalErros = 0;

    foreach ($integracoes as $integracao) {
        $idLoja = $integracao['id_loja'];

        echo "Loja {$idLoja}: Importando produtos do CRM...\n";

        // Obtém o provider configurado
        $provider = $serviceCrm->obterProvider($idLoja);
        $handler = $provider->obterHandler('produto');

        // Busca produtos do CRM via API (paginado)
        $pagina = 1;
        $limite = 100;

        do {
            try {
                $resultado = $serviceCrm->listar('produto', $idLoja, $pagina, $limite);

                if (!isset($resultado['data']) || !is_array($resultado['data'])) {
                    break;
                }

                $totalPagina = count($resultado['data']);
                $criadosPagina = 0;
                $atualizadosPagina = 0;
                $errosPagina = 0;

                // Importa cada produto do CRM IMEDIATAMENTE
                foreach ($resultado['data'] as $produtoCrm) {
                    try {
                        // ID do produto no CRM externo
                        $externalId = (string) ($produtoCrm['id'] ?? null);

                        if (!$externalId) {
                            echo "    ⚠️ Produto sem ID, pulando...\n";
                            $errosPagina++;
                            continue;
                        }

                        // Transforma dados do CRM para formato Ecletech
                        $dadosTransformados = $handler->transformarParaInterno($produtoCrm);
                        $dadosTransformados['external_id'] = $externalId;

                        // Verifica se produto já existe no Ecletech
                        $produtoExistente = $modelProduto->buscarPorExternalId($externalId);

                        if ($produtoExistente) {
                            // Atualiza produto existente
                            $modelProduto->atualizar($produtoExistente['id'], $dadosTransformados);
                            $atualizadosPagina++;
                            $totalAtualizados++;
                        } else {
                            // Cria novo produto
                            $modelProduto->criar($dadosTransformados);
                            $criadosPagina++;
                            $totalCriados++;
                        }

                        $totalImportado++;

                    } catch (\Exception $e) {
                        echo "    ⚠️ Erro ao importar produto ID {$externalId}: " . $e->getMessage() . "\n";
                        $errosPagina++;
                        $totalErros++;
                    }
                }

                echo "  Página {$pagina}: {$totalPagina} produtos | ✓ {$criadosPagina} criados | ↻ {$atualizadosPagina} atualizados";
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
    echo "Total processado: {$totalImportado} produtos\n";
    echo "  ✓ Criados: {$totalCriados}\n";
    echo "  ↻ Atualizados: {$totalAtualizados}\n";
    if ($totalErros > 0) {
        echo "  ⚠ Erros: {$totalErros}\n";
    }
    echo "========================================\n";

} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
