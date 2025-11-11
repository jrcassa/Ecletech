<?php

namespace App\Models\Colaborador;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;

/**
 * Model para gerenciar colaboradores
 */
class ModelColaborador
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca um colaborador por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT a.*, n.nome as nivel_nome, n.codigo as nivel_codigo
             FROM colaboradores a
             LEFT JOIN colaborador_niveis n ON a.nivel_id = n.id
             WHERE a.id = ?",
            [$id]
        );
    }

    /**
     * Busca um colaborador por email
     */
    public function buscarPorEmail(string $email): ?array
    {
        return $this->db->buscarUm(
            "SELECT a.*, n.nome as nivel_nome, n.codigo as nivel_codigo
             FROM colaboradores a
             LEFT JOIN colaborador_niveis n ON a.nivel_id = n.id
             WHERE a.email = ?",
            [$email]
        );
    }

    /**
     * Lista todos os colaboradores
     */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT a.*, n.nome as nivel_nome, n.codigo as nivel_codigo
                FROM colaboradores a
                LEFT JOIN colaborador_niveis n ON a.nivel_id = n.id
                WHERE 1=1";
        $parametros = [];

        if (isset($filtros['ativo'])) {
            $sql .= " AND a.ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        if (isset($filtros['nivel_id'])) {
            $sql .= " AND a.nivel_id = ?";
            $parametros[] = $filtros['nivel_id'];
        }

        if (isset($filtros['busca'])) {
            $sql .= " AND (a.nome LIKE ? OR a.email LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        $sql .= " ORDER BY a.nome ASC";

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
     * Cria um novo colaborador
     */
    public function criar(array $dados): int
    {
        $id = $this->db->inserir('colaboradores', [
            'nome' => $dados['nome'],
            'email' => $dados['email'],
            'senha' => $dados['senha'],
            'nivel_id' => $dados['nivel_id'] ?? 1,
            'ativo' => $dados['ativo'] ?? 1,
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        $this->auditoria->registrarCriacao('colaboradores', $id, $dados, $dados['colaborador_id'] ?? null);

        return $id;
    }

    /**
     * Atualiza um colaborador
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

        if (isset($dados['email'])) {
            $dadosAtualizacao['email'] = $dados['email'];
        }

        if (isset($dados['senha'])) {
            $dadosAtualizacao['senha'] = $dados['senha'];
        }

        if (isset($dados['nivel_id'])) {
            $dadosAtualizacao['nivel_id'] = $dados['nivel_id'];
        }

        if (isset($dados['ativo'])) {
            $dadosAtualizacao['ativo'] = $dados['ativo'];
        }

        if (!empty($dadosAtualizacao)) {
            $dadosAtualizacao['atualizado_em'] = date('Y-m-d H:i:s');
            $this->db->atualizar('colaboradores', $dadosAtualizacao, 'id = ?', [$id]);
            $this->auditoria->registrarAtualizacao('colaboradores', $id, $dadosAntigos, $dadosAtualizacao, $usuarioId);
        }

        return true;
    }

    /**
     * Deleta um colaborador (soft delete)
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        $dados = $this->buscarPorId($id);

        if (!$dados) {
            return false;
        }

        $resultado = $this->db->atualizar(
            'colaboradores',
            ['ativo' => 0, 'deletado_em' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        );

        if ($resultado > 0) {
            $this->auditoria->registrarExclusao('colaboradores', $id, $dados, $usuarioId);
        }

        return $resultado > 0;
    }

    /**
     * Conta o total de colaboradores
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM colaboradores WHERE 1=1";
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
     * Verifica se o email já existe
     */
    public function emailExiste(string $email, ?int $excluirId = null): bool
    {
        $sql = "SELECT id FROM colaboradores WHERE email = ?";
        $parametros = [$email];

        if ($excluirId !== null) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado !== null;
    }

    /**
     * Atualiza o último login
     */
    public function atualizarUltimoLogin(int $id): bool
    {
        return $this->db->atualizar(
            'colaboradores',
            ['ultimo_login' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        ) > 0;
    }

    /**
     * Busca colaboradores por nível
     */
    public function buscarPorNivel(int $nivelId): array
    {
        return $this->db->buscarTodos(
            "SELECT a.*, n.nome as nivel_nome, n.codigo as nivel_codigo
             FROM colaboradores a
             LEFT JOIN colaborador_niveis n ON a.nivel_id = n.id
             WHERE a.nivel_id = ? AND a.ativo = 1
             ORDER BY a.nome ASC",
            [$nivelId]
        );
    }

    /**
     * Obtém todas as permissões de um colaborador
     */
    public function obterPermissoes(int $id): array
    {
        $colaborador = $this->buscarPorId($id);

        if (!$colaborador) {
            return [];
        }

        $permissoes = $this->db->buscarTodos("
            SELECT DISTINCT p.id, p.nome, p.codigo, p.descricao, p.modulo
            FROM colaborador_permissions p
            INNER JOIN colaborador_role_permissions rp ON rp.permission_id = p.id
            INNER JOIN colaborador_roles r ON r.id = rp.role_id
            WHERE r.nivel_id = ? AND r.ativo = 1 AND p.ativo = 1
            ORDER BY p.modulo, p.nome
        ", [$colaborador['nivel_id']]);

        return $permissoes;
    }

    /**
     * Obtém os códigos das permissões de um colaborador
     */
    public function obterCodigosPermissoes(int $id): array
    {
        $permissoes = $this->obterPermissoes($id);
        return array_column($permissoes, 'codigo');
    }

    /**
     * Verifica se um colaborador tem uma permissão específica
     */
    public function temPermissao(int $id, string $permissao): bool
    {
        $codigos = $this->obterCodigosPermissoes($id);
        return in_array($permissao, $codigos);
    }

    /**
     * Verifica se um colaborador tem todas as permissões especificadas
     */
    public function temPermissoes(int $id, array $permissoes): bool
    {
        $codigos = $this->obterCodigosPermissoes($id);

        foreach ($permissoes as $permissao) {
            if (!in_array($permissao, $codigos)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verifica se um colaborador tem pelo menos uma das permissões
     */
    public function temAlgumaPermissao(int $id, array $permissoes): bool
    {
        $codigos = $this->obterCodigosPermissoes($id);

        foreach ($permissoes as $permissao) {
            if (in_array($permissao, $codigos)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtém as roles de um colaborador
     */
    public function obterRoles(int $id): array
    {
        $colaborador = $this->buscarPorId($id);

        if (!$colaborador) {
            return [];
        }

        return $this->db->buscarTodos("
            SELECT r.id, r.nome, r.codigo, r.descricao
            FROM colaborador_roles r
            WHERE r.nivel_id = ? AND r.ativo = 1
            ORDER BY r.nome
        ", [$colaborador['nivel_id']]);
    }

    /**
     * Obtém informações completas do colaborador incluindo permissões
     */
    public function buscarComPermissoes(int $id): ?array
    {
        $colaborador = $this->buscarPorId($id);

        if (!$colaborador) {
            return null;
        }

        $colaborador['permissoes'] = $this->obterPermissoes($id);
        $colaborador['roles'] = $this->obterRoles($id);

        return $colaborador;
    }
}
