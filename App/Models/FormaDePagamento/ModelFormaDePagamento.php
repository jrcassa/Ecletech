<?php

namespace App\Models\FormaDePagamento;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;
use App\Helpers\AuxiliarValidacao;

/**
 * Model para gerenciar formas de pagamento
 */
class ModelFormaDePagamento
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca uma forma de pagamento por ID (com JOIN de conta bancária)
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT
                fp.*,
                cb.nome as nome_conta_bancaria
             FROM forma_de_pagamento fp
             LEFT JOIN contas_bancarias cb ON fp.conta_bancaria_id = cb.id AND cb.deletado_em IS NULL
             WHERE fp.id = ? AND fp.deletado_em IS NULL",
            [$id]
        );
    }

    /**
     * Busca uma forma de pagamento por ID externo
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        return $this->db->buscarUm(
            "SELECT
                fp.*,
                cb.nome as nome_conta_bancaria
             FROM forma_de_pagamento fp
             LEFT JOIN contas_bancarias cb ON fp.conta_bancaria_id = cb.id AND cb.deletado_em IS NULL
             WHERE fp.external_id = ? AND fp.deletado_em IS NULL",
            [$externalId]
        );
    }

    /**
     * Busca uma forma de pagamento por nome
     */
    public function buscarPorNome(string $nome): ?array
    {
        return $this->db->buscarUm(
            "SELECT
                fp.*,
                cb.nome as nome_conta_bancaria
             FROM forma_de_pagamento fp
             LEFT JOIN contas_bancarias cb ON fp.conta_bancaria_id = cb.id AND cb.deletado_em IS NULL
             WHERE fp.nome = ? AND fp.deletado_em IS NULL",
            [$nome]
        );
    }

    /**
     * Lista todas as formas de pagamento (com JOIN de conta bancária)
     */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT
                    fp.*,
                    cb.nome as nome_conta_bancaria
                FROM forma_de_pagamento fp
                LEFT JOIN contas_bancarias cb ON fp.conta_bancaria_id = cb.id AND cb.deletado_em IS NULL
                WHERE fp.deletado_em IS NULL";
        $parametros = [];

        // Filtro por ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND fp.ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        // Filtro por conta bancária
        if (isset($filtros['conta_bancaria_id'])) {
            $sql .= " AND fp.conta_bancaria_id = ?";
            $parametros[] = $filtros['conta_bancaria_id'];
        }

        // Busca textual (nome)
        if (isset($filtros['busca'])) {
            $sql .= " AND fp.nome LIKE ?";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
        }

        // Ordenação (validada contra SQL Injection)
        $camposPermitidos = [
            'id', 'nome', 'maximo_parcelas', 'ativo', 'cadastrado_em', 'modificado_em'
        ];
        $ordenacaoValidada = AuxiliarValidacao::validarOrdenacao(
            $filtros['ordenacao'] ?? 'nome',
            $filtros['direcao'] ?? 'ASC',
            $camposPermitidos,
            'nome'
        );
        $sql .= " ORDER BY fp.{$ordenacaoValidada['campo']} {$ordenacaoValidada['direcao']}";

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
     * Conta o total de formas de pagamento
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM forma_de_pagamento WHERE deletado_em IS NULL";
        $parametros = [];

        // Filtro por ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        // Filtro por conta bancária
        if (isset($filtros['conta_bancaria_id'])) {
            $sql .= " AND conta_bancaria_id = ?";
            $parametros[] = $filtros['conta_bancaria_id'];
        }

        // Busca textual
        if (isset($filtros['busca'])) {
            $sql .= " AND nome LIKE ?";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return (int) $resultado['total'];
    }

    /**
     * Verifica se uma conta bancária existe e está ativa
     */
    public function contaBancariaExiste(int $contaBancariaId): bool
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM contas_bancarias WHERE id = ? AND deletado_em IS NULL",
            [$contaBancariaId]
        );
        return $resultado['total'] > 0;
    }

    /**
     * Cria uma nova forma de pagamento
     */
    public function criar(array $dados): int
    {
        // Validação: Verifica se a conta bancária existe
        if (!$this->contaBancariaExiste($dados['conta_bancaria_id'])) {
            throw new \Exception('Conta bancária não encontrada');
        }

        // Validação: maximo_parcelas >= 1
        if (isset($dados['maximo_parcelas']) && $dados['maximo_parcelas'] < 1) {
            throw new \Exception('Máximo de parcelas deve ser no mínimo 1');
        }

        $dadosInsert = [
            'nome' => $dados['nome'],
            'conta_bancaria_id' => $dados['conta_bancaria_id'],
            'maximo_parcelas' => $dados['maximo_parcelas'] ?? 1,
            'intervalo_parcelas' => $dados['intervalo_parcelas'] ?? 0,
            'intervalo_primeira_parcela' => $dados['intervalo_primeira_parcela'] ?? 0,
            'ativo' => $dados['ativo'] ?? 1,
            'cadastrado_em' => date('Y-m-d H:i:s')
        ];

        // Campos opcionais
        $camposOpcionais = ['external_id', 'observacoes'];

        foreach ($camposOpcionais as $campo) {
            if (isset($dados[$campo]) && $dados[$campo] !== '') {
                $dadosInsert[$campo] = $dados[$campo];
            }
        }

        $id = $this->db->inserir('forma_de_pagamento', $dadosInsert);

        // Registra auditoria
        $this->auditoria->registrar(
            'criar',
            'forma_de_pagamento',
            $id,
            null,
            $dadosInsert,
            $dados['colaborador_id'] ?? null
        );

        return $id;
    }

    /**
     * Atualiza uma forma de pagamento
     */
    public function atualizar(int $id, array $dados, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        // Validação: Se está alterando conta bancária, verifica se existe
        if (isset($dados['conta_bancaria_id']) && !$this->contaBancariaExiste($dados['conta_bancaria_id'])) {
            throw new \Exception('Conta bancária não encontrada');
        }

        // Validação: maximo_parcelas >= 1
        if (isset($dados['maximo_parcelas']) && $dados['maximo_parcelas'] < 1) {
            throw new \Exception('Máximo de parcelas deve ser no mínimo 1');
        }

        $dadosUpdate = [
            'modificado_em' => date('Y-m-d H:i:s')
        ];

        // Campos que podem ser atualizados
        $camposAtualizaveis = [
            'external_id', 'nome', 'conta_bancaria_id', 'maximo_parcelas',
            'intervalo_parcelas', 'intervalo_primeira_parcela', 'observacoes', 'ativo'
        ];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        $resultado = $this->db->atualizar('forma_de_pagamento', $dadosUpdate, 'id = ?', [$id]);

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'atualizar',
                'forma_de_pagamento',
                $id,
                $dadosAtuais,
                $dadosUpdate,
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Deleta uma forma de pagamento (soft delete)
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $resultado = $this->db->atualizar(
            'forma_de_pagamento',
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
                'forma_de_pagamento',
                $id,
                $dadosAtuais,
                ['deletado_em' => date('Y-m-d H:i:s')],
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Restaura uma forma de pagamento deletada
     */
    public function restaurar(int $id, ?int $usuarioId = null): bool
    {
        $resultado = $this->db->atualizar(
            'forma_de_pagamento',
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
                'forma_de_pagamento',
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
        $sql = "SELECT COUNT(*) as total FROM forma_de_pagamento WHERE external_id = ? AND deletado_em IS NULL";
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
        $sql = "SELECT COUNT(*) as total FROM forma_de_pagamento WHERE nome = ? AND deletado_em IS NULL";
        $parametros = [$nome];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Obtém estatísticas das formas de pagamento
     */
    public function obterEstatisticas(): array
    {
        $stats = [];

        // Total de formas de pagamento ativas
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM forma_de_pagamento WHERE ativo = 1 AND deletado_em IS NULL"
        );
        $stats['total_ativos'] = (int) $resultado['total'];

        // Total de formas de pagamento inativas
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM forma_de_pagamento WHERE ativo = 0 AND deletado_em IS NULL"
        );
        $stats['total_inativos'] = (int) $resultado['total'];

        // Total geral
        $stats['total_geral'] = $stats['total_ativos'] + $stats['total_inativos'];

        return $stats;
    }
}
