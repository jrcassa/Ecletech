<?php

namespace App\CRM\Providers\GestaoClick;

use App\CRM\Providers\CrmProviderInterface;
use App\CRM\Core\CrmException;

/**
 * Provider para integração com GestãoClick
 */
class GestaoClickProvider implements CrmProviderInterface
{
    private array $configuracao;
    private array $config;
    private array $credenciais;

    public function __construct(array $configuracao)
    {
        $this->configuracao = $configuracao;
        $this->config = require __DIR__ . '/config.php';
        $this->credenciais = $configuracao['credenciais'] ?? [];

        $this->validarCredenciais();
    }

    /**
     * {@inheritdoc}
     */
    public function criar(string $entidade, array $dados, int $idLoja): array
    {
        $handler = $this->obterHandler($entidade);
        $dadosTransformados = $handler->transformarParaExterno($dados);

        $endpoint = $this->obterEndpoint($entidade, 'criar');
        $response = $this->requisicao('POST', $endpoint, $dadosTransformados, $idLoja);

        return ['external_id' => (string) $response['id']];
    }

    /**
     * {@inheritdoc}
     */
    public function atualizar(string $entidade, string $externalId, array $dados, int $idLoja): array
    {
        $handler = $this->obterHandler($entidade);
        $dadosTransformados = $handler->transformarParaExterno($dados);

        $endpoint = $this->obterEndpoint($entidade, 'atualizar', $externalId);
        $this->requisicao('PUT', $endpoint, $dadosTransformados, $idLoja);

        return ['success' => true];
    }

    /**
     * {@inheritdoc}
     */
    public function buscar(string $entidade, string $externalId, int $idLoja): array
    {
        $endpoint = $this->obterEndpoint($entidade, 'buscar', $externalId);
        $response = $this->requisicao('GET', $endpoint, null, $idLoja);

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function listar(string $entidade, int $idLoja, int $pagina = 1, int $limite = 100): array
    {
        $endpoint = $this->obterEndpoint($entidade, 'listar');

        $queryParams = [
            'page' => $pagina,
            'limit' => $limite
        ];

        $response = $this->requisicao('GET', $endpoint, $queryParams, $idLoja);

        return [
            'data' => $response['data'] ?? [],
            'pagination' => [
                'current_page' => $response['current_page'] ?? $pagina,
                'total_pages' => $response['total_pages'] ?? 1,
                'total_items' => $response['total_items'] ?? 0,
                'per_page' => $limite
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function deletar(string $entidade, string $externalId, int $idLoja): array
    {
        $endpoint = $this->obterEndpoint($entidade, 'deletar', $externalId);
        $this->requisicao('DELETE', $endpoint, null, $idLoja);

        return ['success' => true];
    }

    /**
     * {@inheritdoc}
     */
    public function obterHandler(string $entidade): object
    {
        $className = __NAMESPACE__ . '\\Handlers\\' . ucfirst($entidade) . 'Handler';

        if (!class_exists($className)) {
            throw new CrmException("Handler não encontrado: {$className}");
        }

        return new $className($this->config);
    }

    /**
     * {@inheritdoc}
     */
    public function testarConexao(int $idLoja): array
    {
        try {
            // Tenta listar clientes (apenas 1 para testar)
            $this->listar('cliente', $idLoja, 1, 1);

            return [
                'success' => true,
                'message' => 'Conexão estabelecida com sucesso!'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro na conexão: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Faz uma requisição HTTP para a API do GestaoClick
     */
    private function requisicao(string $metodo, string $endpoint, ?array $dados, int $idLoja): ?array
    {
        $url = $this->config['api_base_url'] . $endpoint;

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->credenciais['api_token']
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);

        switch ($metodo) {
            case 'GET':
                if ($dados) {
                    $url .= '?' . http_build_query($dados);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
                break;

            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
                break;

            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
                break;

            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro = curl_error($ch);

        curl_close($ch);

        if ($erro) {
            throw new CrmException("Erro na requisição cURL: {$erro}");
        }

        // Decodifica resposta
        $responseData = json_decode($response, true);

        // Verifica códigos de erro HTTP
        if ($httpCode >= 400) {
            $mensagemErro = $responseData['message'] ?? $responseData['error'] ?? 'Erro desconhecido';
            throw new CrmException(
                "Erro HTTP {$httpCode}: {$mensagemErro}",
                $httpCode,
                null,
                ['response' => $responseData]
            );
        }

        return $responseData;
    }

    /**
     * Obtém o endpoint configurado para uma entidade e ação
     */
    private function obterEndpoint(string $entidade, string $acao, ?string $id = null): string
    {
        if (!isset($this->config['endpoints'][$entidade])) {
            throw new CrmException("Entidade '{$entidade}' não configurada");
        }

        if (!isset($this->config['endpoints'][$entidade][$acao])) {
            throw new CrmException("Ação '{$acao}' não configurada para entidade '{$entidade}'");
        }

        $endpoint = $this->config['endpoints'][$entidade][$acao];

        // Substitui {id} pelo ID real
        if ($id) {
            $endpoint = str_replace('{id}', $id, $endpoint);
        }

        return $endpoint;
    }

    /**
     * Valida se as credenciais necessárias estão presentes
     */
    private function validarCredenciais(): void
    {
        if (empty($this->credenciais['api_token'])) {
            throw new CrmException("Credencial 'api_token' não encontrada para GestaoClick");
        }
    }
}
