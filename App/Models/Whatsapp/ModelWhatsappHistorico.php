<?php

namespace App\Models\Whatsapp;

use App\Core\BancoDados;

/**
 * Model para gerenciar histórico de eventos do WhatsApp
 */
class ModelWhatsappHistorico
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Adiciona evento ao histórico
     */
    public function adicionar(array $dados): int
    {
        $dados['criado_em'] = date('Y-m-d H:i:s');
        return $this->db->inserir('whatsapp_historico', $dados);
    }

    /**
     * Busca histórico por queue_id
     */
    public function buscarPorQueueId(int $queueId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM whatsapp_historico
             WHERE queue_id = ?
             ORDER BY criado_em DESC",
            [$queueId]
        );
    }

    /**
     * Busca histórico por message_id
     */
    public function buscarPorMessageId(string $messageId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM whatsapp_historico
             WHERE message_id = ?
             ORDER BY criado_em DESC",
            [$messageId]
        );
    }

    /**
     * Busca histórico por tipo de evento
     */
    public function buscarPorTipoEvento(string $tipoEvento, int $limit = 100): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM whatsapp_historico
             WHERE tipo_evento = ?
             ORDER BY criado_em DESC
             LIMIT ?",
            [$tipoEvento, $limit]
        );
    }

    /**
     * Busca histórico com filtros
     */
    public function buscar(array $filtros = [], int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT h.*, q.destinatario, q.tipo_mensagem
                FROM whatsapp_historico h
                LEFT JOIN whatsapp_queue q ON h.queue_id = q.id
                WHERE 1=1";

        $params = [];

        if (isset($filtros['data_inicio'])) {
            $sql .= " AND h.criado_em >= ?";
            $params[] = $filtros['data_inicio'];
        }

        if (isset($filtros['data_fim'])) {
            $sql .= " AND h.criado_em <= ?";
            $params[] = $filtros['data_fim'];
        }

        if (isset($filtros['tipo_evento'])) {
            $sql .= " AND h.tipo_evento = ?";
            $params[] = $filtros['tipo_evento'];
        }

        if (isset($filtros['queue_id'])) {
            $sql .= " AND h.queue_id = ?";
            $params[] = $filtros['queue_id'];
        }

        $sql .= " ORDER BY h.criado_em DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->buscarTodos($sql, $params);
    }

    /**
     * Conta registros do histórico
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM whatsapp_historico WHERE 1=1";
        $params = [];

        if (isset($filtros['data_inicio'])) {
            $sql .= " AND criado_em >= ?";
            $params[] = $filtros['data_inicio'];
        }

        if (isset($filtros['data_fim'])) {
            $sql .= " AND criado_em <= ?";
            $params[] = $filtros['data_fim'];
        }

        if (isset($filtros['tipo_evento'])) {
            $sql .= " AND tipo_evento = ?";
            $params[] = $filtros['tipo_evento'];
        }

        $resultado = $this->db->buscarUm($sql, $params);
        return $resultado['total'] ?? 0;
    }

    /**
     * Remove histórico antigo
     */
    public function limparAntigo(int $dias): int
    {
        return $this->db->executar(
            "DELETE FROM whatsapp_historico WHERE criado_em < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$dias]
        )->rowCount();
    }

    /**
     * Conta mensagens únicas por status_code nas últimas 24 horas
     * Usa message_id DISTINCT para evitar duplicatas quando há múltiplos eventos
     */
    public function contarPorStatusUltimas24h(int $statusCode): int
    {
        // Para status >= 2 (enviado, entregue, lido), precisamos pegar o maior status por message_id
        $sql = "SELECT COUNT(DISTINCT message_id) as total
                FROM whatsapp_historico
                WHERE message_id IS NOT NULL
                AND criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND status_code >= ?";

        $resultado = $this->db->buscarUm($sql, [$statusCode]);
        return $resultado['total'] ?? 0;
    }

    /**
     * Conta eventos de erro nas últimas 24 horas
     */
    public function contarErrosUltimas24h(): int
    {
        $sql = "SELECT COUNT(*) as total
                FROM whatsapp_historico
                WHERE status_code = 0
                AND criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";

        $resultado = $this->db->buscarUm($sql);
        return $resultado['total'] ?? 0;
    }
}
