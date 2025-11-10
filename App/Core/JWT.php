<?php

namespace App\Core;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

/**
 * Classe para gerenciamento de JSON Web Tokens (JWT)
 * Utiliza a biblioteca firebase/php-jwt para segurança e confiabilidade
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
     *
     * @param array $payload Dados a serem incluídos no token
     * @param int|null $expiracao Tempo de expiração em segundos (opcional)
     * @return string Token JWT gerado
     */
    public function gerar(array $payload, ?int $expiracao = null): string
    {
        $expiracao = $expiracao ?? $this->expiracao;

        $agora = time();
        $payloadCompleto = array_merge($payload, [
            'iss' => $this->emissor,
            'iat' => $agora,
            'exp' => $agora + $expiracao,
            'nbf' => $agora
        ]);

        return FirebaseJWT::encode($payloadCompleto, $this->chaveSecreta, $this->algoritmo);
    }

    /**
     * Valida e decodifica um token JWT
     *
     * @param string $token Token JWT a ser validado
     * @return array|null Payload decodificado ou null se inválido
     */
    public function validar(string $token): ?array
    {
        try {
            // Define o emissor esperado para validação
            FirebaseJWT::$leeway = 0;

            $decoded = FirebaseJWT::decode(
                $token,
                new Key($this->chaveSecreta, $this->algoritmo)
            );

            // Converte o objeto stdClass para array
            $payload = (array) $decoded;

            // Verifica o emissor
            if (isset($payload['iss']) && $payload['iss'] !== $this->emissor) {
                return null;
            }

            return $payload;

        } catch (ExpiredException $e) {
            // Token expirado
            return null;
        } catch (SignatureInvalidException $e) {
            // Assinatura inválida
            return null;
        } catch (BeforeValidException $e) {
            // Token ainda não é válido (nbf)
            return null;
        } catch (\Exception $e) {
            // Qualquer outro erro
            return null;
        }
    }

    /**
     * Gera um refresh token com tempo de expiração maior
     *
     * @param array $payload Dados a serem incluídos no token
     * @return string Refresh token JWT gerado
     */
    public function gerarRefreshToken(array $payload): string
    {
        $expiracao = $this->config->obter('jwt.refresh_expiracao', 86400);
        return $this->gerar($payload, $expiracao);
    }

    /**
     * Decodifica um token sem validar a assinatura ou expiração
     * ATENÇÃO: Use este método apenas quando a validação não for necessária
     *
     * @param string $token Token JWT a ser decodificado
     * @return array|null Payload decodificado ou null se inválido
     */
    public function decodificar(string $token): ?array
    {
        try {
            $partes = explode('.', $token);

            if (count($partes) !== 3) {
                return null;
            }

            // Decodifica apenas o payload (segunda parte) sem validar
            $payloadCodificado = $partes[1];
            $payload = json_decode($this->base64UrlDecode($payloadCodificado), true);

            return $payload ?: null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Decodifica Base64 URL-safe
     *
     * @param string $dados Dados codificados
     * @return string Dados decodificados
     */
    private function base64UrlDecode(string $dados): string
    {
        $remainder = strlen($dados) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $dados .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($dados, '-_', '+/'));
    }

    /**
     * Extrai o token do cabeçalho Authorization
     *
     * @return string|null Token extraído ou null se não encontrado
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
     *
     * @param string $token Token JWT
     * @return int|null Timestamp de expiração ou null
     */
    public function obterExpiracao(string $token): ?int
    {
        $payload = $this->decodificar($token);
        return $payload['exp'] ?? null;
    }

    /**
     * Verifica se o token está expirado
     *
     * @param string $token Token JWT
     * @return bool True se expirado, false caso contrário
     */
    public function estaExpirado(string $token): bool
    {
        $expiracao = $this->obterExpiracao($token);
        return $expiracao !== null && $expiracao < time();
    }

    /**
     * Obtém o ID do usuário do token (com validação)
     *
     * @param string $token Token JWT
     * @return int|null ID do usuário ou null se inválido
     */
    public function obterIdUsuario(string $token): ?int
    {
        $payload = $this->validar($token);
        return $payload['usuario_id'] ?? null;
    }
}
