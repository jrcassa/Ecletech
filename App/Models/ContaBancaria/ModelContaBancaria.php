<?php

namespace App\Models\ContaBancaria;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;
use App\Helpers\AuxiliarValidacao;

/**
 * Model para gerenciar contas bancárias
 */
class ModelContaBancaria
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca uma conta bancária por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM contas_bancarias WHERE id = ? AND deletado_em IS NULL",
            [$id]
        );
    }

    /**
     * Busca uma conta bancária por ID externo
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM contas_bancarias WHERE external_id = ? AND deletado_em IS NULL",
            [$externalId]
        );
    }

    /**
     * Busca uma conta bancária por nome
     */
    public function buscarPorNome(string $nome): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM contas_bancarias WHERE nome = ? AND deletado_em IS NULL",
            [$nome]
        );
    }

    /**
     * Lista todas as contas bancárias
     */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT * FROM contas_bancarias WHERE deletado_em IS NULL";
        $parametros = [];

        // Filtro por ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        // Filtro por tipo de conta
        if (isset($filtros['tipo_conta'])) {
            $sql .= " AND tipo_conta = ?";
            $parametros[] = $filtros['tipo_conta'];
        }

        // Filtro por banco
        if (isset($filtros['banco_codigo'])) {
            $sql .= " AND banco_codigo = ?";
            $parametros[] = $filtros['banco_codigo'];
        }

        // Busca textual (nome, banco, conta)
        if (isset($filtros['busca'])) {
            $sql .= " AND (nome LIKE ? OR banco_nome LIKE ? OR banco_codigo LIKE ? OR conta LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        // Ordenação (validada contra SQL Injection)
        $camposPermitidos = [
            'id', 'nome', 'banco_codigo', 'banco_nome', 'tipo_conta',
            'saldo_inicial', 'ativo', 'cadastrado_em', 'modificado_em'
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
     * Conta o total de contas bancárias
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM contas_bancarias WHERE deletado_em IS NULL";
        $parametros = [];

        // Filtro por ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        // Filtro por tipo de conta
        if (isset($filtros['tipo_conta'])) {
            $sql .= " AND tipo_conta = ?";
            $parametros[] = $filtros['tipo_conta'];
        }

        // Filtro por banco
        if (isset($filtros['banco_codigo'])) {
            $sql .= " AND banco_codigo = ?";
            $parametros[] = $filtros['banco_codigo'];
        }

        // Busca textual
        if (isset($filtros['busca'])) {
            $sql .= " AND (nome LIKE ? OR banco_nome LIKE ? OR banco_codigo LIKE ? OR conta LIKE ?)";
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
     * Cria uma nova conta bancária
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'nome' => $dados['nome'],
            'ativo' => $dados['ativo'] ?? 1,
            'cadastrado_em' => date('Y-m-d H:i:s')
        ];

        // Campos opcionais
        $camposOpcionais = [
            'external_id', 'banco_codigo', 'banco_nome', 'agencia', 'agencia_dv',
            'conta', 'conta_dv', 'tipo_conta', 'saldo_inicial', 'observacoes'
        ];

        foreach ($camposOpcionais as $campo) {
            if (isset($dados[$campo]) && $dados[$campo] !== '') {
                $dadosInsert[$campo] = $dados[$campo];
            }
        }

        $id = $this->db->inserir('contas_bancarias', $dadosInsert);

        // Registra auditoria
        $this->auditoria->registrar(
            'criar',
            'conta_bancaria',
            $id,
            null,
            $dadosInsert,
            $dados['colaborador_id'] ?? null
        );

        return $id;
    }

    /**
     * Atualiza uma conta bancária
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
        $camposAtualizaveis = [
            'external_id', 'nome', 'banco_codigo', 'banco_nome', 'agencia', 'agencia_dv',
            'conta', 'conta_dv', 'tipo_conta', 'saldo_inicial', 'observacoes', 'ativo'
        ];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        $resultado = $this->db->atualizar('contas_bancarias', $dadosUpdate, 'id = ?', [$id]);

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'atualizar',
                'conta_bancaria',
                $id,
                $dadosAtuais,
                $dadosUpdate,
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Deleta uma conta bancária (soft delete)
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $resultado = $this->db->atualizar(
            'contas_bancarias',
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
                'conta_bancaria',
                $id,
                $dadosAtuais,
                ['deletado_em' => date('Y-m-d H:i:s')],
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Restaura uma conta bancária deletada
     */
    public function restaurar(int $id, ?int $usuarioId = null): bool
    {
        $resultado = $this->db->atualizar(
            'contas_bancarias',
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
                'conta_bancaria',
                $id,
                ['deletado_em' => date('Y-m-d H:i:s')],
                ['deletado_em' => null],
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Verifica se um external_id já existe
     */
    public function externalIdExiste(string $externalId, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM contas_bancarias WHERE external_id = ? AND deletado_em IS NULL";
        $parametros = [$externalId];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Verifica se um nome já existe
     */
    public function nomeExiste(string $nome, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM contas_bancarias WHERE nome = ? AND deletado_em IS NULL";
        $parametros = [$nome];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Obtém estatísticas das contas bancárias
     */
    public function obterEstatisticas(): array
    {
        $stats = [];

        // Total de contas ativas
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM contas_bancarias WHERE ativo = 1 AND deletado_em IS NULL"
        );
        $stats['total_ativas'] = (int) $resultado['total'];

        // Total por tipo de conta
        $resultado = $this->db->buscarTodos(
            "SELECT tipo_conta, COUNT(*) as total FROM contas_bancarias WHERE ativo = 1 AND deletado_em IS NULL GROUP BY tipo_conta"
        );
        $stats['por_tipo_conta'] = $resultado;

        // Saldo total
        $resultado = $this->db->buscarUm(
            "SELECT SUM(saldo_inicial) as saldo_total FROM contas_bancarias WHERE ativo = 1 AND deletado_em IS NULL"
        );
        $stats['saldo_total'] = (float) ($resultado['saldo_total'] ?? 0);

        // Total por banco
        $resultado = $this->db->buscarTodos(
            "SELECT banco_nome, banco_codigo, COUNT(*) as total FROM contas_bancarias WHERE ativo = 1 AND deletado_em IS NULL GROUP BY banco_codigo, banco_nome ORDER BY total DESC LIMIT 10"
        );
        $stats['por_banco'] = $resultado;

        return $stats;
    }
}
