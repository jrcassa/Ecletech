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

    /**
     * Define uma variável de ambiente e persiste no arquivo .env
     */
    public function definir(string $nome, mixed $valor, string $caminhoArquivo = null): bool
    {
        // Se não foi passado o caminho, tenta usar o padrão
        if ($caminhoArquivo === null) {
            $caminhoArquivo = __DIR__ . '/../../.env';
        }

        // Atualiza em memória
        $this->variaveis[$nome] = $valor;
        putenv("{$nome}={$valor}");
        $_ENV[$nome] = $valor;
        $_SERVER[$nome] = $valor;

        // Persiste no arquivo
        return $this->salvarNoArquivo($nome, $valor, $caminhoArquivo);
    }

    /**
     * Salva uma variável no arquivo .env
     */
    private function salvarNoArquivo(string $nome, mixed $valor, string $caminhoArquivo): bool
    {
        if (!file_exists($caminhoArquivo)) {
            return false;
        }

        // Lê o arquivo
        $conteudo = file_get_contents($caminhoArquivo);
        $linhas = explode("\n", $conteudo);
        $encontrado = false;

        // Procura pela variável existente
        foreach ($linhas as $indice => $linha) {
            $linha = trim($linha);

            // Ignora comentários e linhas vazias
            if (empty($linha) || str_starts_with($linha, '#')) {
                continue;
            }

            // Verifica se é a variável que estamos procurando
            if (strpos($linha, '=') !== false) {
                list($nomeExistente) = explode('=', $linha, 2);
                $nomeExistente = trim($nomeExistente);

                if ($nomeExistente === $nome) {
                    // Atualiza a linha
                    $linhas[$indice] = "{$nome}={$valor}";
                    $encontrado = true;
                    break;
                }
            }
        }

        // Se não encontrou, adiciona no final
        if (!$encontrado) {
            $linhas[] = "{$nome}={$valor}";
        }

        // Salva de volta no arquivo
        $novoConteudo = implode("\n", $linhas);
        return file_put_contents($caminhoArquivo, $novoConteudo) !== false;
    }
}
