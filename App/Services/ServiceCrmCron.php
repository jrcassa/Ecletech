<?php

namespace App\Services;

use App\CRM\Core\CrmManager;
use App\CRM\Core\CrmException;
use App\Models\ModelCrmSyncQueue;
use App\Models\ModelCrmSyncLog;

/**
 * Service para processamento batch via cron (100/min sem delays)
 */
class ServiceCrmCron
{
    private CrmManager $crmManager;
    private ModelCrmSyncQueue $modelQueue;
    private ModelCrmSyncLog $modelLog;

    // Limite de registros por execução do cron (100/min)
    private const BATCH_SIZE = 100;

    // Máximo de tentativas antes de desistir
    private const MAX_TENTATIVAS = 3;

    public function __construct()
    {
        $this->crmManager = new CrmManager();
        $this->modelQueue = new ModelCrmSyncQueue();
        $this->modelLog = new ModelCrmSyncLog();
    }

    /**
     * Processa 100 itens da fila - chamado pelo cron a cada minuto
     */
    public function processar(): array
    {
        $inicio = microtime(true);
        $processados = 0;
        $erros = 0;

        // Busca 100 itens não processados (ordenados por prioridade)
        $itens = $this->modelQueue->buscarPendentes(self::BATCH_SIZE);

        foreach ($itens as $item) {
            try {
                $this->processarItem($item);
                $processados++;
            } catch (\Exception $e) {
                $erros++;
                $this->registrarErro($item, $e);
            }
        }

        $tempo = round(microtime(true) - $inicio, 2);

        return [
            'processados' => $processados,
            'erros' => $erros,
            'tempo_segundos' => $tempo,
            'taxa' => $processados > 0 ? round($processados / $tempo, 2) : 0
        ];
    }

    /**
     * Processa um único item da fila - SEM DELAY
     */
    private function processarItem(array $item): void
    {
        $provider = $this->crmManager->obterProvider($item['id_loja']);

        switch ($item['direcao']) {
            case 'ecletech_para_crm':
                $this->sincronizarParaCrm($provider, $item);
                break;

            case 'crm_para_ecletech':
                $this->sincronizarParaEcletech($provider, $item);
                break;
        }

        // Marca como processado
        $this->modelQueue->marcarProcessado($item['id']);
    }

    /**
     * Sincroniza do Ecletech para CRM
     */
    private function sincronizarParaCrm($provider, array $item): void
    {
        // Busca dados do Ecletech
        $model = $this->obterModel($item['entidade']);
        $dados = $model->buscarPorId($item['id_registro']);

        if (!$dados) {
            throw new CrmException("Registro {$item['entidade']}#{$item['id_registro']} não encontrado");
        }

        // Se já tem external_id, atualiza. Senão, cria
        if (!empty($dados['external_id'])) {
            $provider->atualizar($item['entidade'], $dados['external_id'], $dados, $item['id_loja']);
            $acao = 'atualizado';
        } else {
            $resultado = $provider->criar($item['entidade'], $dados, $item['id_loja']);

            // Salva external_id no Ecletech
            $model->atualizar($item['id_registro'], ['external_id' => $resultado['external_id']]);
            $acao = 'criado';
        }

        $this->registrarLog($item, 'sucesso', ucfirst($item['entidade']) . " {$acao} no CRM");
    }

    /**
     * Sincroniza do CRM para Ecletech (importação)
     */
    private function sincronizarParaEcletech($provider, array $item): void
    {
        $model = $this->obterModel($item['entidade']);

        // Obtém external_id (da fila ou do registro local)
        $externalId = $item['external_id'] ?? null;

        if (empty($externalId) && !empty($item['id_registro'])) {
            // Se não tem external_id na fila, tenta pegar do registro local
            $dadosLocais = $model->buscarPorId($item['id_registro']);
            $externalId = $dadosLocais['external_id'] ?? null;
        }

        if (empty($externalId)) {
            throw new CrmException("External ID não encontrado para sincronização");
        }

        // Busca dados do CRM
        $dadosCrm = $provider->buscar($item['entidade'], $externalId, $item['id_loja']);

        // Transforma para formato Ecletech
        $handler = $provider->obterHandler($item['entidade']);
        $dadosTransformados = $handler->transformarParaInterno($dadosCrm);

        // Adiciona external_id aos dados transformados
        $dadosTransformados['external_id'] = $externalId;

        // Verifica se já existe registro com este external_id
        $registroExistente = $model->buscarPorExternalId($externalId);

        if ($registroExistente) {
            // Atualiza registro existente
            $model->atualizar($registroExistente['id'], $dadosTransformados);
            $acao = 'atualizado';
        } else {
            // Cria novo registro
            $novoId = $model->criar($dadosTransformados);
            $acao = 'criado';

            // Atualiza a fila com o ID local
            $this->modelQueue->atualizar($item['id'], ['id_registro' => $novoId]);
        }

        $this->registrarLog($item, 'sucesso', ucfirst($item['entidade']) . " {$acao} no Ecletech (do CRM)");
    }

    /**
     * Registra erro e incrementa tentativas
     */
    private function registrarErro(array $item, \Exception $e): void
    {
        // Incrementa tentativas
        $tentativas = $item['tentativas'] + 1;

        $update = [
            'tentativas' => $tentativas,
            'erro' => $e->getMessage()
        ];

        // Se atingiu máximo, marca como processado (para não tentar mais)
        if ($tentativas >= self::MAX_TENTATIVAS) {
            $update['processado'] = 1;
            $update['processado_em'] = date('Y-m-d H:i:s');
        }

        $this->modelQueue->atualizar($item['id'], $update);
        $this->registrarLog($item, 'erro', $e->getMessage());
    }

    /**
     * Registra log de processamento
     */
    private function registrarLog(array $item, string $status, string $mensagem): void
    {
        try {
            $this->modelLog->criar([
                'id_loja' => $item['id_loja'],
                'entidade' => $item['entidade'],
                'id_registro' => $item['id_registro'],
                'direcao' => $item['direcao'],
                'status' => $status,
                'mensagem' => $mensagem
            ]);
        } catch (\Exception $e) {
            // Ignora erros de log
        }
    }

    /**
     * Obtém o model apropriado para a entidade
     */
    private function obterModel(string $entidade): object
    {
        // Mapa de entidades para models
        $modelMap = [
            'cliente' => 'App\\Models\\Cliente\\ModelCliente',
            'produto' => 'App\\Models\\Produtos\\ModelProdutos',
            'venda' => 'App\\Models\\Venda\\ModelVenda',
            'atividade' => 'App\\Models\\Atividade\\ModelAtividade'
        ];

        if (!isset($modelMap[$entidade])) {
            throw new CrmException("Model não encontrado para entidade: {$entidade}");
        }

        $className = $modelMap[$entidade];

        if (!class_exists($className)) {
            throw new CrmException("Classe do model não existe: {$className}");
        }

        return new $className();
    }

    /**
     * Retorna estatísticas da fila
     */
    public function obterEstatisticas(): array
    {
        return $this->modelQueue->obterEstatisticas();
    }
}
