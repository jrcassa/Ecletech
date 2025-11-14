<?php

namespace Database\Seeds;

require_once __DIR__ . '/BaseSeeder.php';

/**
 * Seeder para popular vendas
 */
class VendasSeeder extends BaseSeeder
{
    private int $quantidade = 100;

    public function run(): void
    {
        $this->info("Iniciando seed de Vendas...");

        // Verificar se existem clientes e produtos
        $totalClientes = $this->count('clientes');
        $totalProdutos = $this->count('produtos');
        $totalColaboradores = $this->count('colaboradores');

        if ($totalClientes === 0) {
            $this->error("Não há clientes cadastrados. Execute o ClientesSeeder primeiro.");
            return;
        }

        if ($totalProdutos === 0) {
            $this->error("Não há produtos cadastrados. Execute o ProdutosSeeder primeiro.");
            return;
        }

        if ($totalColaboradores === 0) {
            $this->warning("Não há colaboradores cadastrados. Vendas serão criadas sem vendedor.");
        }

        // Criar formas de pagamento se não existirem
        $this->criarFormasPagamento();

        $this->info("Populando {$this->quantidade} vendas...");

        for ($i = 0; $i < $this->quantidade; $i++) {
            $this->criarVenda();
        }

        $this->success("{$this->quantidade} vendas criadas com sucesso!");
    }

    private function criarFormasPagamento(): void
    {
        $formas = [
            'Dinheiro' => 1,
            'Cartão de Crédito' => 30,
            'Cartão de Débito' => 1,
            'PIX' => 1,
            'Boleto' => 30,
            'Transferência' => 1,
        ];

        foreach ($formas as $nome => $prazo) {
            $existe = $this->db->buscarUm(
                "SELECT id FROM forma_de_pagamento WHERE nome = ?",
                [$nome]
            );

            if (!$existe) {
                $this->db->inserir('forma_de_pagamento', [
                    'nome' => $nome,
                    'prazo_dias' => $prazo,
                    'ativo' => 1,
                    'cadastrado_em' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    private function criarVenda(): void
    {
        // Buscar cliente aleatório
        $cliente = $this->db->buscarUm(
            "SELECT id FROM clientes WHERE ativo = 1 AND deletado_em IS NULL ORDER BY RAND() LIMIT 1",
            []
        );

        if (!$cliente) {
            return;
        }

        // Buscar vendedor aleatório (colaborador)
        $vendedor = $this->db->buscarUm(
            "SELECT id FROM colaboradores WHERE ativo = 1 AND deletado_em IS NULL ORDER BY RAND() LIMIT 1",
            []
        );

        // Data da venda entre 1 ano atrás e hoje
        $dataVenda = $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d H:i:s');

        // Status da venda
        $status = $this->faker->randomElement([
            'Pendente',
            'Confirmada',
            'Em Separação',
            'Em Transporte',
            'Entregue',
            'Cancelada'
        ]);

        $dados = [
            'cliente_id' => $cliente['id'],
            'vendedor_id' => $vendedor ? $vendedor['id'] : null,
            'data_venda' => $dataVenda,
            'status' => $status,
            'subtotal' => 0, // Será atualizado depois
            'desconto' => 0,
            'acrescimo' => 0,
            'frete' => 0,
            'valor_total' => 0, // Será atualizado depois
            'observacoes' => $this->faker->optional(0.3)->sentence(),
            'cadastrado_em' => $dataVenda,
        ];

        $vendaId = $this->db->inserir('vendas', $dados);

        // Criar itens da venda
        $totais = $this->criarItensVenda($vendaId);

        // Calcular valores
        $desconto = $this->faker->optional(0.3)->randomFloat(2, 0, $totais['subtotal'] * 0.1) ?? 0;
        $acrescimo = $this->faker->optional(0.2)->randomFloat(2, 0, $totais['subtotal'] * 0.05) ?? 0;
        $frete = $this->faker->optional(0.5)->randomFloat(2, 10, 100) ?? 0;
        $valorTotal = $totais['subtotal'] - $desconto + $acrescimo + $frete;

        // Atualizar totais da venda
        $this->db->atualizar('vendas', [
            'subtotal' => $totais['subtotal'],
            'desconto' => $desconto,
            'acrescimo' => $acrescimo,
            'frete' => $frete,
            'valor_total' => $valorTotal,
        ], "id = $vendaId");

        // Criar pagamentos
        $this->criarPagamentosVenda($vendaId, $valorTotal);

        // Criar endereço de entrega
        $this->criarEnderecoEntrega($vendaId, $cliente['id']);
    }

    private function criarItensVenda(int $vendaId): array
    {
        $quantidadeItens = $this->faker->numberBetween(1, 8);
        $subtotal = 0;

        for ($i = 0; $i < $quantidadeItens; $i++) {
            // Buscar produto aleatório com valores
            $produto = $this->db->buscarUm(
                "SELECT p.id, pv.preco_venda, pv.preco_promocional
                FROM produtos p
                LEFT JOIN produto_valores pv ON p.id = pv.produto_id
                WHERE p.ativo = 1 AND p.deletado_em IS NULL
                ORDER BY RAND()
                LIMIT 1",
                []
            );

            if (!$produto) {
                continue;
            }

            $quantidade = $this->faker->numberBetween(1, 10);
            $precoUnitario = $produto['preco_promocional'] ?? $produto['preco_venda'] ?? $this->faker->randomFloat(2, 10, 500);
            $precoTotal = $quantidade * $precoUnitario;

            $dados = [
                'venda_id' => $vendaId,
                'produto_id' => $produto['id'],
                'quantidade' => $quantidade,
                'preco_unitario' => $precoUnitario,
                'desconto_item' => $this->faker->optional(0.2)->randomFloat(2, 0, $precoTotal * 0.1) ?? 0,
                'preco_total' => $precoTotal,
                'observacoes' => $this->faker->optional(0.2)->sentence(),
                'cadastrado_em' => date('Y-m-d H:i:s'),
            ];

            $this->db->inserir('vendas_itens', $dados);
            $subtotal += $precoTotal;
        }

        return ['subtotal' => $subtotal];
    }

    private function criarPagamentosVenda(int $vendaId, float $valorTotal): void
    {
        // Buscar forma de pagamento aleatória
        $formaPagamento = $this->db->buscarUm(
            "SELECT id, prazo_dias FROM forma_de_pagamento WHERE ativo = 1 ORDER BY RAND() LIMIT 1",
            []
        );

        if (!$formaPagamento) {
            return;
        }

        // Decidir se será parcelado
        $parcelas = $this->faker->randomElement([1, 1, 1, 2, 3, 4, 6, 12]); // Maioria à vista

        $valorParcela = $valorTotal / $parcelas;

        for ($i = 0; $i < $parcelas; $i++) {
            $dataVencimento = date('Y-m-d', strtotime("+{$i} months"));
            $statusPagamento = $this->faker->randomElement(['Pendente', 'Pago', 'Pago', 'Atrasado']);

            $dados = [
                'venda_id' => $vendaId,
                'forma_pagamento_id' => $formaPagamento['id'],
                'parcela' => $i + 1,
                'total_parcelas' => $parcelas,
                'valor' => $valorParcela,
                'data_vencimento' => $dataVencimento,
                'data_pagamento' => $statusPagamento === 'Pago' ? $dataVencimento : null,
                'status' => $statusPagamento,
                'observacoes' => $this->faker->optional(0.2)->sentence(),
                'cadastrado_em' => date('Y-m-d H:i:s'),
            ];

            $this->db->inserir('vendas_pagamentos', $dados);
        }
    }

    private function criarEnderecoEntrega(int $vendaId, int $clienteId): void
    {
        // Buscar endereço do cliente
        $endereco = $this->db->buscarUm(
            "SELECT * FROM clientes_enderecos WHERE cliente_id = ? LIMIT 1",
            [$clienteId]
        );

        if (!$endereco) {
            // Criar endereço fictício
            $cidade = $this->db->buscarUm(
                "SELECT id, estado_id FROM cidades ORDER BY RAND() LIMIT 1",
                []
            );

            if (!$cidade) {
                return;
            }

            $dados = [
                'venda_id' => $vendaId,
                'logradouro' => $this->faker->streetName,
                'numero' => $this->faker->buildingNumber,
                'complemento' => $this->faker->optional(0.3)->secondaryAddress,
                'bairro' => $this->faker->randomElement(['Centro', 'Jardim das Flores', 'Vila Nova']),
                'cidade_id' => $cidade['id'],
                'estado_id' => $cidade['estado_id'],
                'cep' => $this->generateCEP(),
                'cadastrado_em' => date('Y-m-d H:i:s'),
            ];
        } else {
            $dados = [
                'venda_id' => $vendaId,
                'logradouro' => $endereco['logradouro'],
                'numero' => $endereco['numero'],
                'complemento' => $endereco['complemento'],
                'bairro' => $endereco['bairro'],
                'cidade_id' => $endereco['cidade_id'],
                'estado_id' => $endereco['estado_id'],
                'cep' => $endereco['cep'],
                'cadastrado_em' => date('Y-m-d H:i:s'),
            ];
        }

        $this->db->inserir('vendas_enderecos', $dados);
    }

    public function setQuantidade(int $quantidade): self
    {
        $this->quantidade = $quantidade;
        return $this;
    }
}
