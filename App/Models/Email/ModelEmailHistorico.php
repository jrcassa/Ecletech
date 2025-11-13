<?php

namespace App\Models\Email;

use App\Core\BancoDados;

/**
 * Model para gerenciar histórico de eventos de Email
 * Padrão: Segue estrutura do ModelWhatsappHistorico
 */
class ModelEmailHistorico
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
        return $this->db->inserir('email_historico', $dados);
    }

    /**
     * Busca histórico por message_id
     */
    public function buscarPorMessageId(string $messageId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM email_historico
             WHERE message_id = ?
             ORDER BY criado_em DESC",
            [$messageId]
        );
    }

    /**
     * Busca histórico por tracking_code
     */
    public function buscarPorTrackingCode(string $trackingCode): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM email_historico
             WHERE tracking_code = ?
             ORDER BY criado_em DESC",
            [$trackingCode]
        );
    }

    /**
     * Busca histórico por tipo de evento
     */
    public function buscarPorTipoEvento(string $tipoEvento, int $limit = 100): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM email_historico
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
        $sql = "SELECT h.*
                FROM email_historico h
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

        if (isset($filtros['status_code'])) {
            $sql .= " AND h.status_code = ?";
            $params[] = $filtros['status_code'];
        }

        if (isset($filtros['destinatario_email'])) {
            $sql .= " AND h.destinatario_email LIKE ?";
            $params[] = '%' . $filtros['destinatario_email'] . '%';
        }

        if (isset($filtros['tipo_entidade'])) {
            $sql .= " AND h.tipo_entidade = ?";
            $params[] = $filtros['tipo_entidade'];
        }

        if (isset($filtros['entidade_id'])) {
            $sql .= " AND h.entidade_id = ?";
            $params[] = $filtros['entidade_id'];
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
        $sql = "SELECT COUNT(*) as total FROM email_historico WHERE 1=1";
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

        if (isset($filtros['status_code'])) {
            $sql .= " AND status_code = ?";
            $params[] = $filtros['status_code'];
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
            "DELETE FROM email_historico WHERE criado_em < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$dias]
        )->rowCount();
    }

    /**
     * Conta emails únicos por status_code nas últimas 24 horas
     */
    public function contarPorStatusUltimas24h(int $statusCode): int
    {
        $sql = "SELECT COUNT(DISTINCT message_id) as total
                FROM email_historico
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
                FROM email_historico
                WHERE status_code = 0
                AND criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";

        $resultado = $this->db->buscarUm($sql);
        return $resultado['total'] ?? 0;
    }

    /**
     * Conta bounces nas últimas 24 horas
     */
    public function contarBouncesUltimas24h(): int
    {
        $sql = "SELECT COUNT(*) as total
                FROM email_historico
                WHERE status_code = 3
                AND criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";

        $resultado = $this->db->buscarUm($sql);
        return $resultado['total'] ?? 0;
    }

    /**
     * Conta emails abertos nas últimas 24 horas
     */
    public function contarAbertosUltimas24h(): int
    {
        return $this->contarPorStatusUltimas24h(4);
    }

    /**
     * Conta cliques nas últimas 24 horas
     */
    public function contarCliquesUltimas24h(): int
    {
        return $this->contarPorStatusUltimas24h(5);
    }

    /**
     * Busca registro único por message_id
     */
    public function buscarUnicoPorMessageId(string $messageId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM email_historico WHERE message_id = ? ORDER BY criado_em DESC LIMIT 1",
            [$messageId]
        );
    }

    /**
     * Busca registro único por tracking_code
     */
    public function buscarUnicoPorTrackingCode(string $trackingCode): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM email_historico WHERE tracking_code = ? ORDER BY criado_em DESC LIMIT 1",
            [$trackingCode]
        );
    }

    /**
     * Atualiza registro por message_id
     */
    public function atualizarPorMessageId(string $messageId, array $dados): void
    {
        $this->db->atualizar('email_historico', $dados, 'message_id = ?', [$messageId]);
    }

    /**
     * Atualiza registro por tracking_code
     */
    public function atualizarPorTrackingCode(string $trackingCode, array $dados): void
    {
        $this->db->atualizar('email_historico', $dados, 'tracking_code = ?', [$trackingCode]);
    }

    /**
     * Adiciona ou atualiza registro (upsert por message_id)
     */
    public function adicionarOuAtualizar(array $dados): int
    {
        // Se tem message_id, tenta buscar existente
        if (!empty($dados['message_id'])) {
            $existente = $this->buscarUnicoPorMessageId($dados['message_id']);

            if ($existente) {
                // Atualiza apenas campos não nulos
                $dadosUpdate = array_filter($dados, fn($v) => $v !== null);
                $this->atualizarPorMessageId($dados['message_id'], $dadosUpdate);
                return $existente['id'];
            }
        }

        // Se não existe, cria novo
        return $this->adicionar($dados);
    }

    /**
     * Registra evento de abertura de email
     */
    public function registrarAbertura(string $trackingCode, ?string $ip = null, ?string $userAgent = null): bool
    {
        $existente = $this->buscarUnicoPorTrackingCode($trackingCode);

        if (!$existente) {
            return false;
        }

        // Só registra a primeira abertura
        if ($existente['data_aberto'] !== null) {
            return true;
        }

        $dados = [
            'status' => 'aberto',
            'status_code' => 4,
            'data_aberto' => date('Y-m-d H:i:s'),
            'ip_abertura' => $ip,
            'user_agent' => $userAgent
        ];

        $this->atualizarPorTrackingCode($trackingCode, $dados);
        return true;
    }

    /**
     * Registra evento de clique em link
     */
    public function registrarClique(string $trackingCode, ?string $ip = null, ?string $userAgent = null): bool
    {
        $existente = $this->buscarUnicoPorTrackingCode($trackingCode);

        if (!$existente) {
            return false;
        }

        // Atualiza para status clicado (se ainda não foi)
        if ($existente['status_code'] < 5) {
            $dados = [
                'status' => 'clicado',
                'status_code' => 5,
                'data_clicado' => date('Y-m-d H:i:s'),
                'ip_abertura' => $ip ?? $existente['ip_abertura'],
                'user_agent' => $userAgent ?? $existente['user_agent']
            ];

            $this->atualizarPorTrackingCode($trackingCode, $dados);
        }

        return true;
    }
}
