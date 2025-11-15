<?php

namespace App\Services;

use App\Models\ModelCrmSyncSchedule;
use App\Models\ModelCrmSyncQueue;
use App\Models\ModelCrmSyncLog;
use App\Models\Cliente\ModelCliente;
use App\Models\Produtos\ModelProdutos;
use App\Models\Venda\ModelVenda;

/**
 * Service para gerenciar execução de agendamentos CRM
 */
class ServiceCrmScheduler
{
    private ModelCrmSyncSchedule $modelSchedule;
    private ModelCrmSyncQueue $modelQueue;
    private ModelCrmSyncLog $modelLog;
    private ServiceCrm $serviceCrm;

    public function __construct()
    {
        $this->modelSchedule = new ModelCrmSyncSchedule();
        $this->modelQueue = new ModelCrmSyncQueue();
        $this->modelLog = new ModelCrmSyncLog();
        $this->serviceCrm = new ServiceCrm();
    }

    /**
     * Processa agendamentos prontos para execução (chamado pelo cron master)
     */
    public function processarAgendamentosProntos(): array
    {
        $schedules = $this->modelSchedule->buscarProntosParaExecutar();
        $resultados = [];

        foreach ($schedules as $schedule) {
            try {
                $resultado = $this->executarAgendamento($schedule);
                $resultados[] = $resultado;
            } catch (\Exception $e) {
                $resultados[] = [
                    'schedule_id' => $schedule['id'],
                    'sucesso' => false,
                    'erro' => $e->getMessage()
                ];
            }
        }

        return $resultados;
    }

    /**
     * Executa um agendamento específico
     */
    public function executarAgendamento(array $schedule): array
    {
        $scheduleId = $schedule['id'];
        $idLoja = $schedule['id_loja'];
        $entidade = $schedule['entidade'];
        $direcao = $schedule['direcao'];
        $batchSize = $schedule['batch_size'] ?? 10;

        // Marca schedule como executando
        $this->modelSchedule->marcarComoExecutando($scheduleId, true);

        try {
            $batchId = uniqid('batch_', true);
            $registros = [];

            // Busca registros baseado na direção
            if ($direcao === 'crm_para_ecletech') {
                $registros = $this->buscarRegistrosDoCrm($idLoja, $entidade, $batchSize);
            } elseif ($direcao === 'ecletech_para_crm') {
                $registros = $this->buscarRegistrosDoEcletech($idLoja, $entidade, $batchSize);
            } elseif ($direcao === 'bidirecional') {
                // Para bidirecional, processa ambas direções
                $registrosCrm = $this->buscarRegistrosDoCrm($idLoja, $entidade, $batchSize);
                $registrosEcletech = $this->buscarRegistrosDoEcletech($idLoja, $entidade, $batchSize);

                // Cria dois batches separados
                if (!empty($registrosCrm)) {
                    $batchIdCrm = uniqid('batch_crm_', true);
                    $totalCrm = $this->modelQueue->enfileirarBatch(
                        $batchIdCrm,
                        $scheduleId,
                        $idLoja,
                        $entidade,
                        'crm_para_ecletech',
                        $registrosCrm,
                        $schedule['prioridade'] ?? 0
                    );
                }

                if (!empty($registrosEcletech)) {
                    $batchIdEcletech = uniqid('batch_ecl_', true);
                    $totalEcletech = $this->modelQueue->enfileirarBatch(
                        $batchIdEcletech,
                        $scheduleId,
                        $idLoja,
                        $entidade,
                        'ecletech_para_crm',
                        $registrosEcletech,
                        $schedule['prioridade'] ?? 0
                    );
                }

                $totalEnfileirado = ($totalCrm ?? 0) + ($totalEcletech ?? 0);
            } else {
                // Direção única
                if (empty($registros)) {
                    // Sem registros para processar
                    $this->modelSchedule->registrarExecucao($scheduleId, 0, 0);
                    $this->modelSchedule->marcarComoExecutando($scheduleId, false);

                    return [
                        'schedule_id' => $scheduleId,
                        'batch_id' => null,
                        'sucesso' => true,
                        'registros_enfileirados' => 0,
                        'mensagem' => 'Nenhum registro para processar'
                    ];
                }

                // Enfileira batch
                $totalEnfileirado = $this->modelQueue->enfileirarBatch(
                    $batchId,
                    $scheduleId,
                    $idLoja,
                    $entidade,
                    $direcao,
                    $registros,
                    $schedule['prioridade'] ?? 0
                );
            }

            // Atualiza schedule
            $this->modelSchedule->registrarExecucao($scheduleId, $totalEnfileirado ?? 0, 0);
            $this->modelSchedule->marcarComoExecutando($scheduleId, false);

            return [
                'schedule_id' => $scheduleId,
                'batch_id' => $batchId ?? null,
                'sucesso' => true,
                'registros_enfileirados' => $totalEnfileirado ?? 0
            ];

        } catch (\Exception $e) {
            // Marca schedule como não executando em caso de erro
            $this->modelSchedule->marcarComoExecutando($scheduleId, false);
            throw $e;
        }
    }

    /**
     * Busca registros do CRM para importar
     */
    private function buscarRegistrosDoCrm(int $idLoja, string $entidade, int $limit): array
    {
        try {
            // Busca registros do CRM via API
            $resultado = $this->serviceCrm->listar($entidade, $idLoja, 1, $limit);

            if (!isset($resultado['data']) || !is_array($resultado['data'])) {
                return [];
            }

            $registros = [];
            foreach ($resultado['data'] as $item) {
                $externalId = (string) ($item['id'] ?? null);

                if ($externalId) {
                    $registros[] = [
                        'external_id' => $externalId,
                        'id_registro' => null // Ainda não existe no Ecletech
                    ];
                }
            }

            return $registros;

        } catch (\Exception $e) {
            error_log("Erro ao buscar registros do CRM: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca registros do Ecletech para exportar ao CRM
     */
    private function buscarRegistrosDoEcletech(int $idLoja, string $entidade, int $limit): array
    {
        try {
            $registros = [];

            switch ($entidade) {
                case 'cliente':
                    $modelCliente = new ModelCliente();
                    $clientes = $this->buscarClientesSemSincronizar($modelCliente, $idLoja, $limit);

                    foreach ($clientes as $cliente) {
                        $registros[] = [
                            'id_registro' => $cliente['id'],
                            'external_id' => $cliente['external_id'] ?? null
                        ];
                    }
                    break;

                case 'produto':
                    $modelProduto = new ModelProdutos();
                    $produtos = $this->buscarProdutosSemSincronizar($modelProduto, $idLoja, $limit);

                    foreach ($produtos as $produto) {
                        $registros[] = [
                            'id_registro' => $produto['id'],
                            'external_id' => $produto['external_id'] ?? null
                        ];
                    }
                    break;

                case 'venda':
                    $modelVenda = new ModelVenda();
                    $vendas = $this->buscarVendasSemSincronizar($modelVenda, $idLoja, $limit);

                    foreach ($vendas as $venda) {
                        $registros[] = [
                            'id_registro' => $venda['id'],
                            'external_id' => $venda['external_id'] ?? null
                        ];
                    }
                    break;
            }

            return $registros;

        } catch (\Exception $e) {
            error_log("Erro ao buscar registros do Ecletech: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca clientes que precisam ser sincronizados
     */
    private function buscarClientesSemSincronizar(ModelCliente $model, int $idLoja, int $limit): array
    {
        // Busca clientes sem external_id (nunca sincronizados)
        // ou modificados recentemente
        // TODO: Implementar lógica de verificação de modificação
        return [];
    }

    /**
     * Busca produtos que precisam ser sincronizados
     */
    private function buscarProdutosSemSincronizar(ModelProdutos $model, int $idLoja, int $limit): array
    {
        // Busca produtos sem external_id (nunca sincronizados)
        // ou modificados recentemente
        // TODO: Implementar lógica de verificação de modificação
        return [];
    }

    /**
     * Busca vendas que precisam ser sincronizadas
     */
    private function buscarVendasSemSincronizar(ModelVenda $model, int $idLoja, int $limit): array
    {
        // Busca vendas sem external_id (nunca sincronizadas)
        // ou modificadas recentemente
        // TODO: Implementar lógica de verificação de modificação
        return [];
    }

    /**
     * Obtém status de um agendamento
     */
    public function obterStatusAgendamento(int $scheduleId): array
    {
        $schedule = $this->modelSchedule->buscarPorId($scheduleId);

        if (!$schedule) {
            throw new \Exception("Agendamento não encontrado");
        }

        $stats = $this->modelLog->obterEstatisticasPorSchedule($scheduleId);

        return [
            'schedule' => $schedule,
            'estatisticas' => $stats
        ];
    }
}
