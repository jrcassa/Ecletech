<?php

/**
 * WORKER CRON - Processador de Batches CRM
 *
 * Execução: A cada 1 minuto (ou conforme necessário)
 * Função: Processa batches pendentes na fila
 *
 * Adicione no crontab:
 * * * * * * php /caminho/para/Ecletech/cron/crm/crm_sync_worker.php >> /var/log/crm_worker.log 2>&1
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
use App\Models\ModelCrmSyncLog;
use App\Models\Cliente\ModelCliente;
use App\Models\Produtos\ModelProdutos;
use App\Models\Venda\ModelVenda;
use App\Services\ServiceCrm;

try {
    $timestamp = date('Y-m-d H:i:s');

    echo "========================================\n";
    echo "CRM SYNC WORKER - {$timestamp}\n";
    echo "========================================\n";

    // Carrega variáveis de ambiente
    $carregadorEnv = CarregadorEnv::obterInstancia();
    $carregadorEnv->carregar(__DIR__ . '/../../.env');

    // Instancia models e services
    $modelQueue = new ModelCrmSyncQueue();
    $modelLog = new ModelCrmSyncLog();
    $serviceCrm = new ServiceCrm();
    $modelCliente = new ModelCliente();
    $modelProduto = new ModelProdutos();
    $modelVenda = new ModelVenda();

    // Busca próximo batch pendente
    $batchId = $modelQueue->buscarProximoBatchPendente();

    if (!$batchId) {
        echo "✓ Nenhum batch pendente. Worker finalizado.\n";
        echo "========================================\n\n";
        exit(0);
    }

    echo "Processando batch: {$batchId}\n\n";

    // Busca todos os itens do batch
    $itens = $modelQueue->buscarPendentesPorBatch($batchId);

    if (empty($itens)) {
        echo "⚠ Batch vazio. Finalizando...\n";
        exit(0);
    }

    // Pega informações do primeiro item para criar log
    $primeiroItem = $itens[0];
    $scheduleId = $primeiroItem['schedule_id'];
    $idLoja = $primeiroItem['id_loja'];
    $entidade = $primeiroItem['entidade'];
    $direcao = $primeiroItem['direcao'];

    // Registra início no log
    $logId = $modelLog->registrarInicio(
        $batchId,
        $scheduleId,
        $idLoja,
        $entidade,
        $direcao,
        count($itens)
    );

    echo "Batch: {$batchId}\n";
    echo "Entidade: {$entidade}\n";
    echo "Direção: {$direcao}\n";
    echo "Total de itens: " . count($itens) . "\n";
    echo "----------------------------------------\n";

    $contadores = [
        'processados' => 0,
        'criados' => 0,
        'atualizados' => 0,
        'erros' => 0
    ];

    // Processa cada item do batch
    foreach ($itens as $item) {
        $itemId = $item['id'];
        $idRegistro = $item['id_registro'];
        $externalId = $item['external_id'];

        $inicioItem = microtime(true);

        try {
            // Marca como processando
            $modelQueue->marcarComoProcessando($itemId);

            if ($direcao === 'crm_para_ecletech') {
                // Importação: CRM -> Ecletech
                $resultado = processarImportacao(
                    $serviceCrm,
                    $entidade,
                    $idLoja,
                    $externalId,
                    $modelCliente,
                    $modelProduto,
                    $modelVenda
                );

                if ($resultado['criado']) {
                    $contadores['criados']++;
                } elseif ($resultado['atualizado']) {
                    $contadores['atualizados']++;
                }

            } elseif ($direcao === 'ecletech_para_crm') {
                // Exportação: Ecletech -> CRM
                $resultado = processarExportacao(
                    $serviceCrm,
                    $entidade,
                    $idLoja,
                    $idRegistro,
                    $externalId,
                    $modelCliente,
                    $modelProduto,
                    $modelVenda
                );

                if ($resultado['criado']) {
                    $contadores['criados']++;
                } elseif ($resultado['atualizado']) {
                    $contadores['atualizados']++;
                }
            }

            // Calcula tempo de processamento
            $fimItem = microtime(true);
            $tempoMs = round(($fimItem - $inicioItem) * 1000, 2);

            // Marca como completado
            $modelQueue->marcarComoCompletado($itemId, $tempoMs);
            $contadores['processados']++;

            echo ".";

        } catch (\Exception $e) {
            $erro = $e->getMessage();
            $tentativaAtual = ($item['tentativas'] ?? 0) + 1;

            // Marca como falho com retry
            $modelQueue->marcarComoFalho($itemId, $erro, $tentativaAtual);
            $contadores['erros']++;

            echo "E";

            error_log("Erro ao processar item {$itemId}: {$erro}");
        }
    }

    echo "\n\n";

    // Registra fim no log
    $status = ($contadores['erros'] === count($itens)) ? 'failed' : 'completed';
    $erroGeral = ($contadores['erros'] > 0) ? "{$contadores['erros']} itens com erro" : null;

    $modelLog->registrarFim(
        $logId,
        $contadores['processados'],
        $contadores['criados'],
        $contadores['atualizados'],
        $contadores['erros'],
        $status,
        $erroGeral
    );

    echo "========================================\n";
    echo "RESULTADO DO BATCH\n";
    echo "========================================\n";
    echo "✓ Processados: {$contadores['processados']}\n";
    echo "  ➕ Criados: {$contadores['criados']}\n";
    echo "  ↻ Atualizados: {$contadores['atualizados']}\n";
    echo "  ✗ Erros: {$contadores['erros']}\n";
    echo "========================================\n\n";

} catch (\Exception $e) {
    echo "❌ ERRO CRÍTICO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

/**
 * Processa importação do CRM para o Ecletech
 */
function processarImportacao(
    ServiceCrm $serviceCrm,
    string $entidade,
    int $idLoja,
    string $externalId,
    ModelCliente $modelCliente,
    ModelProdutos $modelProduto,
    ModelVenda $modelVenda
): array {
    // Busca dados do CRM
    $dadosCrm = $serviceCrm->buscar($entidade, $idLoja, $externalId);

    if (!$dadosCrm) {
        throw new \Exception("Registro não encontrado no CRM: {$externalId}");
    }

    // Obtém handler para transformação
    $provider = $serviceCrm->obterProvider($idLoja);
    $handler = $provider->obterHandler($entidade);

    // Transforma dados
    $dadosTransformados = $handler->transformarParaInterno($dadosCrm);
    $dadosTransformados['external_id'] = $externalId;

    // Verifica se já existe e salva
    switch ($entidade) {
        case 'cliente':
            $existente = $modelCliente->buscarPorExternalId($externalId);
            if ($existente) {
                $modelCliente->atualizar($existente['id'], $dadosTransformados);
                return ['criado' => false, 'atualizado' => true];
            } else {
                $modelCliente->criar($dadosTransformados);
                return ['criado' => true, 'atualizado' => false];
            }

        case 'produto':
            $existente = $modelProduto->buscarPorExternalId($externalId);
            if ($existente) {
                $modelProduto->atualizar($existente['id'], $dadosTransformados);
                return ['criado' => false, 'atualizado' => true];
            } else {
                $modelProduto->criar($dadosTransformados);
                return ['criado' => true, 'atualizado' => false];
            }

        case 'venda':
            $existente = $modelVenda->buscarPorExternalId($externalId);
            if ($existente) {
                $modelVenda->atualizar($existente['id'], $dadosTransformados);
                return ['criado' => false, 'atualizado' => true];
            } else {
                $modelVenda->criar($dadosTransformados);
                return ['criado' => true, 'atualizado' => false];
            }

        default:
            throw new \Exception("Entidade não suportada: {$entidade}");
    }
}

/**
 * Processa exportação do Ecletech para o CRM
 */
function processarExportacao(
    ServiceCrm $serviceCrm,
    string $entidade,
    int $idLoja,
    ?int $idRegistro,
    ?string $externalId,
    ModelCliente $modelCliente,
    ModelProdutos $modelProduto,
    ModelVenda $modelVenda
): array {
    if (!$idRegistro) {
        throw new \Exception("ID do registro local não informado");
    }

    // Busca dados locais
    switch ($entidade) {
        case 'cliente':
            $dadosLocal = $modelCliente->buscarPorId($idRegistro);
            break;

        case 'produto':
            $dadosLocal = $modelProduto->buscarPorId($idRegistro);
            break;

        case 'venda':
            $dadosLocal = $modelVenda->buscarPorId($idRegistro);
            break;

        default:
            throw new \Exception("Entidade não suportada: {$entidade}");
    }

    if (!$dadosLocal) {
        throw new \Exception("Registro local não encontrado: {$idRegistro}");
    }

    // Verifica se já existe no CRM (update) ou precisa criar
    if ($externalId) {
        // Atualiza no CRM
        $serviceCrm->atualizar($entidade, $idLoja, $externalId, $dadosLocal);
        return ['criado' => false, 'atualizado' => true];
    } else {
        // Cria no CRM
        $resultado = $serviceCrm->criar($entidade, $idLoja, $dadosLocal);
        $novoExternalId = $resultado['id'] ?? null;

        // Atualiza external_id no Ecletech
        if ($novoExternalId) {
            switch ($entidade) {
                case 'cliente':
                    $modelCliente->atualizar($idRegistro, ['external_id' => $novoExternalId]);
                    break;
                case 'produto':
                    $modelProduto->atualizar($idRegistro, ['external_id' => $novoExternalId]);
                    break;
                case 'venda':
                    $modelVenda->atualizar($idRegistro, ['external_id' => $novoExternalId]);
                    break;
            }
        }

        return ['criado' => true, 'atualizado' => false];
    }
}
