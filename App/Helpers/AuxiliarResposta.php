<?php

namespace App\Helpers;

/**
 * Classe auxiliar para formatação de respostas HTTP/JSON
 */
class AuxiliarResposta
{
    /**
     * Envia resposta JSON de sucesso
     */
    public static function sucesso(mixed $dados = null, string $mensagem = 'Operação realizada com sucesso', int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');

        $resposta = [
            'sucesso' => true,
            'mensagem' => $mensagem,
            'codigo' => $codigo
        ];

        if ($dados !== null) {
            $resposta['dados'] = $dados;
        }

        echo json_encode($resposta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Envia resposta JSON de erro
     */
    public static function erro(string $mensagem = 'Ocorreu um erro', int $codigo = 400, ?array $erros = null): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');

        $resposta = [
            'sucesso' => false,
            'mensagem' => $mensagem,
            'codigo' => $codigo
        ];

        if ($erros !== null) {
            $resposta['erros'] = $erros;
        }

        echo json_encode($resposta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Envia resposta de validação com erros
     */
    public static function validacao(array $erros, string $mensagem = 'Dados inválidos'): void
    {
        self::erro($mensagem, 422, $erros);
    }

    /**
     * Envia resposta de erro de validação (alias com ordem de parâmetros invertida)
     */
    public static function erroValidacao(string $mensagem = 'Dados inválidos', array $erros = []): void
    {
        self::erro($mensagem, 422, $erros);
    }

    /**
     * Envia resposta de não autorizado
     */
    public static function naoAutorizado(string $mensagem = 'Não autorizado'): void
    {
        self::erro($mensagem, 401);
    }

    /**
     * Envia resposta de proibido
     */
    public static function proibido(string $mensagem = 'Acesso negado'): void
    {
        self::erro($mensagem, 403);
    }

    /**
     * Envia resposta de não encontrado
     */
    public static function naoEncontrado(string $mensagem = 'Recurso não encontrado'): void
    {
        self::erro($mensagem, 404);
    }

    /**
     * Envia resposta de erro interno
     */
    public static function erroInterno(string $mensagem = 'Erro interno do servidor'): void
    {
        self::erro($mensagem, 500);
    }

    /**
     * Envia resposta de conflito
     */
    public static function conflito(string $mensagem = 'Conflito de dados'): void
    {
        self::erro($mensagem, 409);
    }

    /**
     * Envia resposta de método não permitido
     */
    public static function metodoNaoPermitido(string $mensagem = 'Método não permitido'): void
    {
        self::erro($mensagem, 405);
    }

    /**
     * Envia resposta de muito requisições
     */
    public static function muitasRequisicoes(string $mensagem = 'Muitas requisições. Tente novamente mais tarde.'): void
    {
        self::erro($mensagem, 429);
    }

    /**
     * Envia resposta paginada
     */
    public static function paginado(
        array $dados,
        int $total,
        int $paginaAtual,
        int $porPagina,
        string $mensagem = 'Dados recuperados com sucesso'
    ): void {
        $totalPaginas = (int) ceil($total / $porPagina);

        self::sucesso([
            'itens' => $dados,
            'paginacao' => [
                'total' => $total,
                'por_pagina' => $porPagina,
                'pagina_atual' => $paginaAtual,
                'total_paginas' => $totalPaginas,
                'primeira_pagina' => 1,
                'ultima_pagina' => $totalPaginas,
                'proxima_pagina' => $paginaAtual < $totalPaginas ? $paginaAtual + 1 : null,
                'pagina_anterior' => $paginaAtual > 1 ? $paginaAtual - 1 : null
            ]
        ], $mensagem);
    }

    /**
     * Envia resposta sem conteúdo
     */
    public static function semConteudo(): void
    {
        http_response_code(204);
        exit;
    }

    /**
     * Envia resposta criado
     */
    public static function criado(mixed $dados = null, string $mensagem = 'Recurso criado com sucesso'): void
    {
        self::sucesso($dados, $mensagem, 201);
    }

    /**
     * Envia resposta aceito
     */
    public static function aceito(mixed $dados = null, string $mensagem = 'Requisição aceita'): void
    {
        self::sucesso($dados, $mensagem, 202);
    }

    /**
     * Retorna resposta personalizada
     */
    public static function personalizado(
        bool $sucesso,
        string $mensagem,
        int $codigo,
        ?array $dados = null,
        ?array $extras = null
    ): void {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');

        $resposta = [
            'sucesso' => $sucesso,
            'mensagem' => $mensagem,
            'codigo' => $codigo
        ];

        if ($dados !== null) {
            $resposta['dados'] = $dados;
        }

        if ($extras !== null) {
            $resposta = array_merge($resposta, $extras);
        }

        echo json_encode($resposta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Define cabeçalhos CORS
     */
    public static function definirCabecalhosCors(array $opcoes = []): void
    {
        $origensPermitidas = $opcoes['origens'] ?? ['*'];
        $metodosPermitidos = $opcoes['metodos'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
        $cabecalhosPermitidos = $opcoes['cabecalhos'] ?? ['Content-Type', 'Authorization', 'X-CSRF-Token'];
        $permitirCredenciais = $opcoes['credenciais'] ?? true;
        $maxAge = $opcoes['max_age'] ?? 86400;

        $origem = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array('*', $origensPermitidas) || in_array($origem, $origensPermitidas)) {
            header('Access-Control-Allow-Origin: ' . ($origem ?: '*'));
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $metodosPermitidos));
        header('Access-Control-Allow-Headers: ' . implode(', ', $cabecalhosPermitidos));

        if ($permitirCredenciais) {
            header('Access-Control-Allow-Credentials: true');
        }

        header('Access-Control-Max-Age: ' . $maxAge);

        // Se for uma requisição OPTIONS, retorna sem conteúdo
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    /**
     * Define cabeçalhos de segurança
     */
    public static function definirCabecalhosSeguranca(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header("Content-Security-Policy: default-src 'self'");
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    /**
     * Define cabeçalhos de cache
     */
    public static function definirCache(int $segundos = 0): void
    {
        if ($segundos > 0) {
            header('Cache-Control: public, max-age=' . $segundos);
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $segundos) . ' GMT');
        } else {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }

    /**
     * Obtém dados da requisição
     */
    public static function obterDados(): array
    {
        $metodo = $_SERVER['REQUEST_METHOD'];

        if ($metodo === 'GET') {
            return $_GET;
        }

        if (in_array($metodo, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

            if (str_contains($contentType, 'application/json')) {
                $json = file_get_contents('php://input');
                $dados = json_decode($json, true);
                return $dados ?: [];
            }

            return $_POST;
        }

        return [];
    }

    /**
     * Obtém cabeçalhos da requisição
     */
    public static function obterCabecalhos(): array
    {
        return getallheaders() ?: [];
    }

    /**
     * Obtém um cabeçalho específico
     */
    public static function obterCabecalho(string $nome): ?string
    {
        $cabecalhos = self::obterCabecalhos();
        return $cabecalhos[$nome] ?? $cabecalhos[strtolower($nome)] ?? null;
    }
}
