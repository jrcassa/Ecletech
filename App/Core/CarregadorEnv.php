<?php

namespace App\Core;

/**
 * Classe para carregar e gerenciar variáveis de ambiente do arquivo .env
 */
class CarregadorEnv
{
    private static ?CarregadorEnv $instancia = null;
    private array $variaveis = [];

    private function __construct()
    {
        // Construtor privado para Singleton
    }

    /**
     * Obtém a instância única do CarregadorEnv
     */
    public static function obterInstancia(): self
    {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    /**
     * Carrega as variáveis do arquivo .env
     */
    public function carregar(string $caminhoArquivo): bool
    {
        if (!file_exists($caminhoArquivo)) {
            throw new \RuntimeException("Arquivo .env não encontrado: {$caminhoArquivo}");
        }

        $linhas = file($caminhoArquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($linhas as $linha) {
            $linha = trim($linha);

            // Ignora comentários e linhas vazias
            if (empty($linha) || str_starts_with($linha, '#')) {
                continue;
            }

            // Processa a linha
            if (strpos($linha, '=') !== false) {
                list($nome, $valor) = explode('=', $linha, 2);
                $nome = trim($nome);
                $valor = trim($valor);

                // Remove aspas do valor
                $valor = $this->limparValor($valor);

                // Armazena a variável
                $this->variaveis[$nome] = $valor;

                // Define no ambiente do sistema
                putenv("{$nome}={$valor}");
                $_ENV[$nome] = $valor;
                $_SERVER[$nome] = $valor;
            }
        }

        return true;
    }

    /**
     * Remove aspas e espaços extras do valor
     */
    private function limparValor(string $valor): string
    {
        $valor = trim($valor);

        // Remove aspas duplas ou simples
        if ((str_starts_with($valor, '"') && str_ends_with($valor, '"')) ||
            (str_starts_with($valor, "'") && str_ends_with($valor, "'"))) {
            $valor = substr($valor, 1, -1);
        }

        return $valor;
    }

    /**
     * Obtém o valor de uma variável de ambiente
     */
    public function obter(string $nome, mixed $valorPadrao = null): mixed
    {
        return $this->variaveis[$nome] ?? getenv($nome) ?: $valorPadrao;
    }

    /**
     * Verifica se uma variável existe
     */
    public function existe(string $nome): bool
    {
        return isset($this->variaveis[$nome]) || getenv($nome) !== false;
    }

    /**
     * Obtém todas as variáveis carregadas
     */
    public function obterTodas(): array
    {
        return $this->variaveis;
    }
}
