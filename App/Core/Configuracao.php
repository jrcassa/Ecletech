<?php

namespace App\Core;

/**
 * Classe para gerenciar configurações da aplicação
 */
class Configuracao
{
    private static ?Configuracao $instancia = null;
    private array $configuracoes = [];
    private CarregadorEnv $carregadorEnv;

    private function __construct()
    {
        $this->carregadorEnv = CarregadorEnv::obterInstancia();
        $this->carregarConfiguracoes();
    }

    /**
     * Obtém a instância única da Configuracao
     */
    public static function obterInstancia(): self
    {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    /**
     * Carrega todas as configurações
     */
    private function carregarConfiguracoes(): void
    {
        $this->configuracoes = [
            'app' => [
                'nome' => $this->carregadorEnv->obter('APP_NOME', 'Ecletech API'),
                'ambiente' => $this->carregadorEnv->obter('APP_AMBIENTE', 'producao'),
                'debug' => $this->carregadorEnv->obter('APP_DEBUG', 'false') === 'true',
                'url' => $this->carregadorEnv->obter('APP_URL', 'http://localhost'),
                'timezone' => $this->carregadorEnv->obter('APP_TIMEZONE', 'America/Sao_Paulo'),
            ],
            'database' => [
                'driver' => $this->carregadorEnv->obter('DB_DRIVER', 'mysql'),
                'host' => $this->carregadorEnv->obter('DB_HOST', 'localhost'),
                'porta' => $this->carregadorEnv->obter('DB_PORTA', '3306'),
                'nome' => $this->carregadorEnv->obter('DB_NOME', 'ecletech'),
                'usuario' => $this->carregadorEnv->obter('DB_USUARIO', 'root'),
                'senha' => $this->carregadorEnv->obter('DB_SENHA', ''),
                'charset' => $this->carregadorEnv->obter('DB_CHARSET', 'utf8mb4'),
            ],
            'jwt' => [
                'chave_secreta' => $this->carregadorEnv->obter('JWT_CHAVE_SECRETA', ''),
                'algoritmo' => $this->carregadorEnv->obter('JWT_ALGORITMO', 'HS256'),
                'expiracao' => (int) $this->carregadorEnv->obter('JWT_EXPIRACAO', '3600'),
                'refresh_expiracao' => (int) $this->carregadorEnv->obter('JWT_REFRESH_EXPIRACAO', '86400'),
                'emissor' => $this->carregadorEnv->obter('JWT_EMISSOR', 'Ecletech'),
            ],
            'seguranca' => [
                'csrf_habilitado' => $this->carregadorEnv->obter('CSRF_HABILITADO', 'true') === 'true',
                'csrf_expiracao' => (int) $this->carregadorEnv->obter('CSRF_EXPIRACAO', '3600'),
                'senha_min_tamanho' => (int) $this->carregadorEnv->obter('SENHA_MIN_TAMANHO', '8'),
                'senha_requer_maiuscula' => $this->carregadorEnv->obter('SENHA_REQUER_MAIUSCULA', 'true') === 'true',
                'senha_requer_minuscula' => $this->carregadorEnv->obter('SENHA_REQUER_MINUSCULA', 'true') === 'true',
                'senha_requer_numero' => $this->carregadorEnv->obter('SENHA_REQUER_NUMERO', 'true') === 'true',
                'senha_requer_especial' => $this->carregadorEnv->obter('SENHA_REQUER_ESPECIAL', 'true') === 'true',
                'tentativas_login_max' => (int) $this->carregadorEnv->obter('TENTATIVAS_LOGIN_MAX', '5'),
                'bloqueio_tempo' => (int) $this->carregadorEnv->obter('BLOQUEIO_TEMPO', '900'),
            ],
            'rate_limit' => [
                'habilitado' => $this->carregadorEnv->obter('RATE_LIMIT_HABILITADO', 'true') === 'true',
                'max_requisicoes' => (int) $this->carregadorEnv->obter('RATE_LIMIT_MAX_REQUISICOES', '100'),
                'janela_tempo' => (int) $this->carregadorEnv->obter('RATE_LIMIT_JANELA_TEMPO', '60'),
            ],
            'cors' => [
                'habilitado' => $this->carregadorEnv->obter('CORS_HABILITADO', 'true') === 'true',
                'origens_permitidas' => explode(',', $this->carregadorEnv->obter('CORS_ORIGENS_PERMITIDAS', '*')),
                'metodos_permitidos' => explode(',', $this->carregadorEnv->obter('CORS_METODOS_PERMITIDOS', 'GET,POST,PUT,DELETE,OPTIONS')),
                'cabecalhos_permitidos' => explode(',', $this->carregadorEnv->obter('CORS_CABECALHOS_PERMITIDOS', 'Content-Type,Authorization,X-CSRF-Token')),
                'expor_cabecalhos' => explode(',', $this->carregadorEnv->obter('CORS_EXPOR_CABECALHOS', 'Authorization')),
                'permitir_credenciais' => $this->carregadorEnv->obter('CORS_PERMITIR_CREDENCIAIS', 'true') === 'true',
                'max_age' => (int) $this->carregadorEnv->obter('CORS_MAX_AGE', '86400'),
            ],
            'auditoria' => [
                'habilitada' => $this->carregadorEnv->obter('AUDITORIA_HABILITADA', 'true') === 'true',
                'registrar_requisicoes' => $this->carregadorEnv->obter('AUDITORIA_REGISTRAR_REQUISICOES', 'true') === 'true',
                'registrar_respostas' => $this->carregadorEnv->obter('AUDITORIA_REGISTRAR_RESPOSTAS', 'false') === 'true',
            ],
        ];

        // Define o timezone
        date_default_timezone_set($this->configuracoes['app']['timezone']);
    }

    /**
     * Obtém uma configuração
     * Se a chave não contém ponto, busca diretamente no .env
     */
    public function obter(string $chave, mixed $valorPadrao = null): mixed
    {
        // Se a chave não tem ponto, busca diretamente do .env
        if (strpos($chave, '.') === false) {
            return $this->carregadorEnv->obter($chave, $valorPadrao);
        }

        // Caso contrário, busca do array interno
        $partes = explode('.', $chave);
        $valor = $this->configuracoes;

        foreach ($partes as $parte) {
            if (!isset($valor[$parte])) {
                return $valorPadrao;
            }
            $valor = $valor[$parte];
        }

        return $valor;
    }

    /**
     * Define uma configuração
     * Se a chave não contém ponto, salva diretamente no .env
     */
    public function definir(string $chave, mixed $valor): void
    {
        // Se a chave não tem ponto, é uma variável direta do .env
        if (strpos($chave, '.') === false) {
            // Salva no .env
            $this->carregadorEnv->definir($chave, $valor);
            return;
        }

        // Caso contrário, atualiza apenas em memória
        $partes = explode('.', $chave);
        $config = &$this->configuracoes;

        foreach ($partes as $i => $parte) {
            if ($i === count($partes) - 1) {
                $config[$parte] = $valor;
            } else {
                if (!isset($config[$parte]) || !is_array($config[$parte])) {
                    $config[$parte] = [];
                }
                $config = &$config[$parte];
            }
        }
    }

    /**
     * Verifica se uma configuração existe
     */
    public function existe(string $chave): bool
    {
        $partes = explode('.', $chave);
        $valor = $this->configuracoes;

        foreach ($partes as $parte) {
            if (!isset($valor[$parte])) {
                return false;
            }
            $valor = $valor[$parte];
        }

        return true;
    }

    /**
     * Obtém todas as configurações
     */
    public function obterTodas(): array
    {
        return $this->configuracoes;
    }
}
