<?php

namespace Database\Seeds;

require_once __DIR__ . '/BaseSeeder.php';

/**
 * Seeder para popular fornecedores
 */
class FornecedoresSeeder extends BaseSeeder
{
    private int $quantidade = 30;

    public function run(): void
    {
        $this->info("Iniciando seed de Fornecedores...");

        $this->info("Populando {$this->quantidade} fornecedores...");

        for ($i = 0; $i < $this->quantidade; $i++) {
            $this->criarFornecedor();
        }

        $this->success("{$this->quantidade} fornecedores criados com sucesso!");
    }

    private function criarFornecedor(): void
    {
        $empresa = $this->faker->company;

        $dados = [
            'tipo_pessoa' => 'J',
            'nome' => $empresa,
            'nome_fantasia' => $this->faker->optional(0.7)->companySuffix . ' ' . $this->faker->lastName,
            'razao_social' => $empresa,
            'cpf_cnpj' => $this->generateCNPJ(),
            'rg_ie' => $this->faker->numerify('###.###.###.###'),
            'email' => $this->faker->unique()->companyEmail,
            'telefone' => $this->faker->phoneNumber,
            'celular' => $this->faker->cellphone(false),
            'site' => $this->faker->optional(0.5)->domainName,
            'categoria' => $this->faker->randomElement([
                'Matéria Prima',
                'Insumos',
                'Produtos Acabados',
                'Serviços',
                'Equipamentos',
                'Embalagens',
                'Tecnologia'
            ]),
            'prazo_entrega' => $this->faker->numberBetween(1, 30),
            'limite_credito' => $this->faker->randomFloat(2, 10000, 500000),
            'observacoes' => $this->faker->optional(0.3)->sentence(),
            'ativo' => $this->faker->randomElement([1, 1, 1, 0]), // 75% ativos
            'cadastrado_em' => $this->randomDate('-2 years', 'now'),
        ];

        $fornecedorId = $this->db->inserir('fornecedores', $dados);

        // Criar contatos
        $this->criarContatos($fornecedorId);

        // Criar endereços
        $this->criarEnderecos($fornecedorId);
    }

    private function criarContatos(int $fornecedorId): void
    {
        $quantidade = $this->faker->numberBetween(1, 3);

        for ($i = 0; $i < $quantidade; $i++) {
            $sexo = $this->faker->randomElement(['M', 'F']);
            $nome = $sexo === 'M' ? $this->faker->firstNameMale : $this->faker->firstNameFemale;

            $dados = [
                'fornecedor_id' => $fornecedorId,
                'nome' => $nome . ' ' . $this->faker->lastName,
                'cargo' => $this->faker->randomElement([
                    'Gerente Comercial',
                    'Diretor de Vendas',
                    'Representante Comercial',
                    'Vendedor',
                    'Coordenador de Vendas',
                    'Assistente Comercial'
                ]),
                'email' => $this->faker->email,
                'telefone' => $this->faker->phoneNumber,
                'celular' => $this->faker->cellphone(false),
                'principal' => $i === 0 ? 1 : 0,
                'observacoes' => $this->faker->optional(0.2)->sentence(),
                'cadastrado_em' => date('Y-m-d H:i:s'),
            ];

            $this->db->inserir('fornecedores_contatos', $dados);
        }
    }

    private function criarEnderecos(int $fornecedorId): void
    {
        // Buscar uma cidade aleatória
        $cidade = $this->db->buscarUm(
            "SELECT id, estado_id FROM cidades ORDER BY RAND() LIMIT 1",
            []
        );

        if (!$cidade) {
            return;
        }

        // Buscar tipo de endereço
        $tipoEndereco = $this->db->buscarUm(
            "SELECT id FROM tipos_enderecos WHERE nome = 'Comercial' LIMIT 1",
            []
        );

        if (!$tipoEndereco) {
            $tipoEnderecoId = $this->db->inserir('tipos_enderecos', [
                'nome' => 'Comercial',
                'cadastrado_em' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $tipoEnderecoId = $tipoEndereco['id'];
        }

        $dados = [
            'fornecedor_id' => $fornecedorId,
            'tipo_endereco_id' => $tipoEnderecoId,
            'logradouro' => $this->faker->streetName,
            'numero' => $this->faker->buildingNumber,
            'complemento' => $this->faker->optional(0.3)->secondaryAddress,
            'bairro' => $this->faker->randomElement([
                'Distrito Industrial',
                'Parque Industrial',
                'Centro Empresarial',
                'Vila Comercial',
                'Setor Industrial',
                'Centro Comercial'
            ]),
            'cidade_id' => $cidade['id'],
            'estado_id' => $cidade['estado_id'],
            'cep' => $this->generateCEP(),
            'principal' => 1,
            'cadastrado_em' => date('Y-m-d H:i:s'),
        ];

        $this->db->inserir('fornecedores_enderecos', $dados);
    }

    public function setQuantidade(int $quantidade): self
    {
        $this->quantidade = $quantidade;
        return $this;
    }
}
