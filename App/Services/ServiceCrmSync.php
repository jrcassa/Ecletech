<?php

namespace App\Services;

use App\CRM\Core\CrmManager;
use App\CRM\Core\CrmException;
use App\Models\ModelCrmSyncLog;

/**
 * Service para sincronização bidirecional CRM
 */
class ServiceCrmSync
{
    private CrmManager $crmManager;
    private ModelCrmSyncLog $logModel;

    public function __construct()
    {
        $this->crmManager = new CrmManager();
        $this->logModel = new ModelCrmSyncLog();
    }

    /**
     * Sincroniza registro do Ecletech para o CRM
     */
    public function sincronizarParaCrm(string $entidade, array $dados, int $idLoja): array
    {
        try {
            $provider = $this->crmManager->obterProvider($idLoja);

            // Se já tem external_id, atualiza. Senão, cria
            if (!empty($dados['external_id'])) {
                $resultado = $provider->atualizar($entidade, $dados['external_id'], $dados, $idLoja);
                $acao = 'atualizado';
            } else {
                $resultado = $provider->criar($entidade, $dados, $idLoja);
                $acao = 'criado';
            }

            $this->registrarLog(
                $idLoja,
                $entidade,
                $dados['id'] ?? 0,
                'ecletech_para_crm',
                'sucesso',
                ucfirst($entidade) . " {$acao} no CRM",
                $dados,
                $resultado
            );

            return [
                'success' => true,
                'action' => $acao,
                'external_id' => $resultado['external_id'] ?? $dados['external_id'],
                'message' => ucfirst($entidade) . " {$acao} no CRM com sucesso"
            ];
        } catch (CrmException $e) {
            $this->registrarLog(
                $idLoja,
                $entidade,
                $dados['id'] ?? 0,
                'ecletech_para_crm',
                'erro',
                $e->getMessage(),
                $dados
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }

    /**
     * Sincroniza registro do CRM para o Ecletech
     */
    public function sincronizarParaEcletech(string $entidade, string $externalId, int $idLoja): array
    {
        try {
            $provider = $this->crmManager->obterProvider($idLoja);

            // Busca dados do CRM
            $dadosCrm = $provider->buscar($entidade, $externalId, $idLoja);

            // Transforma para formato Ecletech
            $handler = $provider->obterHandler($entidade);
            $dadosEcletech = $handler->transformarParaInterno($dadosCrm);

            $this->registrarLog(
                $idLoja,
                $entidade,
                0,
                'crm_para_ecletech',
                'sucesso',
                ucfirst($entidade) . " sincronizado do CRM",
                null,
                $dadosEcletech
            );

            return [
                'success' => true,
                'data' => $dadosEcletech,
                'message' => ucfirst($entidade) . " sincronizado do CRM com sucesso"
            ];
        } catch (CrmException $e) {
            $this->registrarLog(
                $idLoja,
                $entidade,
                0,
                'crm_para_ecletech',
                'erro',
                $e->getMessage()
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }

    /**
     * Sincroniza múltiplos registros do CRM (usado para importação inicial)
     */
    public function importarDoCrm(string $entidade, int $idLoja, int $limite = 100): array
    {
        try {
            $provider = $this->crmManager->obterProvider($idLoja);
            $handler = $provider->obterHandler($entidade);

            $pagina = 1;
            $importados = 0;
            $erros = 0;
            $registros = [];

            do {
                // Lista registros do CRM
                $resultado = $provider->listar($entidade, $idLoja, $pagina, $limite);
                $items = $resultado['data'] ?? [];

                foreach ($items as $itemCrm) {
                    try {
                        // Transforma para formato Ecletech
                        $dadosEcletech = $handler->transformarParaInterno($itemCrm);
                        $registros[] = $dadosEcletech;
                        $importados++;
                    } catch (\Exception $e) {
                        $erros++;
                    }
                }

                $pagina++;
                $temMais = ($resultado['pagination']['current_page'] ?? 0) < ($resultado['pagination']['total_pages'] ?? 0);
            } while ($temMais);

            $this->registrarLog(
                $idLoja,
                $entidade,
                0,
                'crm_para_ecletech',
                'sucesso',
                "Importação em lote: {$importados} registros importados, {$erros} erros"
            );

            return [
                'success' => true,
                'importados' => $importados,
                'erros' => $erros,
                'registros' => $registros,
                'message' => "{$importados} registros importados do CRM"
            ];
        } catch (CrmException $e) {
            $this->registrarLog(
                $idLoja,
                $entidade,
                0,
                'crm_para_ecletech',
                'erro',
                "Erro na importação em lote: " . $e->getMessage()
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }

    /**
     * Resolve conflito de sincronização usando estratégia definida
     */
    public function resolverConflito(
        string $entidade,
        array $dadosEcletech,
        array $dadosCrm,
        string $estrategia,
        int $idLoja
    ): array {
        try {
            switch ($estrategia) {
                case 'crm_vence':
                    // CRM tem prioridade
                    $dadosFinais = $dadosCrm;
                    $direcao = 'crm_para_ecletech';
                    break;

                case 'ecletech_vence':
                    // Ecletech tem prioridade
                    $dadosFinais = $dadosEcletech;
                    $direcao = 'ecletech_para_crm';
                    break;

                case 'mais_recente':
                    // Usa o mais recente baseado em updated_at
                    $timestampEcletech = strtotime($dadosEcletech['updated_at'] ?? '1970-01-01');
                    $timestampCrm = strtotime($dadosCrm['updated_at'] ?? '1970-01-01');

                    if ($timestampCrm > $timestampEcletech) {
                        $dadosFinais = $dadosCrm;
                        $direcao = 'crm_para_ecletech';
                    } else {
                        $dadosFinais = $dadosEcletech;
                        $direcao = 'ecletech_para_crm';
                    }
                    break;

                case 'mesclar':
                    // Mescla os dados (CRM sobrescreve apenas campos não vazios)
                    $dadosFinais = $dadosEcletech;
                    foreach ($dadosCrm as $campo => $valor) {
                        if (!empty($valor) && empty($dadosEcletech[$campo])) {
                            $dadosFinais[$campo] = $valor;
                        }
                    }
                    $direcao = 'ecletech_para_crm';
                    break;

                default:
                    throw new CrmException("Estratégia de conflito '{$estrategia}' não reconhecida");
            }

            $this->registrarLog(
                $idLoja,
                $entidade,
                $dadosEcletech['id'] ?? 0,
                $direcao,
                'sucesso',
                "Conflito resolvido usando estratégia: {$estrategia}",
                ['ecletech' => $dadosEcletech, 'crm' => $dadosCrm],
                $dadosFinais
            );

            return [
                'success' => true,
                'data' => $dadosFinais,
                'direction' => $direcao,
                'strategy' => $estrategia
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Registra log de sincronização
     */
    private function registrarLog(
        int $idLoja,
        string $entidade,
        int $idRegistro,
        string $direcao,
        string $status,
        string $mensagem,
        ?array $dadosEnviados = null,
        ?array $dadosRecebidos = null
    ): void {
        try {
            $this->logModel->criar([
                'id_loja' => $idLoja,
                'entidade' => $entidade,
                'id_registro' => $idRegistro,
                'direcao' => $direcao,
                'status' => $status,
                'mensagem' => $mensagem,
                'dados_enviados' => $dadosEnviados,
                'dados_recebidos' => $dadosRecebidos
            ]);
        } catch (\Exception $e) {
            // Ignora erros de log
        }
    }
}
