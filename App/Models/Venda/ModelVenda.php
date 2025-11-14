<?php

namespace App\Models\Venda;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;
use App\Helpers\AuxiliarValidacao;

/**
 * Model para gerenciar vendas/pedidos
 */
class ModelVenda
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca uma venda por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM vendas WHERE id = ? AND deletado_em IS NULL",
            [$id]
        );
    }

    /**
     * Busca uma venda por ID externo
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM vendas WHERE external_id = ? AND deletado_em IS NULL",
            [$externalId]
        );
    }

    /**
     * Busca uma venda por código
     */
    public function buscarPorCodigo(string $codigo): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM vendas WHERE codigo = ? AND deletado_em IS NULL",
            [$codigo]
        );
    }

    /**
     * Busca uma venda por hash
     */
    public function buscarPorHash(string $hash): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM vendas WHERE hash = ? AND deletado_em IS NULL",
            [$hash]
        );
    }

    /**
     * Busca venda completa com todos relacionamentos
     */
    public function buscarCompleta(int $id): ?array
    {
        $venda = $this->buscarPorId($id);
        if (!$venda) {
            return null;
        }

        // Busca itens (produtos e serviços)
        $venda['itens'] = $this->db->buscarTodos(
            "SELECT * FROM vendas_itens WHERE venda_id = ? ORDER BY id",
            [$id]
        );

        // Busca pagamentos
        $venda['pagamentos'] = $this->db->buscarTodos(
            "SELECT * FROM vendas_pagamentos WHERE venda_id = ? ORDER BY data_vencimento",
            [$id]
        );

        // Busca endereços
        $venda['enderecos'] = $this->db->buscarTodos(
            "SELECT
                ve.*,
                c.nome as nome_cidade
            FROM vendas_enderecos ve
            LEFT JOIN cidades c ON ve.cidade_id = c.id
            WHERE ve.venda_id = ?
            ORDER BY ve.id",
            [$id]
        );

        // Busca atributos customizados
        $venda['atributos'] = $this->db->buscarTodos(
            "SELECT * FROM vendas_atributos WHERE venda_id = ? ORDER BY id",
            [$id]
        );

        return $venda;
    }

    /**
     * Lista vendas com filtros e paginação
     */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT v.* FROM vendas v WHERE v.deletado_em IS NULL";
        $parametros = [];

        // Filtro por cliente
        if (isset($filtros['cliente_id'])) {
            $sql .= " AND v.cliente_id = ?";
            $parametros[] = $filtros['cliente_id'];
        }

        // Filtro por vendedor
        if (isset($filtros['vendedor_id'])) {
            $sql .= " AND v.vendedor_id = ?";
            $parametros[] = $filtros['vendedor_id'];
        }

        // Filtro por situação
        if (isset($filtros['situacao_venda_id'])) {
            $sql .= " AND v.situacao_venda_id = ?";
            $parametros[] = $filtros['situacao_venda_id'];
        }

        // Filtro por situação financeira
        if (isset($filtros['situacao_financeiro'])) {
            $sql .= " AND v.situacao_financeiro = ?";
            $parametros[] = $filtros['situacao_financeiro'];
        }

        // Filtro por situação de estoque
        if (isset($filtros['situacao_estoque'])) {
            $sql .= " AND v.situacao_estoque = ?";
            $parametros[] = $filtros['situacao_estoque'];
        }

        // Filtro por canal de venda
        if (isset($filtros['canal_venda'])) {
            $sql .= " AND v.canal_venda = ?";
            $parametros[] = $filtros['canal_venda'];
        }

        // Filtro por loja
        if (isset($filtros['loja_id'])) {
            $sql .= " AND v.loja_id = ?";
            $parametros[] = $filtros['loja_id'];
        }

        // Filtro por período (data_venda)
        if (isset($filtros['data_inicio'])) {
            $sql .= " AND v.data_venda >= ?";
            $parametros[] = $filtros['data_inicio'];
        }

        if (isset($filtros['data_fim'])) {
            $sql .= " AND v.data_venda <= ?";
            $parametros[] = $filtros['data_fim'];
        }

        // Filtro por ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND v.ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        // Busca textual (código, nome cliente, observações)
        if (isset($filtros['busca'])) {
            $sql .= " AND (v.codigo LIKE ? OR v.nome_cliente LIKE ? OR v.observacoes LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        // Ordenação (validada contra SQL Injection)
        $camposPermitidos = [
            'id', 'codigo', 'data_venda', 'prazo_entrega', 'nome_cliente',
            'nome_vendedor', 'nome_situacao', 'valor_total', 'situacao_financeiro',
            'situacao_estoque', 'canal_venda', 'cadastrado_em', 'modificado_em'
        ];
        $ordenacaoValidada = AuxiliarValidacao::validarOrdenacao(
            $filtros['ordenacao'] ?? 'data_venda',
            $filtros['direcao'] ?? 'DESC',
            $camposPermitidos,
            'data_venda'
        );
        $sql .= " ORDER BY v.{$ordenacaoValidada['campo']} {$ordenacaoValidada['direcao']}";

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
     * Conta total de vendas
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM vendas v WHERE v.deletado_em IS NULL";
        $parametros = [];

        // Aplicar os mesmos filtros do método listar
        if (isset($filtros['cliente_id'])) {
            $sql .= " AND v.cliente_id = ?";
            $parametros[] = $filtros['cliente_id'];
        }

        if (isset($filtros['vendedor_id'])) {
            $sql .= " AND v.vendedor_id = ?";
            $parametros[] = $filtros['vendedor_id'];
        }

        if (isset($filtros['situacao_venda_id'])) {
            $sql .= " AND v.situacao_venda_id = ?";
            $parametros[] = $filtros['situacao_venda_id'];
        }

        if (isset($filtros['situacao_financeiro'])) {
            $sql .= " AND v.situacao_financeiro = ?";
            $parametros[] = $filtros['situacao_financeiro'];
        }

        if (isset($filtros['situacao_estoque'])) {
            $sql .= " AND v.situacao_estoque = ?";
            $parametros[] = $filtros['situacao_estoque'];
        }

        if (isset($filtros['canal_venda'])) {
            $sql .= " AND v.canal_venda = ?";
            $parametros[] = $filtros['canal_venda'];
        }

        if (isset($filtros['loja_id'])) {
            $sql .= " AND v.loja_id = ?";
            $parametros[] = $filtros['loja_id'];
        }

        if (isset($filtros['data_inicio'])) {
            $sql .= " AND v.data_venda >= ?";
            $parametros[] = $filtros['data_inicio'];
        }

        if (isset($filtros['data_fim'])) {
            $sql .= " AND v.data_venda <= ?";
            $parametros[] = $filtros['data_fim'];
        }

        if (isset($filtros['ativo'])) {
            $sql .= " AND v.ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        if (isset($filtros['busca'])) {
            $sql .= " AND (v.codigo LIKE ? OR v.nome_cliente LIKE ? OR v.observacoes LIKE ?)";
            $busca = "%{$filtros['busca']}%";
            $parametros[] = $busca;
            $parametros[] = $busca;
            $parametros[] = $busca;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return (int) $resultado['total'];
    }

    /**
     * Cria uma nova venda
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'codigo' => $dados['codigo'],
            'hash' => $dados['hash'],
            'data_venda' => $dados['data_venda'],
            'valor_total' => $dados['valor_total'],
            'ativo' => $dados['ativo'] ?? 1,
            'cadastrado_em' => date('Y-m-d H:i:s')
        ];

        // Campos opcionais
        $camposOpcionais = [
            'external_id', 'cliente_id', 'cliente_external_id', 'nome_cliente',
            'vendedor_id', 'vendedor_external_id', 'nome_vendedor',
            'tecnico_id', 'tecnico_external_id', 'nome_tecnico',
            'situacao_venda_id', 'situacao_venda_external_id', 'nome_situacao',
            'transportadora_id', 'transportadora_external_id', 'nome_transportadora',
            'centro_custo_id', 'centro_custo_external_id', 'nome_centro_custo',
            'loja_id', 'loja_external_id', 'nome_loja',
            'forma_pagamento_id', 'forma_pagamento_external_id', 'nome_forma_pagamento',
            'prazo_entrega', 'validade', 'data_primeira_parcela',
            'valor_produtos', 'valor_servicos', 'valor_frete',
            'desconto_valor', 'desconto_porcentagem', 'valor_custo',
            'condicao_pagamento', 'numero_parcelas', 'intervalo_dias',
            'situacao_financeiro', 'situacao_estoque', 'canal_venda', 'exibir_endereco',
            'aos_cuidados_de', 'introducao', 'observacoes', 'observacoes_interna',
            'nota_fiscal_id', 'nota_fiscal_servico_id'
        ];

        foreach ($camposOpcionais as $campo) {
            if (isset($dados[$campo]) && $dados[$campo] !== '') {
                $dadosInsert[$campo] = $dados[$campo];
            }
        }

        $id = $this->db->inserir('vendas', $dadosInsert);

        // Registra auditoria
        $this->auditoria->registrar(
            'criar',
            'venda',
            $id,
            null,
            $dadosInsert,
            $dados['colaborador_id'] ?? null
        );

        return $id;
    }

    /**
     * Atualiza uma venda
     */
    public function atualizar(int $id, array $dados, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $dadosUpdate = ['modificado_em' => date('Y-m-d H:i:s')];

        // Campos atualizáveis
        $camposAtualizaveis = [
            'external_id', 'codigo', 'hash',
            'cliente_id', 'cliente_external_id', 'nome_cliente',
            'vendedor_id', 'vendedor_external_id', 'nome_vendedor',
            'tecnico_id', 'tecnico_external_id', 'nome_tecnico',
            'situacao_venda_id', 'situacao_venda_external_id', 'nome_situacao',
            'transportadora_id', 'transportadora_external_id', 'nome_transportadora',
            'centro_custo_id', 'centro_custo_external_id', 'nome_centro_custo',
            'loja_id', 'loja_external_id', 'nome_loja',
            'forma_pagamento_id', 'forma_pagamento_external_id', 'nome_forma_pagamento',
            'data_venda', 'prazo_entrega', 'validade', 'data_primeira_parcela',
            'valor_produtos', 'valor_servicos', 'valor_frete',
            'desconto_valor', 'desconto_porcentagem', 'valor_total', 'valor_custo',
            'condicao_pagamento', 'numero_parcelas', 'intervalo_dias',
            'situacao_financeiro', 'situacao_estoque', 'canal_venda', 'exibir_endereco',
            'aos_cuidados_de', 'introducao', 'observacoes', 'observacoes_interna',
            'nota_fiscal_id', 'nota_fiscal_servico_id', 'ativo'
        ];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        // Log para debug
        error_log("ModelVenda::atualizar (id=$id) - Dados a atualizar: " . json_encode($dadosUpdate));

        $sucesso = $this->db->atualizar('vendas', $dadosUpdate, "id = ?", [$id]);

        // Log para debug
        error_log("ModelVenda::atualizar (id=$id) - Sucesso: " . ($sucesso ? 'true' : 'false'));

        if ($sucesso) {
            // Registra auditoria
            $this->auditoria->registrar(
                'atualizar',
                'venda',
                $id,
                $dadosAtuais,
                $dadosUpdate,
                $usuarioId
            );
        }

        return $sucesso;
    }

    /**
     * Deleta uma venda (soft delete)
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        // Busca dados atuais para auditoria
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $sucesso = $this->db->atualizar(
            'vendas',
            ['deletado_em' => date('Y-m-d H:i:s')],
            "id = ?",
            [$id]
        );

        if ($sucesso) {
            // Registra auditoria
            $this->auditoria->registrar(
                'deletar',
                'venda',
                $id,
                $dadosAtuais,
                ['deletado_em' => date('Y-m-d H:i:s')],
                $usuarioId
            );
        }

        return $sucesso;
    }

    /**
     * Restaura uma venda deletada
     */
    public function restaurar(int $id, ?int $usuarioId = null): bool
    {
        $sucesso = $this->db->atualizar(
            'vendas',
            ['deletado_em' => null],
            "id = ?",
            [$id]
        );

        if ($sucesso) {
            // Registra auditoria
            $this->auditoria->registrar(
                'restaurar',
                'venda',
                $id,
                null,
                ['deletado_em' => null],
                $usuarioId
            );
        }

        return $sucesso;
    }

    /**
     * Gera código único para venda
     */
    public function gerarCodigo(): string
    {
        // Busca o último código
        $ultimo = $this->db->buscarUm(
            "SELECT codigo FROM vendas ORDER BY id DESC LIMIT 1"
        );

        if (!$ultimo) {
            return '10001'; // Primeiro código
        }

        // Incrementa
        return (string) ((int) $ultimo['codigo'] + 1);
    }

    /**
     * Gera hash único para venda (compartilhável)
     */
    public function gerarHash(): string
    {
        do {
            // Gera hash aleatório de 7 caracteres
            $hash = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 7);

            // Verifica se já existe
            $existe = $this->buscarPorHash($hash);
        } while ($existe);

        return $hash;
    }

    /**
     * Calcula totais de uma venda baseado nos itens
     */
    public function calcularTotais(int $vendaId): array
    {
        // Calcula total de produtos
        $totalProdutos = $this->db->buscarUm(
            "SELECT COALESCE(SUM(valor_total), 0) as total
            FROM vendas_itens
            WHERE venda_id = ? AND tipo = 'produto'",
            [$vendaId]
        );

        // Calcula total de serviços
        $totalServicos = $this->db->buscarUm(
            "SELECT COALESCE(SUM(valor_total), 0) as total
            FROM vendas_itens
            WHERE venda_id = ? AND tipo = 'servico'",
            [$vendaId]
        );

        // Busca venda para pegar frete e desconto
        $venda = $this->buscarPorId($vendaId);

        $valorProdutos = (float) $totalProdutos['total'];
        $valorServicos = (float) $totalServicos['total'];
        $valorFrete = (float) ($venda['valor_frete'] ?? 0);
        $descontoValor = (float) ($venda['desconto_valor'] ?? 0);

        $valorTotal = $valorProdutos + $valorServicos + $valorFrete - $descontoValor;

        // Log para debug
        error_log("calcularTotais (venda_id=$vendaId): produtos=$valorProdutos, servicos=$valorServicos, frete=$valorFrete, desconto=$descontoValor, total=$valorTotal");

        return [
            'valor_produtos' => $valorProdutos,
            'valor_servicos' => $valorServicos,
            'valor_frete' => $valorFrete,
            'desconto_valor' => $descontoValor,
            'valor_total' => $valorTotal
        ];
    }
}
