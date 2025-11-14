<?php

/**
 * Script para executar todos os seeders e popular o banco de dados com dados fake
 *
 * Uso:
 * php executar_seeds.php
 * php executar_seeds.php --seeder=ClientesSeeder
 * php executar_seeds.php --quantidade=100
 */

// Carregar autoloader do Composer
require_once __DIR__ . '/vendor/autoload.php';

// Carregar configurações do ambiente
require_once __DIR__ . '/App/Core/CarregadorEnv.php';

use App\Core\CarregadorEnv;
use App\Core\BancoDados;

// Carregar variáveis de ambiente
$carregadorEnv = CarregadorEnv::obterInstancia();
$carregadorEnv->carregar(__DIR__ . '/.env');

// Importar seeders
require_once __DIR__ . '/database/seeds/BaseSeeder.php';
require_once __DIR__ . '/database/seeds/EstadosCidadesSeeder.php';
require_once __DIR__ . '/database/seeds/ColaboradoresSeeder.php';
require_once __DIR__ . '/database/seeds/ClientesSeeder.php';
require_once __DIR__ . '/database/seeds/FornecedoresSeeder.php';
require_once __DIR__ . '/database/seeds/ProdutosSeeder.php';
require_once __DIR__ . '/database/seeds/VendasSeeder.php';

use Database\Seeds\EstadosCidadesSeeder;
use Database\Seeds\ColaboradoresSeeder;
use Database\Seeds\ClientesSeeder;
use Database\Seeds\FornecedoresSeeder;
use Database\Seeds\ProdutosSeeder;
use Database\Seeds\VendasSeeder;

// Função para exibir banner
function exibirBanner(): void
{
    echo "\n";
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║                                                            ║\n";
    echo "║       ECLETECH - Sistema de Seeds para Banco de Dados     ║\n";
    echo "║                                                            ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";
}

// Função para exibir ajuda
function exibirAjuda(): void
{
    echo "Uso: php executar_seeds.php [opções]\n\n";
    echo "Opções:\n";
    echo "  --seeder=NOME         Executa apenas o seeder especificado\n";
    echo "  --quantidade=N        Define a quantidade de registros para seeders que suportam\n";
    echo "  --help                Exibe esta mensagem de ajuda\n\n";
    echo "Seeders disponíveis:\n";
    echo "  - EstadosCidadesSeeder\n";
    echo "  - ColaboradoresSeeder\n";
    echo "  - ClientesSeeder\n";
    echo "  - FornecedoresSeeder\n";
    echo "  - ProdutosSeeder\n";
    echo "  - VendasSeeder\n\n";
    echo "Exemplos:\n";
    echo "  php executar_seeds.php\n";
    echo "  php executar_seeds.php --seeder=ClientesSeeder\n";
    echo "  php executar_seeds.php --seeder=ClientesSeeder --quantidade=100\n";
    echo "\n";
}

// Função para formatar tempo
function formatarTempo(float $segundos): string
{
    if ($segundos < 60) {
        return number_format($segundos, 2) . ' segundos';
    }

    $minutos = floor($segundos / 60);
    $segundos = $segundos % 60;

    return "$minutos minutos e " . number_format($segundos, 2) . ' segundos';
}

// Processar argumentos da linha de comando
$opcoes = [];
$argumentos = array_slice($argv, 1);

foreach ($argumentos as $argumento) {
    if (strpos($argumento, '--') === 0) {
        $partes = explode('=', substr($argumento, 2));
        $chave = $partes[0];
        $valor = $partes[1] ?? true;
        $opcoes[$chave] = $valor;
    }
}

// Exibir banner
exibirBanner();

// Verificar se pediu ajuda
if (isset($opcoes['help'])) {
    exibirAjuda();
    exit(0);
}

try {
    // Testar conexão com banco de dados
    echo "[INFO] Testando conexão com banco de dados...\n";
    $db = BancoDados::obterInstancia();
    echo "[✓] Conexão estabelecida com sucesso!\n\n";

    // Configurar seeders
    $seeders = [];
    $quantidade = isset($opcoes['quantidade']) ? (int) $opcoes['quantidade'] : null;

    if (isset($opcoes['seeder'])) {
        // Executar apenas um seeder específico
        $nomeSeeder = $opcoes['seeder'];
        $classeSeeder = "Database\\Seeds\\$nomeSeeder";

        if (!class_exists($classeSeeder)) {
            throw new Exception("Seeder '$nomeSeeder' não encontrado");
        }

        $seeder = new $classeSeeder();

        // Configurar quantidade se suportado e fornecido
        if ($quantidade !== null && method_exists($seeder, 'setQuantidade')) {
            $seeder->setQuantidade($quantidade);
        }

        $seeders = [$nomeSeeder => $seeder];
    } else {
        // Executar todos os seeders na ordem correta
        $seeders = [
            'EstadosCidadesSeeder' => new EstadosCidadesSeeder(),
            'ColaboradoresSeeder' => new ColaboradoresSeeder(),
            'ClientesSeeder' => new ClientesSeeder(),
            'FornecedoresSeeder' => new FornecedoresSeeder(),
            'ProdutosSeeder' => new ProdutosSeeder(),
            'VendasSeeder' => new VendasSeeder(),
        ];

        // Configurar quantidade se fornecido
        if ($quantidade !== null) {
            foreach ($seeders as $seeder) {
                if (method_exists($seeder, 'setQuantidade')) {
                    $seeder->setQuantidade($quantidade);
                }
            }
        }
    }

    // Executar seeders
    $inicioTotal = microtime(true);
    $totalSeeders = count($seeders);
    $seederAtual = 0;

    echo "═══════════════════════════════════════════════════════════\n";
    echo "Executando " . ($totalSeeders === 1 ? '1 seeder' : "$totalSeeders seeders") . "...\n";
    echo "═══════════════════════════════════════════════════════════\n\n";

    foreach ($seeders as $nome => $seeder) {
        $seederAtual++;

        echo "\n┌───────────────────────────────────────────────────────────┐\n";
        echo "│ [$seederAtual/$totalSeeders] Executando $nome...\n";
        echo "└───────────────────────────────────────────────────────────┘\n";

        $inicio = microtime(true);

        try {
            $seeder->run();
            $tempo = microtime(true) - $inicio;
            echo "\n[✓] $nome concluído em " . formatarTempo($tempo) . "\n";
        } catch (Exception $e) {
            echo "\n[✗] Erro ao executar $nome: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        }
    }

    $tempoTotal = microtime(true) - $inicioTotal;

    echo "\n═══════════════════════════════════════════════════════════\n";
    echo "[✓] Todos os seeders foram executados!\n";
    echo "[⏱] Tempo total: " . formatarTempo($tempoTotal) . "\n";
    echo "═══════════════════════════════════════════════════════════\n\n";

    // Exibir estatísticas
    echo "Estatísticas do banco de dados:\n";
    echo "───────────────────────────────────────────────────────────\n";

    $tabelas = [
        'estados' => 'Estados',
        'cidades' => 'Cidades',
        'colaboradores' => 'Colaboradores',
        'clientes' => 'Clientes',
        'fornecedores' => 'Fornecedores',
        'produtos' => 'Produtos',
        'vendas' => 'Vendas',
        'vendas_itens' => 'Itens de Vendas',
    ];

    foreach ($tabelas as $tabela => $nome) {
        try {
            $resultado = $db->buscarUm("SELECT COUNT(*) as total FROM `$tabela`", []);
            $total = $resultado['total'] ?? 0;
            echo sprintf("  %-25s %s\n", $nome . ':', number_format($total, 0, ',', '.'));
        } catch (Exception $e) {
            echo sprintf("  %-25s %s\n", $nome . ':', 'N/A');
        }
    }

    echo "───────────────────────────────────────────────────────────\n\n";
    echo "[✓] Processo concluído com sucesso!\n\n";

} catch (Exception $e) {
    echo "\n[✗] ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
