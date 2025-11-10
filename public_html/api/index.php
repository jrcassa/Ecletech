<?php

/**
 * Ponto de entrada da API
 * Ecletech - Sistema de Gerenciamento
 */

// Define o nível de erros
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Define o timezone padrão
date_default_timezone_set('America/Sao_Paulo');

// Define o cabeçalho de resposta padrão
header('Content-Type: application/json; charset=utf-8');

// Autoloader personalizado
spl_autoload_register(function ($classe) {
    // Converte namespace para caminho de arquivo
    $prefixo = 'App\\';
    $diretorioBase = __DIR__ . '/../../app/';

    // Verifica se a classe usa o namespace base
    $tamanho = strlen($prefixo);
    if (strncmp($prefixo, $classe, $tamanho) !== 0) {
        return;
    }

    // Obtém o nome relativo da classe
    $classeRelativa = substr($classe, $tamanho);

    // Substitui namespace separators por diretório separators
    $arquivo = $diretorioBase . str_replace('\\', '/', $classeRelativa) . '.php';

    // Se o arquivo existe, inclui
    if (file_exists($arquivo)) {
        require $arquivo;
    }
});

try {
    // Carrega as variáveis de ambiente
    $caminhoEnv = __DIR__ . '/../../.env';
    $carregadorEnv = \App\Core\CarregadorEnv::obterInstancia();
    $carregadorEnv->carregar($caminhoEnv);

    // Obtém a configuração
    $config = \App\Core\Configuracao::obterInstancia();

    // Tratamento de erros
    set_error_handler(function($errno, $errstr, $errfile, $errline) use ($config) {
        if ($config->obter('app.debug', false)) {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        } else {
            error_log("Erro [{$errno}]: {$errstr} em {$errfile}:{$errline}");
            \App\Helpers\AuxiliarResposta::erroInterno('Ocorreu um erro no servidor');
        }
    });

    // Tratamento de exceções
    set_exception_handler(function($exception) use ($config) {
        if ($config->obter('app.debug', false)) {
            \App\Helpers\AuxiliarResposta::erro(
                $exception->getMessage(),
                500,
                [
                    'arquivo' => $exception->getFile(),
                    'linha' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString()
                ]
            );
        } else {
            error_log("Exceção: " . $exception->getMessage());
            \App\Helpers\AuxiliarResposta::erroInterno('Ocorreu um erro no servidor');
        }
    });

    // Carrega as rotas
    $router = require __DIR__ . '/../../app/routes/api.php';

    // Despacha a requisição
    $router->despachar();

} catch (\Exception $e) {
    // Log do erro
    error_log("Erro crítico: " . $e->getMessage());

    // Resposta de erro
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro interno do servidor',
        'codigo' => 500
    ], JSON_UNESCAPED_UNICODE);
}
