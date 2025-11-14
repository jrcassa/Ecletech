<?php

namespace App\Models\Recebimento;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;
use App\Helpers\AuxiliarValidacao;

/**
 * Model para gerenciar recebimentos (contas a receber)
 */
class ModelRecebimento
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca um recebimento por ID (com JOINs de entidades relacionadas)
     */
    public function buscarPorId(int $id): ?array
    {
        $recebimento = $this->db->buscarUm(
            "SELECT
                r.*,
                pc.nome as nome_plano_conta,
                cc.nome as nome_centro_custo,
                cb.nome as nome_conta_bancaria,
                fp.nome as nome_forma_pagamento,
                u.nome as nome_usuario,
                l.nome as nome_loja
             FROM recebimentos r
             LEFT JOIN plano_de_contas pc ON r.plano_contas_id = pc.id AND pc.deletado_em IS NULL
             LEFT JOIN centro_de_custo cc ON r.centro_custo_id = cc.id AND cc.deletado_em IS NULL
             LEFT JOIN contas_bancarias cb ON r.conta_bancaria_id = cb.id AND cb.deletado_em IS NULL
             LEFT JOIN forma_de_pagamento fp ON r.forma_pagamento_id = fp.id AND fp.deletado_em IS NULL
             LEFT JOIN colaboradores u ON r.usuario_id = u.id AND u.deletado_em IS NULL
             LEFT JOIN lojas l ON r.loja_id = l.id AND l.deletado_em IS NULL
             WHERE r.id = ? AND r.deletado_em IS NULL",
            [$id]
        );

        if (!$recebimento) {
            return null;
        }

        // Busca nome da entidade relacionada
        $recebimento = $this->buscarNomeEntidade($recebimento);

        return $recebimento;
    }

    /**
     * Busca um recebimento por ID externo
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        $resultado = $this->db->buscarUm(
            "SELECT id FROM recebimentos WHERE external_id = ? AND deletado_em IS NULL",
            [$externalId]
        );

        if (!$resultado) {
            return null;
        }

        return $this->buscarPorId($resultado['id']);
    }

    /**
     * Busca nome da entidade relacionada (Cliente, Fornecedor, Transportadora)
     */
    private function buscarNomeEntidade(array $recebimento): array
    {
        switch ($recebimento['entidade']) {
            case 'C': // Cliente
                if ($recebimento['cliente_id']) {
                    $cliente = $this->db->buscarUm(
                        "SELECT nome FROM clientes WHERE id = ? AND deletado_em IS NULL",
                        [$recebimento['cliente_id']]
                    );
                    $recebimento['nome_cliente'] = $cliente['nome'] ?? '';
                }
                break;

            case 'F': // Fornecedor
                if ($recebimento['fornecedor_id']) {
                    $fornecedor = $this->db->buscarUm(
                        "SELECT nome FROM fornecedores WHERE id = ? AND deletado_em IS NULL",
                        [$recebimento['fornecedor_id']]
                    );
                    $recebimento['nome_fornecedor'] = $fornecedor['nome'] ?? '';
                }
                break;

            case 'T': // Transportadora
                if ($recebimento['transportadora_id']) {
                    $transportadora = $this->db->buscarUm(
                        "SELECT nome FROM transportadoras WHERE id = ? AND deletado_em IS NULL",
                        [$recebimento['transportadora_id']]
                    );
                    $recebimento['nome_transportadora'] = $transportadora['nome'] ?? '';
                }
                break;
        }

        return $recebimento;
    }

    /**
     * Lista todos os recebimentos com filtros e paginação
     */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT
                    r.*,
                    pc.nome as nome_plano_conta,
                    cc.nome as nome_centro_custo,
                    cb.nome as nome_conta_bancaria,
                    fp.nome as nome_forma_pagamento,
                    u.nome as nome_usuario,
                    l.nome as nome_loja
                FROM recebimentos r
                LEFT JOIN plano_de_contas pc ON r.plano_contas_id = pc.id AND pc.deletado_em IS NULL
                LEFT JOIN centro_de_custo cc ON r.centro_custo_id = cc.id AND cc.deletado_em IS NULL
                LEFT JOIN contas_bancarias cb ON r.conta_bancaria_id = cb.id AND cb.deletado_em IS NULL
                LEFT JOIN forma_de_pagamento fp ON r.forma_pagamento_id = fp.id AND fp.deletado_em IS NULL
                LEFT JOIN colaboradores u ON r.usuario_id = u.id AND u.deletado_em IS NULL
                LEFT JOIN lojas l ON r.loja_id = l.id AND l.deletado_em IS NULL
                WHERE r.deletado_em IS NULL";
        $parametros = [];

        // Filtro por liquidado
        if (isset($filtros['liquidado']) && $filtros['liquidado'] !== '') {
            $sql .= " AND r.liquidado = ?";
            $parametros[] = $filtros['liquidado'];
        }

        // Filtro por entidade
        if (isset($filtros['entidade']) && $filtros['entidade'] !== '') {
            $sql .= " AND r.entidade = ?";
            $parametros[] = $filtros['entidade'];
        }

        // Filtro por cliente
        if (isset($filtros['cliente_id'])) {
            $sql .= " AND r.cliente_id = ?";
            $parametros[] = $filtros['cliente_id'];
        }

        // Filtro por fornecedor
        if (isset($filtros['fornecedor_id'])) {
            $sql .= " AND r.fornecedor_id = ?";
            $parametros[] = $filtros['fornecedor_id'];
        }

        // Filtro por transportadora
        if (isset($filtros['transportadora_id'])) {
            $sql .= " AND r.transportadora_id = ?";
            $parametros[] = $filtros['transportadora_id'];
        }

        // Filtro por plano de contas
        if (isset($filtros['plano_contas_id'])) {
            $sql .= " AND r.plano_contas_id = ?";
            $parametros[] = $filtros['plano_contas_id'];
        }

        // Filtro por centro de custo
        if (isset($filtros['centro_custo_id'])) {
            $sql .= " AND r.centro_custo_id = ?";
            $parametros[] = $filtros['centro_custo_id'];
        }

        // Filtro por conta bancária
        if (isset($filtros['conta_bancaria_id'])) {
            $sql .= " AND r.conta_bancaria_id = ?";
            $parametros[] = $filtros['conta_bancaria_id'];
        }

        // Filtro por forma de pagamento
        if (isset($filtros['forma_pagamento_id'])) {
            $sql .= " AND r.forma_pagamento_id = ?";
            $parametros[] = $filtros['forma_pagamento_id'];
        }

        // Filtro por loja
        if (isset($filtros['loja_id'])) {
            $sql .= " AND r.loja_id = ?";
            $parametros[] = $filtros['loja_id'];
        }

        // Filtro por data de vencimento (período)
        if (isset($filtros['data_vencimento_inicio'])) {
            $sql .= " AND r.data_vencimento >= ?";
            $parametros[] = $filtros['data_vencimento_inicio'];
        }

        if (isset($filtros['data_vencimento_fim'])) {
            $sql .= " AND r.data_vencimento <= ?";
            $parametros[] = $filtros['data_vencimento_fim'];
        }

        // Filtro por data de liquidação (período)
        if (isset($filtros['data_liquidacao_inicio'])) {
            $sql .= " AND r.data_liquidacao >= ?";
            $parametros[] = $filtros['data_liquidacao_inicio'];
        }

        if (isset($filtros['data_liquidacao_fim'])) {
            $sql .= " AND r.data_liquidacao <= ?";
            $parametros[] = $filtros['data_liquidacao_fim'];
        }

        // Filtro por data de competência (período)
        if (isset($filtros['data_competencia_inicio'])) {
            $sql .= " AND r.data_competencia >= ?";
            $parametros[] = $filtros['data_competencia_inicio'];
        }

        if (isset($filtros['data_competencia_fim'])) {
            $sql .= " AND r.data_competencia <= ?";
            $parametros[] = $filtros['data_competencia_fim'];
        }

        // Busca textual (descrição ou código)
        if (isset($filtros['busca']) && $filtros['busca'] !== '') {
            $sql .= " AND (r.descricao LIKE ? OR r.codigo LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        // Ordenação (validada contra SQL Injection)
        $camposPermitidos = [
            'id', 'codigo', 'descricao', 'valor', 'valor_total', 'liquidado',
            'data_vencimento', 'data_liquidacao', 'data_competencia',
            'cadastrado_em', 'modificado_em', 'entidade'
        ];
        $ordenacaoValidada = AuxiliarValidacao::validarOrdenacao(
            $filtros['ordenacao'] ?? 'data_vencimento',
            $filtros['direcao'] ?? 'DESC',
            $camposPermitidos,
            'data_vencimento'
        );
        $sql .= " ORDER BY r.{$ordenacaoValidada['campo']} {$ordenacaoValidada['direcao']}";

        // Paginação
        if (isset($filtros['limite'])) {
            $sql .= " LIMIT ?";
            $parametros[] = (int) $filtros['limite'];

            if (isset($filtros['offset'])) {
                $sql .= " OFFSET ?";
                $parametros[] = (int) $filtros['offset'];
            }
        }

        $recebimentos = $this->db->buscarTodos($sql, $parametros);

        // Busca nomes das entidades para cada recebimento
        foreach ($recebimentos as &$recebimento) {
            $recebimento = $this->buscarNomeEntidade($recebimento);
        }

        return $recebimentos;
    }

    /**
     * Conta o total de recebimentos
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM recebimentos WHERE deletado_em IS NULL";
        $parametros = [];

        // Replica todos os filtros do método listar
        if (isset($filtros['liquidado']) && $filtros['liquidado'] !== '') {
            $sql .= " AND liquidado = ?";
            $parametros[] = $filtros['liquidado'];
        }

        if (isset($filtros['entidade']) && $filtros['entidade'] !== '') {
            $sql .= " AND entidade = ?";
            $parametros[] = $filtros['entidade'];
        }

        if (isset($filtros['cliente_id'])) {
            $sql .= " AND cliente_id = ?";
            $parametros[] = $filtros['cliente_id'];
        }

        if (isset($filtros['fornecedor_id'])) {
            $sql .= " AND fornecedor_id = ?";
            $parametros[] = $filtros['fornecedor_id'];
        }

        if (isset($filtros['transportadora_id'])) {
            $sql .= " AND transportadora_id = ?";
            $parametros[] = $filtros['transportadora_id'];
        }

        if (isset($filtros['plano_contas_id'])) {
            $sql .= " AND plano_contas_id = ?";
            $parametros[] = $filtros['plano_contas_id'];
        }

        if (isset($filtros['centro_custo_id'])) {
            $sql .= " AND centro_custo_id = ?";
            $parametros[] = $filtros['centro_custo_id'];
        }

        if (isset($filtros['conta_bancaria_id'])) {
            $sql .= " AND conta_bancaria_id = ?";
            $parametros[] = $filtros['conta_bancaria_id'];
        }

        if (isset($filtros['forma_pagamento_id'])) {
            $sql .= " AND forma_pagamento_id = ?";
            $parametros[] = $filtros['forma_pagamento_id'];
        }

        if (isset($filtros['loja_id'])) {
            $sql .= " AND loja_id = ?";
            $parametros[] = $filtros['loja_id'];
        }

        if (isset($filtros['data_vencimento_inicio'])) {
            $sql .= " AND data_vencimento >= ?";
            $parametros[] = $filtros['data_vencimento_inicio'];
        }

        if (isset($filtros['data_vencimento_fim'])) {
            $sql .= " AND data_vencimento <= ?";
            $parametros[] = $filtros['data_vencimento_fim'];
        }

        if (isset($filtros['data_liquidacao_inicio'])) {
            $sql .= " AND data_liquidacao >= ?";
            $parametros[] = $filtros['data_liquidacao_inicio'];
        }

        if (isset($filtros['data_liquidacao_fim'])) {
            $sql .= " AND data_liquidacao <= ?";
            $parametros[] = $filtros['data_liquidacao_fim'];
        }

        if (isset($filtros['data_competencia_inicio'])) {
            $sql .= " AND data_competencia >= ?";
            $parametros[] = $filtros['data_competencia_inicio'];
        }

        if (isset($filtros['data_competencia_fim'])) {
            $sql .= " AND data_competencia <= ?";
            $parametros[] = $filtros['data_competencia_fim'];
        }

        if (isset($filtros['busca']) && $filtros['busca'] !== '') {
            $sql .= " AND (descricao LIKE ? OR codigo LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return (int) $resultado['total'];
    }

    /**
     * Calcula o valor total do recebimento
     * Fórmula: valor + juros - desconto
     */
    private function calcularValorTotal(array $dados): float
    {
        $valor = (float) ($dados['valor'] ?? 0);
        $juros = (float) ($dados['juros'] ?? 0);
        $desconto = (float) ($dados['desconto'] ?? 0);

        return $valor + $juros - $desconto;
    }

    /**
     * Cria um novo recebimento
     */
    public function criar(array $dados): int
    {
        // Calcula valor total automaticamente
        $dados['valor_total'] = $this->calcularValorTotal($dados);

        $dadosInsert = [
            'descricao' => $dados['descricao'],
            'valor' => $dados['valor'],
            'juros' => $dados['juros'] ?? 0.00,
            'desconto' => $dados['desconto'] ?? 0.00,
            'valor_total' => $dados['valor_total'],
            'entidade' => $dados['entidade'],
            'liquidado' => $dados['liquidado'] ?? 0,
            'data_vencimento' => $dados['data_vencimento'],
            'plano_contas_id' => $dados['plano_contas_id'],
            'centro_custo_id' => $dados['centro_custo_id'],
            'conta_bancaria_id' => $dados['conta_bancaria_id'],
            'forma_pagamento_id' => $dados['forma_pagamento_id'],
            'cadastrado_em' => date('Y-m-d H:i:s')
        ];

        // Campos opcionais
        $camposOpcionais = [
            'external_id', 'codigo',
            'plano_contas_external_id', 'centro_custo_external_id',
            'conta_bancaria_external_id', 'forma_pagamento_external_id',
            'cliente_id', 'cliente_external_id',
            'fornecedor_id', 'fornecedor_external_id',
            'transportadora_id', 'transportadora_external_id',
            'data_liquidacao', 'data_competencia',
            'usuario_id', 'usuario_external_id',
            'loja_id', 'loja_external_id'
        ];

        foreach ($camposOpcionais as $campo) {
            if (isset($dados[$campo]) && $dados[$campo] !== '' && $dados[$campo] !== null) {
                $dadosInsert[$campo] = $dados[$campo];
            }
        }

        $id = $this->db->inserir('recebimentos', $dadosInsert);

        // Registra auditoria
        $this->auditoria->registrar(
            'criar',
            'recebimentos',
            $id,
            null,
            $dadosInsert,
            $dados['usuario_id'] ?? null
        );

        return $id;
    }

    /**
     * Atualiza um recebimento
     */
    public function atualizar(int $id, array $dados, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        // Recalcula valor total se valores foram alterados
        if (isset($dados['valor']) || isset($dados['juros']) || isset($dados['desconto'])) {
            $dadosCalculo = array_merge($dadosAtuais, $dados);
            $dados['valor_total'] = $this->calcularValorTotal($dadosCalculo);
        }

        $dadosUpdate = [
            'modificado_em' => date('Y-m-d H:i:s')
        ];

        // Campos que podem ser atualizados
        $camposAtualizaveis = [
            'external_id', 'codigo', 'descricao', 'valor', 'juros', 'desconto', 'valor_total',
            'plano_contas_id', 'plano_contas_external_id',
            'centro_custo_id', 'centro_custo_external_id',
            'conta_bancaria_id', 'conta_bancaria_external_id',
            'forma_pagamento_id', 'forma_pagamento_external_id',
            'entidade',
            'cliente_id', 'cliente_external_id',
            'fornecedor_id', 'fornecedor_external_id',
            'transportadora_id', 'transportadora_external_id',
            'liquidado', 'data_vencimento', 'data_liquidacao', 'data_competencia',
            'usuario_id', 'usuario_external_id',
            'loja_id', 'loja_external_id'
        ];

        foreach ($camposAtualizaveis as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        $resultado = $this->db->atualizar('recebimentos', $dadosUpdate, 'id = ?', [$id]);

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'atualizar',
                'recebimentos',
                $id,
                $dadosAtuais,
                $dadosUpdate,
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Deleta um recebimento (soft delete)
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $resultado = $this->db->atualizar(
            'recebimentos',
            ['deletado_em' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        );

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'deletar',
                'recebimentos',
                $id,
                $dadosAtuais,
                ['deletado_em' => date('Y-m-d H:i:s')],
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
        $sql = "SELECT COUNT(*) as total FROM recebimentos WHERE external_id = ? AND deletado_em IS NULL";
        $parametros = [$externalId];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Baixa/liquida um recebimento (marca como recebido)
     */
    public function baixar(int $id, string $dataLiquidacao, ?int $usuarioId = null): bool
    {
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $dadosUpdate = [
            'liquidado' => 1,
            'data_liquidacao' => $dataLiquidacao,
            'modificado_em' => date('Y-m-d H:i:s')
        ];

        $resultado = $this->db->atualizar('recebimentos', $dadosUpdate, 'id = ?', [$id]);

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'baixar',
                'recebimentos',
                $id,
                $dadosAtuais,
                $dadosUpdate,
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Obtém estatísticas de recebimentos
     */
    public function obterEstatisticas(array $filtros = []): array
    {
        // Total de recebimentos
        $total = $this->contar($filtros);

        // Recebimentos pendentes
        $filtrosPendentes = array_merge($filtros, ['liquidado' => 0]);
        $totalPendentes = $this->contar($filtrosPendentes);

        // Recebimentos liquidados
        $filtrosLiquidados = array_merge($filtros, ['liquidado' => 1]);
        $totalLiquidados = $this->contar($filtrosLiquidados);

        // Valor total pendente
        $valorPendente = $this->db->buscarUm(
            "SELECT COALESCE(SUM(valor_total), 0) as total
             FROM recebimentos
             WHERE liquidado = 0 AND deletado_em IS NULL"
        );

        // Valor total liquidado
        $valorLiquidado = $this->db->buscarUm(
            "SELECT COALESCE(SUM(valor_total), 0) as total
             FROM recebimentos
             WHERE liquidado = 1 AND deletado_em IS NULL"
        );

        return [
            'total' => $total,
            'total_pendentes' => $totalPendentes,
            'total_liquidados' => $totalLiquidados,
            'valor_pendente' => (float) $valorPendente['total'],
            'valor_liquidado' => (float) $valorLiquidado['total'],
            'valor_total' => (float) $valorPendente['total'] + (float) $valorLiquidado['total']
        ];
    }
}
