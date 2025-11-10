<?php

namespace App\Models\Colaborador;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;

/**
 * Model para gerenciar roles (funções) de colaborador
 */
class ModelColaboradorRole
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca uma role por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT r.*, n.nome as nivel_nome
             FROM colaborador_roles r
             LEFT JOIN colaborador_niveis n ON r.nivel_id = n.id
             WHERE r.id = ?",
            [$id]
        );
    }

    /**
     * Busca uma role por código
     */
    public function buscarPorCodigo(string $codigo): ?array
    {
        return $this->db->buscarUm(
            "SELECT r.*, n.nome as nivel_nome
             FROM colaborador_roles r
             LEFT JOIN colaborador_niveis n ON r.nivel_id = n.id
             WHERE r.codigo = ?",
            [$codigo]
        );
    }

    /**
     * Lista todas as roles
     */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT r.*, n.nome as nivel_nome
                FROM colaborador_roles r
                LEFT JOIN colaborador_niveis n ON r.nivel_id = n.id
                WHERE 1=1";
        $parametros = [];

        if (isset($filtros['ativo'])) {
            $sql .= " AND r.ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        if (isset($filtros['nivel_id'])) {
            $sql .= " AND r.nivel_id = ?";
            $parametros[] = $filtros['nivel_id'];
        }

        $sql .= " ORDER BY r.nome ASC";

        return $this->db->buscarTodos($sql, $parametros);
    }

    /**
     * Cria uma nova role
     */
    public function criar(array $dados, ?int $usuarioId = null): int
    {
        $id = $this->db->inserir('colaborador_roles', [
            'nome' => $dados['nome'],
            'codigo' => $dados['codigo'],
            'descricao' => $dados['descricao'] ?? null,
            'nivel_id' => $dados['nivel_id'],
            'ativo' => $dados['ativo'] ?? 1,
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        $this->auditoria->registrarCriacao('colaborador_roles', $id, $dados, $usuarioId);

        return $id;
    }

    /**
     * Atualiza uma role
     */
    public function atualizar(int $id, array $dados, ?int $usuarioId = null): bool
    {
        $dadosAntigos = $this->buscarPorId($id);

        if (!$dadosAntigos) {
            return false;
        }

        $dadosAtualizacao = [];

        if (isset($dados['nome'])) {
            $dadosAtualizacao['nome'] = $dados['nome'];
        }

        if (isset($dados['codigo'])) {
            $dadosAtualizacao['codigo'] = $dados['codigo'];
        }

        if (isset($dados['descricao'])) {
            $dadosAtualizacao['descricao'] = $dados['descricao'];
        }

        if (isset($dados['nivel_id'])) {
            $dadosAtualizacao['nivel_id'] = $dados['nivel_id'];
        }

        if (isset($dados['ativo'])) {
            $dadosAtualizacao['ativo'] = $dados['ativo'];
        }

        if (!empty($dadosAtualizacao)) {
            $dadosAtualizacao['atualizado_em'] = date('Y-m-d H:i:s');
            $this->db->atualizar('colaborador_roles', $dadosAtualizacao, 'id = ?', [$id]);
            $this->auditoria->registrarAtualizacao('colaborador_roles', $id, $dadosAntigos, $dadosAtualizacao, $usuarioId);
        }

        return true;
    }

    /**
     * Deleta uma role (soft delete)
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        $dados = $this->buscarPorId($id);

        if (!$dados) {
            return false;
        }

        $resultado = $this->db->atualizar(
            'colaborador_roles',
            ['ativo' => 0, 'deletado_em' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        );

        if ($resultado > 0) {
            $this->auditoria->registrarExclusao('colaborador_roles', $id, $dados, $usuarioId);
        }

        return $resultado > 0;
    }

    /**
     * Busca roles por nível
     */
    public function buscarPorNivel(int $nivelId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM colaborador_roles WHERE nivel_id = ? AND ativo = 1 ORDER BY nome ASC",
            [$nivelId]
        );
    }

    /**
     * Conta o total de roles
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM colaborador_roles WHERE 1=1";
        $parametros = [];

        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        if (isset($filtros['nivel_id'])) {
            $sql .= " AND nivel_id = ?";
            $parametros[] = $filtros['nivel_id'];
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return (int) $resultado['total'];
    }

    /**
     * Verifica se o código já existe
     */
    public function codigoExiste(string $codigo, ?int $excluirId = null): bool
    {
        $sql = "SELECT id FROM colaborador_roles WHERE codigo = ?";
        $parametros = [$codigo];

        if ($excluirId !== null) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado !== null;
    }
}
