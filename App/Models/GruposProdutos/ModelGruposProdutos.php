<?php

namespace App\Models\GruposProdutos;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;
use App\Helpers\AuxiliarValidacao;

/**
 * Model para gerenciar grupos de produtos
 */
class ModelGruposProdutos
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca um grupo de produtos por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM grupos_produtos WHERE id = ? AND deletado_em IS NULL",
            [$id]
        );
    }

    /**
     * Busca um grupo de produtos por ID externo
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM grupos_produtos WHERE external_id = ? AND deletado_em IS NULL",
            [$externalId]
        );
    }

    /**
     * Busca um grupo de produtos por nome
     */
    public function buscarPorNome(string $nome): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM grupos_produtos WHERE nome = ? AND deletado_em IS NULL",
            [$nome]
        );
    }

    /**
     * Lista todos os grupos de produtos
     */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT * FROM grupos_produtos WHERE deletado_em IS NULL";
        $parametros = [];

        // Filtro por ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        // Busca textual (nome ou descrição)
        if (isset($filtros['busca'])) {
            $sql .= " AND (nome LIKE ? OR descricao LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        // Ordenação (validada contra SQL Injection)
        $camposPermitidos = [
            'id', 'nome', 'ativo', 'cadastrado_em', 'modificado_em'
        ];
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
     * Conta o total de grupos de produtos
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM grupos_produtos WHERE deletado_em IS NULL";
        $parametros = [];

        // Filtro por ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        // Busca textual
        if (isset($filtros['busca'])) {
            $sql .= " AND (nome LIKE ? OR descricao LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return (int) $resultado['total'];
    }

    /**
     * Cria um novo grupo de produtos
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'nome' => $dados['nome'],
            'ativo' => $dados['ativo'] ?? 1,
            'cadastrado_em' => date('Y-m-d H:i:s')
        ];

        // Campos opcionais
        $camposOpcionais = ['external_id', 'descricao'];

        foreach ($camposOpcionais as $campo) {
            if (isset($dados[$campo]) && $dados[$campo] !== '') {
                $dadosInsert[$campo] = $dados[$campo];
            }
        }

        $id = $this->db->inserir('grupos_produtos', $dadosInsert);

        // Registra auditoria
        $this->auditoria->registrar(
            'criar',
            'grupos_produtos',
            $id,
            null,
            $dadosInsert,
            $dados['colaborador_id'] ?? null
        );

        return $id;
    }

    /**
     * Atualiza um grupo de produtos
     */
    public function atualizar(int $id, array $dados, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $dadosUpdate = [
            'modificado_em' => date('Y-m-d H:i:s')
        ];

        // Campos que podem ser atualizados
        $camposAtualizaveis = ['external_id', 'nome', 'descricao', 'ativo'];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        $resultado = $this->db->atualizar('grupos_produtos', $dadosUpdate, 'id = ?', [$id]);

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'atualizar',
                'grupos_produtos',
                $id,
                $dadosAtuais,
                $dadosUpdate,
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Deleta um grupo de produtos (soft delete)
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $resultado = $this->db->atualizar(
            'grupos_produtos',
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
                'grupos_produtos',
                $id,
                $dadosAtuais,
                ['deletado_em' => date('Y-m-d H:i:s')],
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Restaura um grupo de produtos deletado
     */
    public function restaurar(int $id, ?int $usuarioId = null): bool
    {
        $resultado = $this->db->atualizar(
            'grupos_produtos',
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
                'grupos_produtos',
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
        $sql = "SELECT COUNT(*) as total FROM grupos_produtos WHERE nome = ? AND deletado_em IS NULL";
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
        $sql = "SELECT COUNT(*) as total FROM grupos_produtos WHERE external_id = ? AND deletado_em IS NULL";
        $parametros = [$externalId];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Obtém estatísticas dos grupos de produtos
     */
    public function obterEstatisticas(): array
    {
        $stats = [];

        // Total de grupos ativos
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM grupos_produtos WHERE ativo = 1 AND deletado_em IS NULL"
        );
        $stats['total_ativos'] = (int) $resultado['total'];

        // Total de grupos inativos
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM grupos_produtos WHERE ativo = 0 AND deletado_em IS NULL"
        );
        $stats['total_inativos'] = (int) $resultado['total'];

        // Total geral
        $stats['total_geral'] = $stats['total_ativos'] + $stats['total_inativos'];

        return $stats;
    }
}
