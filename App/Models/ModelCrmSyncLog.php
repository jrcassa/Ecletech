<?php

namespace App\Models;

use App\Core\BancoDados;

/**
 * Model para logs de sincronização CRM
 */
class ModelCrmSyncLog
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Cria novo log de sincronização
     */
    public function criar(array $dados): int
    {
        $campos = [
            'id_loja',
            'entidade',
            'id_registro',
            'direcao',
            'status',
            'mensagem',
            'dados_enviados',
            'dados_recebidos',
            'criado_em'
        ];

        $dados['criado_em'] = date('Y-m-d H:i:s');

        // Converte arrays para JSON
        if (isset($dados['dados_enviados']) && is_array($dados['dados_enviados'])) {
            $dados['dados_enviados'] = json_encode($dados['dados_enviados']);
        }

        if (isset($dados['dados_recebidos']) && is_array($dados['dados_recebidos'])) {
            $dados['dados_recebidos'] = json_encode($dados['dados_recebidos']);
        }

        $placeholders = array_fill(0, count($campos), '?');
        $valores = array_map(fn($campo) => $dados[$campo] ?? null, $campos);

        $sql = sprintf(
            "INSERT INTO crm_sync_log (%s) VALUES (%s)",
            implode(', ', $campos),
            implode(', ', $placeholders)
        );

        $this->db->executar($sql, $valores);

        return (int) $this->db->obterUltimoId();
    }

    /**
     * Busca logs por entidade e ID de registro
     */
    public function buscarPorRegistro(string $entidade, int $idRegistro, int $limit = 50): array
    {
        return $this->db->buscar(
            "SELECT * FROM crm_sync_log
             WHERE entidade = ?
               AND id_registro = ?
             ORDER BY criado_em DESC
             LIMIT ?",
            [$entidade, $idRegistro, $limit]
        );
    }

    /**
     * Busca logs por loja
     */
    public function buscarPorLoja(int $idLoja, int $limit = 100): array
    {
        return $this->db->buscar(
            "SELECT * FROM crm_sync_log
             WHERE id_loja = ?
             ORDER BY criado_em DESC
             LIMIT ?",
            [$idLoja, $limit]
        );
    }

    /**
     * Busca logs com erro
     */
    public function buscarErros(int $limit = 100): array
    {
        return $this->db->buscar(
            "SELECT * FROM crm_sync_log
             WHERE status = 'erro'
             ORDER BY criado_em DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Busca logs recentes (últimas 24h)
     */
    public function buscarRecentes(int $limit = 100): array
    {
        return $this->db->buscar(
            "SELECT * FROM crm_sync_log
             WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY criado_em DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Remove logs antigos (limpeza)
     */
    public function limparAntigos(int $dias = 30): int
    {
        $sql = "DELETE FROM crm_sync_log
                WHERE criado_em < DATE_SUB(NOW(), INTERVAL ? DAY)";

        $this->db->executar($sql, [$dias]);

        return $this->db->obterLinhasAfetadas();
    }

    /**
     * Conta logs por status nas últimas 24h
     */
    public function contarPorStatus(string $status): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total
             FROM crm_sync_log
             WHERE status = ?
               AND criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [$status]
        );

        return (int) ($resultado['total'] ?? 0);
    }

    /**
     * Obtém estatísticas de sincronização
     */
    public function obterEstatisticas(): array
    {
        $resultado = $this->db->buscarUm(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as sucessos,
                SUM(CASE WHEN status = 'erro' THEN 1 ELSE 0 END) as erros,
                SUM(CASE WHEN status = 'alerta' THEN 1 ELSE 0 END) as alertas
             FROM crm_sync_log
             WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        return [
            'total' => (int) ($resultado['total'] ?? 0),
            'sucessos' => (int) ($resultado['sucessos'] ?? 0),
            'erros' => (int) ($resultado['erros'] ?? 0),
            'alertas' => (int) ($resultado['alertas'] ?? 0),
            'taxa_sucesso' => $resultado['total'] > 0
                ? round(($resultado['sucessos'] / $resultado['total']) * 100, 2)
                : 0
        ];
    }
}
