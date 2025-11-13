<?php

namespace App\Models\FrotaAbastecimento;

use App\Core\BancoDados;

/**
 * Model para gerenciar notificações de abastecimentos
 */
class ModelFrotaAbastecimentoNotificacao
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Busca notificação por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM frotas_abastecimentos_notificacoes WHERE id = ?",
            [$id]
        );
    }

    /**
     * Busca notificações de um abastecimento
     */
    public function buscarPorAbastecimento(int $abastecimento_id): array
    {
        $sql = "
            SELECT
                n.*,
                c.nome as destinatario_nome,
                c.email as destinatario_email
            FROM frotas_abastecimentos_notificacoes n
            INNER JOIN colaboradores c ON c.id = n.destinatario_id
            WHERE n.abastecimento_id = ?
            ORDER BY n.criado_em DESC
        ";

        return $this->db->buscarTodos($sql, [$abastecimento_id]);
    }

    /**
     * Lista notificações com filtros
     */
    public function listar(array $filtros = []): array
    {
        $sql = "
            SELECT
                n.*,
                c.nome as destinatario_nome,
                fa.frota_id,
                f.placa as frota_placa
            FROM frotas_abastecimentos_notificacoes n
            INNER JOIN colaboradores c ON c.id = n.destinatario_id
            INNER JOIN frotas_abastecimentos fa ON fa.id = n.abastecimento_id
            INNER JOIN frotas f ON f.id = fa.frota_id
            WHERE 1=1
        ";
        $parametros = [];

        // Filtro por tipo
        if (isset($filtros['tipo_notificacao'])) {
            $sql .= " AND n.tipo_notificacao = ?";
            $parametros[] = $filtros['tipo_notificacao'];
        }

        // Filtro por destinatário
        if (isset($filtros['destinatario_id'])) {
            $sql .= " AND n.destinatario_id = ?";
            $parametros[] = $filtros['destinatario_id'];
        }

        // Filtro por status
        if (isset($filtros['status_envio'])) {
            $sql .= " AND n.status_envio = ?";
            $parametros[] = $filtros['status_envio'];
        }

        $sql .= " ORDER BY n.criado_em DESC";

        // Paginação
        if (isset($filtros['limite'])) {
            $sql .= " LIMIT ?";
            $parametros[] = (int) $filtros['limite'];

            if (isset($filtros['offset'])) {
                $sql .= " OFFSET ?";
                $parametros[] = (int) $filtros['offset'];
            }
        }

        return $this->db->buscarTodos($sql, $parametros);
    }

    /**
     * Busca notificações com erro para retentar
     */
    public function buscarParaRetentar(): array
    {
        $sql = "
            SELECT * FROM frotas_abastecimentos_notificacoes
            WHERE status_envio = 'erro'
            AND tentativas < 3
            ORDER BY criado_em ASC
            LIMIT 50
        ";

        return $this->db->buscarTodos($sql);
    }

    /**
     * Cria registro de notificação
     */
    public function criar(array $dados): int
    {
        $sql = "
            INSERT INTO frotas_abastecimentos_notificacoes (
                abastecimento_id, tipo_notificacao, destinatario_id,
                telefone, mensagem, status_envio, criado_em
            ) VALUES (?, ?, ?, ?, ?, 'pendente', NOW())
        ";

        $parametros = [
            $dados['abastecimento_id'],
            $dados['tipo_notificacao'],
            $dados['destinatario_id'],
            $dados['telefone'],
            $dados['mensagem']
        ];

        return $this->db->executar($sql, $parametros);
    }

    /**
     * Marca notificação como enviada
     */
    public function marcarEnviado(int $id): bool
    {
        $sql = "
            UPDATE frotas_abastecimentos_notificacoes SET
                status_envio = 'enviado',
                enviado_em = NOW()
            WHERE id = ?
        ";

        $this->db->executar($sql, [$id]);
        return true;
    }

    /**
     * Marca notificação com erro
     */
    public function marcarErro(int $id, string $erro): bool
    {
        $sql = "
            UPDATE frotas_abastecimentos_notificacoes SET
                status_envio = 'erro',
                erro_mensagem = ?,
                tentativas = tentativas + 1
            WHERE id = ?
        ";

        $this->db->executar($sql, [$erro, $id]);
        return true;
    }

    /**
     * Obtém estatísticas de envio
     */
    public function obterEstatisticas(array $filtros = []): array
    {
        $sql = "
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status_envio = 'enviado' THEN 1 ELSE 0 END) as enviados,
                SUM(CASE WHEN status_envio = 'erro' THEN 1 ELSE 0 END) as erros,
                SUM(CASE WHEN status_envio = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                ROUND(AVG(tentativas), 2) as media_tentativas
            FROM frotas_abastecimentos_notificacoes
            WHERE 1=1
        ";
        $parametros = [];

        if (isset($filtros['data_inicio'])) {
            $sql .= " AND criado_em >= ?";
            $parametros[] = $filtros['data_inicio'];
        }

        if (isset($filtros['data_fim'])) {
            $sql .= " AND criado_em <= ?";
            $parametros[] = $filtros['data_fim'];
        }

        return $this->db->buscarUm($sql, $parametros) ?: [];
    }
}
