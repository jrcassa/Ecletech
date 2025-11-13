<?php

/**
 * Cron Job: Processa relatórios mensais de abastecimentos
 * Execução: Todo dia 1 de cada mês às 8:00
 * Crontab: 0 8 1 * * /usr/bin/php /path/to/Ecletech/cron/relatorio_mensal.php
 */

// Define o timezone
date_default_timezone_set('America/Sao_Paulo');

// Define diretório raiz
define('ROOT_PATH', dirname(__DIR__, 2));

// Carrega o autoloader do Composer (se existir)
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require ROOT_PATH . '/vendor/autoload.php';
}

use App\Helpers\ErrorLogger;

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

try {
    echo "[" . date('Y-m-d H:i:s') . "] Iniciando processamento de relatórios mensais...\n";

    // Carrega as variáveis de ambiente
    $caminhoEnv = ROOT_PATH . '/.env';
    $carregadorEnv = \App\Core\CarregadorEnv::obterInstancia();
    $carregadorEnv->carregar($caminhoEnv);

    // Instancia o service de relatórios
    $service = new \App\Services\FrotaAbastecimento\ServiceFrotaAbastecimentoRelatorio();

    // Processa envios automáticos para dia 1
    $diaAtual = date('j');
    $totalEnviados = $service->processarEnviosAutomaticos('mensal', $diaAtual);

    echo "[" . date('Y-m-d H:i:s') . "] Total de relatórios enviados: {$totalEnviados}\n";
    echo "[" . date('Y-m-d H:i:s') . "] Processamento concluído com sucesso!\n";

    exit(0);

} catch (\Exception $e) {
    ErrorLogger::log($e, [
        'tipo_erro' => 'cron',
        'nivel' => 'alto',
        'contexto' => [
            'cron_job' => 'relatorio_mensal',
            'descricao' => 'Erro ao processar relatórios mensais de abastecimento'
        ]
    ]);

    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] Trace: " . $e->getTraceAsString() . "\n";

    // Log do erro
    error_log("Erro no cron de relatório mensal: " . $e->getMessage());

    exit(1);
}
