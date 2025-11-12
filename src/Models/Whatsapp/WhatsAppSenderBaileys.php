<?php

namespace Models\Whatsapp;

use Models\Whatsapp\WhatsAppConfiguracao;
use Helpers\Utils;

class WhatsAppSenderBaileys
{
    private $conn;
    private $config;

    // Propriedades configuráveis
    private $baseUrl;
    private $secureToken;
    private $webhookUrl;
    private $token;
    private $timeout;
    private $retryTentativas;
    private $retryIntervalo;

    public function __construct($db = null)
    {
        $this->conn = $db;

        if ($db) {
            $this->config = new WhatsAppConfiguracao($db);
            $this->carregarConfiguracoes();
        } else {
            // Fallback para valores hardcoded (compatibilidade)
            $this->baseUrl = 'https://whatsapp.ecletech.com.br';
            $this->secureToken = '205e8ecac97670e7e60578ac8a2217a04572742cd15';
            $this->webhookUrl = 'https://inovar.com.br/services/whatsapp/webhook';
            $this->timeout = 30;
            $this->retryTentativas = 3;
            $this->retryIntervalo = 5;
        }
    }

    /**
     * Carrega configurações do banco
     */
    private function carregarConfiguracoes()
    {
        $this->baseUrl = $this->config->obter('api_base_url', 'https://whatsapp.ecletech.com.br');
        $this->secureToken = $this->config->obter('api_secure_token');
        $this->webhookUrl = $this->config->obter('webhook_url');
        $this->token = $this->config->obter('instancia_token');
        $this->timeout = $this->config->obter('api_timeout', 30);
        $this->retryTentativas = $this->config->obter('api_retry_tentativas', 3);
        $this->retryIntervalo = $this->config->obter('api_retry_intervalo', 5);
    }

    /**
     * Requisição HTTP genérica com retry
     */
    private function request($method, $endpoint, $data = null, $tentativa = 1)
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->secureToken,
            'Content-Type: application/json'
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Se houve erro e ainda tem tentativas
        if (($curlError || $httpCode >= 500) && $tentativa < $this->retryTentativas) {
            sleep($this->retryIntervalo);
            return $this->request($method, $endpoint, $data, $tentativa + 1);
        }

        // Retorna erro se falhou
        if ($curlError) {
            return json_encode([
                'error' => true,
                'message' => 'Erro cURL: ' . $curlError,
                'http_code' => $httpCode
            ]);
        }

        return $response;
    }

    /**
     * Criar instância
     */
    public function cria_instancia()
    {
        $data = [
            "key" => $this->token,
            "browser" => "Ubuntu",
            "webhook" => true,
            "base64" => true,
            "webhookUrl" => $this->webhookUrl,
            "webhookEvents" => ["messages.upsert", "messages.update"],
            "ignoreGroups" => false,
            "messagesRead" => false
        ];

        return $this->request('POST', "/instance/init?admintoken={$this->secureToken}", $data);
    }

    /**
     * Deletar instância
     */
    public function deletar_instancia()
    {
        return $this->request('GET', "/instance/delete?key=" . urlencode($this->token));
    }

    /**
     * Logout instância
     */
    public function logout_instancia()
    {
        return $this->request('GET', "/instance/logout?key=" . urlencode($this->token));
    }

    /**
     * Status da instância (QR Code)
     */
    public function status_instancia()
    {
        return $this->request('GET', "/instance/qrbase64?key=" . urlencode($this->token));
    }

    /**
     * Informações da instância
     */
    public function info_instancia()
    {
        return $this->request('GET', "/instance/info?key=" . urlencode($this->token));
    }

    /**
     * Enviar texto
     */
    public function sendText($dados, $group = false)
    {
        // Validações
        if ($this->config) {
            $validacao = $this->validarEnvio('text', $dados);
            if (!$validacao['valido']) {
                return json_encode(['error' => true, 'message' => $validacao['erro']]);
            }
        }

        $data = [
            "id" => $group ? $dados['celular'] : Utils::formatarNumeroTelefone($dados['celular']),
            "typeId" => $group ? "group" : "user",
            "message" => $dados['mensagem'],
            "options" => [
                "delay" => $dados['delay'] ?? 0,
                "replyFrom" => $dados['replyFrom'] ?? ""
            ]
        ];

        return $this->request('POST', "/message/text?key=" . urlencode($this->token), $data);
    }

    /**
     * Enviar arquivo (imagem, PDF, etc)
     */
    public function sendFile($dados, $group = false)
    {
        // Determina tipo
        $tipo = $dados['tipo'] ?? 'document';

        // Validações
        if ($this->config) {
            $validacao = $this->validarEnvio($tipo, $dados);
            if (!$validacao['valido']) {
                return json_encode(['error' => true, 'message' => $validacao['erro']]);
            }
        }

        $data = [
            "id" => $group ? $dados['celular'] : Utils::formatarNumeroTelefone($dados['celular']),
            "typeId" => $group ? "group" : "user",
            "type" => $tipo,
            "options" => [
                "caption" => $dados['caption'] ?? "",
                "delay" => $dados['delay'] ?? 0,
                "replyFrom" => $dados['replyFrom'] ?? ""
            ]
        ];

        // URL ou Base64
        if (!empty($dados['url'])) {
            $data['url'] = $dados['url'];
            $endpoint = "/message/sendurlfile?key=" . urlencode($this->token);
        } elseif (!empty($dados['base64'])) {
            $data['base64'] = $dados['base64'];
            $data['fileName'] = $dados['nome'] ?? 'arquivo';
            $endpoint = "/message/sendfile?key=" . urlencode($this->token);
        } else {
            return json_encode(['error' => true, 'message' => 'URL ou base64 é obrigatório']);
        }

        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Enviar imagem
     */
    public function sendImage($dados, $group = false)
    {
        $dados['tipo'] = 'image';
        return $this->sendFile($dados, $group);
    }

    /**
     * Enviar PDF
     */
    public function sendPDF($dados, $group = false)
    {
        $dados['tipo'] = 'document';
        return $this->sendFile($dados, $group);
    }

    /**
     * Enviar áudio
     */
    public function sendAudio($dados, $group = false)
    {
        $dados['tipo'] = 'audio';
        return $this->sendFile($dados, $group);
    }

    /**
     * Enviar vídeo
     */
    public function sendVideo($dados, $group = false)
    {
        $dados['tipo'] = 'video';
        return $this->sendFile($dados, $group);
    }

    /**
     * Validar envio
     */
    private function validarEnvio($tipo, $dados)
    {
        if (!$this->config) {
            return ['valido' => true];
        }

        // Verifica se tipo está habilitado
        $tipoHabilitado = $this->config->obter("tipo_{$tipo}_habilitado", true);
        if (!$tipoHabilitado) {
            return ['valido' => false, 'erro' => "Envio de tipo '{$tipo}' está desabilitado"];
        }

        // Validação de texto
        if ($tipo === 'text') {
            $validarTamanho = $this->config->obter('validar_tamanho_texto', true);
            if ($validarTamanho) {
                $maxTamanho = $this->config->obter('validar_tamanho_texto_max', 4096);
                if (strlen($dados['mensagem']) > $maxTamanho) {
                    return ['valido' => false, 'erro' => "Mensagem excede {$maxTamanho} caracteres"];
                }
            }
        }

        // Validação de arquivo
        if (in_array($tipo, ['image', 'pdf', 'audio', 'video', 'document'])) {
            $validarArquivo = $this->config->obter('validar_arquivo_tamanho', true);
            if ($validarArquivo && !empty($dados['base64'])) {
                $tamanhoBase64 = strlen($dados['base64']);
                $maxTamanho = $this->config->obter('validar_arquivo_tamanho_max', 16777216); // 16MB

                if ($tamanhoBase64 > $maxTamanho) {
                    return ['valido' => false, 'erro' => "Arquivo excede tamanho máximo permitido"];
                }
            }
        }

        return ['valido' => true];
    }

    /**
     * Enviar botão (placeholder)
     */
    public function sendButton($dados, $group = false)
    {
        return json_encode(['error' => true, 'message' => 'Botões não implementados']);
    }

    /**
     * Enviar localização (placeholder)
     */
    public function sendLocation($dados, $group = false)
    {
        return json_encode(['error' => true, 'message' => 'Localização não implementada']);
    }

    // Getters/Setters para testes
    public function setToken($token)
    {
        $this->token = $token;
    }

    public function getToken()
    {
        return $this->token;
    }
}
