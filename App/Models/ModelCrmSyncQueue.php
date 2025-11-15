<?php

namespace App\Models;

use App\Core\BancoDados;

/**
 * Model para gerenciar fila de sincronização CRM (com suporte a batches)
 */
class ModelCrmSyncQueue
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Enfileira um batch de itens para sincronização
     */
    public function enfileirarBatch(
        string $batchId,
        int $scheduleId,
        int $idLoja,
        string $entidade,
        string $direcao,
        array $registros,
        int $prioridade = 0
    ): int {
        $total = 0;

        foreach ($registros as $registro) {
            $idRegistro = $registro['id_registro'] ?? null;
            $externalId = $registro['external_id'] ?? null;

            $this->db->executar(
                "INSERT INTO crm_sync_queue
                 (batch_id, schedule_id, id_loja, entidade, id_registro, external_id, direcao, prioridade, status, criado_em)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)",
                [
                    $batchId,
                    $scheduleId,
                    $idLoja,
                    $entidade,
                    $idRegistro,
                    $externalId,
                    $direcao,
                    $prioridade,
                    date('Y-m-d H:i:s')
                ]
            );

            $total++;
        }

        return $total;
    }

    /**
     * Busca próximo batch pendente
     */
    public function buscarProximoBatchPendente(): ?string
    {
        $resultado = $this->db->buscarUm(
            "SELECT DISTINCT batch_id
             FROM crm_sync_queue
             WHERE status = 'pending'
               AND deletado_em IS NULL
               AND (proximo_retry IS NULL OR proximo_retry <= NOW())
             ORDER BY prioridade DESC, criado_em ASC
             LIMIT 1"
        );

        return $resultado['batch_id'] ?? null;
    }

    /**
     * Busca todos os itens de um batch
     */
    public function buscarItensPorBatch(string $batchId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM crm_sync_queue
             WHERE batch_id = ?
               AND deletado_em IS NULL
             ORDER BY id ASC",
            [$batchId]
        );
    }

    /**
     * Busca itens pending de um batch
     */
    public function buscarPendentesPorBatch(string $batchId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM crm_sync_queue
             WHERE batch_id = ?
               AND status = 'pending'
               AND deletado_em IS NULL
             ORDER BY id ASC",
            [$batchId]
        );
    }

    /**
     * Marca item como processando
     */
    public function marcarComoProcessando(int $id): bool
    {
        $stmt = $this->db->executar(
            "UPDATE crm_sync_queue
             SET status = 'processing', started_at = ?
             WHERE id = ?",
            [date('Y-m-d H:i:s'), $id]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Marca item como completado
     */
    public function marcarComoCompletado(int $id, int $tempoMs = null): bool
    {
        $stmt = $this->db->executar(
            "UPDATE crm_sync_queue
             SET status = 'completed',
                 completed_at = ?,
                 tempo_processamento_ms = ?,
                 processado = 1,
                 processado_em = ?
             WHERE id = ?",
            [
                date('Y-m-d H:i:s'),
                $tempoMs,
                date('Y-m-d H:i:s'),
                $id
            ]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Marca item como falho e agenda retry
     */
    public function marcarComoFalho(int $id, string $erro, int $tentativaAtual): bool
    {
        // Calcula backoff exponencial: 2^tentativa minutos
        $backoffMinutos = pow(2, $tentativaAtual);
        $proximoRetry = date('Y-m-d H:i:s', strtotime("+{$backoffMinutos} minutes"));

        // Se já tentou 3 vezes, deixa como failed definitivamente
        $status = ($tentativaAtual >= 3) ? 'failed' : 'pending';

        $stmt = $this->db->executar(
            "UPDATE crm_sync_queue
             SET status = ?,
                 tentativas = ?,
                 erro_mensagem = ?,
                 proximo_retry = ?
             WHERE id = ?",
            [$status, $tentativaAtual, $erro, $proximoRetry, $id]
        );

        return $stmt->rowCount() > 0;
    }

    /**
     * Busca estatísticas de um batch
     */
    public function obterEstatisticasBatch(string $batchId): array
    {
        $resultado = $this->db->buscarUm(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                AVG(tempo_processamento_ms) as tempo_medio_ms
             FROM crm_sync_queue
             WHERE batch_id = ?",
            [$batchId]
        );

        return $resultado ?: [];
    }

    /**
     * Atualiza id_registro após criar no Ecletech
     */
    public function atualizarIdRegistro(int $id, int $idRegistro): bool
    {
        $stmt = $this->db->executar(
            "UPDATE crm_sync_queue SET id_registro = ? WHERE id = ?",
            [$idRegistro, $id]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Verifica se um batch está completo
     */
    public function batchEstaCompleto(string $batchId): bool
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as pendentes
             FROM crm_sync_queue
             WHERE batch_id = ?
               AND status IN ('pending', 'processing')",
            [$batchId]
        );

        return ($resultado['pendentes'] ?? 0) == 0;
    }

    /**
     * Remove itens completados antigos (limpeza)
     */
    public function limparAntigos(int $dias = 7): int
    {
        $stmt = $this->db->executar(
            "DELETE FROM crm_sync_queue
             WHERE status = 'completed'
               AND completed_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$dias]
        );

        return $stmt->rowCount();
    }

    /**
     * Conta itens por status
     */
    public function contarPorStatus(): array
    {
        $resultados = $this->db->buscarTodos(
            "SELECT status, COUNT(*) as total
             FROM crm_sync_queue
             WHERE deletado_em IS NULL
             GROUP BY status"
        );

        $contadores = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0
        ];

        foreach ($resultados as $resultado) {
            $contadores[$resultado['status']] = (int) $resultado['total'];
        }

        return $contadores;
    }

    /**
     * MÉTODOS LEGADOS (mantidos para compatibilidade)
     */

    /**
     * Busca itens pendentes na fila (método legado)
     */
    public function buscarPendentes(int $limit = 100): array
    {
        return $this->db->buscarTodos(
            "SELECT *
             FROM crm_sync_queue
             WHERE status = 'pending'
               AND deletado_em IS NULL
             ORDER BY prioridade DESC, criado_em ASC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Busca item por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM crm_sync_queue WHERE id = ? AND deletado_em IS NULL",
            [$id]
        );
    }

    /**
     * Enfileira um item individual (método legado - usar enfileirarBatch)
     */
    public function enfileirar(
        int $idLoja,
        string $entidade,
        ?int $idRegistro,
        string $direcao,
        int $prioridade = 0,
        ?string $externalId = null
    ): int {
        // Cria um batch único para este item
        $batchId = uniqid('legacy_', true);

        $this->db->executar(
            "INSERT INTO crm_sync_queue
             (batch_id, id_loja, entidade, id_registro, external_id, direcao, prioridade, status, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)",
            [
                $batchId,
                $idLoja,
                $entidade,
                $idRegistro,
                $externalId,
                $direcao,
                $prioridade,
                date('Y-m-d H:i:s')
            ]
        );

        return (int) $this->db->obterUltimoId();
    }
}
