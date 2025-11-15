<?php

namespace App\Models;

use App\Core\BancoDados;

/**
 * Model para logs de sincronização CRM (audit trail) - Sistema de Batches
 */
class ModelCrmSyncLog
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Registra início de processamento de um batch
     */
    public function registrarInicio(
        string $batchId,
        int $scheduleId,
        int $idLoja,
        string $entidade,
        string $direcao,
        int $totalRegistros
    ): int {
        $this->db->executar(
            "INSERT INTO crm_sync_logs
             (batch_id, schedule_id, id_loja, entidade, direcao, total_registros, inicio, status, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'running', ?)",
            [
                $batchId,
                $scheduleId,
                $idLoja,
                $entidade,
                $direcao,
                $totalRegistros,
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s')
            ]
        );

        return (int) $this->db->obterUltimoId();
    }

    /**
     * Registra fim de processamento com estatísticas
     */
    public function registrarFim(
        int $id,
        int $registrosProcessados,
        int $registrosCriados,
        int $registrosAtualizados,
        int $registrosComErro,
        string $status = 'completed',
        ?string $erroGeral = null
    ): bool {
        $inicio = $this->db->buscarUm(
            "SELECT inicio FROM crm_sync_logs WHERE id = ?",
            [$id]
        );

        $tempoTotalMs = null;
        if ($inicio && isset($inicio['inicio'])) {
            $inicioTime = strtotime($inicio['inicio']);
            $fimTime = time();
            $tempoTotalMs = ($fimTime - $inicioTime) * 1000;
        }

        $stmt = $this->db->executar(
            "UPDATE crm_sync_logs
             SET registros_processados = ?,
                 registros_criados = ?,
                 registros_atualizados = ?,
                 registros_com_erro = ?,
                 fim = ?,
                 tempo_total_ms = ?,
                 status = ?,
                 erro_geral = ?
             WHERE id = ?",
            [
                $registrosProcessados,
                $registrosCriados,
                $registrosAtualizados,
                $registrosComErro,
                date('Y-m-d H:i:s'),
                $tempoTotalMs,
                $status,
                $erroGeral,
                $id
            ]
        );

        return $stmt->rowCount() > 0;
    }

    /**
     * Atualiza estatísticas durante processamento
     */
    public function atualizarEstatisticas(
        int $id,
        int $registrosProcessados,
        int $registrosCriados,
        int $registrosAtualizados,
        int $registrosComErro
    ): bool {
        $stmt = $this->db->executar(
            "UPDATE crm_sync_logs
             SET registros_processados = ?,
                 registros_criados = ?,
                 registros_atualizados = ?,
                 registros_com_erro = ?
             WHERE id = ?",
            [
                $registrosProcessados,
                $registrosCriados,
                $registrosAtualizados,
                $registrosComErro,
                $id
            ]
        );

        return $stmt->rowCount() > 0;
    }

    /**
     * Busca log por batch_id
     */
    public function buscarPorBatch(string $batchId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM crm_sync_logs WHERE batch_id = ?",
            [$batchId]
        );
    }

    /**
     * Busca logs recentes
     */
    public function buscarRecentes(int $limit = 50): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM crm_sync_logs
             ORDER BY criado_em DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Busca logs por schedule
     */
    public function buscarPorSchedule(int $scheduleId, int $limit = 100): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM crm_sync_logs
             WHERE schedule_id = ?
             ORDER BY criado_em DESC
             LIMIT ?",
            [$scheduleId, $limit]
        );
    }

    /**
     * Busca logs por loja
     */
    public function buscarPorLoja(int $idLoja, int $limit = 100): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM crm_sync_logs
             WHERE id_loja = ?
             ORDER BY criado_em DESC
             LIMIT ?",
            [$idLoja, $limit]
        );
    }

    /**
     * Obtém estatísticas agregadas por schedule
     */
    public function obterEstatisticasPorSchedule(int $scheduleId): array
    {
        $resultado = $this->db->buscarUm(
            "SELECT
                COUNT(*) as total_execucoes,
                SUM(total_registros) as total_registros,
                SUM(registros_processados) as total_processados,
                SUM(registros_criados) as total_criados,
                SUM(registros_atualizados) as total_atualizados,
                SUM(registros_com_erro) as total_erros,
                AVG(tempo_total_ms) as tempo_medio_ms,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as execucoes_sucesso,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as execucoes_falhas
             FROM crm_sync_logs
             WHERE schedule_id = ?",
            [$scheduleId]
        );

        return $resultado ?: [];
    }

    /**
     * Obtém estatísticas agregadas por loja
     */
    public function obterEstatisticasPorLoja(int $idLoja): array
    {
        $resultado = $this->db->buscarUm(
            "SELECT
                COUNT(*) as total_execucoes,
                SUM(total_registros) as total_registros,
                SUM(registros_processados) as total_processados,
                SUM(registros_criados) as total_criados,
                SUM(registros_atualizados) as total_atualizados,
                SUM(registros_com_erro) as total_erros,
                AVG(tempo_total_ms) as tempo_medio_ms
             FROM crm_sync_logs
             WHERE id_loja = ?",
            [$idLoja]
        );

        return $resultado ?: [];
    }

    /**
     * Obtém estatísticas por entidade e direção
     */
    public function obterEstatisticasPorEntidade(
        int $idLoja,
        string $entidade,
        ?string $direcao = null
    ): array {
        if ($direcao) {
            $resultado = $this->db->buscarUm(
                "SELECT
                    COUNT(*) as total_execucoes,
                    SUM(total_registros) as total_registros,
                    SUM(registros_processados) as total_processados,
                    SUM(registros_criados) as total_criados,
                    SUM(registros_atualizados) as total_atualizados,
                    SUM(registros_com_erro) as total_erros
                 FROM crm_sync_logs
                 WHERE id_loja = ? AND entidade = ? AND direcao = ?",
                [$idLoja, $entidade, $direcao]
            );
        } else {
            $resultado = $this->db->buscarUm(
                "SELECT
                    COUNT(*) as total_execucoes,
                    SUM(total_registros) as total_registros,
                    SUM(registros_processados) as total_processados,
                    SUM(registros_criados) as total_criados,
                    SUM(registros_atualizados) as total_atualizados,
                    SUM(registros_com_erro) as total_erros
                 FROM crm_sync_logs
                 WHERE id_loja = ? AND entidade = ?",
                [$idLoja, $entidade]
            );
        }

        return $resultado ?: [];
    }

    /**
     * Limpa logs antigos (limpeza)
     */
    public function limparAntigos(int $dias = 30): int
    {
        $stmt = $this->db->executar(
            "DELETE FROM crm_sync_logs
             WHERE criado_em < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$dias]
        );

        return $stmt->rowCount();
    }

    /**
     * Busca logs com erro
     */
    public function buscarComErro(int $limit = 50): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM crm_sync_logs
             WHERE status = 'failed' OR registros_com_erro > 0
             ORDER BY criado_em DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Busca execuções em andamento
     */
    public function buscarEmAndamento(): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM crm_sync_logs
             WHERE status = 'running'
             ORDER BY criado_em DESC"
        );
    }
}
