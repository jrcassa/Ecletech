<?php

namespace App\Helpers;

use App\Models\ErroLog\ModelErroLog;

/**
 * Helper para facilitar o registro de erros no sistema
 *
 * Uso simples em blocos catch:
 * ```php
 * try {
 *     // código
 * } catch (\Exception $e) {
 *     ErrorLogger::log($e);
 * }
 * ```
 */
class ErrorLogger
{
    /**
     * Registra um erro no banco de dados
     *
     * @param \Throwable $exception Exceção capturada
     * @param array $opcoes Opções adicionais
     * @return int ID do erro criado (0 se falhar)
     */
    public static function log(\Throwable $exception, array $opcoes = []): int
    {
        try {
            $model = new ModelErroLog();

            // Determina o tipo de erro baseado na exceção
            $tipoErro = self::determinarTipoErro($exception);

            // Determina o nível baseado no tipo de exceção
            $nivel = self::determinarNivel($exception);

            // Captura informações da requisição
            $contexto = self::capturarContexto();

            // Adiciona contexto personalizado se fornecido
            if (!empty($opcoes['contexto'])) {
                $contexto = array_merge($contexto, $opcoes['contexto']);
            }

            $dados = [
                'tipo_erro' => $opcoes['tipo_erro'] ?? $tipoErro,
                'nivel' => $opcoes['nivel'] ?? $nivel,
                'mensagem' => $exception->getMessage(),
                'stack_trace' => $exception->getTraceAsString(),
                'arquivo' => $exception->getFile(),
                'linha' => $exception->getLine(),
                'codigo_erro' => $exception->getCode() ? (string) $exception->getCode() : null,
                'tipo_entidade' => $opcoes['tipo_entidade'] ?? null,
                'entidade_id' => $opcoes['entidade_id'] ?? null,
                'contexto' => $contexto,
                'usuario_id' => self::obterUsuarioId(),
                'url' => $_SERVER['REQUEST_URI'] ?? null,
                'metodo_http' => $_SERVER['REQUEST_METHOD'] ?? null,
                'ip_address' => self::obterIpCliente(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ];

            return $model->criar($dados);

        } catch (\Exception $e) {
            // Fallback: registra no error_log do PHP para evitar loop infinito
            error_log("ErrorLogger falhou ao salvar erro: " . $e->getMessage());
            error_log("Erro original: " . $exception->getMessage());
            return 0;
        }
    }

    /**
     * Registra um erro personalizado (sem exceção)
     *
     * @param string $mensagem Mensagem do erro
     * @param array $opcoes Opções adicionais
     * @return int ID do erro criado
     */
    public static function logCustom(string $mensagem, array $opcoes = []): int
    {
        try {
            $model = new ModelErroLog();

            $contexto = self::capturarContexto();

            if (!empty($opcoes['contexto'])) {
                $contexto = array_merge($contexto, $opcoes['contexto']);
            }

            $dados = [
                'tipo_erro' => $opcoes['tipo_erro'] ?? 'outro',
                'nivel' => $opcoes['nivel'] ?? 'medio',
                'mensagem' => $mensagem,
                'stack_trace' => $opcoes['stack_trace'] ?? null,
                'arquivo' => $opcoes['arquivo'] ?? null,
                'linha' => $opcoes['linha'] ?? null,
                'codigo_erro' => $opcoes['codigo_erro'] ?? null,
                'tipo_entidade' => $opcoes['tipo_entidade'] ?? null,
                'entidade_id' => $opcoes['entidade_id'] ?? null,
                'contexto' => $contexto,
                'usuario_id' => self::obterUsuarioId(),
                'url' => $_SERVER['REQUEST_URI'] ?? null,
                'metodo_http' => $_SERVER['REQUEST_METHOD'] ?? null,
                'ip_address' => self::obterIpCliente(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ];

            return $model->criar($dados);

        } catch (\Exception $e) {
            error_log("ErrorLogger::logCustom falhou: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Registra erro de banco de dados
     *
     * @param \Throwable $exception Exceção do banco
     * @param string|null $query Query SQL (opcional)
     * @param array $params Parâmetros da query (opcional)
     * @return int ID do erro criado
     */
    public static function logDatabase(\Throwable $exception, ?string $query = null, array $params = []): int
    {
        $contexto = [];

        if ($query) {
            $contexto['sql_query'] = $query;
        }

        if (!empty($params)) {
            // Remove valores sensíveis
            $paramsSafe = array_map(function($value) {
                if (is_string($value) && strlen($value) > 100) {
                    return substr($value, 0, 100) . '... (truncado)';
                }
                return $value;
            }, $params);
            $contexto['sql_params'] = $paramsSafe;
        }

        return self::log($exception, [
            'tipo_erro' => 'database',
            'nivel' => 'alto',
            'contexto' => $contexto
        ]);
    }

    /**
     * Registra erro de API externa
     *
     * @param \Throwable $exception Exceção da API
     * @param array $requestData Dados da requisição
     * @return int ID do erro criado
     */
    public static function logApi(\Throwable $exception, array $requestData = []): int
    {
        $contexto = [];

        if (!empty($requestData)) {
            $contexto['api_request'] = $requestData;
        }

        return self::log($exception, [
            'tipo_erro' => 'api',
            'nivel' => 'medio',
            'contexto' => $contexto
        ]);
    }

    /**
     * Registra erro de validação
     *
     * @param string $mensagem Mensagem de erro
     * @param array $camposInvalidos Campos que falharam na validação
     * @return int ID do erro criado
     */
    public static function logValidacao(string $mensagem, array $camposInvalidos = []): int
    {
        return self::logCustom($mensagem, [
            'tipo_erro' => 'validacao',
            'nivel' => 'baixo',
            'contexto' => [
                'campos_invalidos' => $camposInvalidos
            ]
        ]);
    }

    /**
     * Registra erro de autenticação
     *
     * @param string $mensagem Mensagem de erro
     * @param array $contextoExtra Contexto adicional
     * @return int ID do erro criado
     */
    public static function logAutenticacao(string $mensagem, array $contextoExtra = []): int
    {
        return self::logCustom($mensagem, [
            'tipo_erro' => 'autenticacao',
            'nivel' => 'alto',
            'contexto' => $contextoExtra
        ]);
    }

    /**
     * Determina o tipo de erro baseado na exceção
     *
     * @param \Throwable $exception
     * @return string
     */
    private static function determinarTipoErro(\Throwable $exception): string
    {
        $classe = get_class($exception);

        if (strpos($classe, 'PDOException') !== false || strpos($classe, 'Database') !== false) {
            return 'database';
        }

        if (strpos($classe, 'ValidationException') !== false) {
            return 'validacao';
        }

        if (strpos($classe, 'AuthException') !== false || strpos($classe, 'Unauthorized') !== false) {
            return 'autenticacao';
        }

        if (strpos($classe, 'ApiException') !== false || strpos($classe, 'HttpException') !== false) {
            return 'api';
        }

        if ($exception instanceof \ErrorException) {
            $severity = $exception->getSeverity();
            if ($severity === E_ERROR || $severity === E_PARSE || $severity === E_CORE_ERROR) {
                return 'fatal';
            }
            if ($severity === E_WARNING || $severity === E_CORE_WARNING) {
                return 'warning';
            }
            if ($severity === E_NOTICE || $severity === E_USER_NOTICE) {
                return 'notice';
            }
        }

        return 'exception';
    }

    /**
     * Determina o nível de severidade baseado na exceção
     *
     * @param \Throwable $exception
     * @return string
     */
    private static function determinarNivel(\Throwable $exception): string
    {
        $tipo = self::determinarTipoErro($exception);

        // Erros críticos
        if (in_array($tipo, ['fatal', 'database', 'autenticacao'])) {
            return 'critico';
        }

        // Erros altos
        if (in_array($tipo, ['exception', 'api'])) {
            return 'alto';
        }

        // Erros médios
        if ($tipo === 'warning') {
            return 'medio';
        }

        // Erros baixos
        return 'baixo';
    }

    /**
     * Captura contexto da requisição atual
     *
     * @return array
     */
    private static function capturarContexto(): array
    {
        $contexto = [];

        // Captura POST data (sem senhas)
        if (!empty($_POST)) {
            $postData = $_POST;
            // Remove campos sensíveis
            $camposSensiveis = ['senha', 'password', 'token', 'secret', 'api_key'];
            foreach ($camposSensiveis as $campo) {
                if (isset($postData[$campo])) {
                    $postData[$campo] = '***REDACTED***';
                }
            }
            $contexto['post_data'] = $postData;
        }

        // Captura GET data
        if (!empty($_GET)) {
            $contexto['get_data'] = $_GET;
        }

        // Captura headers importantes (sem tokens)
        $headers = [];
        if (function_exists('getallheaders')) {
            $allHeaders = getallheaders();
            $headersPermitidos = ['Content-Type', 'Accept', 'Referer', 'Origin'];
            foreach ($headersPermitidos as $header) {
                if (isset($allHeaders[$header])) {
                    $headers[$header] = $allHeaders[$header];
                }
            }
        }
        if (!empty($headers)) {
            $contexto['headers'] = $headers;
        }

        // Memória e tempo
        $contexto['memoria_usada'] = round(memory_get_usage() / 1024 / 1024, 2) . ' MB';
        $contexto['memoria_pico'] = round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB';

        return $contexto;
    }

    /**
     * Obtém o ID do usuário logado
     *
     * @return int|null
     */
    private static function obterUsuarioId(): ?int
    {
        // Tenta obter do session
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['usuario_id'])) {
            return (int) $_SESSION['usuario_id'];
        }

        // Tenta obter de JWT se existir
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            try {
                $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
                // Aqui você poderia decodificar o JWT e extrair o user_id
                // Por enquanto retornamos null
            } catch (\Exception $e) {
                // Ignora erro ao decodificar JWT
            }
        }

        return null;
    }

    /**
     * Obtém o IP do cliente
     *
     * @return string|null
     */
    private static function obterIpCliente(): ?string
    {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',  // Proxy
            'HTTP_X_REAL_IP',        // Nginx
            'REMOTE_ADDR'            // Direto
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Se for uma lista de IPs, pega o primeiro
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                return $ip;
            }
        }

        return null;
    }
}
