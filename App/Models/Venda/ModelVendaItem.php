<?php

namespace App\Models\Venda;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;

/**
 * Model para gerenciar itens de venda (produtos e serviços)
 */
class ModelVendaItem
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca um item por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM vendas_itens WHERE id = ?",
            [$id]
        );
    }

    /**
     * Busca um item por external_id
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM vendas_itens WHERE external_id = ?",
            [$externalId]
        );
    }

    /**
     * Lista todos os itens de uma venda
     */
    public function listarPorVenda(int $vendaId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM vendas_itens WHERE venda_id = ? ORDER BY id",
            [$vendaId]
        );
    }

    /**
     * Lista itens por tipo (produto ou servico)
     */
    public function listarPorVendaETipo(int $vendaId, string $tipo): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM vendas_itens WHERE venda_id = ? AND tipo = ? ORDER BY id",
            [$vendaId, $tipo]
        );
    }

    /**
     * Cria um novo item de venda
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'venda_id' => $dados['venda_id'],
            'tipo' => $dados['tipo'] ?? 'produto',
            'quantidade' => $dados['quantidade'],
            'valor_venda' => $dados['valor_venda'],
            'valor_total' => $dados['valor_total'],
            'cadastrado_em' => date('Y-m-d H:i:s')
        ];

        // Campos opcionais
        $camposOpcionais = [
            'external_id', 'venda_external_id',
            'produto_id', 'produto_external_id',
            'variacao_id', 'variacao_external_id',
            'servico_id', 'servico_external_id',
            'tipo_valor_id', 'tipo_valor_external_id',
            'nome_produto', 'detalhes', 'nome_tipo_valor', 'sigla_unidade',
            'movimenta_estoque', 'possui_variacao',
            'valor_custo', 'tipo_desconto', 'desconto_valor', 'desconto_porcentagem'
        ];

        foreach ($camposOpcionais as $campo) {
            if (isset($dados[$campo]) && $dados[$campo] !== '') {
                $dadosInsert[$campo] = $dados[$campo];
            }
        }

        $id = $this->db->inserir('vendas_itens', $dadosInsert);

        // Registra auditoria
        $this->auditoria->registrar(
            'criar',
            'venda_item',
            $id,
            null,
            $dadosInsert,
            $dados['colaborador_id'] ?? null
        );

        return $id;
    }

    /**
     * Atualiza um item de venda
     */
    public function atualizar(int $id, array $dados, ?int $usuarioId = null): bool
    {
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $dadosUpdate = ['modificado_em' => date('Y-m-d H:i:s')];

        // Campos atualizáveis
        $camposAtualizaveis = [
            'external_id', 'venda_external_id', 'tipo',
            'produto_id', 'produto_external_id',
            'variacao_id', 'variacao_external_id',
            'servico_id', 'servico_external_id',
            'tipo_valor_id', 'tipo_valor_external_id',
            'nome_produto', 'detalhes', 'nome_tipo_valor', 'sigla_unidade',
            'movimenta_estoque', 'possui_variacao',
            'quantidade', 'valor_custo', 'valor_venda',
            'tipo_desconto', 'desconto_valor', 'desconto_porcentagem', 'valor_total'
        ];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        $sucesso = $this->db->atualizar('vendas_itens', $dadosUpdate, "id = ?", [$id]);

        if ($sucesso) {
            $this->auditoria->registrar(
                'atualizar',
                'venda_item',
                $id,
                $dadosAtuais,
                $dadosUpdate,
                $usuarioId
            );
        }

        return $sucesso;
    }

    /**
     * Deleta um item de venda
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $sucesso = $this->db->deletar('vendas_itens', "id = ?", [$id]);

        if ($sucesso) {
            $this->auditoria->registrar(
                'deletar',
                'venda_item',
                $id,
                $dadosAtuais,
                null,
                $usuarioId
            );
        }

        return $sucesso;
    }

    /**
     * Deleta todos os itens de uma venda
     */
    public function deletarPorVenda(int $vendaId, ?int $usuarioId = null): bool
    {
        $itens = $this->listarPorVenda($vendaId);

        foreach ($itens as $item) {
            $this->deletar($item['id'], $usuarioId);
        }

        return true;
    }

    /**
     * Calcula o valor total de um item
     */
    public function calcularValorTotal(array $item): float
    {
        $quantidade = (float) $item['quantidade'];
        $valorVenda = (float) $item['valor_venda'];
        $tipoDesconto = $item['tipo_desconto'] ?? 'R$';
        $descontoValor = (float) ($item['desconto_valor'] ?? 0);
        $descontoPorcentagem = (float) ($item['desconto_porcentagem'] ?? 0);

        // Calcula valor sem desconto
        $valorSemDesconto = $quantidade * $valorVenda;

        // Aplica desconto
        if ($tipoDesconto === '%' && $descontoPorcentagem > 0) {
            $desconto = ($valorSemDesconto * $descontoPorcentagem) / 100;
            return $valorSemDesconto - $desconto;
        } elseif ($tipoDesconto === 'R$' && $descontoValor > 0) {
            return $valorSemDesconto - $descontoValor;
        }

        return $valorSemDesconto;
    }
}
