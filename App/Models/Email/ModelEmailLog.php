<?php

namespace App\Models\Email;

use App\Core\BancoDados;

/**
 * Model para gerenciar logs do sistema de Email
 */
class ModelEmailLog
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Adiciona log
     */
    public function adicionar(array $dados): int
    {
        $dados['criado_em'] = date('Y-m-d H:i:s');

        // IP e User Agent se disponíveis
        if (!isset($dados['ip']) && isset($_SERVER['REMOTE_ADDR'])) {
            $dados['ip'] = $_SERVER['REMOTE_ADDR'];
        }

        if (!isset($dados['user_agent']) && isset($_SERVER['HTTP_USER_AGENT'])) {
            $dados['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        }

        return $this->db->inserir('email_logs', $dados);
    }

    /**
     * Busca logs por tipo
     */
    public function buscarPorTipo(string $tipo, int $limit = 100): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM email_logs
             WHERE tipo = ?
             ORDER BY criado_em DESC
             LIMIT ?",
            [$tipo, $limit]
        );
    }

    /**
     * Busca logs por nível
     */
    public function buscarPorNivel(string $nivel, int $limit = 100): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM email_logs
             WHERE nivel = ?
             ORDER BY criado_em DESC
             LIMIT ?",
            [$nivel, $limit]
        );
    }

    /**
     * Busca logs por message_id
     */
    public function buscarPorMessageId(string $messageId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM email_logs
             WHERE message_id = ?
             ORDER BY criado_em ASC",
            [$messageId]
        );
    }

    /**
     * Busca logs recentes
     */
    public function buscarRecentes(int $limit = 100, int $offset = 0): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM email_logs
             ORDER BY criado_em DESC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    /**
     * Busca logs com filtros
     */
    public function buscar(array $filtros = [], int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM email_logs WHERE 1=1";
        $params = [];

        if (isset($filtros['tipo'])) {
            $sql .= " AND tipo = ?";
            $params[] = $filtros['tipo'];
        }

        if (isset($filtros['nivel'])) {
            $sql .= " AND nivel = ?";
            $params[] = $filtros['nivel'];
        }

        if (isset($filtros['message_id'])) {
            $sql .= " AND message_id = ?";
            $params[] = $filtros['message_id'];
        }

        if (isset($filtros['data_inicio'])) {
            $sql .= " AND criado_em >= ?";
            $params[] = $filtros['data_inicio'];
        }

        if (isset($filtros['data_fim'])) {
            $sql .= " AND criado_em <= ?";
            $params[] = $filtros['data_fim'];
        }

        $sql .= " ORDER BY criado_em DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->buscarTodos($sql, $params);
    }

    /**
     * Conta logs
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM email_logs WHERE 1=1";
        $params = [];

        if (isset($filtros['tipo'])) {
            $sql .= " AND tipo = ?";
            $params[] = $filtros['tipo'];
        }

        if (isset($filtros['nivel'])) {
            $sql .= " AND nivel = ?";
            $params[] = $filtros['nivel'];
        }

        $resultado = $this->db->buscarUm($sql, $params);
        return $resultado['total'] ?? 0;
    }

    /**
     * Remove logs antigos
     */
    public function limparAntigos(int $dias): int
    {
        return $this->db->executar(
            "DELETE FROM email_logs WHERE criado_em < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$dias]
        )->rowCount();
    }

    /**
     * Conta erros nas últimas 24 horas
     */
    public function contarErrosUltimas24h(): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM email_logs
             WHERE nivel = 'error'
             AND criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        return $resultado['total'] ?? 0;
    }
}
