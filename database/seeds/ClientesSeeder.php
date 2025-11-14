<?php

namespace Database\Seeds;

require_once __DIR__ . '/BaseSeeder.php';

/**
 * Seeder para popular clientes
 */
class ClientesSeeder extends BaseSeeder
{
    private int $quantidade = 50;

    public function run(): void
    {
        $this->info("Iniciando seed de Clientes...");

        $this->info("Populando {$this->quantidade} clientes...");

        for ($i = 0; $i < $this->quantidade; $i++) {
            $tipoPessoa = $this->faker->randomElement(['F', 'J']); // Física ou Jurídica

            if ($tipoPessoa === 'F') {
                $this->criarClientePessoaFisica();
            } else {
                $this->criarClientePessoaJuridica();
            }
        }

        $this->success("{$this->quantidade} clientes criados com sucesso!");
    }

    private function criarClientePessoaFisica(): void
    {
        $sexo = $this->faker->randomElement(['M', 'F']);
        $nome = $sexo === 'M' ? $this->faker->firstNameMale : $this->faker->firstNameFemale;
        $sobrenome = $this->faker->lastName;
        $nomeCompleto = "$nome $sobrenome";

        $dados = [
            'tipo_pessoa' => 'F',
            'nome' => $nomeCompleto,
            'nome_fantasia' => null,
            'razao_social' => null,
            'cpf_cnpj' => $this->generateCPF(),
            'rg_ie' => $this->faker->numerify('##.###.###-#'),
            'email' => $this->faker->unique()->email,
            'telefone' => $this->faker->phoneNumber,
            'celular' => $this->faker->cellphone(false),
            'data_nascimento' => $this->faker->date('Y-m-d', '-30 years'),
            'sexo' => $sexo,
            'estado_civil' => $this->faker->randomElement(['Solteiro', 'Casado', 'Divorciado', 'Viúvo']),
            'profissao' => $this->faker->randomElement([
                'Empresário',
                'Advogado',
                'Médico',
                'Engenheiro',
                'Professor',
                'Comerciante',
                'Autônomo',
                'Funcionário Público'
            ]),
            'limite_credito' => $this->faker->randomFloat(2, 1000, 50000),
            'dia_vencimento' => $this->faker->numberBetween(1, 28),
            'observacoes' => $this->faker->optional(0.3)->sentence(),
            'ativo' => $this->faker->randomElement([1, 1, 1, 0]), // 75% ativos
            'cadastrado_em' => $this->randomDate('-2 years', 'now'),
        ];

        $clienteId = $this->db->inserir('clientes', $dados);

        // Criar contatos
        $this->criarContatos($clienteId);

        // Criar endereços
        $this->criarEnderecos($clienteId);
    }

    private function criarClientePessoaJuridica(): void
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
            'data_nascimento' => null,
            'sexo' => null,
            'estado_civil' => null,
            'profissao' => null,
            'limite_credito' => $this->faker->randomFloat(2, 10000, 200000),
            'dia_vencimento' => $this->faker->numberBetween(1, 28),
            'observacoes' => $this->faker->optional(0.3)->sentence(),
            'ativo' => $this->faker->randomElement([1, 1, 1, 0]), // 75% ativos
            'cadastrado_em' => $this->randomDate('-2 years', 'now'),
        ];

        $clienteId = $this->db->inserir('clientes', $dados);

        // Criar contatos (empresas geralmente têm mais contatos)
        $this->criarContatos($clienteId, $this->faker->numberBetween(1, 3));

        // Criar endereços
        $this->criarEnderecos($clienteId);
    }

    private function criarContatos(int $clienteId, int $quantidade = null): void
    {
        $quantidade = $quantidade ?? $this->faker->numberBetween(0, 2);

        for ($i = 0; $i < $quantidade; $i++) {
            $sexo = $this->faker->randomElement(['M', 'F']);
            $nome = $sexo === 'M' ? $this->faker->firstNameMale : $this->faker->firstNameFemale;

            $dados = [
                'cliente_id' => $clienteId,
                'nome' => $nome . ' ' . $this->faker->lastName,
                'cargo' => $this->faker->randomElement([
                    'Gerente',
                    'Diretor',
                    'Comprador',
                    'Assistente',
                    'Coordenador',
                    'Responsável'
                ]),
                'email' => $this->faker->email,
                'telefone' => $this->faker->phoneNumber,
                'celular' => $this->faker->cellphone(false),
                'principal' => $i === 0 ? 1 : 0,
                'observacoes' => $this->faker->optional(0.2)->sentence(),
                'cadastrado_em' => date('Y-m-d H:i:s'),
            ];

            $this->db->inserir('clientes_contatos', $dados);
        }
    }

    private function criarEnderecos(int $clienteId): void
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
            // Criar tipo de endereço padrão
            $tipoEnderecoId = $this->db->inserir('tipos_enderecos', [
                'nome' => 'Comercial',
                'cadastrado_em' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $tipoEnderecoId = $tipoEndereco['id'];
        }

        $dados = [
            'cliente_id' => $clienteId,
            'tipo_endereco_id' => $tipoEnderecoId,
            'logradouro' => $this->faker->streetName,
            'numero' => $this->faker->buildingNumber,
            'complemento' => $this->faker->optional(0.3)->secondaryAddress,
            'bairro' => $this->faker->randomElement([
                'Centro',
                'Jardim das Flores',
                'Vila Nova',
                'Parque Industrial',
                'Bela Vista',
                'Alto da Boa Vista',
                'Distrito Industrial',
                'Vila Comercial'
            ]),
            'cidade_id' => $cidade['id'],
            'estado_id' => $cidade['estado_id'],
            'cep' => $this->generateCEP(),
            'principal' => 1,
            'cadastrado_em' => date('Y-m-d H:i:s'),
        ];

        $this->db->inserir('clientes_enderecos', $dados);
    }

    public function setQuantidade(int $quantidade): self
    {
        $this->quantidade = $quantidade;
        return $this;
    }
}
