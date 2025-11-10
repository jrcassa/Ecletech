<?php

namespace App\Core;

/**
 * Classe para gerenciamento de tokens CSRF
 */
class TokenCsrf
{
    private Configuracao $config;
    private int $expiracao;

    public function __construct()
    {
        $this->config = Configuracao::obterInstancia();
        $this->expiracao = $this->config->obter('seguranca.csrf_expiracao', 3600);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Gera um novo token CSRF
     */
    public function gerar(): string
    {
        $token = bin2hex(random_bytes(32));

        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();

        return $token;
    }

    /**
     * Valida um token CSRF
     */
    public function validar(string $token): bool
    {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }

        // Verifica se o token expirou
        if (time() - $_SESSION['csrf_token_time'] > $this->expiracao) {
            $this->limpar();
            return false;
        }

        // Verifica se o token corresponde
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Obtém o token atual ou gera um novo
     */
    public function obter(): string
    {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return $this->gerar();
        }

        // Se o token expirou, gera um novo
        if (time() - $_SESSION['csrf_token_time'] > $this->expiracao) {
            return $this->gerar();
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Limpa o token CSRF
     */
    public function limpar(): void
    {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
    }

    /**
     * Extrai o token do cabeçalho da requisição
     */
    public static function extrairDaRequisicao(): ?string
    {
        // Tenta obter do cabeçalho X-CSRF-Token
        $headers = getallheaders();
        if (isset($headers['X-CSRF-Token'])) {
            return $headers['X-CSRF-Token'];
        }
        if (isset($headers['x-csrf-token'])) {
            return $headers['x-csrf-token'];
        }

        // Tenta obter do corpo da requisição
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['csrf_token'])) {
            return $input['csrf_token'];
        }

        // Tenta obter do POST
        if (isset($_POST['csrf_token'])) {
            return $_POST['csrf_token'];
        }

        return null;
    }

    /**
     * Verifica se o CSRF está habilitado
     */
    public function estaHabilitado(): bool
    {
        return $this->config->obter('seguranca.csrf_habilitado', true);
    }

    /**
     * Gera um campo hidden HTML com o token CSRF
     */
    public function gerarCampoHtml(): string
    {
        $token = $this->obter();
        return sprintf('<input type="hidden" name="csrf_token" value="%s">', htmlspecialchars($token));
    }

    /**
     * Gera meta tag HTML com o token CSRF
     */
    public function gerarMetaTag(): string
    {
        $token = $this->obter();
        return sprintf('<meta name="csrf-token" content="%s">', htmlspecialchars($token));
    }
}
