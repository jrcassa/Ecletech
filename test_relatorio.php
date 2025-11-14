#!/usr/bin/env php
<?php
/**
 * Script de teste para diagnosticar o sistema de relatórios
 */

// Define o timezone
date_default_timezone_set('America/Sao_Paulo');

// Define diretório raiz
define('ROOT_PATH', __DIR__);

// Carrega o autoloader do Composer (se existir)
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require ROOT_PATH . '/vendor/autoload.php';
}

// Autoloader personalizado
spl_autoload_register(function ($classe) {
    $prefixo = 'App\\';
    $diretorioBase = ROOT_PATH . '/App/';

    $tamanho = strlen($prefixo);
    if (strncmp($prefixo, $classe, $tamanho) !== 0) {
        return;
    }

    $classeRelativa = substr($classe, $tamanho);
    $arquivo = $diretorioBase . str_replace('\\', '/', $classeRelativa) . '.php';

    if (file_exists($arquivo)) {
        require $arquivo;
    }
});

// Carrega variáveis de ambiente
$caminhoEnv = ROOT_PATH . '/.env';
$carregadorEnv = \App\Core\CarregadorEnv::obterInstancia();
$carregadorEnv->carregar($caminhoEnv);

echo "=== DIAGNÓSTICO DO SISTEMA DE RELATÓRIOS ===\n\n";

try {
    // 1. Verifica se há abastecimentos no sistema
    echo "1. Verificando abastecimentos...\n";
    $modelAbast = new \App\Models\FrotaAbastecimento\ModelFrotaAbastecimento();
    $abastecimentos = $modelAbast->listar(['limite' => 5]);
    echo "   Total de abastecimentos encontrados: " . count($abastecimentos) . "\n";

    if (!empty($abastecimentos)) {
        $primeiro = $abastecimentos[0];
        echo "   Último abastecimento: ID={$primeiro['id']}, Data={$primeiro['data_abastecimento']}\n";
    }
    echo "\n";

    // 2. Verifica configurações de relatórios
    echo "2. Verificando configurações de relatórios...\n";
    $modelConfig = new \App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoRelatorioConfiguracao();
    $configs = $modelConfig->listar(['ativo' => true]);
    echo "   Configurações ativas: " . count($configs) . "\n";

    foreach ($configs as $config) {
        echo "   - Colaborador ID: {$config['colaborador_id']}, Tipo: {$config['tipo_relatorio']}, Dia: " .
             ($config['tipo_relatorio'] === 'semanal' ? $config['dia_envio_semanal'] : $config['dia_envio_mensal']) . "\n";
    }
    echo "\n";

    // 3. Verifica snapshots existentes
    echo "3. Verificando snapshots...\n";
    $modelSnapshot = new \App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoRelatorioSnapshot();
    $snapshots = $modelSnapshot->buscarHistorico('semanal', 5);
    echo "   Snapshots semanais: " . count($snapshots) . "\n";

    if (!empty($snapshots)) {
        $ultimo = $snapshots[0];
        echo "   Último snapshot: {$ultimo['periodo_inicio']} a {$ultimo['periodo_fim']}\n";
    }
    echo "\n";

    // 4. Verifica logs de envio
    echo "4. Verificando logs de envio...\n";
    $modelLog = new \App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoRelatorioLog();
    $logs = $modelLog->listar(['limite' => 5]);
    echo "   Total de logs: " . count($logs) . "\n";

    foreach ($logs as $log) {
        echo "   - ID: {$log['id']}, Status: {$log['status_envio']}, Período: {$log['periodo_inicio']} a {$log['periodo_fim']}\n";
    }
    echo "\n";

    // 5. Testa geração de relatório (sem enviar)
    echo "5. Testando geração de relatório...\n";
    $service = new \App\Services\FrotaAbastecimento\ServiceFrotaAbastecimentoRelatorio();

    // Período da última semana
    $fim = new DateTime();
    $inicio = clone $fim;
    $inicio->modify('-7 days');

    echo "   Período: {$inicio->format('Y-m-d')} a {$fim->format('Y-m-d')}\n";

    try {
        $relatorio = $service->gerarRelatorioManual(
            'semanal',
            $inicio->format('Y-m-d'),
            $fim->format('Y-m-d'),
            'detalhado'
        );

        echo "   ✓ Relatório gerado com sucesso!\n";
        echo "   Total de abastecimentos no período: {$relatorio['dados']['total_abastecimentos']}\n";
        echo "   Total de litros: {$relatorio['dados']['total_litros']}\n";
        echo "   Total em R$: {$relatorio['dados']['total_valor']}\n";
        echo "\n   Prévia da mensagem:\n";
        echo "   " . str_repeat('-', 60) . "\n";
        $linhas = explode("\n", $relatorio['mensagem']);
        foreach (array_slice($linhas, 0, 10) as $linha) {
            echo "   $linha\n";
        }
        if (count($linhas) > 10) {
            echo "   ... (+" . (count($linhas) - 10) . " linhas)\n";
        }
        echo "   " . str_repeat('-', 60) . "\n";

    } catch (\Exception $e) {
        echo "   ✗ Erro ao gerar relatório: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 6. Verifica configuração do WhatsApp
    echo "6. Verificando configuração do WhatsApp...\n";
    $serviceWhatsapp = new \App\Services\Whatsapp\ServiceWhatsapp();
    $status = $serviceWhatsapp->verificarConfiguracao();

    echo "   Configurado: " . ($status['configurado'] ? 'SIM' : 'NÃO') . "\n";
    echo "   API URL: " . ($status['api_url_configurada'] ? 'OK' : 'NÃO CONFIGURADA') . "\n";
    echo "   Token: " . ($status['token_configurado'] ? 'OK' : 'NÃO CONFIGURADO') . "\n";

    if (!$status['configurado']) {
        echo "   ⚠ ATENÇÃO: WhatsApp não está configurado!\n";
        echo "   {$status['mensagem']}\n";
    }
    echo "\n";

    echo "=== FIM DO DIAGNÓSTICO ===\n";

} catch (\Exception $e) {
    echo "\n✗ ERRO FATAL: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
