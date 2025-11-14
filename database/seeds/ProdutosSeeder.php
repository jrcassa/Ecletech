<?php

namespace Database\Seeds;

require_once __DIR__ . '/BaseSeeder.php';

/**
 * Seeder para popular produtos
 */
class ProdutosSeeder extends BaseSeeder
{
    private int $quantidade = 100;

    private array $categoriasProdutos = [
        'Eletrônicos' => [
            'Notebook', 'Mouse', 'Teclado', 'Monitor', 'Webcam', 'Headset',
            'Smartphone', 'Tablet', 'Smartwatch', 'Carregador'
        ],
        'Informática' => [
            'HD Externo', 'SSD', 'Memória RAM', 'Placa de Vídeo', 'Processador',
            'Fonte', 'Gabinete', 'Cooler', 'Cabo HDMI', 'Adaptador'
        ],
        'Escritório' => [
            'Cadeira', 'Mesa', 'Armário', 'Estante', 'Arquivo',
            'Quadro Branco', 'Flipchart', 'Pasta', 'Organizador'
        ],
        'Papelaria' => [
            'Caneta', 'Lápis', 'Borracha', 'Apontador', 'Papel A4',
            'Grampeador', 'Clips', 'Post-it', 'Caderno', 'Agenda'
        ],
        'Limpeza' => [
            'Detergente', 'Sabão', 'Desinfetante', 'Álcool', 'Pano',
            'Vassoura', 'Rodo', 'Balde', 'Luva', 'Esponja'
        ],
        'Ferramentas' => [
            'Furadeira', 'Parafusadeira', 'Martelo', 'Alicate', 'Chave Philips',
            'Chave de Fenda', 'Trena', 'Nível', 'Serra', 'Esquadro'
        ],
    ];

    public function run(): void
    {
        $this->info("Iniciando seed de Produtos...");

        // Criar grupos de produtos se não existirem
        $this->criarGruposProdutos();

        $this->info("Populando {$this->quantidade} produtos...");

        for ($i = 0; $i < $this->quantidade; $i++) {
            $this->criarProduto();
        }

        $this->success("{$this->quantidade} produtos criados com sucesso!");
    }

    private function criarGruposProdutos(): void
    {
        $this->info("Criando grupos de produtos...");

        foreach (array_keys($this->categoriasProdutos) as $categoria) {
            $existe = $this->db->buscarUm(
                "SELECT id FROM grupos_produtos WHERE nome = ?",
                [$categoria]
            );

            if (!$existe) {
                $this->db->inserir('grupos_produtos', [
                    'nome' => $categoria,
                    'descricao' => "Produtos da categoria $categoria",
                    'ativo' => 1,
                    'cadastrado_em' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    private function criarProduto(): void
    {
        // Escolher categoria aleatória
        $categoria = $this->faker->randomElement(array_keys($this->categoriasProdutos));
        $nomeProduto = $this->faker->randomElement($this->categoriasProdutos[$categoria]);

        // Buscar grupo do produto
        $grupo = $this->db->buscarUm(
            "SELECT id FROM grupos_produtos WHERE nome = ?",
            [$categoria]
        );

        if (!$grupo) {
            return;
        }

        // Gerar variação do produto
        $marca = $this->faker->randomElement([
            'Dell', 'HP', 'Samsung', 'LG', 'Sony', 'Philips',
            'Multilaser', 'Intelbras', 'Fortrek', 'Logitech'
        ]);

        $nomeCompleto = "$nomeProduto $marca " . $this->faker->numberBetween(100, 9999);

        // Calcular preços
        $custoProduto = $this->faker->randomFloat(2, 10, 2000);
        $margemLucro = $this->faker->randomFloat(2, 20, 100);
        $precoVenda = $custoProduto * (1 + ($margemLucro / 100));

        $dados = [
            'nome' => $nomeCompleto,
            'descricao' => $this->faker->optional(0.6)->sentence(),
            'codigo_interno' => $this->faker->unique()->numerify('PROD-#####'),
            'codigo_barras' => $this->faker->ean13(),
            'ncm' => $this->faker->numerify('########'),
            'unidade' => $this->faker->randomElement(['UN', 'PC', 'CX', 'KG', 'MT', 'LT']),
            'grupo_id' => $grupo['id'],
            'estoque_minimo' => $this->faker->numberBetween(5, 50),
            'estoque_maximo' => $this->faker->numberBetween(100, 500),
            'estoque_atual' => $this->faker->numberBetween(0, 300),
            'localizacao' => $this->faker->optional(0.5)->bothify('Corredor ## - Prateleira ##'),
            'peso' => $this->faker->randomFloat(2, 0.1, 50),
            'largura' => $this->faker->randomFloat(2, 1, 100),
            'altura' => $this->faker->randomFloat(2, 1, 100),
            'profundidade' => $this->faker->randomFloat(2, 1, 100),
            'ativo' => $this->faker->randomElement([1, 1, 1, 0]), // 75% ativos
            'observacoes' => $this->faker->optional(0.3)->sentence(),
            'cadastrado_em' => $this->randomDate('-2 years', 'now'),
        ];

        $produtoId = $this->db->inserir('produtos', $dados);

        // Criar valores do produto
        $this->criarValoresProduto($produtoId, $custoProduto, $precoVenda);

        // Criar dados fiscais do produto
        $this->criarDadosFiscais($produtoId);
    }

    private function criarValoresProduto(int $produtoId, float $custoProduto, float $precoVenda): void
    {
        $dados = [
            'produto_id' => $produtoId,
            'custo' => $custoProduto,
            'preco_venda' => $precoVenda,
            'preco_promocional' => $this->faker->optional(0.3)->randomFloat(2, $precoVenda * 0.8, $precoVenda * 0.95),
            'margem_lucro' => (($precoVenda - $custoProduto) / $custoProduto) * 100,
            'data_inicio_promocao' => $this->faker->optional(0.3)->date('Y-m-d'),
            'data_fim_promocao' => $this->faker->optional(0.3)->date('Y-m-d', '+30 days'),
            'cadastrado_em' => date('Y-m-d H:i:s'),
        ];

        $this->db->inserir('produto_valores', $dados);
    }

    private function criarDadosFiscais(int $produtoId): void
    {
        $dados = [
            'produto_id' => $produtoId,
            'origem' => $this->faker->randomElement(['0', '1', '2', '3']),
            'cest' => $this->faker->optional(0.5)->numerify('#######'),
            'tipo_tributacao' => $this->faker->randomElement(['T', 'F', 'I', 'N']),
            'aliquota_icms' => $this->faker->randomElement([0, 7, 12, 18]),
            'aliquota_ipi' => $this->faker->randomElement([0, 5, 10, 15]),
            'aliquota_pis' => $this->faker->randomFloat(2, 0, 2),
            'aliquota_cofins' => $this->faker->randomFloat(2, 0, 8),
            'cadastrado_em' => date('Y-m-d H:i:s'),
        ];

        $this->db->inserir('produto_fiscal', $dados);
    }

    public function setQuantidade(int $quantidade): self
    {
        $this->quantidade = $quantidade;
        return $this;
    }
}
