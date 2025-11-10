<?php

namespace App\Core;

/**
 * Classe para gerenciar autenticação de usuários
 */
class Autenticacao
{
    private JWT $jwt;
    private BancoDados $db;
    private Configuracao $config;
    private LimitadorRequisicao $limitador;

    public function __construct()
    {
        $this->jwt = new JWT();
        $this->db = BancoDados::obterInstancia();
        $this->config = Configuracao::obterInstancia();
        $this->limitador = new LimitadorRequisicao();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Realiza login do usuário
     */
    public function login(string $email, string $senha): ?array
    {
        // Verifica rate limiting
        $identificador = LimitadorRequisicao::obterIdentificador();
        if ($this->limitador->estaHabilitado() && !$this->limitador->verificar("login:{$identificador}")) {
            throw new \RuntimeException("Muitas tentativas de login. Tente novamente mais tarde.");
        }

        // Busca o usuário
        $usuario = $this->db->buscarUm(
            "SELECT * FROM colaboradores WHERE email = ? AND ativo = 1",
            [$email]
        );

        if (!$usuario) {
            $this->registrarTentativaFalha($email);
            throw new \RuntimeException("Credenciais inválidas");
        }

        // Verifica se a conta está bloqueada
        if ($this->contaBloqueada($usuario['id'])) {
            throw new \RuntimeException("Conta bloqueada temporariamente");
        }

        // Verifica a senha
        if (!password_verify($senha, $usuario['senha'])) {
            $this->registrarTentativaFalha($email, $usuario['id']);
            throw new \RuntimeException("Credenciais inválidas");
        }

        // Limpa tentativas de login
        $this->limparTentativasFalhas($usuario['id']);

        // Registra a requisição de login
        if ($this->limitador->estaHabilitado()) {
            $this->limitador->registrar("login:{$identificador}");
        }

        // Atualiza último login
        $this->db->atualizar(
            'colaboradores',
            ['ultimo_login' => date('Y-m-d H:i:s')],
            'id = ?',
            [$usuario['id']]
        );

        // Gera tokens
        $payload = [
            'usuario_id' => $usuario['id'],
            'email' => $usuario['email'],
            'nivel_id' => $usuario['nivel_id']
        ];

        $accessToken = $this->jwt->gerar($payload);
        $refreshToken = $this->jwt->gerarRefreshToken($payload);

        // Armazena o refresh token
        $this->salvarRefreshToken($usuario['id'], $refreshToken);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->config->obter('jwt.expiracao', 3600),
            'usuario' => [
                'id' => $usuario['id'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
                'nivel_id' => $usuario['nivel_id']
            ]
        ];
    }

    /**
     * Realiza logout do usuário
     */
    public function logout(?string $token = null): bool
    {
        if ($token) {
            $payload = $this->jwt->validar($token);
            if ($payload && isset($payload['usuario_id'])) {
                $this->invalidarRefreshTokens($payload['usuario_id']);
            }
        }

        // Limpa a sessão
        session_destroy();

        return true;
    }

    /**
     * Renova o token de acesso usando o refresh token
     */
    public function renovarToken(string $refreshToken): ?array
    {
        $payload = $this->jwt->validar($refreshToken);

        if (!$payload || !isset($payload['usuario_id'])) {
            throw new \RuntimeException("Refresh token inválido");
        }

        // Verifica se o refresh token está armazenado
        $tokenArmazenado = $this->db->buscarUm(
            "SELECT * FROM colaborador_tokens WHERE usuario_id = ? AND token = ? AND tipo = 'refresh' AND revogado = 0 AND expira_em > NOW()",
            [$payload['usuario_id'], $refreshToken]
        );

        if (!$tokenArmazenado) {
            throw new \RuntimeException("Refresh token inválido ou expirado");
        }

        // Busca o usuário
        $usuario = $this->db->buscarUm(
            "SELECT * FROM colaboradores WHERE id = ? AND ativo = 1",
            [$payload['usuario_id']]
        );

        if (!$usuario) {
            throw new \RuntimeException("Usuário não encontrado");
        }

        // Gera novo access token
        $novoPayload = [
            'usuario_id' => $usuario['id'],
            'email' => $usuario['email'],
            'nivel_id' => $usuario['nivel_id']
        ];

        $accessToken = $this->jwt->gerar($novoPayload);

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->config->obter('jwt.expiracao', 3600)
        ];
    }

    /**
     * Valida o token e retorna o usuário autenticado
     */
    public function validarToken(?string $token = null): ?array
    {
        $token = $token ?? JWT::extrairDoCabecalho();

        if (!$token) {
            return null;
        }

        $payload = $this->jwt->validar($token);

        if (!$payload || !isset($payload['usuario_id'])) {
            return null;
        }

        // Busca o usuário
        $usuario = $this->db->buscarUm(
            "SELECT id, nome, email, nivel_id, ativo FROM colaboradores WHERE id = ? AND ativo = 1",
            [$payload['usuario_id']]
        );

        return $usuario ?: null;
    }

    /**
     * Verifica se o usuário está autenticado
     */
    public function estaAutenticado(): bool
    {
        return $this->validarToken() !== null;
    }

    /**
     * Obtém o usuário autenticado
     */
    public function obterUsuarioAutenticado(): ?array
    {
        return $this->validarToken();
    }

    /**
     * Registra tentativa de login falha
     */
    private function registrarTentativaFalha(string $email, ?int $usuarioId = null): void
    {
        if (!isset($_SESSION['tentativas_login'])) {
            $_SESSION['tentativas_login'] = [];
        }

        $_SESSION['tentativas_login'][] = [
            'email' => $email,
            'usuario_id' => $usuarioId,
            'timestamp' => time(),
            'ip' => LimitadorRequisicao::obterIdentificador()
        ];
    }

    /**
     * Limpa tentativas de login falhas
     */
    private function limparTentativasFalhas(int $usuarioId): void
    {
        if (isset($_SESSION['tentativas_login'])) {
            $_SESSION['tentativas_login'] = array_filter(
                $_SESSION['tentativas_login'],
                fn($t) => $t['usuario_id'] !== $usuarioId
            );
        }
    }

    /**
     * Verifica se a conta está bloqueada
     */
    private function contaBloqueada(int $usuarioId): bool
    {
        if (!isset($_SESSION['tentativas_login'])) {
            return false;
        }

        $maxTentativas = $this->config->obter('seguranca.tentativas_login_max', 5);
        $tempoBloqueio = $this->config->obter('seguranca.bloqueio_tempo', 900);
        $agora = time();

        $tentativas = array_filter(
            $_SESSION['tentativas_login'],
            fn($t) => $t['usuario_id'] === $usuarioId && ($agora - $t['timestamp']) < $tempoBloqueio
        );

        return count($tentativas) >= $maxTentativas;
    }

    /**
     * Salva o refresh token no banco
     */
    private function salvarRefreshToken(int $usuarioId, string $token): void
    {
        $expiracao = $this->config->obter('jwt.refresh_expiracao', 86400);

        $this->db->inserir('colaborador_tokens', [
            'usuario_id' => $usuarioId,
            'token' => $token,
            'tipo' => 'refresh',
            'expira_em' => date('Y-m-d H:i:s', time() + $expiracao),
            'criado_em' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Invalida todos os refresh tokens de um usuário
     */
    private function invalidarRefreshTokens(int $usuarioId): void
    {
        $this->db->atualizar(
            'colaborador_tokens',
            ['revogado' => 1],
            'usuario_id = ? AND tipo = ?',
            [$usuarioId, 'refresh']
        );
    }
}
