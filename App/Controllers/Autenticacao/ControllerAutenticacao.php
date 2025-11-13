<?php

namespace App\Controllers\Autenticacao;

use App\Controllers\BaseController;

use App\Core\Autenticacao;
use App\Core\GerenciadorUsuario;
use App\Core\TokenCsrf;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controlador para autenticação
 */
class ControllerAutenticacao extends BaseController
{
    private Autenticacao $auth;
    private GerenciadorUsuario $gerenciadorUsuario;
    private TokenCsrf $csrf;

    public function __construct()
    {
        $this->auth = new Autenticacao();
        $this->gerenciadorUsuario = new GerenciadorUsuario();
        $this->csrf = new TokenCsrf();
    }

    /**
     * Realiza login
     */
    public function login(): void
    {
        $dados = $this->obterDados();

        // Validação
        $erros = AuxiliarValidacao::validar($dados, [
            'email' => 'obrigatorio|email',
            'senha' => 'obrigatorio|min:6'
        ]);

        if (!empty($erros)) {
            $this->validacao($erros);
            return;
        }

        try {
            $resultado = $this->auth->login($dados['email'], $dados['senha']);

            // Configura o JWT como cookie httpOnly
            if (isset($resultado['access_token'])) {
                $this->configurarCookieJwt($resultado['access_token'], $resultado['expires_in'] ?? 3600);
            }

            $this->sucesso($resultado, 'Login realizado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 401);
        }
    }

    /**
     * Realiza logout
     */
    public function logout(): void
    {
        try {
            $token = \App\Core\JWT::extrair();
            $this->auth->logout($token);

            // Limpa o cookie JWT
            $this->limparCookieJwt();

            $this->sucesso(null, 'Logout realizado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 500);
        }
    }

    /**
     * Renova o token de acesso
     */
    public function refresh(): void
    {
        $dados = $this->obterDados();

        if (empty($dados['refresh_token'])) {
            $this->erro('Refresh token é obrigatório', 400);
            return;
        }

        try {
            $resultado = $this->auth->renovarToken($dados['refresh_token']);
            $this->sucesso($resultado, 'Token renovado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 401);
        }
    }

    /**
     * Obtém o perfil do usuário autenticado (endpoint /me)
     */
    public function me(): void
    {
        try {
            $usuario = $this->obterUsuarioAutenticado();

            if (!$usuario) {
                $this->naoAutorizado();
                return;
            }

            // Remove a senha dos dados retornados
            $usuario = $this->removerCamposSensiveis($usuario);

            $this->sucesso($usuario, 'Usuário autenticado');
        } catch (\Exception $e) {
            $this->tratarErro($e, 500);
        }
    }

    /**
     * Altera a senha do usuário autenticado
     */
    public function alterarSenha(): void
    {
        $dados = $this->obterDados();

        // Validação
        $erros = AuxiliarValidacao::validar($dados, [
            'senha_atual' => 'obrigatorio',
            'nova_senha' => 'obrigatorio|min:8',
            'confirmar_senha' => 'obrigatorio'
        ]);

        if (!empty($erros)) {
            $this->validacao($erros);
            return;
        }

        // Verifica se as senhas coincidem
        if ($dados['nova_senha'] !== $dados['confirmar_senha']) {
            $this->erro('As senhas não coincidem', 400);
            return;
        }

        try {
            $usuario = $this->auth->obterUsuarioAutenticado();

            if (!$usuario) {
                $this->naoAutorizado();
                return;
            }

            $this->gerenciadorUsuario->alterarSenha(
                $usuario['id'],
                $dados['senha_atual'],
                $dados['nova_senha']
            );

            $this->sucesso(null, 'Senha alterada com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém token CSRF
     */
    public function obterTokenCsrf(): void
    {
        try {
            $token = $this->csrf->obter();
            $this->sucesso(['csrf_token' => $token], 'Token CSRF gerado');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 500);
        }
    }

    /**
     * Configura o cookie JWT com httpOnly e Secure
     */
    private function configurarCookieJwt(string $token, int $expiresIn): void
    {
        $cookieName = 'auth_token';
        $expirationTime = time() + $expiresIn;

        // Configurações de segurança do cookie
        $options = [
            'expires' => $expirationTime,
            'path' => '/',
            'domain' => '', // Deixe vazio para o domínio atual
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', // true se HTTPS
            'httponly' => true, // Impede acesso via JavaScript
            'samesite' => 'Lax' // Proteção contra CSRF
        ];

        setcookie($cookieName, $token, $options);
    }

    /**
     * Limpa o cookie JWT
     */
    private function limparCookieJwt(): void
    {
        $cookieName = 'auth_token';

        // Define o cookie com tempo de expiração no passado
        $options = [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ];

        setcookie($cookieName, '', $options);
    }
}
