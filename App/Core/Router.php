<?php

namespace App\Core;

/**
 * Classe para gerenciar rotas da aplicação
 */
class Router
{
    private array $rotas = [];
    private array $middleware = [];
    private array $gruposMiddleware = [];
    private string $prefixoAtual = '';

    /**
     * Adiciona uma rota GET
     */
    public function get(string $uri, callable|array $acao): self
    {
        return $this->adicionarRota('GET', $uri, $acao);
    }

    /**
     * Adiciona uma rota POST
     */
    public function post(string $uri, callable|array $acao): self
    {
        return $this->adicionarRota('POST', $uri, $acao);
    }

    /**
     * Adiciona uma rota PUT
     */
    public function put(string $uri, callable|array $acao): self
    {
        return $this->adicionarRota('PUT', $uri, $acao);
    }

    /**
     * Adiciona uma rota DELETE
     */
    public function delete(string $uri, callable|array $acao): self
    {
        return $this->adicionarRota('DELETE', $uri, $acao);
    }

    /**
     * Adiciona uma rota PATCH
     */
    public function patch(string $uri, callable|array $acao): self
    {
        return $this->adicionarRota('PATCH', $uri, $acao);
    }

    /**
     * Adiciona uma rota OPTIONS
     */
    public function options(string $uri, callable|array $acao): self
    {
        return $this->adicionarRota('OPTIONS', $uri, $acao);
    }

    /**
     * Adiciona uma rota para qualquer método
     */
    public function any(string $uri, callable|array $acao): self
    {
        $metodos = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        foreach ($metodos as $metodo) {
            $this->adicionarRota($metodo, $uri, $acao);
        }
        return $this;
    }

    /**
     * Adiciona middleware a uma rota
     */
    public function middleware(string|array|object $middleware): self
    {
        $ultimaChave = array_key_last($this->rotas);
        if ($ultimaChave !== null) {
            $middlewares = is_array($middleware) ? $middleware : [$middleware];
            $this->rotas[$ultimaChave]['middleware'] = array_merge(
                $this->rotas[$ultimaChave]['middleware'] ?? [],
                $middlewares
            );
        }
        return $this;
    }

    /**
     * Cria um grupo de rotas com prefixo e/ou middleware
     */
    public function grupo(array $opcoes, callable $callback): void
    {
        $prefixoAnterior = $this->prefixoAtual;
        $middlewareAnterior = $this->gruposMiddleware;

        if (isset($opcoes['prefixo'])) {
            $this->prefixoAtual .= '/' . trim($opcoes['prefixo'], '/');
        }

        if (isset($opcoes['middleware'])) {
            $middlewares = is_array($opcoes['middleware']) ? $opcoes['middleware'] : [$opcoes['middleware']];
            $this->gruposMiddleware = array_merge($this->gruposMiddleware, $middlewares);
        }

        $callback($this);

        $this->prefixoAtual = $prefixoAnterior;
        $this->gruposMiddleware = $middlewareAnterior;
    }

    /**
     * Adiciona uma rota
     */
    private function adicionarRota(string $metodo, string $uri, callable|array $acao): self
    {
        $uri = '/' . trim($this->prefixoAtual . '/' . trim($uri, '/'), '/');
        $uri = $uri === '/' ? '/' : rtrim($uri, '/');

        $this->rotas[] = [
            'metodo' => $metodo,
            'uri' => $uri,
            'acao' => $acao,
            'middleware' => $this->gruposMiddleware,
            'padrao' => $this->converterParaPadrao($uri)
        ];

        return $this;
    }

    /**
     * Converte a URI em um padrão regex
     */
    private function converterParaPadrao(string $uri): string
    {
        // Substitui {parametro} por expressão regular
        $padrao = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $uri);
        return '#^' . $padrao . '$#';
    }

    /**
     * Despacha a requisição para a rota correspondente
     */
    public function despachar(): void
    {
        // Registra a requisição para auditoria
        try {
            $auditoria = new RegistroAuditoria();
            $auditoria->registrarRequisicao();
        } catch (\Exception $e) {
            // Não interrompe o fluxo se falhar o registro de auditoria
            error_log("Erro ao registrar requisição na auditoria: " . $e->getMessage());
        }

        $metodo = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Remove o prefixo base da aplicação se existir
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptName !== '/' && strpos($uri, $scriptName) === 0) {
            $uri = substr($uri, strlen($scriptName));
        }

        // Remove trailing slash, exceto para a raiz
        $uri = $uri === '/' ? '/' : rtrim($uri, '/');

        $rota = $this->encontrarRota($metodo, $uri);

        if ($rota === null) {
            $this->responder404();
            return;
        }

        try {
            // Executa os middlewares
            $this->executarMiddlewares($rota['middleware']);

            // Executa a ação da rota
            $resposta = $this->executarAcao($rota['acao'], $rota['parametros']);

            // Envia a resposta
            $this->enviarResposta($resposta);
        } catch (\Exception $e) {
            $this->responderErro($e);
        }
    }

    /**
     * Encontra a rota correspondente
     */
    private function encontrarRota(string $metodo, string $uri): ?array
    {
        foreach ($this->rotas as $rota) {
            if ($rota['metodo'] === $metodo && preg_match($rota['padrao'], $uri, $matches)) {
                // Extrai os parâmetros nomeados
                $parametros = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                return [
                    'acao' => $rota['acao'],
                    'middleware' => $rota['middleware'],
                    'parametros' => $parametros
                ];
            }
        }

        return null;
    }

    /**
     * Executa os middlewares
     */
    private function executarMiddlewares(array $middlewares): void
    {
        foreach ($middlewares as $middleware) {
            if (is_string($middleware) && isset($this->middleware[$middleware])) {
                $instancia = $this->middleware[$middleware];
            } elseif (is_string($middleware) && class_exists($middleware)) {
                $instancia = new $middleware();
            } else {
                $instancia = $middleware;
            }

            if (method_exists($instancia, 'handle')) {
                $resultado = $instancia->handle();
                if ($resultado === false) {
                    throw new \RuntimeException("Middleware bloqueou a requisição");
                }
            }
        }
    }

    /**
     * Executa a ação da rota
     */
    private function executarAcao(callable|array $acao, array $parametros): mixed
    {
        if (is_array($acao)) {
            [$classe, $metodo] = $acao;
            $controlador = new $classe();
            return call_user_func_array([$controlador, $metodo], $parametros);
        }

        return call_user_func_array($acao, $parametros);
    }

    /**
     * Envia a resposta
     */
    private function enviarResposta(mixed $resposta): void
    {
        if (is_array($resposta) || is_object($resposta)) {
            header('Content-Type: application/json');
            echo json_encode($resposta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif (is_string($resposta)) {
            echo $resposta;
        }
    }

    /**
     * Responde com erro 404
     */
    private function responder404(): void
    {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Rota não encontrada',
            'codigo' => 404
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Responde com erro
     */
    private function responderErro(\Exception $e): void
    {
        $codigo = $e->getCode() ?: 500;
        http_response_code($codigo);

        header('Content-Type: application/json');
        echo json_encode([
            'sucesso' => false,
            'mensagem' => $e->getMessage(),
            'codigo' => $codigo
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Registra um middleware
     */
    public function registrarMiddleware(string $nome, object|string $classe): void
    {
        $this->middleware[$nome] = is_string($classe) ? new $classe() : $classe;
    }

    /**
     * Obtém todas as rotas registradas
     */
    public function obterRotas(): array
    {
        return $this->rotas;
    }
}
