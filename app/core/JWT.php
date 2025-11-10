<?php

namespace App\Core;

/**
 * Classe para gerenciamento de JSON Web Tokens (JWT)
 */
class JWT
{
    private Configuracao $config;
    private string $chaveSecreta;
    private string $algoritmo;
    private int $expiracao;
    private string $emissor;

    public function __construct()
    {
        $this->config = Configuracao::obterInstancia();
        $this->chaveSecreta = $this->config->obter('jwt.chave_secreta');
        $this->algoritmo = $this->config->obter('jwt.algoritmo', 'HS256');
        $this->expiracao = $this->config->obter('jwt.expiracao', 3600);
        $this->emissor = $this->config->obter('jwt.emissor', 'Ecletech');

        if (empty($this->chaveSecreta)) {
            throw new \RuntimeException("Chave secreta JWT não configurada");
        }
    }

    /**
     * Gera um token JWT
     */
    public function gerar(array $payload, ?int $expiracao = null): string
    {
        $expiracao = $expiracao ?? $this->expiracao;

        $header = [
            'typ' => 'JWT',
            'alg' => $this->algoritmo
        ];

        $agora = time();
        $payloadCompleto = array_merge($payload, [
            'iss' => $this->emissor,
            'iat' => $agora,
            'exp' => $agora + $expiracao,
            'nbf' => $agora
        ]);

        $headerCodificado = $this->base64UrlEncode(json_encode($header));
        $payloadCodificado = $this->base64UrlEncode(json_encode($payloadCompleto));

        $assinatura = $this->assinar($headerCodificado . '.' . $payloadCodificado);

        return $headerCodificado . '.' . $payloadCodificado . '.' . $assinatura;
    }

    /**
     * Valida e decodifica um token JWT
     */
    public function validar(string $token): ?array
    {
        $partes = explode('.', $token);

        if (count($partes) !== 3) {
            return null;
        }

        [$headerCodificado, $payloadCodificado, $assinatura] = $partes;

        // Verifica a assinatura
        $assinaturaValida = $this->verificarAssinatura(
            $headerCodificado . '.' . $payloadCodificado,
            $assinatura
        );

        if (!$assinaturaValida) {
            return null;
        }

        // Decodifica o payload
        $payload = json_decode($this->base64UrlDecode($payloadCodificado), true);

        if (!$payload) {
            return null;
        }

        // Verifica expiração
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        // Verifica not before
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            return null;
        }

        // Verifica emissor
        if (isset($payload['iss']) && $payload['iss'] !== $this->emissor) {
            return null;
        }

        return $payload;
    }

    /**
     * Gera um refresh token
     */
    public function gerarRefreshToken(array $payload): string
    {
        $expiracao = $this->config->obter('jwt.refresh_expiracao', 86400);
        return $this->gerar($payload, $expiracao);
    }

    /**
     * Decodifica um token sem validar
     */
    public function decodificar(string $token): ?array
    {
        $partes = explode('.', $token);

        if (count($partes) !== 3) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($partes[1]), true);
        return $payload ?: null;
    }

    /**
     * Assina os dados
     */
    private function assinar(string $dados): string
    {
        $assinatura = hash_hmac('sha256', $dados, $this->chaveSecreta, true);
        return $this->base64UrlEncode($assinatura);
    }

    /**
     * Verifica a assinatura
     */
    private function verificarAssinatura(string $dados, string $assinatura): bool
    {
        $assinaturaEsperada = $this->assinar($dados);
        return hash_equals($assinaturaEsperada, $assinatura);
    }

    /**
     * Codifica em Base64 URL-safe
     */
    private function base64UrlEncode(string $dados): string
    {
        return rtrim(strtr(base64_encode($dados), '+/', '-_'), '=');
    }

    /**
     * Decodifica Base64 URL-safe
     */
    private function base64UrlDecode(string $dados): string
    {
        return base64_decode(strtr($dados, '-_', '+/'));
    }

    /**
     * Extrai o token do cabeçalho Authorization
     */
    public static function extrairDoCabecalho(): ?string
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (empty($authHeader)) {
            return null;
        }

        // Formato esperado: "Bearer {token}"
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Obtém o tempo de expiração do token
     */
    public function obterExpiracao(string $token): ?int
    {
        $payload = $this->decodificar($token);
        return $payload['exp'] ?? null;
    }

    /**
     * Verifica se o token está expirado
     */
    public function estaExpirado(string $token): bool
    {
        $expiracao = $this->obterExpiracao($token);
        return $expiracao !== null && $expiracao < time();
    }

    /**
     * Obtém o ID do usuário do token
     */
    public function obterIdUsuario(string $token): ?int
    {
        $payload = $this->validar($token);
        return $payload['usuario_id'] ?? null;
    }
}
