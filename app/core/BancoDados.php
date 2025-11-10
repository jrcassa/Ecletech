<?php

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Classe para gerenciar conexões com o banco de dados
 */
class BancoDados
{
    private static ?BancoDados $instancia = null;
    private ?PDO $conexao = null;
    private Configuracao $config;

    private function __construct()
    {
        $this->config = Configuracao::obterInstancia();
        $this->conectar();
    }

    /**
     * Obtém a instância única do BancoDados
     */
    public static function obterInstancia(): self
    {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    /**
     * Estabelece a conexão com o banco de dados
     */
    private function conectar(): void
    {
        try {
            $driver = $this->config->obter('database.driver', 'mysql');
            $host = $this->config->obter('database.host', 'localhost');
            $porta = $this->config->obter('database.porta', '3306');
            $nome = $this->config->obter('database.nome');
            $usuario = $this->config->obter('database.usuario');
            $senha = $this->config->obter('database.senha');
            $charset = $this->config->obter('database.charset', 'utf8mb4');

            $dsn = "{$driver}:host={$host};port={$porta};dbname={$nome};charset={$charset}";

            $opcoes = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ];

            $this->conexao = new PDO($dsn, $usuario, $senha, $opcoes);
        } catch (PDOException $e) {
            throw new \RuntimeException("Erro ao conectar ao banco de dados: " . $e->getMessage());
        }
    }

    /**
     * Obtém a conexão PDO
     */
    public function obterConexao(): PDO
    {
        if ($this->conexao === null) {
            $this->conectar();
        }
        return $this->conexao;
    }

    /**
     * Executa uma query preparada
     */
    public function executar(string $sql, array $parametros = []): PDOStatement
    {
        try {
            $stmt = $this->conexao->prepare($sql);
            $stmt->execute($parametros);
            return $stmt;
        } catch (PDOException $e) {
            throw new \RuntimeException("Erro ao executar query: " . $e->getMessage());
        }
    }

    /**
     * Busca um único registro
     */
    public function buscarUm(string $sql, array $parametros = []): ?array
    {
        $stmt = $this->executar($sql, $parametros);
        $resultado = $stmt->fetch();
        return $resultado !== false ? $resultado : null;
    }

    /**
     * Busca múltiplos registros
     */
    public function buscarTodos(string $sql, array $parametros = []): array
    {
        $stmt = $this->executar($sql, $parametros);
        return $stmt->fetchAll();
    }

    /**
     * Insere um registro e retorna o ID
     */
    public function inserir(string $tabela, array $dados): int|string
    {
        $campos = array_keys($dados);
        $valores = array_values($dados);
        $placeholders = array_fill(0, count($campos), '?');

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $tabela,
            implode(', ', $campos),
            implode(', ', $placeholders)
        );

        $this->executar($sql, $valores);
        return $this->conexao->lastInsertId();
    }

    /**
     * Atualiza registros
     */
    public function atualizar(string $tabela, array $dados, string $condicao, array $parametrosCondicao = []): int
    {
        $set = [];
        $valores = [];

        foreach ($dados as $campo => $valor) {
            $set[] = "{$campo} = ?";
            $valores[] = $valor;
        }

        $valores = array_merge($valores, $parametrosCondicao);

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $tabela,
            implode(', ', $set),
            $condicao
        );

        $stmt = $this->executar($sql, $valores);
        return $stmt->rowCount();
    }

    /**
     * Deleta registros
     */
    public function deletar(string $tabela, string $condicao, array $parametros = []): int
    {
        $sql = sprintf("DELETE FROM %s WHERE %s", $tabela, $condicao);
        $stmt = $this->executar($sql, $parametros);
        return $stmt->rowCount();
    }

    /**
     * Inicia uma transação
     */
    public function iniciarTransacao(): bool
    {
        return $this->conexao->beginTransaction();
    }

    /**
     * Confirma uma transação
     */
    public function commit(): bool
    {
        return $this->conexao->commit();
    }

    /**
     * Reverte uma transação
     */
    public function rollback(): bool
    {
        return $this->conexao->rollBack();
    }

    /**
     * Verifica se está em uma transação
     */
    public function emTransacao(): bool
    {
        return $this->conexao->inTransaction();
    }

    /**
     * Executa uma função dentro de uma transação
     */
    public function transacao(callable $callback): mixed
    {
        $this->iniciarTransacao();

        try {
            $resultado = $callback($this);
            $this->commit();
            return $resultado;
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Escapa valores para uso em queries (use prepared statements sempre que possível)
     */
    public function escapar(string $valor): string
    {
        return $this->conexao->quote($valor);
    }

    /**
     * Fecha a conexão
     */
    public function fechar(): void
    {
        $this->conexao = null;
    }

    /**
     * Previne clonagem
     */
    private function __clone()
    {
    }

    /**
     * Previne desserialização
     */
    public function __wakeup()
    {
        throw new \Exception("Não é possível desserializar um singleton.");
    }
}
