<?php

namespace App\Middleware;

use App\Core\Configuracao;

/**
 * Middleware para gerenciar CORS
 */
class IntermediarioCors
{
    private Configuracao $config;

    public function __construct()
    {
        $this->config = Configuracao::obterInstancia();
    }

    /**
     * Processa a requisição
     */
    public function handle(): bool
    {
        if (!$this->config->obter('cors.habilitado', true)) {
            return true;
        }

        $origem = $_SERVER['HTTP_ORIGIN'] ?? '';
        $origensPermitidas = $this->config->obter('cors.origens_permitidas', ['*']);
        $metodosPermitidos = $this->config->obter('cors.metodos_permitidos', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']);
        $cabecalhosPermitidos = $this->config->obter('cors.cabecalhos_permitidos', ['Content-Type', 'Authorization', 'X-CSRF-Token']);
        $exporCabecalhos = $this->config->obter('cors.expor_cabecalhos', ['Authorization']);
        $permitirCredenciais = $this->config->obter('cors.permitir_credenciais', true);
        $maxAge = $this->config->obter('cors.max_age', 86400);

        // Verifica se a origem é permitida
        if (in_array('*', $origensPermitidas) || in_array($origem, $origensPermitidas)) {
            header('Access-Control-Allow-Origin: ' . ($origem ?: '*'));
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $metodosPermitidos));
        header('Access-Control-Allow-Headers: ' . implode(', ', $cabecalhosPermitidos));
        header('Access-Control-Expose-Headers: ' . implode(', ', $exporCabecalhos));

        if ($permitirCredenciais) {
            header('Access-Control-Allow-Credentials: true');
        }

        header('Access-Control-Max-Age: ' . $maxAge);

        // Se for uma requisição OPTIONS (preflight), retorna sem executar a rota
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        return true;
    }
}
