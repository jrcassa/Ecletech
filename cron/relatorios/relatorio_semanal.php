<?php

/**
 * Cron Job: Processa relatórios semanais de abastecimentos
 * Execução: Toda segunda-feira às 8:00
 * Crontab: 0 8 * * 1 /usr/bin/php /path/to/Ecletech/cron/relatorio_semanal.php
 */

// Define o timezone
date_default_timezone_set('America/Sao_Paulo');

// Carrega o autoloader
require __DIR__ . '/../vendor/autoload.php';

use App\Helpers\ErrorLogger;

// Autoloader personalizado
spl_autoload_register(function ($classe) {
    $prefixo = 'App\\';
    $diretorioBase = __DIR__ . '/../App/';

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

try {
    echo "[" . date('Y-m-d H:i:s') . "] Iniciando processamento de relatórios semanais...\n";

    // Carrega as variáveis de ambiente
    $caminhoEnv = __DIR__ . '/../.env';
    $carregadorEnv = \App\Core\CarregadorEnv::obterInstancia();
    $carregadorEnv->carregar($caminhoEnv);

    // Instancia o service de relatórios
    $service = new \App\Services\FrotaAbastecimento\ServiceFrotaAbastecimentoRelatorio();

    // Processa envios automáticos para segunda-feira
    $totalEnviados = $service->processarEnviosAutomaticos('semanal', 'segunda');

    echo "[" . date('Y-m-d H:i:s') . "] Total de relatórios enviados: {$totalEnviados}\n";
    echo "[" . date('Y-m-d H:i:s') . "] Processamento concluído com sucesso!\n";

    exit(0);

} catch (\Exception $e) {
    ErrorLogger::log($e, [
        'tipo_erro' => 'cron',
        'nivel' => 'alto',
        'contexto' => [
            'cron_job' => 'relatorio_semanal',
            'descricao' => 'Erro ao processar relatórios semanais de abastecimento'
        ]
    ]);

    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] Trace: " . $e->getTraceAsString() . "\n";

    // Log do erro
    error_log("Erro no cron de relatório semanal: " . $e->getMessage());

    exit(1);
}
