<?php

namespace App\Models\Servico;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;
use App\Helpers\AuxiliarValidacao;

/**
 * Model para gerenciar serviços
 */
class ModelServico
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca um serviço por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM servicos WHERE id = ? AND deletado_em IS NULL",
            [$id]
        );
    }

    /**
     * Busca serviço por código
     */
    public function buscarPorCodigo(string $codigo): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM servicos WHERE codigo = ? AND deletado_em IS NULL",
            [$codigo]
        );
    }

    /**
     * Busca serviço por external_id
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM servicos WHERE external_id = ? AND deletado_em IS NULL",
            [$externalId]
        );
    }

    /**
     * Lista serviços com filtros e paginação
     */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT * FROM servicos WHERE deletado_em IS NULL";
        $parametros = [];

        // Filtro por busca (nome ou código)
        if (isset($filtros['busca']) && !empty($filtros['busca'])) {
            $sql .= " AND (nome LIKE ? OR codigo LIKE ?)";
            $parametros[] = '%' . $filtros['busca'] . '%';
            $parametros[] = '%' . $filtros['busca'] . '%';
        }

        // Filtro por ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = (bool) $filtros['ativo'];
        }

        // Filtro por external_id
        if (isset($filtros['external_id'])) {
            $sql .= " AND external_id = ?";
            $parametros[] = $filtros['external_id'];
        }

        // Ordenação
        $camposPermitidos = ['id', 'codigo', 'nome', 'valor_venda', 'ativo', 'criado_em'];
        $ordenacaoValidada = AuxiliarValidacao::validarOrdenacao(
            $filtros['ordenacao'] ?? 'nome',
            $filtros['direcao'] ?? 'ASC',
            $camposPermitidos,
            'nome'
        );
        $sql .= " ORDER BY {$ordenacaoValidada['campo']} {$ordenacaoValidada['direcao']}";

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
     * Conta total de serviços
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM servicos WHERE deletado_em IS NULL";
        $parametros = [];

        // Aplicar os mesmos filtros do listar
        if (isset($filtros['busca']) && !empty($filtros['busca'])) {
            $sql .= " AND (nome LIKE ? OR codigo LIKE ?)";
            $parametros[] = '%' . $filtros['busca'] . '%';
            $parametros[] = '%' . $filtros['busca'] . '%';
        }

        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = (bool) $filtros['ativo'];
        }

        if (isset($filtros['external_id'])) {
            $sql .= " AND external_id = ?";
            $parametros[] = $filtros['external_id'];
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return (int) $resultado['total'];
    }

    /**
     * Lista serviços ativos (para uso em selects)
     */
    public function listarAtivos(): array
    {
        $sql = "
            SELECT id, codigo, nome, valor_venda
            FROM servicos
            WHERE ativo = TRUE AND deletado_em IS NULL
            ORDER BY nome ASC
        ";

        return $this->db->buscarTodos($sql);
    }

    /**
     * Cria novo serviço
     */
    public function criar(array $dados): int
    {
        $sql = "
            INSERT INTO servicos (
                external_id, codigo, external_codigo, nome, valor_venda,
                observacoes, ativo, criado_por, criado_em
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        $parametros = [
            $dados['external_id'] ?? null,
            $dados['codigo'],
            $dados['external_codigo'] ?? null,
            $dados['nome'],
            $dados['valor_venda'] ?? 0.00,
            $dados['observacoes'] ?? null,
            $dados['ativo'] ?? true,
            $dados['criado_por'] ?? null
        ];

        $this->db->executar($sql, $parametros);
        $id = (int) $this->db->obterConexao()->lastInsertId();

        // Registrar auditoria
        $this->auditoria->registrar('criar', 'servicos', $id, null, [
            'codigo' => $dados['codigo'],
            'nome' => $dados['nome']
        ]);

        return $id;
    }

    /**
     * Atualiza serviço
     */
    public function atualizar(int $id, array $dados): bool
    {
        $campos = [];
        $parametros = [];

        // Campos atualizáveis
        $camposPermitidos = [
            'external_id', 'codigo', 'external_codigo', 'nome',
            'valor_venda', 'observacoes', 'ativo', 'atualizado_por'
        ];

        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $dados)) {
                $campos[] = "{$campo} = ?";
                $parametros[] = $dados[$campo];
            }
        }

        if (empty($campos)) {
            return false;
        }

        $sql = "UPDATE servicos SET " . implode(', ', $campos) . " WHERE id = ?";
        $parametros[] = $id;

        $this->db->executar($sql, $parametros);

        // Registrar auditoria
        $this->auditoria->registrar('atualizar', 'servicos', $id, null, $dados);

        return true;
    }

    /**
     * Ativa/Desativa serviço
     */
    public function alterarStatus(int $id, bool $ativo): bool
    {
        $sql = "UPDATE servicos SET ativo = ?, atualizado_em = NOW() WHERE id = ?";
        $this->db->executar($sql, [$ativo, $id]);

        // Registrar auditoria
        $this->auditoria->registrar('alterarStatus', 'servicos', $id, null, ['ativo' => $ativo]);

        return true;
    }

    /**
     * Soft delete
     */
    public function deletar(int $id): bool
    {
        $sql = "UPDATE servicos SET deletado_em = NOW() WHERE id = ?";
        $this->db->executar($sql, [$id]);

        // Registrar auditoria
        $this->auditoria->registrar('deletar', 'servicos', $id, null, []);

        return true;
    }

    /**
     * Restaurar soft delete
     */
    public function restaurar(int $id): bool
    {
        $sql = "UPDATE servicos SET deletado_em = NULL WHERE id = ?";
        $this->db->executar($sql, [$id]);

        // Registrar auditoria
        $this->auditoria->registrar('restaurar', 'servicos', $id, null, []);

        return true;
    }

    /**
     * Verifica se código já existe (para validação)
     */
    public function codigoExiste(string $codigo, ?int $ignorarId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM servicos WHERE codigo = ? AND deletado_em IS NULL";
        $parametros = [$codigo];

        if ($ignorarId !== null) {
            $sql .= " AND id != ?";
            $parametros[] = $ignorarId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return (int) $resultado['total'] > 0;
    }

    /**
     * Obter estatísticas gerais
     */
    public function obterEstatisticas(): array
    {
        $sql = "
            SELECT
                COUNT(*) as total_servicos,
                SUM(CASE WHEN ativo = TRUE THEN 1 ELSE 0 END) as total_ativos,
                SUM(CASE WHEN ativo = FALSE THEN 1 ELSE 0 END) as total_inativos,
                COALESCE(AVG(valor_venda), 0) as valor_medio,
                COALESCE(MIN(valor_venda), 0) as valor_minimo,
                COALESCE(MAX(valor_venda), 0) as valor_maximo
            FROM servicos
            WHERE deletado_em IS NULL
        ";

        return $this->db->buscarUm($sql) ?: [];
    }
}
