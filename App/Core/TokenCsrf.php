<?php

namespace App\Core;

use App\Models\Csrf\ModelCsrfToken;

/**
 * Classe para gerenciamento de tokens CSRF
 * Armazena tokens no banco de dados para maior segurança e persistência
 */
class TokenCsrf
{
    private Configuracao $config;
    private int $expiracao;
    private ?ModelCsrfToken $model = null;
    private bool $usarBancoDados = true;

    public function __construct()
    {
        $this->config = Configuracao::obterInstancia();
        $this->expiracao = $this->config->obter('seguranca.csrf_expiracao', 3600);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Tenta usar o banco de dados, mas usa sessão como fallback se houver erro
        try {
            $this->model = new ModelCsrfToken();
        } catch (\Exception $e) {
            $this->usarBancoDados = false;
            error_log("TokenCsrf: Não foi possível conectar ao banco de dados. Usando sessão como fallback. Erro: " . $e->getMessage());
        }
    }

    /**
     * Gera um novo token CSRF
     */
    public function gerar(): string
    {
        $token = bin2hex(random_bytes(32));

        // Salva na sessão (sempre, como fallback)
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();

        // Se o banco de dados estiver disponível, salva também lá
        if ($this->usarBancoDados && $this->model) {
            try {
                $this->model->criar([
                    'token' => $token,
                    'session_id' => session_id(),
                    'usuario_id' => $_SESSION['usuario_id'] ?? null,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'expira_em' => date('Y-m-d H:i:s', time() + $this->expiracao)
                ]);
            } catch (\Exception $e) {
                error_log("TokenCsrf: Erro ao salvar token no banco de dados: " . $e->getMessage());
                // Continua usando sessão como fallback
            }
        }

        return $token;
    }

    /**
     * Valida um token CSRF
     */
    public function validar(string $token): bool
    {
        $valido = false;

        // Tenta validar no banco de dados primeiro
        if ($this->usarBancoDados && $this->model) {
            try {
                $valido = $this->model->validar($token, session_id());

                // Se for válido, marca como usado (one-time token)
                if ($valido) {
                    $this->model->marcarComoUsado($token);
                    // Limpa da sessão também
                    $this->limpar();
                    return true;
                }
            } catch (\Exception $e) {
                error_log("TokenCsrf: Erro ao validar token no banco de dados: " . $e->getMessage());
                // Continua para validação via sessão
            }
        }

        // Fallback: valida via sessão
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

    /**
     * Limpa tokens expirados do banco de dados
     * Deve ser chamado periodicamente (ex: via cron job)
     */
    public function limparTokensExpirados(): int
    {
        if (!$this->usarBancoDados || !$this->model) {
            return 0;
        }

        try {
            return $this->model->limparExpirados();
        } catch (\Exception $e) {
            error_log("TokenCsrf: Erro ao limpar tokens expirados: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Limpa tokens usados antigos do banco de dados
     * @param int $diasAtras Quantidade de dias para considerar tokens antigos (padrão: 1)
     */
    public function limparTokensUsados(int $diasAtras = 1): int
    {
        if (!$this->usarBancoDados || !$this->model) {
            return 0;
        }

        try {
            return $this->model->limparUsados($diasAtras);
        } catch (\Exception $e) {
            error_log("TokenCsrf: Erro ao limpar tokens usados: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtém estatísticas sobre tokens CSRF
     */
    public function obterEstatisticas(): array
    {
        if (!$this->usarBancoDados || !$this->model) {
            return [
                'usando_banco_dados' => false,
                'tokens_ativos' => isset($_SESSION['csrf_token']) ? 1 : 0
            ];
        }

        try {
            return [
                'usando_banco_dados' => true,
                'tokens_ativos' => $this->model->contarAtivos(),
                'tokens_sessao_atual' => $this->model->contarPorSessao(session_id())
            ];
        } catch (\Exception $e) {
            error_log("TokenCsrf: Erro ao obter estatísticas: " . $e->getMessage());
            return [
                'usando_banco_dados' => false,
                'erro' => $e->getMessage()
            ];
        }
    }
}
