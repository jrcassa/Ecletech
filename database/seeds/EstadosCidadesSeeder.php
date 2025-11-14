<?php

namespace Database\Seeds;

require_once __DIR__ . '/BaseSeeder.php';

/**
 * Seeder para popular estados e cidades brasileiras
 */
class EstadosCidadesSeeder extends BaseSeeder
{
    private array $estadosBrasileiros = [
        ['sigla' => 'AC', 'nome' => 'Acre'],
        ['sigla' => 'AL', 'nome' => 'Alagoas'],
        ['sigla' => 'AP', 'nome' => 'Amapá'],
        ['sigla' => 'AM', 'nome' => 'Amazonas'],
        ['sigla' => 'BA', 'nome' => 'Bahia'],
        ['sigla' => 'CE', 'nome' => 'Ceará'],
        ['sigla' => 'DF', 'nome' => 'Distrito Federal'],
        ['sigla' => 'ES', 'nome' => 'Espírito Santo'],
        ['sigla' => 'GO', 'nome' => 'Goiás'],
        ['sigla' => 'MA', 'nome' => 'Maranhão'],
        ['sigla' => 'MT', 'nome' => 'Mato Grosso'],
        ['sigla' => 'MS', 'nome' => 'Mato Grosso do Sul'],
        ['sigla' => 'MG', 'nome' => 'Minas Gerais'],
        ['sigla' => 'PA', 'nome' => 'Pará'],
        ['sigla' => 'PB', 'nome' => 'Paraíba'],
        ['sigla' => 'PR', 'nome' => 'Paraná'],
        ['sigla' => 'PE', 'nome' => 'Pernambuco'],
        ['sigla' => 'PI', 'nome' => 'Piauí'],
        ['sigla' => 'RJ', 'nome' => 'Rio de Janeiro'],
        ['sigla' => 'RN', 'nome' => 'Rio Grande do Norte'],
        ['sigla' => 'RS', 'nome' => 'Rio Grande do Sul'],
        ['sigla' => 'RO', 'nome' => 'Rondônia'],
        ['sigla' => 'RR', 'nome' => 'Roraima'],
        ['sigla' => 'SC', 'nome' => 'Santa Catarina'],
        ['sigla' => 'SP', 'nome' => 'São Paulo'],
        ['sigla' => 'SE', 'nome' => 'Sergipe'],
        ['sigla' => 'TO', 'nome' => 'Tocantins'],
    ];

    private array $cidadesPrincipais = [
        'SP' => ['São Paulo', 'Campinas', 'Santos', 'São José dos Campos', 'Ribeirão Preto', 'Sorocaba'],
        'RJ' => ['Rio de Janeiro', 'Niterói', 'Duque de Caxias', 'Nova Iguaçu', 'Petrópolis', 'Volta Redonda'],
        'MG' => ['Belo Horizonte', 'Uberlândia', 'Contagem', 'Juiz de Fora', 'Betim', 'Montes Claros'],
        'RS' => ['Porto Alegre', 'Caxias do Sul', 'Pelotas', 'Canoas', 'Santa Maria', 'Gravataí'],
        'BA' => ['Salvador', 'Feira de Santana', 'Vitória da Conquista', 'Camaçari', 'Juazeiro', 'Ilhéus'],
        'PR' => ['Curitiba', 'Londrina', 'Maringá', 'Ponta Grossa', 'Cascavel', 'Foz do Iguaçu'],
        'CE' => ['Fortaleza', 'Caucaia', 'Juazeiro do Norte', 'Maracanaú', 'Sobral', 'Crato'],
        'PE' => ['Recife', 'Jaboatão dos Guararapes', 'Olinda', 'Caruaru', 'Petrolina', 'Cabo de Santo Agostinho'],
        'SC' => ['Florianópolis', 'Joinville', 'Blumenau', 'São José', 'Criciúma', 'Chapecó'],
        'GO' => ['Goiânia', 'Aparecida de Goiânia', 'Anápolis', 'Rio Verde', 'Luziânia', 'Águas Lindas de Goiás'],
        'PB' => ['João Pessoa', 'Campina Grande', 'Santa Rita', 'Patos', 'Bayeux', 'Sousa'],
        'ES' => ['Vitória', 'Vila Velha', 'Serra', 'Cariacica', 'Cachoeiro de Itapemirim', 'Linhares'],
        'AM' => ['Manaus', 'Parintins', 'Itacoatiara', 'Manacapuru', 'Coari', 'Tefé'],
        'MA' => ['São Luís', 'Imperatriz', 'São José de Ribamar', 'Timon', 'Caxias', 'Codó'],
        'RN' => ['Natal', 'Mossoró', 'Parnamirim', 'São Gonçalo do Amarante', 'Macaíba', 'Ceará-Mirim'],
        'AL' => ['Maceió', 'Arapiraca', 'Rio Largo', 'Palmeira dos Índios', 'União dos Palmares', 'Penedo'],
        'PI' => ['Teresina', 'Parnaíba', 'Picos', 'Piripiri', 'Floriano', 'Campo Maior'],
        'DF' => ['Brasília', 'Taguatinga', 'Ceilândia', 'Samambaia', 'Planaltina', 'Gama'],
        'MT' => ['Cuiabá', 'Várzea Grande', 'Rondonópolis', 'Sinop', 'Tangará da Serra', 'Cáceres'],
        'MS' => ['Campo Grande', 'Dourados', 'Três Lagoas', 'Corumbá', 'Ponta Porã', 'Aquidauana'],
        'SE' => ['Aracaju', 'Nossa Senhora do Socorro', 'Lagarto', 'Itabaiana', 'Estância', 'Simão Dias'],
        'RO' => ['Porto Velho', 'Ji-Paraná', 'Ariquemes', 'Cacoal', 'Vilhena', 'Jaru'],
        'AC' => ['Rio Branco', 'Cruzeiro do Sul', 'Sena Madureira', 'Tarauacá', 'Feijó', 'Senador Guiomard'],
        'AP' => ['Macapá', 'Santana', 'Laranjal do Jari', 'Oiapoque', 'Porto Grande', 'Mazagão'],
        'RR' => ['Boa Vista', 'Rorainópolis', 'Caracaraí', 'Mucajaí', 'Alto Alegre', 'Bonfim'],
        'TO' => ['Palmas', 'Araguaína', 'Gurupi', 'Porto Nacional', 'Paraíso do Tocantins', 'Colinas do Tocantins'],
        'PA' => ['Belém', 'Ananindeua', 'Santarém', 'Marabá', 'Castanhal', 'Parauapebas'],
    ];

    public function run(): void
    {
        $this->info("Iniciando seed de Estados e Cidades...");

        // Verifica se as tabelas já têm dados
        $totalEstados = $this->count('estados');
        $totalCidades = $this->count('cidades');

        if ($totalEstados > 0 || $totalCidades > 0) {
            $this->warning("Já existem $totalEstados estados e $totalCidades cidades cadastrados");
            $this->info("Pulando seed de Estados e Cidades");
            return;
        }

        // Popular estados
        $this->popularEstados();

        // Popular cidades
        $this->popularCidades();

        $this->success("Seed de Estados e Cidades concluído!");
    }

    private function popularEstados(): void
    {
        $this->info("Populando estados brasileiros...");

        foreach ($this->estadosBrasileiros as $estado) {
            $this->db->inserir('estados', [
                'nome' => $estado['nome'],
                'sigla' => $estado['sigla'],
                'cadastrado_em' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->success("27 estados cadastrados com sucesso");
    }

    private function popularCidades(): void
    {
        $this->info("Populando cidades brasileiras...");

        $totalCidades = 0;

        foreach ($this->cidadesPrincipais as $siglaEstado => $cidades) {
            // Buscar ID do estado
            $estado = $this->db->buscarUm(
                "SELECT id FROM estados WHERE sigla = ?",
                [$siglaEstado]
            );

            if (!$estado) {
                $this->warning("Estado $siglaEstado não encontrado");
                continue;
            }

            foreach ($cidades as $nomeCidade) {
                $this->db->inserir('cidades', [
                    'nome' => $nomeCidade,
                    'estado_id' => $estado['id'],
                    'cadastrado_em' => date('Y-m-d H:i:s'),
                ]);
                $totalCidades++;
            }
        }

        $this->success("$totalCidades cidades cadastradas com sucesso");
    }
}
