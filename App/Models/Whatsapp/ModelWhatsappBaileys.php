<?php

namespace App\Models\Whatsapp;

use App\Models\Whatsapp\ModelWhatsappConfiguracao;

/**
 * Model para interagir com API Baileys WhatsApp
 */
class ModelWhatsappBaileys
{
    private ModelWhatsappConfiguracao $config;
    private string $apiUrl;
    private string $instanceToken;

    public function __construct()
    {
        $this->config = new ModelWhatsappConfiguracao();
        $this->apiUrl = $this->config->obter('api_url');
        $this->instanceToken = $this->config->obter('instancia_token');
    }

    /**
     * Envia requisição HTTP para API
     */
    private function request(string $endpoint, string $method = 'GET', ?array $data = null, int $tentativa = 1): string
    {
        $maxRetries = $this->config->obter('api_max_retries', 3);
        $retryDelay = $this->config->obter('api_retry_delay', 2);

        $url = rtrim($this->apiUrl, '/') . '/' . ltrim($endpoint, '/');

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config->obter('api_timeout', 30));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $headers = ['Content-Type: application/json'];

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
            throw new \Exception("Erro na requisição: {$error}");
        }

        if ($httpCode >= 400) {
            throw new \Exception("Erro HTTP {$httpCode}: {$response}");
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
    public function sendText(string $numero, string $mensagem): string
    {
        $data = [
            'number' => $numero,
            'text' => $mensagem
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
