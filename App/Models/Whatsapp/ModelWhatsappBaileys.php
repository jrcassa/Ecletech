<?php

namespace App\Models\Whatsapp;

use App\Models\Whatsapp\ModelWhatsappConfiguracao;

/**
 * Model para interagir com API Baileys WhatsApp
 */
class ModelWhatsappBaileys
{
    private ModelWhatsappConfiguracao $config;
    private ?string $apiBaseUrl;
    private ?string $instanceToken;
    private ?string $secureToken;

    public function __construct()
    {
        $this->config = new ModelWhatsappConfiguracao();
        $this->apiBaseUrl = $this->config->obter('api_base_url', null);
        $this->instanceToken = $this->config->obter('instancia_token', null);
        $this->secureToken = $this->config->obter('api_secure_token', null);
    }

    /**
     * Verifica se a API está configurada
     */
    private function verificarConfiguracao(): void
    {
        if (empty($this->apiBaseUrl)) {
            throw new \Exception('API Base URL não configurada. Configure a chave "api_base_url" nas configurações do WhatsApp.');
        }

        if (empty($this->instanceToken)) {
            throw new \Exception('Token da instância não configurado. Configure a chave "instancia_token" nas configurações do WhatsApp.');
        }

        if (empty($this->secureToken)) {
            throw new \Exception('Token de segurança não configurado. Configure a chave "api_secure_token" nas configurações do WhatsApp.');
        }
    }

    /**
     * Verifica se a API está configurada (método público)
     * Retorna array com status da configuração
     */
    public function estaConfigurado(): array
    {
        $configurado = !empty($this->apiBaseUrl) && !empty($this->instanceToken) && !empty($this->secureToken);

        return [
            'configurado' => $configurado,
            'api_base_url_configurada' => !empty($this->apiBaseUrl),
            'instancia_token_configurado' => !empty($this->instanceToken),
            'secure_token_configurado' => !empty($this->secureToken),
            'mensagem' => $configurado
                ? 'API configurada corretamente'
                : 'Configure api_base_url, instancia_token e api_secure_token nas configurações do WhatsApp'
        ];
    }

    /**
     * Envia requisição HTTP para API
     */
    private function request(string $endpoint, string $method = 'GET', ?array $data = null, int $tentativa = 1): string
    {
        // Verifica se a API está configurada
        $this->verificarConfiguracao();

        $maxRetries = $this->config->obter('api_max_retries', 3);
        $retryDelay = $this->config->obter('api_retry_delay', 2);

        $url = rtrim($this->apiBaseUrl, '/') . '/' . ltrim($endpoint, '/');

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config->obter('api_timeout', 30));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Headers com Authorization Bearer
        $headers = [
            'Authorization: Bearer ' . $this->secureToken,
            'Content-Type: application/json'
        ];

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        // Retry em caso de erro
        if (($error || $httpCode >= 500) && $tentativa < $maxRetries) {
            sleep($retryDelay * $tentativa);
            return $this->request($endpoint, $method, $data, $tentativa + 1);
        }

        if ($error) {
            throw new \Exception("Erro na requisição para {$url}: {$error}");
        }

        if ($httpCode >= 400) {
            throw new \Exception("Erro HTTP {$httpCode} na URL {$url}: {$response}");
        }

        // Verifica se a resposta está vazia
        if ($response === false || $response === '') {
            throw new \Exception("Resposta vazia da API para URL {$url}. HTTP Code: {$httpCode}");
        }

        return $response;
    }

    /**
     * Cria nova instância
     */
    public function criarInstancia(): string
    {
        return $this->request("instance/create?key={$this->instanceToken}", 'POST');
    }

    /**
     * Obtém status/QR code da instância
     */
    public function statusInstancia(): string
    {
        return $this->request("instance/qr?key={$this->instanceToken}");
    }

    /**
     * Obtém informações da instância
     */
    public function infoInstancia(): string
    {
        return $this->request("instance/info?key={$this->instanceToken}");
    }

    /**
     * Faz logout da instância
     */
    public function logoutInstancia(): string
    {
        return $this->request("instance/logout?key={$this->instanceToken}", 'POST');
    }

    /**
     * Deleta instância (BLOQUEADO - não usar)
     */
    public function deletarInstancia(): string
    {
        throw new \Exception('Operação bloqueada: deletar instância não é permitido por segurança');
    }

    /**
     * Envia mensagem de texto
     */
    public function sendText(string $numero, string $mensagem, array $options = []): string
    {
        $data = [
            'id' => $numero,
            'typeId' => 'user',
            'message' => $mensagem,
            'options' => [
                'delay' => $options['delay'] ?? 0,
                'replyFrom' => $options['replyFrom'] ?? ''
            ]
        ];

        return $this->request("message/text?key={$this->instanceToken}", 'POST', $data);
    }

    /**
     * Envia mensagem de texto para grupo
     */
    public function sendTextGrupo(string $grupoId, string $mensagem, array $options = []): string
    {
        $data = [
            'id' => $grupoId,
            'typeId' => 'group',
            'message' => $mensagem,
            'options' => [
                'delay' => $options['delay'] ?? 0,
                'replyFrom' => $options['replyFrom'] ?? ''
            ],
            'groupOptions' => [
                'markUser' => $options['markUser'] ?? 'ghostMention'
            ]
        ];

        return $this->request("message/text?key={$this->instanceToken}", 'POST', $data);
    }

    /**
     * Envia arquivo (imagem, PDF, etc)
     */
    public function sendFile(string $numero, string $tipo, ?string $url, ?string $base64, ?string $caption, ?string $filename): string
    {
        $data = ['number' => $numero];

        if ($url !== null) {
            $data['url'] = $url;
        } elseif ($base64 !== null) {
            $data['base64'] = $base64;
        } else {
            throw new \Exception('URL ou base64 é obrigatório');
        }

        if ($caption) {
            $data['caption'] = $caption;
        }

        if ($filename) {
            $data['filename'] = $filename;
        }

        // Mapeia tipo para endpoint
        $endpoints = [
            'image' => 'message/image',
            'pdf' => 'message/document',
            'document' => 'message/document',
            'audio' => 'message/audio',
            'video' => 'message/video'
        ];

        $endpoint = $endpoints[$tipo] ?? 'message/document';

        return $this->request("{$endpoint}?key={$this->instanceToken}", 'POST', $data);
    }
}
