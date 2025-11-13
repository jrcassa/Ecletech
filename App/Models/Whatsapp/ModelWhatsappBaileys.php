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
    private ?string $webhookUrl;

    public function __construct()
    {
        $this->config = new ModelWhatsappConfiguracao();
        $this->apiBaseUrl = $this->config->obter('api_base_url', null);
        $this->instanceToken = $this->config->obter('instancia_token', null);
        $this->secureToken = $this->config->obter('api_secure_token', null);
        $this->webhookUrl = $this->config->obter('webhook_url', null);
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
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Segue redirecionamentos (301, 302, etc)
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3); // Máximo 3 redirecionamentos
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Verifica SSL
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // Verifica hostname SSL

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
    public function cria_instancia()
    {
        $retorno = false;

        // URL da API com o admintoken como parâmetro
        $url = "https://whatsapp.ecletech.com.br/instance/init?admintoken={$this->secureToken}";

        // Dados a serem enviados na requisição
        $data = [
            "key" => $this->instanceToken,
            "browser" => "Ubuntu",
            "webhook" => true,
            "base64" => true,
            "webhookUrl" => $this->webhookUrl,
            "webhookEvents" => ["messages.upsert"],
            "ignoreGroups" => false,
            "messagesRead" => false
        ];

        // Inicializa o cURL
        $ch = curl_init($url);

        // Configurações da requisição cURL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->secureToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        // Executa a requisição e captura a resposta
        $response = curl_exec($ch);

        // Verifica se houve erro na execução
        if (curl_errno($ch)) {
            $retorno = curl_error($ch);
            // echo 'Erro: ' . curl_error($ch);
        } else {
            $retorno = $response;
        }

        // Fecha a conexão cURL
        curl_close($ch);

        return $retorno;
    }

    /**
     * Método alias para compatibilidade
     * @deprecated Use cria_instancia() ao invés
     */
    public function criarInstancia()
    {
        return $this->cria_instancia();
    }

    /**
     * Obtém status/QR code da instância
     */
    public function statusInstancia(): string
    {
        return $this->request("instance/qr?key={$this->instanceToken}");
    }

    /**
     * Obtém QR Code em base64 da instância
     * Usado quando a instância está aguardando conexão
     */
    public function status_instancia()
    {
        $retorno = false;

        // URL da API para obter QR Code em base64
        $url = "https://whatsapp.ecletech.com.br/instance/qrbase64?key=" . urlencode($this->instanceToken);

        // Inicializa o cURL
        $ch = curl_init($url);

        // Configurações da requisição cURL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->secureToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_HTTPGET, true); // Define que é uma requisição GET

        // Executa a requisição e captura a resposta
        $response = curl_exec($ch);

        // Verifica se houve erro na execução
        if (curl_errno($ch)) {
            $retorno = curl_error($ch);
        } else {
            $retorno = $response;
        }

        // Fecha a conexão cURL
        curl_close($ch);

        return $retorno;
    }

    /**
     * Método alias para compatibilidade
     * @deprecated Use status_instancia() para QR base64
     */
    public function obterQRCodeBase64()
    {
        return $this->status_instancia();
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
