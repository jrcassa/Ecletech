<?php

namespace App\Models\Venda;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;

/**
 * Model para gerenciar pagamentos de vendas
 */
class ModelVendaPagamento
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca um pagamento por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM vendas_pagamentos WHERE id = ?",
            [$id]
        );
    }

    /**
     * Busca um pagamento por external_id
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM vendas_pagamentos WHERE external_id = ?",
            [$externalId]
        );
    }

    /**
     * Lista todos os pagamentos de uma venda
     */
    public function listarPorVenda(int $vendaId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM vendas_pagamentos WHERE venda_id = ? ORDER BY data_vencimento",
            [$vendaId]
        );
    }

    /**
     * Lista pagamentos pendentes
     */
    public function listarPendentes(int $vendaId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM vendas_pagamentos WHERE venda_id = ? AND pago = 0 ORDER BY data_vencimento",
            [$vendaId]
        );
    }

    /**
     * Cria um novo pagamento
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'venda_id' => $dados['venda_id'],
            'data_vencimento' => $dados['data_vencimento'],
            'valor' => $dados['valor'],
            'cadastrado_em' => date('Y-m-d H:i:s')
        ];

        // Campos opcionais
        $camposOpcionais = [
            'external_id', 'venda_external_id',
            'forma_pagamento_id', 'forma_pagamento_external_id', 'nome_forma_pagamento',
            'plano_contas_id', 'plano_contas_external_id', 'nome_plano_conta',
            'observacao', 'pago', 'data_pagamento', 'valor_pago'
        ];

        foreach ($camposOpcionais as $campo) {
            if (isset($dados[$campo]) && $dados[$campo] !== '') {
                $dadosInsert[$campo] = $dados[$campo];
            }
        }

        $id = $this->db->inserir('vendas_pagamentos', $dadosInsert);

        // Registra auditoria
        $this->auditoria->registrar(
            'criar',
            'venda_pagamento',
            $id,
            null,
            $dadosInsert,
            $dados['colaborador_id'] ?? null
        );

        return $id;
    }

    /**
     * Atualiza um pagamento
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
            'external_id', 'venda_external_id',
            'forma_pagamento_id', 'forma_pagamento_external_id', 'nome_forma_pagamento',
            'plano_contas_id', 'plano_contas_external_id', 'nome_plano_conta',
            'data_vencimento', 'valor', 'observacao',
            'pago', 'data_pagamento', 'valor_pago'
        ];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        $sucesso = $this->db->atualizar('vendas_pagamentos', $dadosUpdate, "id = ?", [$id]);

        if ($sucesso) {
            $this->auditoria->registrar(
                'atualizar',
                'venda_pagamento',
                $id,
                $dadosAtuais,
                $dadosUpdate,
                $usuarioId
            );
        }

        return $sucesso;
    }

    /**
     * Marca um pagamento como pago
     */
    public function marcarComoPago(int $id, ?string $dataPagamento = null, ?float $valorPago = null, ?int $usuarioId = null): bool
    {
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $dadosUpdate = [
            'pago' => 1,
            'data_pagamento' => $dataPagamento ?? date('Y-m-d'),
            'valor_pago' => $valorPago ?? $dadosAtuais['valor'],
            'modificado_em' => date('Y-m-d H:i:s')
        ];

        $sucesso = $this->db->atualizar('vendas_pagamentos', $dadosUpdate, "id = ?", [$id]);

        if ($sucesso) {
            $this->auditoria->registrar(
                'atualizar',
                'venda_pagamento',
                $id,
                $dadosAtuais,
                $dadosUpdate,
                $usuarioId
            );
        }

        return $sucesso;
    }

    /**
     * Deleta um pagamento
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $sucesso = $this->db->deletar('vendas_pagamentos', "id = ?", [$id]);

        if ($sucesso) {
            $this->auditoria->registrar(
                'deletar',
                'venda_pagamento',
                $id,
                $dadosAtuais,
                null,
                $usuarioId
            );
        }

        return $sucesso;
    }

    /**
     * Deleta todos os pagamentos de uma venda
     */
    public function deletarPorVenda(int $vendaId, ?int $usuarioId = null): bool
    {
        $pagamentos = $this->listarPorVenda($vendaId);

        foreach ($pagamentos as $pagamento) {
            $this->deletar($pagamento['id'], $usuarioId);
        }

        return true;
    }

    /**
     * Calcula total pago de uma venda
     */
    public function calcularTotalPago(int $vendaId): float
    {
        $resultado = $this->db->buscarUm(
            "SELECT COALESCE(SUM(valor_pago), 0) as total FROM vendas_pagamentos WHERE venda_id = ? AND pago = 1",
            [$vendaId]
        );

        return (float) $resultado['total'];
    }

    /**
     * Calcula total a pagar de uma venda
     */
    public function calcularTotalAPagar(int $vendaId): float
    {
        $resultado = $this->db->buscarUm(
            "SELECT COALESCE(SUM(valor), 0) as total FROM vendas_pagamentos WHERE venda_id = ? AND pago = 0",
            [$vendaId]
        );

        return (float) $resultado['total'];
    }
}
