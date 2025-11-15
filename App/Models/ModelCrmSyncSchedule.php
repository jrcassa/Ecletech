<?php

namespace App\Models;

use App\Core\BancoDados;

/**
 * Model para gerenciar agendamentos de sincronização CRM
 */
class ModelCrmSyncSchedule
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Cria novo agendamento
     */
    public function criar(array $dados): int
    {
        $campos = [
            'id_loja' => $dados['id_loja'],
            'entidade' => $dados['entidade'],
            'direcao' => $dados['direcao'],
            'batch_size' => $dados['batch_size'] ?? 10,
            'frequencia_minutos' => $dados['frequencia_minutos'] ?? 5,
            'horario_inicio' => $dados['horario_inicio'] ?? null,
            'horario_fim' => $dados['horario_fim'] ?? null,
            'ativo' => $dados['ativo'] ?? 1,
            'criado_em' => date('Y-m-d H:i:s')
        ];

        // Calcula próxima execução
        $campos['proxima_execucao'] = $this->calcularProximaExecucao(
            $campos['frequencia_minutos'],
            $campos['horario_inicio'],
            $campos['horario_fim']
        );

        return (int) $this->db->inserir('crm_sync_schedules', $campos);
    }

    /**
     * Atualiza agendamento
     */
    public function atualizar(int $id, array $dados): bool
    {
        $resultado = $this->db->atualizar('crm_sync_schedules', $dados, 'id = ?', [$id]);
        return $resultado > 0;
    }

    /**
     * Busca agendamento por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM crm_sync_schedules WHERE id = ? AND deletado_em IS NULL",
            [$id]
        );
    }

    /**
     * Busca agendamentos prontos para executar
     */
    public function buscarProntosParaExecutar(): array
    {
        $agora = date('Y-m-d H:i:s');
        $horaAtual = date('H:i:s');

        return $this->db->buscarTodos(
            "SELECT * FROM crm_sync_schedules
             WHERE ativo = 1
               AND executando = 0
               AND deletado_em IS NULL
               AND (proxima_execucao IS NULL OR proxima_execucao <= ?)
               AND (horario_inicio IS NULL OR horario_inicio <= ?)
               AND (horario_fim IS NULL OR horario_fim >= ?)
             ORDER BY proxima_execucao ASC",
            [$agora, $horaAtual, $horaAtual]
        );
    }

    /**
     * Busca todos os agendamentos ativos
     */
    public function listarAtivos(int $idLoja = null): array
    {
        if ($idLoja) {
            return $this->db->buscarTodos(
                "SELECT * FROM crm_sync_schedules
                 WHERE id_loja = ? AND ativo = 1 AND deletado_em IS NULL
                 ORDER BY entidade, direcao",
                [$idLoja]
            );
        }

        return $this->db->buscarTodos(
            "SELECT * FROM crm_sync_schedules
             WHERE ativo = 1 AND deletado_em IS NULL
             ORDER BY id_loja, entidade, direcao"
        );
    }

    /**
     * Marca schedule como executando
     */
    public function marcarComoExecutando(int $id, bool $executando = true): bool
    {
        return $this->atualizar($id, ['executando' => $executando ? 1 : 0]);
    }

    /**
     * Registra execução e calcula próxima
     */
    public function registrarExecucao(int $id, int $registrosProcessados = 0, int $erros = 0): bool
    {
        $schedule = $this->buscarPorId($id);
        if (!$schedule) {
            return false;
        }

        $dados = [
            'ultima_execucao' => date('Y-m-d H:i:s'),
            'executando' => 0,
            'total_execucoes' => $schedule['total_execucoes'] + 1,
            'total_registros_processados' => $schedule['total_registros_processados'] + $registrosProcessados,
            'total_erros' => $schedule['total_erros'] + $erros,
            'proxima_execucao' => $this->calcularProximaExecucao(
                $schedule['frequencia_minutos'],
                $schedule['horario_inicio'],
                $schedule['horario_fim']
            )
        ];

        return $this->atualizar($id, $dados);
    }

    /**
     * Calcula próxima execução baseado na frequência
     */
    private function calcularProximaExecucao(
        int $frequenciaMinutos,
        ?string $horarioInicio = null,
        ?string $horarioFim = null
    ): string {
        $agora = new \DateTime();
        $proxima = clone $agora;
        $proxima->modify("+{$frequenciaMinutos} minutes");

        // Se tem horário de início/fim, ajusta
        if ($horarioInicio && $horarioFim) {
            $horaProxima = $proxima->format('H:i:s');

            // Se passar do horário fim, agenda para o início do próximo dia
            if ($horaProxima > $horarioFim) {
                $proxima->modify('tomorrow');
                $proxima->setTime(
                    (int)substr($horarioInicio, 0, 2),
                    (int)substr($horarioInicio, 3, 2),
                    0
                );
            }

            // Se antes do horário de início, agenda para o horário de início
            if ($horaProxima < $horarioInicio) {
                $proxima->setTime(
                    (int)substr($horarioInicio, 0, 2),
                    (int)substr($horarioInicio, 3, 2),
                    0
                );
            }
        }

        return $proxima->format('Y-m-d H:i:s');
    }

    /**
     * Soft delete
     */
    public function deletar(int $id): bool
    {
        return $this->atualizar($id, ['deletado_em' => date('Y-m-d H:i:s')]);
    }

    /**
     * Ativa/desativa agendamento
     */
    public function alterarStatus(int $id, bool $ativo): bool
    {
        return $this->atualizar($id, ['ativo' => $ativo ? 1 : 0]);
    }
}
