<?php

namespace App\Services;

use App\CRM\Core\CrmManager;
use App\CRM\Core\CrmException;
use App\Models\ModelCrmSyncLog;

/**
 * Service para operações básicas de CRM (CRUD)
 */
class ServiceCrm
{
    private CrmManager $crmManager;
    private ModelCrmSyncLog $logModel;

    public function __construct()
    {
        $this->crmManager = new CrmManager();
        $this->logModel = new ModelCrmSyncLog();
    }

    /**
     * Cria uma entidade no CRM
     */
    public function criar(string $entidade, array $dados, int $idLoja): array
    {
        try {
            $provider = $this->crmManager->obterProvider($idLoja);

            $resultado = $provider->criar($entidade, $dados, $idLoja);

            $this->registrarLog($idLoja, $entidade, $dados['id'] ?? 0, 'ecletech_para_crm', 'sucesso', 'Criado com sucesso', $dados, $resultado);

            return [
                'success' => true,
                'external_id' => $resultado['external_id'],
                'message' => 'Entidade criada no CRM com sucesso'
            ];
        } catch (CrmException $e) {
            $this->registrarLog($idLoja, $entidade, $dados['id'] ?? 0, 'ecletech_para_crm', 'erro', $e->getMessage(), $dados);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }

    /**
     * Atualiza uma entidade no CRM
     */
    public function atualizar(string $entidade, string $externalId, array $dados, int $idLoja): array
    {
        try {
            $provider = $this->crmManager->obterProvider($idLoja);

            $resultado = $provider->atualizar($entidade, $externalId, $dados, $idLoja);

            $this->registrarLog($idLoja, $entidade, $dados['id'] ?? 0, 'ecletech_para_crm', 'sucesso', 'Atualizado com sucesso', $dados, $resultado);

            return [
                'success' => true,
                'message' => 'Entidade atualizada no CRM com sucesso'
            ];
        } catch (CrmException $e) {
            $this->registrarLog($idLoja, $entidade, $dados['id'] ?? 0, 'ecletech_para_crm', 'erro', $e->getMessage(), $dados);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }

    /**
     * Busca uma entidade no CRM
     */
    public function buscar(string $entidade, string $externalId, int $idLoja): array
    {
        try {
            $provider = $this->crmManager->obterProvider($idLoja);

            $dados = $provider->buscar($entidade, $externalId, $idLoja);

            return [
                'success' => true,
                'data' => $dados
            ];
        } catch (CrmException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }

    /**
     * Lista entidades do CRM
     */
    public function listar(string $entidade, int $idLoja, int $pagina = 1, int $limite = 100): array
    {
        try {
            $provider = $this->crmManager->obterProvider($idLoja);

            $resultado = $provider->listar($entidade, $idLoja, $pagina, $limite);

            return [
                'success' => true,
                'data' => $resultado['data'],
                'pagination' => $resultado['pagination']
            ];
        } catch (CrmException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }

    /**
     * Deleta uma entidade no CRM
     */
    public function deletar(string $entidade, string $externalId, int $idLoja): array
    {
        try {
            $provider = $this->crmManager->obterProvider($idLoja);

            $resultado = $provider->deletar($entidade, $externalId, $idLoja);

            $this->registrarLog($idLoja, $entidade, 0, 'ecletech_para_crm', 'sucesso', 'Deletado com sucesso');

            return [
                'success' => true,
                'message' => 'Entidade deletada no CRM com sucesso'
            ];
        } catch (CrmException $e) {
            $this->registrarLog($idLoja, $entidade, 0, 'ecletech_para_crm', 'erro', $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }

    /**
     * Testa conexão com o CRM
     */
    public function testarConexao(int $idLoja): array
    {
        try {
            $provider = $this->crmManager->obterProvider($idLoja);

            return $provider->testarConexao($idLoja);
        } catch (CrmException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Verifica se loja tem integração CRM ativa
     */
    public function temIntegracaoAtiva(int $idLoja): bool
    {
        return $this->crmManager->temIntegracaoAtiva($idLoja);
    }

    /**
     * Registra log de operação
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
            // Ignora erros de log para não quebrar fluxo principal
        }
    }
}
