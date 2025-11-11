<?php

namespace App\Models\TipoEndereco;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;
use App\Helpers\AuxiliarValidacao;

/**
 * Model para gerenciar tipos de endereços
 */
class ModelTipoEndereco
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca um tipo de endereço por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM tipos_enderecos WHERE id = ? AND deletado_em IS NULL",
            [$id]
        );
    }

    /**
     * Busca um tipo de endereço por external_id
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM tipos_enderecos WHERE external_id = ? AND deletado_em IS NULL",
            [$externalId]
        );
    }

    /**
     * Busca um tipo de endereço por nome
     */
    public function buscarPorNome(string $nome): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM tipos_enderecos WHERE nome = ? AND deletado_em IS NULL",
            [$nome]
        );
    }

    /**
     * Lista todos os tipos de endereços
     */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT * FROM tipos_enderecos WHERE deletado_em IS NULL";
        $parametros = [];

        // Filtro por ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        // Busca textual (nome, external_id)
        if (isset($filtros['busca'])) {
            $sql .= " AND (nome LIKE ? OR external_id LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        // Ordenação (validada contra SQL Injection)
        $camposPermitidos = ['id', 'nome', 'external_id', 'ativo', 'criado_em', 'atualizado_em'];
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
     * Conta o total de tipos de endereços
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM tipos_enderecos WHERE deletado_em IS NULL";
        $parametros = [];

        // Filtro por ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        // Busca textual
        if (isset($filtros['busca'])) {
            $sql .= " AND (nome LIKE ? OR external_id LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return (int) $resultado['total'];
    }

    /**
     * Cria um novo tipo de endereço
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'nome' => $dados['nome'],
            'ativo' => $dados['ativo'] ?? 1,
            'criado_em' => date('Y-m-d H:i:s')
        ];

        // Campo opcional external_id
        if (isset($dados['external_id']) && !empty($dados['external_id'])) {
            $dadosInsert['external_id'] = $dados['external_id'];
        }

        $id = $this->db->inserir('tipos_enderecos', $dadosInsert);

        // Registra auditoria
        $this->auditoria->registrar(
            'criar',
            'tipo_endereco',
            $id,
            null,
            $dadosInsert,
            $dados['colaborador_id'] ?? null
        );

        return $id;
    }

    /**
     * Atualiza um tipo de endereço
     */
    public function atualizar(int $id, array $dados, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $dadosUpdate = [
            'atualizado_em' => date('Y-m-d H:i:s')
        ];

        // Campos que podem ser atualizados
        $camposAtualizaveis = ['external_id', 'nome', 'ativo'];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        $resultado = $this->db->atualizar('tipos_enderecos', $dadosUpdate, 'id = ?', [$id]);

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'atualizar',
                'tipo_endereco',
                $id,
                $dadosAtuais,
                $dadosUpdate,
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Deleta um tipo de endereço (soft delete)
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $resultado = $this->db->atualizar(
            'tipos_enderecos',
            [
                'deletado_em' => date('Y-m-d H:i:s'),
                'ativo' => 0
            ],
            'id = ?',
            [$id]
        );

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'deletar',
                'tipo_endereco',
                $id,
                $dadosAtuais,
                ['deletado_em' => date('Y-m-d H:i:s')],
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Restaura um tipo de endereço deletado
     */
    public function restaurar(int $id, ?int $usuarioId = null): bool
    {
        $resultado = $this->db->atualizar(
            'tipos_enderecos',
            [
                'deletado_em' => null,
                'ativo' => 1
            ],
            'id = ?',
            [$id]
        );

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'restaurar',
                'tipo_endereco',
                $id,
                ['deletado_em' => date('Y-m-d H:i:s')],
                ['deletado_em' => null],
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Verifica se um nome já existe
     */
    public function nomeExiste(string $nome, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM tipos_enderecos WHERE nome = ? AND deletado_em IS NULL";
        $parametros = [$nome];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Verifica se um external_id já existe
     */
    public function externalIdExiste(string $externalId, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM tipos_enderecos WHERE external_id = ? AND deletado_em IS NULL";
        $parametros = [$externalId];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Obtém estatísticas de tipos de endereços
     */
    public function obterEstatisticas(): array
    {
        $stats = [];

        // Total de tipos ativos
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM tipos_enderecos WHERE ativo = 1 AND deletado_em IS NULL"
        );
        $stats['total_ativos'] = (int) $resultado['total'];

        // Total de tipos inativos
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM tipos_enderecos WHERE ativo = 0 AND deletado_em IS NULL"
        );
        $stats['total_inativos'] = (int) $resultado['total'];

        // Total geral
        $stats['total_geral'] = $stats['total_ativos'] + $stats['total_inativos'];

        return $stats;
    }
}
