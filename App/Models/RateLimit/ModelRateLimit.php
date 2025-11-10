<?php

namespace App\Models\RateLimit;

use App\Core\BancoDados;

/**
 * Model para gerenciar rate limiting
 */
class ModelRateLimit
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Busca registro de rate limit por identificador
     */
    public function buscarPorIdentificador(string $identificador): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM rate_limits WHERE identificador = ?",
            [$identificador]
        );
    }

    /**
     * Registra uma nova requisição
     */
    public function registrarRequisicao(string $identificador, int $timestamp): void
    {
        $this->db->inserir('rate_limit_requisicoes', [
            'identificador' => $identificador,
            'timestamp' => $timestamp,
            'criado_em' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Conta requisições dentro de uma janela de tempo
     */
    public function contarRequisicoes(string $identificador, int $janelaTempo): int
    {
        $tempoLimite = time() - $janelaTempo;

        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total
             FROM rate_limit_requisicoes
             WHERE identificador = ? AND timestamp > ?",
            [$identificador, $tempoLimite]
        );

        return (int) ($resultado['total'] ?? 0);
    }

    /**
     * Obtém requisições dentro da janela de tempo
     */
    public function obterRequisicoes(string $identificador, int $janelaTempo): array
    {
        $tempoLimite = time() - $janelaTempo;

        return $this->db->buscarTodos(
            "SELECT * FROM rate_limit_requisicoes
             WHERE identificador = ? AND timestamp > ?
             ORDER BY timestamp ASC",
            [$identificador, $tempoLimite]
        );
    }

    /**
     * Remove requisições antigas
     */
    public function limparRequisicoesAntigas(string $identificador, int $janelaTempo): int
    {
        $tempoLimite = time() - $janelaTempo;

        return $this->db->deletar(
            'rate_limit_requisicoes',
            'identificador = ? AND timestamp <= ?',
            [$identificador, $tempoLimite]
        );
    }

    /**
     * Bloqueia um identificador
     */
    public function bloquear(string $identificador, int $tempo): void
    {
        $bloqueadoAte = time() + $tempo;

        $registro = $this->buscarPorIdentificador($identificador);

        if ($registro) {
            $this->db->atualizar(
                'rate_limits',
                [
                    'bloqueado_ate' => $bloqueadoAte,
                    'atualizado_em' => date('Y-m-d H:i:s')
                ],
                'identificador = ?',
                [$identificador]
            );
        } else {
            $this->db->inserir('rate_limits', [
                'identificador' => $identificador,
                'bloqueado_ate' => $bloqueadoAte,
                'criado_em' => date('Y-m-d H:i:s'),
                'atualizado_em' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * Desbloqueia um identificador
     */
    public function desbloquear(string $identificador): void
    {
        $this->db->atualizar(
            'rate_limits',
            [
                'bloqueado_ate' => 0,
                'atualizado_em' => date('Y-m-d H:i:s')
            ],
            'identificador = ?',
            [$identificador]
        );
    }

    /**
     * Verifica se um identificador está bloqueado
     */
    public function estaBloqueado(string $identificador): bool
    {
        $registro = $this->buscarPorIdentificador($identificador);

        if (!$registro) {
            return false;
        }

        return ($registro['bloqueado_ate'] ?? 0) > time();
    }

    /**
     * Limpa todos os registros antigos (manutenção)
     */
    public function limparTodosRegistrosAntigos(int $dias = 7): int
    {
        $tempoLimite = time() - ($dias * 86400);

        return $this->db->deletar(
            'rate_limit_requisicoes',
            'timestamp <= ?',
            [$tempoLimite]
        );
    }

    /**
     * Remove bloqueios expirados
     */
    public function removerBloqueiosExpirados(): int
    {
        $agora = time();

        return $this->db->deletar(
            'rate_limits',
            'bloqueado_ate > 0 AND bloqueado_ate <= ?',
            [$agora]
        );
    }

    /**
     * Obtém estatísticas de um identificador
     */
    public function obterEstatisticas(string $identificador): array
    {
        $registro = $this->buscarPorIdentificador($identificador);

        $totalRequisicoes = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM rate_limit_requisicoes WHERE identificador = ?",
            [$identificador]
        );

        return [
            'identificador' => $identificador,
            'bloqueado' => $this->estaBloqueado($identificador),
            'bloqueado_ate' => $registro['bloqueado_ate'] ?? 0,
            'total_requisicoes' => (int) ($totalRequisicoes['total'] ?? 0),
            'criado_em' => $registro['criado_em'] ?? null,
            'atualizado_em' => $registro['atualizado_em'] ?? null
        ];
    }
}
