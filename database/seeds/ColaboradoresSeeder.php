<?php

namespace Database\Seeds;

require_once __DIR__ . '/BaseSeeder.php';

/**
 * Seeder para popular colaboradores
 */
class ColaboradoresSeeder extends BaseSeeder
{
    private int $quantidade = 20;

    public function run(): void
    {
        $this->info("Iniciando seed de Colaboradores...");

        // Buscar níveis de colaborador disponíveis
        $niveis = $this->db->buscarTodos("SELECT id FROM colaborador_niveis WHERE deletado_em IS NULL", []);

        if (empty($niveis)) {
            $this->warning("Nenhum nível de colaborador encontrado. Criando níveis padrão...");
            $this->criarNiveisPadrao();
            $niveis = $this->db->buscarTodos("SELECT id FROM colaborador_niveis WHERE deletado_em IS NULL", []);
        }

        if (empty($niveis)) {
            $this->error("Não foi possível criar níveis de colaborador");
            return;
        }

        $this->info("Populando {$this->quantidade} colaboradores...");

        for ($i = 0; $i < $this->quantidade; $i++) {
            $this->criarColaborador($niveis);
        }

        $this->success("{$this->quantidade} colaboradores criados com sucesso!");
    }

    private function criarNiveisPadrao(): void
    {
        $niveis = [
            ['nome' => 'Administrador', 'nivel' => 1],
            ['nome' => 'Gerente', 'nivel' => 2],
            ['nome' => 'Supervisor', 'nivel' => 3],
            ['nome' => 'Vendedor', 'nivel' => 4],
            ['nome' => 'Atendente', 'nivel' => 5],
        ];

        foreach ($niveis as $nivel) {
            $this->db->inserir('colaborador_niveis', [
                'nome' => $nivel['nome'],
                'nivel' => $nivel['nivel'],
                'cadastrado_em' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->success("Níveis de colaborador criados");
    }

    private function criarColaborador(array $niveis): void
    {
        $sexo = $this->faker->randomElement(['M', 'F']);
        $nome = $sexo === 'M' ? $this->faker->firstNameMale : $this->faker->firstNameFemale;
        $sobrenome = $this->faker->lastName;
        $nomeCompleto = "$nome $sobrenome";

        $email = strtolower(
            $this->removeAcento($nome) . '.' .
            $this->removeAcento($sobrenome) . '@' .
            $this->faker->randomElement(['ecletech.com', 'empresa.com.br', 'comercial.com.br'])
        );

        $nivel = $this->faker->randomElement($niveis);

        $dados = [
            'nome' => $nomeCompleto,
            'email' => $email,
            'cpf' => $this->generateCPF(),
            'rg' => $this->faker->numerify('##.###.###-#'),
            'telefone' => $this->faker->cellphone(false),
            'celular' => $this->faker->cellphone(false),
            'data_nascimento' => $this->faker->date('Y-m-d', '-25 years'),
            'sexo' => $sexo,
            'estado_civil' => $this->faker->randomElement(['Solteiro', 'Casado', 'Divorciado', 'Viúvo']),
            'cargo' => $this->faker->randomElement([
                'Vendedor',
                'Gerente de Vendas',
                'Atendente',
                'Supervisor',
                'Coordenador',
                'Assistente Administrativo',
                'Analista',
                'Auxiliar'
            ]),
            'setor' => $this->faker->randomElement([
                'Vendas',
                'Administrativo',
                'Financeiro',
                'Comercial',
                'Atendimento',
                'Logística',
                'Recursos Humanos'
            ]),
            'data_admissao' => $this->faker->date('Y-m-d', '-3 years'),
            'salario' => $this->faker->randomFloat(2, 1500, 8000),
            'comissao' => $this->faker->randomFloat(2, 0, 10),
            'nivel_id' => $nivel['id'],
            'ativo' => $this->faker->randomElement([1, 1, 1, 0]), // 75% ativos
            'senha' => password_hash('senha123', PASSWORD_DEFAULT),
            'observacoes' => $this->faker->optional(0.3)->sentence(),
            'cadastrado_em' => $this->randomDate('-2 years', 'now'),
        ];

        // Inserir colaborador
        $colaboradorId = $this->db->inserir('colaboradores', $dados);

        // Criar endereço para alguns colaboradores
        if ($this->faker->boolean(70)) {
            $this->criarEndereco($colaboradorId);
        }
    }

    private function criarEndereco(int $colaboradorId): void
    {
        // Buscar uma cidade aleatória
        $cidade = $this->db->buscarUm(
            "SELECT id, estado_id FROM cidades ORDER BY RAND() LIMIT 1",
            []
        );

        if (!$cidade) {
            return;
        }

        $dados = [
            'colaborador_id' => $colaboradorId,
            'logradouro' => $this->faker->streetName,
            'numero' => $this->faker->buildingNumber,
            'complemento' => $this->faker->optional(0.3)->secondaryAddress,
            'bairro' => $this->faker->randomElement([
                'Centro',
                'Jardim das Flores',
                'Vila Nova',
                'Parque Industrial',
                'Bela Vista',
                'Alto da Boa Vista'
            ]),
            'cidade_id' => $cidade['id'],
            'estado_id' => $cidade['estado_id'],
            'cep' => $this->generateCEP(),
            'cadastrado_em' => date('Y-m-d H:i:s'),
        ];

        $this->db->inserir('colaborador_enderecos', $dados);
    }

    private function removeAcento(string $texto): string
    {
        $acentos = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C',
        ];

        return strtr($texto, $acentos);
    }

    public function setQuantidade(int $quantidade): self
    {
        $this->quantidade = $quantidade;
        return $this;
    }
}
