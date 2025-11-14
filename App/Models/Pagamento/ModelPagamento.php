<?php

namespace App\Models\Pagamento;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;
use App\Helpers\AuxiliarValidacao;

/**
 * Model para gerenciar pagamentos (contas a pagar e contas a receber)
 */
class ModelPagamento
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca um pagamento por ID (com JOINs de entidades relacionadas)
     */
    public function buscarPorId(int $id): ?array
    {
        $pagamento = $this->db->buscarUm(
            "SELECT
                p.*,
                pc.nome as nome_plano_conta,
                cc.nome as nome_centro_custo,
                cb.nome as nome_conta_bancaria,
                fp.nome as nome_forma_pagamento,
                u.nome as nome_usuario,
                l.nome as nome_loja
             FROM pagamentos p
             LEFT JOIN plano_de_contas pc ON p.plano_contas_id = pc.id AND pc.deletado_em IS NULL
             LEFT JOIN centro_de_custo cc ON p.centro_custo_id = cc.id AND cc.deletado_em IS NULL
             LEFT JOIN contas_bancarias cb ON p.conta_bancaria_id = cb.id AND cb.deletado_em IS NULL
             LEFT JOIN forma_de_pagamento fp ON p.forma_pagamento_id = fp.id AND fp.deletado_em IS NULL
             LEFT JOIN colaboradores u ON p.usuario_id = u.id AND u.deletado_em IS NULL
             LEFT JOIN lojas l ON p.loja_id = l.id AND l.deletado_em IS NULL
             WHERE p.id = ? AND p.deletado_em IS NULL",
            [$id]
        );

        if (!$pagamento) {
            return null;
        }

        // Busca nome da entidade relacionada
        $pagamento = $this->buscarNomeEntidade($pagamento);

        // Busca atributos customizados
        $pagamento['atributos'] = $this->buscarAtributos($id);

        return $pagamento;
    }

    /**
     * Busca um pagamento por ID externo
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        $resultado = $this->db->buscarUm(
            "SELECT id FROM pagamentos WHERE external_id = ? AND deletado_em IS NULL",
            [$externalId]
        );

        if (!$resultado) {
            return null;
        }

        return $this->buscarPorId($resultado['id']);
    }

    /**
     * Busca nome da entidade relacionada (Cliente, Fornecedor, etc)
     */
    private function buscarNomeEntidade(array $pagamento): array
    {
        switch ($pagamento['entidade']) {
            case 'C': // Cliente
                if ($pagamento['cliente_id']) {
                    $cliente = $this->db->buscarUm(
                        "SELECT nome FROM clientes WHERE id = ? AND deletado_em IS NULL",
                        [$pagamento['cliente_id']]
                    );
                    $pagamento['nome_cliente'] = $cliente['nome'] ?? '';
                }
                break;

            case 'F': // Fornecedor
                if ($pagamento['fornecedor_id']) {
                    $fornecedor = $this->db->buscarUm(
                        "SELECT nome FROM fornecedores WHERE id = ? AND deletado_em IS NULL",
                        [$pagamento['fornecedor_id']]
                    );
                    $pagamento['nome_fornecedor'] = $fornecedor['nome'] ?? '';
                }
                break;

            case 'T': // Transportadora
                if ($pagamento['transportadora_id']) {
                    $transportadora = $this->db->buscarUm(
                        "SELECT nome FROM transportadoras WHERE id = ? AND deletado_em IS NULL",
                        [$pagamento['transportadora_id']]
                    );
                    $pagamento['nome_transportadora'] = $transportadora['nome'] ?? '';
                }
                break;

            case 'U': // Funcionário
                if ($pagamento['funcionario_id']) {
                    $funcionario = $this->db->buscarUm(
                        "SELECT nome FROM colaboradores WHERE id = ? AND deletado_em IS NULL",
                        [$pagamento['funcionario_id']]
                    );
                    $pagamento['nome_funcionario'] = $funcionario['nome'] ?? '';
                }
                break;
        }

        return $pagamento;
    }

    /**
     * Busca atributos customizados de um pagamento
     */
    public function buscarAtributos(int $pagamentoId): array
    {
        return $this->db->buscarTodos(
            "SELECT chave, valor FROM pagamentos_atributos WHERE pagamento_id = ? ORDER BY chave",
            [$pagamentoId]
        );
    }

    /**
     * Lista todos os pagamentos com filtros e paginação
     */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT
                    p.*,
                    pc.nome as nome_plano_conta,
                    cc.nome as nome_centro_custo,
                    cb.nome as nome_conta_bancaria,
                    fp.nome as nome_forma_pagamento,
                    u.nome as nome_usuario,
                    l.nome as nome_loja
                FROM pagamentos p
                LEFT JOIN plano_de_contas pc ON p.plano_contas_id = pc.id AND pc.deletado_em IS NULL
                LEFT JOIN centro_de_custo cc ON p.centro_custo_id = cc.id AND cc.deletado_em IS NULL
                LEFT JOIN contas_bancarias cb ON p.conta_bancaria_id = cb.id AND cb.deletado_em IS NULL
                LEFT JOIN forma_de_pagamento fp ON p.forma_pagamento_id = fp.id AND fp.deletado_em IS NULL
                LEFT JOIN colaboradores u ON p.usuario_id = u.id AND u.deletado_em IS NULL
                LEFT JOIN lojas l ON p.loja_id = l.id AND l.deletado_em IS NULL
                WHERE p.deletado_em IS NULL";
        $parametros = [];

        // Filtro por liquidado
        if (isset($filtros['liquidado']) && $filtros['liquidado'] !== '') {
            $sql .= " AND p.liquidado = ?";
            $parametros[] = $filtros['liquidado'];
        }

        // Filtro por entidade
        if (isset($filtros['entidade']) && $filtros['entidade'] !== '') {
            $sql .= " AND p.entidade = ?";
            $parametros[] = $filtros['entidade'];
        }

        // Filtro por cliente
        if (isset($filtros['cliente_id'])) {
            $sql .= " AND p.cliente_id = ?";
            $parametros[] = $filtros['cliente_id'];
        }

        // Filtro por fornecedor
        if (isset($filtros['fornecedor_id'])) {
            $sql .= " AND p.fornecedor_id = ?";
            $parametros[] = $filtros['fornecedor_id'];
        }

        // Filtro por plano de contas
        if (isset($filtros['plano_contas_id'])) {
            $sql .= " AND p.plano_contas_id = ?";
            $parametros[] = $filtros['plano_contas_id'];
        }

        // Filtro por centro de custo
        if (isset($filtros['centro_custo_id'])) {
            $sql .= " AND p.centro_custo_id = ?";
            $parametros[] = $filtros['centro_custo_id'];
        }

        // Filtro por forma de pagamento
        if (isset($filtros['forma_pagamento_id'])) {
            $sql .= " AND p.forma_pagamento_id = ?";
            $parametros[] = $filtros['forma_pagamento_id'];
        }

        // Filtro por data de vencimento (período)
        if (isset($filtros['data_vencimento_inicio'])) {
            $sql .= " AND p.data_vencimento >= ?";
            $parametros[] = $filtros['data_vencimento_inicio'];
        }

        if (isset($filtros['data_vencimento_fim'])) {
            $sql .= " AND p.data_vencimento <= ?";
            $parametros[] = $filtros['data_vencimento_fim'];
        }

        // Filtro por data de competência (período)
        if (isset($filtros['data_competencia_inicio'])) {
            $sql .= " AND p.data_competencia >= ?";
            $parametros[] = $filtros['data_competencia_inicio'];
        }

        if (isset($filtros['data_competencia_fim'])) {
            $sql .= " AND p.data_competencia <= ?";
            $parametros[] = $filtros['data_competencia_fim'];
        }

        // Busca textual (descrição ou código)
        if (isset($filtros['busca']) && $filtros['busca'] !== '') {
            $sql .= " AND (p.descricao LIKE ? OR p.codigo LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        // Ordenação (validada contra SQL Injection)
        $camposPermitidos = [
            'id', 'codigo', 'descricao', 'valor', 'valor_total', 'liquidado',
            'data_vencimento', 'data_liquidacao', 'data_competencia',
            'cadastrado_em', 'modificado_em'
        ];
        $ordenacaoValidada = AuxiliarValidacao::validarOrdenacao(
            $filtros['ordenacao'] ?? 'data_vencimento',
            $filtros['direcao'] ?? 'DESC',
            $camposPermitidos,
            'data_vencimento'
        );
        $sql .= " ORDER BY p.{$ordenacaoValidada['campo']} {$ordenacaoValidada['direcao']}";

        // Paginação
        if (isset($filtros['limite'])) {
            $sql .= " LIMIT ?";
            $parametros[] = (int) $filtros['limite'];

            if (isset($filtros['offset'])) {
                $sql .= " OFFSET ?";
                $parametros[] = (int) $filtros['offset'];
            }
        }

        $pagamentos = $this->db->buscarTodos($sql, $parametros);

        // Busca nomes das entidades para cada pagamento
        foreach ($pagamentos as &$pagamento) {
            $pagamento = $this->buscarNomeEntidade($pagamento);
        }

        return $pagamentos;
    }

    /**
     * Conta o total de pagamentos
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM pagamentos WHERE deletado_em IS NULL";
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

        if (isset($filtros['plano_contas_id'])) {
            $sql .= " AND plano_contas_id = ?";
            $parametros[] = $filtros['plano_contas_id'];
        }

        if (isset($filtros['centro_custo_id'])) {
            $sql .= " AND centro_custo_id = ?";
            $parametros[] = $filtros['centro_custo_id'];
        }

        if (isset($filtros['forma_pagamento_id'])) {
            $sql .= " AND forma_pagamento_id = ?";
            $parametros[] = $filtros['forma_pagamento_id'];
        }

        if (isset($filtros['data_vencimento_inicio'])) {
            $sql .= " AND data_vencimento >= ?";
            $parametros[] = $filtros['data_vencimento_inicio'];
        }

        if (isset($filtros['data_vencimento_fim'])) {
            $sql .= " AND data_vencimento <= ?";
            $parametros[] = $filtros['data_vencimento_fim'];
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
     * Cria um novo pagamento
     */
    public function criar(array $dados): int
    {
        // Calcula valor total se não fornecido
        if (!isset($dados['valor_total'])) {
            $dados['valor_total'] = $this->calcularValorTotal($dados);
        }

        $dadosInsert = [
            'descricao' => $dados['descricao'],
            'valor' => $dados['valor'],
            'juros' => $dados['juros'] ?? 0.00,
            'desconto' => $dados['desconto'] ?? 0.00,
            'taxa_banco' => $dados['taxa_banco'] ?? 0.00,
            'taxa_operadora' => $dados['taxa_operadora'] ?? 0.00,
            'valor_total' => $dados['valor_total'],
            'entidade' => $dados['entidade'],
            'liquidado' => $dados['liquidado'] ?? 0,
            'data_vencimento' => $dados['data_vencimento'],
            'cadastrado_em' => date('Y-m-d H:i:s')
        ];

        // Campos opcionais
        $camposOpcionais = [
            'external_id', 'codigo', 'plano_contas_id', 'plano_contas_external_id',
            'centro_custo_id', 'centro_custo_external_id', 'conta_bancaria_id', 'conta_bancaria_external_id',
            'forma_pagamento_id', 'forma_pagamento_external_id',
            'cliente_id', 'cliente_external_id', 'fornecedor_id', 'fornecedor_external_id',
            'transportadora_id', 'transportadora_external_id', 'funcionario_id', 'funcionario_external_id',
            'data_liquidacao', 'data_competencia',
            'usuario_id', 'usuario_external_id', 'loja_id', 'loja_external_id'
        ];

        foreach ($camposOpcionais as $campo) {
            if (isset($dados[$campo]) && $dados[$campo] !== '' && $dados[$campo] !== null) {
                $dadosInsert[$campo] = $dados[$campo];
            }
        }

        $id = $this->db->inserir('pagamentos', $dadosInsert);

        // Insere atributos customizados
        if (isset($dados['atributos']) && is_array($dados['atributos']) && count($dados['atributos']) > 0) {
            $this->salvarAtributos($id, $dados['atributos']);
        }

        // Registra auditoria
        $this->auditoria->registrar(
            'criar',
            'pagamentos',
            $id,
            null,
            $dadosInsert,
            $dados['usuario_id'] ?? null
        );

        return $id;
    }

    /**
     * Atualiza um pagamento
     */
    public function atualizar(int $id, array $dados, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        // Recalcula valor total se valores foram alterados
        if (isset($dados['valor']) || isset($dados['juros']) || isset($dados['desconto'])
            || isset($dados['taxa_banco']) || isset($dados['taxa_operadora'])) {
            $dadosCalculo = array_merge($dadosAtuais, $dados);
            $dados['valor_total'] = $this->calcularValorTotal($dadosCalculo);
        }

        $dadosUpdate = [
            'modificado_em' => date('Y-m-d H:i:s')
        ];

        // Campos que podem ser atualizados
        $camposAtualizaveis = [
            'external_id', 'codigo', 'descricao', 'valor', 'juros', 'desconto',
            'taxa_banco', 'taxa_operadora', 'valor_total',
            'plano_contas_id', 'plano_contas_external_id',
            'centro_custo_id', 'centro_custo_external_id',
            'conta_bancaria_id', 'conta_bancaria_external_id',
            'forma_pagamento_id', 'forma_pagamento_external_id',
            'entidade', 'cliente_id', 'cliente_external_id',
            'fornecedor_id', 'fornecedor_external_id',
            'transportadora_id', 'transportadora_external_id',
            'funcionario_id', 'funcionario_external_id',
            'liquidado', 'data_vencimento', 'data_liquidacao', 'data_competencia',
            'usuario_id', 'usuario_external_id', 'loja_id', 'loja_external_id'
        ];

        foreach ($camposAtualizaveis as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        $resultado = $this->db->atualizar('pagamentos', $dadosUpdate, 'id = ?', [$id]);

        // Atualiza atributos customizados se fornecidos
        if (isset($dados['atributos']) && is_array($dados['atributos'])) {
            // Remove atributos antigos
            $this->db->executar("DELETE FROM pagamentos_atributos WHERE pagamento_id = ?", [$id]);
            // Insere novos
            if (count($dados['atributos']) > 0) {
                $this->salvarAtributos($id, $dados['atributos']);
            }
        }

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'atualizar',
                'pagamentos',
                $id,
                $dadosAtuais,
                $dadosUpdate,
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Deleta um pagamento (soft delete)
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $resultado = $this->db->atualizar(
            'pagamentos',
            ['deletado_em' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        );

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'deletar',
                'pagamentos',
                $id,
                $dadosAtuais,
                ['deletado_em' => date('Y-m-d H:i:s')],
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Restaura um pagamento deletado
     */
    public function restaurar(int $id, ?int $usuarioId = null): bool
    {
        $resultado = $this->db->atualizar(
            'pagamentos',
            ['deletado_em' => null],
            'id = ?',
            [$id]
        );

        // Registra auditoria
        if ($resultado) {
            $this->auditoria->registrar(
                'restaurar',
                'pagamentos',
                $id,
                ['deletado_em' => date('Y-m-d H:i:s')],
                ['deletado_em' => null],
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Liquida um pagamento
     */
    public function liquidar(int $id, string $dataLiquidacao, ?int $usuarioId = null): bool
    {
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $resultado = $this->db->atualizar(
            'pagamentos',
            [
                'liquidado' => 1,
                'data_liquidacao' => $dataLiquidacao,
                'modificado_em' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$id]
        );

        if ($resultado) {
            $this->auditoria->registrar(
                'liquidar',
                'pagamentos',
                $id,
                $dadosAtuais,
                ['liquidado' => 1, 'data_liquidacao' => $dataLiquidacao],
                $usuarioId
            );
        }

        return $resultado;
    }

    /**
     * Salva atributos customizados
     */
    private function salvarAtributos(int $pagamentoId, array $atributos): void
    {
        foreach ($atributos as $atributo) {
            if (isset($atributo['chave']) && isset($atributo['valor'])) {
                $this->db->inserir('pagamentos_atributos', [
                    'pagamento_id' => $pagamentoId,
                    'chave' => $atributo['chave'],
                    'valor' => $atributo['valor'],
                    'cadastrado_em' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    /**
     * Calcula o valor total do pagamento
     */
    private function calcularValorTotal(array $dados): float
    {
        $valor = (float) ($dados['valor'] ?? 0);
        $juros = (float) ($dados['juros'] ?? 0);
        $desconto = (float) ($dados['desconto'] ?? 0);
        $taxaBanco = (float) ($dados['taxa_banco'] ?? 0);
        $taxaOperadora = (float) ($dados['taxa_operadora'] ?? 0);

        return $valor + $juros - $desconto - $taxaBanco - $taxaOperadora;
    }

    /**
     * Verifica se um external_id já existe
     */
    public function externalIdExiste(string $externalId, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM pagamentos WHERE external_id = ? AND deletado_em IS NULL";
        $parametros = [$externalId];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }

    /**
     * Obtém estatísticas dos pagamentos
     */
    public function obterEstatisticas(): array
    {
        $stats = [];

        // Contas a receber (Cliente)
        $resultado = $this->db->buscarUm(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN liquidado = 0 THEN valor_total ELSE 0 END) as valor_pendente,
                SUM(CASE WHEN liquidado = 1 THEN valor_total ELSE 0 END) as valor_liquidado
             FROM pagamentos
             WHERE entidade = 'C' AND deletado_em IS NULL"
        );
        $stats['contas_receber'] = [
            'total' => (int) $resultado['total'],
            'valor_pendente' => (float) $resultado['valor_pendente'],
            'valor_liquidado' => (float) $resultado['valor_liquidado']
        ];

        // Contas a pagar (Fornecedor)
        $resultado = $this->db->buscarUm(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN liquidado = 0 THEN valor_total ELSE 0 END) as valor_pendente,
                SUM(CASE WHEN liquidado = 1 THEN valor_total ELSE 0 END) as valor_liquidado
             FROM pagamentos
             WHERE entidade = 'F' AND deletado_em IS NULL"
        );
        $stats['contas_pagar'] = [
            'total' => (int) $resultado['total'],
            'valor_pendente' => (float) $resultado['valor_pendente'],
            'valor_liquidado' => (float) $resultado['valor_liquidado']
        ];

        // Total geral
        $stats['total_geral'] = $stats['contas_receber']['total'] + $stats['contas_pagar']['total'];

        return $stats;
    }
}
