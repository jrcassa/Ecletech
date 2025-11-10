<?php

namespace App\Models\Colaborador;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;

/**
 * Model para gerenciar níveis de colaborador
 */
class ModelColaboradorNivel
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca um nível por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM colaborador_niveis WHERE id = ?",
            [$id]
        );
    }

    /**
     * Busca um nível por código
     */
    public function buscarPorCodigo(string $codigo): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM colaborador_niveis WHERE codigo = ?",
            [$codigo]
        );
    }

    /**
     * Lista todos os níveis
     */
    public function listar(bool $apenasAtivos = true): array
    {
        $sql = "SELECT * FROM colaborador_niveis";

        if ($apenasAtivos) {
            $sql .= " WHERE ativo = 1";
        }

        $sql .= " ORDER BY ordem ASC, nome ASC";

        return $this->db->buscarTodos($sql);
    }

    /**
     * Cria um novo nível
     */
    public function criar(array $dados, ?int $usuarioId = null): int
    {
        $id = $this->db->inserir('colaborador_niveis', [
            'nome' => $dados['nome'],
            'codigo' => $dados['codigo'],
            'descricao' => $dados['descricao'] ?? null,
            'ordem' => $dados['ordem'] ?? 0,
            'ativo' => $dados['ativo'] ?? 1,
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        $this->auditoria->registrarCriacao('colaborador_niveis', $id, $dados, $usuarioId);

        return $id;
    }

    /**
     * Atualiza um nível
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

        if (isset($dados['ordem'])) {
            $dadosAtualizacao['ordem'] = $dados['ordem'];
        }

        if (isset($dados['ativo'])) {
            $dadosAtualizacao['ativo'] = $dados['ativo'];
        }

        if (!empty($dadosAtualizacao)) {
            $dadosAtualizacao['atualizado_em'] = date('Y-m-d H:i:s');
            $this->db->atualizar('colaborador_niveis', $dadosAtualizacao, 'id = ?', [$id]);
            $this->auditoria->registrarAtualizacao('colaborador_niveis', $id, $dadosAntigos, $dadosAtualizacao, $usuarioId);
        }

        return true;
    }

    /**
     * Deleta um nível (soft delete)
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        $dados = $this->buscarPorId($id);

        if (!$dados) {
            return false;
        }

        $resultado = $this->db->atualizar(
            'colaborador_niveis',
            ['ativo' => 0, 'deletado_em' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        );

        if ($resultado > 0) {
            $this->auditoria->registrarExclusao('colaborador_niveis', $id, $dados, $usuarioId);
        }

        return $resultado > 0;
    }

    /**
     * Conta o total de níveis
     */
    public function contar(bool $apenasAtivos = true): int
    {
        $sql = "SELECT COUNT(*) as total FROM colaborador_niveis";

        if ($apenasAtivos) {
            $sql .= " WHERE ativo = 1";
        }

        $resultado = $this->db->buscarUm($sql);
        return (int) $resultado['total'];
    }

    /**
     * Verifica se o código já existe
     */
    public function codigoExiste(string $codigo, ?int $excluirId = null): bool
    {
        $sql = "SELECT id FROM colaborador_niveis WHERE codigo = ?";
        $parametros = [$codigo];

        if ($excluirId !== null) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado !== null;
    }
}
