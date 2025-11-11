<?php

namespace App\Models\Estado;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;

/**
 * Model para gerenciar estados
 */
class ModelEstado
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca um estado por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM estados WHERE id = ? AND deletado_em IS NULL",
            [$id]
        );
    }

    /**
     * Busca um estado por código
     */
    public function buscarPorCodigo(string $codigo): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM estados WHERE codigo = ? AND deletado_em IS NULL",
            [$codigo]
        );
    }

    /**
     * Busca um estado por sigla
     */
    public function buscarPorSigla(string $sigla): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM estados WHERE sigla = ? AND deletado_em IS NULL",
            [$sigla]
        );
    }

    /**
     * Busca um estado por external_id
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM estados WHERE external_id = ? AND deletado_em IS NULL",
            [$externalId]
        );
    }

    /**
     * Lista todos os estados
     */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT * FROM estados WHERE deletado_em IS NULL";
        $parametros = [];

        // Filtro por ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        // Busca textual (nome, código, sigla, external_id)
        if (isset($filtros['busca'])) {
            $sql .= " AND (nome LIKE ? OR codigo LIKE ? OR sigla LIKE ? OR external_id LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        // Ordenação
        $ordenacao = $filtros['ordenacao'] ?? 'nome';
        $direcao = $filtros['direcao'] ?? 'ASC';
        $sql .= " ORDER BY {$ordenacao} {$direcao}";

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
     * Conta o total de estados
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM estados WHERE deletado_em IS NULL";
        $parametros = [];

        // Filtro por ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        // Busca textual
        if (isset($filtros['busca'])) {
            $sql .= " AND (nome LIKE ? OR codigo LIKE ? OR sigla LIKE ? OR external_id LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return (int) $resultado['total'];
    }

    /**
     * Cria um novo estado
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'codigo' => $dados['codigo'],
            'nome' => $dados['nome'],
            'sigla' => $dados['sigla'],
            'ativo' => $dados['ativo'] ?? 1,
            'criado_em' => date('Y-m-d H:i:s')
        ];

        // Campo opcional external_id
        if (isset($dados['external_id']) && !empty($dados['external_id'])) {
            $dadosInsert['external_id'] = $dados['external_id'];
        }

        $id = $this->db->inserir('estados', $dadosInsert);

        // Registra auditoria
        $this->auditoria->registrar(
            'criar',
            'estado',
            $id,
            null,
            $dadosInsert,
            $dados['colaborador_id'] ?? null
        );

        return $id;
    }

    /**
     * Atualiza um estado
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
        $camposAtualizaveis = ['external_id', 'codigo', 'nome', 'sigla', 'ativo'];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        $resultado = $this->db->atualizar('estados', $dadosUpdate, 'id = ?', [$id]);

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'atualizar',
                'estado',
                $id,
                $dadosAtuais,
                $dadosUpdate,
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Deleta um estado (soft delete)
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $resultado = $this->db->atualizar(
            'estados',
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
                'estado',
                $id,
                $dadosAtuais,
                ['deletado_em' => date('Y-m-d H:i:s')],
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Restaura um estado deletado
     */
    public function restaurar(int $id, ?int $usuarioId = null): bool
    {
        $resultado = $this->db->atualizar(
            'estados',
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
                'estado',
                $id,
                ['deletado_em' => date('Y-m-d H:i:s')],
                ['deletado_em' => null],
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Verifica se um código já existe
     */
    public function codigoExiste(string $codigo, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM estados WHERE codigo = ? AND deletado_em IS NULL";
        $parametros = [$codigo];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Verifica se uma sigla já existe
     */
    public function siglaExiste(string $sigla, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM estados WHERE sigla = ? AND deletado_em IS NULL";
        $parametros = [$sigla];

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
        $sql = "SELECT COUNT(*) as total FROM estados WHERE external_id = ? AND deletado_em IS NULL";
        $parametros = [$externalId];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Obtém estatísticas de estados
     */
    public function obterEstatisticas(): array
    {
        $stats = [];

        // Total de estados ativos
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM estados WHERE ativo = 1 AND deletado_em IS NULL"
        );
        $stats['total_ativas'] = (int) $resultado['total'];

        // Total de estados inativos
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM estados WHERE ativo = 0 AND deletado_em IS NULL"
        );
        $stats['total_inativas'] = (int) $resultado['total'];

        // Total geral
        $stats['total_geral'] = $stats['total_ativas'] + $stats['total_inativas'];

        return $stats;
    }
}
