<?php

namespace App\Models;

use App\Core\BancoDados;

/**
 * Model para gerenciar fila de sincronização CRM
 */
class ModelCrmSyncQueue
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Busca itens pendentes na fila (ordenados por prioridade e data)
     */
    public function buscarPendentes(int $limit = 100): array
    {
        return $this->db->buscar(
            "SELECT *
             FROM crm_sync_queue
             WHERE processado = 0
               AND tentativas < 3
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
     * Enfileira um item para sincronização
     */
    public function enfileirar(
        int $idLoja,
        string $entidade,
        int $idRegistro,
        string $direcao,
        int $prioridade = 0
    ): int {
        // Verifica se já existe item pendente para este registro
        $existente = $this->db->buscarUm(
            "SELECT id FROM crm_sync_queue
             WHERE entidade = ?
               AND id_registro = ?
               AND direcao = ?
               AND processado = 0
               AND deletado_em IS NULL",
            [$entidade, $idRegistro, $direcao]
        );

        if ($existente) {
            // Atualiza prioridade se for maior
            if ($prioridade > 0) {
                $this->db->executar(
                    "UPDATE crm_sync_queue SET prioridade = ? WHERE id = ?",
                    [$prioridade, $existente['id']]
                );
            }
            return $existente['id'];
        }

        // Cria novo item na fila
        $this->db->executar(
            "INSERT INTO crm_sync_queue
             (id_loja, entidade, id_registro, direcao, prioridade, criado_em)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $idLoja,
                $entidade,
                $idRegistro,
                $direcao,
                $prioridade,
                date('Y-m-d H:i:s')
            ]
        );

        return (int) $this->db->obterUltimoId();
    }

    /**
     * Marca item como processado
     */
    public function marcarProcessado(int $id): bool
    {
        return $this->db->executar(
            "UPDATE crm_sync_queue
             SET processado = 1, processado_em = ?
             WHERE id = ?",
            [date('Y-m-d H:i:s'), $id]
        );
    }

    /**
     * Atualiza item da fila
     */
    public function atualizar(int $id, array $dados): bool
    {
        $camposPermitidos = ['processado', 'tentativas', 'erro', 'processado_em'];
        $sets = [];
        $valores = [];

        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $dados)) {
                $sets[] = "{$campo} = ?";
                $valores[] = $dados[$campo];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $valores[] = $id;

        $sql = sprintf(
            "UPDATE crm_sync_queue SET %s WHERE id = ?",
            implode(', ', $sets)
        );

        return $this->db->executar($sql, $valores);
    }

    /**
     * Incrementa contador de tentativas
     */
    public function incrementarTentativas(int $id, ?string $erro = null): bool
    {
        $dados = ['tentativas' => 'tentativas + 1'];

        if ($erro) {
            $dados['erro'] = $erro;
        }

        return $this->db->executar(
            "UPDATE crm_sync_queue
             SET tentativas = tentativas + 1, erro = ?
             WHERE id = ?",
            [$erro, $id]
        );
    }

    /**
     * Remove itens processados antigos (limpeza)
     */
    public function limparAntigos(int $dias = 7): int
    {
        $sql = "DELETE FROM crm_sync_queue
                WHERE processado = 1
                  AND processado_em < DATE_SUB(NOW(), INTERVAL ? DAY)";

        $this->db->executar($sql, [$dias]);

        return $this->db->obterLinhasAfetadas();
    }

    /**
     * Conta itens pendentes
     */
    public function contarPendentes(): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total
             FROM crm_sync_queue
             WHERE processado = 0 AND deletado_em IS NULL"
        );

        return (int) ($resultado['total'] ?? 0);
    }

    /**
     * Conta itens processados hoje
     */
    public function contarProcessadosHoje(): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total
             FROM crm_sync_queue
             WHERE processado = 1
               AND DATE(processado_em) = CURDATE()"
        );

        return (int) ($resultado['total'] ?? 0);
    }

    /**
     * Conta erros nas últimas 24h
     */
    public function contarErros24h(): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total
             FROM crm_sync_queue
             WHERE erro IS NOT NULL
               AND criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        return (int) ($resultado['total'] ?? 0);
    }

    /**
     * Busca estatísticas da fila
     */
    public function obterEstatisticas(): array
    {
        return [
            'pendentes' => $this->contarPendentes(),
            'processados_hoje' => $this->contarProcessadosHoje(),
            'erros_24h' => $this->contarErros24h()
        ];
    }
}
