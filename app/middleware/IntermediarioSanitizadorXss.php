<?php

namespace App\Middleware;

use App\Helpers\AuxiliarSanitizacao;

/**
 * Middleware para sanitização contra XSS
 */
class IntermediarioSanitizadorXss
{
    /**
     * Processa a requisição
     */
    public function handle(): bool
    {
        // Sanitiza $_GET
        if (!empty($_GET)) {
            $_GET = $this->sanitizarArray($_GET);
        }

        // Sanitiza $_POST
        if (!empty($_POST)) {
            $_POST = $this->sanitizarArray($_POST);
        }

        // Sanitiza dados JSON do corpo da requisição
        $this->sanitizarJson();

        return true;
    }

    /**
     * Sanitiza array recursivamente
     */
    private function sanitizarArray(array $dados): array
    {
        $resultado = [];

        foreach ($dados as $chave => $valor) {
            $chaveSanitizada = AuxiliarSanitizacao::antiXss($chave);

            if (is_array($valor)) {
                $resultado[$chaveSanitizada] = $this->sanitizarArray($valor);
            } elseif (is_string($valor)) {
                $resultado[$chaveSanitizada] = AuxiliarSanitizacao::antiXss($valor);
            } else {
                $resultado[$chaveSanitizada] = $valor;
            }
        }

        return $resultado;
    }

    /**
     * Sanitiza dados JSON
     */
    private function sanitizarJson(): void
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $json = file_get_contents('php://input');
            $dados = json_decode($json, true);

            if (is_array($dados)) {
                $dados = $this->sanitizarArray($dados);

                // Armazena os dados sanitizados em uma variável global para acesso posterior
                $GLOBALS['_JSON_SANITIZED'] = $dados;
            }
        }
    }
}
