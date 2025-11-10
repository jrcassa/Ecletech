<?php

namespace App\Models;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;

/**
 * Model para gerenciar administradores
 */
class ModelAdministrador
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca um administrador por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT a.*, n.nome as nivel_nome, n.codigo as nivel_codigo
             FROM administradores a
             LEFT JOIN administrador_niveis n ON a.nivel_id = n.id
             WHERE a.id = ?",
            [$id]
        );
    }

    /**
     * Busca um administrador por email
     */
    public function buscarPorEmail(string $email): ?array
    {
        return $this->db->buscarUm(
            "SELECT a.*, n.nome as nivel_nome, n.codigo as nivel_codigo
             FROM administradores a
             LEFT JOIN administrador_niveis n ON a.nivel_id = n.id
             WHERE a.email = ?",
            [$email]
        );
    }

    /**
     * Lista todos os administradores
     */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT a.*, n.nome as nivel_nome, n.codigo as nivel_codigo
                FROM administradores a
                LEFT JOIN administrador_niveis n ON a.nivel_id = n.id
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
     * Cria um novo administrador
     */
    public function criar(array $dados): int
    {
        $id = $this->db->inserir('administradores', [
            'nome' => $dados['nome'],
            'email' => $dados['email'],
            'senha' => $dados['senha'],
            'nivel_id' => $dados['nivel_id'] ?? 1,
            'ativo' => $dados['ativo'] ?? 1,
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        $this->auditoria->registrarCriacao('administradores', $id, $dados, $dados['usuario_id'] ?? null);

        return $id;
    }

    /**
     * Atualiza um administrador
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
            $this->db->atualizar('administradores', $dadosAtualizacao, 'id = ?', [$id]);
            $this->auditoria->registrarAtualizacao('administradores', $id, $dadosAntigos, $dadosAtualizacao, $usuarioId);
        }

        return true;
    }

    /**
     * Deleta um administrador (soft delete)
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        $dados = $this->buscarPorId($id);

        if (!$dados) {
            return false;
        }

        $resultado = $this->db->atualizar(
            'administradores',
            ['ativo' => 0, 'deletado_em' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        );

        if ($resultado > 0) {
            $this->auditoria->registrarExclusao('administradores', $id, $dados, $usuarioId);
        }

        return $resultado > 0;
    }

    /**
     * Conta o total de administradores
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM administradores WHERE 1=1";
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
        $sql = "SELECT id FROM administradores WHERE email = ?";
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
            'administradores',
            ['ultimo_login' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        ) > 0;
    }

    /**
     * Busca administradores por nível
     */
    public function buscarPorNivel(int $nivelId): array
    {
        return $this->db->buscarTodos(
            "SELECT a.*, n.nome as nivel_nome, n.codigo as nivel_codigo
             FROM administradores a
             LEFT JOIN administrador_niveis n ON a.nivel_id = n.id
             WHERE a.nivel_id = ? AND a.ativo = 1
             ORDER BY a.nome ASC",
            [$nivelId]
        );
    }
}
