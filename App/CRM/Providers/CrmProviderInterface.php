<?php

namespace App\CRM\Providers;

/**
 * Interface que todos os providers de CRM devem implementar
 */
interface CrmProviderInterface
{
    /**
     * Cria uma nova entidade no CRM
     *
     * @param string $entidade Nome da entidade (cliente, produto, venda, atividade)
     * @param array $dados Dados da entidade no formato Ecletech
     * @param int $idLoja ID da loja
     * @return array ['external_id' => string]
     * @throws \App\CRM\Core\CrmException
     */
    public function criar(string $entidade, array $dados, int $idLoja): array;

    /**
     * Atualiza uma entidade existente no CRM
     *
     * @param string $entidade Nome da entidade
     * @param string $externalId ID da entidade no CRM
     * @param array $dados Dados atualizados no formato Ecletech
     * @param int $idLoja ID da loja
     * @return array ['success' => bool]
     * @throws \App\CRM\Core\CrmException
     */
    public function atualizar(string $entidade, string $externalId, array $dados, int $idLoja): array;

    /**
     * Busca uma entidade no CRM
     *
     * @param string $entidade Nome da entidade
     * @param string $externalId ID da entidade no CRM
     * @param int $idLoja ID da loja
     * @return array Dados da entidade no formato do CRM
     * @throws \App\CRM\Core\CrmException
     */
    public function buscar(string $entidade, string $externalId, int $idLoja): array;

    /**
     * Lista entidades do CRM (com paginação)
     *
     * @param string $entidade Nome da entidade
     * @param int $idLoja ID da loja
     * @param int $pagina Página atual (inicia em 1)
     * @param int $limite Quantidade de registros por página
     * @return array ['data' => array, 'pagination' => array]
     * @throws \App\CRM\Core\CrmException
     */
    public function listar(string $entidade, int $idLoja, int $pagina = 1, int $limite = 100): array;

    /**
     * Deleta uma entidade no CRM
     *
     * @param string $entidade Nome da entidade
     * @param string $externalId ID da entidade no CRM
     * @param int $idLoja ID da loja
     * @return array ['success' => bool]
     * @throws \App\CRM\Core\CrmException
     */
    public function deletar(string $entidade, string $externalId, int $idLoja): array;

    /**
     * Obtém o handler de transformação para uma entidade
     *
     * @param string $entidade Nome da entidade
     * @return object Handler da entidade
     * @throws \App\CRM\Core\CrmException
     */
    public function obterHandler(string $entidade): object;

    /**
     * Testa a conexão com o CRM
     *
     * @param int $idLoja ID da loja
     * @return array ['success' => bool, 'message' => string]
     */
    public function testarConexao(int $idLoja): array;
}
