<?php

namespace App\Models\FrotaAbastecimento;

use App\Core\BancoDados;

/**
 * Model para gerenciar configurações de relatórios
 */
class ModelFrotaAbastecimentoRelatorioConfiguracao
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Busca configuração por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM frotas_abastecimentos_relatorios_configuracoes WHERE id = ?",
            [$id]
        );
    }

    /**
     * Busca todas as configurações de um colaborador
     */
    public function buscarPorColaborador(int $colaborador_id): array
    {
        $sql = "
            SELECT * FROM frotas_abastecimentos_relatorios_configuracoes
            WHERE colaborador_id = ?
            ORDER BY tipo_relatorio ASC
        ";

        return $this->db->buscarTodos($sql, [$colaborador_id]);
    }

    /**
     * Busca configuração específica de um colaborador e tipo
     */
    public function buscarPorColaboradorETipo(int $colaborador_id, string $tipo_relatorio): ?array
    {
        $sql = "
            SELECT * FROM frotas_abastecimentos_relatorios_configuracoes
            WHERE colaborador_id = ?
            AND tipo_relatorio = ?
        ";

        return $this->db->buscarUm($sql, [$colaborador_id, $tipo_relatorio]);
    }

    /**
     * Lista todas configurações
     */
    public function listar(array $filtros = []): array
    {
        $sql = "
            SELECT
                c.*,
                col.nome as colaborador_nome,
                col.email as colaborador_email,
                col.telefone as colaborador_telefone
            FROM frotas_abastecimentos_relatorios_configuracoes c
            INNER JOIN colaboradores col ON col.id = c.colaborador_id
            WHERE 1=1
        ";
        $parametros = [];

        if (isset($filtros['ativo'])) {
            $sql .= " AND c.ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        if (isset($filtros['tipo_relatorio'])) {
            $sql .= " AND c.tipo_relatorio = ?";
            $parametros[] = $filtros['tipo_relatorio'];
        }

        $sql .= " ORDER BY col.nome ASC";

        return $this->db->buscarTodos($sql, $parametros);
    }

    /**
     * Busca configurações ativas para envio
     */
    public function buscarParaEnvio(string $tipo_relatorio, string $dia): array
    {
        $sql = "
            SELECT
                c.*,
                col.nome as colaborador_nome,
                col.telefone as colaborador_telefone
            FROM frotas_abastecimentos_relatorios_configuracoes c
            INNER JOIN colaboradores col ON col.id = c.colaborador_id
            WHERE c.ativo = TRUE
            AND c.tipo_relatorio = ?
            AND col.ativo = TRUE
            AND col.deletado_em IS NULL
            AND col.telefone IS NOT NULL
        ";
        $parametros = [$tipo_relatorio];

        if ($tipo_relatorio === 'semanal') {
            $sql .= " AND c.dia_envio_semanal = ?";
            $parametros[] = $dia;
        } else {
            $sql .= " AND c.dia_envio_mensal = ?";
            $parametros[] = (int) $dia;
        }

        return $this->db->buscarTodos($sql, $parametros);
    }

    /**
     * Cria configuração
     */
    public function criar(array $dados): int
    {
        $sql = "
            INSERT INTO frotas_abastecimentos_relatorios_configuracoes (
                colaborador_id, tipo_relatorio, ativo, dia_envio_semanal,
                dia_envio_mensal, hora_envio, filtros_personalizados,
                formato_relatorio, criado_por, criado_em
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        $parametros = [
            $dados['colaborador_id'],
            $dados['tipo_relatorio'],
            $dados['ativo'] ?? true,
            $dados['dia_envio_semanal'] ?? 'segunda',
            $dados['dia_envio_mensal'] ?? 1,
            $dados['hora_envio'] ?? '08:00:00',
            isset($dados['filtros_personalizados']) ? json_encode($dados['filtros_personalizados']) : null,
            $dados['formato_relatorio'] ?? 'detalhado',
            $dados['criado_por'] ?? null
        ];

        $this->db->executar($sql, $parametros);
        return (int) $this->db->obterConexao()->lastInsertId();
    }

    /**
     * Atualiza configuração
     */
    public function atualizar(int $id, array $dados): bool
    {
        $campos = [];
        $parametros = [];

        $camposPermitidos = [
            'ativo', 'dia_envio_semanal', 'dia_envio_mensal', 'hora_envio',
            'formato_relatorio', 'atualizado_por'
        ];

        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $dados)) {
                $campos[] = "{$campo} = ?";
                $parametros[] = $dados[$campo];
            }
        }

        if (array_key_exists('filtros_personalizados', $dados)) {
            $campos[] = "filtros_personalizados = ?";
            $parametros[] = json_encode($dados['filtros_personalizados']);
        }

        if (empty($campos)) {
            return false;
        }

        $sql = "UPDATE frotas_abastecimentos_relatorios_configuracoes SET " . implode(', ', $campos) . " WHERE id = ?";
        $parametros[] = $id;

        $this->db->executar($sql, $parametros);
        return true;
    }

    /**
     * Ativa configuração
     */
    public function ativar(int $id): bool
    {
        $sql = "UPDATE frotas_abastecimentos_relatorios_configuracoes SET ativo = TRUE WHERE id = ?";
        $this->db->executar($sql, [$id]);
        return true;
    }

    /**
     * Desativa configuração
     */
    public function desativar(int $id): bool
    {
        $sql = "UPDATE frotas_abastecimentos_relatorios_configuracoes SET ativo = FALSE WHERE id = ?";
        $this->db->executar($sql, [$id]);
        return true;
    }

    /**
     * Deleta configuração
     */
    public function deletar(int $id): bool
    {
        $sql = "DELETE FROM frotas_abastecimentos_relatorios_configuracoes WHERE id = ?";
        $this->db->executar($sql, [$id]);
        return true;
    }
}
