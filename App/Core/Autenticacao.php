<?php

namespace App\Core;

use App\Models\Login\ModelLoginAttempt;
use App\Helpers\AuxiliarRede;

/**
 * Classe para gerenciar autenticação de usuários
 */
class Autenticacao
{
    private JWT $jwt;
    private BancoDados $db;
    private Configuracao $config;
    private LimitadorRequisicao $limitador;
    private ModelLoginAttempt $loginAttempt;

    public function __construct()
    {
        $this->jwt = new JWT();
        $this->db = BancoDados::obterInstancia();
        $this->config = Configuracao::obterInstancia();
        $this->limitador = new LimitadorRequisicao();
        $this->loginAttempt = new ModelLoginAttempt();
    }

    /**
     * Realiza login do usuário
     */
    public function login(string $email, string $senha): ?array
    {
        // Obtém IP do cliente
        $ipAddress = AuxiliarRede::obterIp();
        $userAgent = AuxiliarRede::obterUserAgent();

        // Verifica se o IP está bloqueado
        if ($this->loginAttempt->ipEstaBloqueado($ipAddress)) {
            $bloqueio = $this->loginAttempt->obterBloqueioIp($ipAddress);
            $mensagem = "Seu IP está bloqueado";
            if ($bloqueio && isset($bloqueio['bloqueado_ate'])) {
                $mensagem .= " até " . date('d/m/Y H:i:s', strtotime($bloqueio['bloqueado_ate']));
            }
            throw new \RuntimeException($mensagem);
        }

        // Verifica se o email está bloqueado
        if ($this->loginAttempt->emailEstaBloqueado($email)) {
            $bloqueio = $this->loginAttempt->obterBloqueioEmail($email);
            $mensagem = "Esta conta está bloqueada";
            if ($bloqueio && isset($bloqueio['bloqueado_ate'])) {
                $mensagem .= " até " . date('d/m/Y H:i:s', strtotime($bloqueio['bloqueado_ate']));
            }
            throw new \RuntimeException($mensagem);
        }

        // Verifica rate limiting global
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
            $this->registrarTentativaFalha($email, $ipAddress, $userAgent, 'usuario_nao_encontrado');
            throw new \RuntimeException("Credenciais inválidas");
        }

        // Verifica a senha
        if (!password_verify($senha, $usuario['senha'])) {
            $this->registrarTentativaFalha($email, $ipAddress, $userAgent, 'senha_invalida');
            throw new \RuntimeException("Credenciais inválidas");
        }

        // Login bem-sucedido - registra tentativa
        $this->loginAttempt->registrarTentativa($email, $ipAddress, true, null, $userAgent);

        // Registra auditoria de login
        $auditoria = new RegistroAuditoria();
        $auditoria->registrarLogin($usuario['id'], true);

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
            'colaborador_id' => $usuario['id'],
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
            if ($payload && isset($payload['colaborador_id'])) {
                // Registra auditoria de logout
                $auditoria = new RegistroAuditoria();
                $auditoria->registrarLogout($payload['colaborador_id']);

                $this->invalidarRefreshTokens($payload['colaborador_id']);
            }
        }

        return true;
    }

    /**
     * Renova o token de acesso usando o refresh token
     */
    public function renovarToken(string $refreshToken): ?array
    {
        $payload = $this->jwt->validar($refreshToken);

        if (!$payload || !isset($payload['colaborador_id'])) {
            throw new \RuntimeException("Refresh token inválido");
        }

        // Verifica se o refresh token está armazenado
        $tokenArmazenado = $this->db->buscarUm(
            "SELECT * FROM colaborador_tokens WHERE colaborador_id = ? AND token = ? AND tipo = 'refresh' AND revogado = 0 AND expira_em > NOW()",
            [$payload['colaborador_id'], $refreshToken]
        );

        if (!$tokenArmazenado) {
            throw new \RuntimeException("Refresh token inválido ou expirado");
        }

        // Busca o usuário
        $usuario = $this->db->buscarUm(
            "SELECT * FROM colaboradores WHERE id = ? AND ativo = 1",
            [$payload['colaborador_id']]
        );

        if (!$usuario) {
            throw new \RuntimeException("Usuário não encontrado");
        }

        // Gera novo access token
        $novoPayload = [
            'colaborador_id' => $usuario['id'],
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
        // Extrai do cookie ou cabeçalho (cookie tem prioridade)
        $token = $token ?? JWT::extrair();

        if (!$token) {
            return null;
        }

        $payload = $this->jwt->validar($token);

        if (!$payload || !isset($payload['colaborador_id'])) {
            return null;
        }

        // Busca o usuário
        $usuario = $this->db->buscarUm(
            "SELECT id, nome, email, nivel_id, ativo FROM colaboradores WHERE id = ? AND ativo = 1",
            [$payload['colaborador_id']]
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
     * Registra tentativa de login falha e gerencia bloqueios
     */
    private function registrarTentativaFalha(
        string $email,
        string $ipAddress,
        ?string $userAgent,
        string $motivoFalha
    ): void {
        // Registra a tentativa falhada no banco
        $this->loginAttempt->registrarTentativa($email, $ipAddress, false, $motivoFalha, $userAgent);

        // Conta tentativas falhadas recentes por email
        $tentativasEmail = $this->loginAttempt->contarTentativasFalhadasPorEmail($email);

        // Conta tentativas falhadas recentes por IP
        $tentativasIp = $this->loginAttempt->contarTentativasFalhadasPorIp($ipAddress);

        $maxTentativas = $this->config->obter('BRUTE_FORCE_MAX_TENTATIVAS', 5);

        // Se excedeu tentativas por email, bloqueia o email
        if ($tentativasEmail >= $maxTentativas) {
            $this->loginAttempt->criarBloqueio(
                'email',
                $email,
                null,
                $tentativasEmail,
                false,
                "Bloqueio automático: {$tentativasEmail} tentativas falhadas"
            );
        }

        // Se excedeu tentativas por IP, bloqueia o IP
        if ($tentativasIp >= $maxTentativas) {
            $this->loginAttempt->criarBloqueio(
                'ip',
                null,
                $ipAddress,
                $tentativasIp,
                false,
                "Bloqueio automático: {$tentativasIp} tentativas falhadas do IP"
            );
        }
    }

    /**
     * Salva o refresh token no banco
     */
    private function salvarRefreshToken(int $colaboradorId, string $token): void
    {
        $expiracao = $this->config->obter('jwt.refresh_expiracao', 86400);

        $this->db->inserir('colaborador_tokens', [
            'colaborador_id' => $colaboradorId,
            'token' => $token,
            'tipo' => 'refresh',
            'expira_em' => date('Y-m-d H:i:s', time() + $expiracao),
            'criado_em' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Invalida todos os refresh tokens de um colaborador
     */
    private function invalidarRefreshTokens(int $colaboradorId): void
    {
        $this->db->atualizar(
            'colaborador_tokens',
            ['revogado' => 1],
            'colaborador_id = ? AND tipo = ?',
            [$colaboradorId, 'refresh']
        );
    }
}
